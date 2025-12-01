<?php

declare(strict_types=1);

class GFDataBindHiddenField extends GF_Field
{
    public string $type = 'wicket_data_hidden';

    public function get_form_editor_field_title(): string
    {
        return esc_attr__('JS Data Bind', 'wicket-gf');
    }

    public function get_form_editor_button(): array
    {
        return [
            'group' => 'wicket_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    public function is_conditional_logic_supported(): bool
    {
        return true;
    }

    public function get_input_type(): string
    {
        return 'text';
    }

    public function get_form_editor_field_settings(): array
    {
        return [
            'label_setting',
            'admin_label_setting',
            'description_setting',
            'css_class_setting',
            'conditional_logic_field_setting',
            'visibility_setting',
            'wicket_data_bind_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.liveUpdateEnabled = true;
                field.liveUpdateDataSource = '';
                field.liveUpdateOrganizationUuid = '';
                field.liveUpdateSchemaSlug = '';
                field.liveUpdateValueKey = '';
                field.liveUpdateDisplayMode = 'hidden';
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public static function custom_settings($position, $form_id): void
    {
        if ($position == 25) {
            ?>
            <?php ob_start(); ?>

            <li class="wicket_data_bind_setting field_setting" style="display:none;">
                <input type="checkbox" id="liveUpdateEnabled"
                    onchange="SetFieldProperty('liveUpdateEnabled', this.checked);" />
                <label for="liveUpdateEnabled" class="inline">
                    <?php esc_html_e('Enable JS Data Bind', 'wicket-gf'); ?>
                    <?php gform_tooltip('live_update_enable_setting'); ?>
                </label>

                <?php // Summary View (initially hidden, shown by JS)?>
                <div id="liveUpdateSummaryContainer" style="display:none; margin-top: 10px;">
                    <label class="section_label"><?php esc_html_e('Current JS Data Bind Configuration', 'wicket-gf'); ?></label>
                    <div id="liveUpdateSummaryDetails" style="padding-bottom: 10px;">
                        <p><strong><?php esc_html_e('Data Source:', 'wicket-gf'); ?></strong>
                            <span id="summaryDataSourceText"></span></p>
                        <p id="summaryOrgUuidContainer" style="display:none;">
                            <strong><?php esc_html_e('Organization UUID:', 'wicket-gf'); ?></strong>
                            <span id="summaryOrgUuidText"></span></p>
                        <p><strong><?php esc_html_e('Schema/Data Slug:', 'wicket-gf'); ?></strong>
                            <span id="summarySchemaSlugText"></span></p>
                        <p><strong><?php esc_html_e('Value Key:', 'wicket-gf'); ?></strong>
                            <span id="summaryValueKeyText"></span></p>
                    </div>
                    <button type="button" id="wicketResetLiveUpdateSettingsButton" class="button gf_input_button">
                        <?php esc_html_e('Change JS Data Bind Settings', 'wicket-gf'); ?>
                    </button>
                </div>

                <?php // Selector View (conditionally hidden/shown by JS)?>
                <div id="wicketLiveUpdateSelectorsWrapper" style="display:none; margin-top: 10px;">
                    <div style="margin-bottom: 10px;">
                        <label for="liveUpdateDataSource" class="section_label">
                            <?php esc_html_e('Bind: Data Source', 'wicket-gf'); ?>
                            <?php gform_tooltip('live_update_data_source_setting'); ?>
                        </label>
                        <select id="liveUpdateDataSource" onchange="SetFieldProperty('liveUpdateDataSource', this.value);">
                            <option value="">
                                <?php esc_html_e('Select Data Source', 'wicket-gf'); ?>
                            </option>
                            <option value="person_addinfo">
                                <?php esc_html_e('Person Add. Info. (Current User)', 'wicket-gf'); ?>
                            </option>
                            <option value="person_profile">
                                <?php esc_html_e('Person Profile (Current User)', 'wicket-gf'); ?>
                            </option>
                            <option value="organization">
                                <?php esc_html_e('Organization', 'wicket-gf'); ?>
                            </option>
                            <option value="organization_profile">
                                <?php esc_html_e('Organization Profile', 'wicket-gf'); ?>
                            </option>
                        </select>
                    </div>

                    <div id="liveUpdateOrgUuidWrapper" style="display:none; margin-bottom: 10px;">
                        <label for="liveUpdateOrganizationUuid" class="section_label">
                            <?php esc_html_e('Bind: Organization UUID', 'wicket-gf'); ?>
                            <?php gform_tooltip('live_update_organization_uuid_setting'); ?>
                        </label>
                        <input type="text" id="liveUpdateOrganizationUuid" class="fieldwidth-3"
                            onkeyup="SetFieldProperty('liveUpdateOrganizationUuid', this.value);" />
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label for="liveUpdateSchemaSlug" class="section_label">
                            <?php esc_html_e('Bind: Schema/Data Slug', 'wicket-gf'); ?>
                            <?php gform_tooltip('live_update_schema_slug_setting'); ?>
                        </label>
                        <select id="liveUpdateSchemaSlug" class="fieldwidth-3"
                            onchange="SetFieldProperty('liveUpdateSchemaSlug', this.value);">
                            <option value="">
                                <?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?>
                            </option>
                        </select>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label for="liveUpdateValueKey" class="section_label">
                            <?php esc_html_e('Bind: Value Key', 'wicket-gf'); ?>
                            <?php gform_tooltip('live_update_value_key_setting'); ?>
                        </label>
                        <select id="liveUpdateValueKey" class="fieldwidth-3"
                            onchange="SetFieldProperty('liveUpdateValueKey', this.value);">
                            <option value="">
                                <?php esc_html_e('Select Value Key', 'wicket-gf'); ?>
                            </option>
                        </select>
                    </div>
                    </div>
                </div>

                <div style="margin-bottom: 10px;">
                    <label for="liveUpdateDisplayMode" class="section_label">
                        <?php esc_html_e('Display Mode', 'wicket-gf'); ?>
                        <?php gform_tooltip('live_update_display_mode_setting'); ?>
                    </label>
                    <select id="liveUpdateDisplayMode" onchange="SetFieldProperty('liveUpdateDisplayMode', this.value);">
                        <option value="hidden"><?php esc_html_e('Hidden Field', 'wicket-gf'); ?></option>
                        <option value="readonly"><?php esc_html_e('Read-only Text Field', 'wicket-gf'); ?></option>
                        <option value="editable"><?php esc_html_e('Editable Text Field', 'wicket-gf'); ?></option>
                        <option value="static"><?php esc_html_e('Static Text (no form field)', 'wicket-gf'); ?></option>
                    </select>
                </div>
            </li>

            <?php echo ob_get_clean(); ?>

            <script type='text/javascript'>
            // Embed JavaScript directly in field settings to avoid gform_editor_js conflicts
            jQuery(document).ready(function($) {
                // Use the official Gravity Forms API to wait for field settings to load
                $(document).on('gform_load_field_settings', function(event, field) {
                    // Only initialize for our field type
                    if (field.type !== 'wicket_data_hidden') {
                        return;
                    }

                    // Initialize the Data Bind functionality
                    window.WicketGF = window.WicketGF || {};
                    window.WicketGF.DataBind = {

                        loadFieldSettings: function(field) {
                            // Set values for form elements
                            $('#liveUpdateEnabled').prop('checked', field.liveUpdateEnabled || false);
                            $('#liveUpdateDataSource').val(field.liveUpdateDataSource || '');
                            $('#liveUpdateOrganizationUuid').val(field.liveUpdateOrganizationUuid || '');
                            $('#liveUpdateDisplayMode').val(field.liveUpdateDisplayMode || 'hidden');

                            // Update the view based on current settings
                            this.refreshView(field);
                        },

                        refreshView: function(field) {
                            var $summaryContainer = $('#liveUpdateSummaryContainer');
                            var $selectorsWrapper = $('#wicketLiveUpdateSelectorsWrapper');
                            var $orgUuidWrapper = $('#liveUpdateOrgUuidWrapper');

                            if (field.liveUpdateEnabled) {
                                // Check if we have complete configuration
                                var isConfigured = field.liveUpdateDataSource && field.liveUpdateSchemaSlug && field.liveUpdateValueKey;

                                if (isConfigured) {
                                    // Show summary view
                                    this.updateSummaryView(field);
                                    $summaryContainer.show();
                                    $selectorsWrapper.hide();
                                } else {
                                    // Show selectors view
                                    $summaryContainer.hide();
                                    $selectorsWrapper.show();
                                    this.updateDataSourceDependentFields(field.liveUpdateDataSource || '');
                                }
                            } else {
                                // Data bind disabled
                                $summaryContainer.hide();
                                $selectorsWrapper.hide();
                            }
                        },

                        updateSummaryView: function(field) {
                            var dataSourceDisplay = field.liveUpdateDataSource;
                            if (field.liveUpdateDataSource === 'person_addinfo') {
                                dataSourceDisplay = 'Person Add. Info. (Current User)';
                            } else if (field.liveUpdateDataSource === 'person_profile') {
                                dataSourceDisplay = 'Person Profile (Current User)';
                            } else if (field.liveUpdateDataSource === 'organization') {
                                dataSourceDisplay = 'Organization';
                            } else if (field.liveUpdateDataSource === 'organization_profile') {
                                dataSourceDisplay = 'Organization Profile';
                            }

                            $('#summaryDataSourceText').text(dataSourceDisplay);
                            $('#summarySchemaSlugText').text(field.liveUpdateSchemaSlug);
                            $('#summaryValueKeyText').text(field.liveUpdateValueKey);

                            if (field.liveUpdateDataSource === 'organization' && field.liveUpdateOrganizationUuid) {
                                $('#summaryOrgUuidText').text(field.liveUpdateOrganizationUuid);
                                $('#summaryOrgUuidContainer').show();
                            } else {
                                $('#summaryOrgUuidContainer').hide();
                            }
                        },

                        updateDataSourceDependentFields: function(dataSource) {
                            var $orgUuidWrapper = $('#liveUpdateOrgUuidWrapper');
                            var $schemaSlugDropdown = $('#liveUpdateSchemaSlug');
                            var $valueKeyDropdown = $('#liveUpdateValueKey');

                            // Show/hide org UUID field
                            if (dataSource === 'organization' || dataSource === 'organization_profile') {
                                $orgUuidWrapper.show();

                                // Check if org UUID is valid
                                var orgUuid = $('#liveUpdateOrganizationUuid').val();
                                var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
                                var hasValidUuid = orgUuid && uuidRegex.test(orgUuid);

                                // Disable dropdowns if no valid UUID
                                $schemaSlugDropdown.prop('disabled', !hasValidUuid);
                                $valueKeyDropdown.prop('disabled', !hasValidUuid);

                                if (!hasValidUuid) {
                                    $schemaSlugDropdown.html('<option value="">Enter Organization UUID first</option>');
                                    $valueKeyDropdown.html('<option value="">Enter Organization UUID first</option>');
                                    return;
                                }
                            } else {
                                $orgUuidWrapper.hide();
                                // Enable dropdowns for non-org data sources
                                $schemaSlugDropdown.prop('disabled', false);
                                $valueKeyDropdown.prop('disabled', false);
                            }

                            // Reset dependent dropdowns
                            $schemaSlugDropdown.html('<option value="">Select Schema/Data Slug</option>');
                            $valueKeyDropdown.html('<option value="">Select Value Key</option>');

                            // Load schemas if we have enough info
                            if (dataSource) {
                                if (dataSource === 'organization' || dataSource === 'organization_profile') {
                                    var orgUuid = $('#liveUpdateOrganizationUuid').val();
                                    if (orgUuid && orgUuid.length > 30) {
                                        this.fetchSchemas(dataSource, orgUuid);
                                    }
                                } else {
                                    this.fetchSchemas(dataSource, null);
                                }
                            }
                        },

                        fetchSchemas: function(dataSource, orgUuid) {
                            var $schemaSlugDropdown = $('#liveUpdateSchemaSlug');
                            var $valueKeyDropdown = $('#liveUpdateValueKey');

                            $schemaSlugDropdown.html('<option value="">Loading...</option>');
                            $valueKeyDropdown.html('<option value="">Select Value Key</option>');

                            var self = this;

                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'gf_wicket_get_mdp_schemas',
                                    nonce: '<?php echo wp_create_nonce('gf_wicket_mdp_nonce'); ?>',
                                    data_source: dataSource,
                                    organization_uuid: orgUuid,
                                    form_id: 0
                                },
                                success: function(response) {
                                    $schemaSlugDropdown.html('<option value="">Select Schema/Data Slug</option>');

                                    if (response.success && response.data) {
                                        $.each(response.data, function(value, text) {
                                            $schemaSlugDropdown.append($('<option></option>').attr('value', value).text(text));
                                        });

                                        // Restore saved value if it exists
                                        var currentField = GetSelectedField();
                                        if (currentField.liveUpdateSchemaSlug &&
                                            $schemaSlugDropdown.find('option[value="' + currentField.liveUpdateSchemaSlug + '"]').length > 0) {
                                            $schemaSlugDropdown.val(currentField.liveUpdateSchemaSlug);
                                            self.fetchValueKeys(dataSource, currentField.liveUpdateSchemaSlug, orgUuid);
                                        }
                                    } else {
                                        var errorMessage = 'Error: ' + (response.data || 'Could not load schemas.');
                                        $schemaSlugDropdown.append($('<option></option>').attr('value', '').text(errorMessage));
                                    }
                                },
                                error: function() {
                                    $schemaSlugDropdown.html('<option value="">Error loading schemas</option>');
                                }
                            });
                        },

                        fetchValueKeys: function(dataSource, schemaSlug, orgUuid) {
                            var $valueKeyDropdown = $('#liveUpdateValueKey');

                            if (!schemaSlug) {
                                $valueKeyDropdown.html('<option value="">Select Value Key</option>');
                                return;
                            }

                            $valueKeyDropdown.html('<option value="">Loading...</option>');

                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'gf_wicket_get_mdp_value_keys',
                                    nonce: '<?php echo wp_create_nonce('gf_wicket_mdp_nonce'); ?>',
                                    data_source: dataSource,
                                    schema_data_slug: schemaSlug,
                                    organization_uuid: orgUuid,
                                    form_id: 0
                                },
                                success: function(response) {
                                    $valueKeyDropdown.html('<option value="">Select Value Key</option>');

                                    if (response.success && response.data) {
                                        $.each(response.data, function(value, text) {
                                            $valueKeyDropdown.append($('<option></option>').attr('value', value).text(text));
                                        });

                                        // Restore saved value if it exists
                                        var currentField = GetSelectedField();
                                        if (currentField.liveUpdateValueKey &&
                                            $valueKeyDropdown.find('option[value="' + currentField.liveUpdateValueKey + '"]').length > 0) {
                                            $valueKeyDropdown.val(currentField.liveUpdateValueKey);
                                        }
                                    } else {
                                        var errorMessage = 'Error: ' + (response.data || 'Could not load value keys.');
                                        $valueKeyDropdown.append($('<option></option>').attr('value', '').text(errorMessage));
                                    }
                                },
                                error: function() {
                                    $valueKeyDropdown.html('<option value="">Error loading value keys</option>');
                                }
                            });
                        },

                        resetSettings: function() {
                            SetFieldProperty('liveUpdateDataSource', '');
                            SetFieldProperty('liveUpdateOrganizationUuid', '');
                            SetFieldProperty('liveUpdateSchemaSlug', '');
                            SetFieldProperty('liveUpdateValueKey', '');
                            SetFieldProperty('liveUpdateDisplayMode', 'hidden');

                            $('#liveUpdateDataSource').val('');
                            $('#liveUpdateOrganizationUuid').val('');
                            $('#liveUpdateSchemaSlug').html('<option value="">Select Schema/Data Slug</option>');
                            $('#liveUpdateValueKey').html('<option value="">Select Value Key</option>');
                            $('#liveUpdateDisplayMode').val('hidden');

                            // Force show selectors view
                            $('#liveUpdateSummaryContainer').hide();
                            $('#wicketLiveUpdateSelectorsWrapper').show();
                            $('#liveUpdateOrgUuidWrapper').hide();
                        }
                    };

                    // Load field settings and set up event handlers
                    window.WicketGF.DataBind.loadFieldSettings(field);

                    // Set up event handlers
                    $('#liveUpdateEnabled').off('change.wicket-databind').on('change.wicket-databind', function() {
                        var currentField = GetSelectedField();
                        window.WicketGF.DataBind.refreshView(currentField);
                    });

                    $('#liveUpdateDataSource').off('change.wicket-databind').on('change.wicket-databind', function() {
                        window.WicketGF.DataBind.updateDataSourceDependentFields(this.value);
                    });

                    $('#liveUpdateOrganizationUuid').off('input.wicket-databind').on('input.wicket-databind', function() {
                        var dataSource = $('#liveUpdateDataSource').val();
                        if (dataSource === 'organization' || dataSource === 'organization_profile') {
                            var orgUuid = this.value;
                            var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
                            var $schemaSlugDropdown = $('#liveUpdateSchemaSlug');
                            var $valueKeyDropdown = $('#liveUpdateValueKey');

                            if (orgUuid && uuidRegex.test(orgUuid)) {
                                // Enable dropdowns and fetch schemas
                                $schemaSlugDropdown.prop('disabled', false);
                                $valueKeyDropdown.prop('disabled', false);
                                window.WicketGF.DataBind.fetchSchemas(dataSource, orgUuid);
                            } else {
                                // Disable dropdowns until valid UUID is entered
                                $schemaSlugDropdown.prop('disabled', true).html('<option value="">Enter Organization UUID first</option>');
                                $valueKeyDropdown.prop('disabled', true).html('<option value="">Enter Organization UUID first</option>');
                            }
                        }
                    });

                    $('#liveUpdateSchemaSlug').off('change.wicket-databind').on('change.wicket-databind', function() {
                        var dataSource = $('#liveUpdateDataSource').val();
                        var orgUuid = dataSource === 'organization' || dataSource === 'organization_profile' ? $('#liveUpdateOrganizationUuid').val() : null;
                        window.WicketGF.DataBind.fetchValueKeys(dataSource, this.value, orgUuid);
                    });

                    $('#wicketResetLiveUpdateSettingsButton').off('click.wicket-databind').on('click.wicket-databind', function(e) {
                        e.preventDefault();
                        window.WicketGF.DataBind.resetSettings();
                    });
                });
            });
            </script>

        <?php
        }
    }

    public static function editor_script(): void
    {
        // JavaScript is now embedded directly in the field settings output
        // to avoid conflicts with the gform_editor_js hook
    }

    public function get_conditional_logic_operators(): array
    {
        return [
            'is'          => esc_html__('is', 'gravityforms'),
            'isnot'       => esc_html__('is not', 'gravityforms'),
            'contains'    => esc_html__('contains', 'gravityforms'),
            'startsWith'  => esc_html__('starts with', 'gravityforms'),
            'endsWith'    => esc_html__('ends with', 'gravityforms'),
            'isempty'     => esc_html__('is empty', 'gravityforms'), // Added for completeness
            'isnotempty'  => esc_html__('is not empty', 'gravityforms'), // Added for completeness
        ];
    }

    public function get_field_input($form, $value = '', $entry = null): string
    {
        if ($this->is_form_editor()) {
            return '<p>' . esc_html__('JS Data Bind Field: Captures data from JavaScript widgets. Configure in field settings.', 'wicket-gf') . '</p>';
        }
        $id = (int) $this->id;
        $field_id = sprintf('input_%d_%d', $form['id'], $this->id);
        $input_value = esc_attr($value);
        $data_attributes = '';
        $class_attribute = '';

        if (!empty($this->liveUpdateEnabled)) {
            $class_attribute = " class='wicket-gf-hidden-data-bind-target'";
            $data_attributes .= ' data-hidden-data-bind-enabled="true"';

            if (!empty($this->liveUpdateDataSource)) { // Changed from liveUpdateWidgetSource
                $data_attributes .= ' data-hidden-data-bind-data-source="' . esc_attr($this->liveUpdateDataSource) . '"';
            }

            if (($this->liveUpdateDataSource === 'organization' || $this->liveUpdateDataSource === 'organization_profile') && !empty($this->liveUpdateOrganizationUuid)) {
                $data_attributes .= ' data-hidden-data-bind-organization-uuid="' . esc_attr($this->liveUpdateOrganizationUuid) . '"';
            }

            if (!empty($this->liveUpdateSchemaSlug)) { // Changed from liveUpdateSchemaKey
                $data_attributes .= ' data-hidden-data-bind-schema-slug="' . esc_attr($this->liveUpdateSchemaSlug) . '"';
            }

            if (isset($this->liveUpdateValueKey)) {
                $data_attributes .= ' data-hidden-data-bind-value-key="' . esc_attr($this->liveUpdateValueKey) . '"';
            }
        }

        $display_mode = $this->liveUpdateDisplayMode ?? 'hidden';
        $css_class = !empty($this->cssClass) ? esc_attr($this->cssClass) : '';

        // Add custom class if present
        if (!empty($css_class)) {
            $class_attribute = str_replace("class='", "class='" . $css_class . " ", $class_attribute);
        }

        switch ($display_mode) {
            case 'static':
                return sprintf(
                    "<div id='%s' %s %s>%s</div>",
                    $field_id,
                    $class_attribute,
                    $data_attributes,
                    !empty($input_value) ? esc_html($input_value) : '<em>' . esc_html__('No data available', 'wicket-gf') . '</em>'
                );

            case 'editable':
                return sprintf(
                    "<input name='input_%d' id='%s' type='text' %s value='%s' %s />",
                    $id,
                    $field_id,
                    $class_attribute,
                    $input_value,
                    $data_attributes
                );

            case 'readonly':
                return sprintf(
                    "<input name='input_%d' id='%s' type='text' readonly %s value='%s' %s />",
                    $id,
                    $field_id,
                    $class_attribute,
                    $input_value,
                    $data_attributes
                );

            case 'hidden':
            default:
                return sprintf(
                    "<input name='input_%d' id='%s' type='hidden'%s value='%s'%s />",
                    $id,
                    $field_id,
                    $class_attribute,
                    $input_value,
                    $data_attributes
                );
        }
    }

    private static function extract_included_items_from_response($api_response): ?array
    {
        $included_items_array = null;

        if (is_object($api_response)) {
            if (method_exists($api_response, 'included')) {
                try {
                    $included_data = $api_response->included();
                    if ($included_data instanceof Illuminate\Support\Collection) {
                        $included_items_array = $included_data->all();
                    } elseif (is_array($included_data)) {
                        $included_items_array = $included_data;
                    }
                } catch (Exception $e) {
                    // Ignore inclusion extraction failures and fallback to other strategies.
                }
            }

            if (is_null($included_items_array) && property_exists($api_response, 'included')) {
                $included_data_prop = $api_response->included;
                if ($included_data_prop instanceof Illuminate\Support\Collection) {
                    $included_items_array = $included_data_prop->all();
                } elseif (is_array($included_data_prop)) {
                    $included_items_array = $included_data_prop;
                }
            }

            if (is_null($included_items_array) && method_exists($api_response, 'toJsonAPI')) {
                try {
                    $response_as_array = $api_response->toJsonAPI();
                    if (isset($response_as_array['included'])) {
                        if ($response_as_array['included'] instanceof Illuminate\Support\Collection) {
                            $included_items_array = $response_as_array['included']->all();
                        } elseif (is_array($response_as_array['included'])) {
                            $included_items_array = $response_as_array['included'];
                        }
                    }
                } catch (Exception $e) {
                    // Ignore and fallback to other response shapes.
                }
            }
        } elseif (is_array($api_response) && isset($api_response['included'])) {
            $included_data_from_main_array = $api_response['included'];
            if ($included_data_from_main_array instanceof Illuminate\Support\Collection) {
                $included_items_array = $included_data_from_main_array->all();
            } elseif (is_array($included_data_from_main_array)) {
                $included_items_array = $included_data_from_main_array;
            }
        }

        return $included_items_array;
    }

    private static function build_schema_property_options(array $schema_attributes): array
    {
        $schema_definition = null;

        foreach (['schema', 'schema_raw'] as $schema_key) {
            if (isset($schema_attributes[$schema_key]) && is_array($schema_attributes[$schema_key])) {
                $schema_definition = $schema_attributes[$schema_key];
                break;
            }
        }

        if (!$schema_definition || !isset($schema_definition['properties']) || !is_array($schema_definition['properties'])) {
            return [];
        }

        $ui_schema = null;
        foreach (['ui_schema', 'ui_schema_raw'] as $ui_schema_key) {
            if (isset($schema_attributes[$ui_schema_key]) && is_array($schema_attributes[$ui_schema_key])) {
                $ui_schema = $schema_attributes[$ui_schema_key];
                break;
            }
        }

        $options = [];

        foreach ($schema_definition['properties'] as $property_key => $property_definition) {
            if (!is_array($property_definition)) {
                continue;
            }

            $label = self::derive_schema_property_label($property_key, $property_definition, $ui_schema);
            $options[$property_key] = $label;
        }

        return $options;
    }

    private static function derive_schema_property_label(string $property_key, array $property_definition, ?array $ui_schema): string
    {
        $label = null;

        if (is_array($ui_schema) && isset($ui_schema[$property_key]) && is_array($ui_schema[$property_key])) {
            $label = self::extract_label_from_ui_schema_section($ui_schema[$property_key]);
        }

        if (!$label && isset($property_definition['title']) && is_string($property_definition['title'])) {
            $label = $property_definition['title'];
        }

        if (!$label && isset($property_definition['description']) && is_string($property_definition['description'])) {
            $label = $property_definition['description'];
        }

        return $label ?: ucwords(str_replace(['_', '-'], ' ', $property_key));
    }

    private static function extract_label_from_ui_schema_section(array $ui_schema_section): ?string
    {
        if (isset($ui_schema_section['ui:i18n']['label'])) {
            $label_data = $ui_schema_section['ui:i18n']['label'];

            if (is_array($label_data)) {
                foreach (['en', 'fr'] as $locale) {
                    if (!empty($label_data[$locale]) && is_string($label_data[$locale])) {
                        return $label_data[$locale];
                    }
                }

                $first_value = reset($label_data);
                if (is_string($first_value)) {
                    return $first_value;
                }
            } elseif (is_string($label_data)) {
                return $label_data;
            }
        }

        if (isset($ui_schema_section['label']) && is_string($ui_schema_section['label'])) {
            return $ui_schema_section['label'];
        }

        if (isset($ui_schema_section['ui:title']) && is_string($ui_schema_section['ui:title'])) {
            return $ui_schema_section['ui:title'];
        }

        return null;
    }

    /**
     * AJAX handler to get MDP schemas for the selected data source.
     */
    public static function ajax_get_mdp_schemas()
    {
        check_ajax_referer('gf_wicket_mdp_nonce', 'nonce');

        $data_source = isset($_POST['data_source']) ? sanitize_text_field(wp_unslash($_POST['data_source'])) : null;
        $organization_uuid = isset($_POST['organization_uuid']) ? sanitize_text_field(wp_unslash($_POST['organization_uuid'])) : null;
        $options = [];

        try {
            if ($data_source === 'person_addinfo') {
                // Check if wicket helper function exists
                if (!function_exists('wicket_current_person_uuid')) {
                    $logger = wc_get_logger();
                    $logger->error('wicket_current_person_uuid function not found', ['source' => 'wicket-gf']);
                    wp_send_json_error('Wicket helper functions not available.');

                    return;
                }

                $person_uuid = wicket_current_person_uuid();

                if (empty($person_uuid)) {
                    wp_send_json_error('Could not retrieve current person UUID.');

                    return;
                }

                $person_data_response = wicket_get_person_by_id($person_uuid);
                if (!$person_data_response || is_wp_error($person_data_response)) {
                    wp_send_json_error('Failed to fetch person data.');

                    return;
                }

                $included_items_array = null;

                if (is_object($person_data_response)) {
                    // Attempt 1: Use the included() method
                    if (method_exists($person_data_response, 'included')) {
                        try {
                            $included_data = $person_data_response->included();
                            if ($included_data instanceof Illuminate\Support\Collection) {
                                $included_items_array = $included_data->all();
                            } elseif (is_array($included_data)) {
                                $included_items_array = $included_data;
                            }
                        } catch (Exception $e) {
                            // Error calling included()
                        }
                    }

                    // Attempt 2: Direct access to 'included' property (fallback)
                    if (is_null($included_items_array) && property_exists($person_data_response, 'included')) {
                        $included_data_prop = $person_data_response->included;
                        if ($included_data_prop instanceof Illuminate\Support\Collection) {
                            $included_items_array = $included_data_prop->all();
                        } elseif (is_array($included_data_prop)) {
                            $included_items_array = $included_data_prop;
                        }
                    }

                    // Attempt 3: Using toJsonAPI()['included'] (fallback)
                    if (is_null($included_items_array) && method_exists($person_data_response, 'toJsonAPI')) {
                        try {
                            $response_as_array = $person_data_response->toJsonAPI();
                            if (isset($response_as_array['included'])) {
                                if ($response_as_array['included'] instanceof Illuminate\Support\Collection) {
                                    $included_items_array = $response_as_array['included']->all();
                                } elseif (is_array($response_as_array['included'])) {
                                    $included_items_array = $response_as_array['included'];
                                }
                            }
                        } catch (Exception $e) {
                            // Error calling toJsonAPI()
                        }
                    }
                } elseif (is_array($person_data_response) && isset($person_data_response['included'])) {
                    $included_data_from_main_array = $person_data_response['included'];
                    if ($included_data_from_main_array instanceof Illuminate\Support\Collection) {
                        $included_items_array = $included_data_from_main_array->all();
                    } elseif (is_array($included_data_from_main_array)) {
                        $included_items_array = $included_data_from_main_array;
                    }
                }

                if (is_array($included_items_array)) {
                    foreach ($included_items_array as $item) {
                        $item_arr = is_object($item) ? (array) $item : $item;

                        if (isset($item_arr['type']) && $item_arr['type'] === 'json_schemas') {
                            $attributes = $item_arr['attributes'] ?? null;
                            if (is_array($attributes)) {
                                $identifier = $attributes['slug'] ?? $attributes['key'] ?? null;
                                if ($identifier) {
                                    $title = null;

                                    // PRIORITY 1: Try to extract title from ui_schema (most user-friendly)
                                    $ui_schema = $attributes['ui_schema'] ?? null;
                                    if (is_array($ui_schema)) {
                                        if (isset($ui_schema['ui:i18n']['title']['en'])) {
                                            $title = $ui_schema['ui:i18n']['title']['en'];
                                        } elseif (isset($ui_schema['title'])) {
                                            $title = $ui_schema['title'];
                                        } elseif (isset($ui_schema['ui:title'])) {
                                            $title = $ui_schema['ui:title'];
                                        }
                                    }

                                    // PRIORITY 2: Try to extract title from attributes directly
                                    if (!$title && isset($attributes['title'])) {
                                        $title = $attributes['title'];
                                    }

                                    // PRIORITY 3: Try to extract title from attributes.name
                                    if (!$title && isset($attributes['name'])) {
                                        $title = $attributes['name'];
                                    }

                                    // PRIORITY 4: Try label field
                                    if (!$title && isset($attributes['label'])) {
                                        $title = $attributes['label'];
                                    }

                                    // PRIORITY 5: Try to extract title from schema definition (fallback only)
                                    if (!$title) {
                                        $schema_def = $attributes['schema'] ?? null;
                                        if (is_array($schema_def)) {
                                            if (isset($schema_def['title']) && $schema_def['title'] !== $identifier) {
                                                // Only use schema title if it's different from the identifier
                                                $title = $schema_def['title'];
                                            } elseif (isset($schema_def['description'])) {
                                                $title = $schema_def['description'];
                                            }
                                        }
                                    }

                                    // Enhanced fallback: create better human-readable names from slugs
                                    if (!$title) {
                                        $title = ucwords(str_replace(['_', '-'], ' ', $identifier));
                                    }

                                    $options[$identifier] = $title;
                                }
                            }
                        }
                    }
                }
            } elseif ($data_source === 'person_profile') {
                // Check if wicket helper function exists
                if (!function_exists('wicket_current_person_uuid')) {
                    $logger = wc_get_logger();
                    $logger->error('wicket_current_person_uuid function not found for person_profile', ['source' => 'wicket-gf']);
                    wp_send_json_error('Wicket helper functions not available.');

                    return;
                }

                $person_uuid = wicket_current_person_uuid();
                if (empty($person_uuid)) {
                    wp_send_json_error('Could not retrieve current person UUID.');

                    return;
                }

                // Fetch person profile data with relationships
                $person_data_response = wicket_get_person_by_id($person_uuid, 'organizations,phones,emails,addresses,web_addresses');
                if (!$person_data_response || is_wp_error($person_data_response)) {
                    wp_send_json_error('Failed to fetch person profile data.');

                    return;
                }

                // Extract person attributes from API response
                $person_data = null;
                if (is_object($person_data_response)) {
                    if (method_exists($person_data_response, 'toJsonAPI')) {
                        try {
                            $api_response = $person_data_response->toJsonAPI();
                            $person_data = $api_response['data'] ?? null;
                        } catch (Exception $e) {
                            // Fallback to object properties
                        }
                    }

                    if (!$person_data && property_exists($person_data_response, 'attributes')) {
                        $person_data = ['attributes' => $person_data_response->attributes];
                    }
                } elseif (is_array($person_data_response) && isset($person_data_response['data'])) {
                    $person_data = $person_data_response['data'];
                }

                // Add main profile data slug - always available
                $options['profile_attributes'] = 'Profile Attributes';

                // Always expose common relationship collections so editors can bind
                // even if the current person has no records yet.
                $relationship_mappings = [
                    'organizations'  => 'Organizations',
                    'addresses'      => 'Addresses',
                    'emails'         => 'Emails',
                    'phones'         => 'Phones',
                    'web_addresses'  => 'Web Addresses',
                ];

                foreach ($relationship_mappings as $rel_key => $label) {
                    $options['profile_' . $rel_key] = $label;
                }

                // Check for primary address in included data and add primary address option
                $included_items_array = null;
                if (is_object($person_data_response)) {
                    // Attempt 1: Use the included() method
                    if (method_exists($person_data_response, 'included')) {
                        try {
                            $included_data = $person_data_response->included();
                            if ($included_data instanceof Illuminate\Support\Collection) {
                                $included_items_array = $included_data->all();
                            } elseif (is_array($included_data)) {
                                $included_items_array = $included_data;
                            }
                        } catch (Exception $e) {
                            // Error calling included()
                        }
                    }

                    // Attempt 2: Direct access to 'included' property (fallback)
                    if (is_null($included_items_array) && property_exists($person_data_response, 'included')) {
                        $included_data_prop = $person_data_response->included;
                        if ($included_data_prop instanceof Illuminate\Support\Collection) {
                            $included_items_array = $included_data_prop->all();
                        } elseif (is_array($included_data_prop)) {
                            $included_items_array = $included_data_prop;
                        }
                    }

                    // Attempt 3: Using toJsonAPI()['included'] (fallback)
                    if (is_null($included_items_array) && method_exists($person_data_response, 'toJsonAPI')) {
                        try {
                            $response_as_array = $person_data_response->toJsonAPI();
                            if (isset($response_as_array['included'])) {
                                if ($response_as_array['included'] instanceof Illuminate\Support\Collection) {
                                    $included_items_array = $response_as_array['included']->all();
                                } elseif (is_array($response_as_array['included'])) {
                                    $included_items_array = $response_as_array['included'];
                                }
                            }
                        } catch (Exception $e) {
                            // Error calling toJsonAPI()
                        }
                    }
                } elseif (is_array($person_data_response) && isset($person_data_response['included'])) {
                    $included_data_from_main_array = $person_data_response['included'];
                    if ($included_data_from_main_array instanceof Illuminate\Support\Collection) {
                        $included_items_array = $included_data_from_main_array->all();
                    } elseif (is_array($included_data_from_main_array)) {
                        $included_items_array = $included_data_from_main_array;
                    }
                }

                // Check if there's a primary address available
                if (is_array($included_items_array)) {
                    foreach ($included_items_array as $item) {
                        $item_arr = is_object($item) ? (array) $item : $item;

                        if (isset($item_arr['type']) && $item_arr['type'] === 'addresses') {
                            $address_attributes = $item_arr['attributes'] ?? null;
                            if (is_array($address_attributes)) {
                                $is_primary = isset($address_attributes['primary']) && $address_attributes['primary'] === true;
                                $is_active = isset($address_attributes['active']) && $address_attributes['active'] === true;

                                if ($is_primary && $is_active) {
                                    $options['profile_primary_address'] = 'Primary Address';
                                    break; // Found primary address, no need to continue
                                }
                            }
                        }
                    }
                }
            } elseif ($data_source === 'organization') {
                if (empty($organization_uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $organization_uuid)) {
                    wp_send_json_error('Invalid or missing Organization UUID.');

                    return;
                }

                $org_data_response = wicket_get_organization($organization_uuid, 'jsonSchemasAvailable');
                if (!$org_data_response || is_wp_error($org_data_response)) {
                    wp_send_json_error('Failed to fetch organization data.');

                    return;
                }

                $included_resources = $org_data_response['included'] ?? [];

                if (!empty($included_resources) && is_array($included_resources)) {
                    foreach ($included_resources as $included_item) {
                        if (!is_array($included_item)) {
                            continue;
                        }

                        if (isset($included_item['type']) && $included_item['type'] === 'json_schemas') {
                            $attributes = $included_item['attributes'] ?? null;
                            if (!is_array($attributes)) {
                                continue;
                            }

                            $schema_definition = $attributes['schema'] ?? null;
                            if (!is_array($schema_definition) || !isset($schema_definition['properties'])) {
                                continue;
                            }

                            $properties = $schema_definition['properties'];
                            $schema_identifier = $attributes['slug'] ?? $attributes['key'] ?? 'unknown_schema_' . ($included_item['id'] ?? uniqid());

                            if (is_array($properties)) {
                                foreach ($properties as $property_key => $property_definition) {
                                    if (!is_array($property_definition)) {
                                        continue;
                                    }
                                    $option_value = $schema_identifier . '.' . $property_key;
                                    $option_text = $property_definition['title'] ?? ucfirst(str_replace('_', ' ', (string) $property_key));
                                    $options[$option_value] = $option_text;
                                }
                            }
                        }
                    }
                }
            } elseif ($data_source === 'organization_profile') {
                // For organization profile schemas, avoid calling a hard-coded dummy UUID which may not exist on all tenants.
                // Use the provided organization UUID if supplied; otherwise fall back to sensible static options
                // and, when available, the global JSON schemas list.
                if (!empty($organization_uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $organization_uuid)) {
                    $org_data_response = wicket_get_organization($organization_uuid, 'addresses,web_addresses,emails,phones');
                } else {
                    $org_data_response = null;
                }

                // Add main profile data slug - always available
                $options['profile_attributes'] = 'Profile Attributes';

                // Always expose common organization relationship collections so editors
                // can bind address/contact/web data even if the selected org currently
                // has no items yet.
                $org_relationship_mappings = [
                    'addresses'      => 'Addresses',
                    'web_addresses'  => 'Web Addresses',
                    'emails'         => 'Emails',
                    'phones'         => 'Phone Numbers',
                ];

                foreach ($org_relationship_mappings as $rel_key => $label) {
                    $options['profile_' . $rel_key] = $label;
                }

                // If we did get included items, detect primary address and add direct option
                $included_items_array = $org_data_response['included'] ?? [];
                if (is_array($included_items_array)) {
                    foreach ($included_items_array as $item) {
                        $item_arr = is_object($item) ? (array) $item : $item;

                        if (isset($item_arr['type']) && $item_arr['type'] === 'addresses') {
                            $address_attributes = $item_arr['attributes'] ?? null;
                            if (is_array($address_attributes)) {
                                $is_primary = isset($address_attributes['primary']) && $address_attributes['primary'] === true;
                                $is_active = isset($address_attributes['active']) && $address_attributes['active'] === true;

                                if ($is_primary && $is_active) {
                                    $options['profile_primary_address'] = 'Primary Address';
                                    break; // Found primary address, no need to continue
                                }
                            }
                        }
                    }
                }
            } else {
                wp_send_json_error('Invalid data source specified.');

                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('API Error: ' . $e->getMessage());

            return;
        }

        wp_send_json_success($options);
    }

    /**
     * AJAX handler to get MDP value keys for the selected schema/data slug.
     */
    public static function ajax_get_mdp_value_keys()
    {
        check_ajax_referer('gf_wicket_mdp_nonce', 'nonce');

        $data_source = isset($_POST['data_source']) ? sanitize_text_field(wp_unslash($_POST['data_source'])) : null;
        $schema_data_slug = isset($_POST['schema_data_slug']) ? sanitize_text_field(wp_unslash($_POST['schema_data_slug'])) : null;
        $organization_uuid = isset($_POST['organization_uuid']) ? sanitize_text_field(wp_unslash($_POST['organization_uuid'])) : null;
        $options = [];

        if (empty($schema_data_slug)) {
            wp_send_json_error('Schema/Data Slug is required.');

            return;
        }

        try {
            if ($data_source === 'person_addinfo') {
                // Check if wicket helper function exists
                if (!function_exists('wicket_current_person_uuid')) {
                    $logger = wc_get_logger();
                    $logger->error('wicket_current_person_uuid function not found in value_keys', ['source' => 'wicket-gf']);
                    wp_send_json_error('Wicket helper functions not available.');

                    return;
                }

                $person_uuid = wicket_current_person_uuid();
                if (empty($person_uuid)) {
                    wp_send_json_error('Could not retrieve current person UUID.');

                    return;
                }

                $person_data_response = wicket_get_person_by_id($person_uuid);
                if (!$person_data_response || is_wp_error($person_data_response)) {
                    wp_send_json_error('Failed to fetch person data.');

                    return;
                }

                $included_items_array = self::extract_included_items_from_response($person_data_response);
                $person_attributes_arr = null;
                if (is_object($person_data_response)) {
                    // Attempt 1: Direct access to data_fields property
                    if (property_exists($person_data_response, 'data_fields')) {
                        $data_fields_prop = $person_data_response->data_fields;
                        if (is_array($data_fields_prop)) {
                            $person_attributes_arr = ['data_fields' => $data_fields_prop];
                        }
                    }

                    // Attempt 2: Access attributes property, then look for data_fields
                    if (is_null($person_attributes_arr) && property_exists($person_data_response, 'attributes')) {
                        $attrs = $person_data_response->attributes;
                        if (is_array($attrs) && isset($attrs['data_fields'])) {
                            $person_attributes_arr = $attrs;
                        } elseif (is_object($attrs) && method_exists($attrs, 'toArray')) {
                            $attrs_array = $attrs->toArray();
                            if (isset($attrs_array['data_fields'])) {
                                $person_attributes_arr = $attrs_array;
                            }
                        }
                    }

                    // Attempt 3: Using getAttribute('data_fields') method
                    if (is_null($person_attributes_arr) && method_exists($person_data_response, 'getAttribute')) {
                        try {
                            $data_fields_attr = $person_data_response->getAttribute('data_fields');
                            if (is_array($data_fields_attr)) {
                                $person_attributes_arr = ['data_fields' => $data_fields_attr];
                            }
                        } catch (Exception $e) {
                            // Error calling getAttribute()
                        }
                    }

                    // Attempt 4: Using toJsonAPI() and looking for attributes.data_fields
                    if (is_null($person_attributes_arr) && method_exists($person_data_response, 'toJsonAPI')) {
                        try {
                            $response_array = $person_data_response->toJsonAPI();
                            if (isset($response_array['attributes']['data_fields']) && is_array($response_array['attributes']['data_fields'])) {
                                $person_attributes_arr = $response_array['attributes'];
                            }
                        } catch (Exception $e) {
                            // Error calling toJsonAPI()
                        }
                    }
                } elseif (is_array($person_data_response) && isset($person_data_response['attributes']['data_fields'])) {
                    $person_attributes_arr = $person_data_response['attributes'];
                } elseif (is_array($person_data_response) && isset($person_data_response['data_fields'])) {
                    $person_attributes_arr = ['data_fields' => $person_data_response['data_fields']];
                }

                if (is_array($person_attributes_arr) && isset($person_attributes_arr['data_fields']) && is_array($person_attributes_arr['data_fields'])) {
                    $data_fields_array = $person_attributes_arr['data_fields'];

                    foreach ($data_fields_array as $schema_value_arr) {
                        $current_schema_value_arr = is_object($schema_value_arr) ? (array) $schema_value_arr : $schema_value_arr;
                        $identifier = $current_schema_value_arr['schema_slug'] ?? $current_schema_value_arr['key'] ?? null;

                        if ($identifier && $identifier === $schema_data_slug) {
                            if (isset($current_schema_value_arr['value']) && (is_object($current_schema_value_arr['value']) || is_array($current_schema_value_arr['value']))) {
                                $value_data = is_object($current_schema_value_arr['value']) ? (array) $current_schema_value_arr['value'] : $current_schema_value_arr['value'];
                                foreach (array_keys($value_data) as $key) {
                                    $options[$key] = ucfirst(str_replace('_', ' ', $key));
                                }
                            } elseif (isset($current_schema_value_arr['value'])) {
                                $options['_self'] = 'Value';
                            }
                            break;
                        }
                    }
                }

                if (empty($options) && is_array($included_items_array)) {
                    foreach ($included_items_array as $item) {
                        $item_arr = is_object($item) ? (array) $item : $item;

                        if (($item_arr['type'] ?? '') !== 'json_schemas') {
                            continue;
                        }

                        $attributes = $item_arr['attributes'] ?? null;
                        if (!is_array($attributes)) {
                            continue;
                        }

                        $current_schema_slug = $attributes['slug'] ?? $attributes['key'] ?? null;
                        if (!$current_schema_slug || $current_schema_slug !== $schema_data_slug) {
                            continue;
                        }

                        $schema_options = self::build_schema_property_options($attributes);

                        if (!empty($schema_options)) {
                            $options = $schema_options;
                            break;
                        }
                    }
                }
            } elseif ($data_source === 'person_profile') {
                // Handle different profile field types
                if ($schema_data_slug === 'profile_attributes') {
                    // Fetch person data to get available attributes dynamically
                    $person_uuid = wicket_current_person_uuid();
                    if (empty($person_uuid)) {
                        wp_send_json_error('Could not retrieve current person UUID.');

                        return;
                    }

                    $person_data_response = wicket_get_person_by_id($person_uuid, 'organizations,phones,emails,addresses,web_addresses');
                    if (!$person_data_response || is_wp_error($person_data_response)) {
                        wp_send_json_error('Failed to fetch person profile data.');

                        return;
                    }

                    // Extract person attributes from API response
                    $person_data = null;
                    if (is_object($person_data_response)) {
                        if (method_exists($person_data_response, 'toJsonAPI')) {
                            try {
                                $api_response = $person_data_response->toJsonAPI();
                                $person_data = $api_response['data'] ?? null;
                            } catch (Exception $e) {
                                // Fallback to object properties
                            }
                        }

                        if (!$person_data && property_exists($person_data_response, 'attributes')) {
                            $person_data = ['attributes' => $person_data_response->attributes];
                        }
                    } elseif (is_array($person_data_response) && isset($person_data_response['data'])) {
                        $person_data = $person_data_response['data'];
                    }

                    if ($person_data && isset($person_data['attributes'])) {
                        $attributes = $person_data['attributes'];

                        // Map actual API field names to user-friendly labels
                        $field_mappings = [
                            'honorific_prefix' => 'Salutation (Honorific Prefix)',
                            'alternate_name' => 'Alternate Name',
                            'maiden_name' => 'Maiden Name',
                            'given_name' => 'First Name (Given Name)',
                            'additional_name' => 'Middle Name (Additional Name)',
                            'family_name' => 'Last Name (Family Name)',
                            'suffix' => 'Suffix',
                            'honorific_suffix' => 'Post-nominal (Honorific Suffix)',
                            'nickname' => 'Nickname',
                            'preferred_pronoun' => 'Pronouns (Preferred Pronoun)',
                            'gender' => 'Gender',
                            'birth_date' => 'Birth Date',
                            'job_title' => 'Title (Job Title)',
                            'job_function' => 'Job Function',
                            'job_level' => 'Job Level',
                            'person_type' => 'Person Type',
                            'primary_email_address' => 'Primary Email Address',
                            'full_name' => 'Full Name',
                            'language' => 'Language',
                            'membership_number' => 'Membership Number',
                            'membership_began_on' => 'Membership Began On',
                            'status' => 'Status',
                            'identifying_number' => 'Identifying Number',
                        ];

                        // Add available profile fields based on what exists in the API
                        foreach ($field_mappings as $api_field => $label) {
                            if (array_key_exists($api_field, $attributes)) {
                                $options[$api_field] = $label;
                            }
                        }

                        // Add user fields if available
                        if (isset($attributes['user']) && is_array($attributes['user'])) {
                            $user_fields = [
                                'email' => 'User Email',
                                'username' => 'Username',
                                'confirmed_at' => 'Email Confirmed At',
                            ];

                            foreach ($user_fields as $user_field => $label) {
                                if (array_key_exists($user_field, $attributes['user'])) {
                                    $options['user_' . $user_field] = $label;
                                }
                            }
                        }
                    }
                } elseif ($schema_data_slug === 'profile_organizations') {
                    $options = [
                        'uuid' => 'Organization UUID',
                        'alternate_name' => 'Alternate Name',
                        'legal_name' => 'Legal Name',
                        'type' => 'Organization Type',
                        'status' => 'Status',
                        'description' => 'Description',
                        'slug' => 'Slug',
                        'people_count' => 'People Count',
                        'membership_began_on' => 'Membership Began On',
                        'identifying_number' => 'Identifying Number',
                        'is_primary_organization' => 'Is Primary Organization',
                    ];
                } elseif ($schema_data_slug === 'profile_addresses') {
                    $options = [
                        'uuid' => 'Address UUID',
                        'type' => 'Address Type',
                        'company_name' => 'Company Name',
                        'city' => 'City',
                        'zip_code' => 'Postal/Zip Code',
                        'address1' => 'Address Line 1',
                        'address2' => 'Address Line 2',
                        'state_name' => 'Province/State Name',
                        'country_code' => 'Country Code',
                        'country_name' => 'Country Name',
                        'formatted_address_label' => 'Formatted Address',
                        'latitude' => 'Latitude',
                        'longitude' => 'Longitude',
                        'primary' => 'Is Primary',
                        'mailing' => 'Is Mailing',
                        'department' => 'Department',
                        'division' => 'Division',
                    ];
                } elseif ($schema_data_slug === 'profile_emails') {
                    $options = [
                        'uuid' => 'Email UUID',
                        'localpart' => 'Local Part',
                        'domain' => 'Domain',
                        'type' => 'Email Type',
                        'address' => 'Email Address',
                        'primary' => 'Is Primary',
                        'consent' => 'Has Consent',
                        'consent_third_party' => 'Third Party Consent',
                        'consent_directory' => 'Directory Consent',
                        'unique' => 'Is Unique',
                    ];
                } elseif ($schema_data_slug === 'profile_phones') {
                    $options = [
                        'uuid' => 'Phone UUID',
                        'type' => 'Phone Type',
                        'number' => 'Phone Number',
                        'extension' => 'Extension',
                        'primary' => 'Is Primary',
                        'consent' => 'Has Consent',
                        'consent_third_party' => 'Third Party Consent',
                        'consent_directory' => 'Directory Consent',
                    ];
                } elseif ($schema_data_slug === 'profile_web_addresses') {
                    $options = [
                        'uuid' => 'Web Address UUID',
                        'type' => 'Web Address Type',
                        'uri' => 'URI/URL',
                        'primary' => 'Is Primary',
                        'consent' => 'Has Consent',
                        'consent_third_party' => 'Third Party Consent',
                        'consent_directory' => 'Directory Consent',
                    ];
                } elseif ($schema_data_slug === 'profile_primary_address') {
                    // Fetch person data to get included addresses
                    $person_uuid = wicket_current_person_uuid();
                    $person_data_response = null;
                    if (!empty($person_uuid)) {
                        $person_data_response = wicket_get_person_by_id($person_uuid, 'organizations,phones,emails,addresses,web_addresses');
                    }

                    $included_items_array = null;
                    if (is_array($person_data_response) && isset($person_data_response['included'])) {
                        $included_data_from_main_array = $person_data_response['included'];
                        if ($included_data_from_main_array instanceof Illuminate\Support\Collection) {
                            $included_items_array = $included_data_from_main_array->all();
                        } elseif (is_array($included_data_from_main_array)) {
                            $included_items_array = $included_data_from_main_array;
                        }
                    }

                    // Find primary address attributes
                    if (is_array($included_items_array)) {
                        foreach ($included_items_array as $item) {
                            $item_arr = is_object($item) ? (array) $item : $item;

                            if (isset($item_arr['type']) && $item_arr['type'] === 'addresses') {
                                $address_attributes = $item_arr['attributes'] ?? null;
                                if (is_array($address_attributes)) {
                                    $is_primary = isset($address_attributes['primary']) && $address_attributes['primary'] === true;
                                    $is_active = isset($address_attributes['active']) && $address_attributes['active'] === true;

                                    if ($is_primary && $is_active) {
                                        // Add individual address field options based on what exists
                                        $address_field_mappings = [
                                            'type' => 'Address Type',
                                            'company_name' => 'Company Name',
                                            'address1' => 'Address Line 1',
                                            'address2' => 'Address Line 2',
                                            'city' => 'City',
                                            'state_name' => 'State/Province',
                                            'zip_code' => 'Postal/Zip Code',
                                            'country_code' => 'Country Code',
                                            'country_name' => 'Country Name',
                                            'formatted_address_label' => 'Formatted Address',
                                            'latitude' => 'Latitude',
                                            'longitude' => 'Longitude',
                                            'department' => 'Department',
                                            'division' => 'Division',
                                        ];

                                        foreach ($address_field_mappings as $api_field => $label) {
                                            if (array_key_exists($api_field, $address_attributes)) {
                                                $options[$api_field] = $label;
                                            }
                                        }
                                        break; // Found primary address, no need to continue
                                    }
                                }
                            }
                        }
                    }

                    // If no primary address fields found, provide fallback
                    if (empty($options)) {
                        $options['_self'] = 'Primary Address (Not Available)';
                    }
                } else {
                    // For other profile fields, return the value itself
                    $options['_self'] = 'Value';
                }
            } elseif ($data_source === 'organization') {
                if (empty($organization_uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $organization_uuid)) {
                    wp_send_json_error('Invalid or missing Organization UUID.');

                    return;
                }

                $org_data_response = wicket_get_organization($organization_uuid, 'jsonSchemasAvailable');
                if (!$org_data_response || is_wp_error($org_data_response)) {
                    wp_send_json_error('Failed to fetch organization data.');

                    return;
                }

                $included_resources = $org_data_response['included'] ?? [];
                $found_schema = false;

                [$schema_key_filter, $property_key_filter] = array_pad(explode('.', $schema_data_slug, 2), 2, null);
                if (!$property_key_filter) {
                    wp_send_json_error('Invalid schema/data slug format for organization.');

                    return;
                }

                if (is_array($included_resources)) {
                    foreach ($included_resources as $included_item) {
                        if (!is_array($included_item)) {
                            continue;
                        }

                        if (isset($included_item['type']) && $included_item['type'] === 'json_schemas') {
                            $attributes = $included_item['attributes'] ?? null;
                            if (!is_array($attributes)) {
                                continue;
                            }

                            $current_schema_slug_from_attributes = $attributes['slug'] ?? $attributes['key'] ?? null;

                            if ($current_schema_slug_from_attributes && $current_schema_slug_from_attributes === $schema_key_filter) {
                                $schema_definition = $attributes['schema'] ?? null;
                                if (!is_array($schema_definition)) {
                                    continue;
                                }

                                $properties = $schema_definition['properties'] ?? null;
                                if (!is_array($properties) || !isset($properties[$property_key_filter])) {
                                    continue;
                                }

                                $target_property = $properties[$property_key_filter];
                                if (!is_array($target_property)) {
                                    continue;
                                }

                                $found_schema = true;
                                if (isset($target_property['enum']) && is_array($target_property['enum'])) {
                                    foreach ($target_property['enum'] as $enum_value) {
                                        $label = $target_property['enumNames'][array_search($enum_value, $target_property['enum'])] ?? ucfirst(str_replace('_', ' ', (string) $enum_value));
                                        $options[(string) $enum_value] = $label;
                                    }
                                } elseif (isset($target_property['items']['enum']) && is_array($target_property['items']['enum'])) {
                                    $item_enums = $target_property['items']['enum'];
                                    $item_enum_names = $target_property['items']['enumNames'] ?? [];
                                    foreach ($item_enums as $enum_value) {
                                        $label = $item_enum_names[array_search($enum_value, $item_enums)] ?? ucfirst(str_replace('_', ' ', (string) $enum_value));
                                        $options[(string) $enum_value] = $label;
                                    }
                                } elseif (isset($target_property['type']) && $target_property['type'] === 'object' && isset($target_property['properties']) && is_array($target_property['properties'])) {
                                    foreach ($target_property['properties'] as $sub_prop_key => $sub_prop_def) {
                                        if (!is_array($sub_prop_def)) {
                                            continue;
                                        }
                                        $options[$sub_prop_key] = $sub_prop_def['title'] ?? ucfirst(str_replace('_', ' ', $sub_prop_key));
                                    }
                                } else {
                                    $options['_self'] = 'Value';
                                }
                                break;
                            }
                        }
                        if ($found_schema) {
                            break;
                        }
                    }
                }
            } elseif ($data_source === 'organization_profile') {
                // Handle different profile field types
                if ($schema_data_slug === 'profile_attributes') {
                    // Try to use provided organization UUID if available; safe fallback to default mapping otherwise
                    $org_data_response = null;
                    if (!empty($organization_uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $organization_uuid)) {
                        $org_data_response = wicket_get_organization($organization_uuid);
                    }

                    // Extract organization attributes from API response if present
                    $org_data = null;
                    $attributes = [];
                    if ($org_data_response && is_array($org_data_response) && isset($org_data_response['data'])) {
                        $org_data = $org_data_response['data'];
                        if ($org_data && isset($org_data['attributes'])) {
                            $attributes = $org_data['attributes'];
                        }
                    }

                    // Map actual API field names to user-friendly labels
                    $field_mappings = [
                        'legal_name' => 'Legal Name',
                        'alternate_name' => 'Alternate Name',
                        'type' => 'Organization Type',
                        'status' => 'Status',
                        'description' => 'Description',
                        'slug' => 'Slug',
                        'people_count' => 'People Count',
                        'membership_began_on' => 'Membership Began On',
                        'identifying_number' => 'Identifying Number',
                    ];

                    // Add available profile fields based on what exists in the API
                    foreach ($field_mappings as $api_field => $label) {
                        if (array_key_exists($api_field, $attributes)) {
                            $options[$api_field] = $label;
                        }
                    }

                    // Add language-specific name fields if available
                    $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
                    $lang_specific_fields = [
                        'legal_name_' . $lang => 'Legal Name (' . strtoupper($lang) . ')',
                        'alternate_name_' . $lang => 'Alternate Name (' . strtoupper($lang) . ')',
                    ];

                    foreach ($lang_specific_fields as $api_field => $label) {
                        if (array_key_exists($api_field, $attributes)) {
                            $options[$api_field] = $label;
                        }
                    }
                } elseif ($schema_data_slug === 'profile_addresses') {
                    $options = [
                        'uuid' => 'Address UUID',
                        'type' => 'Address Type',
                        'company_name' => 'Company Name',
                        'city' => 'City',
                        'zip_code' => 'Postal/Zip Code',
                        'address1' => 'Address Line 1',
                        'address2' => 'Address Line 2',
                        'state_name' => 'Province/State Name',
                        'country_code' => 'Country Code',
                        'country_name' => 'Country Name',
                        'formatted_address_label' => 'Formatted Address',
                        'latitude' => 'Latitude',
                        'longitude' => 'Longitude',
                        'primary' => 'Is Primary',
                        'mailing' => 'Is Mailing',
                        'department' => 'Department',
                        'division' => 'Division',
                    ];
                } elseif ($schema_data_slug === 'profile_web_addresses') {
                    $options = [
                        'uuid' => 'Web Address UUID',
                        'type' => 'Web Address Type',
                        'uri' => 'URI/URL',
                        'primary' => 'Is Primary',
                        'consent' => 'Has Consent',
                        'consent_third_party' => 'Third Party Consent',
                        'consent_directory' => 'Directory Consent',
                    ];
                } elseif ($schema_data_slug === 'profile_emails') {
                    $options = [
                        'uuid' => 'Email UUID',
                        'localpart' => 'Local Part',
                        'domain' => 'Domain',
                        'type' => 'Email Type',
                        'address' => 'Email Address',
                        'primary' => 'Is Primary',
                        'consent' => 'Has Consent',
                        'consent_third_party' => 'Third Party Consent',
                        'consent_directory' => 'Directory Consent',
                        'unique' => 'Is Unique',
                    ];
                } elseif ($schema_data_slug === 'profile_phones') {
                    $options = [
                        'uuid' => 'Phone UUID',
                        'type' => 'Phone Type',
                        'number' => 'Phone Number',
                        'extension' => 'Extension',
                        'primary' => 'Is Primary',
                        'consent' => 'Has Consent',
                        'consent_third_party' => 'Third Party Consent',
                        'consent_directory' => 'Directory Consent',
                    ];
                } elseif ($schema_data_slug === 'profile_primary_address') {
                    $org_data_response = null;
                    if (!empty($organization_uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $organization_uuid)) {
                        $org_data_response = wicket_get_organization($organization_uuid, 'addresses');
                    }

                    // If we have included address items, find the primary one
                    $included_items_array = $org_data_response['included'] ?? [];
                    if (is_array($included_items_array)) {
                        foreach ($included_items_array as $item) {
                            $item_arr = is_object($item) ? (array) $item : $item;

                            if (isset($item_arr['type']) && $item_arr['type'] === 'addresses') {
                                $address_attributes = $item_arr['attributes'] ?? null;
                                if (is_array($address_attributes)) {
                                    $is_primary = isset($address_attributes['primary']) && $address_attributes['primary'] === true;
                                    $is_active = isset($address_attributes['active']) && $address_attributes['active'] === true;

                                    if ($is_primary && $is_active) {
                                        // Add individual address field options based on what exists
                                        $address_field_mappings = [
                                            'type' => 'Address Type',
                                            'company_name' => 'Company Name',
                                            'address1' => 'Address Line 1',
                                            'address2' => 'Address Line 2',
                                            'city' => 'City',
                                            'state_name' => 'State/Province',
                                            'zip_code' => 'Postal/Zip Code',
                                            'country_code' => 'Country Code',
                                            'country_name' => 'Country Name',
                                            'formatted_address_label' => 'Formatted Address',
                                            'latitude' => 'Latitude',
                                            'longitude' => 'Longitude',
                                            'department' => 'Department',
                                            'division' => 'Division',
                                        ];

                                        foreach ($address_field_mappings as $api_field => $label) {
                                            if (array_key_exists($api_field, $address_attributes)) {
                                                $options[$api_field] = $label;
                                            }
                                        }
                                        break; // Found primary address, no need to continue
                                    }
                                }
                            }
                        }
                    }

                    // If no primary address fields found, provide fallback
                    if (empty($options)) {
                        $options['_self'] = 'Primary Address (Not Available)';
                    }
                } else {
                    // For other profile fields, return the value itself
                    $options['_self'] = 'Value';
                }
            } else {
                wp_send_json_error('Invalid data source specified.');

                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('API Error: ' . $e->getMessage());

            return;
        }

        if (empty($options)) {
            wp_send_json_success(['_self' => 'Value (no sub-keys)']);

            return;
        }

        wp_send_json_success($options);
    }
}

// Register AJAX handlers
add_action('wp_ajax_gf_wicket_get_mdp_schemas', ['GFDataBindHiddenField', 'ajax_get_mdp_schemas']);
add_action('wp_ajax_gf_wicket_get_mdp_value_keys', ['GFDataBindHiddenField', 'ajax_get_mdp_value_keys']);
