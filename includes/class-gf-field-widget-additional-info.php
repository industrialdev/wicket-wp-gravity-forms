<?php
class GFWicketFieldWidgetAdditionalInfo extends GF_Field
{
    public $type = 'wicket_widget_ai';

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Additional Info', 'wicket-gf');
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
            'ai_type_setting',
            'ai_org_uuid_setting',
            'ai_schemas_setting',
            'ai_use_slugs_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.ai_schemas = [[]];
                field.ai_type = 'people';
                field.org_uuid = '';
                field.use_slugs = false;
            }",
            $this->type,
            $this->get_form_editor_field_title()
        );
    }

    public static function custom_settings($position, $form_id)
    {
        if ($position == 25) { ?>
            <li class="ai_type_setting field_setting">
                <label>Additional Info Type:</label>
                <select id="ai_type_selector" onchange="window.WicketGF.AdditionalInfo.updateAiType(this.value)">
                    <option value="people">People</option>
                    <option value="organizations">Organizations</option>
                </select>
            </li>
            <li class="ai_org_uuid_setting field_setting">
                <div id="ai_org_uuid_wrapper">
                    <label>Org UUID:</label>
                    <input id="ai_org_uuid_input" onkeyup="SetFieldProperty('org_uuid', this.value)" type="text" placeholder="1234-5678-9100" />
                    <p style="margin-top: 2px;"><em>Tip: if using a multi-page form, and a field on a previous page will get populated with the org UUID, you can simply enter that field ID here instead.</em></p>
                </div>
            </li>
            <li class="ai_schemas_setting field_setting">
                <label>Additional Info Schemas:</label>
                <div id="ai_schema_container">

                </div>
                <button id="ai_add_schema_button">+</button>
            </li>
            <li class="ai_use_slugs_setting field_setting">
                <input
                    onchange="SetFieldProperty('use_slugs', this.checked)"
                    type="checkbox" id="ai_use_slugs" class="ai_use_slugs">
                <label for="ai_use_slugs" class="inline">Use schema slugs instead of IDs</label>
            </li>
        <?php
        }
    }

    public static function editor_script()
    {
        ?>
        <script type='text/javascript'>
        gform.addFilter( 'gform_form_editor_can_field_be_added', function( canAdd, fieldType ) {
            if ( fieldType === 'wicket_widget_ai' ) {
                return true;
            }
            return canAdd;
        });

        gform.addFilter( 'gform_form_editor_field_settings', function( settings, field ) {
            if ( field.type === 'wicket_widget_ai' ) {
                settings.push( 'ai_type_setting' );
                settings.push( 'ai_org_uuid_setting' );
                settings.push( 'ai_schemas_setting' );
                settings.push( 'ai_use_slugs_setting' );
            }
            return settings;
        });

        gform.addAction( 'gform_editor_js_set_field_properties', function( field ) {
            if ( field.type === 'wicket_widget_ai' ) {
                field.label = 'Wicket Widget: Additional Info';
                field.ai_schemas = [[]];
                field.ai_type = 'people';
                field.org_uuid = '';
                field.use_slugs = false;
            }
        });

        window.WicketGF = window.WicketGF || {};
        window.WicketGF.AdditionalInfo = {
            schemaArray: [],
            init: function() {
                const self = this;
                gform.addAction( 'gform_load_field_settings', function( field ) {
                    // Only process if this is our field type
                    if ( field.type !== 'wicket_widget_ai' ) {
                        return;
                    }

                    // Minimal field initialization - let PHP migration handle the complex stuff
                    window.WicketGF.AdditionalInfo.loadFieldSettings( field );
                });

                // Wait for DOM to be ready before adding event listeners
                jQuery(document).ready(function() {
                    const addButton = document.getElementById('ai_add_schema_button');
                    if (addButton) {
                        addButton.addEventListener('click', function(e) {
                            e.preventDefault();
                            self.addNewSchemaGrouping();
                        });
                    }
                });
            },
            loadFieldSettings: function(field) {
                let fieldDataSchemas = field.ai_schemas || [[]];
                this.schemaArray = fieldDataSchemas;

                // Check if elements exist before trying to set values
                const typeSelector = document.getElementById('ai_type_selector');
                if (typeSelector) {
                    typeSelector.value = field.ai_type || 'people';
                }

                const orgUuidInput = document.getElementById('ai_org_uuid_input');
                if (orgUuidInput) {
                    orgUuidInput.value = field.org_uuid || '';
                }

                const useSlugsCheckbox = document.getElementById('ai_use_slugs');
                if (useSlugsCheckbox) {
                    useSlugsCheckbox.checked = field.use_slugs || false;
                }

                this.renderSchemas();
                this.updateAiType(field.ai_type || 'people');
            },
            updateAiType: function(type) {
                SetFieldProperty('ai_type', type);
                const orgUuidWrapper = document.getElementById('ai_org_uuid_wrapper');
                if (orgUuidWrapper) {
                    orgUuidWrapper.style.display = type === 'organizations' ? 'block' : 'none';
                }
            },
            addNewSchemaGrouping: function() {
                this.schemaArray.push([]);
                this.renderSchemas();
            },
            removeSchemaGrouping: function(index) {
                this.schemaArray.splice(index, 1);
                this.renderSchemas();
                SetFieldProperty('ai_schemas', this.schemaArray);
            },
            updateSchemaArray: function(index, type, value) {
                if (!this.schemaArray[index]) {
                    this.schemaArray[index] = [];
                }
                if (type == 'schema-id') {
                    this.schemaArray[index][0] = value;
                } else if (type == 'override-id') {
                    this.schemaArray[index][1] = value;
                } else if (type == 'friendly-name') {
                    this.schemaArray[index][2] = value;
                } else if (type == 'show-as-required') {
                    this.schemaArray[index][3] = value;
                }

                SetFieldProperty('ai_schemas', this.schemaArray);
            },
            renderSchemas: function() {
                const container = document.getElementById('ai_schema_container');
                if (!container) {
                    return;
                }

                container.innerHTML = '';

                // Create schema groupings
                for (let i = 0; i < this.schemaArray.length; i++) {
                    let schemaGrouping = this.schemaArray[i];

                    // Create row
                    let row = document.createElement('div');
                    row.style.borderBottom = '1px solid #ccc';
                    row.style.paddingBottom = '10px';
                    row.style.marginBottom = '10px';

                    // Create schema selector
                    let schemaLabel = document.createElement('label');
                    schemaLabel.textContent = 'Schema ' + (i + 1) + ':';
                    schemaLabel.style.display = 'block';
                    row.appendChild(schemaLabel);

                    let schemaInput = document.createElement('input');
                    schemaInput.type = 'text';
                    schemaInput.placeholder = 'Schema ID/Slug';
                    schemaInput.value = schemaGrouping[0] || '';
                    schemaInput.addEventListener('input', (e) => {
                        this.updateSchemaArray(i, 'schema-id', e.target.value);
                    });
                    row.appendChild(schemaInput);

                    // Create override ID field
                    let overrideLabel = document.createElement('label');
                    overrideLabel.textContent = 'Resource ID Override:';
                    overrideLabel.style.display = 'block';
                    overrideLabel.style.marginTop = '5px';
                    row.appendChild(overrideLabel);

                    let overrideInput = document.createElement('input');
                    overrideInput.type = 'text';
                    overrideInput.placeholder = 'Optional override ID';
                    overrideInput.value = schemaGrouping[1] || '';
                    overrideInput.addEventListener('input', (e) => {
                        this.updateSchemaArray(i, 'override-id', e.target.value);
                    });
                    row.appendChild(overrideInput);

                    // Create friendly name field
                    let friendlyLabel = document.createElement('label');
                    friendlyLabel.textContent = 'Friendly Name:';
                    friendlyLabel.style.display = 'block';
                    friendlyLabel.style.marginTop = '5px';
                    row.appendChild(friendlyLabel);

                    let friendlyInput = document.createElement('input');
                    friendlyInput.type = 'text';
                    friendlyInput.placeholder = 'Display name for frontend';
                    friendlyInput.value = schemaGrouping[2] || '';
                    friendlyInput.addEventListener('input', (e) => {
                        this.updateSchemaArray(i, 'friendly-name', e.target.value);
                    });
                    row.appendChild(friendlyInput);

                    // Create show as required checkbox
                    let requiredDiv = document.createElement('div');
                    requiredDiv.style.marginTop = '5px';

                    let requiredCheckbox = document.createElement('input');
                    requiredCheckbox.type = 'checkbox';
                    requiredCheckbox.id = 'schema_required_' + i;
                    requiredCheckbox.checked = schemaGrouping[3] || false;
                    requiredCheckbox.addEventListener('change', (e) => {
                        this.updateSchemaArray(i, 'show-as-required', e.target.checked);
                    });
                    requiredDiv.appendChild(requiredCheckbox);

                    let requiredLabel = document.createElement('label');
                    requiredLabel.setAttribute('for', 'schema_required_' + i);
                    requiredLabel.textContent = ' Show as required';
                    requiredLabel.className = 'inline';
                    requiredDiv.appendChild(requiredLabel);

                    row.appendChild(requiredDiv);

                    // Create remove button
                    if (this.schemaArray.length > 1) {
                        let removeButton = document.createElement('button');
                        removeButton.textContent = 'Remove';
                        removeButton.style.marginTop = '5px';
                        removeButton.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.removeSchemaGrouping(i);
                        });
                        row.appendChild(removeButton);
                    }

                    container.appendChild(row);
                }
            }
        };

        window.WicketGF.AdditionalInfo.init();
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
        $unique_component_id = 'wicket-ai-widget-' . $id; // Generate a unique ID for the component instance

        $ai_widget_schemas = [[]];
        $ai_type = 'people';
        $org_uuid = '';
        $use_slugs = false;

        foreach ($form['fields'] as $field) {
            if (gettype($field) == 'object') {
                if (get_class($field) == 'GFWicketFieldWidgetAdditionalInfo') {
                    if ($field->id == $id) {
                        if (isset($field->ai_schemas)) {
                            $ai_widget_schemas = $field->ai_schemas;
                        }
                        if (isset($field->ai_type)) {
                            $ai_type = $field->ai_type;
                        }
                        if (isset($field->org_uuid)) {
                            $org_uuid = $field->org_uuid;
                        }
                        if (isset($field->use_slugs)) {
                            $use_slugs = $field->use_slugs;
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

        // Re-form the $ai_widget_schemas array before passing to component
        $cleaned_ai_widget_schemas = [];
        if ($use_slugs) {
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
                'id'                               => $unique_component_id, // Pass the unique ID to the component
                'classes'                          => [],
                'additional_info_data_field_name'  => 'input_' . $id,
                'resource_type'                    => $ai_type,
                'org_uuid'                         => $org_uuid,
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
            if (gettype($field) == 'object' && get_class($field) == 'GFWicketFieldWidgetAdditionalInfo' && $field->id == $id) {
                if (isset($field->ai_schemas)) {
                    $field_schemas = $field->ai_schemas;
                }
                if (isset($field->use_slugs)) {
                    $use_slugs = $field->use_slugs;
                }
                break;
            }
        }

        // Check for required schemas that are empty
        $missing_required = [];

        foreach ($field_schemas as $index => $schema) {
            // Check if schema is marked as required (4th element)
            $is_required = isset($schema[3]) && $schema[3] === true;

            if ($is_required) {
                $schema_identifier = $use_slugs ? $schema[0] : $schema[0]; // schema ID or slug
                $schema_name = !empty($schema[2]) ? $schema[2] : $schema_identifier; // friendly name or fallback

                // Check if this required schema has data in the validation array
                $has_data = false;

                if (isset($validation[$schema_identifier])) {
                    $schema_data = $validation[$schema_identifier];
                    // Consider it has data if any field in the schema has a value
                    if (is_array($schema_data)) {
                        foreach ($schema_data as $field_value) {
                            if (!empty($field_value)) {
                                $has_data = true;
                                break;
                            }
                        }
                    }
                }

                if (!$has_data) {
                    $missing_required[] = $schema_name;
                }
            }
        }

        if (!empty($missing_required)) {
            $this->failed_validation = true;
            $this->validation_message = sprintf(
                'The following required information is missing: %s',
                implode(', ', $missing_required)
            );
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
