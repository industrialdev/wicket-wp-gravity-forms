<?php 

if (class_exists('GF_Field')) {
	class GFWicketFieldOrgSearchSelect extends GF_Field {
    // Ref for example: https://awhitepixel.com/tutorial-create-an-advanced-custom-gravity-forms-field-type-and-how-to-handle-multiple-input-values/

		public $type = 'wicket_org_search_select';

    public static function custom_settings( $position, $form_id ) {
      //create settings on position 25 (right after Field Label)
      if ( $position == 25 ) { ?>
        <?php ob_start(); ?>

        <div class="wicket_orgss_setting" style="display:none;" x-data="{
          searchMode: 'org'
        }">
          <label>Search Mode</label>
          <select name="orgss_search_mode" class="orgss_search_mode" x-model="searchMode">
            <option value="org" selected>Organizations</option>
            <option value="groups">Groups (Beta, In Development)</option>
          </select>

          <div x-show=" searchMode == 'org' " class="orgss-org-settings">
            <label style="margin-top: 1em;display: block;">Organization Type</label>
            <input type="text" name="orgss_search_org_type" class="orgss_search_org_type" />
            <p style="margin-top: 2px;margin-bottom: 0px;"><em>If left blank, all organization types will be searchable. If you wish to filter, you'll need to provide the "slug" of the organization type, e.g. "it_company".</em></p>

            <label style="margin-top: 1em;display: block;">Relationship Type Upon Org Creation</label>
            <input type="text" name="orgss_relationship_type_upon_org_creation" class="orgss_relationship_type_upon_org_creation" value="employee" />

            <label style="margin-top: 1em;display: block;">Relationship Mode</label>
            <input type="text" name="orgss_relationship_mode" class="orgss_relationship_mode" value="person_to_organization" />

            <label style="margin-top: 1em;display: block;">Org Type When User Creates New Org</label>
            <input type="text" name="orgss_new_org_type_override" class="orgss_new_org_type_override" value="" />
            <p style="margin-top: 2px;margin-bottom: 1em;"><em>If left blank, the user will be allowed to select the organization type themselves from the frontend.</em></p>
          </div>
          <div x-show=" searchMode == 'groups' class="orgss-groups-settings">
          </div>
        </div>

        <?php echo ob_get_clean(); ?>

        <?php
      }
    }

    public static function editor_script(){
      ?>
      <script type='text/javascript'>
        // TODO: Listen for initial element add and load the default values for certain fields into the GF data

        // Check if we're currently looking at our element, and if so show the settings for it
        let orgss_settings_panes = document.querySelectorAll('.wicket_orgss_setting');
        let gf_fields_wrapper = document.querySelector('#gform_fields');
        let gf_edit_field_button = document.querySelector('.gfield-field-action.gfield-edit');

        jQuery(document).on('gform_load_field_settings', conditionallyShowElementControls);
        gf_fields_wrapper.addEventListener('click', conditionallyShowElementControls);
        gf_edit_field_button.addEventListener('click', conditionallyShowElementControls);

        function conditionallyShowElementControls (event) {
          let selectedField = GetSelectedField(); // GF editor function

          if( selectedField.type == "wicket_org_search_select" ) {
            for (let orgss_settings_pane of orgss_settings_panes) {
              orgss_settings_pane.style.display = "block";
            }
          } else {
            for (let orgss_settings_pane of orgss_settings_panes) {
              orgss_settings_pane.style.display = "none";
            }
          }

        }

        //adding setting to fields of type "text" so GF is aware of them
        fieldSettings.text += ', .orgss_search_mode';
        fieldSettings.text += ', .orgss_search_org_type';
        fieldSettings.text += ', .orgss_relationship_type_upon_org_creation';
        fieldSettings.text += ', .orgss_relationship_mode';
        fieldSettings.text += ', .orgss_new_org_type_override';

        //binding to the load field settings event to load current field values
        jQuery(document).on('gform_load_field_settings', function(event, field, form){
            jQuery( '.orgss_search_mode' ).val( rgar( field, 'orgss_search_mode' ) );
            jQuery( '.orgss_search_org_type' ).val( rgar( field, 'orgss_search_org_type' ) );
            jQuery( '.orgss_relationship_type_upon_org_creation' ).val( rgar( field, 'orgss_relationship_type_upon_org_creation' ) );
            jQuery( '.orgss_relationship_mode' ).val( rgar( field, 'orgss_relationship_mode' ) );
            jQuery( '.orgss_new_org_type_override' ).val( rgar( field, 'orgss_new_org_type_override' ) );
        });

          // Listen for and update other fields
          let searchModeElement = document.querySelector('select[name="orgss_search_mode"]');
          let orgss_search_org_type = document.querySelector('.orgss_search_org_type');
          let orgss_relationship_type_upon_org_creation = document.querySelector('.orgss_relationship_type_upon_org_creation');
          let orgss_relationship_mode = document.querySelector('.orgss_relationship_mode');
          let orgss_new_org_type_override = document.querySelector('.orgss_new_org_type_override');
          searchModeElement.addEventListener('change', function(event){
            SetFieldProperty('orgss_search_mode', searchModeElement.value);
          });
          orgss_search_org_type.addEventListener('change', function (event) {
            SetFieldProperty('orgss_search_org_type', orgss_search_org_type.value);
          });
          orgss_relationship_type_upon_org_creation.addEventListener('change', function (event) {
            SetFieldProperty('orgss_relationship_type_upon_org_creation', orgss_relationship_type_upon_org_creation.value);
          });
          orgss_relationship_mode.addEventListener('change', function (event) {
            SetFieldProperty('orgss_relationship_mode', orgss_relationship_mode.value);
          });
          orgss_new_org_type_override.addEventListener('change', function (event) {
            SetFieldProperty('orgss_new_org_type_override', orgss_new_org_type_override.value);
          });
      </script>
      <?php
    }
 
		public function get_form_editor_field_title() {
      return esc_attr__('Wicket Org Search/Select', 'wicket-gf');
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
        return '<p>Org Search/Select UI will show here on the frontend</p>';
      }

      $id = (int) $this->id;

      $search_mode = 'org';
      $search_org_type = '';
      $relationship_type_upon_org_creation = 'employee';
      $relationship_mode = 'person_to_organization';
      $new_org_type_override = '';

      // TODO: Make this support multiple org search/select elements on one page, if necessary
      foreach( $form['fields'] as $field ) {
        if( gettype( $field ) == 'object' ) {
          if( get_class( $field ) == 'GFWicketFieldOrgSearchSelect' ) {
            if( isset( $field->orgss_search_mode ) ) {
              $search_mode = $field->orgss_search_mode;
            }
            if( isset( $field->orgss_search_org_type ) ) {
              $search_org_type = $field->orgss_search_org_type;
            }
            if( isset( $field->orgss_relationship_type_upon_org_creation ) ) {
              $relationship_type_upon_org_creation = $field->orgss_relationship_type_upon_org_creation;
            }
            if( isset( $field->orgss_relationship_mode ) ) {
              $relationship_mode = $field->orgss_relationship_mode;
            }
            if( isset( $field->orgss_new_org_type_override ) ) {
              $new_org_type_override = $field->orgss_new_org_type_override;
            }
          }
        }
      }

      if( component_exists('org-search-select') ) {
        return get_component( 'org-search-select', [ 
          'classes'                             => [],
          'search_mode'                         => $search_mode, 
          'search_org_type'                     => $search_org_type,
          'relationship_type_upon_org_creation' => $relationship_type_upon_org_creation,
          'relationship_mode'                   => $relationship_mode,
          'new_org_type_override'               => $new_org_type_override,
          'selected_uuid_hidden_field_name'     => 'input_' . $id,
        ], false );
      } else {
        return '<p>Org search/select component is missing. Please update the Wicket Base Plugin.</p>';
      }
      
      
    }

    // Override how to Save the field value
    // public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead) {
    //   if (empty($value)) {
    //     $value = '';
    //   } else {
    //     // Do things
    //   }
    //   return $value;
    // }

    public function validate( $value, $form ) {      
      if (strlen(trim($value)) <= 0) {
        $this->failed_validation = true;
        if ( ! empty( $this->errorMessage ) ) {
            $this->validation_message = $this->errorMessage;
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
	GF_Fields::register(new GFWicketFieldOrgSearchSelect());
}