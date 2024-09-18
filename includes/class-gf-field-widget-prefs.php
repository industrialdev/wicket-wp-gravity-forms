<?php 

if (class_exists('GF_Field')) {
	class GFWicketFieldWidgetPrefs extends GF_Field {
    // Ref for example: https://awhitepixel.com/tutorial-create-an-advanced-custom-gravity-forms-field-type-and-how-to-handle-multiple-input-values/

		public $type = 'wicket_widget_prefs';
 
		public function get_form_editor_field_title() {
      return esc_attr__('Wicket Widget: Person Preferences', 'wicket-gf');
    }

    // Move the field to 'advanced fields'
    public function get_form_editor_button() {
      return [
        'group' => 'advanced_fields',
        'text'  => $this->get_form_editor_field_title(),
      ];
    }

    function get_form_editor_field_settings() {
      return [
        'label_setting',
        'description_setting',
        'rules_setting',
        'error_message_setting',
        'css_class_setting',
        'conditional_logic_field_setting'
      ];
    }

    public static function custom_settings( $position, $form_id ) {
      //create settings on position 25 (right after Field Label)
      if ( $position == 25 ) { ?>
        <?php ob_start(); ?>

        <div class="wicket_widget_person_prefs_setting" 
             style="display:none;margin-bottom: 10px;" 
             x-data="wwidgetPersonPrefsData" 
             x-on:gf-wwidget-person-prefs-field-settings.window="loadFieldSettings" >
            <input 
              @change="SetFieldProperty('wwidget_prefs_hide_comm', $el.checked)" x-bind:value="wwidget_prefs_hide_comm"
              type="checkbox" id="wwidget_prefs_hide_comm" class="wwidget_prefs_hide_comm">
					  <label for="wwidget_prefs_hide_comm" class="inline">Disable communication preferences?</label>
        </div>

        <?php echo ob_get_clean(); ?>

        <?php
      }
    }

    public static function editor_script(){
      ?>
      <script>
      document.addEventListener('alpine:init', () => {
          Alpine.data('wwidgetPersonPrefsData', () => ({
            wwidget_prefs_hide_comm: false,

          loadFieldSettings(event) {
            let fieldData = event.detail;

            if( Object.hasOwn(fieldData, 'wwidget_prefs_hide_comm') ) {
              // Handle checkboxes slightly differently
              this.wwidget_prefs_hide_comm = fieldData.wwidget_prefs_hide_comm ? true : false;
            }
          },
        }))
      });

      // Catching GF event via jQuery (which it uses) and re-dispatching needed values for easier use
      jQuery(document).on('gform_load_field_settings', (event, field, form) => {
        let detailPayload = {
          wwidget_prefs_hide_comm: rgar( field, 'wwidget_prefs_hide_comm' ),
        };
        let customEvent = new CustomEvent("gf-wwidget-person-prefs-field-settings", {
          detail: detailPayload
        });
        window.dispatchEvent(customEvent);
      });
    </script>

    <?php
    }

    // Render the field
    public function get_field_input($form, $value = '', $entry = null) {
      if ( $this->is_form_editor() ) {
        return '<p>Widget will show here on the frontend</p>';
      }

      $id = (int) $this->id;

      $hide_comm_prefs = false;

      foreach( $form['fields'] as $field ) {
        if( gettype( $field ) == 'object' ) {
          if( get_class( $field ) == 'GFWicketFieldWidgetPrefs' ) {
            if( $field->id == $id ) {
              if( isset( $field->wwidget_prefs_hide_comm ) ) {
                $hide_comm_prefs = (bool) $field->wwidget_prefs_hide_comm;
              }
            }
          }
        }
      }

      if( component_exists('widget-prefs-person') ) {
        // Adding extra ob_start/clean since the component was jumping the gun for some reason
        ob_start();

        get_component( 'widget-prefs-person', [ 
          'classes'                      => [],
          'hide_comm_prefs'              => $hide_comm_prefs,
          'preferences_data_field_name'  => 'input_' . $id,
        ], true );

        return ob_get_clean();
      } else {
        return '<p>Widget-prefs-person component is missing. Please update the Wicket Base Plugin.</p>';
      }
       
    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead) {
      $value_array = json_decode($value);
      $user_id = wicket_current_person_uuid();
      $wicket_settings = get_wicket_settings(); 

      $link_to_user_profile = $wicket_settings['wicket_admin'] . '/people/' . $user_id . '/preferences';

      return $link_to_user_profile;
      //return '<a href="'.$link_to_user_profile.'">Link to user profile in Wicket</a>';
    }

    public function validate( $value, $form ) {
      // Do nothing as the preferences widget doesn't need validation
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
	GF_Fields::register(new GFWicketFieldWidgetPrefs());
}