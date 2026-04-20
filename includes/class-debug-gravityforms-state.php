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
        return $validation_result;
    }
    $_POST = stripslashes_deep($_POST);
    $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');

    $state_key = "state_{$form_id}";
    if (!isset($_POST[$state_key])) {
        return $validation_result;
    }

    $raw = $_POST[$state_key];
    $decoded = json_decode(base64_decode($raw), true);

    if (!$decoded || !is_array($decoded) || count($decoded) !== 2) {
        Wicket()->log()->warning('gform_validation: invalid state payload for form ' . $form_id, ['source' => 'gravityforms-state-debug']);
        return $validation_result;
    }

    $checksum = wp_hash(crc32($decoded[0]));
    if ($checksum !== $decoded[1]) {
        Wicket()->log()->warning('gform_validation: checksum mismatch for form ' . $form_id, ['source' => 'gravityforms-state-debug']);
        return $validation_result;
    }

    $gf_state = json_decode($decoded[0], true);

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

        if (substr($pkey, -strlen('_validation')) === '_validation') {
            continue;
        }

        $field_id = intval($field_id_str);
        if (!$field_id) {
            continue;
        }

        $val = $pval;
        if (!is_string($val)) {
            continue;
        }

        $decoded_val = json_decode($val);
        if (!is_null($decoded_val) && (is_object($decoded_val) || is_array($decoded_val))) {

            $hash = wp_hash($val);
            if (isset($gf_state[$field_id])) {
                if (is_array($gf_state[$field_id])) {
                    if (!in_array($hash, $gf_state[$field_id], true)) {
                        $gf_state[$field_id][] = $hash;
                        $modified = true;
                    }
                } else {
                    if ($gf_state[$field_id] !== $hash) {
                        $gf_state[$field_id] = [$gf_state[$field_id], $hash];
                        $modified = true;
                    }
                }
            } else {
                $gf_state[$field_id] = [$hash];
                $modified = true;
            }
        }
    }

    if ($modified) {
        $new_state0 = json_encode($gf_state);
        $new_checksum = wp_hash(crc32($new_state0));
        $_POST[$state_key] = base64_encode(json_encode([$new_state0, $new_checksum]));
        Wicket()->log()->debug('gform_validation: state updated for form ' . $form_id, ['source' => 'gravityforms-state-debug']);
    }

    return $validation_result;
});

// Log per-field validation results to detect any external overrides causing failed validation.
add_filter('gform_field_validation', function ($result, $value, $form, $field) {
    $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');
    $field_id = is_object($field) && isset($field->id) ? $field->id : (is_array($field) && isset($field['id']) ? $field['id'] : null);
    $field_type = is_object($field) && isset($field->type) ? $field->type : (is_array($field) && isset($field['type']) ? $field['type'] : null);

    if ($field_type === 'wicket_widget_ai' && !$result['is_valid']) {
        Wicket()->log()->warning(
            'gform_field_validation: field ' . $field_id . ' (type=' . $field_type . ') in form ' . $form_id . ' failed validation',
            ['source' => 'gravityforms-state-debug']
        );
    }

    return $result;
}, 10, 4);
