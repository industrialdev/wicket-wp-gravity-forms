<?php

declare(strict_types=1);

namespace WicketGF;

use GuzzleHttp\Exception\RequestException;

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
     */
    private const CACHE_KEY_SCHEMAS = 'wicket_gf_mdp_schemas';

    /**
     * Transient key for caching preferences discovery results.
     */
    private const CACHE_KEY_PREFS = 'wicket_gf_mdp_preferences';

    /**
     * Cache expiration in seconds (12 hours).
     */
    private const CACHE_TTL = 43200;

    /**
     * Get all target fields grouped by target object.
     *
     * @return array<string, array<array{value: string, label: string}>>
     */
    public function getAllTargetFields(): array
    {
        $objects = ['person_profile', 'org_profile', 'additional_info', 'preferences'];
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
            'person_profile'   => $this->getPersonProfileFields(),
            'org_profile'      => $this->getOrgProfileFields(),
            'additional_info'  => $this->getAdditionalInfoFields(),
            'preferences'      => $this->getPreferencesFields(),
            default            => [],
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
            static fn(array $f): string => $f['value'],
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
            ['value' => 'attributes.preferred_pronoun','label' => __('Preferred Pronoun', 'wicket-gf')],
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
     * Each JSON Schema key becomes a target field option.
     * Results are cached as a transient for CACHE_TTL seconds.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getAdditionalInfoFields(): array
    {
        $cached = get_transient(self::CACHE_KEY_SCHEMAS);
        if ($cached !== false) {
            return $cached;
        }

        $fields = $this->discoverSchemaFields();

        set_transient(self::CACHE_KEY_SCHEMAS, $fields, self::CACHE_TTL);

        return $fields;
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
        $cached = get_transient(self::CACHE_KEY_PREFS);
        if ($cached !== false) {
            return $cached;
        }

        $fields = $this->discoverPreferenceFields();

        set_transient(self::CACHE_KEY_PREFS, $fields, self::CACHE_TTL);

        return $fields;
    }

    /**
     * Force-refresh all cached discovery results.
     */
    public function refreshCache(): void
    {
        delete_transient(self::CACHE_KEY_SCHEMAS);
        delete_transient(self::CACHE_KEY_PREFS);
    }

    /**
     * Discover fields from JSON Schemas API endpoint.
     *
     * Fetches GET /json_schemas and maps each schema's key + title
     * to the {value, label} format.
     *
     * @return array<array{value: string, label: string}>
     */
    protected function discoverSchemaFields(): array
    {
        if (!function_exists('wicket_api_client')) {
            return [];
        }

        try {
            $client = wicket_api_client();
            $response = $client->get('json_schemas');
            $schemas = $response['data'] ?? [];

            if (empty($schemas) || !is_array($schemas)) {
                return [];
            }

            $fields = [];
            foreach ($schemas as $schema) {
                $attrs = $schema['attributes'] ?? [];
                $key = $attrs['key'] ?? '';
                if ($key === '') {
                    continue;
                }

                // Try to get a human-readable label from ui_schema or fall back to key
                $uiSchema = $attrs['ui_schema'] ?? [];
                $language = function_exists('get_user_locale')
                    ? strtok(get_user_locale(), '-')
                    : 'en';

                $label = $uiSchema['ui:i18n']['label'][$language]
                    ?? $uiSchema['ui:i18n']['label']['en']
                    ?? $attrs['schema']['title']
                    ?? ucwords(str_replace(['_', '-'], ' ', $key));

                $fields[] = [
                    'value' => 'data_field.' . $key,
                    'label' => $label,
                ];
            }

            return $fields;
        } catch (RequestException $e) {
            return [];
        } catch (\Exception $e) {
            return [];
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

            // Use the communications config endpoint if available,
            // otherwise fetch a sample person to discover sublists
            try {
                $response = $client->get('people/communications/config');
                $config = $response['data'] ?? [];

                if (!empty($config)) {
                    return $this->parsePreferenceConfig($config);
                }
            } catch (RequestException $e) {
                // Endpoint may not exist; fall through to person-based discovery
            }

            // Fallback: discover from a person record's communications sublists
            return $this->discoverPreferencesFromPerson($client);
        } catch (\Exception $e) {
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
                    'value' => 'communications.sublists.' . $key,
                    'label' => $label,
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
            $person = $client->people->fetch($person_uuid);
            if (function_exists('wicket_convert_obj_to_array')) {
                $person_array = wicket_convert_obj_to_array($person);
            } else {
                $person_array = (array) $person;
            }

            $communications = $person_array['data']['communications'] ?? [];
            $sublists = $communications['sublists'] ?? [];

            if (!is_array($sublists)) {
                return [];
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
                    'value' => 'communications.sublists.' . $key,
                    'label' => ucwords(str_replace(['_', '-'], ' ', $key)),
                ];
            }

            return $fields;
        } catch (\Exception $e) {
            return [];
        }
    }
}
