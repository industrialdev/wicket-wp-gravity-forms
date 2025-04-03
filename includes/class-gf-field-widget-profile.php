<?php 

if (class_exists('GF_Field')) {
	class GFWicketFieldWidgetProfile extends GF_Field {
    // Ref for example: https://awhitepixel.com/tutorial-create-an-advanced-custom-gravity-forms-field-type-and-how-to-handle-multiple-input-values/

		public $type = 'wicket_widget_profile_individual';
 
		public function get_form_editor_field_title() {
      return esc_attr__('Wicket Widget: Profile', 'wicket-gf');
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

    // Render the field
    public function get_field_input($form, $value = '', $entry = null) {
      if ( $this->is_form_editor() ) {
        return '<p>Widget will show here on the frontend</p>';
      }

      $id = (int) $this->id;

      if( component_exists('widget-profile-individual') ) {
        $component_output = get_component( 'widget-profile-individual', [ 
          'classes'                    => [],
          'user_info_data_field_name'  => 'input_' . $id,
        ], false );
        return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
      } else {
        return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-profile-individual component is missing. Please update the Wicket Base Plugin.</p></div>';
      }
       
    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead) {
      $value_array = json_decode($value);
      $user_id = $value_array->attributes->uuid;
      $wicket_settings = get_wicket_settings(); 

      $link_to_user_profile = $wicket_settings['wicket_admin'] . '/people/' . $user_id;

      return $link_to_user_profile;
      //return '<a href="'.$link_to_user_profile.'">Link to user profile in Wicket</a>';
    }

    public function validate( $value, $form ) {
      $value_array = json_decode($value, true);
      if( isset( $value_array['incompleteRequiredFields'] ) ) {
        if( count( $value_array['incompleteRequiredFields'] ) > 0 ) {
          $this->failed_validation = true;
          if ( ! empty( $this->errorMessage ) ) {
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
	GF_Fields::register(new GFWicketFieldWidgetProfile());
}