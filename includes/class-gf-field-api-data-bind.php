<?php

declare(strict_types=1);

/**
 * Gravity Forms API Data Bind Field.
 *
 * This field type provides live binding between Gravity Forms fields and Wicket API data.
 *
 * Current Implementation:
 * - Client-side JavaScript handles ORGSS field binding for multi-step forms
 * - Uses sessionStorage to persist organization selections across form steps
 * - Real-time field updates when organization is selected
 * - Backwards compatible with static UUID configuration
 *
 * ALTERNATIVE SERVER-SIDE APPROACHES (for future reference):
 *
 * Option 1: Server-Side Field Pre-Population
 * -----------------------------------------
 * Use Gravity Forms gform_field_value filter to pre-populate the field on step load:
 *
 * add_filter('gform_field_value', 'populate_api_data_bind_field', 10, 3);
 *
 * function populate_api_data_bind_field($value, $field, $form) {
 *     if ($field->type !== 'wicket_api_data_bind') {
 *         return $value;
 *     }
 *
 *     // Check if we have stored org selection from previous step
 *     $stored_selection = get_transient('gform_org_selection_' . $form['id']);
 *     if ($stored_selection) {
 *         // Reuse the field's API fetching logic
 *         $api_field = new GFApiDataBindField();
 *         $api_field->apiDataSource = 'organization';
 *         $api_field->apiFieldPath = $field->apiFieldPath;
 *         return $api_field->fetch_value_from_api_by_uuid($stored_selection);
 *     }
 *
 *     return $value;
 * }
 *
 * Benefits:
 * - No JavaScript dependency
 * - Field values are part of initial form HTML
 * - Better for SEO and accessibility
 * - Values survive page refresh
 *
 * Drawbacks:
 * - Requires server-side state management (transients/session)
 * - More complex implementation
 * - Can't update in real-time after page load
 *
 * Option 2: Hidden Field Injection
 * -------------------------------
 * Create a hidden field when ORGSS selection happens and inject it into form submission:
 *
 * add_action('gform_pre_submission', 'inject_org_data_hidden_field');
 *
 * function inject_org_data_hidden_field($form) {
 *     foreach ($form['fields'] as $field) {
 *         if ($field->type === 'wicket_api_data_bind' &&
 *             ($field->orgUuidSource ?? 'static') === 'orgss_field') {
 *
 *             $org_uuid = $_POST['input_' . $field->orgssFieldId] ?? '';
 *             if (!empty($org_uuid)) {
 *                 // Create hidden input field with the API data
 *                 $_POST['input_' . $field->id] = fetch_org_data_for_field($org_uuid, $field);
 *             }
 *         }
 *     }
 * }
 *
 * Benefits:
 * - Clean separation between display and submission
 * - No client-side JavaScript required for value setting
 * - Values are part of official form submission
 *
 * Drawbacks:
 * - Only works on form submission, not real-time display
 * - Conditional logic won't work until form is submitted
 * - More complex hook management
 *
 * Option 3: Enhanced AJAX Endpoint with Caching
 * --------------------------------------------
 * Enhance the existing AJAX endpoint with server-side caching:
 *
 * public static function ajax_fetch_value_for_live_update() {
 *     check_ajax_referer('wicket_gf_api_data_bind', 'nonce');
 *
 *     $org_uuid = sanitize_text_field(wp_unslash($_POST['org_uuid'] ?? ''));
 *     $field_path = sanitize_text_field(wp_unslash($_POST['field_path'] ?? ''));
 *     $cache_key = "wicket_org_data_{$org_uuid}_" . md5($field_path);
 *
 *     // Check cache first
 *     $cached_value = wp_cache_get($cache_key, 'wicket_org_data');
 *     if ($cached_value !== false) {
 *         wp_send_json_success($cached_value);
 *         return;
 *     }
 *
 *     // Fetch fresh data
 *     $org_data = wicket_get_organization($org_uuid);
 *     if (!$org_data || is_wp_error($org_data)) {
 *         wp_send_json_error('Failed to fetch organization data');
 *         return;
 *     }
 *
 *     $temp_field = new self();
 *     $value = $temp_field->extract_field_value($org_data, $field_path);
 *
 *     // Cache for 15 minutes
 *     wp_cache_set($cache_key, $value, 'wicket_org_data', 15 * MINUTE_IN_SECONDS);
 *
 *     wp_send_json_success($value);
 * }
 *
 * Benefits:
 * - Improved performance for repeated requests
 * - Reduces API calls to Wicket
 * - Maintains current client-side approach
 * - Easy to implement
 *
 * Drawbacks:
 * - Still requires JavaScript
 * - Cache invalidation complexity
 * - Stale data if organization changes during cache period
 *
 * Option 4: Hybrid Approach with Gravity Forms Hooks
 * --------------------------------------------------
 * Combine server-side population with client-side updates:
 *
 * // Server-side: Pre-populate on form load
 * add_filter('gform_field_value', 'populate_api_data_bind_field', 10, 3);
 *
 * // Client-side: Update when org changes (current implementation)
 * // Keep existing JavaScript for real-time updates
 *
 * Benefits:
 * - Best of both worlds
 * - Graceful degradation if JavaScript fails
 * - Initial page load shows correct values
 * - Real-time updates still work
 *
 * Drawbacks:
 * - Most complex implementation
 * - Requires maintaining both server and client code
 * - Potential for code duplication
 */
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
            'wicket_api_orgss_binding_setting',
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
                field.orgUuidSource = 'static';
                field.orgssFieldId = '';
                field.liveUpdateEnabled = false;
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
        // Skip server-side fetch if using ORGSS binding (JavaScript will handle it)
        $org_uuid_source = $this->orgUuidSource ?? 'static';
        $live_update_enabled = $this->liveUpdateEnabled ?? false;
        $should_skip_server_fetch = ($org_uuid_source === 'orgss_field' && $live_update_enabled);

        if (empty($value) && !empty($this->apiDataSource) && !empty($this->apiFieldPath) && !$should_skip_server_fetch) {
            $value = $this->fetch_value_from_api();
        }

        $input_value = esc_attr($value);
        $placeholder = !empty($this->apiFallbackValue) ? esc_attr($this->apiFallbackValue) : '';

        return $this->get_field_input_by_display_mode($id, $field_id, $input_value, $placeholder, $form);
    }

    /**
     * Render field input based on display mode.
     */
    private function get_field_input_by_display_mode($id, $field_id, $value, $placeholder, $form): string
    {
        $display_mode = $this->apiDisplayMode ?? 'hidden';
        $css_class = !empty($this->cssClass) ? esc_attr($this->cssClass) : '';

        // Get data attributes for ORGSS binding
        $data_attributes = $this->build_data_attributes($form);

        // Add target class if data attributes are present
        if (!empty($data_attributes)) {
            $css_class .= ' wicket-gf-api-data-bind-target';
        }

        switch ($display_mode) {
            case 'hidden':
                return sprintf(
                    "<input name='input_%d' id='%s' type='hidden' value='%s' class='%s'%s />",
                    $id,
                    $field_id,
                    $value,
                    $css_class,
                    $data_attributes
                );

            case 'static':
                return sprintf(
                    "<div id='%s' class='wicket-api-data-bind-static %s'%s>%s</div>",
                    $field_id,
                    $css_class,
                    $data_attributes,
                    !empty($value) ? esc_html($value) : '<em>' . esc_html__('No data available', 'wicket-gf') . '</em>'
                );

            case 'editable':
                return sprintf(
                    "<input name='input_%d' id='%s' type='text' class='large textfield %s' value='%s' placeholder='%s'%s />",
                    $id,
                    $field_id,
                    $css_class,
                    $value,
                    $placeholder,
                    $data_attributes
                );

            case 'readonly':
            default:
                return sprintf(
                    "<input name='input_%d' id='%s' type='text' class='large textfield %s' value='%s' placeholder='%s' readonly%s />",
                    $id,
                    $field_id,
                    $css_class,
                    $value,
                    $placeholder,
                    $data_attributes
                );
        }
    }

    /**
     * Build data attributes for frontend JavaScript binding.
     *
     * @param array $form The form object
     * @return string HTML data attributes string
     */
    private function build_data_attributes($form): string
    {
        // Only add data attributes if ORGSS binding is enabled
        $org_uuid_source = $this->orgUuidSource ?? 'static';
        $live_update_enabled = $this->liveUpdateEnabled ?? false;

        if ($org_uuid_source !== 'orgss_field' || !$live_update_enabled) {
            return '';
        }

        // Build data attributes string (no class attribute here - handled in get_field_input_by_display_mode)
        $attributes = ' data-api-bind-enabled="true"';
        $attributes .= ' data-api-bind-data-source="' . esc_attr($this->apiDataSource ?? '') . '"';
        $attributes .= ' data-api-bind-field-path="' . esc_attr($this->apiFieldPath ?? '') . '"';
        $attributes .= ' data-api-bind-orgss-field-id="' . esc_attr($this->orgssFieldId ?? '') . '"';
        $attributes .= ' data-api-bind-display-mode="' . esc_attr($this->apiDisplayMode ?? 'hidden') . '"';
        $attributes .= ' data-api-bind-fallback="' . esc_attr($this->apiFallbackValue ?? '') . '"';
        $attributes .= ' data-api-bind-form-id="' . esc_attr($form['id']) . '"';

        return $attributes;
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
        return function_exists('wicket_current_person_uuid')
               && function_exists('wicket_get_person_by_id')
               && function_exists('wicket_get_organization');
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

        foreach ($path_parts as $index => $part) {
            // Special handling if current part is 'data_fields' - use dedicated extraction
            if ($part === 'data_fields') {
                // Look for data_fields in current level or in attributes
                $data_fields_array = null;
                if (isset($current_data['data_fields']) && is_array($current_data['data_fields'])) {
                    $data_fields_array = $current_data['data_fields'];
                } elseif (isset($current_data['attributes']['data_fields']) && is_array($current_data['attributes']['data_fields'])) {
                    $data_fields_array = $current_data['attributes']['data_fields'];
                }

                if ($data_fields_array) {
                    $result = $this->extract_data_field_value($data_fields_array, $field_path, $part);

                    return $result ?? '';
                }

                return '';
            }

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
                // Check if data_fields exists directly
                if (isset($current_data['data_fields']) && is_array($current_data['data_fields'])) {
                    $data_fields_value = $this->extract_data_field_value($current_data['data_fields'], $field_path, $part);
                    if ($data_fields_value !== null) {
                        return $data_fields_value;
                    }
                }

                // Check if data_fields exists in attributes
                if (isset($current_data['attributes']['data_fields']) && is_array($current_data['attributes']['data_fields'])) {
                    $data_fields_value = $this->extract_data_field_value($current_data['attributes']['data_fields'], $field_path, $part);
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
     * Supports advanced search path format: data_fields.{schema_slug}.value.{field_key}.
     */
    private function extract_data_field_value($data_fields, $full_field_path, $current_part): ?string
    {
        $path_parts = explode('.', $full_field_path);
        $current_index = array_search($current_part, $path_parts);

        if ($current_index === false || $current_index >= count($path_parts) - 1) {
            return null;
        }

        $schema_slug = $path_parts[$current_index + 1] ?? null;
        $value_key = $path_parts[$current_index + 2] ?? null;
        $field_key = $path_parts[$current_index + 3] ?? null;

        if (!$schema_slug) {
            return null;
        }

        // Support both formats:
        // 1. New format: data_fields.{schema_slug}.value.{field_key}
        // 2. Legacy format: data_fields.{schema_slug}.{field_key}
        $is_advanced_format = ($value_key === 'value' && $field_key !== null);
        $actual_field_key = $is_advanced_format ? $field_key : $value_key;

        if (!$actual_field_key) {
            return null;
        }

        foreach ($data_fields as $field_data) {
            if (!is_array($field_data) || (!isset($field_data['schema_slug']) && !isset($field_data['schema_id']))) {
                continue;
            }

            $schema_key = $field_data['schema_slug'] ?? $field_data['schema_id'];
            if ($schema_key === $schema_slug) {
                $value = $field_data['value'] ?? null;
                if (is_array($value) && isset($value[$actual_field_key])) {
                    return (string) $value[$actual_field_key];
                } elseif ($actual_field_key === '_self' && !is_array($value)) {
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

            <!-- Organization UUID Source - Only shown when Organization is selected -->
            <li class="wicket_api_orgss_binding_setting field_setting" style="display:none;">
                <label for="orgUuidSource" class="section_label">
                    <?php esc_html_e('Organization UUID Source', 'wicket-gf'); ?>
                    <?php gform_tooltip('org_uuid_source_setting'); ?>
                </label>
                <select id="orgUuidSource">
                    <option value="static"><?php esc_html_e('Static UUID', 'wicket-gf'); ?></option>
                    <option value="orgss_field"><?php esc_html_e('Bind to ORGSS Field', 'wicket-gf'); ?></option>
                </select>
                <p class="instruction">
                    <?php esc_html_e('Choose whether to use a static organization UUID or bind to an ORGSS field selection.', 'wicket-gf'); ?>
                </p>
            </li>

            <!-- Static Organization UUID - Shown when UUID Source is "static" -->
            <li class="wicket_api_organization_uuid_setting field_setting" style="display:none;">
                <label for="apiOrganizationUuid" class="section_label">
                    <?php esc_html_e('Organization UUID', 'wicket-gf'); ?>
                    <?php gform_tooltip('api_organization_uuid_setting'); ?>
                </label>
                <input type="text" id="apiOrganizationUuid" class="fieldwidth-3"
                    placeholder="Required when using Organization data source"
                    onkeyup="SetFieldProperty('apiOrganizationUuid', this.value);" />
                <p class="instruction">
                    <?php esc_html_e('Enter an organization UUID to fetch data from.', 'wicket-gf'); ?>
                </p>
            </li>

            <!-- ORGSS Field Selector - Shown when UUID Source is "orgss_field" -->
            <li class="wicket_api_orgss_field_selector_setting field_setting" style="display:none;">
                <label for="orgssFieldId" class="section_label">
                    <?php esc_html_e('Select ORGSS Field', 'wicket-gf'); ?>
                    <?php gform_tooltip('orgss_field_selector_setting'); ?>
                </label>
                <select id="orgssFieldId" class="fieldwidth-3">
                    <option value=""><?php esc_html_e('Select an ORGSS field...', 'wicket-gf'); ?></option>
                </select>
                <p class="instruction" id="orgssFieldNotice">
                    <?php esc_html_e('Select which ORGSS field this field should bind to. The field will automatically update when an organization is selected.', 'wicket-gf'); ?>
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
                    <?php esc_html_e('Use dot notation to access nested data. Examples: attributes.given_name, organizations.0.legal_name, data_fields.{schema_slug}.value.{field_name}', 'wicket-gf'); ?>
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

                    // Load new ORGSS binding settings
                    var orgUuidSource = field.orgUuidSource || 'static';
                    $('#orgUuidSource').val(orgUuidSource);

                    // Automatically set liveUpdateEnabled based on UUID source
                    // (No UI for this setting - it's automatic)
                    if (orgUuidSource === 'orgss_field') {
                        field.liveUpdateEnabled = true;
                    } else {
                        field.liveUpdateEnabled = false;
                    }

                    // Show/hide organization UUID field based on data source
                    toggleOrganizationUuidField(field.apiDataSource);

                    // Show field examples for data source
                    updateFieldExamples(field.apiDataSource);

                    // IMPORTANT: Populate ORGSS field dropdown FIRST, then set the value
                    populateOrgssFieldDropdown();
                    $('#orgssFieldId').val(field.orgssFieldId || '');

                    // Handle binding UI visibility
                    toggleOrgssBindingUI(field.orgUuidSource || 'static');

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

                // Handle UUID source change
                $('#orgUuidSource').off('change.api-data-bind').on('change.api-data-bind', function() {
                    var uuidSource = this.value;
                    SetFieldProperty('orgUuidSource', uuidSource);

                    // Automatically enable/disable live updates based on UUID source
                    if (uuidSource === 'orgss_field') {
                        SetFieldProperty('liveUpdateEnabled', true);
                    } else {
                        SetFieldProperty('liveUpdateEnabled', false);
                    }

                    toggleOrgssBindingUI(uuidSource);
                    var dataSource = $('#apiDataSource').val();
                    updateBrowseButtonState(dataSource);
                    validateCurrentConfiguration();
                });

                // Handle ORGSS field selection change
                $('#orgssFieldId').off('change.api-data-bind').on('change.api-data-bind', function() {
                    SetFieldProperty('orgssFieldId', this.value);
                    validateCurrentConfiguration();
                });

                // Initial button state update
                var currentField = (typeof GetSelectedField === 'function') ? GetSelectedField() : null;
                var initialDataSource = (currentField && currentField.apiDataSource) ? currentField.apiDataSource : '';
                updateBrowseButtonState(initialDataSource);

                function toggleOrganizationUuidField(dataSource) {
                    var $orgUuidSourceSetting = $('.wicket_api_orgss_binding_setting');

                    if (dataSource === 'organization') {
                        // Show the UUID Source selector
                        $orgUuidSourceSetting.show();

                        // Then show/hide the appropriate sub-fields based on current UUID source
                        var currentUuidSource = $('#orgUuidSource').val() || 'static';
                        toggleOrgssBindingUI(currentUuidSource);
                    } else {
                        // Hide all organization-related fields
                        $orgUuidSourceSetting.hide();
                        $('.wicket_api_organization_uuid_setting').hide();
                        $('.wicket_api_orgss_field_selector_setting').hide();
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
                    var orgUuidSource = $('#orgUuidSource').val();
                    var orgssFieldId = $('#orgssFieldId').val();
                    var $statusDiv = $('.wicket-api-status');
                    var $message = $statusDiv.find('.status-message');

                    var validation = validateConfiguration(dataSource, fieldPath, orgUuid, orgUuidSource, orgssFieldId);

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

                function validateConfiguration(dataSource, fieldPath, orgUuid, orgUuidSource, orgssFieldId) {
                    if (!dataSource) {
                        return { valid: false, message: '<?php esc_html_e('Please select a data source.', 'wicket-gf'); ?>' };
                    }

                    if (!fieldPath) {
                        return { valid: false, message: '<?php esc_html_e('Please enter a field path.', 'wicket-gf'); ?>' };
                    }

                    if (dataSource === 'organization') {
                        // Check UUID source
                        if (!orgUuidSource) {
                            orgUuidSource = 'static'; // Default
                        }

                        if (orgUuidSource === 'static') {
                            // Static UUID validation
                            if (!orgUuid) {
                                return { valid: false, message: '<?php esc_html_e('Organization UUID is required when using static UUID source.', 'wicket-gf'); ?>' };
                            }

                            if (!isValidUuid(orgUuid)) {
                                return { valid: false, message: '<?php esc_html_e('Invalid Organization UUID format.', 'wicket-gf'); ?>' };
                            }
                        } else if (orgUuidSource === 'orgss_field') {
                            // ORGSS field binding validation
                            if (!orgssFieldId) {
                                return { valid: false, message: '<?php esc_html_e('Please select an ORGSS field to bind to.', 'wicket-gf'); ?>' };
                            }
                        }
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
                        var orgUuidSource = $('#orgUuidSource').val() || 'static';
                        var orgUuid = $('#apiOrganizationUuid').val();

                        // For ORGSS binding, we can still allow browse if they provide a sample UUID
                        if (orgUuidSource === 'orgss_field' && !orgUuid) {
                            $browseBtn.prop('disabled', true);
                            $browseBtn.text('<?php esc_html_e('Enter Sample UUID to Browse', 'wicket-gf'); ?>');
                            return;
                        }

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

                /**
                 * Toggle ORGSS binding UI elements based on UUID source
                 */
                function toggleOrgssBindingUI(source) {
                    var $staticUuidSetting = $('.wicket_api_organization_uuid_setting');
                    var $orgssFieldSelector = $('.wicket_api_orgss_field_selector_setting');
                    var $browseBtn = $('#useFieldDropdownBtn');

                    if (source === 'orgss_field') {
                        // Hide static UUID and browse button, show ORGSS selector
                        $staticUuidSetting.hide();
                        $orgssFieldSelector.show();
                        $browseBtn.hide();
                    } else {
                        // Show static UUID and browse button, hide ORGSS selector
                        $staticUuidSetting.show();
                        $orgssFieldSelector.hide();
                        $browseBtn.show();
                    }
                }

                /**
                 * Populate ORGSS field dropdown with available ORGSS fields in the form
                 */
                function populateOrgssFieldDropdown() {
                    var $dropdown = $('#orgssFieldId');
                    var $notice = $('#orgssFieldNotice');

                    // Clear existing options except the first one
                    $dropdown.find('option:not(:first)').remove();

                    // Get current form
                    var form = (typeof window.form !== 'undefined') ? window.form : null;
                    if (!form || !form.fields) {
                        $notice.html('<span style="color: #d63638;"><?php esc_html_e('Unable to load form fields.', 'wicket-gf'); ?></span>');
                        return;
                    }

                    // Find all ORGSS fields
                    var orgssFields = [];
                    for (var i = 0; i < form.fields.length; i++) {
                        var field = form.fields[i];
                        if (field.type === 'wicket_org_search_select') {
                            orgssFields.push({
                                id: field.id,
                                label: field.label || ('Field ' + field.id)
                            });
                        }
                    }

                    if (orgssFields.length === 0) {
                        $notice.html('<span style="color: #d63638;"><?php esc_html_e('No ORGSS fields found in this form. Please add an ORGSS field first.', 'wicket-gf'); ?></span>');
                        return;
                    }

                    // Populate dropdown with ORGSS fields
                    orgssFields.forEach(function(field) {
                        $dropdown.append(
                            $('<option></option>')
                                .attr('value', field.id)
                                .text(field.label + ' (ID: ' + field.id + ')')
                        );
                    });

                    $notice.html('<?php esc_html_e('Select which ORGSS field this field should bind to.', 'wicket-gf'); ?>');
                }
            });
            </script>

        <?php
        }
    }

    /**
     * Check if frontend JavaScript should be enqueued for this form.
     *
     * @param array $form The Gravity Forms form object
     * @return bool True if JS should be enqueued, false otherwise
     */
    public static function should_enqueue_frontend_js($form): bool
    {
        if (empty($form['fields']) || !is_array($form['fields'])) {
            return false;
        }

        foreach ($form['fields'] as $field) {
            // Check if this is an API Data Bind field with ORGSS binding enabled
            if ($field->type === 'wicket_api_data_bind') {
                $org_uuid_source = $field->orgUuidSource ?? 'static';
                $live_update_enabled = $field->liveUpdateEnabled ?? false;

                if ($org_uuid_source === 'orgss_field' && $live_update_enabled) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * AJAX handler for live update - fetch organization data for a specific field path.
     *
     * This handler is called from the frontend JavaScript when an ORGSS field selection is made.
     * It fetches organization data and extracts the value for the requested field path.
     *
     * @return void Sends JSON response
     */
    public static function ajax_fetch_value_for_live_update()
    {
        // Verify nonce for security
        check_ajax_referer('wicket_gf_api_data_bind', 'nonce');

        // Get and sanitize parameters
        $org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
        $field_path = isset($_POST['field_path']) ? sanitize_text_field(wp_unslash($_POST['field_path'])) : '';

        // Validate required parameters
        if (empty($org_uuid)) {
            wp_send_json_error('Missing organization UUID');

            return;
        }

        if (empty($field_path)) {
            wp_send_json_error('Missing field path');

            return;
        }

        // Validate UUID format (RFC 4122)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $org_uuid)) {
            wp_send_json_error('Invalid UUID format');

            return;
        }

        // Check if Wicket API is available
        if (!function_exists('wicket_get_organization')) {
            wp_send_json_error('Wicket API not available');

            return;
        }

        try {
            // Fetch organization data
            $org_data = wicket_get_organization($org_uuid);

            if (!$org_data || is_wp_error($org_data)) {
                wp_send_json_error('Failed to fetch organization data');

                return;
            }

            // Create a temporary instance to leverage existing extraction logic
            $temp_field = new self();
            $value = $temp_field->extract_field_value($org_data, $field_path);

            // Return the extracted value
            wp_send_json_success($value);
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
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

        // Extract schema titles and field labels for person profile
        $schema_info = self::extract_schema_titles($person_data);

        $fields = [];
        self::extract_dynamic_fields($person_data, '', $fields, $schema_info);

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

        // Fetch organization data (json_schemas are included by default which contain ui_schema with friendly titles)
        $org_data = wicket_get_organization($organization_uuid);
        if (!$org_data || is_wp_error($org_data)) {
            return [];
        }

        // Extract schema titles and field labels from json_schemas in included data
        $schema_info = self::extract_schema_titles($org_data);

        $fields = [];
        self::extract_dynamic_fields($org_data, '', $fields, $schema_info);

        return $fields;
    }

    /**
     * Extract schema titles and field labels from json_schemas in included data.
     * Returns array with 'titles' and 'field_labels' keys.
     */
    private static function extract_schema_titles($data): array
    {
        $schema_info = [
            'titles' => [],
            'field_labels' => [],
        ];

        if (!is_array($data) || !isset($data['included'])) {
            return $schema_info;
        }

        foreach ($data['included'] as $included_item) {
            if (!is_array($included_item) || !isset($included_item['type']) || $included_item['type'] !== 'json_schemas') {
                continue;
            }

            $slug = $included_item['attributes']['slug'] ?? null;
            $ui_schema = $included_item['attributes']['ui_schema'] ?? null;

            if (!$slug || !is_array($ui_schema)) {
                continue;
            }

            // Extract title from ui_schema (ui:i18n → title → en)
            $title = $ui_schema['ui:i18n']['title']['en']
                     ?? $ui_schema['ui:i18n']['title']['fr']
                     ?? $ui_schema['ui:i18n']['Title']['En']
                     ?? $ui_schema['title']
                     ?? null;

            if ($title) {
                $schema_info['titles'][$slug] = $title;
            }

            // Extract field labels from ui_schema
            $schema_info['field_labels'][$slug] = [];
            foreach ($ui_schema as $field_key => $field_config) {
                // Skip non-field keys like 'ui:i18n', 'ui:order', etc.
                if (strpos($field_key, 'ui:') === 0 || !is_array($field_config)) {
                    continue;
                }

                // Extract field label (ui:i18n → label → en)
                $field_label = $field_config['ui:i18n']['label']['en']
                               ?? $field_config['ui:i18n']['label']['fr']
                               ?? $field_config['ui:i18n']['Label']['En']
                               ?? $field_config['label']
                               ?? null;

                if ($field_label) {
                    $schema_info['field_labels'][$slug][$field_key] = $field_label;
                }
            }
        }

        return $schema_info;
    }

    /**
     * Recursively extract all available fields from MDP API data structure.
     */
    private static function extract_dynamic_fields($data, $prefix, &$fields, $schema_info = []): void
    {
        if (!is_array($data) && !is_object($data)) {
            return;
        }

        $original_data = $data;

        // Handle JSON:API structure - extract from both main data and included data
        if (is_array($data) && isset($data['data'])) {
            // Process main data structure
            self::extract_dynamic_fields_recursive($data['data'], '', $fields, $schema_info);

            // Process included data (relationships, phones, addresses, etc.)
            if (isset($data['included']) && is_array($data['included'])) {
                foreach ($data['included'] as $included_item) {
                    if (is_array($included_item) && isset($included_item['type']) && isset($included_item['attributes'])) {
                        $item_type = $included_item['type'];
                        $item_id = $included_item['id'] ?? 'unknown';

                        // Skip schema-related included items
                        $type_lower = strtolower($item_type);
                        if (strpos($type_lower, 'schema') !== false
                            || strpos($type_lower, 'json_schema') !== false
                            || strpos($type_lower, 'ui_schema') !== false) {
                            continue;
                        }

                        // Extract fields from included item with prefix like "included.phones.0.number"
                        self::extract_dynamic_fields_recursive($included_item['attributes'], "included.{$item_type}.{$item_id}", $fields, $schema_info);
                    }
                }
            }
        } else {
            // Process as regular array/object
            self::extract_dynamic_fields_recursive($data, $prefix, $fields, $schema_info);
        }
    }

    /**
     * Recursively extract fields from data structure.
     */
    private static function extract_dynamic_fields_recursive($data, $prefix, &$fields, $schema_info = []): void
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
            $skip_fields = ['links', 'meta', 'relationships', 'json_schema', 'json_schemas', 'ui_schema', 'ui_schemas', 'ui_schemas_for_refs'];
            if (in_array($key, $skip_fields)) {
                continue;
            }

            // Skip any keys or paths that contain schema-related terms (case-insensitive)
            $key_lower = strtolower($key);
            $path_lower = strtolower($field_path);

            // Check both the key and the full path for schema-related terms
            if (strpos($key_lower, 'ui:i18n') !== false
                || strpos($key_lower, 'ui_schema') !== false
                || strpos($key_lower, 'json_schema') !== false
                || strpos($path_lower, 'json_schemas') !== false
                || strpos($path_lower, 'ui_schemas') !== false
                || strpos($path_lower, 'schema.') !== false
                || strpos($path_lower, '.schema.') !== false
                || (strpos($key_lower, 'schema') !== false && strpos($key_lower, 'ui') !== false)) {
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
                        // Special handling for data_fields - extract individual fields with advanced search paths
                        if ($attr_key === 'data_fields' && is_array($attr_value)) {
                            self::extract_additional_info_fields($attr_value, $fields, $schema_info);
                            continue;
                        }

                        $attr_path = empty($prefix) ? $attr_key : $prefix . '.' . $attr_key;
                        $display_name = self::format_field_display_name_dynamic($attr_path, $attr_value);
                        $fields[$attr_path] = $display_name;

                        // Recursively process nested attribute values
                        if (is_array($attr_value) || is_object($attr_value)) {
                            self::extract_dynamic_fields_recursive($attr_value, $attr_path, $fields, $schema_info);
                        }
                    }
                }
                continue;
            }

            // Special handling for data_fields (additional info)
            if ($key === 'data_fields' && is_array($value)) {
                self::extract_additional_info_fields($value, $fields, $schema_info);
                continue;
            }

            // Get user-friendly display name
            $display_name = self::format_field_display_name_dynamic($field_path, $value);

            // Add this field to the list
            $fields[$field_path] = $display_name;

            // Recursively process nested data
            if (is_array($value) || is_object($value)) {
                self::extract_dynamic_fields_recursive($value, $field_path, $fields, $schema_info);
            }
        }
    }

    /**
     * Extract additional info fields (data_fields) with proper advanced search paths.
     */
    private static function extract_additional_info_fields($data_fields, &$fields, $schema_info = []): void
    {
        if (!is_array($data_fields)) {
            return;
        }

        // Extract titles and field_labels from schema_info
        $schema_titles = $schema_info['titles'] ?? [];
        $field_labels = $schema_info['field_labels'] ?? [];

        foreach ($data_fields as $field_item) {
            if (!is_array($field_item)) {
                continue;
            }

            // Get schema information
            $schema_slug = $field_item['schema_slug'] ?? null;
            $schema_id = $field_item['schema_id'] ?? null;
            $value_data = $field_item['value'] ?? null;

            if (!$schema_slug && !$schema_id) {
                continue;
            }

            // Use schema_slug if available, otherwise use schema_id
            $schema_key = $schema_slug ?: $schema_id;

            // Get friendly schema title
            $schema_display_name = $schema_titles[$schema_key] ?? ucfirst(str_replace('_', ' ', $schema_key));

            // Extract individual fields from the value array
            if (is_array($value_data)) {
                foreach ($value_data as $field_key => $field_value) {
                    // Build the advanced search path format: data_fields.{schema_slug}.value.{field_key}
                    $field_path = "data_fields.{$schema_key}.value.{$field_key}";

                    // Get friendly field label if available
                    $field_display_name = $field_labels[$schema_key][$field_key] ?? ucfirst(str_replace('_', ' ', $field_key));

                    // Create a friendly display name using schema title and field label
                    $display_name = $schema_display_name . ' → ' . $field_display_name;

                    // Add type indicator
                    if (is_string($field_value)) {
                        $display_name .= ' (Text)';
                    } elseif (is_numeric($field_value)) {
                        $display_name .= ' (Number)';
                    } elseif (is_bool($field_value)) {
                        $display_name .= ' (Yes/No)';
                    } elseif (is_array($field_value)) {
                        $display_name .= ' (List)';
                    }

                    $fields[$field_path] = $display_name;
                }
            } elseif ($value_data !== null) {
                // Single value (not an array)
                $field_path = "data_fields.{$schema_key}.value._self";
                $display_name = $schema_display_name;

                if (is_string($value_data)) {
                    $display_name .= ' (Text)';
                } elseif (is_numeric($value_data)) {
                    $display_name .= ' (Number)';
                } elseif (is_bool($value_data)) {
                    $display_name .= ' (Yes/No)';
                }

                $fields[$field_path] = $display_name;
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
add_action('wp_ajax_gf_wicket_api_data_bind_fetch_value', ['GFApiDataBindField', 'ajax_fetch_value_for_live_update']);
add_action('wp_ajax_nopriv_gf_wicket_api_data_bind_fetch_value', ['GFApiDataBindField', 'ajax_fetch_value_for_live_update']);
