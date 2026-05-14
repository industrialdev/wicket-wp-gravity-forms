<?php

declare(strict_types=1);

namespace WicketGF;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wicket Organization Validation — validates org profile fields on GF form submission.
 */
class Validation
{
    /** @var string[] The only Wicket field types we want to log on submit. */
    private const PROFILE_FIELD_TYPES = [
        'wicket_widget_profile_individual',
        'wicket_widget_profile_org',
    ];

    public function __construct()
    {
        add_filter('gform_validation', [$this, 'validate_org_profile']);
        add_filter('gform_pre_validation', [$this, 'log_pre_submit_context']);
    }

    /**
     * On final form submit, log the validation state of every Wicket Person
     * Profile and Organization Profile field. All other field types are ignored.
     * Fires only when target_page == 0 (final submit, not page navigation).
     */
    public function log_pre_submit_context(array $form): array
    {
        $form_id = $form['id'] ?? 'unknown';
        $target_page = rgpost('gform_target_page_number_' . $form_id);
        $target_page = $target_page !== false ? (int) $target_page : 0;

        if ($target_page !== 0) {
            return $form;
        }

        // Collect only the profile fields we care about.
        $profile_fields = array_filter(
            $form['fields'],
            fn ($f) => in_array($f->type ?? '', self::PROFILE_FIELD_TYPES, true)
        );

        if (empty($profile_fields)) {
            return $form;
        }

        $current_page = rgpost('gform_source_page_number_' . $form_id);
        $current_page = $current_page !== false ? (int) $current_page : 1;

        $log = \Wicket()->log();
        $source = 'wicket-gf-form-submit';
        $user = wp_get_current_user();
        $login = $user && $user->user_login ? $user->user_login : 'guest';

        $log->info(
            sprintf(
                'GF submit: form=%s current_page=%d user=%s',
                $form_id,
                $current_page,
                $login
            ),
            ['source' => $source]
        );

        foreach ($profile_fields as $field) {
            $field_id = $field->id;
            $field_type = $field->type ?? 'unknown';
            $field_label = substr((string) ($field->label ?? ''), 0, 60);
            $field_page = (int) ($field->pageNumber ?? 1);
            $is_required = !empty($field->isRequired);
            $raw_value = rgpost('input_' . $field_id);
            $validation_flag = rgpost('input_' . $field_id . '_validation');

            $incomplete_fields = [];
            $incomplete_resources = [];
            if (!empty($raw_value)) {
                $decoded = json_decode((string) $raw_value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $incomplete_fields = $decoded['incompleteRequiredFields'] ?? [];
                    $incomplete_resources = $decoded['incompleteRequiredResources'] ?? [];
                }
            }

            $log->debug(
                sprintf(
                    'field=%s type=%s label="%s" page=%d required=%s value=%s flag=%s incompleteFields=%s incompleteResources=%s',
                    $field_id,
                    $field_type,
                    $field_label,
                    $field_page,
                    $is_required ? 'yes' : 'no',
                    $this->summarize_value($raw_value),
                    var_export($validation_flag, true),
                    wp_json_encode($incomplete_fields),
                    wp_json_encode($incomplete_resources)
                ),
                ['source' => $source]
            );
        }

        return $form;
    }

    public function validate_org_profile($validation_result)
    {
        $form = $validation_result['form'];
        $form_id = $form['id'] ?? 'unknown';

        \Wicket()->log()->debug('Org validation called for form ' . $form_id, ['source' => 'gravityforms-state-debug']);
        \Wicket()->log()->debug('Initial validation result: ' . var_export($validation_result['is_valid'], true), ['source' => 'gravityforms-state-debug']);

        if (!$validation_result['is_valid']) {
            $failed_fields = [];
            foreach ($form['fields'] as $field) {
                if (isset($field->failed_validation) && $field->failed_validation) {
                    $label = substr((string) ($field->label ?? ''), 0, 50);
                    $admin_label = (string) ($field->adminLabel ?? '');
                    $field_page = (int) ($field->pageNumber ?? 1);
                    $value = rgpost('input_' . $field->id);
                    $value_sum = $this->summarize_value($value);
                    $val_msg = (string) ($field->validation_message ?? '');
                    $failed_fields[] = sprintf(
                        'Field %s (type=%s label="%s" admin="%s" page=%d required=%s value=%s msg="%s")',
                        $field->id,
                        $field->type,
                        $label,
                        $admin_label,
                        $field_page,
                        !empty($field->isRequired) ? 'yes' : 'no',
                        $value_sum,
                        $val_msg
                    );
                }
            }
            \Wicket()->log()->debug('Org validation: Initial validation false - failed fields: ' . implode(' | ', $failed_fields), ['source' => 'gravityforms-state-debug']);
        }

        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? (int) rgpost('gform_source_page_number_' . $form['id']) : 1;
        $target_page = rgpost('gform_target_page_number_' . $form['id']) ? (int) rgpost('gform_target_page_number_' . $form['id']) : 0;

        \Wicket()->log()->debug('Org validation pages: current=' . $current_page . ' target=' . $target_page, ['source' => 'gravityforms-state-debug']);

        $is_navigating_forward = $target_page > 0 && $target_page > $current_page;

        \Wicket()->log()->debug('Navigating forward: ' . var_export($is_navigating_forward, true), ['source' => 'gravityforms-state-debug']);

        $should_validate = $is_navigating_forward || $target_page == 0;

        \Wicket()->log()->debug('Should validate: ' . var_export($should_validate, true), ['source' => 'gravityforms-state-debug']);

        if ($should_validate) {
            \Wicket()->log()->debug('Org validation: Starting field validation', ['source' => 'gravityforms-state-debug']);
            $org_fields_found = 0;
            $org_validation_failed = false;

            foreach ($form['fields'] as &$field) {
                if ($field->type == 'wicket_widget_profile_org') {
                    $field_page = isset($field->pageNumber) ? (int) $field->pageNumber : 1;
                    if ($field_page !== $current_page) {
                        \Wicket()->log()->debug('Org validation: Skipping field not on current page', [
                            'source'       => 'gravityforms-state-debug',
                            'field_id'     => $field->id,
                            'field_page'   => $field_page,
                            'current_page' => $current_page,
                        ]);
                        continue;
                    }

                    $org_fields_found++;
                    $field_id = $field->id;
                    $value = rgpost("input_{$field_id}");
                    $validation_flag = rgpost("input_{$field_id}_validation");

                    \Wicket()->log()->debug('Org validation: Found org field ' . $field_id . ', value_length=' . strlen($value) . ', validation_flag=' . var_export($validation_flag, true), ['source' => 'gravityforms-state-debug']);

                    if ($is_navigating_forward) {
                        $value_array = !empty($value) ? json_decode($value, true) : [];
                        if (!is_array($value_array)) {
                            $value_array = [];
                        }

                        $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
                        $fields_incomplete = isset($value_array['incompleteRequiredFields']) && is_array($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0;
                        $resources_incomplete = isset($value_array['incompleteRequiredResources']) && is_array($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0;
                        $required_and_empty = !empty($field->isRequired) && empty($value);
                        $is_incomplete = $flag_false || $fields_incomplete || $resources_incomplete || $required_and_empty;

                        if ($is_incomplete) {
                            $org_validation_failed = true;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please ensure the organization has at least one address, email, phone, and web address.';
                            \Wicket()->log()->debug('Org validation: Field ' . $field_id . ' failed validation (forward navigation)', [
                                'source'               => 'gravityforms-state-debug',
                                'flag_false'           => $flag_false,
                                'fields_incomplete'    => $fields_incomplete,
                                'resources_incomplete' => $resources_incomplete,
                                'required_and_empty'   => $required_and_empty,
                            ]);
                            break;
                        }

                        \Wicket()->log()->debug('Org validation: Field ' . $field_id . ' complete for forward navigation', ['source' => 'gravityforms-state-debug']);
                        continue;
                    }

                    if (!empty($value)) {
                        $value_array = json_decode($value, true);

                        if (isset($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0) {
                            $org_validation_failed = true;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please complete all required fields in the organization profile.';
                            break;
                        }

                        if (isset($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0) {
                            $org_validation_failed = true;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Please ensure the organization has at least one address, email, phone, and web address.';
                            break;
                        }
                    } else {
                        if ($field->isRequired) {
                            $org_validation_failed = true;
                            $field->failed_validation = true;
                            $field->validation_message = !empty($field->errorMessage) ? $field->errorMessage : 'Organization profile is required.';
                            \Wicket()->log()->debug('Org validation: Field ' . $field_id . ' is required but empty, failing validation', ['source' => 'gravityforms-state-debug']);
                            break;
                        }
                        \Wicket()->log()->debug('Org validation: Field ' . $field_id . ' is empty but not required, allowing progression', ['source' => 'gravityforms-state-debug']);
                    }
                }
            }

            if ($org_validation_failed) {
                $validation_result['is_valid'] = false;
                \Wicket()->log()->debug('Org validation: Set form validation to false due to org profile issues', ['source' => 'gravityforms-state-debug']);
            } else {
                \Wicket()->log()->debug('Org validation: No org profile issues found, not changing validation result', ['source' => 'gravityforms-state-debug']);
            }

            \Wicket()->log()->debug('Org validation: Found ' . $org_fields_found . ' org profile fields', ['source' => 'gravityforms-state-debug']);
        } else {
            \Wicket()->log()->debug('Org validation: Should not validate, skipping', ['source' => 'gravityforms-state-debug']);
        }

        $validation_result['form'] = $form;

        \Wicket()->log()->debug('Org validation: Final validation result: ' . var_export($validation_result['is_valid'], true), ['source' => 'gravityforms-state-debug']);

        return $validation_result;
    }

    /**
     * Produce a compact, log-safe summary of a submitted field value.
     */
    private function summarize_value(mixed $value): string
    {
        if ($value === null || $value === false || $value === '') {
            return '[empty]';
        }

        if (is_array($value)) {
            return '[array:' . count($value) . ']';
        }

        $str = (string) $value;
        $len = strlen($str);

        if ($len === 0) {
            return '[empty]';
        }

        $decoded = json_decode($str, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return '[json:keys=' . implode(',', array_keys($decoded)) . ']';
        }

        return $len <= 120 ? $str : '[string:' . $len . 'chars]';
    }
}
