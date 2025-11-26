<?php
/**
 * Wicket Organization Validation Class.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class Wicket_Gf_Validation
{
    /**
     * Initialize the validation hooks.
     */
    public function __construct()
    {
        add_filter('gform_validation', [$this, 'validate_org_profile']);
        add_filter('gform_field_validation', [$this, 'validate_profile_individual_widget_field'], 10, 4);
    }

    /**
     * Validates Wicket Widget Profile Org fields before allowing multi-step form progression
     * or form submission.
     *
     * @param array $validation_result The validation result array
     * @return array Modified validation result
     */
    public function validate_org_profile($validation_result)
    {
        $logger = wc_get_logger();
        $form = $validation_result['form'];
        $form_id = $form['id'] ?? 'unknown';

        $logger->debug('Org validation called for form ' . $form_id, ['source' => 'gravityforms-state-debug']);
        $logger->debug('Initial validation result: ' . var_export($validation_result['is_valid'], true), ['source' => 'gravityforms-state-debug']);

        // Debug what might be causing the initial validation result to be false
        if (!$validation_result['is_valid']) {
            $failed_fields = [];
            foreach ($form['fields'] as $field) {
                if (isset($field->failed_validation) && $field->failed_validation) {
                    $failed_fields[] = 'Field ' . $field->id . ' (' . $field->type . ')';
                }
            }
            $logger->debug('Org validation: Initial validation false - failed fields: ' . implode(', ', $failed_fields), ['source' => 'gravityforms-state-debug']);
        }

        // Check if this is a multi-step form and we're trying to go to the next step
        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? (int) rgpost('gform_source_page_number_' . $form['id']) : 1;
        $target_page = rgpost('gform_target_page_number_' . $form['id']) ? (int) rgpost('gform_target_page_number_' . $form['id']) : 0;

        $logger->debug('Org validation pages: current=' . $current_page . ' target=' . $target_page, ['source' => 'gravityforms-state-debug']);

        // Check if the user explicitly clicked the 'Next' button
        $next_button_clicked = rgpost('gform_save') === '1' || (rgpost('gform_next_button') !== null);

        $logger->debug('Next button clicked: ' . var_export($next_button_clicked, true), ['source' => 'gravityforms-state-debug']);

        // Determine if we should validate (either moving to next step or submitting)
        $should_validate = false;

        // If we're moving forward in a multi-step form and the user clicked 'Next'
        if ($target_page > $current_page && $next_button_clicked) {
            $should_validate = true;
        }

        // If we're submitting the form (target_page is 0 on submission)
        if ($target_page == 0) {
            $should_validate = true;
        }

        $logger->debug('Should validate: ' . var_export($should_validate, true), ['source' => 'gravityforms-state-debug']);

        // Detect if form is multi-step (has page fields)
        $is_multi_step = false;
        if (!empty($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $f) {
                if ((is_object($f) && isset($f->type) && $f->type === 'page') || (is_array($f) && isset($f['type']) && $f['type'] === 'page')) {
                    $is_multi_step = true;
                    break;
                }
            }
        }

        // If multi-step and on final submit, skip org profile validation entirely (handled earlier on its page)
        if ($is_multi_step && $target_page == 0) {
            $logger->debug('Org validation: Multi-step final submit, skipping validation', ['source' => 'gravityforms-state-debug']);
            $validation_result['form'] = $form;

            return $validation_result;
        }

        // If we should validate, check all Wicket org profile fields
        if ($should_validate) {
            $logger->debug('Org validation: Starting field validation', ['source' => 'gravityforms-state-debug']);
            $org_fields_found = 0;
            $org_validation_failed = false;  // Track if WE specifically failed validation

            foreach ($form['fields'] as &$field) {
                if ($field->type == 'wicket_widget_profile_org') {
                    $org_fields_found++;
                    $field_id = $field->id;
                    $value = rgpost("input_{$field_id}");
                    $validation_flag = rgpost("input_{$field_id}_validation");

                    $logger->debug('Org validation: Found org field ' . $field_id . ', value_length=' . strlen($value) . ', validation_flag=' . var_export($validation_flag, true), ['source' => 'gravityforms-state-debug']);

                    // On multi-step Next: rely ONLY on validation flag to avoid stale payloads
                    if ($target_page > $current_page && $next_button_clicked) {
                        $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
                        if ($flag_false) {
                            // Double-check payload; only block if payload still shows incompleteness
                            $is_incomplete = true;
                            if (!empty($value)) {
                                $value_array = json_decode($value, true);
                                $fields_incomplete = isset($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0;
                                $resources_incomplete = isset($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0;
                                $is_incomplete = ($fields_incomplete || $resources_incomplete);
                            }
                            if ($is_incomplete) {
                                $org_validation_failed = true;
                                $field->failed_validation = true;
                                $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please ensure the organization has at least one address, email, phone, and web address.';
                                $logger->debug('Org validation: Field ' . $field_id . ' failed validation (incomplete)', ['source' => 'gravityforms-state-debug']);
                                break;
                            }
                            // JSON indicates complete; allow progression despite stale flag
                            $logger->debug('Org validation: Field ' . $field_id . ' JSON indicates complete, allowing progression', ['source' => 'gravityforms-state-debug']);
                            continue;
                        }
                        // If flag is true or missing, allow progression
                        $logger->debug('Org validation: Field ' . $field_id . ' validation flag is true/missing, allowing progression', ['source' => 'gravityforms-state-debug']);
                        continue;
                    }

                    // On final submission or non-multi-step forms: perform full checks
                    if (!empty($value)) {
                        $value_array = json_decode($value, true);

                        // Check for incomplete required fields
                        if (isset($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0) {
                            $org_validation_failed = true;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please complete all required fields in the organization profile.';
                            break;
                        }

                        // Check for incomplete required resources
                        if (isset($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0) {
                            $org_validation_failed = true;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please ensure the organization has at least one address, email, phone, and web address.';
                            break;
                        }
                    } else {
                        // Only fail validation for empty org profile if the field is actually required
                        if ($field->isRequired) {
                            $org_validation_failed = true;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Organization profile is required.';
                            $logger->debug('Org validation: Field ' . $field_id . ' is required but empty, failing validation', ['source' => 'gravityforms-state-debug']);
                            break;
                        } else {
                            $logger->debug('Org validation: Field ' . $field_id . ' is empty but not required, allowing progression', ['source' => 'gravityforms-state-debug']);
                        }
                    }
                }
            }

            // Only set validation to false if we specifically found org validation issues
            if ($org_validation_failed) {
                $validation_result['is_valid'] = false;
                $logger->debug('Org validation: Set form validation to false due to org profile issues', ['source' => 'gravityforms-state-debug']);
            } else {
                $logger->debug('Org validation: No org profile issues found, not changing validation result', ['source' => 'gravityforms-state-debug']);
            }

            $logger->debug('Org validation: Found ' . $org_fields_found . ' org profile fields', ['source' => 'gravityforms-state-debug']);
        } else {
            $logger->debug('Org validation: Should not validate, skipping', ['source' => 'gravityforms-state-debug']);
        }

        $validation_result['form'] = $form;

        $logger->debug('Org validation: Final validation result: ' . var_export($validation_result['is_valid'], true), ['source' => 'gravityforms-state-debug']);

        return $validation_result;
    }

    /**
     * Validate profile individual widget field during multi-step form progression.
     *
     * @param array $result The current validation result.
     * @param string $value The field value.
     * @param array $form The form object.
     * @param object $field The field object.
     * @return array Updated validation result.
     */
    public function validate_profile_individual_widget_field($result, $value, $form, $field)
    {
        if ($field->type !== 'wicket_widget_profile_individual') {
            return $result;
        }



        // For multi-step forms, we shouldn't require this field to have a value on step progression
        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? (int) rgpost('gform_source_page_number_' . $form['id']) : 1;
        $target_page = rgpost('gform_target_page_number_' . $form['id']) ? (int) rgpost('gform_target_page_number_' . $form['id']) : 0;

        if ($target_page > $current_page) {
            $result['is_valid'] = true;
            $result['message'] = '';

            return $result;
        }

        // Detect if form is multi-step (has page fields)
        $is_multi_step = false;
        if (!empty($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $f) {
                if ((is_object($f) && isset($f->type) && $f->type === 'page') || (is_array($f) && isset($f['type']) && $f['type'] === 'page')) {
                    $is_multi_step = true;
                    break;
                }
            }
        }

        // If multi-step and on final submit, skip validation entirely (handled earlier on its page)
        if ($is_multi_step && $target_page == 0) {

            $result['is_valid'] = true;
            $result['message'] = '';
            return $result;
        }

        // For final submission, only validate if the field is actually required and has incomplete data
        if (!empty($value)) {
            $value_array = json_decode($value, true);
            if (isset($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0) {
                $result['is_valid'] = false;
                $result['message'] = !empty($field->errorMessage) ? $field->errorMessage : 'Please complete all required fields in your profile.';
            } else {
                $result['is_valid'] = true;
                $result['message'] = '';
            }
        } else {
            $result['is_valid'] = true;
            $result['message'] = '';
        }

        return $result;
    }
}
