<?php

declare(strict_types=1);

class GFApiDataBindField extends GF_Field
{
    public string $type = 'wicket_api_data_bind';

    public function get_form_editor_field_title(): string
    {
        return esc_attr__('API Data Bind', 'wicket-gf');
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
            'rules_setting',
            'wicket_api_data_source_setting',
            'wicket_api_organization_uuid_setting',
            'wicket_api_field_mapping_setting',
            'wicket_api_display_mode_setting',
            'wicket_api_fallback_value_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.apiDataSource = '';
                field.apiFieldPath = '';
                field.apiDisplayMode = 'hidden';
                field.apiFallbackValue = '';
                field.apiOrganizationUuid = '';
                field.isRequired = false;
                field.visibility = 'visible';
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public function get_field_input($form, $value = '', $entry = null): string
    {
        if ($this->is_form_editor()) {
            return '<p>' . esc_html__('API Data Bind Field: Fetches data directly from Wicket API. Configure in field settings.', 'wicket-gf') . '</p>';
        }

        $id = (int) $this->id;
        $field_id = sprintf('input_%d_%d', $form['id'], $this->id);

        // Get fetched value from API if configured
        if (empty($value) && !empty($this->apiDataSource) && !empty($this->apiFieldPath)) {
            $value = $this->fetch_value_from_api();
        }

        $input_value = esc_attr($value);
        $placeholder = !empty($this->apiFallbackValue) ? esc_attr($this->apiFallbackValue) : '';

        return $this->get_field_input_by_display_mode($id, $field_id, $input_value, $placeholder);
    }

    /**
     * Render field input based on display mode.
     */
    private function get_field_input_by_display_mode($id, $field_id, $value, $placeholder): string
    {
        $display_mode = $this->apiDisplayMode ?? 'hidden';
        $css_class = !empty($this->cssClass) ? esc_attr($this->cssClass) : '';

        switch ($display_mode) {
            case 'hidden':
                return sprintf(
                    "<input name='input_%d' id='%s' type='hidden' value='%s' class='%s' />",
                    $id,
                    $field_id,
                    $value,
                    esc_attr($css_class)
                );

            case 'static':
                return sprintf(
                    "<div class='wicket-api-data-bind-static %s'>%s</div>",
                    esc_attr($css_class),
                    !empty($value) ? esc_html($value) : '<em>' . esc_html__('No data available', 'wicket-gf') . '</em>'
                );

            case 'editable':
                return sprintf(
                    "<input name='input_%d' id='%s' type='text' class='large textfield %s' value='%s' placeholder='%s' />",
                    $id,
                    $field_id,
                    esc_attr($css_class),
                    $value,
                    $placeholder
                );

            case 'readonly':
            default:
                return sprintf(
                    "<input name='input_%d' id='%s' type='text' class='large textfield %s' value='%s' placeholder='%s' readonly />",
                    $id,
                    $field_id,
                    esc_attr($css_class),
                    $value,
                    $placeholder
                );
        }
    }

    /**
     * Fetch value from Wicket API based on field configuration.
     */
    private function fetch_value_from_api(): string
    {
        if (empty($this->apiDataSource) || empty($this->apiFieldPath)) {
            return $this->get_fallback_value();
        }

        try {
            $data_source = $this->apiDataSource;
            $field_path = $this->apiFieldPath;
            $org_uuid = !empty($this->apiOrganizationUuid) ? $this->apiOrganizationUuid : null;

            // Validate configuration
            if ($data_source === 'organization' && empty($org_uuid)) {
                return $this->get_fallback_value();
            }

            // Validate UUID format for organization
            if ($data_source === 'organization' && !$this->is_valid_uuid($org_uuid)) {
                return $this->get_fallback_value();
            }

            switch ($data_source) {
                case 'person_profile':
                    return $this->fetch_person_profile_data($field_path);
                case 'organization':
                    return $this->fetch_organization_data($org_uuid, $field_path);
                default:
                    return $this->get_fallback_value();
            }
        } catch (Exception $e) {
            return $this->get_fallback_value();
        }
    }

    /**
     * Get fallback value.
     */
    private function get_fallback_value(): string
    {
        return !empty($this->apiFallbackValue) ? (string) $this->apiFallbackValue : '';
    }

    /**
     * Validate UUID format.
     */
    private function is_valid_uuid($uuid): bool
    {
        if (empty($uuid)) {
            return false;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Check if Wicket API client is available.
     */
    private function is_wicket_api_available(): bool
    {
        return function_exists('wicket_current_person_uuid') &&
               function_exists('wicket_get_person_by_id') &&
               function_exists('wicket_get_organization');
    }

    /**
     * Fetch person profile data (includes all related data).
     */
    private function fetch_person_profile_data($field_path): string
    {
        if (!$this->is_wicket_api_available()) {
            return $this->get_fallback_value();
        }

        $person_uuid = wicket_current_person_uuid();
        if (empty($person_uuid)) {
            return $this->get_fallback_value();
        }

        // Cache disabled for debugging - always fetch fresh data
        // $cache_key = "wicket_gf_person_data_{$person_uuid}_" . md5($field_path);
        // $cached_value = get_transient($cache_key);
        //
        // if ($cached_value !== false) {
        //     return $cached_value;
        // }

        $person_data = wicket_get_person_by_id($person_uuid, 'organizations,phones,emails,addresses,web_addresses');
        if (!$person_data || is_wp_error($person_data)) {
            return $this->get_fallback_value();
        }

        // Convert to array for consistent processing
        if (function_exists('wicket_convert_obj_to_array') && is_object($person_data)) {
            $person_data = wicket_convert_obj_to_array($person_data);
        }

        $value = $this->extract_field_value($person_data, $field_path);

        // Cache disabled for debugging - skip caching
        // set_transient($cache_key, $value, 300);

        return $value;
    }

    /**
     * Fetch organization data.
     */
    private function fetch_organization_data($org_uuid, $field_path): string
    {
        if (!$this->is_wicket_api_available()) {
            return $this->get_fallback_value();
        }

        if (empty($org_uuid)) {
            return $this->get_fallback_value();
        }

        // Cache disabled - always fetch fresh data
        $cache_key = "wicket_gf_org_data_{$org_uuid}_" . md5($field_path);
        $cached_value = false; // get_transient($cache_key);

        if ($cached_value !== false) {
            return $cached_value;
        }

        $org_data = wicket_get_organization($org_uuid);
        if (!$org_data || is_wp_error($org_data)) {
            return $this->get_fallback_value();
        }

        $value = $this->extract_field_value($org_data, $field_path);

        // Cache disabled for debugging - skip caching
        // set_transient($cache_key, $value, 600);

        return $value;
    }

    /**
     * Extract specific field value using dot notation.
     */
    private function extract_field_value($data, $field_path): string
    {
        if (empty($field_path) || empty($data)) {
            return '';
        }

        $path_parts = explode('.', $field_path);
        $current_data = $data;

        // Handle JSON:API structure - if data has 'data' wrapper, start with that
        if (is_array($current_data) && isset($current_data['data']) && is_array($current_data['data'])) {
            $current_data = $current_data['data'];
        }

        foreach ($path_parts as $part) {
            // Handle array indices (e.g., organizations.0.legal_name)
            if (is_numeric($part)) {
                if (is_array($current_data) && isset($current_data[(int) $part])) {
                    $current_data = $current_data[(int) $part];
                    continue;
                } else {
                    return '';
                }
            }

            // Convert object to array if needed
            if (is_object($current_data)) {
                if (method_exists($current_data, 'toJsonAPI')) {
                    try {
                        $api_response = $current_data->toJsonAPI();
                        $current_data = $api_response['data'] ?? $api_response;
                    } catch (Exception $e) {
                        $current_data = (array) $current_data;
                    }
                } else {
                    $current_data = (array) $current_data;
                }
            }

            if (is_array($current_data)) {
                // Try direct access first
                if (isset($current_data[$part])) {
                    $current_data = $current_data[$part];
                    continue;
                }

                // Try attributes level
                if (isset($current_data['attributes']) && is_array($current_data['attributes']) && isset($current_data['attributes'][$part])) {
                    $current_data = $current_data['attributes'][$part];
                    continue;
                }

                // Try relationships level (for included data)
                if (isset($current_data['relationships']) && is_array($current_data['relationships']) && isset($current_data['relationships'][$part])) {
                    // Handle relationship data - extract from included if available
                    $current_data = $this->extract_relationship_data($data, $part);
                    continue;
                }

                // Special handling for data_fields (additional info)
                if (isset($current_data['data_fields']) && is_array($current_data['data_fields'])) {
                    $data_fields_value = $this->extract_data_field_value($current_data['data_fields'], $field_path, $part);
                    if ($data_fields_value !== null) {
                        return $data_fields_value;
                    }
                }

                // Special handling for common profile fields
                $special_mappings = $this->get_special_field_mappings();
                if (isset($special_mappings[$part])) {
                    return $this->extract_special_field($current_data, $special_mappings[$part]);
                }

                return '';
            } else {
                return '';
            }
        }

        return $this->format_output_value($current_data);
    }

    /**
     * Extract relationship data from included resources.
     */
    private function extract_relationship_data($data, $relationship_name): string
    {
        if (!is_array($data) || !isset($data['included'])) {
            return '';
        }

        $relationship_data = $data['relationships'][$relationship_name]['data'] ?? null;
        if (!$relationship_data) {
            return '';
        }

        // Handle single relationship
        if (isset($relationship_data['id'])) {
            return $this->find_included_resource($data['included'], $relationship_data['type'], $relationship_data['id']);
        }

        // Handle multiple relationships (array)
        if (is_array($relationship_data) && isset($relationship_data[0])) {
            return $this->find_included_resource($data['included'], $relationship_data[0]['type'], $relationship_data[0]['id']);
        }

        return '';
    }

    /**
     * Find specific resource in included data.
     */
    private function find_included_resource($included, $type, $id): string
    {
        foreach ($included as $resource) {
            if ($resource['type'] === $type && $resource['id'] === $id) {
                return isset($resource['attributes']) ? json_encode($resource['attributes']) : '';
            }
        }

        return '';
    }

    /**
     * Extract value from data_fields (additional info).
     */
    private function extract_data_field_value($data_fields, $full_field_path, $current_part): ?string
    {
        $path_parts = explode('.', $full_field_path);
        $current_index = array_search($current_part, $path_parts);

        if ($current_index === false || $current_index >= count($path_parts) - 1) {
            return null;
        }

        $schema_slug = $path_parts[$current_index + 1] ?? null;
        $field_key = $path_parts[$current_index + 2] ?? null;

        if (!$schema_slug || !$field_key) {
            return null;
        }

        foreach ($data_fields as $field_data) {
            if (!is_array($field_data) || !isset($field_data['schema_slug'])) {
                continue;
            }

            if ($field_data['schema_slug'] === $schema_slug) {
                $value = $field_data['value'] ?? null;
                if (is_array($value) && isset($value[$field_key])) {
                    return (string) $value[$field_key];
                } elseif ($field_key === '_self' && !is_array($value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    /**
     * Get special field mappings for common profile fields.
     */
    private function get_special_field_mappings(): array
    {
        return [
            'full_name' => ['given_name', 'family_name'],
            'primary_email' => ['emails', 0, 'address'],
            'primary_phone' => ['phones', 0, 'number'],
            'primary_address' => ['addresses', 0],
            'primary_organization' => ['organizations', 0, 'legal_name'],
        ];
    }

    /**
     * Extract special field based on mapping.
     */
    private function extract_special_field($data, $mapping): string
    {
        $current = $data;
        foreach ($mapping as $key) {
            if (is_numeric($key)) {
                $key = (int) $key;
            }

            if (isset($current[$key])) {
                $current = $current[$key];
            } elseif (isset($current['attributes']) && isset($current['attributes'][$key])) {
                $current = $current['attributes'][$key];
            } else {
                return '';
            }
        }

        return (string) $current;
    }

    /**
     * Format output value.
     */
    private function format_output_value($value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Add custom field settings.
     */
    public static function custom_settings($position, $form_id): void
    {
        if ($position == 25) {
            ?>
            <li class="wicket_api_data_source_setting field_setting" style="display:none;">
                <label for="apiDataSource" class="section_label">
                    <?php esc_html_e('API Data Source', 'wicket-gf'); ?>
                    <?php gform_tooltip('api_data_source_setting'); ?>
                </label>
                <select id="apiDataSource" onchange="SetFieldProperty('apiDataSource', this.value);">
                    <option value="">
                        <?php esc_html_e('Select Data Source', 'wicket-gf'); ?>
                    </option>
                    <option value="person_profile">
                        <?php esc_html_e('Person Profile (Current User)', 'wicket-gf'); ?>
                    </option>
                    <option value="organization">
                        <?php esc_html_e('Organization', 'wicket-gf'); ?>
                    </option>
                </select>
            </li>
            <li class="wicket_api_organization_uuid_setting field_setting" style="display:none;">
                <label for="apiOrganizationUuid" class="section_label">
                    <?php esc_html_e('Organization UUID', 'wicket-gf'); ?>
                    <?php gform_tooltip('api_organization_uuid_setting'); ?>
                </label>
                <input type="text" id="apiOrganizationUuid" class="fieldwidth-3"
                    placeholder="Required when using Organization data source"
                    onkeyup="SetFieldProperty('apiOrganizationUuid', this.value);" />
                <p class="instruction">
                    <?php esc_html_e('Enter the UUID of the organization to fetch data from.', 'wicket-gf'); ?>
                </p>
            </li>

            <li class="wicket_api_field_mapping_setting field_setting" style="display:none;">
                <label for="apiFieldPath" class="section_label">
                    <?php esc_html_e('Field Path (dot notation)', 'wicket-gf'); ?>
                    <?php gform_tooltip('api_field_path_setting'); ?>
                </label>

                <!-- Field Path Selection Container -->
                <div id="fieldPathSelectionContainer">
                    <div id="fieldPathDropdownContainer" style="display:none;">
                        <select id="apiFieldPathDropdown" class="fieldwidth-3">
                            <option value=""><?php esc_html_e('Select a field...', 'wicket-gf'); ?></option>
                        </select>
                        <button type="button" id="useCustomFieldPathBtn" style="margin-top: 5px;" class="button">
                            <?php esc_html_e('Use Custom Field Path', 'wicket-gf'); ?>
                        </button>
                    </div>

                    <div id="fieldPathTextContainer">
                        <input type="text" id="apiFieldPath" class="fieldwidth-3"
                               placeholder="e.g., attributes.given_name"
                               onkeyup="SetFieldProperty('apiFieldPath', this.value);" />
                        <button type="button" id="useFieldDropdownBtn" style="margin-top: 5px;" class="button">
                            <?php esc_html_e('Browse Available Fields', 'wicket-gf'); ?>
                        </button>
                    </div>
                </div>

                <p class="instruction" id="fieldPathInstruction">
                    <?php esc_html_e('Use dot notation to access nested data. Examples: attributes.given_name, organizations.0.legal_name, data_fields.custom_schema.field_name', 'wicket-gf'); ?>
                </p>

                <!-- Common field path examples -->
                <div class="wicket-field-examples" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                    <strong><?php esc_html_e('Common Field Paths:', 'wicket-gf'); ?></strong>
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <li><code>attributes.given_name</code> - <?php esc_html_e('First Name', 'wicket-gf'); ?></li>
                        <li><code>attributes.family_name</code> - <?php esc_html_e('Last Name', 'wicket-gf'); ?></li>
                        <li><code>attributes.email</code> - <?php esc_html_e('Email Address', 'wicket-gf'); ?></li>
                        <li><code>organizations.0.legal_name</code> - <?php esc_html_e('Primary Organization', 'wicket-gf'); ?></li>
                        <li><code>addresses.0.city</code> - <?php esc_html_e('Primary Address City', 'wicket-gf'); ?></li>
                        <li><code>full_name</code> - <?php esc_html_e('Full Name (auto-combined)', 'wicket-gf'); ?></li>
                    </ul>
                </div>
            </li>

            <li class="wicket_api_display_mode_setting field_setting" style="display:none;">
                <label for="apiDisplayMode" class="section_label">
                    <?php esc_html_e('Display Mode', 'wicket-gf'); ?>
                    <?php gform_tooltip('api_display_mode_setting'); ?>
                </label>
                <select id="apiDisplayMode" onchange="SetFieldProperty('apiDisplayMode', this.value);">
                    <option value="readonly"><?php esc_html_e('Read-only Text Field', 'wicket-gf'); ?></option>
                    <option value="editable"><?php esc_html_e('Editable Text Field', 'wicket-gf'); ?></option>
                    <option value="hidden"><?php esc_html_e('Hidden Field', 'wicket-gf'); ?></option>
                    <option value="static"><?php esc_html_e('Static Text (no form field)', 'wicket-gf'); ?></option>
                </select>
                <p class="instruction">
                    <?php esc_html_e('Choose how the fetched data should be displayed to users.', 'wicket-gf'); ?>
                </p>
            </li>

            <li class="wicket_api_fallback_value_setting field_setting" style="display:none;">
                <label for="apiFallbackValue" class="section_label">
                    <?php esc_html_e('Fallback Value', 'wicket-gf'); ?>
                    <?php gform_tooltip('api_fallback_value_setting'); ?>
                </label>
                <input type="text" id="apiFallbackValue" class="fieldwidth-3"
                    placeholder="Value to use if API call fails"
                    onkeyup="SetFieldProperty('apiFallbackValue', this.value);" />
                <p class="instruction">
                    <?php esc_html_e('This value will be used if the API call fails or no data is found.', 'wicket-gf'); ?>
                </p>
            </li>

            <li class="wicket_api_validation_setting field_setting" style="display:none;">
                <div class="wicket-api-status" style="padding: 10px; margin-top: 5px; border-radius: 4px; display: none;">
                    <span class="status-message"></span>
                </div>
            </li>

            <script type='text/javascript'>
            jQuery(document).ready(function($) {
                $(document).on('gform_load_field_settings', function(event, field) {
                    if (field.type !== 'wicket_api_data_bind') {
                        return;
                    }

                    // Load field settings
                    $('#apiDataSource').val(field.apiDataSource || '');
                    $('#apiFieldPath').val(field.apiFieldPath || '');
                    $('#apiOrganizationUuid').val(field.apiOrganizationUuid || '');
                    $('#apiDisplayMode').val(field.apiDisplayMode || 'readonly');
                    $('#apiFallbackValue').val(field.apiFallbackValue || '');

                    // Show/hide organization UUID field based on data source
                    toggleOrganizationUuidField(field.apiDataSource);

                    // Show field examples for data source
                    updateFieldExamples(field.apiDataSource);

                    // Validate configuration
                    validateFieldConfiguration(field);
                });

                // Handle data source change
                $('#apiDataSource').off('change.api-data-bind').on('change.api-data-bind', function() {
                    toggleOrganizationUuidField(this.value);
                    updateFieldExamples(this.value);
                    validateCurrentConfiguration();
                });

                // Handle field path change
                $('#apiFieldPath').off('input.api-data-bind').on('input.api-data-bind', function() {
                    validateCurrentConfiguration();
                });

                // Handle field path dropdown change
                $('#apiFieldPathDropdown').off('change.api-data-bind').on('change.api-data-bind', function() {
                    updateFieldPathFromDropdown(this.value);
                });

                // Handle Browse Available Fields button click
                $('#useFieldDropdownBtn').off('click.api-data-bind').on('click.api-data-bind', function() {
                    var dataSource = $('#apiDataSource').val();
                    if (dataSource) {
                        loadAvailableFields(dataSource);
                    }
                });

                // Handle Use Custom Field Path button click
                $('#useCustomFieldPathBtn').off('click.api-data-bind').on('click.api-data-bind', function() {
                    showCustomFieldPath();
                });

                // Load fields when data source changes
                $('#apiDataSource').off('change.api-field-load').on('change.api-field-load', function() {
                    var dataSource = this.value;
                    updateBrowseButtonState(dataSource);
                    loadAvailableFields(dataSource);
                });

                // Handle organization UUID change
                $('#apiOrganizationUuid').off('input.api-data-bind').on('input.api-data-bind', function() {
                    var dataSource = $('#apiDataSource').val();
                    updateBrowseButtonState(dataSource);
                    validateCurrentConfiguration();
                });

                // Initial button state update
                updateBrowseButtonState(field.apiDataSource);

                function toggleOrganizationUuidField(dataSource) {
                    var $orgUuidSetting = $('.wicket_api_organization_uuid_setting');
                    if (dataSource === 'organization') {
                        $orgUuidSetting.show();
                    } else {
                        $orgUuidSetting.hide();
                    }
                }

                function updateFieldExamples(dataSource) {
                    var examples = '';

                    switch(dataSource) {
                        case 'person_profile':
                            examples = '<strong><?php esc_html_e('Profile Field Examples:', 'wicket-gf'); ?></strong><br>' +
                                '<code>attributes.given_name</code> - <?php esc_html_e('First Name', 'wicket-gf'); ?><br>' +
                                '<code>attributes.family_name</code> - <?php esc_html_e('Last Name', 'wicket-gf'); ?><br>' +
                                '<code>full_name</code> - <?php esc_html_e('Full Name', 'wicket-gf'); ?><br>' +
                                '<code>primary_email</code> - <?php esc_html_e('Primary Email', 'wicket-gf'); ?><br>' +
                                '<code>addresses.0.city</code> - <?php esc_html_e('Address City', 'wicket-gf'); ?><br>' +
                                '<code>organizations.0.legal_name</code> - <?php esc_html_e('Organization Name', 'wicket-gf'); ?>';
                            break;
                        case 'organization':
                            examples = '<strong><?php esc_html_e('Organization Field Examples:', 'wicket-gf'); ?></strong><br>' +
                                '<code>attributes.legal_name</code> - <?php esc_html_e('Legal Name', 'wicket-gf'); ?><br>' +
                                '<code>attributes.type</code> - <?php esc_html_e('Organization Type', 'wicket-gf'); ?><br>' +
                                '<code>attributes.description</code> - <?php esc_html_e('Description', 'wicket-gf'); ?><br>' +
                                '<code>attributes.identifying_number</code> - <?php esc_html_e('ID Number', 'wicket-gf'); ?>';
                            break;
                        default:
                            examples = '<strong><?php esc_html_e('Select a data source to see field path examples.', 'wicket-gf'); ?></strong>';
                    }

                    $('.wicket-field-examples').html(examples);
                }

                function validateCurrentConfiguration() {
                    var dataSource = $('#apiDataSource').val();
                    var fieldPath = $('#apiFieldPath').val();
                    var orgUuid = $('#apiOrganizationUuid').val();
                    var $statusDiv = $('.wicket-api-status');
                    var $message = $statusDiv.find('.status-message');

                    var validation = validateConfiguration(dataSource, fieldPath, orgUuid);

                    if (validation.valid) {
                        $statusDiv.removeClass('error warning').addClass('success').css('background-color', '#d4edda').css('border', '1px solid #c3e6cb').css('color', '#155724').show();
                        $message.text('<?php esc_html_e('Configuration is valid.', 'wicket-gf'); ?>');
                    } else {
                        $statusDiv.removeClass('success warning').addClass('error').css('background-color', '#f8d7da').css('border', '1px solid #f5c6cb').css('color', '#721c24').show();
                        $message.text(validation.message);
                    }
                }

                function validateFieldConfiguration(field) {
                    var $statusDiv = $('.wicket-api-status');

                    if (!field.apiDataSource) {
                        $statusDiv.hide();
                        return;
                    }

                    validateCurrentConfiguration();
                }

                function validateConfiguration(dataSource, fieldPath, orgUuid) {
                    if (!dataSource) {
                        return { valid: false, message: '<?php esc_html_e('Please select a data source.', 'wicket-gf'); ?>' };
                    }

                    if (!fieldPath) {
                        return { valid: false, message: '<?php esc_html_e('Please enter a field path.', 'wicket-gf'); ?>' };
                    }

                    if (dataSource === 'organization' && !orgUuid) {
                        return { valid: false, message: '<?php esc_html_e('Organization UUID is required for organization data source.', 'wicket-gf'); ?>' };
                    }

                    if (dataSource === 'organization' && orgUuid && !isValidUuid(orgUuid)) {
                        return { valid: false, message: '<?php esc_html_e('Invalid Organization UUID format.', 'wicket-gf'); ?>' };
                    }

                    return { valid: true, message: '<?php esc_html_e('Configuration is valid.', 'wicket-gf'); ?>' };
                }

                function isValidUuid(uuid) {
                    var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
                    return uuidRegex.test(uuid);
                }

                /**
                 * Load available fields from the API
                 */
                function loadAvailableFields(dataSource) {
                    if (!dataSource) {
                        showCustomFieldPath();
                        return;
                    }

                    // Only show dropdown for person_profile and organization
                    if (dataSource !== 'person_profile' && dataSource !== 'organization') {
                        showCustomFieldPath();
                        return;
                    }

                    var organizationUuid = $('#apiOrganizationUuid').val();

                    // For organization, require valid UUID
                    if (dataSource === 'organization') {
                        if (!organizationUuid) {
                            showCustomFieldPath();
                            $('#fieldPathExamples').html('<span style="color: #d63638;"><?php esc_html_e('Please enter Organization UUID first.', 'wicket-gf'); ?></span>');
                            return;
                        }

                        if (!isValidUuid(organizationUuid)) {
                            showCustomFieldPath();
                            $('#fieldPathExamples').html('<span style="color: #d63638;"><?php esc_html_e('Please enter a valid Organization UUID format.', 'wicket-gf'); ?></span>');
                            return;
                        }
                    }

                    // Show loading state
                    $('#apiFieldPathDropdown').html('<option value=""><?php esc_html_e('Loading fields...', 'wicket-gf'); ?></option>');
                    showFieldDropdown();

                    // AJAX request to get available fields
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gf_wicket_get_api_data_fields',
                            nonce: '<?php echo wp_create_nonce('gf_wicket_api_data_nonce'); ?>',
                            data_source: dataSource,
                            organization_uuid: organizationUuid
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                populateFieldDropdown(response.data);
                            } else {
                                showCustomFieldPath();
                                $('#fieldPathExamples').html('<span style="color: #d63638;"><?php esc_html_e('Failed to load fields. Please use custom field path.', 'wicket-gf'); ?></span>');
                            }
                        },
                        error: function() {
                            showCustomFieldPath();
                            $('#fieldPathExamples').html('<span style="color: #d63638;"><?php esc_html_e('Error loading fields. Please use custom field path.', 'wicket-gf'); ?></span>');
                        }
                    });
                }

                /**
                 * Populate the field dropdown with available fields
                 */
                function populateFieldDropdown(fields) {
                    var $dropdown = $('#apiFieldPathDropdown');
                    $dropdown.empty().append('<option value=""><?php esc_html_e('Select a field...', 'wicket-gf'); ?></option>');

                    if (Object.keys(fields).length === 0) {
                        $dropdown.append('<option value="" disabled><?php esc_html_e('No fields available', 'wicket-gf'); ?></option>');
                        return;
                    }

                    // Sort fields by display name
                    var sortedFields = {};
                    Object.keys(fields).sort().forEach(function(key) {
                        sortedFields[key] = fields[key];
                    });

                    // Group fields by type for better organization
                    var groups = {
                        'attributes': {},
                        'data': {},
                        'relationships': {},
                        'other': {}
                    };

                    Object.keys(sortedFields).forEach(function(fieldPath) {
                        var groupName = 'other';
                        if (fieldPath.startsWith('attributes.')) {
                            groupName = 'attributes';
                        } else if (fieldPath.startsWith('data.')) {
                            groupName = 'data';
                        } else if (fieldPath.startsWith('relationships.') || fieldPath.includes('organizations.') || fieldPath.includes('emails.') || fieldPath.includes('phones.') || fieldPath.includes('addresses.')) {
                            groupName = 'relationships';
                        }
                        groups[groupName][fieldPath] = sortedFields[fieldPath];
                    });

                    // Create option groups
                    var groupLabels = {
                        'attributes': '<?php esc_html_e('Attributes', 'wicket-gf'); ?>',
                        'data': '<?php esc_html_e('Data Fields', 'wicket-gf'); ?>',
                        'relationships': '<?php esc_html_e('Relationships', 'wicket-gf'); ?>',
                        'other': '<?php esc_html_e('Other', 'wicket-gf'); ?>'
                    };

                    Object.keys(groups).forEach(function(groupName) {
                        var groupFields = groups[groupName];
                        if (Object.keys(groupFields).length > 0) {
                            var $optgroup = $('<optgroup label="' + groupLabels[groupName] + '"></optgroup>');
                            Object.keys(groupFields).forEach(function(fieldPath) {
                                var displayName = groupFields[fieldPath];
                                $optgroup.append('<option value="' + fieldPath + '">' + displayName + '</option>');
                            });
                            $dropdown.append($optgroup);
                        }
                    });

                    // Set current selection if field path is already set
                    var currentFieldPath = $('#apiFieldPath').val();
                    if (currentFieldPath) {
                        $dropdown.val(currentFieldPath);
                    }
                }

                /**
                 * Show field dropdown, hide custom input
                 */
                function showFieldDropdown() {
                    $('#fieldPathDropdownContainer').show();
                    $('#fieldPathTextContainer').hide();
                    $('#fieldPathInstruction').hide();
                }

                /**
                 * Show custom field path input, hide dropdown
                 */
                function showCustomFieldPath() {
                    $('#fieldPathDropdownContainer').hide();
                    $('#fieldPathTextContainer').show();
                    $('#fieldPathInstruction').show();
                }

                /**
                 * Update field path from dropdown selection
                 */
                function updateFieldPathFromDropdown(fieldPath) {
                    $('#apiFieldPath').val(fieldPath);
                    SetFieldProperty('apiFieldPath', fieldPath);
                    validateCurrentConfiguration();
                }

                /**
                 * Update the Browse Available Fields button state based on data source and UUID
                 */
                function updateBrowseButtonState(dataSource) {
                    var $browseBtn = $('#useFieldDropdownBtn');

                    if (!dataSource) {
                        $browseBtn.prop('disabled', true);
                        $browseBtn.text('<?php esc_html_e('Select Data Source First', 'wicket-gf'); ?>');
                        return;
                    }

                    if (dataSource === 'person_profile') {
                        $browseBtn.prop('disabled', false);
                        $browseBtn.text('<?php esc_html_e('Browse Available Fields', 'wicket-gf'); ?>');
                        return;
                    }

                    if (dataSource === 'organization') {
                        var orgUuid = $('#apiOrganizationUuid').val();

                        if (!orgUuid) {
                            $browseBtn.prop('disabled', true);
                            $browseBtn.text('<?php esc_html_e('Enter Organization UUID', 'wicket-gf'); ?>');
                            return;
                        }

                        if (!isValidUuid(orgUuid)) {
                            $browseBtn.prop('disabled', true);
                            $browseBtn.text('<?php esc_html_e('Enter Valid UUID Format', 'wicket-gf'); ?>');
                            return;
                        }

                        $browseBtn.prop('disabled', false);
                        $browseBtn.text('<?php esc_html_e('Browse Available Fields', 'wicket-gf'); ?>');
                        return;
                    }

                    // For any other data source
                    $browseBtn.prop('disabled', true);
                    $browseBtn.text('<?php esc_html_e('Use Custom Field Path', 'wicket-gf'); ?>');
                }
            });
            </script>

        <?php
        }
    }

    /**
     * AJAX handler to get available fields for API data source.
     */
    public static function ajax_get_api_data_fields()
    {
        check_ajax_referer('gf_wicket_api_data_nonce', 'nonce');

        $data_source = isset($_POST['data_source']) ? sanitize_text_field(wp_unslash($_POST['data_source'])) : null;
        $organization_uuid = isset($_POST['organization_uuid']) ? sanitize_text_field(wp_unslash($_POST['organization_uuid'])) : null;

        $fields = [];

        if ($data_source === 'person_profile') {
            $fields = self::get_person_profile_fields();
        } elseif ($data_source === 'organization' && !empty($organization_uuid)) {
            $fields = self::get_organization_fields_by_uuid($organization_uuid);
        }

        wp_send_json_success($fields);
    }

    /**
     * Get available person profile fields dynamically from MDP API.
     */
    private static function get_person_profile_fields(): array
    {
        if (!function_exists('wicket_current_person_uuid') || !function_exists('wicket_get_person_by_id')) {
            return [];
        }

        $person_uuid = wicket_current_person_uuid();
        if (empty($person_uuid)) {
            return [];
        }

        // Get person data with all relationships
        $person_data = wicket_get_person_by_id($person_uuid, 'organizations,phones,emails,addresses,web_addresses');
        if (!$person_data || is_wp_error($person_data)) {
            return [];
        }

        // Convert to array for consistent processing
        if (function_exists('wicket_convert_obj_to_array') && is_object($person_data)) {
            $person_data = wicket_convert_obj_to_array($person_data);
        }

        $fields = [];
        self::extract_dynamic_fields($person_data, '', $fields);

        return $fields;
    }

    /**
     * Get available organization fields dynamically by UUID.
     */
    private static function get_organization_fields_by_uuid($organization_uuid): array
    {
        if (!function_exists('wicket_get_organization')) {
            return [];
        }

        if (empty($organization_uuid)) {
            return [];
        }

        $org_data = wicket_get_organization($organization_uuid);
        if (!$org_data || is_wp_error($org_data)) {
            return [];
        }

        $fields = [];
        self::extract_dynamic_fields($org_data, '', $fields);

        return $fields;
    }

    /**
     * Recursively extract all available fields from MDP API data structure.
     */
    private static function extract_dynamic_fields($data, $prefix, &$fields): void
    {
        if (!is_array($data) && !is_object($data)) {
            return;
        }

        $original_data = $data;

        // Handle JSON:API structure - extract from both main data and included data
        if (is_array($data) && isset($data['data'])) {
            // Process main data structure
            self::extract_dynamic_fields_recursive($data['data'], '', $fields);

            // Process included data (relationships, phones, addresses, etc.)
            if (isset($data['included']) && is_array($data['included'])) {
                foreach ($data['included'] as $included_item) {
                    if (is_array($included_item) && isset($included_item['type']) && isset($included_item['attributes'])) {
                        $item_type = $included_item['type'];
                        $item_id = $included_item['id'] ?? 'unknown';

                        // Extract fields from included item with prefix like "included.phones.0.number"
                        self::extract_dynamic_fields_recursive($included_item['attributes'], "included.{$item_type}.{$item_id}", $fields);
                    }
                }
            }
        } else {
            // Process as regular array/object
            self::extract_dynamic_fields_recursive($data, $prefix, $fields);
        }
    }

    /**
     * Recursively extract fields from data structure.
     */
    private static function extract_dynamic_fields_recursive($data, $prefix, &$fields): void
    {
        if (!is_array($data) && !is_object($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            $field_path = empty($prefix) ? $key : $prefix . '.' . $key;

            // Skip numeric array indices for now (they're handled separately)
            if (is_numeric($key)) {
                continue;
            }

            // Skip internal/meta fields that users don't need
            $skip_fields = ['links', 'meta', 'relationships'];
            if (in_array($key, $skip_fields)) {
                continue;
            }

            // Skip PHP internal properties and collection metadata
            if (strpos($key, '__') === 0) {
                continue; // Skip magic properties like __PHP_Incomplete_Class_Name
            }
            if (in_array($key, ['escapeWhenCastingToString', 'items'])) {
                continue; // Skip Laravel collection metadata
            }

            // Handle special cases
            if ($key === 'attributes' && is_array($value)) {
                // Extract individual attributes instead of adding "attributes" as a field
                foreach ($value as $attr_key => $attr_value) {
                    if (!is_numeric($attr_key)) {
                        $attr_path = empty($prefix) ? $attr_key : $prefix . '.' . $attr_key;
                        $display_name = self::format_field_display_name_dynamic($attr_path, $attr_value);
                        $fields[$attr_path] = $display_name;

                        // Recursively process nested attribute values
                        if (is_array($attr_value) || is_object($attr_value)) {
                            self::extract_dynamic_fields_recursive($attr_value, $attr_path, $fields);
                        }
                    }
                }
                continue;
            }

            // Get user-friendly display name
            $display_name = self::format_field_display_name_dynamic($field_path, $value);

            // Add this field to the list
            $fields[$field_path] = $display_name;

            // Recursively process nested data
            if (is_array($value) || is_object($value)) {
                self::extract_dynamic_fields_recursive($value, $field_path, $fields);
            }
        }
    }

    /**
     * Format field path for dynamic display.
     */
    private static function format_field_display_name_dynamic($field_path, $value): string
    {
        // Convert field_path to readable format
        $parts = explode('.', $field_path);
        $readable_parts = [];

        foreach ($parts as $part) {
            // Convert snake_case and camelCase to readable format
            $readable = ucfirst(str_replace('_', ' ', $part));
            $readable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $readable);

            // No special mappings - use actual field names for clarity
            // This makes it transparent for users who know the MDP data structure

            $readable_parts[] = $readable;
        }

        $display_name = implode(' → ', $readable_parts);

        // Add value type indicator
        if (is_string($value)) {
            // Check for common patterns to make more descriptive
            if (strpos($part, 'email') !== false || strpos($field_path, 'email') !== false) {
                $display_name .= ' (Email)';
            } elseif (strpos($part, 'phone') !== false || strpos($field_path, 'phone') !== false) {
                $display_name .= ' (Phone)';
            } elseif (strpos($part, 'url') !== false || strpos($field_path, 'url') !== false) {
                $display_name .= ' (URL)';
            } elseif (strpos($part, 'date') !== false || strpos($field_path, 'date') !== false) {
                $display_name .= ' (Date)';
            } else {
                $display_name .= ' (Text)';
            }
        } elseif (is_numeric($value)) {
            $display_name .= ' (Number)';
        } elseif (is_bool($value)) {
            $display_name .= ' (Yes/No)';
        } elseif (is_array($value)) {
            if (empty($value)) {
                $display_name .= ' (Empty List)';
            } else {
                $display_name .= ' (List)';
            }
        }

        return $display_name;
    }
}

// Register AJAX handlers
add_action('wp_ajax_gf_wicket_get_api_data_fields', ['GFApiDataBindField', 'ajax_get_api_data_fields']);
