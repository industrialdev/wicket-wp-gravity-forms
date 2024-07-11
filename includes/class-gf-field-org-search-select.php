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

          <div x-show=" searchMode == 'org' " class="orgss-org-settings" style="padding: 1em 0;">
            <label style="display: block;">Organization Type</label>
            <input type="text" name="orgss_search_org_type" class="orgss_search_org_type" />
            <p style="margin-top: 2px;margin-bottom: 0px;"><em>If left blank, all organization types will be searchable. If you wish to filter, you'll need to provide the "slug" of the organization type, e.g. "it_company".</em></p>

            <label style="margin-top: 1em;display: block;">Relationship Type Upon Org Creation</label>
            <input type="text" name="orgss_relationship_type_upon_org_creation" class="orgss_relationship_type_upon_org_creation" value="employee" />

            <label style="margin-top: 1em;display: block;">Relationship Mode</label>
            <input type="text" name="orgss_relationship_mode" class="orgss_relationship_mode" value="person_to_organization" />

            <label style="margin-top: 1em;display: block;">Org Type When User Creates New Org</label>
            <input type="text" name="orgss_new_org_type_override" class="orgss_new_org_type_override" value="" />
            <p style="margin-top: 2px;"><em>If left blank, the user will be allowed to select the organization type themselves from the frontend.</em></p>
          
            <label style="margin-top: 1em;display: block;">Org name singular</label>
            <input type="text" name="orgss_org_term_singular" class="orgss_org_term_singular" value="" />
            <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organization" or "Chapter". Can be left blank to use default.</em></p>

            <label style="margin-top: 1em;display: block;">Org name plural</label>
            <input type="text" name="orgss_org_term_plural" class="orgss_org_term_plural" value="" />
            <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organizations" or "Chapters". Can be left blank to use default.</em></p>

            <label style="margin-top: 1em;display: block;">'New Org Created' checkbox ID</label>
            <input type="text" name="orgss_checkbox_id_new_org" class="orgss_checkbox_id_new_org" value="" placeholder="E.g. choice_5_12_1" />
            <p style="margin-top: 2px;"><em>ID of checkbox to be checked if a new org gets created.</em></p>
            
            <input type="checkbox" id="orgss_disable_org_creation" class="orgss_disable_org_creation">
					  <label for="orgss_disable_org_creation" class="inline">Disable ability to create new org/entity?</label>
          
          </div>
          <div x-show=" searchMode == 'groups' " class="orgss-groups-settings">
          </div>
        </div>

        <?php echo ob_get_clean(); ?>

        <?php
      }
    }

    public static function editor_script(){
      ?>
      <script type='text/javascript'>
        	// When the page is ready
        window.addEventListener('load', function () {
          if (document.querySelector('body') !== null) { // Some element that should be rendered by now before we execute code

          //adding setting to fields of type "text" so GF is aware of them. Not sure if this is necessary.
          fieldSettings.text += ', .orgss_search_mode';
          fieldSettings.text += ', .orgss_search_org_type';
          fieldSettings.text += ', .orgss_relationship_type_upon_org_creation';
          fieldSettings.text += ', .orgss_relationship_mode';
          fieldSettings.text += ', .orgss_new_org_type_override';
          fieldSettings.text += ', .orgss_org_term_singular';
          fieldSettings.text += ', .orgss_org_term_plural';
          fieldSettings.text += ', .orgss_checkbox_id_new_org';

          // Input fields
          let searchModeElement = document.querySelector('select[name="orgss_search_mode"]');
          let orgss_search_org_type = document.querySelector('.orgss_search_org_type');
          let orgss_relationship_type_upon_org_creation = document.querySelector('.orgss_relationship_type_upon_org_creation');
          let orgss_relationship_mode = document.querySelector('.orgss_relationship_mode');
          let orgss_new_org_type_override = document.querySelector('.orgss_new_org_type_override');
          let orgss_org_term_singular = document.querySelector('.orgss_org_term_singular');
          let orgss_org_term_plural = document.querySelector('.orgss_org_term_plural');
          let orgss_disable_org_creation = document.querySelector('.orgss_disable_org_creation');
          let orgss_checkbox_id_new_org = document.querySelector('.orgss_checkbox_id_new_org');

          // Listen for and update other fields
          searchModeElement.addEventListener('change', (e) => {
            SetFieldProperty('orgss_search_mode', searchModeElement.value);
          });
          orgss_search_org_type.addEventListener('change', (e) => {
            SetFieldProperty('orgss_search_org_type', orgss_search_org_type.value);
          });
          orgss_relationship_type_upon_org_creation.addEventListener('change', (e) => {
            SetFieldProperty('orgss_relationship_type_upon_org_creation', orgss_relationship_type_upon_org_creation.value);
          });
          orgss_relationship_mode.addEventListener('change', (e) => {
            SetFieldProperty('orgss_relationship_mode', orgss_relationship_mode.value);
          });
          orgss_new_org_type_override.addEventListener('change', (e) => {
            SetFieldProperty('orgss_new_org_type_override', orgss_new_org_type_override.value);
          });
          orgss_org_term_singular.addEventListener('change', (e) => {
            SetFieldProperty('orgss_org_term_singular', orgss_org_term_singular.value);
          });
          orgss_org_term_plural.addEventListener('change', (e) => {
            SetFieldProperty('orgss_org_term_plural', orgss_org_term_plural.value);
          }); 
          orgss_checkbox_id_new_org.addEventListener('change', (e) => {
            SetFieldProperty('orgss_checkbox_id_new_org', orgss_checkbox_id_new_org.value);
          }); 
          orgss_disable_org_creation.addEventListener('change', (e) => {
            SetFieldProperty('orgss_disable_org_creation', orgss_disable_org_creation.checked);
          }); 

          //binding to the load field settings event to load current field values
          jQuery(document).on('gform_load_field_settings', function(event, field, form){
            let orgss_search_mode_value = rgar( field, 'orgss_search_mode' );
            let orgss_search_org_type_value = rgar( field, 'orgss_search_org_type' );
            let orgss_relationship_type_upon_org_creation_value = rgar( field, 'orgss_relationship_type_upon_org_creation' );
            let orgss_relationship_mode_value = rgar( field, 'orgss_relationship_mode' );
            let orgss_new_org_type_override_value = rgar( field, 'orgss_new_org_type_override' );
            let orgss_org_term_singular_value = rgar( field, 'orgss_org_term_singular' );
            let orgss_org_term_plural_value = rgar( field, 'orgss_org_term_plural' );
            let orgss_checkbox_id_new_org_value = rgar( field, 'orgss_checkbox_id_new_org' );
            let orgss_disable_org_creation_value = rgar( field, 'orgss_disable_org_creation' );

            // Determine if this is a brand new field or if it has values already
            if( (orgss_search_mode_value +
            orgss_search_org_type_value + 
            orgss_relationship_type_upon_org_creation_value + 
            orgss_relationship_mode_value +
            orgss_new_org_type_override_value +
            orgss_org_term_singular_value +
            orgss_org_term_plural_value +
            orgss_checkbox_id_new_org_value).length <= 0 ) {
              // No values have been saved, so the element must have been freshly added,
              // meaning we should not override the field default values
              // Instead we'll initially save the defaults so this field isn't blank
              orgss_save_default_field_values();
            } else {
              // We have existing values, so we'll load them
              jQuery( '.orgss_search_mode' ).val( orgss_search_mode_value );
              jQuery( '.orgss_search_org_type' ).val( orgss_search_org_type_value );
              jQuery( '.orgss_relationship_type_upon_org_creation' ).val( orgss_relationship_type_upon_org_creation_value );
              jQuery( '.orgss_relationship_mode' ).val( orgss_relationship_mode_value );
              jQuery( '.orgss_new_org_type_override' ).val( orgss_new_org_type_override_value );
              jQuery( '.orgss_org_term_singular' ).val( orgss_org_term_singular_value );
              jQuery( '.orgss_org_term_plural' ).val( orgss_org_term_plural_value );
              jQuery( '.orgss_checkbox_id_new_org' ).val( orgss_checkbox_id_new_org_value );
              if(orgss_disable_org_creation_value) {
                jQuery( '.orgss_disable_org_creation' ).prop( "checked", true );
              } else {
                jQuery( '.orgss_disable_org_creation' ).prop( "checked", false );
              }
            }
          });

          // TODO: Sync these default values with the ones from the component if possible
          function orgss_save_default_field_values(event = null) {
            SetFieldProperty('orgss_search_mode', 'org');
            SetFieldProperty('orgss_search_org_type', '');
            SetFieldProperty('orgss_relationship_type_upon_org_creation', 'employee');
            SetFieldProperty('orgss_relationship_mode', 'person_to_organization');
            SetFieldProperty('orgss_new_org_type_override', '');
            SetFieldProperty('orgss_org_term_singular', 'Organization');
            SetFieldProperty('orgss_org_term_plural', 'Organizations');
            SetFieldProperty('orgss_checkbox_id_new_org', '');
            SetFieldProperty('orgss_disable_org_creation', false);
            }
          }
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

      $search_mode                         = 'org';
      $search_org_type                     = '';
      $relationship_type_upon_org_creation = 'employee';
      $relationship_mode                   = 'person_to_organization';
      $new_org_type_override               = '';
      $org_term_singular                   = 'Organization';
      $org_term_plural                     = 'Organizations';
      $disable_org_creation                = false;
      $checkbox_id_new_org                 = '';

      //wicket_write_log($form, true);

      foreach( $form['fields'] as $field ) {
        if( gettype( $field ) == 'object' ) {
          if( get_class( $field ) == 'GFWicketFieldOrgSearchSelect' ) {
            if( $field->id == $id ) {
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
              if( isset( $field->orgss_org_term_singular ) ) {
                $org_term_singular = $field->orgss_org_term_singular;
              }
              if( isset( $field->orgss_org_term_plural ) ) {
                $org_term_plural = $field->orgss_org_term_plural;
              }
              if( isset( $field->orgss_disable_org_creation ) ) {
                $disable_org_creation = $field->orgss_disable_org_creation;
              }
              if( isset( $field->orgss_checkbox_id_new_org ) ) {
                $checkbox_id_new_org = $field->orgss_checkbox_id_new_org;
              }
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
          'checkbox_id_new_org'                 => $checkbox_id_new_org,
          'key'                                 => $id,
          'org_term_singular'                   => $org_term_singular,
          'org_term_plural'                     => $org_term_plural,
          'disable_create_org_ui'               => $disable_org_creation,
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