<?php
class GFWicketFieldWidgetProfileOrg extends GF_Field
{
    public $type = 'wicket_widget_profile_org';

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Org Profile', 'wicket-gf');
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
            'wicket_widget_profile_org_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.wwidget_org_profile_uuid = '';
                field.wwidget_org_profile_required_resources = '';
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

<li class="wicket_widget_profile_org_setting field_setting" style="display:none;">
    <div>
        <label>Org UUID:</label>
        <input id="wwidget_org_profile_uuid_input" onkeyup="SetFieldProperty('wwidget_org_profile_uuid', this.value)" type="text"
            placeholder="1234-5678-9100" />
        <p style="margin-top: 2px;"><em>Tip: if using a multi-page form, and a field on a previous page will get
                populated with the org UUID, you can simply enter that field ID here instead.</em></p>
    </div>
</li>

<li class="wicket_widget_profile_org_setting field_setting" style="display:none;">
    <div>
        <label>Required Resources:</label>
        <textarea id="wwidget_org_profile_required_resources_input" onkeyup="SetFieldProperty('wwidget_org_profile_required_resources', this.value)" type="text" ></textarea>
        <p style="margin-top: 2px;"><em>You can pass required resources like this: { addresses: "work", phones: ["mobile", "work"] }</em></p>
    </div>
</li>

<script type='text/javascript'>
window.WicketGF = window.WicketGF || {};
window.WicketGF.ProfileOrg = window.WicketGF.ProfileOrg || {
    init: function() {
        const self = this;

        // Handle field settings load
        gform.addAction('gform_load_field_settings', function(field) {
            if (field.type === 'wicket_widget_profile_org') {
                self.loadFieldSettings(field);
            }
        });

        // Handle field properties
        gform.addAction('gform_editor_js_set_field_properties', function(field) {
            if (field.type === 'wicket_widget_profile_org') {
                field.label = 'Wicket Widget: Org Profile';
                field.wwidget_org_profile_uuid = field.wwidget_org_profile_uuid || '';
                field.wwidget_org_profile_required_resources = field.wwidget_org_profile_required_resources || '';
            }
        });

        // Allow field to be added
        gform.addFilter('gform_form_editor_can_field_be_added', function(canAdd, fieldType) {
            if (fieldType === 'wicket_widget_profile_org') {
            return true;
        }
        return canAdd;
            });
        },

        loadFieldSettings: function(field) {
        const orgUuidInput = document.getElementById('wwidget_org_profile_uuid_input');
        if (orgUuidInput) {
            orgUuidInput.value = field.wwidget_org_profile_uuid || '';
        }

        const requiredResourcesInput = document.getElementById('wwidget_org_profile_required_resources_input');
        if (requiredResourcesInput) {
            requiredResourcesInput.value = field.wwidget_org_profile_required_resources || '';
        }
    }
};

// Initialize if not already done
if (!window.WicketGF.ProfileOrg.initialized) {
    window.WicketGF.ProfileOrg.init();
    window.WicketGF.ProfileOrg.initialized = true;
}
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

        $org_uuid = '';
        $org_required_resources = '';

        foreach ($form['fields'] as $field) {
            if (gettype($field) == 'object') {
                if (get_class($field) == 'GFWicketFieldWidgetProfileOrg') {
                    if ($field->id == $id) {
                        if (isset($field->wwidget_org_profile_uuid)) {
                            $org_uuid = $field->wwidget_org_profile_uuid;
                        }
                        if (isset($field->wwidget_org_profile_required_resources)) {
                            $org_required_resources = $field->wwidget_org_profile_required_resources;
                        }
                    }
                }
            }
        }

        // Test if a UUID was manually saved, or if a field ID was saved instead (in the case of a multi-page form)
        if (!str_contains($org_uuid, '-')) {
            if (isset($_POST['input_' . $org_uuid])) {
                $field_value = $_POST['input_' . $org_uuid];
                if (str_contains($field_value, '-')) {
                    $org_uuid = $field_value;
                }
            }
        }

        if (component_exists('widget-profile-org')) {
            $component_output = get_component('widget-profile-org', [
                'classes'                    => [],
                'org_info_data_field_name'   => 'input_' . $id,
                'org_id'                     => $org_uuid,
                'org_required_resources'     => $org_required_resources,
            ], false);

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
        } else {
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-profile-org component is missing. Please update the Wicket Base Plugin.</p></div>';
        }

    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $value_array = json_decode($value);
        $org_id = $value_array->attributes->uuid; // Double check this is the correct location of org uuid
        $wicket_settings = get_wicket_settings();

        $link_to_user_profile = $wicket_settings['wicket_admin'] . '/organizations/' . $org_id;

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

        if (isset($value_array['incompleteRequiredResources'])) {
            if (count($value_array['incompleteRequiredResources']) > 0) {
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                }
            }
        }
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
?>
