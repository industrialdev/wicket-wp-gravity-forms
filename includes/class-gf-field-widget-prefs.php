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

    public static function custom_settings($position, $form_id)
    {
        //create settings on position 25 (right after Field Label)
        if ($position == 25) { ?>
<?php ob_start(); ?>

<li class="wicket_widget_person_prefs_setting field_setting" style="display:none;margin-bottom: 10px;">
    <input onchange="SetFieldProperty('wwidget_prefs_hide_comm', this.checked)"
        type="checkbox" id="wwidget_prefs_hide_comm" class="wwidget_prefs_hide_comm">
    <label for="wwidget_prefs_hide_comm" class="inline">Disable communication preferences?</label>
</li>

<?php echo ob_get_clean(); ?>

    <?php
        }
    }

    public static function editor_script()
    {
        ?>
        <script type='text/javascript'>
        gform.addFilter( 'gform_form_editor_can_field_be_added', function( canAdd, fieldType ) {
        if ( fieldType === 'wicket_widget_prefs' ) {
            return true;
        }
        return canAdd;
    });

    gform.addFilter( 'gform_form_editor_field_settings', function( settings, field ) {
        if ( field.type === 'wicket_widget_prefs' ) {
            settings.push( 'wicket_widget_person_prefs_setting' );
        }
        return settings;
    });

    gform.addAction( 'gform_editor_js_set_field_properties', function( field ) {
        if ( field.type === 'wicket_widget_prefs' ) {
            field.label = 'Wicket Widget: Person Preferences';
            field.wwidget_prefs_hide_comm = false;
        }
    });

    window.WicketGF = window.WicketGF || {};
    window.WicketGF.Prefs = {
        init: function() {
            const self = this;
            gform.addAction( 'gform_load_field_settings', function( field ) {
                if ( field.type === 'wicket_widget_prefs' ) {
                    window.WicketGF.Prefs.loadFieldSettings( field );
                }
            });
        },
        loadFieldSettings: function(field) {
            const hideCommCheckbox = document.getElementById('wwidget_prefs_hide_comm');
            if (hideCommCheckbox) {
                hideCommCheckbox.checked = field.wwidget_prefs_hide_comm || false;
            }
        }
    }
    window.WicketGF.Prefs.init();
</script>

<?php
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
                'preferences_data_field_name'  => 'input_' . $id,
            ], true);

            $component_output = ob_get_clean();

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
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
