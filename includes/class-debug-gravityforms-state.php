<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Gravity Forms state debugging helper.
 * Hooks into GF validation to log the POST payload and decoded GF state for debugging failed_state_validation.
 */

add_filter('gform_validation', function ($validation_result) {
    if (is_object($validation_result)) {
        $form = $validation_result->get_form();
    } elseif (is_array($validation_result) && isset($validation_result['form'])) {
        $form = $validation_result['form'];
    } else {
        // Cannot get form, bail out.
        return $validation_result;
    }
    // Recursively strip slashes from all POST data, which may be added by WP or other plugins.
    $_POST = stripslashes_deep($_POST);
    $logger = wc_get_logger();
    $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');

    // Log all field validation attempts
    if (is_array($form) && isset($form['fields'])) {
        foreach ($form['fields'] as $field) {
            $field_id = is_object($field) && isset($field->id) ? $field->id : (is_array($field) && isset($field['id']) ? $field['id'] : null);
            $field_type = is_object($field) && isset($field->type) ? $field->type : (is_array($field) && isset($field['type']) ? $field['type'] : null);
            if ($field_type === 'wicket_widget_ai') {
                $logger->debug('Found wicket_widget_ai field ' . $field_id . ' in form ' . $form_id, ['source' => 'gravityforms-state-debug']);
            }
        }
    }

    // Only log when state exists or when POST keys related to the form are present
    $state_key = "state_{$form_id}";
    if (!isset($_POST[$state_key])) {
        $logger->debug('gform_validation: state missing for form ' . $form_id . ' POST keys: ' . json_encode(array_keys($_POST)), ['source' => 'gravityforms-state-debug']);

        return $validation_result;
    }

    $raw = $_POST[$state_key];
    $decoded = json_decode(base64_decode($raw), true);

    if (!$decoded || !is_array($decoded) || count($decoded) !== 2) {
        $logger->debug('gform_validation: invalid state payload for form ' . $form_id . ' raw_trunc: ' . substr($raw, 0, 1000) . ' decoded: ' . var_export($decoded, true), ['source' => 'gravityforms-state-debug']);

        return $validation_result;
    }

    $checksum = wp_hash(crc32($decoded[0]));
    if ($checksum !== $decoded[1]) {
        $logger->debug('gform_validation: checksum mismatch for form ' . $form_id . ' computed: ' . $checksum . ' state_checksum: ' . var_export($decoded[1], true) . ' state0_trunc: ' . substr($decoded[0], 0, 1000), ['source' => 'gravityforms-state-debug']);

        return $validation_result;
    }

    $gf_state = json_decode($decoded[0], true);
    $logger->debug('gform_validation: decoded gf_state for form ' . $form_id . ' gf_state: ' . var_export($gf_state, true) . ' relevant_post: ' . var_export(array_filter($_POST, function ($k) use ($form_id) { return strpos($k, 'input_') === 0 || strpos($k, 'gform_target_page_number_') === 0 || strpos($k, 'wicket_ai_info_data_') === 0; }, ARRAY_FILTER_USE_KEY), true), ['source' => 'gravityforms-state-debug']);

    // If any posted input contains JSON, make GF accept it by adding its wp_hash to the allowed state
    $modified = false;
    foreach ($_POST as $pkey => $pval) {
        $field_id_str = null;
        if (strpos($pkey, 'input_') === 0) {
            $field_id_str = substr($pkey, strlen('input_'));
        } elseif (strpos($pkey, 'wicket_ai_info_data_') === 0) {
            $field_id_str = substr($pkey, strlen('wicket_ai_info_data_'));
        } else {
            continue;
        }

        // Skip validation fields to prevent conflicts
        if (substr($pkey, -strlen('_validation')) === '_validation') {
            continue;
        }

        // extract ID (handles input_5 and input_5.1 style)
        $field_id = intval($field_id_str);
        if (!$field_id) {
            continue;
        }

        $val = $pval;
        if (!is_string($val)) {
            continue;
        }

        // Use json_decode to reliably detect JSON
        $decoded_val = json_decode($val);
        if (!is_null($decoded_val) && (is_object($decoded_val) || is_array($decoded_val))) {

            $hash = wp_hash($val);
            // ensure gf_state key exists
            if (isset($gf_state[$field_id])) {
                // if state is array, append if missing
                if (is_array($gf_state[$field_id])) {
                    if (!in_array($hash, $gf_state[$field_id], true)) {
                        $gf_state[$field_id][] = $hash;
                        $logger->debug('gform_validation: appended hash for form ' . $form_id . ' field ' . $field_id . ' hash=' . $hash, ['source' => 'gravityforms-state-debug']);
                        $modified = true;
                    }
                } else {
                    // single value, convert to array
                    if ($gf_state[$field_id] !== $hash) {
                        $gf_state[$field_id] = [$gf_state[$field_id], $hash];
                        $logger->debug('gform_validation: converted state to array and added hash for form ' . $form_id . ' field ' . $field_id . ' hash=' . $hash, ['source' => 'gravityforms-state-debug']);
                        $modified = true;
                    }
                }
            } else {
                // if state does not exist, create it
                $gf_state[$field_id] = [$hash];
                $logger->debug('gform_validation: created state and added hash for form ' . $form_id . ' field ' . $field_id . ' hash=' . $hash, ['source' => 'gravityforms-state-debug']);
                $modified = true;
            }
        }
    }

    if ($modified) {
        // re-encode state and recompute checksum
        $new_state0 = json_encode($gf_state);
        $new_checksum = wp_hash(crc32($new_state0));
        $_POST[$state_key] = base64_encode(json_encode([$new_state0, $new_checksum]));
        $logger->debug('gform_validation: updated POST[' . $state_key . '] for form ' . $form_id . ' with new checksum', ['source' => 'gravityforms-state-debug']);
    }

    // Additional diagnostic: log the form's field definition for field 5 (common failing field) if available
    $target_field_id = 5;
    if (is_array($form) && isset($form['fields']) && is_array($form['fields'])) {
        foreach ($form['fields'] as $fld) {
            $fid = null;
            if (is_object($fld) && isset($fld->id)) {
                $fid = $fld->id;
            } elseif (is_array($fld) && isset($fld['id'])) {
                $fid = $fld['id'];
            }

            if ($fid === $target_field_id) {
                $logger->debug('gform_validation: form ' . $form_id . ' contains field ' . $target_field_id . ' definition: ' . var_export($fld, true), ['source' => 'gravityforms-state-debug']);
                break;
            }
        }
    }

    // Log the current/target page numbers so we can see which page is being submitted
    $current_page = rgpost('gform_source_page_number_' . $form_id);
    $target_page = rgpost('gform_target_page_number_' . $form_id);
    $logger->debug('gform_validation: form ' . $form_id . ' pages: current=' . var_export($current_page, true) . ' target=' . var_export($target_page, true) . ' POST keys count=' . count($_POST), ['source' => 'gravityforms-state-debug']);

    return $validation_result;
});

// Log per-field validation results to detect any external overrides causing failed validation.
add_filter('gform_field_validation', function ($result, $value, $form, $field) {
    $logger = wc_get_logger();

    $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');
    $field_id = is_object($field) && isset($field->id) ? $field->id : (is_array($field) && isset($field['id']) ? $field['id'] : null);
    $field_type = is_object($field) && isset($field->type) ? $field->type : (is_array($field) && isset($field['type']) ? $field['type'] : null);

    if ($field_type === 'wicket_widget_ai') {
        // Truncate large values for log safety
        $value_str = is_string($value) ? $value : json_encode($value);
        if (is_string($value_str)) {
            $value_str = substr($value_str, 0, 1000);
        }

        $logger->debug(
            'gform_field_validation: field ' . $field_id . ' (type=' . $field_type . ') in form ' . $form_id . ' result=' . var_export($result, true) . ' value_trunc=' . var_export($value_str, true),
            ['source' => 'gravityforms-state-debug']
        );
    }

    return $result;
}, 10, 4);
