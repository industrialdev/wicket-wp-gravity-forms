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
            'wicket_widget_ai_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.wwidget_ai_schemas = [[]];
                field.wwidget_ai_type = 'people';
                field.wwidget_ai_org_uuid = '';
                field.wwidget_ai_use_slugs = false;
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public static function custom_settings($position, $form_id)
    {
        if ($position == 25) { ?>
            <?php ob_start(); ?>

            <li class="wicket_widget_ai_setting field_setting" style="display:none;">
                <label>Additional Info Type:</label>
                <select id="ai_type_selector" onchange="SetFieldProperty('wwidget_ai_type', this.value)">
                    <option value="people">People</option>
                    <option value="organizations">Organizations</option>
                </select>

                <div id="ai_org_uuid_wrapper" style="display: none;">
                    <label>Org UUID:</label>
                    <input id="ai_org_uuid_input" onkeyup="SetFieldProperty('wwidget_ai_org_uuid', this.value)" type="text" placeholder="1234-5678-9100" />
                    <p style="margin-top: 2px;"><em>Tip: if using a multi-page form, and a field on a previous page will get populated with the org UUID, you can simply enter that field ID here instead.</em></p>
                </div>

                <label>Additional Info Schemas:</label>
                <div id="ai_schema_container"></div>
                <button id="ai_add_schema_button" style="margin-top: 10px; padding: 5px 10px;">Add Schema</button>

                <div style="margin-top: 10px;">
                    <input onchange="SetFieldProperty('wwidget_ai_use_slugs', this.checked)" type="checkbox" id="ai_use_slugs" class="ai_use_slugs">
                    <label for="ai_use_slugs" class="inline">Use schema slugs instead of IDs</label>
                </div>
            </li>

            <?php echo ob_get_clean(); ?>

            <script type='text/javascript'>
            // Embed JavaScript directly in field settings to avoid gform_editor_js conflicts
            jQuery(document).ready(function($) {
                // Use the official Gravity Forms API to wait for field settings to load
                $(document).on('gform_load_field_settings', function(event, field) {
                    // Only initialize for our field type
                    if (field.type !== 'wicket_widget_ai') {
                        return;
                    }

                    // Initialize the Additional Info widget functionality
        window.WicketGF = window.WicketGF || {};
        window.WicketGF.AdditionalInfo = {
            schemaArray: [],

            loadFieldSettings: function(field) {
                            // Ensure we have a valid schema array
                let fieldDataSchemas = field.wwidget_ai_schemas || [[]];
                            // Make sure we have at least one empty schema if none exist
                            if (!fieldDataSchemas.length) {
                                fieldDataSchemas = [[]];
                            }
                this.schemaArray = fieldDataSchemas;

                            // Set values for form elements
                            $('#ai_type_selector').val(field.wwidget_ai_type || 'people');
                            $('#ai_org_uuid_input').val(field.wwidget_ai_org_uuid || '');
                            $('#ai_use_slugs').prop('checked', field.wwidget_ai_use_slugs || false);

                            // Always render schemas after loading field settings
                this.renderSchemas();
                this.updateAiType(field.wwidget_ai_type || 'people');
            },

            updateAiType: function(type) {
                            var orgUuidWrapper = $('#ai_org_uuid_wrapper');
                            if (type === 'organizations') {
                                orgUuidWrapper.show();
                            } else {
                                orgUuidWrapper.hide();
                            }
                        },

            addNewSchemaGrouping: function() {
                this.schemaArray.push([]);
                this.renderSchemas();
            },

            removeSchemaGrouping: function(index) {
                this.schemaArray.splice(index, 1);
                this.renderSchemas();
                SetFieldProperty('wwidget_ai_schemas', this.schemaArray);
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

                SetFieldProperty('wwidget_ai_schemas', this.schemaArray);
            },

            renderSchemas: function() {
                            var container = $('#ai_schema_container');
                            if (!container.length) {
                                console.error('Schema container not found');
                                return;
                            }

                            container.empty();

                            // Ensure we have at least one schema
                            if (!this.schemaArray.length) {
                                this.schemaArray = [[]];
                            }

                            var self = this;

                                                         // Create schema groupings
                             for (let i = 0; i < this.schemaArray.length; i++) {
                                 let schemaGrouping = this.schemaArray[i];

                                 // Create row with flexbox layout
                                 let row = $('<div>').css({
                                     'border': '1px solid #ddd',
                                     'padding': '15px',
                                     'margin-bottom': '10px',
                                     'border-radius': '4px',
                                     'background': '#f9f9f9',
                                     'position': 'relative'
                                 });

                                 // Create main content area
                                 let contentArea = $('<div>').css({
                                     'margin-right': '60px'
                                 });

                                 // Create button area on the right
                                 let buttonArea = $('<div>').css({
                                     'position': 'absolute',
                                     'right': '15px',
                                     'top': '15px',
                                     'display': 'flex',
                                     'flex-direction': 'column',
                                     'gap': '5px'
                                 });

                                 (function(index) {
                                     // Schema ID input
                                     $('<input>').attr({
                                         'type': 'text',
                                         'placeholder': 'Schema ID',
                                         'value': schemaGrouping[0] || '',
                                         'style': 'width: 100%; margin-bottom: 8px; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                                     }).on('input', function() {
                                         self.updateSchemaArray(index, 'schema-id', this.value);
                                     }).appendTo(contentArea);

                                     // Override ID input
                                     $('<input>').attr({
                                         'type': 'text',
                                         'placeholder': 'Schema override ID (optional)',
                                         'value': schemaGrouping[1] || '',
                                         'style': 'width: 100%; margin-bottom: 8px; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                                     }).on('input', function() {
                                         self.updateSchemaArray(index, 'override-id', this.value);
                                     }).appendTo(contentArea);

                                     // Friendly name input
                                     $('<input>').attr({
                                         'type': 'text',
                                         'placeholder': 'Friendly name (optional)',
                                         'value': schemaGrouping[2] || '',
                                         'style': 'width: 100%; margin-bottom: 8px; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                                     }).on('input', function() {
                                         self.updateSchemaArray(index, 'friendly-name', this.value);
                                     }).appendTo(contentArea);

                                     // Required dropdown
                                     var requiredSelect = $('<select>').attr({
                                         'style': 'width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                                     }).on('change', function() {
                                         self.updateSchemaArray(index, 'show-as-required', this.value === 'required');
                                     });

                                     requiredSelect.append($('<option>').val('not-required').text("Don't show as required"));
                                     requiredSelect.append($('<option>').val('required').text('Show as required'));
                                     requiredSelect.val(schemaGrouping[3] ? 'required' : 'not-required');
                                     contentArea.append(requiredSelect);

                                     // Add button (always visible)
                                     var addButton = $('<button>').attr({
                                         'type': 'button',
                                         'title': 'Add schema'
                                     }).css({
                                         'width': '30px',
                                         'height': '30px',
                                         'border-radius': '50%',
                                         'border': '1px solid #ddd',
                                         'background': '#fff',
                                         'cursor': 'pointer',
                                         'display': 'flex',
                                         'align-items': 'center',
                                         'justify-content': 'center',
                                         'font-size': '16px',
                                         'color': '#666'
                                     }).text('+').on('click', function(e) {
                                         e.preventDefault();
                                         self.addNewSchemaGrouping();
                                     });
                                     buttonArea.append(addButton);

                                     // Remove button (only if more than one schema)
                                     if (self.schemaArray.length > 1) {
                                         var removeButton = $('<button>').attr({
                                             'type': 'button',
                                             'title': 'Remove schema'
                                         }).css({
                                             'width': '30px',
                                             'height': '30px',
                                             'border-radius': '50%',
                                             'border': '1px solid #ddd',
                                             'background': '#fff',
                                             'cursor': 'pointer',
                                             'display': 'flex',
                                             'align-items': 'center',
                                             'justify-content': 'center',
                                             'font-size': '16px',
                                             'color': '#666'
                                         }).text('-').on('click', function(e) {
                                             e.preventDefault();
                                             self.removeSchemaGrouping(index);
                                         });
                                         buttonArea.append(removeButton);
                                     }
                                 })(i);

                                 row.append(contentArea);
                                 row.append(buttonArea);
                                 container.append(row);
                             }
                        }
                    };

                    // Load field settings and set up event handlers
                    window.WicketGF.AdditionalInfo.loadFieldSettings(field);

                    // Set up type selector change handler
                    $('#ai_type_selector').off('change.wicket-ai').on('change.wicket-ai', function() {
                        window.WicketGF.AdditionalInfo.updateAiType(this.value);
                    });

                    // Set up add schema button handler
                    $('#ai_add_schema_button').off('click.wicket-ai').on('click.wicket-ai', function(e) {
                        e.preventDefault();
                        window.WicketGF.AdditionalInfo.addNewSchemaGrouping();
                    });
                });
    });
</script>

<?php
        }
    }

    public static function editor_script()
    {
        // JavaScript is now embedded directly in the field settings output
        // to avoid conflicts with the gform_editor_js hook
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
                        if (isset($field->wwidget_ai_schemas)) {
                            $ai_widget_schemas = $field->wwidget_ai_schemas;
                        }
                        if (isset($field->wwidget_ai_type)) {
                            $ai_type = $field->wwidget_ai_type;
                        }
                        if (isset($field->wwidget_ai_org_uuid)) {
                            $org_uuid = $field->wwidget_ai_org_uuid;
                        }
                        if (isset($field->wwidget_ai_use_slugs)) {
                            $use_slugs = $field->wwidget_ai_use_slugs;
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
                if (isset($field->wwidget_ai_schemas)) {
                    $field_schemas = $field->wwidget_ai_schemas;
                }
                if (isset($field->wwidget_ai_use_slugs)) {
                    $use_slugs = $field->wwidget_ai_use_slugs;
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
