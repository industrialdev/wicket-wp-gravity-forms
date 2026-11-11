<?php

declare(strict_types=1);

namespace WicketGF;

use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * MDP Sync Engine.
 *
 * Hooks into gform_after_submission to schedule async background processing
 * of mapped field values. Collects, groups by target object/endpoint,
 * builds PATCH payloads, and pushes to the Wicket MDP API via WP-Cron.
 *
 * Stores sync result as entry meta for traceability.
 * Flow: after_submission → PENDING entry meta → wp_schedule_single_event
 *       → cron callback → collect values → push to MDP → SUCCESS/FAILED meta.
 */
class MdpSyncEngine
{
    /**
     * Entry meta key for sync status.
     */
    private const META_KEY = 'wicket_mdp_sync_status';

    /**
     * Sync status values.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Target objects that belong to each entity type.
     *
     * A mapping whose target object is not in the entity's list would write
     * person attributes to an organization record (or vice versa).
     */
    public const OBJECTS_BY_ENTITY = [
        'person' => ['person_profile', 'additional_info', 'preferences', 'addresses', 'phones', 'emails', 'web_addresses'],
        'organization' => ['org_profile', 'org_additional_info', 'addresses', 'phones', 'emails', 'web_addresses'],
    ];

    /**
     * Contact resource target objects (WWID-2981).
     *
     * These are MDP related resources, not PATCHable attributes: they are
     * written through their own endpoints (see push_contacts_to_mdp()) and
     * never ride the profile PATCH body.
     */
    private const CONTACT_OBJECTS = ['addresses', 'phones', 'emails', 'web_addresses'];

    /**
     * GF address sub-input position => MDP address attribute.
     *
     * GF fixes these input ids across address layouts: .1 street, .2 street 2,
     * .3 city, .4 state, .5 zip, .6 country.
     */
    private const ADDRESS_INPUT_MAP = [1 => 'address1', 2 => 'address2', 3 => 'city', 4 => 'state_name', 5 => 'zip_code', 6 => 'country_code'];

    /**
     * GF Canadian province names (no code helper exists in GF) to Carmen
     * subregion codes, which the MDP requires for CA/US state values. English
     * and French labels both map: GF translates province names, and Canadian
     * tenants submit FR labels.
     */
    private const CA_PROVINCE_CODES = [
        'alberta' => 'AB',
        'british columbia' => 'BC',
        'colombie-britannique' => 'BC',
        'manitoba' => 'MB',
        'new brunswick' => 'NB',
        'nouveau-brunswick' => 'NB',
        'newfoundland and labrador' => 'NL',
        'terre-neuve-et-labrador' => 'NL',
        'northwest territories' => 'NT',
        'territoires du nord-ouest' => 'NT',
        'nova scotia' => 'NS',
        'nouvelle-ecosse' => 'NS',
        'nunavut' => 'NU',
        'ontario' => 'ON',
        'prince edward island' => 'PE',
        'ile-du-prince-edouard' => 'PE',
        'quebec' => 'QC',
        'saskatchewan' => 'SK',
        'yukon' => 'YT',
    ];

    /**
     * Field discovery service.
     *
     * @var MdpFieldDiscovery
     */
    private MdpFieldDiscovery $discovery;

    public function __construct(MdpFieldDiscovery $discovery)
    {
        $this->discovery = $discovery;
    }

    /**
     * Cron hook name for async MDP sync processing.
     */
    public const CRON_HOOK = 'wicket_gf_mdp_sync_process';

    /**
     * Transient lock so concurrent admin requests do not drain the same
     * overdue sync events twice.
     */
    private const DRAIN_LOCK = 'wicket_gf_mdp_sync_drain_lock';

    /**
     * Max due events drained per admin request. The rest stay due and the
     * next request continues, so a backlog never stretches one response.
     */
    private const DRAIN_BATCH_LIMIT = 5;

    /**
     * Register hooks: after_submission (schedules async) + cron callback.
     */
    public function register(): void
    {
        add_action('gform_after_submission', [$this, 'schedule_sync'], 10, 2);
        add_action(self::CRON_HOOK, [$this, 'process_scheduled_sync'], 10, 1);
        add_action('admin_init', [$this, 'process_overdue_syncs']);
    }

    /**
     * Push mapped values to MDP after form submission.
     *
     * Default is a synchronous push inside the submission request, with the
     * terminal status written before the response. The
     * wicket_gf_mdp_sync_async filter opts a site into the WP-Cron deferred
     * path instead; if scheduling then fails, processing still falls back
     * to synchronous.
     *
     * @param array $entry The GF entry object.
     * @param array $form  The GF form object.
     */
    public function schedule_sync(array $entry, array $form): void
    {
        $entry_id = (int) ($entry['id'] ?? 0);
        $form_id = (int) ($form['id'] ?? 0);
        $form_config = $this->get_form_config($form);
        $log_ctx = ['form_id' => $form_id, 'entity_type' => $form_config['entity_type']];

        if (!$this->is_sync_eligible($form_config)) {
            // Forms with no MDP-enabled fields stay silent: no meta noise on
            // plain submissions. A half-configured form gets a visible skip.
            if ($this->form_has_mapped_fields($form)) {
                $this->record_status($entry_id, self::STATUS_SKIPPED, 'Missing required form-level MDP config', $log_ctx);
            }

            return;
        }

        $mapped_values = $this->collect_mapped_values($form, $entry);

        if (empty($mapped_values)) {
            $this->record_status($entry_id, self::STATUS_SKIPPED, 'No mapped fields with values', $log_ctx);

            return;
        }

        // Per-field UUID sources (WWID-2968): group mapped values by the UUID
        // each field resolved to. All-or-nothing: any unresolvable or
        // unconfigured source fails the entry before a partial write can
        // reach the API.
        $built = $this->build_uuid_groups($mapped_values);

        if ($built['error'] !== null) {
            $this->record_status($entry_id, self::STATUS_FAILED, $built['error'], $log_ctx);

            return;
        }

        $payload_groups = $built['groups'];
        $log_ctx['uuid'] = count($payload_groups) === 1 ? $payload_groups[0]['uuid'] : '';

        // Prepare minimal payload for the cron job
        $payload = [
            'entry_id'    => $entry_id,
            'form_id'     => $form_id,
            'entity_type' => $form_config['entity_type'],
            'groups'      => $payload_groups,
        ];

        // Synchronous push is the default: submission requests complete with
        // the terminal status already recorded, with no WP-Cron dependency.
        // The filter opts a site into the deferred cron path instead.
        $async = apply_filters('wicket_gf_mdp_sync_async', false, $form, $entry);

        if ($async) {
            // Record PENDING status immediately for UI visibility
            $this->record_status($entry_id, self::STATUS_PENDING, 'Scheduled for async MDP sync', $log_ctx);

            if (wp_schedule_single_event(time(), self::CRON_HOOK, [$payload]) !== false) {
                return;
            }
        }

        // Synchronous path: scheduling failed, or async disabled by filter.
        // Started/terminal log pair: a "starting" line with no terminal after
        // it means the request died mid-push.
        if ($async) {
            $this->log_line('warning', 'Async scheduling failed; falling back to synchronous push.', $log_ctx + ['entry_id' => $entry_id]);
        }

        $this->log_line('debug', 'MDP sync push starting (synchronous).', $log_ctx);

        $results = $this->push_groups($form_config['entity_type'], $payload_groups);
        $this->record_sync_results($entry_id, $results, $log_ctx);
    }

    /**
     * Normalize a cron payload into the per-UUID groups shape.
     *
     * Events scheduled before the per-field UUID change carry the single-UUID
     * shape (uuid + grouped); they are converted so a deploy mid-flight never
     * turns a pending sync into a failed one.
     *
     * @return array<int, array{uuid: string, grouped: array}>
     */
    private function normalize_payload_groups(array $payload): array
    {
        if (!empty($payload['groups']) && is_array($payload['groups'])) {
            return $payload['groups'];
        }

        $uuid = (string) ($payload['uuid'] ?? '');
        $grouped = $payload['grouped'] ?? [];

        if ($uuid === '' || !is_array($grouped) || $grouped === []) {
            return [];
        }

        return [['uuid' => $uuid, 'grouped' => $grouped]];
    }

    /**
     * Push every UUID group to the MDP API and aggregate the results.
     *
     * @param string $entity_type 'person' or 'organization'.
     * @param array  $groups      List of {uuid, grouped} patches.
     * @return array{success: bool, message: string, objects: array<string, bool>}
     */
    private function push_groups(string $entity_type, array $groups): array
    {
        $objects = [];
        $success = true;
        $failures = [];
        $endpoint_prefix = $entity_type === 'organization' ? 'organizations' : 'people';

        foreach ($groups as $group) {
            $uuid = (string) ($group['uuid'] ?? '');
            $result = $this->push_to_mdp($entity_type, $uuid, (array) ($group['grouped'] ?? []));

            foreach ((array) ($result['objects'] ?? []) as $obj => $ok) {
                $objects[$obj] = ($objects[$obj] ?? true) && $ok;
            }

            if (empty($result['success'])) {
                $success = false;
                // Name each failed record: one entry can patch several UUIDs.
                $failures[] = sprintf('%s/%s: %s', $endpoint_prefix, $uuid, (string) ($result['message'] ?? 'MDP sync failed'));
            }
        }

        $message = $success ? 'MDP sync successful' : implode(' | ', $failures);

        return ['success' => $success, 'message' => mb_substr($message, 0, 2000), 'objects' => $objects];
    }

    /**
     * Cron callback: process a scheduled MDP sync.
     *
     * @param array $payload Scheduled sync data.
     */
    public function process_scheduled_sync(array $payload): void
    {
        $entry_id = (int) ($payload['entry_id'] ?? 0);
        $form_id = (int) ($payload['form_id'] ?? 0);
        $entity_type = (string) ($payload['entity_type'] ?? '');
        $groups = $this->normalize_payload_groups($payload);

        $log_ctx = ['form_id' => $form_id, 'entity_type' => $entity_type];

        if ($entry_id <= 0 || empty($groups)) {
            $this->record_status($entry_id, self::STATUS_FAILED, 'Invalid scheduled sync payload', $log_ctx);

            return;
        }

        $log_ctx['uuid'] = count($groups) === 1 ? $groups[0]['uuid'] : '';

        // Started/terminal log pair: a "starting" line with no terminal after
        // it means the cron run died mid-push.
        $this->log_line('debug', 'MDP sync push starting (cron).', $log_ctx);

        $results = $this->push_groups($entity_type, $groups);
        $this->record_sync_results($entry_id, $results, $log_ctx);
    }

    /**
     * Drain sync events that WP-Cron never ran.
     *
     * Common pattern: DISABLE_WP_CRON=true plus an external trigger hitting
     * wp-cron.php. When that trigger is missing or late, nothing executes
     * cron events and scheduled syncs sit at PENDING forever. Admin requests
     * pick up events that are due now and process them inline, so a
     * submission is never stranded behind a dead cron pipeline.
     *
     * The core doing_cron lock is honored: an external wp-cron.php run in
     * flight owns the queue.
     *
     * Runs only on real wp-admin page loads by managers. admin-ajax fires
     * admin_init for anonymous traffic too (heartbeat, front-end handlers);
     * queue reads and blocking MDP pushes never happen in those requests.
     * admin-post.php nopriv handlers also reach admin_init without
     * DOING_AJAX, so the capability check is what closes them: never drop
     * it believing the ajax check covers anonymous traffic.
     */
    public function process_overdue_syncs(): void
    {
        if (wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }

        if (get_transient('doing_cron')) {
            $this->log_line('debug', 'MDP sync drain skipped: a cron run is in flight.');

            return;
        }

        if (get_transient(self::DRAIN_LOCK)) {
            $this->log_line('debug', 'MDP sync drain skipped: another drain holds the lock.');

            return;
        }

        $due = array_slice(self::due_sync_events(), 0, self::DRAIN_BATCH_LIMIT);
        if (empty($due)) {
            return;
        }

        set_transient(self::DRAIN_LOCK, 1, 5 * MINUTE_IN_SECONDS);

        $processed = 0;

        foreach ($due as $event) {
            $args = $event['args'] ?? [];

            // Claim before firing. Core removes an event from the queue
            // before running it, so matching that order closes the window
            // where a concurrent wp-cron.php run would fire it a second time.
            $removed = wp_clear_scheduled_hook(self::CRON_HOOK, $args);

            if ($removed === false) {
                // A real update_option failure, distinct from losing a race.
                // The event stays queued; the next drain retries it.
                $this->log_line('warning', 'MDP sync cron event could not be unscheduled; will retry on the next admin request.');
                continue;
            }

            if (!$removed) {
                // A real cron run claimed it first.
                $this->log_line('debug', 'MDP sync cron event was claimed by a concurrent run; skipped.');
                continue;
            }

            $payload = $args[0] ?? null;

            if (!is_array($payload)) {
                $this->log_line('warning', 'MDP sync cron event had a malformed payload; dropped.', ['payload_type' => gettype($payload)]);
                continue;
            }

            $this->process_scheduled_sync($payload);
            $processed++;
        }

        delete_transient(self::DRAIN_LOCK);

        if ($processed > 0) {
            $this->log_line('info', 'MDP sync drain processed overdue event(s).', ['count' => $processed]);
        }
    }

    /**
     * Count of due sync events (consumed by the admin cron notice).
     */
    public static function count_due_syncs(): int
    {
        return count(self::due_sync_events());
    }

    /**
     * Sync events due right now, flattened across timestamps.
     *
     * wp_get_ready_cron_jobs() returns [timestamp => [hook => [key => event]]],
     * not a hook-keyed array.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function due_sync_events(): array
    {
        $events = [];

        foreach (wp_get_ready_cron_jobs() as $cronhooks) {
            foreach ($cronhooks[self::CRON_HOOK] ?? [] as $event) {
                if (is_array($event)) {
                    $events[] = $event;
                }
            }
        }

        return $events;
    }

    /**
     * Write to the centralized Wicket log (Wicket()->log()).
     *
     * Falls back silently if Wicket() is not available.
     */
    private function log_line(string $level, string $message, array $context = []): void
    {
        if (!function_exists('Wicket')) {
            return;
        }

        Wicket()->log()->{$level}($message, $context + ['source' => 'wicket-gf-mdp-sync']);
    }

    /**
     * Synchronous processing entry point (kept for direct calls / tests).
     *
     * @param array $entry The GF entry object.
     * @param array $form  The GF form object.
     */
    public function process_submission(array $entry, array $form): void
    {
        $entry_id = (int) ($entry['id'] ?? 0);
        $form_config = $this->get_form_config($form);

        if (!$this->is_sync_eligible($form_config)) {
            // Same noise guard as schedule_sync(): plain forms stay silent.
            if ($this->form_has_mapped_fields($form)) {
                $this->record_status($entry_id, self::STATUS_SKIPPED, 'Missing required form-level MDP config');
            }

            return;
        }

        $mapped_values = $this->collect_mapped_values($form, $entry);

        if (empty($mapped_values)) {
            $this->record_status($entry_id, self::STATUS_SKIPPED, 'No mapped fields with values');

            return;
        }

        $groups = $this->build_uuid_groups($mapped_values);

        if ($groups['error'] !== null) {
            $this->record_status($entry_id, self::STATUS_FAILED, $groups['error']);

            return;
        }

        $results = $this->push_groups($form_config['entity_type'], $groups['groups']);
        $this->record_sync_results($entry_id, $results);
    }

    /**
     * Extract form-level MDP config.
     *
     * The UUID source is a per-field setting (WWID-2968); the form-level
     * wicket_mdp_uuid_source_field value only survives as a legacy fallback
     * read by resolve_field_uuid_source().
     *
     * @param array $form GF form object.
     * @return array{entity_type: string}
     */
    protected function get_form_config(array $form): array
    {
        return [
            'entity_type' => $form['wicket_mdp_entity_type'] ?? '',
        ];
    }

    /**
     * Whether any field on the form has MDP mapping enabled.
     *
     * @param array $form GF form object.
     */
    protected function form_has_mapped_fields(array $form): bool
    {
        foreach ($form['fields'] ?? [] as $field) {
            if (is_object($field) && !empty($field->wicket_enable_mdp_mapping)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if form is configured for MDP sync.
     *
     * UUID sources are validated per field at collection time.
     */
    protected function is_sync_eligible(array $config): bool
    {
        return $config['entity_type'] !== '';
    }

    /**
     * Collect mapped field values from the submission.
     *
     * Returns an array of mapped field data, each with:
     * - field_id: string (the GF field id)
     * - target_object: string
     * - target_field: string (e.g. 'attributes.given_name', 'data_field.custom-fields')
     * - value: mixed
     * - uuid: string (resolved from the field's own UUID source; '' when the
     *   source is unconfigured or holds no submitted value)
     * - uuid_source_field: string (the effective source field id, '' when none)
     *
     * @param array $form  GF form object.
     * @param array $entry GF entry object.
     * @return array<int, array{field_id: string, target_object: string, target_field: string, value: mixed, uuid: string, uuid_source_field: string}>
     */
    protected function collect_mapped_values(array $form, array $entry): array
    {
        $mapped = [];

        if (empty($form['fields'])) {
            return $mapped;
        }

        $entity_type = (string) ($form['wicket_mdp_entity_type'] ?? '');

        foreach ($form['fields'] as $field) {
            if (!is_object($field)) {
                continue;
            }

            $enabled = !empty($field->wicket_enable_mdp_mapping);
            if (!$enabled) {
                continue;
            }

            $target_object = (string) ($field->wicket_mdp_target_object ?? '');
            $target_field = (string) ($field->wicket_mdp_target_field ?? '');

            if ($target_object === '' || $target_field === '') {
                continue;
            }

            // Last-resort guard: a mapping whose target object does not belong
            // to the form's entity type would write person attributes to an
            // organization record (or vice versa). Never send it.
            if ($entity_type !== '' && !in_array($target_object, self::OBJECTS_BY_ENTITY[$entity_type] ?? [], true)) {
                $this->write_log(
                    (int) ($form['id'] ?? 0),
                    (int) ($entry['id'] ?? 0),
                    $entity_type,
                    '',
                    self::STATUS_SKIPPED,
                    sprintf('Field %s: target object %s does not belong to entity type %s', $field->id ?? '', $target_object, $entity_type)
                );
                continue;
            }

            // Get submitted value for this field
            $value = $this->get_field_value($field, $entry, $target_object, $target_field);
            if ($value === '' || $value === null) {
                continue;
            }

            // Per-field UUID source (WWID-2968). A mapping with no configured
            // source, or a configured source with an empty submitted value,
            // resolves to no record: it stays in the collection (uuid '') so
            // the entry fails visibly instead of silently losing the mapping.
            // A warning is logged naming the field for diagnostics.
            $uuid_source_field = $this->resolve_field_uuid_source($field, $form);

            if ($uuid_source_field === '') {
                $this->write_log(
                    (int) ($form['id'] ?? 0),
                    (int) ($entry['id'] ?? 0),
                    $entity_type,
                    '',
                    self::STATUS_SKIPPED,
                    sprintf('Field %s: no UUID source field configured; mapping skipped', $field->id ?? '')
                );
            }

            $mapped[] = [
                'field_id' => (string) ($field->id ?? ''),
                'target_object' => $target_object,
                'target_field' => $target_field,
                'value' => $value,
                'uuid' => $this->resolve_uuid($uuid_source_field, $entry),
                'uuid_source_field' => $uuid_source_field,
            ];
        }

        return $mapped;
    }

    /**
     * Get a field's submitted value from the entry.
     *
     * Uses GF's rgars() pattern for multi-input fields. Address targets read
     * GF address fields structurally (build_address_value()); every other
     * target gets a string.
     *
     * @param object $field         GF field object.
     * @param array  $entry         GF entry object.
     * @param string $target_object Target object key. Preferences targets and
     *                              checkbox fields take the first non-empty
     *                              input value instead of joining inputs: a
     *                              checkbox is a set of discrete choices, not
     *                              parts of one value.
     * @param string $target_field MDP target field value. Array-typed
     *                             data_field targets collect every submitted
     *                             value as a list instead of a join.
     * @return string|array|null
     */
    protected function get_field_value(object $field, array $entry, string $target_object = '', string $target_field = ''): mixed
    {
        $field_id = (string) ($field->id ?? '');
        if ($field_id === '') {
            return null;
        }

        if ($target_object === 'addresses' && ($field->type ?? '') === 'address') {
            return $this->build_address_value($field, $entry);
        }

        // Array data-field targets (WWID-2980): multi-select style schema
        // properties take every submitted value as a list, never a join.
        if ($this->data_field_target_is_array($target_object, $target_field)) {
            return $this->build_list_value($field, $entry, $field_id);
        }

        // Contact string targets: like checkbox/preference fields, a multi-
        // input string contact field (GF email confirmation is the common
        // case) must take its first non-empty input, never a joined string.
        $is_checkbox = ($field->type ?? '') === 'checkbox';
        $takes_first_input = $target_object === 'preferences'
            || $is_checkbox
            || in_array($target_object, ['phones', 'emails', 'web_addresses'], true);

        if (!empty($field->inputs) && is_array($field->inputs)) {
            $parts = [];
            foreach ($field->inputs as $input) {
                $input_id = (string) ($input['id'] ?? '');
                if ($input_id === '') {
                    continue;
                }
                $val = $entry[$input_id] ?? '';
                if ($val !== '') {
                    if ($takes_first_input) {
                        return (string) $val;
                    }
                    $parts[] = $val;
                }
            }

            return !empty($parts) ? implode(' ', $parts) : null;
        }

        // Single input field
        $value = $entry[$field_id] ?? '';

        // Handle array values (checkbox, multiselect)
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return $value !== '' ? (string) $value : null;
    }

    /**
     * Whether the mapping aims at an array-typed data_field property.
     *
     * The discovery type map carries the schema property types, so an
     * array-of-scalar property (WWID-2980) reads as 'array'.
     */
    protected function data_field_target_is_array(string $target_object, string $target_field): bool
    {
        if (!in_array($target_object, ['additional_info', 'org_additional_info'], true)) {
            return false;
        }

        if (!str_starts_with($target_field, 'data_field.')) {
            return false;
        }

        // Parity with merge_data_fields: a missing discovery (constructed
        // outside the normal path) must not be dereferenced.
        if (!isset($this->discovery)) {
            return false;
        }

        return ($this->discovery->getPropertyTypeMap()[$target_field] ?? '') === 'array';
    }

    /**
     * Collect every submitted value of a field as a list for an array
     * data_field target.
     *
     * Checkbox fields submit one entry key per choice; single-input fields
     * hold GF's joined storage (comma-joined by GF_Field_Multiselect, or JSON
     * when storageType is json).
     *
     * An empty list is meaningful: it clears the mapped MDP property on sync
     * (WWID-2980 review).
     *
     * @return list<string>
     */
    protected function build_list_value(object $field, array $entry, string $field_id): array
    {
        if (!empty($field->inputs) && is_array($field->inputs)) {
            $values = [];
            foreach ($field->inputs as $input) {
                $input_id = (string) ($input['id'] ?? '');
                if ($input_id === '') {
                    continue;
                }
                $val = $entry[$input_id] ?? '';
                if ($val !== '') {
                    $values[] = $val;
                }
            }

            return $this->string_list($values);
        }

        $raw = $entry[$field_id] ?? '';
        if (is_array($raw)) {
            return $this->string_list($raw);
        }

        $raw = (string) $raw;
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $this->string_list($decoded);
        }

        return $this->string_list(explode(',', $raw));
    }

    /**
     * Normalize collected values into a clean string list: non-strings drop
     * (programmatic entries can carry nested structures), values trim, and
     * empties vanish.
     *
     * @return list<string>
     */
    private function string_list(array $values): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $v): string => is_string($v) ? trim($v) : '',
                $values
            ),
            static fn (string $v): bool => $v !== ''
        ));
    }

    /**
     * Resolve a field's effective UUID source field id.
     *
     * The field-level selection wins; the form-level value is a legacy
     * fallback (WWID-2968) so forms configured before the change keep
     * syncing. Empty when neither is set.
     *
     * @param object $field GF field object.
     * @param array  $form  GF form object.
     */
    protected function resolve_field_uuid_source(object $field, array $form): string
    {
        $field_source = (string) ($field->wicket_mdp_uuid_source_field ?? '');

        if ($field_source !== '') {
            return $field_source;
        }

        return (string) ($form['wicket_mdp_uuid_source_field'] ?? '');
    }

    /**
     * Group mapped values by their resolved entity UUID.
     *
     * Fields sharing one UUID source (or carrying the same value) merge into
     * a single group and patch. Unresolvable sources collect under the '' key
     * so the caller can fail the entry before any partial write.
     *
     * @param array $mapped_values Collected mapped values.
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function group_by_uuid(array $mapped_values): array
    {
        $grouped = [];
        foreach ($mapped_values as $item) {
            $grouped[(string) ($item['uuid'] ?? '')][] = $item;
        }

        return $grouped;
    }

    /**
     * Group mapped values per resolved UUID, or fail the entry.
     *
     * Fields sharing one UUID source (or carrying the same value) merge into
     * a single group and patch. All-or-nothing: mappings with an unresolvable
     * or unconfigured source produce an error message instead of groups, so
     * no partial write can reach the API.
     *
     * @param array $mapped_values Collected mapped values.
     * @return array{error: string|null, groups: array<int, array{uuid: string, grouped: array}>}
     */
    private function build_uuid_groups(array $mapped_values): array
    {
        $grouped = $this->group_by_uuid($mapped_values);
        $unresolved = $grouped[''] ?? [];

        if (!empty($unresolved)) {
            return ['error' => $this->unresolvable_message($unresolved), 'groups' => []];
        }

        unset($grouped['']);

        $groups = [];
        foreach ($grouped as $uuid => $items) {
            $groups[] = [
                'uuid' => (string) $uuid,
                'grouped' => $this->group_by_target_object($items),
            ];
        }

        return ['error' => null, 'groups' => $groups];
    }

    /**
     * Build the failure message for mappings whose UUID cannot be resolved.
     *
     * @param array $unresolved Mapped values grouped under the '' UUID key.
     */
    private function unresolvable_message(array $unresolved): string
    {
        $empty_sources = [];
        $unconfigured = [];

        foreach ($unresolved as $item) {
            $source = (string) ($item['uuid_source_field'] ?? '');

            if ($source === '') {
                $unconfigured[] = (string) ($item['field_id'] ?? '?');
            } else {
                $empty_sources[] = $source;
            }
        }

        $parts = [];

        if ($empty_sources !== []) {
            $parts[] = sprintf(
                'source field %s was empty',
                implode(', ', array_values(array_unique($empty_sources)))
            );
        }

        if ($unconfigured !== []) {
            $parts[] = sprintf(
                'no UUID source field configured for mapped field(s) %s',
                implode(', ', array_values(array_unique($unconfigured)))
            );
        }

        return 'Could not resolve entity UUID: ' . implode('; ', $parts);
    }

    /**
     * Group mapped values by target object.
     *
     * @param array $mapped_values Collected mapped values.
     * @return array<string, array<int, array{target_field: string, value: mixed}>>
     */
    protected function group_by_target_object(array $mapped_values): array
    {
        $grouped = [];
        foreach ($mapped_values as $item) {
            $obj = $item['target_object'];
            if (!isset($grouped[$obj])) {
                $grouped[$obj] = [];
            }
            $grouped[$obj][] = [
                'target_field' => $item['target_field'],
                'value' => $item['value'],
            ];
        }

        return $grouped;
    }

    /**
     * Build a structured MDP address from a GF address field's sub-inputs.
     *
     * Maps GF's fixed input positions (.1 street .. .6 country) to MDP address
     * attributes and converts country names to ISO alpha-2 and state/province
     * names to codes when the resolved country is US/CA (Carmen validates
     * state codes only there). Submitted-but-empty parts are kept as '' so a
     * contact update can clear a part the user emptied; the create path drops
     * them again. Returns null when nothing at all was submitted.
     *
     * @return array<string, string>|null
     */
    protected function build_address_value(object $field, array $entry): ?array
    {
        $base_id = (string) ($field->id ?? '');
        $inputs = $field->inputs ?? [];
        if ($base_id === '' || !is_array($inputs)) {
            return null;
        }

        $value = [];
        foreach ($inputs as $input) {
            $input_id = (string) ($input['id'] ?? '');
            $prefix = $base_id . '.';
            if (!str_starts_with($input_id, $prefix)) {
                continue;
            }

            $attr = self::ADDRESS_INPUT_MAP[(int) substr($input_id, strlen($prefix))] ?? null;
            if ($attr === null) {
                continue;
            }

            $value[$attr] = sanitize_text_field(trim((string) ($entry[$input_id] ?? '')));
        }

        if ($value === [] || trim(implode('', $value)) === '') {
            return null;
        }

        if (($value['country_code'] ?? '') === '') {
            $value['country_code'] = $this->default_country_code($field, (int) ($entry['form_id'] ?? 0));
        }

        $value['country_code'] = $this->to_country_code($value['country_code'] ?? '');
        $value['state_name'] = $this->to_state_code($value['state_name'] ?? '', $value['country_code']);

        return $value;
    }

    /**
     * Convert a GF country value (name or already a code) to ISO alpha-2.
     *
     * The MDP validates country_code against Carmen alpha-2 codes. Passthrough
     * when GF's converter is unavailable (unit context) or rejects the value:
     * the API surfaces the real validation error on sync instead.
     */
    protected function to_country_code(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!class_exists(\GF_Fields::class)) {
            return $value;
        }

        $address_field = \GF_Fields::get('address');
        if (!$address_field || !method_exists($address_field, 'get_country_code')) {
            return $value;
        }

        $code = (string) $address_field->get_country_code($value);

        return $code !== '' ? $code : $value;
    }

    /**
     * Resolve a country code from the address field's own configuration.
     *
     * GF renders no country input for single-country address types (us,
     * canadian, or a custom gform_address_types entry carrying a 'country'
     * key), so those entries carry no country value at all and the type's
     * own country fills the gap. International layouts use the defaultCountry
     * preselect, which only matters when the input is hidden another way.
     */
    private function default_country_code(object $field, int $form_id): string
    {
        $country_name = '';
        $type = (string) ($field->addressType ?? '');
        if ($type !== '' && $type !== 'international' && class_exists(\GF_Fields::class)) {
            $address_field = \GF_Fields::get('address');
            if ($address_field && method_exists($address_field, 'get_address_types')) {
                $country_name = (string) ($address_field->get_address_types($form_id)[$type]['country'] ?? '');
            }
        }

        if ($country_name === '') {
            $country_name = trim((string) ($field->defaultCountry ?? ''));
        }

        return $country_name === '' ? '' : $this->to_country_code($country_name);
    }

    /**
     * Convert a GF state/province value to a Carmen subregion code.
     *
     * Carmen validates state codes only for US and CA addresses, so any other
     * country passes through untouched. GF US dropdowns submit codes already;
     * Canadian provinces submit names (EN or FR) and GF ships no province-code
     * helper, so those convert here. Anything else passes through for the API
     * to accept or reject visibly.
     */
    protected function to_state_code(string $value, string $country_code): string
    {
        if ($value === '' || !in_array($country_code, ['US', 'CA'], true)) {
            return $value;
        }

        if (preg_match('/^[A-Z]{2}$/', $value) === 1) {
            return $value;
        }

        $folded = strtolower($this->fold_accents($value));
        if (isset(self::CA_PROVINCE_CODES[$folded])) {
            return self::CA_PROVINCE_CODES[$folded];
        }

        if ($country_code !== 'US' || !class_exists(\GF_Fields::class)) {
            return $value;
        }

        $address_field = \GF_Fields::get('address');
        if (!$address_field || !method_exists($address_field, 'get_us_state_code')) {
            return $value;
        }

        $code = (string) $address_field->get_us_state_code($value);

        return $code !== '' ? $code : $value;
    }

    /**
     * Fold accented letters to their ASCII base (Québec → Quebec) so the
     * province map matches accented submissions without a locale dependency.
     */
    private function fold_accents(string $value): string
    {
        $folded = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $folded === false ? $value : strtolower($folded);
    }

    /**
     * Resolve the entity UUID from the source field's submitted value.
     *
     * @param string $source_field_id The GF field ID containing the UUID.
     * @param array  $entry           The GF entry.
     * @return string
     */
    protected function resolve_uuid(string $source_field_id, array $entry): string
    {
        return sanitize_text_field((string) ($entry[$source_field_id] ?? ''));
    }

    /**
     * Push grouped values to the MDP API.
     *
     * Groups into a single PATCH call per entity (profile + data_fields + communications
     * can all go in one PATCH body).
     *
     * @param string $entity_type 'person' or 'organization'.
     * @param string $uuid        Entity UUID.
     * @param array  $grouped     Values grouped by target object.
     * @return array{success: bool, message: string, objects: array<string, bool>}
     */
    protected function push_to_mdp(string $entity_type, string $uuid, array $grouped): array
    {
        if (!function_exists('wicket_api_client')) {
            return ['success' => false, 'message' => 'Wicket API client not available', 'objects' => []];
        }

        try {
            $client = wicket_api_client();
            if (!$client) {
                return ['success' => false, 'message' => 'Wicket API client returned false', 'objects' => []];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'API client init failed: ' . $e->getMessage(), 'objects' => []];
        }

        $objects = [];

        try {
            // Guarded with the request below: this runs on every mapped
            // submission now, and a TypeError here must degrade to a failed
            // sync status, never a 500 after the entry was saved.
            $contact_groups = array_intersect_key($grouped, array_flip(self::CONTACT_OBJECTS));
            $profile_groups = array_diff_key($grouped, $contact_groups);
            $payload = $this->build_patch_payload($entity_type, $uuid, $profile_groups);

            $failures = [];

            // MDP contract: data_fields PATCHes must carry the complete value
            // structure per schema (GET + merge + PATCH, with version).
            if (!empty($payload['data']['attributes']['data_fields'])) {
                $merged = $this->merge_data_fields(
                    $entity_type,
                    $uuid,
                    $payload['data']['attributes']['data_fields']
                );

                if ($merged === null) {
                    $failures[] = 'Could not fetch current record to merge additional info data_fields';
                    foreach (array_keys($profile_groups) as $obj) {
                        $objects[$obj] = false;
                    }
                } else {
                    $payload['data']['attributes']['data_fields'] = $merged;
                }
            }

            if (empty($failures) && !empty($payload['data']['attributes'])) {
                $endpoint = $entity_type === 'organization' ? "organizations/$uuid" : "people/$uuid";

                try {
                    $client->patch($endpoint, ['json' => $payload]);

                    foreach (array_keys($profile_groups) as $obj) {
                        $objects[$obj] = true;
                    }
                } catch (RequestException $e) {
                    $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';

                    $failures[] = $this->summarize_api_error((int) $e->getCode(), $body);
                    foreach (array_keys($profile_groups) as $obj) {
                        $objects[$obj] = false;
                    }
                }
            }

            if (empty($failures) && empty($payload['data']['attributes'])) {
                // Nothing to write on the profile (e.g. only contact mappings
                // resolved): report those groups as satisfied, no PATCH sent.
                foreach (array_keys($profile_groups) as $obj) {
                    $objects[$obj] = true;
                }
            }

            if (!empty($contact_groups)) {
                $contact_result = $this->push_contacts_to_mdp($client, $entity_type, $uuid, $contact_groups);
                $objects = $objects + $contact_result['objects'];

                if (!$contact_result['success']) {
                    $failures[] = $contact_result['message'];
                }
            }

            if (empty($failures) && empty($objects)) {
                return ['success' => true, 'message' => 'No attributes to update', 'objects' => []];
            }

            if (empty($failures)) {
                return ['success' => true, 'message' => 'MDP sync successful', 'objects' => $objects];
            }

            $message = mb_substr(implode(' | ', $failures), 0, 2000);

            return ['success' => false, 'message' => $message, 'objects' => $objects];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                // Union keeps results already recorded (e.g. a profile PATCH
                // that succeeded before the throw) intact; unknown keys fail.
                'objects' => array_fill_keys(array_keys($grouped), false) + $objects,
            ];
        }
    }

    /**
     * Write contact-resource mappings (addresses, phones, emails, web
     * addresses) for one entity.
     *
     * Contact resources are separate MDP endpoints, not PATCHable attributes.
     * For each resource: update the entity's existing contact whose type
     * matches the target value (partial PATCH, so untouched attributes such as
     * primary flags survive); create one when absent. The MDP keeps a single
     * primary per resource itself (ResourceCollectionFlagUpdator demotes
     * siblings on a primary save), so creates claim primary and no client-side
     * demotion runs.
     *
     * @param object $client      Wicket API client.
     * @param string $entity_type 'person' or 'organization'.
     * @param string $uuid        Entity UUID.
     * @param array  $grouped     Contact target object => {target_field, value} rows.
     * @return array{success: bool, message: string, objects: array<string, bool>}
     */
    protected function push_contacts_to_mdp(object $client, string $entity_type, string $uuid, array $grouped): array
    {
        $prefix = $entity_type === 'organization' ? 'organizations' : 'people';

        $error = '';
        $current = $this->fetch_entity_contacts($client, $prefix, $uuid, $error);
        if ($current === null) {
            return [
                'success' => false,
                'message' => sprintf('Could not fetch current %s record to sync contacts: %s', $prefix, $error),
                'objects' => array_fill_keys(array_keys($grouped), false),
            ];
        }

        $objects = [];
        $failures = [];

        foreach ($grouped as $resource => $fields) {
            // Two mappings on one form can aim at the same contact target
            // (e.g. two phone fields mapped to phones.work). Last one wins:
            // scalars overwrite, address parts merge per attribute. A string
            // colliding with an array normalizes through the string-as-address1
            // contract so neither side silently drops.
            $deduped = [];
            foreach ($fields as $field) {
                $key = (string) ($field['target_field'] ?? '');
                if (!isset($deduped[$key])) {
                    $deduped[$key] = $field;
                    continue;
                }

                $existing = $deduped[$key]['value'];
                $incoming = $field['value'];
                if (is_array($existing) || is_array($incoming)) {
                    $base = is_array($existing)
                        ? $existing
                        : (is_string($existing) && $existing !== '' ? ['address1' => $existing] : []);
                    $add = is_array($incoming)
                        ? $incoming
                        : (is_string($incoming) && $incoming !== '' ? ['address1' => $incoming] : []);
                    $deduped[$key]['value'] = array_merge($base, $add);
                } else {
                    $deduped[$key] = $field;
                }
            }

            $ok = true;
            $resource_failures = [];

            foreach ($deduped as $field) {
                try {
                    $this->push_contact_resource(
                        $client,
                        $prefix,
                        $uuid,
                        (string) $resource,
                        (string) $field['target_field'],
                        $field['value'],
                        $current
                    );
                } catch (RequestException $e) {
                    $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
                    $ok = false;
                    $resource_failures[] = $this->summarize_api_error((int) $e->getCode(), $body);
                } catch (\Throwable $e) {
                    $ok = false;
                    $resource_failures[] = $e->getMessage();
                }
            }

            $objects[$resource] = $ok;
            if (!$ok) {
                $failures[] = sprintf('%s: %s', $resource, implode('; ', $resource_failures));
            }
        }

        return [
            'success' => $failures === [],
            'message' => mb_substr(implode(' | ', $failures), 0, 2000),
            'objects' => $objects,
        ];
    }

    /**
     * Fetch the entity's current contact resources, grouped by type.
     *
     * One raw GET per entity: people and organizations single-record responses
     * include all four contact resources by default. Raw GET, not the SDK
     * fetch helper, because the SDK model throws on some payloads and only
     * id/attributes pairs are needed here.
     *
     * @param object $client Wicket API client.
     * @param string $prefix 'people' or 'organizations'.
     * @param string $uuid   Entity UUID.
     * @param string $error  Set to the failure reason when null is returned.
     * @return array<string, array<int, array{id: string, attributes: array<string, mixed>}>}|null Null when the fetch fails.
     */
    protected function fetch_entity_contacts(object $client, string $prefix, string $uuid, string &$error = ''): ?array
    {
        try {
            // Explicit includes pin the contract: the seeded DEFAULT_INCLUDES_SINGLE
            // on both controllers already carries the contact resources, but a
            // default-only GET would silently create duplicates if that ever
            // drifted. All four names sit in both controllers' whitelists.
            $response = $client->get("$prefix/$uuid?include=" . implode(',', self::CONTACT_OBJECTS));
            $body = is_array($response) ? $response : wicket_convert_obj_to_array($response);
        } catch (\Throwable $e) {
            $error = $e->getMessage();

            return null;
        }

        // A JSON:API single response with explicit includes always carries the
        // key (empty when the entity has no contacts). Missing key = payload we
        // cannot interpret: fail closed rather than treat it as zero contacts
        // and create duplicates.
        if (!is_array($body) || !array_key_exists('included', $body)) {
            $error = 'MDP response has no included contacts payload; refusing to write contacts';

            return null;
        }

        $current = [];
        foreach ((array) ($body['included'] ?? []) as $item) {
            $type = is_string($item['type'] ?? null) ? $item['type'] : '';
            if (!in_array($type, self::CONTACT_OBJECTS, true)) {
                continue;
            }

            $id = is_string($item['id'] ?? null) ? $item['id'] : '';
            if ($id === '') {
                continue;
            }

            $current[$type][] = [
                'id' => $id,
                'attributes' => is_array($item['attributes'] ?? null) ? $item['attributes'] : [],
            ];
        }

        return $current;
    }

    /**
     * Write one contact mapping: update the existing contact of the target
     * type, or create it (with primary semantics) when absent.
     *
     * @param object $client      Wicket API client.
     * @param string $prefix      'people' or 'organizations'.
     * @param string $uuid        Entity UUID.
     * @param string $resource    Contact resource key.
     * @param string $target_field Target field value (e.g. 'phones.work').
     * @param mixed  $value       Mapped value (string, or address array).
     * @param array  $current     Entity contacts from fetch_entity_contacts().
     * @throws \Throwable On invalid target or failed API write.
     */
    protected function push_contact_resource(object $client, string $prefix, string $uuid, string $resource, string $target_field, mixed $value, array $current): void
    {
        $parsed = $this->parse_contact_target($target_field);
        if ($parsed === null || $parsed['resource'] !== $resource) {
            throw new \RuntimeException(sprintf('Invalid contact target %s', $target_field));
        }

        $existing = $current[$resource] ?? [];

        // Several contacts can share a type; the primary one owns form updates
        // so the entity's canonical record is the one rewritten. First of the
        // type wins when none carries the primary flag.
        $match = null;
        $first_of_type = null;
        foreach ($existing as $item) {
            if (($item['attributes']['type'] ?? '') !== $parsed['type']) {
                continue;
            }

            $first_of_type ??= $item;
            if (($item['attributes']['primary'] ?? false) === true) {
                $match = $item;
                break;
            }
        }
        $match ??= $first_of_type;

        if ($match !== null) {
            $id = (string) ($match['id'] ?? '');
            if ($id === '') {
                throw new \RuntimeException(sprintf('Existing %s of type %s has no id', $resource, $parsed['type']));
            }

            // Partial PATCH with explicit values for every mapped address part
            // (a cleared input must clear the attribute) but no country_code
            // when empty: its inclusion validation has no allow_nil.
            $attrs = $this->contact_attributes($resource, $value, true);
            $client->patch("$resource/$id", ['json' => [
                'data' => ['type' => $resource, 'id' => $id, 'attributes' => $attrs],
            ]]);

            return;
        }

        $attrs = $this->contact_attributes($resource, $value, false);

        if ($resource === 'web_addresses') {
            // Its controller parses jsonapi-style params; the type rides in
            // attributes and there is no primary flag to claim.
            $attrs['type'] = $parsed['type'];
            $client->post("$prefix/$uuid/$resource", ['json' => [
                'data' => ['attributes' => $attrs],
            ]]);

            return;
        }

        // A created contact always claims the primary flag: form-fed contact
        // data is the entity's current contact point. The MDP demotes sibling
        // primaries itself on a primary save, so no client-side demotion runs.
        $attrs['type'] = $parsed['type'];
        $attrs['primary'] = true;

        $client->post("$prefix/$uuid/$resource", ['json' => [
            'data' => ['type' => $resource, 'attributes' => $attrs],
        ]]);
    }

    /**
     * Map a contact value to the resource's writable attributes.
     *
     * @param string $resource  Contact resource key.
     * @param mixed  $value     Mapped value (string, or address parts array).
     * @param bool   $is_update True when patching an existing record: address
     *                          text parts are always sent so cleared inputs
     *                          clear the attribute; country_code is omitted
     *                          when empty (no allow_nil on its inclusion rule).
     * @return array<string, mixed>
     * @throws \RuntimeException When the value shape cannot populate the resource.
     */
    protected function contact_attributes(string $resource, mixed $value, bool $is_update): array
    {
        if ($resource === 'addresses') {
            $parts = is_array($value)
                ? $value
                : ['address1' => (string) $value];

            $text_parts = ['company_name', 'address1', 'address2', 'city', 'state_name', 'zip_code'];
            $attrs = [];
            foreach ($text_parts as $key) {
                $part = trim((string) ($parts[$key] ?? ''));
                if ($part !== '' || $is_update) {
                    $attrs[$key] = $part;
                }
            }

            $country = trim((string) ($parts['country_code'] ?? ''));
            if ($country !== '') {
                $attrs['country_code'] = $country;
            }

            if (trim(implode('', $attrs)) === '') {
                throw new \RuntimeException('Mapped address value is empty');
            }

            return $attrs;
        }

        $attr = match ($resource) {
            'phones' => 'number',
            'emails' => 'address',
            'web_addresses' => 'address',
            default => throw new \RuntimeException(sprintf('Unknown contact resource %s', $resource)),
        };

        $value = trim((string) $value);
        if ($value === '') {
            throw new \RuntimeException(sprintf('Mapped %s value is empty', $resource));
        }

        if ($resource === 'emails') {
            $address = sanitize_email($value);
            if ($address === '') {
                // sanitize_email empties invalid input; sending '' would wipe
                // the stored email on a partial PATCH, so fail before writing.
                throw new \RuntimeException('Mapped emails value is not a valid email address');
            }

            return [$attr => $address];
        }

        if ($resource === 'web_addresses') {
            $address = esc_url_raw($value);
            if ($address === '') {
                // esc_url_raw empties invalid URLs; '' on a partial PATCH
                // would wipe the stored address, so fail before writing.
                throw new \RuntimeException('Mapped web_addresses value is not a valid URL');
            }

            return [$attr => $address];
        }

        return [$attr => sanitize_text_field($value)];
    }

    /**
     * Parse a contact target value into resource + contact type.
     *
     * 'phones.work' => ['resource' => 'phones', 'type' => 'work']
     *
     * @return array{resource: string, type: string}|null
     */
    protected function parse_contact_target(string $target_field): ?array
    {
        $dot = strpos($target_field, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($target_field) - 1) {
            return null;
        }

        $resource = substr($target_field, 0, $dot);
        if (!in_array($resource, self::CONTACT_OBJECTS, true)) {
            return null;
        }

        return [
            'resource' => $resource,
            'type' => substr($target_field, $dot + 1),
        ];
    }

    /**
     * Build a log-safe summary of an MDP API error response.
     *
     * Raw error bodies echo submitted values (kept out of logs) and a
     * truncated body hides every validation error behind the first.
     * Validation envelopes are summarized per failing field: schema slug
     * or field name plus the schema-side messages. meta.value stays out.
     * Non-envelope bodies fall back to the truncated raw body.
     *
     * @param int    $status HTTP status code.
     * @param string $body   Raw response body.
     */
    protected function summarize_api_error(int $status, string $body): string
    {
        $decoded = json_decode($body, true);
        $errors = is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];

        if ($errors === []) {
            return sprintf('API error (%d): %s', $status, substr($body, 0, 500));
        }

        $parts = [];
        foreach (array_slice($errors, 0, 5) as $error) {
            $meta = is_array($error['meta'] ?? null) ? $error['meta'] : [];
            $locus = (string) ($meta['schema_slug'] ?? ($error['field'] ?? ''));
            $piece = (string) ($error['title'] ?? 'error');

            if ($locus !== '') {
                $piece .= ' [' . $locus . ']';
            }

            $schema_messages = [];
            foreach ((array) ($meta['json_schema'] ?? []) as $schema_error) {
                if (is_array($schema_error) && isset($schema_error['message'])) {
                    $schema_messages[] = mb_substr((string) $schema_error['message'], 0, 200);
                }
            }

            if ($schema_messages !== []) {
                $piece .= ': ' . implode('; ', array_slice($schema_messages, 0, 2));
            }

            $parts[] = $piece;
        }

        $total = count($errors);
        $summary = sprintf('API error (%d): %d validation error(s): %s', $status, $total, implode(' | ', $parts));

        if ($total > 5) {
            $summary .= sprintf(' (+%d more)', $total - 5);
        }

        return mb_substr($summary, 0, 1000);
    }

    /**
     * Build the JSON API PATCH payload from grouped values.
     *
     * All target objects (profile, data_fields, communications) are merged
     * into a single PATCH body since the MDP API accepts them together.
     *
     * @param string $entity_type 'person' or 'organization'.
     * @param string $uuid        Entity UUID.
     * @param array  $grouped     Values grouped by target object.
     * @return array JSON API payload.
     */
    protected function build_patch_payload(string $entity_type, string $uuid, array $grouped): array
    {
        $type = $entity_type === 'organization' ? 'organizations' : 'people';
        $attributes = [];

        foreach ($grouped as $target_object => $fields) {
            switch ($target_object) {
                case 'person_profile':
                case 'org_profile':
                    // Top-level attributes (e.g. 'attributes.given_name' → 'given_name')
                    foreach ($fields as $field) {
                        $attr_name = $this->strip_attributes_prefix($field['target_field']);
                        $attributes[$attr_name] = $field['value'];
                    }
                    break;

                case 'additional_info':
                case 'org_additional_info':
                    // Schema-based data fields. The MDP API validates each
                    // data_field value against its JSON Schema, so targets are
                    // property-level: 'data_field.<schema_slug>.<property>'.
                    // Values are staged per schema slug; push_to_mdp() merges
                    // them into the record's full data_fields before the PATCH.
                    $data_fields = [];
                    foreach ($fields as $field) {
                        $parsed = $this->parse_data_field_target($field['target_field']);
                        if ($parsed === null) {
                            continue;
                        }
                        $data_fields[$parsed['schema_slug']][] = [
                            'property' => $parsed['property'],
                            'value'    => $field['value'],
                        ];
                    }
                    if (!empty($data_fields)) {
                        $attributes['data_fields'] = $data_fields;
                    }
                    break;

                case 'preferences':
                    // Communications sublists. The MDP API expects booleans;
                    // GF entry values are strings ('1', 'true', ...).
                    $communications = [];
                    foreach ($fields as $field) {
                        $pref_key = $this->extract_communications_key($field['target_field']);
                        if ($pref_key === 'email') {
                            $communications['email'] = $this->to_bool($field['value']);
                        } elseif ($pref_key !== '') {
                            if (!isset($communications['sublists'])) {
                                $communications['sublists'] = [];
                            }
                            $communications['sublists'][$pref_key] = $this->to_bool($field['value']);
                        }
                    }
                    if (!empty($communications)) {
                        $attributes['data']['communications'] = $communications;
                    }
                    break;
            }
        }

        return [
            'data' => [
                'type' => $type,
                'id' => $uuid,
                'attributes' => $attributes,
            ],
        ];
    }

    /**
     * Strip 'attributes.' prefix from a field value string.
     *
     * 'attributes.given_name' → 'given_name'
     */
    protected function strip_attributes_prefix(string $field): string
    {
        if (str_starts_with($field, 'attributes.')) {
            return substr($field, strlen('attributes.'));
        }

        return $field;
    }

    /**
     * Parse a composite data_field target into schema slug + property.
     *
     * 'data_field.custom-fields.custom_field' →
     * ['schema_slug' => 'custom-fields', 'property' => 'custom_field']
     *
     * @return array{schema_slug: string, property: string}|null
     */
    protected function parse_data_field_target(string $field): ?array
    {
        if (!str_starts_with($field, 'data_field.')) {
            return null;
        }

        $rest = substr($field, strlen('data_field.'));
        $dot = strpos($rest, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($rest) - 1) {
            return null;
        }

        return [
            'schema_slug' => substr($rest, 0, $dot),
            'property'    => substr($rest, $dot + 1),
        ];
    }

    /**
     * Merge staged property values into the record's full data_fields.
     *
     * Implements the MDP API GET + merge + PATCH contract: data_fields are
     * complete JSON structures validated against schemas, so a partial PATCH
     * could fail validation or wipe sibling properties. Existing entries keep
     * their value object and version (409 record_conflict protection).
     *
     * @param string $entity_type 'person' or 'organization'.
     * @param string $uuid        Entity UUID.
     * @param array  $staged      map of schema_slug => list of {property, value}.
     * @return array|null Full data_fields array for the PATCH, or null when the
     *                    current record cannot be fetched.
     */
    protected function merge_data_fields(string $entity_type, string $uuid, array $staged): ?array
    {
        try {
            $client = wicket_api_client();
            $path = $entity_type === 'organization' ? "organizations/$uuid" : "people/$uuid";
            $response = $client->get($path);
            $body = is_array($response) ? $response : wicket_convert_obj_to_array($response);
            $current = $body['data']['attributes']['data_fields'] ?? [];
            if (!is_array($current)) {
                $current = [];
            }
        } catch (\Throwable $e) {
            // Raw response bodies echo record values; summarize like the PATCH path.
            $reason = $e instanceof RequestException && $e->hasResponse()
                ? $this->summarize_api_error((int) $e->getCode(), (string) $e->getResponse()->getBody())
                : $e->getMessage();
            $this->write_log(
                0,
                0,
                $entity_type,
                $uuid,
                self::STATUS_FAILED,
                'data_fields merge fetch failed; profile update aborted: ' . $reason
            );

            return null;
        }

        $type_map = [];
        if (isset($this->discovery)) {
            $type_map = $this->discovery->getPropertyTypeMap();
        }

        // Merge staged values into matching existing entries; preserve
        // everything else untouched (including entries without schema_slug).
        $pending = $staged;
        // Slug -> schema UUID, so legacy entries keyed only by `$schema`
        // (urn:uuid:<id>) can still be matched and merged instead of being
        // duplicated as a second entry for the same schema.
        $id_map = [];
        if (isset($this->discovery)) {
            $id_map = $this->discovery->getSchemaIdMap();
        }

        $merged = [];
        foreach (array_values($current) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $slug = is_string($entry['schema_slug'] ?? null) ? $entry['schema_slug'] : '';
            if ($slug === '' && isset($entry['$schema']) && is_string($entry['$schema'])) {
                // Legacy shape: resolve the slug from the $schema URN
                foreach ($id_map as $map_slug => $uuid) {
                    if ($uuid !== '' && str_contains($entry['$schema'], $uuid)) {
                        $slug = $map_slug;
                        break;
                    }
                }
            }

            if ($slug !== '' && isset($pending[$slug])) {
                $value = is_array($entry['value'] ?? null) ? $entry['value'] : [];
                foreach ($pending[$slug] as $staged_prop) {
                    $type_key = 'data_field.' . $slug . '.' . $staged_prop['property'];
                    $value[$staged_prop['property']] = $this->cast_value(
                        $staged_prop['value'],
                        $type_map[$type_key] ?? ''
                    );
                }
                $entry['value'] = $value;
                unset($pending[$slug]);
            }

            $merged[] = $entry;
        }

        // Slugs with no existing entry become new data_fields. Cast the slug
        // back to string: PHP coerces numeric array keys to int, which would
        // emit an unquoted "schema_slug":2024 the API rejects.
        foreach ($pending as $slug => $staged_props) {
            $slug = (string) $slug;
            $value = [];
            foreach ($staged_props as $staged_prop) {
                $type_key = 'data_field.' . $slug . '.' . $staged_prop['property'];
                $value[$staged_prop['property']] = $this->cast_value(
                    $staged_prop['value'],
                    $type_map[$type_key] ?? ''
                );
            }
            $merged[] = [
                'schema_slug' => $slug,
                'value'       => $value,
            ];
        }

        return $merged;
    }

    /**
     * Cast a submitted GF string to the schema property's expected type.
     *
     * @param mixed  $value Raw submitted value.
     * @param string $type  'boolean'|'number'|'integer'|'string' (empty = leave as-is).
     */
    protected function cast_value(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => $this->to_bool($value),
            'number'  => is_numeric($value) ? $value + 0 : $value,
            'integer' => is_numeric($value) ? (int) $value : $value,
            default   => $value,
        };
    }

    /**
     * Interpret a submitted string as a boolean.
     *
     * GF checkboxes submit '1' when checked and nothing when unchecked.
     */
    protected function to_bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Extract the preference key from a communications value string.
     *
     * 'communications.email' → 'email'
     * 'communications.sublists.knowledge_weekly' → 'knowledge_weekly'
     */
    protected function extract_communications_key(string $field): string
    {
        if (str_starts_with($field, 'communications.sublists.')) {
            return substr($field, strlen('communications.sublists.'));
        }
        if (str_starts_with($field, 'communications.')) {
            return substr($field, strlen('communications.'));
        }

        return '';
    }

    /**
     * Record sync status as GF entry meta.
     *
     * @param int    $entry_id GF entry ID.
     * @param string $status   One of the STATUS_* constants.
     * @param string $message  Human-readable status message.
     */
    protected function record_status(int $entry_id, string $status, string $message, array $log_context = []): void
    {
        if ($entry_id <= 0) {
            return;
        }

        $meta = [
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql'),
        ];

        gform_update_meta($entry_id, self::META_KEY, $meta);

        $this->write_log(
            (int) ($log_context['form_id'] ?? 0),
            $entry_id,
            (string) ($log_context['entity_type'] ?? ''),
            (string) ($log_context['uuid'] ?? ''),
            $status,
            $message
        );
    }

    /**
     * Record aggregate sync results from multiple object pushes.
     *
     * @param int   $entry_id GF entry ID.
     * @param array $results  Result from push_to_mdp().
     */
    protected function record_sync_results(int $entry_id, array $results, array $log_context = []): void
    {
        $status = $results['success'] ? self::STATUS_SUCCESS : self::STATUS_FAILED;

        if ($entry_id <= 0) {
            return;
        }

        $meta = [
            'status' => $status,
            'message' => $results['message'],
            'objects' => $results['objects'] ?? [],
            'timestamp' => current_time('mysql'),
        ];

        gform_update_meta($entry_id, self::META_KEY, $meta);

        $this->write_log(
            (int) ($log_context['form_id'] ?? 0),
            $entry_id,
            (string) ($log_context['entity_type'] ?? ''),
            (string) ($log_context['uuid'] ?? ''),
            $status,
            $results['message'],
            $results['objects'] ?? []
        );
    }

    /**
     * Write to the centralized Wicket log (Wicket()->log()).
     *
     * Uses the standard file-based logger from wicket-wp-base-plugin.
     * Falls back silently if Wicket() is not available.
     *
     * @param int    $form_id     GF form ID.
     * @param int    $entry_id    GF entry ID.
     * @param string $entity_type Entity type.
     * @param string $uuid        Entity UUID.
     * @param string $status      Sync status.
     * @param string $message     Status message.
     */
    private function write_log(int $form_id, int $entry_id, string $entity_type, string $uuid, string $status, string $message, array $target_objects = []): void
    {
        if (!function_exists('Wicket')) {
            return;
        }

        $context = [
            'source'      => 'wicket-gf-mdp-sync',
            'form_id'     => $form_id,
            'entry_id'    => $entry_id,
            'entity_type' => $entity_type,
            'status'      => $status,
        ];

        if ($uuid !== '') {
            $context['uuid'] = $uuid;
        }

        if (!empty($target_objects)) {
            $context['target_objects'] = array_keys($target_objects);
        }

        $level = match ($status) {
            self::STATUS_FAILED  => 'error',
            self::STATUS_SKIPPED => 'warning',
            default              => 'info',
        };

        Wicket()->log()->{$level}($message, $context);
    }

    /**
     * Get the sync status meta key.
     * Exposed for tests and entry detail display.
     */
    public static function get_meta_key(): string
    {
        return self::META_KEY;
    }
}
