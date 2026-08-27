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
    private const CRON_HOOK = 'wicket_gf_mdp_sync_process';

    /**
     * Register hooks: after_submission (schedules async) + cron callback.
     */
    public function register(): void
    {
        add_action('gform_after_submission', [$this, 'schedule_sync'], 10, 2);
        add_action(self::CRON_HOOK, [$this, 'process_scheduled_sync'], 10, 1);
    }

    /**
     * Schedule async MDP sync after form submission.
     *
     * Records PENDING status immediately, then schedules background processing.
     * Falls back to synchronous processing if scheduling fails.
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

        $uuid = $this->resolve_uuid($form_config['uuid_source_field'], $entry);
        if (empty($uuid)) {
            $this->record_status($entry_id, self::STATUS_FAILED, 'Could not resolve entity UUID from source field', $log_ctx);

            return;
        }

        $log_ctx['uuid'] = $uuid;

        // Record PENDING status immediately for UI visibility
        $this->record_status($entry_id, self::STATUS_PENDING, 'Scheduled for async MDP sync', $log_ctx);

        // Prepare minimal payload for the cron job
        $grouped = $this->group_by_target_object($mapped_values);
        $payload = [
            'entry_id'    => $entry_id,
            'form_id'     => $form_id,
            'entity_type' => $form_config['entity_type'],
            'uuid'        => $uuid,
            'grouped'     => $grouped,
        ];

        $scheduled = wp_schedule_single_event(time(), self::CRON_HOOK, [$payload]);

        // Fallback: if scheduling fails, process synchronously
        if ($scheduled === false) {
            $results = $this->push_to_mdp($payload['entity_type'], $payload['uuid'], $payload['grouped']);
            $this->record_sync_results($entry_id, $results, $log_ctx);
        }
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
        $uuid = (string) ($payload['uuid'] ?? '');
        $grouped = (array) ($payload['grouped'] ?? []);

        $log_ctx = ['form_id' => $form_id, 'entity_type' => $entity_type, 'uuid' => $uuid];

        if ($entry_id <= 0 || $uuid === '' || empty($grouped)) {
            $this->record_status($entry_id, self::STATUS_FAILED, 'Invalid scheduled sync payload', $log_ctx);

            return;
        }

        $results = $this->push_to_mdp($entity_type, $uuid, $grouped);
        $this->record_sync_results($entry_id, $results, $log_ctx);
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
            $this->record_status($entry_id, self::STATUS_SKIPPED, 'Missing required form-level MDP config');

            return;
        }

        $mapped_values = $this->collect_mapped_values($form, $entry);

        if (empty($mapped_values)) {
            $this->record_status($entry_id, self::STATUS_SKIPPED, 'No mapped fields with values');

            return;
        }

        $uuid = $this->resolve_uuid($form_config['uuid_source_field'], $entry);
        if (empty($uuid)) {
            $this->record_status($entry_id, self::STATUS_FAILED, 'Could not resolve entity UUID from source field');

            return;
        }

        $grouped = $this->group_by_target_object($mapped_values);
        $results = $this->push_to_mdp($form_config['entity_type'], $uuid, $grouped);
        $this->record_sync_results($entry_id, $results);
    }

    /**
     * Extract form-level MDP config.
     *
     * @param array $form GF form object.
     * @return array{entity_type: string, uuid_source_field: string}
     */
    protected function get_form_config(array $form): array
    {
        return [
            'entity_type' => $form['wicket_mdp_entity_type'] ?? '',
            'uuid_source_field' => $form['wicket_mdp_uuid_source_field'] ?? '',
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
     */
    protected function is_sync_eligible(array $config): bool
    {
        return $config['entity_type'] !== '' && $config['uuid_source_field'] !== '';
    }

    /**
     * Collect mapped field values from the submission.
     *
     * Returns an array of mapped field data, each with:
     * - target_object: string
     * - target_field: string (e.g. 'attributes.given_name', 'data_field.custom-fields')
     * - value: mixed
     *
     * @param array $form  GF form object.
     * @param array $entry GF entry object.
     * @return array<int, array{target_object: string, target_field: string, value: mixed}>
     */
    protected function collect_mapped_values(array $form, array $entry): array
    {
        $mapped = [];

        if (empty($form['fields'])) {
            return $mapped;
        }

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

            // Get submitted value for this field
            $value = $this->get_field_value($field, $entry);
            if ($value === '' || $value === null) {
                continue;
            }

            $mapped[] = [
                'target_object' => $target_object,
                'target_field' => $target_field,
                'value' => $value,
            ];
        }

        return $mapped;
    }

    /**
     * Get a field's submitted value from the entry.
     *
     * Uses GF's rgars() pattern for multi-input fields.
     *
     * @param object $field GF field object.
     * @param array  $entry GF entry object.
     * @return string|null
     */
    protected function get_field_value(object $field, array $entry): ?string
    {
        $field_id = (string) ($field->id ?? '');
        if ($field_id === '') {
            return null;
        }

        // Multi-input fields (name, address) — combine all inputs
        if (!empty($field->inputs) && is_array($field->inputs)) {
            $parts = [];
            foreach ($field->inputs as $input) {
                $input_id = (string) ($input['id'] ?? '');
                if ($input_id === '') {
                    continue;
                }
                $val = $entry[$input_id] ?? '';
                if ($val !== '') {
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

        $payload = $this->build_patch_payload($entity_type, $uuid, $grouped);

        if (empty($payload['data']['attributes'])) {
            return ['success' => true, 'message' => 'No attributes to update', 'objects' => []];
        }

        try {
            $payload = $this->build_patch_payload($entity_type, $uuid, $grouped);

            // MDP contract: data_fields PATCHes must carry the complete value
            // structure per schema (GET + merge + PATCH, with version).
            if (!empty($payload['data']['attributes']['data_fields'])) {
                $merged = $this->merge_data_fields(
                    $entity_type,
                    $uuid,
                    $payload['data']['attributes']['data_fields']
                );

                if ($merged === null) {
                    return [
                        'success' => false,
                        'message' => 'Could not fetch current record to merge additional info data_fields',
                        'objects' => array_fill_keys(array_keys($grouped), false),
                    ];
                }

                $payload['data']['attributes']['data_fields'] = $merged;
            }

            if (empty($payload['data']['attributes'])) {
                return ['success' => true, 'message' => 'No attributes to update', 'objects' => []];
            }

            $endpoint = $entity_type === 'organization' ? "organizations/$uuid" : "people/$uuid";

            try {
                $client->patch($endpoint, ['json' => $payload]);

                $objects = array_fill_keys(array_keys($grouped), true);

                return ['success' => true, 'message' => 'MDP sync successful', 'objects' => $objects];
            } catch (RequestException $e) {
                $body = '';
                if ($e->hasResponse()) {
                    // Truncated: API error bodies can echo submitted values. No PII in logs.
                    $body = substr((string) $e->getResponse()->getBody(), 0, 500);
                }

                return [
                    'success' => false,
                    'message' => sprintf('API error (%d): %s', $e->getCode(), $body),
                    'objects' => array_fill_keys(array_keys($grouped), false),
                ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'objects' => array_fill_keys(array_keys($grouped), false),
            ];
        }
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
            return null;
        }

        $type_map = [];
        if (isset($this->discovery)) {
            $type_map = $this->discovery->getPropertyTypeMap();
        }

        // Merge staged values into matching existing entries; preserve
        // everything else untouched (including entries without schema_slug).
        $pending = $staged;
        $merged = [];
        foreach (array_values($current) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $slug = is_string($entry['schema_slug'] ?? null) ? $entry['schema_slug'] : '';
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

        // Slugs with no existing entry become new data_fields
        foreach ($pending as $slug => $staged_props) {
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
