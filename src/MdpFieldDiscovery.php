<?php

declare(strict_types=1);

namespace WicketGF;

// No direct access
defined('ABSPATH') || exit;

/**
 * Centralized MDP field discovery service.
 *
 * Consolidates field definitions for all MDP target objects:
 * - person_profile: static attributes from MDP PATCH schema
 * - org_profile: static attributes from MDP PATCH schema
 * - additional_info: dynamic discovery via GET /json_schemas API endpoint
 * - preferences: dynamic discovery via person communications
 *
 * All methods return arrays of ['value' => string, 'label' => string]
 * for direct consumption by GF dropdowns.
 */
class MdpFieldDiscovery
{
    /**
     * Transient key for caching json_schemas discovery results.
     *
     * v3: payload is ['properties' => rows, 'ids' => [slug => uuid]]
     * (see discoverSchemaFields()). Versioned so stale v1/v2 transients
     * are never read with the new shape.
     */
    private const CACHE_KEY_SCHEMAS = 'wicket_gf_mdp_schemas_v3';

    /**
     * Transient marking a recent discovery failure (short TTL).
     *
     * During an MDP API outage, this stops every form save from re-issuing
     * a synchronous API call, and lets consumers distinguish "API failed"
     * from "API answered, genuinely zero fields" before mutating anything.
     */
    private const CACHE_KEY_API_FAIL = 'wicket_gf_mdp_api_fail';

    /**
     * Short TTL for the failure marker (seconds).
     */
    private const CACHE_TTL_FAIL = 60;

    /**
     * Whether the most recent discovery attempt in this request failed.
     */
    private bool $discovery_failed = false;

    /**
     * Transient key for caching preferences discovery results.
     */
    private const CACHE_KEY_PREFS = 'wicket_gf_mdp_preferences';

    /**
     * Default cache TTL in seconds (12 hours). Filterable.
     */
    private const CACHE_TTL_DEFAULT = 43200;

    /**
     * Get all target fields grouped by target object.
     *
     * @return array<string, array<array{value: string, label: string}>>
     */
    public function getAllTargetFields(): array
    {
        $objects = ['person_profile', 'org_profile', 'additional_info', 'org_additional_info', 'preferences'];
        $result = [];
        foreach ($objects as $object) {
            $result[$object] = $this->getTargetFields($object);
        }

        return $result;
    }

    /**
     * Get target fields for a specific target object.
     *
     * @param string $target_object The target object key.
     * @return array<array{value: string, label: string}>
     */
    public function getTargetFields(string $target_object): array
    {
        return match ($target_object) {
            'person_profile'      => $this->getPersonProfileFields(),
            'org_profile'         => $this->getOrgProfileFields(),
            'additional_info'     => $this->getAdditionalInfoFields(),
            'org_additional_info' => $this->getAdditionalInfoFields(),
            'preferences'         => $this->getPreferencesFields(),
            default               => [],
        };
    }

    /**
     * Get valid field value strings for a specific target object.
     *
     * @param string $target_object The target object key.
     * @return string[]
     */
    public function getTargetFieldValues(string $target_object): array
    {
        $fields = $this->getTargetFields($target_object);

        return array_map(
            static fn (array $f): string => $f['value'],
            $fields
        );
    }

    /**
     * Person profile fields (static, filterable).
     *
     * Top-level attributes on PATCH /people/{uuid}.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getPersonProfileFields(): array
    {
        $fields = [
            ['value' => 'attributes.given_name',       'label' => __('First Name', 'wicket-gf')],
            ['value' => 'attributes.family_name',      'label' => __('Last Name', 'wicket-gf')],
            ['value' => 'attributes.additional_name',  'label' => __('Additional Name', 'wicket-gf')],
            ['value' => 'attributes.alternate_name',   'label' => __('Alternate Name', 'wicket-gf')],
            ['value' => 'attributes.full_name',        'label' => __('Full Name', 'wicket-gf')],
            ['value' => 'attributes.gender',           'label' => __('Gender', 'wicket-gf')],
            ['value' => 'attributes.honorific_prefix', 'label' => __('Honorific Prefix', 'wicket-gf')],
            ['value' => 'attributes.honorific_suffix', 'label' => __('Honorific Suffix', 'wicket-gf')],
            ['value' => 'attributes.preferred_pronoun', 'label' => __('Preferred Pronoun', 'wicket-gf')],
            ['value' => 'attributes.job_title',        'label' => __('Job Title', 'wicket-gf')],
            ['value' => 'attributes.birth_date',       'label' => __('Birth Date', 'wicket-gf')],
            ['value' => 'attributes.language',         'label' => __('Language', 'wicket-gf')],
            ['value' => 'attributes.nickname',         'label' => __('Nickname', 'wicket-gf')],
            ['value' => 'attributes.job_function',     'label' => __('Job Function', 'wicket-gf')],
            ['value' => 'attributes.job_level',        'label' => __('Job Level', 'wicket-gf')],
        ];

        return apply_filters('wicket_gf_mdp_person_profile_fields', $fields);
    }

    /**
     * Organization profile fields (static, filterable).
     *
     * Top-level attributes on PATCH /organizations/{uuid}.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getOrgProfileFields(): array
    {
        $fields = [
            ['value' => 'attributes.legal_name', 'label' => __('Legal Name', 'wicket-gf')],
        ];

        return apply_filters('wicket_gf_mdp_org_profile_fields', $fields);
    }

    /**
     * Additional Info fields (dynamic discovery via json_schemas API).
     *
     * The MDP API validates each data_field `value` against the schema's
     * JSON Schema, so a target must point at one scalar property inside a
     * schema, not at the schema as a whole. Values use the composite
     * format `data_field.<schema_key>.<property_name>`.
     * Results are cached as a transient for CACHE_TTL seconds.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getAdditionalInfoFields(): array
    {
        $fields = [];
        foreach ($this->getScalarSchemaProperties() as $prop) {
            $fields[] = [
                'value' => $prop['value'],
                'label' => $prop['label'],
            ];
        }

        return $fields;
    }

    /**
     * Map of additional-info target value string to schema property type.
     *
     * Used by the sync engine to cast submitted GF strings to the type the
     * schema expects (booleans/numbers must not be sent as strings).
     *
     * @return array<string, string> value string => 'boolean'|'number'|'integer'|'string'
     */
    public function getPropertyTypeMap(): array
    {
        $map = [];
        foreach ($this->getScalarSchemaProperties() as $prop) {
            $map[$prop['value']] = $prop['type'];
        }

        return $map;
    }

    /**
     * Scalar properties across all JSON Schemas (cached raw discovery).
     *
     * @return array<int, array{value: string, label: string, type: string}>
     */
    protected function getScalarSchemaProperties(): array
    {
        return $this->getSchemaDiscoveryPayload()['properties'];
    }

    /**
     * Map of schema slug to schema UUID (cached raw discovery).
     *
     * Lets the sync engine match legacy data_fields entries that only carry
     * a `$schema` URN instead of `schema_slug`.
     *
     * @return array<string, string> slug => schema UUID
     */
    public function getSchemaIdMap(): array
    {
        return $this->getSchemaDiscoveryPayload()['ids'];
    }

    /**
     * Whether the most recent discovery attempt failed (API unreachable,
     * error response, or preference source unavailable).
     *
     * Consumers must treat an empty field list as "unknown" while this is
     * true: never strip saved mappings based on a failed discovery.
     */
    public function discoveryFailed(): bool
    {
        if ($this->discovery_failed) {
            return true;
        }

        return (bool) get_transient(self::CACHE_KEY_API_FAIL);
    }

    /**
     * Cached discovery payload: scalar properties + slug-to-UUID map.
     *
     * @return array{properties: array<int, array{value: string, label: string, type: string}>, ids: array<string, string>}
     */
    protected function getSchemaDiscoveryPayload(): array
    {
        if (get_transient(self::CACHE_KEY_API_FAIL)) {
            $this->discovery_failed = true;

            return ['properties' => [], 'ids' => []];
        }

        $cached = get_transient(self::CACHE_KEY_SCHEMAS);
        if (is_array($cached) && isset($cached['properties'], $cached['ids'])) {
            return $cached;
        }

        $payload = $this->discoverSchemaFields();

        if ($payload['failed']) {
            $this->discovery_failed = true;
            set_transient(self::CACHE_KEY_API_FAIL, 1, self::CACHE_TTL_FAIL);

            return ['properties' => [], 'ids' => []];
        }

        $ttl = (int) apply_filters('wicket_gf_mdp_discovery_cache_ttl', self::CACHE_TTL_DEFAULT);
        if (!empty($payload['properties'])) {
            set_transient(self::CACHE_KEY_SCHEMAS, $payload, $ttl);
        }

        return $payload;
    }

    /**
     * Preferences fields (dynamic discovery via person communications).
     *
     * Discovers available communication sublist keys from the MDP API.
     * Results are cached as a transient for CACHE_TTL seconds.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getPreferencesFields(): array
    {
        if (get_transient(self::CACHE_KEY_API_FAIL)) {
            $this->discovery_failed = true;

            return [];
        }

        $cached = get_transient(self::CACHE_KEY_PREFS);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $fields = $this->discoverPreferenceFields();

        if ($fields === []) {
            // Empty preferences are ambiguous (no sublists configured vs no
            // API access). Treat as failure so consumers never strip saved
            // preference mappings on a suspected outage.
            $this->discovery_failed = true;
            set_transient(self::CACHE_KEY_API_FAIL, 1, self::CACHE_TTL_FAIL);

            return [];
        }

        $ttl = (int) apply_filters('wicket_gf_mdp_discovery_cache_ttl', self::CACHE_TTL_DEFAULT);
        set_transient(self::CACHE_KEY_PREFS, $fields, $ttl);

        return $fields;
    }

    /**
     * Force-refresh all cached discovery results.
     */
    public function refreshCache(): void
    {
        delete_transient(self::CACHE_KEY_SCHEMAS);
        delete_transient(self::CACHE_KEY_PREFS);
        delete_transient(self::CACHE_KEY_API_FAIL);
    }

    /**
     * Discover scalar properties from the JSON Schemas API endpoint.
     *
     * Fetches GET /json_schemas and walks each schema's `properties`.
     * Only scalar-typed properties are exposed (string, number, integer,
     * boolean, or an enum with a scalar type). Array/object properties
     * (repeaters) need structural values a single GF field cannot supply
     * and are skipped.
     *
     * @return array{properties: array<int, array{value: string, label: string, type: string}>, ids: array<string, string>, failed: bool}
     */
    protected function discoverSchemaFields(): array
    {
        if (!function_exists('wicket_api_client')) {
            return ['properties' => [], 'ids' => [], 'failed' => true];
        }

        try {
            $client = wicket_api_client();
            if (!$client) {
                return ['properties' => [], 'ids' => [], 'failed' => true];
            }

            $response = $client->get('json_schemas');
            $schemas = $response['data'] ?? [];

            if (empty($schemas) || !is_array($schemas)) {
                // API answered but returned nothing usable. Treat as failure:
                // a tenant with zero schemas is not a real state, and treating
                // it as empty would let the sanitizer wipe saved mappings.
                return ['properties' => [], 'ids' => [], 'failed' => true];
            }

            $fields = [];
            $ids = [];
            foreach ($schemas as $schema) {
                $attrs = $schema['attributes'] ?? [];
                $key = $attrs['key'] ?? '';
                if ($key === '') {
                    continue;
                }

                // Record the schema UUID so legacy `$schema`-keyed data_fields
                // entries can be matched during merge.
                $schema_id = is_string($schema['id'] ?? null) ? $schema['id'] : '';
                if ($schema_id !== '') {
                    $ids[sanitize_text_field($key)] = $schema_id;
                }

                // Try to get a human-readable label from ui_schema or fall back to key
                $uiSchema = $attrs['ui_schema'] ?? [];
                // Use English as canonical label source for cache stability.
                // Prevents locale-dependent cache poisoning across admins.
                $language = 'en';

                $schemaLabel = $uiSchema['ui:i18n']['label'][$language]
                    ?? $uiSchema['ui:i18n']['label']['en']
                    ?? $attrs['schema']['title']
                    ?? ucwords(str_replace(['_', '-'], ' ', $key));

                $properties = $attrs['schema']['properties'] ?? [];
                if (!is_array($properties)) {
                    continue;
                }

                foreach ($properties as $prop_name => $definition) {
                    if (!is_array($definition)) {
                        continue;
                    }

                    $type = is_string($definition['type'] ?? null) ? $definition['type'] : '';
                    if (isset($definition['enum']) && $type === '') {
                        $type = 'string';
                    }

                    if (!in_array($type, ['string', 'number', 'integer', 'boolean'], true)) {
                        continue;
                    }

                    $propLabel = $definition['title']
                        ?? ucwords(str_replace(['_', '-'], ' ', (string) $prop_name));

                    $fields[] = [
                        'value' => 'data_field.' . sanitize_text_field($key) . '.' . sanitize_text_field((string) $prop_name),
                        'label' => sanitize_text_field($schemaLabel) . ': ' . sanitize_text_field($propLabel),
                        'type'  => $type,
                    ];
                }
            }

            return ['properties' => $fields, 'ids' => $ids, 'failed' => false];
        } catch (\Throwable $e) {
            return ['properties' => [], 'ids' => [], 'failed' => true];
        }
    }

    /**
     * Discover preference fields from MDP communications config.
     *
     * Fetches a person record to extract available sublist keys,
     * or falls back to a dedicated config endpoint if available.
     *
     * @return array<array{value: string, label: string}>
     */
    protected function discoverPreferenceFields(): array
    {
        if (!function_exists('wicket_api_client')) {
            return [];
        }

        try {
            $client = wicket_api_client();
            if (!$client) {
                return [];
            }

            // Use the communications config endpoint if available,
            // otherwise fetch a sample person to discover sublists
            try {
                $response = $client->get('people/communications/config');
                $config = $response['data'] ?? [];

                if (!empty($config)) {
                    return $this->parsePreferenceConfig($config);
                }
            } catch (\Throwable $e) {
                // Endpoint may not exist; fall through to person-based discovery
            }

            // Fallback: discover from a person record's communications sublists
            return $this->discoverPreferencesFromPerson($client);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Parse a communications config response into field options.
     *
     * @param array $config Communications config data from API.
     * @return array<array{value: string, label: string}>
     */
    protected function parsePreferenceConfig(array $config): array
    {
        $fields = [];

        // Top-level email preference
        if (isset($config['email'])) {
            $fields[] = [
                'value' => 'communications.email',
                'label' => __('Email Opt-in', 'wicket-gf'),
            ];
        }

        // Sublist preferences
        $sublists = $config['sublists'] ?? [];
        if (is_array($sublists)) {
            foreach ($sublists as $key => $meta) {
                $label = is_array($meta) && isset($meta['label'])
                    ? $meta['label']
                    : ucwords(str_replace(['_', '-'], ' ', (string) $key));

                $fields[] = [
                    'value' => 'communications.sublists.' . sanitize_text_field((string) $key),
                    'label' => sanitize_text_field($label),
                ];
            }
        }

        return $fields;
    }

    /**
     * Discover preference fields by examining a person's communications data.
     *
     * Fallback when no dedicated config endpoint exists.
     *
     * @param mixed $client Wicket API client instance.
     * @return array<array{value: string, label: string}>
     */
    protected function discoverPreferencesFromPerson($client): array
    {
        // Try to get current user's person UUID
        $person_uuid = function_exists('wicket_current_person_uuid')
            ? wicket_current_person_uuid()
            : null;

        if (empty($person_uuid)) {
            return [];
        }

        try {
            // Raw GET, not the SDK fetch helper: the SDK model throws on some
            // person payloads, and we only need the attributes object.
            $response = $client->get("people/$person_uuid");
            $person_array = is_array($response) ? $response : wicket_convert_obj_to_array($response);

            $communications = $person_array['data']['attributes']['data']['communications'] ?? [];
            $sublists = $communications['sublists'] ?? [];

            if (!is_array($sublists)) {
                $sublists = [];
            }

            $fields = [];

            // Email preference
            if (array_key_exists('email', $communications)) {
                $fields[] = [
                    'value' => 'communications.email',
                    'label' => __('Email Opt-in', 'wicket-gf'),
                ];
            }

            // Each sublist key becomes a preference option
            foreach (array_keys($sublists) as $key) {
                $fields[] = [
                    'value' => 'communications.sublists.' . sanitize_text_field((string) $key),
                    'label' => sanitize_text_field(ucwords(str_replace(['_', '-'], ' ', (string) $key))),
                ];
            }

            return $fields;
        } catch (\Exception $e) {
            return [];
        }
    }
}
