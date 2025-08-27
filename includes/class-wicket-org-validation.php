<?php
/**
 * Wicket Organization Validation Class.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class Wicket_Gf_Org_Validation
{
    /**
     * Initialize the validation hooks.
     */
    public function __construct()
    {
        add_filter('gform_validation', [$this, 'validate_org_profile']);
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
        $form = $validation_result['form'];

        // Check if this is a multi-step form and we're trying to go to the next step
        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? (int) rgpost('gform_source_page_number_' . $form['id']) : 1;
        $target_page = rgpost('gform_target_page_number_' . $form['id']) ? (int) rgpost('gform_target_page_number_' . $form['id']) : 0;

        // Check if the user explicitly clicked the 'Next' button
        $next_button_clicked = rgpost('gform_save') === '1' || (rgpost('gform_next_button') !== null);

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
            $validation_result['form'] = $form;

            return $validation_result;
        }

        // If we should validate, check all Wicket org profile fields
        if ($should_validate) {
            foreach ($form['fields'] as &$field) {
                if ($field->type == 'wicket_widget_profile_org') {
                    $field_id = $field->id;
                    $value = rgpost("input_{$field_id}");
                    $validation_flag = rgpost("input_{$field_id}_validation");

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
                                $validation_result['is_valid'] = false;
                                $field->failed_validation = true;
                                $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please ensure the organization has at least one address, email, phone, and web address.';
                                break;
                            }
                            // JSON indicates complete; allow progression despite stale flag
                            continue;
                        }
                        // If flag is true or missing, allow progression
                        continue;
                    }

                    // On final submission or non-multi-step forms: perform full checks
                    if (!empty($value)) {
                        $value_array = json_decode($value, true);

                        // Check for incomplete required fields
                        if (isset($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0) {
                            $validation_result['is_valid'] = false;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please complete all required fields in the organization profile.';
                            break;
                        }

                        // Check for incomplete required resources
                        if (isset($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0) {
                            $validation_result['is_valid'] = false;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please ensure the organization has at least one address, email, phone, and web address.';
                            break;
                        }
                    } else {
                        $validation_result['is_valid'] = false;
                        $field->failed_validation = true;
                        $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Organization profile is required.';
                        break;
                    }
                }
            }
        }

        $validation_result['form'] = $form;

        return $validation_result;
    }
}
