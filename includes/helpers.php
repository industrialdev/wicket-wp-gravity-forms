<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!function_exists('wicket_gf_get_form_id_by_slug')) {
    /**
     * Retrieve the Gravity Forms ID associated with a given slug.
     *
     * @param string $slug Gravity Forms slug configured in Wicket mapping.
     *
     * @return int|false Form ID on success, false when slug has no mapping.
     */
    function wicket_gf_get_form_id_by_slug($slug)
    {
        $current_mappings = get_option('wicket_gf_slug_mapping');
        if (empty($current_mappings)) {
            return false;
        }

        $current_mappings = json_decode($current_mappings, true);

        if (!is_array($current_mappings) || !isset($current_mappings[$slug])) {
            return false;
        }

        return absint($current_mappings[$slug]);
    }
}

if (!function_exists('wicket_get_gf_mapping_addon')) {
    /**
     * Access the singleton instance of the Wicket Gravity Forms mapping add-on.
     *
     * @return GFWicketMappingAddOn|null Mapping add-on instance when available, null otherwise.
     */
    function wicket_get_gf_mapping_addon()
    {
        return GFWicketMappingAddOn::get_instance();
    }
}
