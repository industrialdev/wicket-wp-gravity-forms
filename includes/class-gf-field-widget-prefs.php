<?php
class GFWicketFieldWidgetPrefs extends GF_Field
{
    public $type = 'wicket_widget_prefs';

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Person Preferences', 'wicket-gf');
    }

    // Move the field to 'wicket fields'
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
            'wicket_widget_person_prefs_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.wwidget_prefs_hide_comm = false;
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

<li class="wicket_widget_person_prefs_setting field_setting" style="display:none;margin-bottom: 10px;">
    <input onchange="SetFieldProperty('wwidget_prefs_hide_comm', this.checked)"
        type="checkbox" id="wwidget_prefs_hide_comm" class="wwidget_prefs_hide_comm">
    <label for="wwidget_prefs_hide_comm" class="inline">Disable communication preferences?</label>
</li>

<script type='text/javascript'>
window.WicketGF = window.WicketGF || {};
window.WicketGF.Prefs = window.WicketGF.Prefs || {
    init: function() {
        const self = this;

        // Handle field settings load
        gform.addAction('gform_load_field_settings', function(field) {
            if (field.type === 'wicket_widget_prefs') {
                self.loadFieldSettings(field);
        }
        });

        // Handle field properties
        gform.addAction('gform_editor_js_set_field_properties', function(field) {
            if (field.type === 'wicket_widget_prefs') {
                field.label = 'Wicket Widget: Person Preferences';
                field.wwidget_prefs_hide_comm = field.wwidget_prefs_hide_comm || false;
            }
        });

        // Allow field to be added
        gform.addFilter('gform_form_editor_can_field_be_added', function(canAdd, fieldType) {
            if (fieldType === 'wicket_widget_prefs') {
            return true;
        }
        return canAdd;
            });
        },

        loadFieldSettings: function(field) {
        const hideCommCheckbox = document.getElementById('wwidget_prefs_hide_comm');
        if (hideCommCheckbox) {
            hideCommCheckbox.checked = field.wwidget_prefs_hide_comm || false;
        }
    }
};

// Initialize if not already done
if (!window.WicketGF.Prefs.initialized) {
    window.WicketGF.Prefs.init();
    window.WicketGF.Prefs.initialized = true;
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

        $hide_comm_prefs = false;

        foreach ($form['fields'] as $field) {
            if (gettype($field) == 'object') {
                if (get_class($field) == 'GFWicketFieldWidgetPrefs') {
                    if ($field->id == $id) {
                        if (isset($field->wwidget_prefs_hide_comm)) {
                            $hide_comm_prefs = (bool) $field->wwidget_prefs_hide_comm;
                        }
                    }
                }
            }
        }

        if (component_exists('widget-prefs-person')) {
            // Adding extra ob_start/clean since the component was jumping the gun for some reason
            ob_start();

            get_component('widget-prefs-person', [
                'classes'                      => [],
                'hide_comm_prefs'              => $hide_comm_prefs,
                // Provide a unique hidden field name so component doesn't write to input_{id}
                'preferences_data_field_name'  => 'wicket_prefs_data_' . $id,
            ], true);

            $component_output = ob_get_clean();

            // Render a defensive wrapper fallback input with a distinct name to avoid colliding
            // with the component-rendered hidden input. Prefill from the component POST key if present.
            $wrapper_fallback_name = 'wicket_wrapper_fallback_' . $id;
            $hidden = '<input type="hidden" name="' . esc_attr($wrapper_fallback_name) . '" value="' . (isset($_POST['wicket_prefs_data_' . $id]) ? esc_attr($_POST['wicket_prefs_data_' . $id]) : '') . '" />';

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . $hidden . '</div>';
        } else {
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-prefs-person component is missing. Please update the Wicket Base Plugin.</p></div>';
        }

    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $value_array = json_decode($value);
        $user_id = wicket_current_person_uuid();
        $wicket_settings = get_wicket_settings();

        $link_to_user_profile = $wicket_settings['wicket_admin'] . '/people/' . $user_id . '/preferences';

        return $link_to_user_profile;
        //return '<a href="'.$link_to_user_profile.'">Link to user profile in Wicket</a>';
    }

    public function validate($value, $form)
    {
        // Do nothing as the preferences widget doesn't need validation
    }
}
