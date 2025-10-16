<?php
class GFWicketFieldWidgetAdditionalInfo extends GF_Field
{
    public $type = 'wicket_widget_ai';

    /**
     * Initialize the widget field and enqueue validation scripts
     */
    public static function init() {
        // Enqueue validation scripts when this widget is used
        add_action('gform_enqueue_scripts', [__CLASS__, 'enqueue_validation_scripts'], 10, 2);
    }

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
    public function get_value_submission($field_values, $get_from_post = true)
    {
        if ($get_from_post) {
            // Use standard GF naming convention
            $value = rgpost('input_' . $this->id);

            return $value;
        }

        return parent::get_value_submission($field_values, $get_from_post);
    }

    // Ensure GF does not treat our JSON payload as empty when required logic runs.
    // We let validate() decide validity based on the widget's 'invalid' array.
    public function is_value_submission_empty($form_id)
    {
        $value = $this->get_value_submission([], true);

        // Log for diagnostics
        $logger = wc_get_logger();
        $logger->debug('GF AI Widget is_value_submission_empty called for field ' . $this->id . ' with value: ' . var_export($value, true), ['source' => 'gravityforms-state-debug']);

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return true; // truly empty
            }

            // If it's valid JSON representing an object/array, treat as not empty so GF doesn't auto-fail required check.
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded))) {
                return false;
            }

            // Non-empty string, not JSON: still consider not empty to avoid false required failures.
            return false;
        }

        // Fallback to parent behavior for non-string cases.
        return parent::is_value_submission_empty($form_id);
    }

    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<p>Widget will show here on the frontend</p>';
        }

        $logger = wc_get_logger();
        $logger->debug('GF AI Widget get_field_input called for field ' . $this->id, ['source' => 'gravityforms-state-debug']);
        $logger->debug('Field isRequired: ' . var_export($this->isRequired, true), ['source' => 'gravityforms-state-debug']);
        $logger->debug('Field properties: ' . var_export(get_object_vars($this), true), ['source' => 'gravityforms-state-debug']);

        $id = (int) $this->id;
        $unique_component_id = 'wicket-ai-widget-' . $id; // Generate a unique ID for the component instance

        $ai_widget_schemas = $this->wwidget_ai_schemas ?? [[]];
        $ai_type = $this->wwidget_ai_type ?? 'people';
        $org_uuid = $this->wwidget_ai_org_uuid ?? '';
        $use_slugs = $this->wwidget_ai_use_slugs ?? false;

        // On multi-page forms, the pre-render hook runs too late. We need to get the UUID directly from POST.
        $current_page = GFFormDisplay::get_current_page($form['id']);
        if ($current_page > 1) {
            // Find the org_uuid from the POST data of the previous page
            foreach ($form['fields'] as $field) {
                if ($field->type == 'wicket_org_search_select') {
                    // Use standard GF naming convention
                    $field_name = 'input_' . $field->id;

                    if (!empty($_POST[$field_name])) {
                        $org_uuid = sanitize_text_field($_POST[$field_name]);
                        break;
                    }
                }
            }
        }

        // Re-form the $ai_widget_schemas array before passing to component
        $cleaned_ai_widget_schemas = [];
        if ($use_slugs) {
            foreach ($ai_widget_schemas as $ai_item) {
                $cleaned_ai_widget_schemas[] = [
                    'slug'           => $ai_item[0] ?? '',
                    'resourceSlug'   => $ai_item[1] ?? '',
                    'showAsRequired' => isset($ai_item[3]) ? (bool) $ai_item[3] : false,
                ];
            }
        } else {
            foreach ($ai_widget_schemas as $ai_item) {
                $cleaned_ai_widget_schemas[] = [
                    'id'             => $ai_item[0] ?? '',
                    'resourceId'     => $ai_item[1] ?? '',
                    'showAsRequired' => isset($ai_item[3]) ? (bool) $ai_item[3] : false,
                ];
            }
        }

        if (component_exists('widget-additional-info')) {

            // Use standard GF naming convention
            $ai_info_field_name = 'input_' . $id;
            $ai_validation_field_name = 'input_' . $id . '_validation';

            $component_output = get_component(
                'widget-additional-info',
                [
                    'additional_info_data_field_name' => $ai_info_field_name,
                    'validation_data_field_name'      => $ai_validation_field_name,
                    'resource_type'                   => $ai_type,
                    'org_uuid'                        => $org_uuid,
                    'schemas_and_overrides'           => $cleaned_ai_widget_schemas,
                ],
                false
            );

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
    }

    public function validate($value, $form)
    {
        $logger = wc_get_logger();
        $logger->debug('GF AI Widget validate called', ['source' => 'gravityforms-state-debug']);
        $logger->debug('Validate value: ' . var_export($value, true), ['source' => 'gravityforms-state-debug']);

        $value_array = json_decode($value, true);
        $logger->debug('JSON decode result: ' . var_export($value_array, true), ['source' => 'gravityforms-state-debug']);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->debug('JSON decode error: ' . json_last_error_msg(), ['source' => 'gravityforms-state-debug']);
            if (!empty($value)) {
                $this->failed_validation = true;
                $this->validation_message = 'Invalid data format submitted.';
                $logger->debug('Setting failed_validation due to JSON error', ['source' => 'gravityforms-state-debug']);
            }

            return;
        }

        $invalid = $value_array['invalid'] ?? [];
        $validation = $value_array['validation'] ?? [];
        $logger->debug('Invalid array: ' . var_export($invalid, true), ['source' => 'gravityforms-state-debug']);
        $logger->debug('Validation array: ' . var_export($validation, true), ['source' => 'gravityforms-state-debug']);
        $logger->debug('Field isRequired: ' . var_export($this->isRequired, true), ['source' => 'gravityforms-state-debug']);

        // Check for validation errors, but only for fields that are actually required
        // Individual schemas can have their own required fields based on showAsRequired setting
        $has_validation_errors = false;

        // If the field itself is required, check for any validation errors
        if ($this->isRequired) {
            $has_validation_errors = !empty($invalid) || !empty($validation);
        } else {
            // If the field is not required, only fail validation for specific required schemas
            // Check if any of the invalid/validation errors are from schemas marked as showAsRequired
            $required_schemas_with_errors = false;

            // Get the schema settings for this field
            $ai_widget_schemas = $this->wwidget_ai_schemas ?? [[]];

            // Check invalid array for required schema errors
            if (!empty($invalid)) {
                foreach ($invalid as $schema_id => $errors) {
                    // Find if this schema is marked as required
                    foreach ($ai_widget_schemas as $schema_config) {
                        $schema_id_or_slug = $schema_config[0] ?? ''; // ID or slug
                        $show_as_required = $schema_config[3] ?? false; // showAsRequired setting

                        // If this schema is marked as required and has errors, fail validation
                        if (($schema_id_or_slug == $schema_id || $schema_id_or_slug == $schema_id) && $show_as_required) {
                            $required_schemas_with_errors = true;
                            break 2;
                        }
                    }
                }
            }

            // Check validation array for required schema errors
            if (!$required_schemas_with_errors && !empty($validation)) {
                foreach ($validation as $schema_id => $schema_validation) {
                    // Find if this schema is marked as required
                    foreach ($ai_widget_schemas as $schema_config) {
                        $schema_id_or_slug = $schema_config[0] ?? ''; // ID or slug
                        $show_as_required = $schema_config[3] ?? false; // showAsRequired setting

                        // If this schema is marked as required and has validation errors, fail validation
                        if (($schema_id_or_slug == $schema_id || $schema_id_or_slug == $schema_id) && $show_as_required) {
                            if (isset($schema_validation['errors']) && !empty($schema_validation['errors'])) {
                                $required_schemas_with_errors = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            $has_validation_errors = $required_schemas_with_errors;
        }

        if ($has_validation_errors) {
            $this->failed_validation = true;
            $error_message = $this->errorMessage ?: 'Please complete all required information in the form.';

            // If we have specific validation messages, include them
            if (!empty($validation)) {
                $specific_errors = [];
                foreach ($validation as $schema_validation) {
                    if (isset($schema_validation['errors'])) {
                        foreach ($schema_validation['errors'] as $error) {
                            $property = $error['property'] ?? '';
                            $message = $error['message'] ?? '';
                            if ($property && $message) {
                                $specific_errors[] = ucfirst(str_replace('.', ' ', trim($property, '.'))) . ' ' . $message;
                            }
                        }
                    }
                }
                if (!empty($specific_errors)) {
                    $error_message .= ' Issues: ' . implode(', ', array_unique($specific_errors));
                }
            }

            $this->validation_message = $error_message;
            $logger->debug('Setting failed_validation due to schema validation errors', ['source' => 'gravityforms-state-debug']);
        } else {
            $this->failed_validation = false;
            $this->validation_message = '';
            $logger->debug('Validation passed - clearing failed_validation', ['source' => 'gravityforms-state-debug']);
        }
    }

    /**
     * Enqueue validation scripts for MDP widgets
     */
    public static function enqueue_validation_scripts($form, $is_ajax) {
        // Check if this form contains an additional info widget
        $has_ai_widget = false;
        foreach ($form['fields'] as $field) {
            if ($field instanceof GFWicketFieldWidgetAdditionalInfo) {
                $has_ai_widget = true;
                break;
            }
        }

        if (!$has_ai_widget) {
            return;
        }

        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $version = defined('WICKET_WP_GF_VERSION') ? WICKET_WP_GF_VERSION : '1.0.0';

        // Enqueue the validation scripts
        wp_enqueue_script(
            'wicket-gf-automatic-widget-validation',
            $plugin_url . 'assets/js/wicket-gf-automatic-widget-validation.js',
            ['jquery'],
            $version,
            true
        );

        // Pass configuration to automatic validation script
        wp_localize_script(
            'wicket-gf-automatic-widget-validation',
            'WicketMDPAutoValidationConfig',
            [
                'enableLogging' => defined('WP_ENV') && in_array(WP_ENV, ['development', 'staging'], true),
                'enableAutoDetection' => true,
                'debugMode' => defined('WP_ENV') && WP_ENV === 'development'
            ]
        );
    }
}

// Initialize the widget field
GFWicketFieldWidgetAdditionalInfo::init();
