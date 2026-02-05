<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Ensures Gravity Forms state contains hashes for JSON payloads posted
 * by Wicket widgets, preventing failed_state_validation errors.
 */
add_filter('gform_validation', function ($validation_result) {
    if (is_object($validation_result)) {
        $form = $validation_result->get_form();
    } elseif (is_array($validation_result) && isset($validation_result['form'])) {
        $form = $validation_result['form'];
    } else {
        return $validation_result;
    }

    // Recursively strip slashes from all POST data, which may be added by WP or other plugins.
    $_POST = stripslashes_deep($_POST);

    $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');
    $state_key = "state_{$form_id}";
    if (!isset($_POST[$state_key])) {
        return $validation_result;
    }

    $raw = $_POST[$state_key];
    $decoded = json_decode(base64_decode($raw), true);
    if (!$decoded || !is_array($decoded) || count($decoded) !== 2) {
        return $validation_result;
    }

    $checksum = wp_hash(crc32($decoded[0]));
    if ($checksum !== $decoded[1]) {
        return $validation_result;
    }

    $gf_state = json_decode($decoded[0], true);
    if (!is_array($gf_state)) {
        return $validation_result;
    }

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

        $field_id = intval($field_id_str);
        if (!$field_id) {
            continue;
        }

        if (!is_string($pval)) {
            continue;
        }

        $decoded_val = json_decode($pval);
        if (!is_null($decoded_val) && (is_object($decoded_val) || is_array($decoded_val))) {
            $hash = wp_hash($pval);

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
    }

    return $validation_result;
});

// Log failed field validations to identify which field blocks progression.
add_filter('gform_field_validation', function ($result, $value, $form, $field) {
    if (is_array($result) && isset($result['is_valid']) && $result['is_valid']) {
        return $result;
    }

    $logger = wc_get_logger();
    $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');
    $field_id = is_object($field) && isset($field->id) ? $field->id : (is_array($field) && isset($field['id']) ? $field['id'] : null);
    $field_type = is_object($field) && isset($field->type) ? $field->type : (is_array($field) && isset($field['type']) ? $field['type'] : null);
    $message = is_array($result) && isset($result['message']) ? $result['message'] : '';

    $value_str = is_string($value) ? $value : json_encode($value);
    if (is_string($value_str)) {
        $value_str = substr($value_str, 0, 500);
    }

    $logger->info(
        'GF field validation failed: form=' . $form_id . ' field=' . $field_id . ' type=' . $field_type . ' message=' . $message . ' value_trunc=' . var_export($value_str, true),
        ['source' => 'wicket-gf-validation']
    );

    return $result;
}, 10, 4);

// Log summary when form validation fails.
add_filter('gform_validation', function ($validation_result) {
    if (!is_array($validation_result) || empty($validation_result['is_valid'])) {
        $logger = wc_get_logger();
        $form = $validation_result['form'] ?? null;
        $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');
        $failed_fields = [];

        if (is_array($form) && isset($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                $failed = is_object($field) && isset($field->failed_validation) && $field->failed_validation;
                if (!$failed && is_array($field) && isset($field['failed_validation']) && $field['failed_validation']) {
                    $failed = true;
                }
                if ($failed) {
                    $field_id = is_object($field) && isset($field->id) ? $field->id : (is_array($field) && isset($field['id']) ? $field['id'] : null);
                    $field_type = is_object($field) && isset($field->type) ? $field->type : (is_array($field) && isset($field['type']) ? $field['type'] : null);
                    $message = is_object($field) && isset($field->validation_message) ? $field->validation_message : (is_array($field) && isset($field['validation_message']) ? $field['validation_message'] : '');
                    $failed_fields[] = sprintf('%s:%s:%s', $field_id, $field_type, $message);
                }
            }
        }

        $logger->info(
            'GF validation failed: form=' . $form_id . ' failed_fields=' . implode(' | ', $failed_fields),
            ['source' => 'wicket-gf-validation']
        );
    }

    return $validation_result;
});
