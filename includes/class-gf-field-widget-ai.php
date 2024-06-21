<?php 

if (class_exists('GF_Field')) {
	class GFWicketFieldWidgetAi extends GF_Field {
    // Ref for example: https://awhitepixel.com/tutorial-create-an-advanced-custom-gravity-forms-field-type-and-how-to-handle-multiple-input-values/

		public $type = 'wicket_widget_ai';
 
		public function get_form_editor_field_title() {
      return esc_attr__('Wicket Widget: Additional Info', 'wicket-gf');
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

        <div class="wicket_widget_ai_setting" 
             style="display:none;" 
             x-data="wwidgetAiData" 
             x-init="start" 
             x-on:gf-wwidget-ai-field-settings.window="loadFieldSettings">
          <label>Additional Info Schemas:</label>
          <template x-for="(schema, index) in schemaArray" :key="index">
            <div class="schema-grouping">
              <div class="inputs-wrapper">
                <input @keyup="updateSchemaArray(index, 'schema-id', $el.value)" type="text" placeholder="Schema ID" x-bind:value="typeof schema[0] === 'undefined' ? '' : schema[0]" />
                <input @keyup="updateSchemaArray(index, 'override-id', $el.value)" type="text" placeholder="Schema override ID (optional)" x-bind:value="typeof schema[1] === 'undefined' ? '' : schema[1]" />
                <input @keyup="updateSchemaArray(index, 'friendly-name', $el.value)" type="text" placeholder="Friendly name (optional)" x-bind:value="typeof schema[2] === 'undefined' ? '' : schema[2]" />
              </div>
              <div class="buttons-wrapper">
                <button @click="addNewSchemaGrouping">+</button>
                <button @click="removeSchemaGrouping(index)">-</button>
              </div>
            </div>
          </template>

          <style>
            .wicket_widget_ai_setting {
              margin-bottom: 10px;
            }
            .wicket_widget_ai_setting>label {
              display: block;
              margin-bottom: 0.7rem;
            }
            .wicket_widget_ai_setting .schema-grouping {
              display: flex;
              justify-content: center;
              align-items: center;
              padding: 10px;
              background: #f4f4f4;
              border-radius: 10px;
              margin-bottom: 8px;
            }
            .wicket_widget_ai_setting .schema-grouping .inputs-wrapper {
              display: flex; 
              flex-direction: column;
              width: 100%;
              margin-right: 5px;
            }
            .wicket_widget_ai_setting .schema-grouping .inputs-wrapper input:not(:last-of-type) {
              margin-bottom: 5px;
            }
            .wicket_widget_ai_setting .schema-grouping .buttons-wrapper {
              display: flex;
              flex-direction: column;
            }
            .wicket_widget_ai_setting .schema-grouping button {
              border: 2px solid #c5c5c5;
              background: #fff;
              border-radius: 999px;
              padding: 5px 8px;
            }
            .wicket_widget_ai_setting .schema-grouping button:hover {
              cursor: pointer;
            }
            .wicket_widget_ai_setting .schema-grouping button:first-of-type {
              margin-bottom: 5px;
            }
          </style>

        </div>

        <?php echo ob_get_clean(); ?>

        <?php
      }
    }

    public static function editor_script(){
      ?>
      <script>
      document.addEventListener('alpine:init', () => {
          Alpine.data('wwidgetAiData', () => ({
          schemaArray: [],

          start() {},
          loadFieldSettings(event) {
            let fieldData = event.detail;

            if( typeof fieldData !== 'object' ) {
              fieldData = [ [] ];
            } else if(fieldData.length <= 0) {
              fieldData.push(['']);
            }

            this.schemaArray = fieldData;
          },
          addNewSchemaGrouping() {
            this.schemaArray.push(['']);
          },
          removeSchemaGrouping(index) {
            this.schemaArray.splice(index, 1);
          },
          updateSchemaArray(index, type, value) {
            if( type == 'schema-id' ){
              this.schemaArray[index][0] = value;
            } else if( type == 'override-id' ) {
              this.schemaArray[index][1] = value;
            } else if( type == 'friendly-name' ) {
              this.schemaArray[index][2] = value;
            }

            SetFieldProperty('wwidget_ai_schemas', this.schemaArray);
          },

        }))
      });

      // Catching GF event via jQuery (which it uses) and re-dispatching needed values for easier use
      jQuery(document).on('gform_load_field_settings', (event, field, form) => {
        let customEvent = new CustomEvent("gf-wwidget-ai-field-settings", {
          detail: rgar( field, 'wwidget_ai_schemas' )
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

      $ai_widget_schemas = [[]];

      //wicket_write_log($form, true);

      // TODO: Make this support multiple org search/select elements on one page, if necessary
      foreach( $form['fields'] as $field ) {
        if( gettype( $field ) == 'object' ) {
          if( get_class( $field ) == 'GFWicketFieldWidgetAi' ) {
            if( isset( $field->wwidget_ai_schemas ) ) {
              $ai_widget_schemas = $field->wwidget_ai_schemas; 
            }
          }
        }
      }

      if( component_exists('widget-additional-info') ) {
        // Adding extra ob_start/clean since the component was jumping the gun for some reason
        ob_start();

        get_component( 'widget-additional-info', [ 
          'classes'                          => [],
          'additional_info_data_field_name'  => 'input_' . $id,
          'resource_type'                    => 'people', // TODO: Make this configurable, if needed
          'schemas_and_overrides'            => $ai_widget_schemas,
        ], true );

        return ob_get_clean();
      } else {
        return '<p>Widget-additional-info component is missing. Please update the Wicket Base Plugin.</p>';
      }
       
    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead) {
      $value_array = json_decode($value);
      $user_id = wicket_current_person_uuid();
      $wicket_settings = get_wicket_settings(); 

      $link_to_user_profile = $wicket_settings['wicket_admin'] . '/people/' . $user_id . '/additional_info';

      return $link_to_user_profile;
      //return '<a href="'.$link_to_user_profile.'">Link to user profile in Wicket</a>';
    }

    public function validate( $value, $form ) {
      $value_array = json_decode($value, true);
      //wicket_write_log('Value array:');
      //wicket_write_log($value_array);

      $notFound   = $value_array['notFound'];
      $validation = $value_array['validation'];
      $invalid    = $value_array['invalid'];

      if( count( $invalid ) > 0 ) {
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
	GF_Fields::register(new GFWicketFieldWidgetAi());
}