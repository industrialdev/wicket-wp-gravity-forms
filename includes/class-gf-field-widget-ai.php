<?php

if (!class_exists('GF_Field')) {
    return;
}

class GFWicketFieldWidgetAi extends GF_Field
{
    // Ref for example: https://awhitepixel.com/tutorial-create-an-advanced-custom-gravity-forms-field-type-and-how-to-handle-multiple-input-values/

    public $type = 'wicket_widget_ai';

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Additional Info', 'wicket-gf');
    }

    // Move the field to 'advanced fields'
    public function get_form_editor_button()
    {
        return [
            'group' => 'advanced_fields',
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

    public static function custom_settings($position, $form_id)
    {
        //create settings on position 25 (right after Field Label)
        if ($position == 25) { ?>
            <?php ob_start(); ?>

            <div class="wicket_widget_ai_setting"
                style="display:none;"
                x-data="wwidgetAiData"
                x-on:gf-wwidget-ai-field-settings.window="loadFieldSettings">
                <label>Additional Info Type:</label>
                <select @change="updateAiType($el.value)" x-model="wwidget_ai_type">
                    <option value="people">People</option>
                    <option value="organizations">Organizations</option>
                </select>
                <div x-show=" wwidget_ai_type == 'organizations' ">
                    <label>Org UUID:</label>
                    <input @keyup="SetFieldProperty('wwidget_ai_org_uuid', $el.value)" x-bind:value="wwidget_ai_org_uuid" type="text" placeholder="1234-5678-9100" />
                    <p style="margin-top: 2px;"><em>Tip: if using a multi-page form, and a field on a previous page will get populated with the org UUID, you can simply enter that field ID here instead.</em></p>
                </div>
                <label>Additional Info Schemas:</label>
                <template x-for="(schema, index) in schemaArray" :key="index">
                    <div class="schema-grouping">
                        <div class="inputs-wrapper">
                            <input @keyup="updateSchemaArray(index, 'schema-id', $el.value)" type="text" placeholder="Schema ID" x-bind:value="typeof schema[0] === 'undefined' ? '' : schema[0]" />
                            <input @keyup="updateSchemaArray(index, 'override-id', $el.value)" type="text" placeholder="Schema override ID (optional)" x-bind:value="typeof schema[1] === 'undefined' ? '' : schema[1]" />
                            <input @keyup="updateSchemaArray(index, 'friendly-name', $el.value)" type="text" placeholder="Friendly name (optional)" x-bind:value="typeof schema[2] === 'undefined' ? '' : schema[2]" />
                            <select @change="updateSchemaArray(index, 'show-as-required', $el.value)" x-bind:value="typeof schema[3] === 'undefined' ? '' : schema[3]">
                                <option value="false">Don't show as required</option>
                                <option value="true">Show as required</option>
                            </select>
                        </div>
                        <div class="buttons-wrapper">
                            <button @click="addNewSchemaGrouping">+</button>
                            <button @click="removeSchemaGrouping(index)">-</button>
                        </div>
                    </div>
                </template>

                <input
                    @change="SetFieldProperty('wwidget_ai_use_slugs', $el.checked)" x-bind:value="wwidget_ai_use_slugs"
                    type="checkbox" id="wwidget_ai_use_slugs" class="wwidget_ai_use_slugs">
                <label for="wwidget_ai_use_slugs" class="inline">Use schema slugs instead of IDs</label>

                <style>
                    .wicket_widget_ai_setting {
                        margin-bottom: 10px;
                    }

                    .wicket_widget_ai_setting>label {
                        display: block;
                        margin-bottom: 0.7rem;
                    }

                    .wicket_widget_ai_setting>select {
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

                    .wicket_widget_ai_setting .schema-grouping .inputs-wrapper input {
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

    public static function editor_script()
    {
        ?>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('wwidgetAiData', () => ({
                    schemaArray: [],
                    wwidget_ai_type: 'people',
                    wwidget_ai_org_uuid: '',
                    wwidget_ai_use_slugs: false,

                    loadFieldSettings(event) {
                        let fieldData = event.detail;
                        let fieldDataSchemas = fieldData.ai_schemas;
                        let fieldDataType = fieldData.type;
                        let fieldDataOrgUuid = fieldData.org_uuid;
                        let fieldDataUseSlugs = fieldData.use_slugs;

                        if (typeof fieldDataSchemas !== 'object') {
                            fieldDataSchemas = [
                                []
                            ];
                        } else if (fieldDataSchemas.length <= 0) {
                            fieldDataSchemas.push(['']);
                        }

                        this.schemaArray = fieldDataSchemas;

                        this.wwidget_ai_type = fieldDataType;
                        this.wwidget_ai_org_uuid = fieldDataOrgUuid;
                        this.wwidget_ai_use_slugs = fieldDataUseSlugs;
                    },
                    updateAiType(type) {
                        this.wwidget_ai_type = type;
                        SetFieldProperty('wwidget_ai_type', type);
                    },
                    addNewSchemaGrouping() {
                        this.schemaArray.push(['']);
                    },
                    removeSchemaGrouping(index) {
                        this.schemaArray.splice(index, 1);
                    },
                    updateSchemaArray(index, type, value) {
                        if (type == 'schema-id') {
                            this.schemaArray[index][0] = value;
                        } else if (type == 'override-id') {
                            this.schemaArray[index][1] = value;
                        } else if (type == 'friendly-name') {
                            this.schemaArray[index][2] = value;
                        } else if (type == 'show-as-required') {
                            this.schemaArray[index][3] = value;
                        }

                        SetFieldProperty('wwidget_ai_schemas', this.schemaArray);
                    },

                }))
            });

            // Catching GF event via jQuery (which it uses) and re-dispatching needed values for easier use
            jQuery(document).on('gform_load_field_settings', (event, field, form) => {
                let customEvent = new CustomEvent("gf-wwidget-ai-field-settings", {
                    detail: {
                        ai_schemas: rgar(field, 'wwidget_ai_schemas'),
                        type: rgar(field, 'wwidget_ai_type'),
                        org_uuid: rgar(field, 'wwidget_ai_org_uuid'),
                        use_slugs: rgar(field, 'wwidget_ai_use_slugs'),
                    }
                });
                window.dispatchEvent(customEvent);
            });
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

        $ai_widget_schemas = [[]];
        $wwidget_ai_type = 'people';
        $wwidget_ai_org_uuid = '';
        $wwidget_ai_use_slugs = false;

        //wicket_gf_write_log($form, true);

        foreach ($form['fields'] as $field) {
            if (gettype($field) == 'object') {
                if (get_class($field) == 'GFWicketFieldWidgetAi') {
                    if ($field->id == $id) {
                        if (isset($field->wwidget_ai_schemas)) {
                            $ai_widget_schemas = $field->wwidget_ai_schemas;
                        }
                        if (isset($field->wwidget_ai_type)) {
                            $wwidget_ai_type = $field->wwidget_ai_type;
                        }
                        if (isset($field->wwidget_ai_org_uuid)) {
                            $wwidget_ai_org_uuid = $field->wwidget_ai_org_uuid;
                        }
                        if (isset($field->wwidget_ai_use_slugs)) {
                            $wwidget_ai_use_slugs = $field->wwidget_ai_use_slugs;
                        }
                    }
                }
            }
        }

        // Test if a UUID was manually saved, or if a field ID was saved instead (in the case of a multi-page form)
        if (!str_contains($wwidget_ai_org_uuid, '-')) {
            if (isset($_POST['input_' . $wwidget_ai_org_uuid])) {
                $field_value = $_POST['input_' . $wwidget_ai_org_uuid];
                if (str_contains($field_value, '-')) {
                    $wwidget_ai_org_uuid = $field_value;
                }
            }
        }

        // Re-form the $ai_widget_schemas array before passing to component
        $cleaned_ai_widget_schemas = [];
        if ($wwidget_ai_use_slugs) {
            foreach ($ai_widget_schemas as $ai_item) {
                $cleaned_ai_widget_schemas[] = [
                    'slug'           => $ai_item[0],
                    'resourceSlug'   => $ai_item[1],
                    'showAsRequired' => $ai_item[3],
                ];
            }
        } else {
            foreach ($ai_widget_schemas as $ai_item) {
                $cleaned_ai_widget_schemas[] = [
                    'id'             => $ai_item[0],
                    'resourceId'     => $ai_item[1],
                    'showAsRequired' => $ai_item[3] ?? false,
                ];
            }
        }

        if (component_exists('widget-additional-info')) {
            // Adding extra ob_start/clean since the component was jumping the gun for some reason
            ob_start();

            get_component('widget-additional-info', [
                'classes'                          => [],
                'additional_info_data_field_name'  => 'input_' . $id,
                'resource_type'                    => $wwidget_ai_type,
                'org_uuid'                         => $wwidget_ai_org_uuid,
                'schemas_and_overrides'            => $cleaned_ai_widget_schemas,
            ], true);

            $component_output = ob_get_clean();

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
        } else {
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-additional-info component is missing. Please update the Wicket Base Plugin.</p></div>';
        }
    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $value_array = json_decode($value);
        $user_id = wicket_current_person_uuid();
        $wicket_settings = get_wicket_settings();

        $link_to_user_profile = $wicket_settings['wicket_admin'] . '/people/' . $user_id . '/additional_info';

        return $link_to_user_profile;
        //return '<a href="'.$link_to_user_profile.'">Link to user profile in Wicket</a>';
    }

    public function validate($value, $form)
    {
        $value_array = json_decode($value, true);
        // wicket_gf_write_log('Start validation for field ID: ' . $this->id);
        // wicket_gf_write_log('Value array:');
        // wicket_gf_write_log($value_array);

        $notFound = $value_array['notFound'] ?? [];
        $validation = $value_array['validation'] ?? [];
        $invalid = $value_array['invalid'] ?? [];

        if (count($invalid) > 0 && $this->isRequired) {
            $this->failed_validation = true;
            if (!empty($this->errorMessage)) {
                $this->validation_message = $this->errorMessage;
            }
        }

        // Find our field in the form to get the schemas
        $id = (int) $this->id;
        $field_schemas = [];
        $use_slugs = false;

        // Find this field's data in the form
        foreach ($form['fields'] as $field) {
            if (gettype($field) == 'object' && get_class($field) == 'GFWicketFieldWidgetAi' && $field->id == $id) {
                if (isset($field->wwidget_ai_schemas)) {
                    $field_schemas = $field->wwidget_ai_schemas;
                }
                if (isset($field->wwidget_ai_use_slugs)) {
                    $use_slugs = $field->wwidget_ai_use_slugs;
                }
                break;
            }
        }

        // wicket_gf_write_log('Field schemas from form:');
        // wicket_gf_write_log($field_schemas);
        // wicket_gf_write_log('Use slugs: ' . ($use_slugs ? 'true' : 'false'));

        // Check for required schemas that are empty
        $missing_required = [];

        foreach ($field_schemas as $schema) {
            // Check if schema is configured to show as required (index 3 is "true")
            // wicket_gf_write_log('Checking schema: ');
            // wicket_gf_write_log($schema);

            if (isset($schema[3]) && $schema[3] === 'true') {
                $schema_id = $schema[0]; // The schema ID/slug is at index 0
                // wicket_gf_write_log('Schema is required: ' . $schema_id);

                // Check if this required schema has data
                $schema_has_data = false;

                // Look for data in dataFields array where schema_slug matches our schema_id
                if (isset($value_array['dataFields']) && is_array($value_array['dataFields'])) {
                    foreach ($value_array['dataFields'] as $dataField) {
                        if (isset($dataField['schema_slug']) && $dataField['schema_slug'] === $schema_id) {
                            // wicket_gf_write_log('Found data field for schema_id: ' . $schema_id);
                            // wicket_gf_write_log('Data value:');
                            // wicket_gf_write_log($dataField['value']);

                            // Use the 'valid' flag provided by the component to determine if the field has valid data
                            // This respects the component's own validation rules, including fields with intentionally empty values
                            $schema_has_data = isset($dataField['valid']) && $dataField['valid'] == 1;

                            break; // Found the schema we were looking for, no need to continue loop
                        }
                    }
                }

                if (!$schema_has_data) {
                    // wicket_gf_write_log('No data found for schema_id: ' . $schema_id);
                    // Use friendly name if available (index 2), otherwise use the ID/slug
                    $display_name = !empty($schema[2]) ? $schema[2] : $schema_id;
                    $missing_required[] = $display_name;
                    // wicket_gf_write_log('Added to missing required: ' . $display_name);
                }
            }
        }

        // wicket_gf_write_log('Missing required fields:');
        // wicket_gf_write_log($missing_required);

        // If we have missing required fields, set validation error
        if (count($missing_required) > 0) {
            $this->failed_validation = true;

            // Use custom error message if set, otherwise create one
            if (!empty($this->errorMessage)) {
                $this->validation_message = $this->errorMessage;
            } else {
                $this->validation_message = sprintf(
                    __('Please fill in the required information: %s', 'wicket-gf'),
                    implode(', ', $missing_required)
                );
            }
            // wicket_gf_write_log('Validation failed with message: ' . $this->validation_message);
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
