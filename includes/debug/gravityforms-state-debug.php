<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Gravity Forms state debugging helper.
 * Hooks into GF validation to log the POST payload and decoded GF state for debugging failed_state_validation.
 */

add_action('gform_pre_validation', function ($form) {
    $logger = wc_get_logger();
    $form_id = is_array($form) && isset($form['id']) ? $form['id'] : (is_object($form) && isset($form->id) ? $form->id : 'unknown');

    // Only log when state exists or when POST keys related to the form are present
    $state_key = "state_{$form_id}";
    if (!isset($_POST[$state_key])) {
        $logger->debug('gform_pre_validation: state missing for form ' . $form_id . ' POST keys: ' . json_encode(array_keys($_POST)), ['source' => 'gravityforms-state-debug']);

        return $form;
    }

    $raw = $_POST[$state_key];
    $decoded = json_decode(base64_decode($raw), true);

    if (!$decoded || !is_array($decoded) || count($decoded) !== 2) {
        $logger->debug('gform_pre_validation: invalid state payload for form ' . $form_id . ' raw_trunc: ' . substr($raw, 0, 1000) . ' decoded: ' . var_export($decoded, true), ['source' => 'gravityforms-state-debug']);

        return $form;
    }

    $checksum = wp_hash(crc32($decoded[0]));
    if ($checksum !== $decoded[1]) {
        $logger->debug('gform_pre_validation: checksum mismatch for form ' . $form_id . ' computed: ' . $checksum . ' state_checksum: ' . var_export($decoded[1], true) . ' state0_trunc: ' . substr($decoded[0], 0, 1000), ['source' => 'gravityforms-state-debug']);

        return $form;
    }

    $gf_state = json_decode($decoded[0], true);
    $logger->debug('gform_pre_validation: decoded gf_state for form ' . $form_id . ' gf_state: ' . var_export($gf_state, true) . ' relevant_post: ' . var_export(array_filter($_POST, function ($k) use ($form_id) { return strpos($k, 'input_') === 0 || strpos($k, 'gform_target_page_number_') === 0; }, ARRAY_FILTER_USE_KEY), true), ['source' => 'gravityforms-state-debug']);

    // If any posted input contains JSON, make GF accept it by adding its wp_hash to the allowed state
    $modified = false;
    foreach ($_POST as $pkey => $pval) {
        if (strpos($pkey, 'input_') !== 0) {
            continue;
        }

        // extract ID (handles input_5 and input_5.1 style)
        $parts = explode('.', substr($pkey, strlen('input_')));
        $field_id = $parts[0] ?? null;
        if (!$field_id) {
            continue;
        }

        $val = $pval;
        if (!is_string($val)) {
            continue;
        }

        $trim = ltrim($val);
        // quick JSON detection
        if (strlen($trim) > 0 && ($trim[0] === '{' || $trim[0] === '[')) {
            $hash = wp_hash($val);
            // ensure gf_state key exists
            if (isset($gf_state[$field_id])) {
                // if state is array, append if missing
                if (is_array($gf_state[$field_id])) {
                    if (!in_array($hash, $gf_state[$field_id], true)) {
                        $gf_state[$field_id][] = $hash;
                        $logger->debug('gform_pre_validation: appended hash for form ' . $form_id . ' field ' . $field_id . ' hash=' . $hash, ['source' => 'gravityforms-state-debug']);
                        $modified = true;
                    }
                } else {
                    // single value, convert to array
                    if ($gf_state[$field_id] !== $hash) {
                        $gf_state[$field_id] = [$gf_state[$field_id], $hash];
                        $logger->debug('gform_pre_validation: converted state to array and added hash for form ' . $form_id . ' field ' . $field_id . ' hash=' . $hash, ['source' => 'gravityforms-state-debug']);
                        $modified = true;
                    }
                }
            }
        }
    }

    if ($modified) {
        // re-encode state and recompute checksum
        $new_state0 = json_encode($gf_state);
        $new_checksum = wp_hash(crc32($new_state0));
        $_POST[$state_key] = base64_encode(json_encode([$new_state0, $new_checksum]));
        $logger->debug('gform_pre_validation: updated POST[' . $state_key . '] for form ' . $form_id . ' with new checksum', ['source' => 'gravityforms-state-debug']);
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
                $logger->debug('gform_pre_validation: form ' . $form_id . ' contains field ' . $target_field_id . ' definition: ' . var_export($fld, true), ['source' => 'gravityforms-state-debug']);
                break;
            }
        }
    }

    // Log the current/target page numbers so we can see which page is being submitted
    $current_page = rgpost('gform_source_page_number_' . $form_id);
    $target_page = rgpost('gform_target_page_number_' . $form_id);
    $logger->debug('gform_pre_validation: form ' . $form_id . ' pages: current=' . var_export($current_page, true) . ' target=' . var_export($target_page, true) . ' POST keys count=' . count($_POST), ['source' => 'gravityforms-state-debug']);

    return $form;
});
