<?php

class GFWicketFieldWidgetProfile extends GF_Field
{
    public $type = 'wicket_widget_profile_individual';

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
        ];
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
            $component_args = [
                'classes'                   => [],
                'user_info_data_field_name' => $user_info_field_name,
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

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $value_array = json_decode($value);
        $user_id = $value_array->attributes->uuid;
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

        $value_array = json_decode($value, true);
        $logger->debug('Profile Individual Widget JSON decode result: ' . var_export($value_array, true), ['source' => 'gravityforms-state-debug']);

        if (isset($value_array['incompleteRequiredFields'])) {
            $logger->debug('Profile Individual Widget incompleteRequiredFields count: ' . count($value_array['incompleteRequiredFields']), ['source' => 'gravityforms-state-debug']);
            if (count($value_array['incompleteRequiredFields']) > 0) {
                $logger->debug('Profile Individual Widget failing validation due to incomplete required fields', ['source' => 'gravityforms-state-debug']);
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                }
            } else {
                $logger->debug('Profile Individual Widget no incomplete required fields, validation passed', ['source' => 'gravityforms-state-debug']);
            }
        } else {
            $logger->debug('Profile Individual Widget no incompleteRequiredFields key found', ['source' => 'gravityforms-state-debug']);
        }

    }
}
