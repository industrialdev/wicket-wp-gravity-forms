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
            $component_args = apply_filters('wicket_gf_widget_profile_component_args', $component_args, $form, $this, $id);

            $component_output = get_component('widget-profile-individual', $component_args, false);

            // Build output with default wrapper classes (no filter needed)
            // Render a defensive wrapper fallback input with a distinct name to avoid colliding
            // with the component-rendered hidden input. Prefill its value from the component
            // POST key if present so we don't lose any submitted data in edge cases.
            $wrapper_fallback_name = 'wicket_wrapper_fallback_' . $id;
            $hidden = '<input type="hidden" name="' . esc_attr($wrapper_fallback_name) . '" value="' . (isset($_POST[$user_info_field_name]) ? esc_attr($_POST[$user_info_field_name]) : '') . '" />';

            $output = '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . $hidden . '</div>';

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
        $value_array = json_decode($value, true);
        if (isset($value_array['incompleteRequiredFields'])) {
            if (count($value_array['incompleteRequiredFields']) > 0) {
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                }
            }
        }

        // Note: Commenting out incompleteRequiredResources validation as it's causing form submission issues
        // The red asterisks on the buttons serve as visual indicators for required resources
        // if (isset($value_array['incompleteRequiredResources'])) {
        //     if (count($value_array['incompleteRequiredResources']) > 0) {
        //         $this->failed_validation = true;
        //         if (!empty($this->errorMessage)) {
        //             $this->validation_message = $this->errorMessage;
        //         }
        //     }
        // }
    }

    // Functions for how the field value gets displayed on the backend
    // public function get_value_entry_list($value, $entry, $field_id, $columns, $form) {
    //   return __('Enter details', 'txtdomain');
    // }
    // public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen') {
    //     return '';
    // }

    // Edit merge tag
    // public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br) {
    //   return $this->prettyListOutput($value);
    // }

}
