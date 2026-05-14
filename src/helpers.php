<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!function_exists('wicket_gf_get_form_id_by_slug')) {
    /**
     * Retrieve the Gravity Forms ID associated with a given slug.
     *
     * Queries the canonical wicket_gf_slug_mapping option first (transient-cached),
     * then falls back to the per-form wicket_mdp_form_slug property for migration.
     *
     * @param string $slug Gravity Forms slug.
     *
     * @return int|false Form ID on success, false when slug has no mapping.
     */
    function wicket_gf_get_form_id_by_slug($slug)
    {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return false;
        }

        $cache_key = 'wicket_gf_form_slug_' . $slug;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return (int) $cached > 0 ? (int) $cached : false;
        }

        // Primary: canonical wicket_gf_slug_mapping option
        $current_mappings = get_option('wicket_gf_slug_mapping');
        if (!empty($current_mappings)) {
            $current_mappings = json_decode($current_mappings, true);
            if (is_array($current_mappings) && isset($current_mappings[$slug])) {
                $form_id = absint($current_mappings[$slug]);
                if ($form_id > 0) {
                    set_transient($cache_key, $form_id, HOUR_IN_SECONDS);

                    return $form_id;
                }
            }
        }

        // Fallback: per-form wicket_mdp_form_slug property (migration path)
        $forms = GFAPI::get_forms();
        if (is_array($forms)) {
            foreach ($forms as $form) {
                $form_slug = $form['wicket_mdp_form_slug'] ?? '';
                if ($form_slug === $slug) {
                    set_transient($cache_key, (int) $form['id'], HOUR_IN_SECONDS);

                    return (int) $form['id'];
                }
            }
        }

        // Truly not found anywhere — cache the miss
        set_transient($cache_key, 0, HOUR_IN_SECONDS);

        return false;
    }
}

if (!function_exists('wicket_gf_flush_slug_cache')) {
    /**
     * Flush the transient cache for a given form slug (or all slug caches).
     *
     * @param string|null $slug Specific slug to flush, or null to flush all.
     *
     * @return void
     */
    function wicket_gf_flush_slug_cache(?string $slug = null): void
    {
        if ($slug !== null) {
            delete_transient('wicket_gf_form_slug_' . sanitize_title($slug));
        } else {
            global $wpdb;
            $like = $wpdb->esc_like('_transient_wicket_gf_form_slug_') . '%';
            $like_timeout = $wpdb->esc_like('_transient_timeout_wicket_gf_form_slug_') . '%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout));
        }
    }
}

if (!function_exists('wicket_get_gf_mapping_addon')) {
    /**
     * Access the singleton instance of the Wicket Gravity Forms mapping add-on.
     *
     * @return WicketGF\MappingAddOn|null Mapping add-on instance when available, null otherwise.
     */
    function wicket_get_gf_mapping_addon()
    {
        return WicketGF\MappingAddOn::get_instance();
    }
}

if (!function_exists('wicket_gf_normalize_field_slug')) {
    /**
     * Normalize a string into a slug suitable for Gravity Forms field identification.
     *
     * Uses the same sanitization WordPress applies to post slugs (sanitize_title)
     * so field slugs are consistent with WP conventions.
     *
     * @param string $slug Raw slug input.
     *
     * @return string Normalized slug (lowercase, hyphens, no special chars).
     */
    function wicket_gf_normalize_field_slug(string $slug): string
    {
        return sanitize_title($slug);
    }
}

if (!function_exists('wicket_gf_resolve_form')) {
    /**
     * Resolve a form identifier to a GF form array.
     *
     * Accepts a form array (passthrough), a numeric form ID, or a form slug
     * registered in the Wicket slug mapping option.
     *
     * @param array|int|string $form Form object, form ID, or form slug.
     *
     * @return array|null Form array on success, null when unresolvable.
     */
    function wicket_gf_resolve_form($form): ?array
    {
        // Already a form array
        if (is_array($form)) {
            return $form;
        }

        // Numeric form ID
        if (is_int($form) || (is_string($form) && ctype_digit($form))) {
            $resolved = GFAPI::get_form((int) $form);

            return $resolved ?: null;
        }

        // String slug — resolve via Wicket form slug mapping
        if (is_string($form)) {
            $form_id = wicket_gf_get_form_id_by_slug($form);
            if ($form_id) {
                $resolved = GFAPI::get_form($form_id);

                return $resolved ?: null;
            }
        }

        return null;
    }
}

if (!function_exists('wicket_gf_get_field_by_slug')) {
    /**
     * Find a Gravity Forms field by its Wicket field slug.
     *
     * @param array|int|string $form Form object, form ID, or form slug.
     * @param string           $slug The field slug to look up.
     *
     * @return GF_Field|null Field instance on success, null when not found.
     */
    function wicket_gf_get_field_by_slug($form, string $slug): ?GF_Field
    {
        $form = wicket_gf_resolve_form($form);
        if ($form === null || $slug === '' || empty($form['fields'])) {
            return null;
        }

        foreach ($form['fields'] as $field) {
            if (isset($field->wicket_field_slug) && $field->wicket_field_slug === $slug) {
                return $field;
            }
        }

        return null;
    }
}

if (!function_exists('wicket_gf_get_field_id_by_slug')) {
    /**
     * Resolve a field slug to its numeric Gravity Forms field ID.
     *
     * Useful for code that needs to pass a field ID to GF APIs but wants
     * the portability of slug-based references.
     *
     * @param array|int|string $form Form object, form ID, or form slug.
     * @param string           $slug The field slug to resolve.
     *
     * @return int|null Field ID on success, null when slug is not mapped.
     */
    function wicket_gf_get_field_id_by_slug($form, string $slug): ?int
    {
        $field = wicket_gf_get_field_by_slug($form, $slug);

        return $field ? (int) $field->id : null;
    }
}
