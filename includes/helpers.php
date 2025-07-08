<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wicket_gf_get_form_id_by_slug')) {
    function wicket_gf_get_form_id_by_slug($slug)
    {
        $current_mappings = get_option('wicket_gf_slug_mapping');
        if (empty($current_mappings)) {
            return false;
        } else {
            $current_mappings = json_decode($current_mappings, true);

            if (isset($current_mappings[$slug])) {
                return $current_mappings[$slug];
            } else {
                return false;
            }
        }
    }
}

if (!function_exists('wicket_get_gf_mapping_addon')) {
    function wicket_get_gf_mapping_addon()
    {
        return GFWicketMappingAddOn::get_instance();
    }
}
