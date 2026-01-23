<?php

class GFWicketFieldWidgetProfile extends GF_Field
{
    public $type = 'wicket_widget_profile_individual';
    public $wwidget_profile_required_resources = '';

    /**
     * Initialize the widget field and enqueue validation scripts.
     */
    public static function init()
    {
        // Enqueue validation scripts when this widget is used
        add_action('gform_enqueue_scripts', [__CLASS__, 'enqueue_validation_scripts'], 10, 2);
    }

    // Ensure new fields have sane defaults server-side
    public function get_default_properties()
    {
        $defaults = parent::get_default_properties();
        $defaults['wwidget_profile_required_resources'] = '';

        return $defaults;
    }

    // Sanitize and enforce defaults when the form is saved in the editor
    public function sanitize_settings()
    {
        parent::sanitize_settings();

        // Required resources: keep as a raw string (brace/quote content) but strip tags; set default if empty
        $default_required = '';
        if (empty($this->wwidget_profile_required_resources)) {
            $this->wwidget_profile_required_resources = $default_required;
        } else {
            $raw = (string) $this->wwidget_profile_required_resources;
            // Remove any tags while preserving braces/quotes
            $this->wwidget_profile_required_resources = wp_kses_post($raw);
            if ($this->wwidget_profile_required_resources === '') {
                $this->wwidget_profile_required_resources = $default_required;
            }
        }
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Profile', 'wicket-gf');
    }

    // Move the field to 'advanced fields'
    public function get_form_editor_button()
    {
        return [
            'group' => 'wicket_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    public function get_form_editor_field_settings()
    {
        return [
            'label_setting',
            'description_setting',
            'rules_setting',
            'error_message_setting',
            'css_class_setting',
            'conditional_logic_field_setting',
            'wicket_widget_profile_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.wwidget_profile_required_resources = '';
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }


    public static function custom_settings($position, $form_id)
    {
        //create settings on position 25 (right after Field Label)
        if ($position == 25) {
            ob_start(); ?>

<li class="wicket_widget_profile_setting field_setting" style="display:none;">
    <div>
        <label>Required Resources:</label>
        <textarea id="wwidget_profile_required_resources_input" onkeyup="SetFieldProperty('wwidget_profile_required_resources', this.value)" type="text" ></textarea>
        <p style="margin-top: 2px;"><em>You can pass required resources like this: { addresses: "work", phones: ["mobile", "work"], webAddresses: "website" }</em></p>
        <p style="margin-top: 2px;"><em>See <a href="https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme-staging.html#createpersonprofile" target="_blank">full documentation for MDP JS Widgets</a>.</em></p>
    </div>
</li>

<script type='text/javascript'>
// Use jQuery-based GF editor events for reliability (matches working field patterns)
jQuery(document).ready(function($) {
    var defaultRequired = '';

    // When settings panel loads for a field
    $(document).on('gform_load_field_settings', function(event, field) {
        if (field.type !== 'wicket_widget_profile_individual') {
            return;
        }

        // Populate inputs

        if (!field.wwidget_profile_required_resources) {
            field.wwidget_profile_required_resources = defaultRequired;
            SetFieldProperty('wwidget_profile_required_resources', defaultRequired);
        }
        $('#wwidget_profile_required_resources_input').val(field.wwidget_profile_required_resources || '');

        // Bind once: keep model in sync as user types
        var rrSel = '#wwidget_profile_required_resources_input';
        if (!$(rrSel).data('bound')) {
            $(rrSel).on('input.wicket-profile change.wicket-profile', function() {
                SetFieldProperty('wwidget_profile_required_resources', this.value);
            }).data('bound', true);
        }
    });

    // When a new field is added to the form
    $(document).on('gform_field_added', function(event, field) {
        if (field.type !== 'wicket_widget_profile_individual') {
            return;
        }
        field.label = 'Wicket Widget: Individual Profile';
        if (!field.wwidget_profile_required_resources) {
            field.wwidget_profile_required_resources = defaultRequired;
            SetFieldProperty('wwidget_profile_required_resources', defaultRequired);
        }
    });
});
</script>

<?php
            echo ob_get_clean();
        }
    }

    public static function editor_script()
    {
        // JavaScript now embedded in custom_settings method for better integration
    }

    // Render the field
    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<p>Widget will show here on the frontend</p>';
        }

        $id = (int) $this->id;

        if (component_exists('widget-profile-individual')) {
            // Default component args with filter for extensibility
            $user_info_field_name = 'wicket_user_info_data_' . $id;
            $profile_required_resources = $this->wwidget_profile_required_resources ?? '';

            $component_args = [
                'classes'                    => [],
                'user_info_data_field_name'  => $user_info_field_name,
                'hidden_fields'              => ['personType'],
                'validation_data_field_name' => 'input_' . $this->id . '_validation',
                'profile_required_resources' => $profile_required_resources,
            ];

            if (isset($this->org_uuid)) {
                $component_args['org_id'] = $this->org_uuid;
            }
            $component_args = apply_filters('wicket_gf_widget_profile_component_args', $component_args, $form, $this, $id);

            $component_output = get_component('widget-profile-individual', $component_args, false);

            // Build output with default wrapper classes (no filter needed)
            $output = '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';

            do_action('wicket_gf_widget_profile_output_after', $output, $component_output, $form, $this, $id);

            return $output;
        } else {
            // No hooks in missing component state; return a static message.
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-profile-individual component is missing. Please update the Wicket Base Plugin.</p></div>';
        }

    }

    /**
     * Enqueue validation scripts for MDP widgets.
     */
    public static function enqueue_validation_scripts($form, $is_ajax)
    {
        // Check if this form contains a profile widget
        $has_profile_widget = false;
        foreach ($form['fields'] as $field) {
            if ($field instanceof self) {
                $has_profile_widget = true;
                break;
            }
        }

        if (!$has_profile_widget) {
            return;
        }

        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $version = defined('WICKET_WP_GF_VERSION') ? WICKET_WP_GF_VERSION : '1.0.0';

        // Enqueue the automatic widget validation script
        wp_enqueue_script(
            'wicket-gf-automatic-widget-validation',
            $plugin_url . 'assets/js/wicket-gf-automatic-widget-validation.js',
            ['jquery'],
            $version,
            true
        );

        // Pass configuration to automatic validation script
        wp_localize_script(
            'wicket-gf-automatic-widget-validation',
            'WicketMDPAutoValidationConfig',
            [
                'enableLogging' => defined('WP_ENV') && in_array(WP_ENV, ['development', 'staging'], true),
                'enableAutoDetection' => true,
                'debugMode' => defined('WP_ENV') && WP_ENV === 'development',
            ]
        );
    }

    /**
     * Override value submission to retrieve from the widget's component field
     * while maintaining backwards compatibility with the legacy GF field name.
     */
    public function get_value_submission($field_values, $get_from_post_global_var = true)
    {
        // The component field with collision-avoidance naming
        $component_field = 'wicket_user_info_data_' . $this->id;

        // Fallback to legacy GF field name for backwards compatibility
        $legacy = 'input_' . $this->id;

        if ($get_from_post_global_var) {
            if (isset($_POST[$component_field])) {
                $value = rgpost($component_field);
            } else {
                $value = rgpost($legacy);
            }
        } else {
            if (isset($field_values[$component_field])) {
                $value = $field_values[$component_field];
            } else {
                $value = $field_values[$legacy] ?? '';
            }
        }

        return $value;
    }

    /**
     * Override empty value detection to check component and legacy field names.
     */
    public function is_value_submission_empty($form_id)
    {
        // Check the component field first, then legacy fallback
        $component_field = 'wicket_user_info_data_' . $this->id;
        $legacy = 'input_' . $this->id;

        $value = rgpost($component_field);
        if ($value !== null) {
            return empty($value);
        }

        $value = rgpost($legacy);

        return empty($value);
    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        // If value is empty or malformed, fall back to current logged-in user
        if (empty($value)) {
            $user_id = wicket_current_person_uuid();
        } else {
            $value_array = json_decode($value);
            if (!isset($value_array->attributes->uuid)) {
                // Fallback to current logged-in user if JSON is malformed
                $user_id = wicket_current_person_uuid();
            } else {
                $user_id = $value_array->attributes->uuid;
            }
        }

        // Final fallback - if no user ID found, use empty string
        if (empty($user_id)) {
            return '';
        }

        $wicket_settings = get_wicket_settings();
        $link_to_user_profile = $wicket_settings['wicket_admin'] . '/people/' . $user_id;

        return $link_to_user_profile;
        //return '<a href="'.$link_to_user_profile.'">Link to user profile in Wicket</a>';
    }

    public function validate($value, $form)
    {
        $logger = wc_get_logger();
        $logger->debug('Profile Individual Widget validate called for field ' . $this->id, ['source' => 'gravityforms-state-debug']);
        $logger->debug('Profile Individual Widget validate value: ' . var_export($value, true), ['source' => 'gravityforms-state-debug']);

        // Gate validation by user action: only on Next or final submit
        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? (int) rgpost('gform_source_page_number_' . $form['id']) : 1;
        $target_page = rgpost('gform_target_page_number_' . $form['id']) ? (int) rgpost('gform_target_page_number_' . $form['id']) : 0;
        $next_clicked = rgpost('gform_save') === '1' || (rgpost('gform_next_button') !== null);

        $on_next = ($target_page > $current_page && $next_clicked);
        $on_submit = ($target_page == 0); // GF uses 0 when submitting the form

        if (!$on_next && !$on_submit) {
            // Do not validate on initial load or unrelated actions
            return;
        }

        // If this is a multi-step form and we're on final submit, skip validation for this field
        $is_multi_step = false;
        if (!empty($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $f) {
                if ((is_object($f) && isset($f->type) && $f->type === 'page') || (is_array($f) && isset($f['type']) && $f['type'] === 'page')) {
                    $is_multi_step = true;
                    break;
                }
            }
        }
        if ($on_submit && $is_multi_step) {
            return;
        }

        $field_id = $this->id ?? null;
        $validation_flag = $field_id !== null ? rgpost('input_' . $field_id . '_validation') : null;

        if ($on_next) {
            // On Next, rely on hidden flag; double-check JSON only if flag is false
            $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
            if ($flag_false) {
                $is_incomplete = true;
                if (!empty($value)) {
                    $value_array = json_decode($value, true);
                    $value_array = is_array($value_array) ? $value_array : [];
                    $fields_incomplete = isset($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0;
                    $resources_incomplete = isset($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0;
                    $is_incomplete = ($fields_incomplete || $resources_incomplete);
                }
                if ($is_incomplete) {
                    $this->failed_validation = true;
                    $this->validation_message = !empty($this->errorMessage)
                        ? $this->errorMessage
                        /* translators: Message displayed when person profile is incomplete */
                        : __('Please fill out all required fields in your profile.', 'wicket_gf');
                }
            }

            // If flag true or missing, allow progression
            return;
        }

        // On final submission, perform checks but avoid false blocking when field isn't on this page
        $value_array = json_decode($value, true);
        $value_array = is_array($value_array) ? $value_array : [];

        // If the hidden flag is posted and explicitly false, block; if it's absent, don't use it to block
        $flag_false_submit = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
        if ($validation_flag !== null && $flag_false_submit) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage)
                ? $this->errorMessage
                : __('Please fill out all required fields in your profile.', 'wicket_gf');

            return;
        }

        // If the hidden flag is explicitly true, allow submit (authoritative success from the widget)
        $flag_true_submit = ($validation_flag === true || $validation_flag === 'true' || $validation_flag === '1');
        if ($flag_true_submit) {
            return;
        }

        // If there is no JSON payload at all, allow submit (field likely not present on this page / no new data)
        if (empty($value_array)) {
            return;
        }

        if (isset($value_array['incompleteRequiredFields'])) {
            if (count($value_array['incompleteRequiredFields']) > 0) {
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                } else {
                    $this->validation_message = __('Please fill out all required fields in your profile.', 'wicket_gf');
                }

                return;
            }
        }

        if (isset($value_array['incompleteRequiredResources'])) {
            if (count($value_array['incompleteRequiredResources']) > 0) {
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                } else {
                    $this->validation_message = __('Please fill out all required fields in your profile.', 'wicket_gf');
                }

                return;
            }
        }

    }
}

// Initialize the widget field
GFWicketFieldWidgetProfile::init();
