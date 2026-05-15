<?php

declare(strict_types=1);

namespace WicketGF;

// No direct access
defined('ABSPATH') || exit;

/**
 * MDP Type Compatibility checker.
 *
 * Warns when a Gravity Forms field type is unlikely to produce a value
 * compatible with a given MDP target field.
 *
 * Design: warnings only, never hard blocks. GF values are ultimately strings
 * sent to the API; this catches obvious mismatches (e.g. multi-select checkbox
 * → boolean communication preference) before they reach production.
 */
class MdpTypeCompatibility
{
    /**
     * GF field types that can produce multiple selected values.
     */
    private const MULTI_VALUE_TYPES = [
        'checkbox',
        'multiselect',
        'post_category',
    ];

    /**
     * Target field prefixes that expect boolean-ish values.
     *
     * Preferences sublists and email opt-in are boolean toggles in MDP.
     * A multi-value GF field will serialize to a comma-separated string
     * like "Option 1, Option 2" which is almost certainly wrong.
     */
    private const BOOLEAN_TARGET_PREFIXES = [
        'communications.',
    ];

    /**
     * Check whether a GF field type is compatible with a target field.
     *
     * @param string $gf_field_type  GF field type (e.g. 'text', 'checkbox').
     * @param string $target_field   MDP target field (e.g. 'communications.email').
     * @return array{compatible: bool, warning: string}
     */
    public function check(string $gf_field_type, string $target_field): array
    {
        $warning = $this->getWarning($gf_field_type, $target_field);

        return [
            'compatible' => $warning === '',
            'warning'    => $warning,
        ];
    }

    /**
     * Get a warning message for an incompatible pairing, or empty string.
     *
     * @param string $gf_field_type GF field type.
     * @param string $target_field  MDP target field.
     * @return string Warning message or empty.
     */
    public function getWarning(string $gf_field_type, string $target_field): string
    {
        // Multi-value → boolean is the primary incompatibility
        if (in_array($gf_field_type, self::MULTI_VALUE_TYPES, true)
            && $this->isBooleanTarget($target_field)) {
            return sprintf(
                /* translators: %s: MDP target field name */
                __('Multi-value field mapped to a boolean target (%s). Only the first selected value will be sent.', 'wicket-gf'),
                $this->getTargetLabel($target_field)
            );
        }

        return '';
    }

    /**
     * Check whether a target field expects a boolean value.
     */
    private function isBooleanTarget(string $target_field): bool
    {
        foreach (self::BOOLEAN_TARGET_PREFIXES as $prefix) {
            if (str_starts_with($target_field, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a human-readable label for a target field.
     */
    private function getTargetLabel(string $target_field): string
    {
        if ($target_field === 'communications.email') {
            return __('Email Opt-in', 'wicket-gf');
        }

        if (str_starts_with($target_field, 'communications.sublists.')) {
            $key = substr($target_field, strlen('communications.sublists.'));

            return sprintf(__('Sublist: %s', 'wicket-gf'), $key);
        }

        return $target_field;
    }

    /**
     * Build a JS-compatible compatibility matrix for all GF field types
     * against all known boolean targets.
     *
     * Returns an object keyed by GF field type, with arrays of target field
     * values that are incompatible.
     *
     * @param MdpFieldDiscovery $discovery Field discovery service.
     * @return array<string, string[]>
     */
    public function buildJsMatrix(MdpFieldDiscovery $discovery): array
    {
        $boolean_targets = [];

        // Collect all boolean target fields from preferences
        $pref_fields = $discovery->getPreferencesFields();
        foreach ($pref_fields as $field) {
            $value = $field['value'] ?? '';
            if ($this->isBooleanTarget($value)) {
                $boolean_targets[] = $value;
            }
        }

        $matrix = [];
        foreach (self::MULTI_VALUE_TYPES as $type) {
            $matrix[$type] = $boolean_targets;
        }

        return $matrix;
    }
}
