<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fix Gravity Forms JavaScript syntax error when adding fields
 * by properly escaping apostrophes in JSON output.
 *
 * Affects GF v2.9.14 and v2.9.14.1
 */
function wicket_gf_add_field()
{
    // Copy the original logic but with proper JSON encoding
    check_ajax_referer('rg_add_field', 'rg_add_field');

    if (!GFCommon::current_user_can_any('gravityforms_edit_forms')) {
        wp_die(-1, 403);
    }

    $field_json = stripslashes_deep($_POST['field']);
    $field_properties = GFCommon::json_decode($field_json, true);
    $field = GF_Fields::create($field_properties);
    $field->sanitize_settings();

    $index = rgpost('index');
    if ($index != 'undefined') {
        $index = absint($index);
    }

    require_once GFCommon::get_base_path() . '/form_display.php';

    $form_id = absint(rgpost('form_id'));
    $form = GFFormsModel::get_form_meta($form_id);

    $field_html = GFFormDisplay::get_field($field, '', true, $form);
    $field_html_json = json_encode($field_html, JSON_HEX_APOS);

    $field_json = json_encode($field, JSON_HEX_APOS);

    die("EndAddField($field_json, " . $field_html_json . ", $index);");
}

/**
 * Apply the fix only for affected Gravity Forms versions.
 */
function wicket_maybe_fix_gf_add_field()
{
    // Check if Gravity Forms is active and get version
    if (!class_exists('GFForms')) {
        return;
    }

    $gf_version = GFForms::$version;
    $affected_versions = ['2.9.14', '2.9.14.1'];

    // Only apply fix for affected versions
    if (in_array($gf_version, $affected_versions)) {
        // Remove the default handler
        remove_action('wp_ajax_rg_add_field', ['GFForms', 'add_field']);

        // Add our custom handler
        add_action('wp_ajax_rg_add_field', 'wicket_gf_add_field');
    }
}

/**
 * Hides the label for Gravity Forms consent fields when the label placement is set to 'hidden_label'.
 *
 * This function injects a CSS style to hide the label of consent fields in Gravity Forms
 * when the field's label placement is configured as 'hidden_label'. It hooks into the
 * 'gform_field_content' filter to modify the field's HTML output.
 *
 * @param string $content The original field content HTML.
 * @param GF_Field $field The Gravity Forms field object.
 * @return string The modified field content with the label hidden if conditions are met.
 */
function wicket_gf_hide_consent_label($content, $field)
{
    if ($field->type === 'consent' && isset($field->labelPlacement) && $field->labelPlacement === 'hidden_label') {
        $field_id = 'input_' . $field->formId . '_' . $field->id;
        $css = '<style>.gform_wrapper.gravity-theme label[for=\'input_' . $field_id . '\'].gfield_label { display: none; }</style>';
        $content = $css . $content;
    }

    return $content;
}
add_filter('gform_field_content', 'wicket_gf_hide_consent_label', 10, 2);
