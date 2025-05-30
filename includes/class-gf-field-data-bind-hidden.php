<?php

declare(strict_types=1);

if (!class_exists('GF_Field')) {
    return;
}

class GFDataBindHiddenField extends GF_Field
{
    public string $type = 'wicket_data_hidden';

    public function get_form_editor_field_title(): string
    {
        return esc_attr__('Wicket Hidden Data Bind', 'wicket-gf');
    }

    public function get_form_editor_button(): array
    {
        return [
            'group' => 'advanced_fields',
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
            'live_update_enable_setting' => [
                'label'   => esc_html__('Enable Live Update', 'wicket-gf'),
                'tooltip' => '<h6>' . esc_html__('Enable Live Update', 'wicket-gf') . '</h6>' .
                    esc_html__('Enable real-time updates from Wicket Widgets on the same page. The field will listen for Wicket Widget SAVE_SUCCESS events.', 'wicket-gf'),
            ],
            'live_update_data_source_setting' => [ // Changed from live_update_widget_source_setting
                'label'   => esc_html__('Live Update: Data Source', 'wicket-gf'), // Changed label
                'type'    => 'select', // Explicitly type as select
                'options' => [ // Add options directly here for initial render
                    'person'       => esc_html__('Person (Current User)', 'wicket-gf'),
                    'organization' => esc_html__('Organization', 'wicket-gf'),
                ],
                'tooltip' => '<h6>' . esc_html__('Data Source', 'wicket-gf') . '</h6>' .
                    esc_html__('Select if the data comes from the current Person or a specific Organization.', 'wicket-gf'),
            ],
            'live_update_organization_uuid_setting' => [ // New setting
                'type'    => 'text',
                'label'   => esc_html__('Live Update: Organization UUID', 'wicket-gf'),
                'tooltip' => '<h6>' . esc_html__('Organization UUID', 'wicket-gf') . '</h6>' .
                    esc_html__('Enter the UUID of the Wicket Organization. Required if Data Source is Organization.', 'wicket-gf'),
                'class'   => 'wicket-gf-dependent-setting wicket-gf-org-setting', // Add class for JS show/hide
            ],
            'live_update_schema_slug_setting' => [ // Changed from live_update_schema_key_setting
                'type'    => 'select', // Change to select
                'label'   => esc_html__('Live Update: Schema/Data Slug', 'wicket-gf'), // Changed label
                'tooltip' => '<h6>' . esc_html__('Schema/Data Slug', 'wicket-gf') . '</h6>' .
                    esc_html__('Select the schema or data slug. This will be populated based on the Data Source.', 'wicket-gf'),
                'class'   => 'wicket-gf-dependent-setting',
            ],
            'live_update_value_key_setting' => [
                'type'    => 'select', // Change to select
                'label'   => esc_html__('Live Update: Value Key', 'wicket-gf'), // Changed label (removed Optional)
                'tooltip' => '<h6>' . esc_html__('Value Key', 'wicket-gf') . '</h6>' .
                    esc_html__('Select the specific value key from the chosen schema. This will be populated after selecting a Schema/Data Slug.', 'wicket-gf'),
                'class'   => 'wicket-gf-dependent-setting',
            ],
        ];
    }

    public static function render_wicket_live_update_settings($position, $form_id): void
    {
        if ($position == 25) {
            ?>
<li class="live_update_enable_setting field_setting">
    <input type="checkbox" id="liveUpdateEnabled"
        onclick="SetFieldProperty('liveUpdateEnabled', this.checked); refreshWicketLiveUpdateView(jQuery(this).closest('ul.gform-settings-panel__fields'));" />
    <label for="liveUpdateEnabled" class="inline">
        <?php esc_html_e('Enable Live Update', 'wicket-gf'); ?>
        <?php gform_tooltip('live_update_enable_setting'); ?>
    </label>
</li>

<?php // Summary View (initially hidden, shown by JS)?>
<li class="live_update_summary_setting field_setting" id="liveUpdateSummaryContainer" style="display:none;">
    <label class="section_label"><?php esc_html_e('Current Live Update Configuration', 'wicket-gf'); ?></label>
    <div id="liveUpdateSummaryDetails" style="padding-bottom: 10px;">
        <p><strong><?php esc_html_e('Data Source:', 'wicket-gf'); ?></strong> <span id="summaryDataSourceText"></span></p>
        <p id="summaryOrgUuidContainer" style="display:none;"><strong><?php esc_html_e('Organization UUID:', 'wicket-gf'); ?></strong> <span id="summaryOrgUuidText"></span></p>
        <p><strong><?php esc_html_e('Schema/Data Slug:', 'wicket-gf'); ?></strong> <span id="summarySchemaSlugText"></span></p>
        <p><strong><?php esc_html_e('Value Key:', 'wicket-gf'); ?></strong> <span id="summaryValueKeyText"></span></p>
    </div>
    <button type="button" id="wicketResetLiveUpdateSettingsButton" class="button gf_input_button" onclick="handleWicketResetLiveUpdateSettings(this)"><?php esc_html_e('Change Settings', 'wicket-gf'); ?></button>
</li>

<?php // Selector View (conditionally hidden/shown by JS)?>
<div id="wicketLiveUpdateSelectorsWrapper">
    <li class="live_update_data_source_setting field_setting">
        <label for="liveUpdateDataSource" class="section_label">
            <?php esc_html_e('Live Update: Data Source', 'wicket-gf'); ?>
            <?php gform_tooltip('live_update_data_source_setting'); ?>
        </label>
        <select id="liveUpdateDataSource"
            onchange="SetFieldProperty('liveUpdateDataSource', this.value); wicketHandleDataSourceChange(this.value, jQuery(this).closest('ul.gform-settings-panel__fields'));">
            <option value="">
                <?php esc_html_e('Select Data Source', 'wicket-gf'); ?>
            </option>
            <option value="person">
                <?php esc_html_e('Person (Current User)', 'wicket-gf'); ?>
            </option>
            <option value="organization">
                <?php esc_html_e('Organization', 'wicket-gf'); ?>
            </option>
        </select>
    </li>

    <li class="live_update_organization_uuid_setting field_setting wicket-gf-org-setting" style="display:none;">
        <label for="liveUpdateOrganizationUuid" class="section_label">
            <?php esc_html_e('Live Update: Organization UUID', 'wicket-gf'); ?>
            <?php gform_tooltip('live_update_organization_uuid_setting'); ?>
        </label>
        <input type="text" id="liveUpdateOrganizationUuid" class="fieldwidth-3"
            onkeyup="SetFieldProperty('liveUpdateOrganizationUuid', this.value); wicketHandleOrgUuidChange(this.value, jQuery(this).closest('ul.gform-settings-panel__fields'));" />
    </li>

    <li class="live_update_schema_slug_setting field_setting">
        <label for="liveUpdateSchemaSlug" class="section_label">
            <?php esc_html_e('Live Update: Schema/Data Slug', 'wicket-gf'); ?>
            <?php gform_tooltip('live_update_schema_slug_setting'); ?>
        </label>
        <select id="liveUpdateSchemaSlug" class="fieldwidth-3"
            onchange="SetFieldProperty('liveUpdateSchemaSlug', this.value); wicketHandleSchemaSlugChange(this.value, jQuery(this).closest('ul.gform-settings-panel__fields'));">
            <option value="">
                <?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?>
            </option>
            <?php // Options will be populated by JavaScript?>
        </select>
    </li>

    <li class="live_update_value_key_setting field_setting">
        <label for="liveUpdateValueKey" class="section_label">
            <?php esc_html_e('Live Update: Value Key', 'wicket-gf'); ?>
            <?php gform_tooltip('live_update_value_key_setting'); ?>
        </label>
        <select id="liveUpdateValueKey" class="fieldwidth-3"
            onchange="SetFieldProperty('liveUpdateValueKey', this.value);">
            <option value="">
                <?php esc_html_e('Select Value Key', 'wicket-gf'); ?>
            </option>
            <?php // Options will be populated by JavaScript?>
        </select>
    </li>
</div>
<?php
        }
    }

    public static function editor_script(): void
    {
        ?>
<script type="text/javascript">
    // Ensure this script is loaded after jQuery and gform_field_settings.js

    function getContext(element) {
        var $context = jQuery(element).closest('ul.gform-settings-panel__fields');
        if (!$context.length) $context = jQuery(element).closest('.settings_panel_content');
        if (!$context.length) $context = jQuery(element).closest('.gform_editor_panel_content');
        if (!$context.length) $context = jQuery('#gform_selected_field_settings'); // Fallback to main settings area
        if (!$context.length) $context = jQuery(document); // Absolute fallback
        return $context;
    }

    function refreshWicketLiveUpdateView(contextElement, forceSwitchToSelectors = false) { // Added forceSwitchToSelectors
        var $context = getContext(contextElement);
        var field = GetSelectedField();

        var $summaryContainer = $context.find('#liveUpdateSummaryContainer');
        var $selectorsWrapper = $context.find('#wicketLiveUpdateSelectorsWrapper');
        var $orgUuidFieldLi = $selectorsWrapper.find('.live_update_organization_uuid_setting'); // Org UUID LI within selectors
        var $orgSummaryContainerP = $summaryContainer.find('#summaryOrgUuidContainer');

        if (field.liveUpdateEnabled) {
            // Determine if we should be in summary mode
            var canShowSummary = field.liveUpdateDataSource && field.liveUpdateSchemaSlug && field.liveUpdateValueKey;

            if (canShowSummary && !forceSwitchToSelectors) { // Check forceSwitchToSelectors
                // Summary Mode
                var dataSourceDisplay = field.liveUpdateDataSource;
                if (field.liveUpdateDataSource === 'person') dataSourceDisplay = '<?php esc_html_e('Person (Current User)', 'wicket-gf'); ?>';
                if (field.liveUpdateDataSource === 'organization') dataSourceDisplay = '<?php esc_html_e('Organization', 'wicket-gf'); ?>';
                $summaryContainer.find('#summaryDataSourceText').text(dataSourceDisplay);

                if (field.liveUpdateDataSource === 'organization' && field.liveUpdateOrganizationUuid) {
                    $summaryContainer.find('#summaryOrgUuidText').text(field.liveUpdateOrganizationUuid);
                    $orgSummaryContainerP.show();
                } else {
                    $orgSummaryContainerP.hide();
                }
                // For schema slug and value key, we display the stored value.
                // Fetching the display text would require another AJAX or storing it.
                $summaryContainer.find('#summarySchemaSlugText').text(field.liveUpdateSchemaSlug);
                $summaryContainer.find('#summaryValueKeyText').text(field.liveUpdateValueKey);

                $summaryContainer.show();
                $selectorsWrapper.hide();
            } else {
                // Selector Mode (either not fully configured or forced by reset)
                $summaryContainer.hide();
                $selectorsWrapper.show();

                // Explicitly show relevant individual selector items
                $selectorsWrapper.find('.live_update_data_source_setting').show();
                $selectorsWrapper.find('.live_update_schema_slug_setting').show();
                $selectorsWrapper.find('.live_update_value_key_setting').show();
                // Org UUID field visibility is handled below based on data source

                var currentDataSourceInDropdown = $context.find('#liveUpdateDataSource').val();
                // Visibility of Org UUID field depends on the current UI selection for Data Source
                if (currentDataSourceInDropdown === 'organization') {
                    $orgUuidFieldLi.show();
                } else {
                    $orgUuidFieldLi.hide();
                }

                var dataSourceToUseForLoading;
                if (forceSwitchToSelectors) {
                    // If forced to selectors (i.e., after a reset), don't attempt to load anything automatically.
                    // The UI has been reset by the button handler, user needs to select again.
                    dataSourceToUseForLoading = '';
                } else {
                    // Normal selector mode (e.g. initial load, field not fully configured)
                    // Try current UI selection first, then fallback to stored field property.
                    dataSourceToUseForLoading = currentDataSourceInDropdown || field.liveUpdateDataSource;
                }

                if (dataSourceToUseForLoading) {
                    // isInitialLoad = false: if data source changes, dependent properties should be cleared by SetFieldProperty.
                    // AJAX success handlers will try to restore saved schema/key if they match new options.
                    wicketHandleDataSourceChange(dataSourceToUseForLoading, $context, false);
                } else {
                    // No data source to load from (e.g. after reset, or new field with no selection)
                    $context.find('#liveUpdateSchemaSlug').html('<option value=""><?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?></option>');
                    $context.find('#liveUpdateValueKey').html('<option value=""><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>');
                    // Ensure Org UUID field is hidden if no data source implies it shouldn't be shown
                    // (this is typically handled by the check on currentDataSourceInDropdown for $orgUuidFieldLi visibility)
                }
            }
        } else {
            // Live Update Disabled
            $summaryContainer.hide();
            $selectorsWrapper.hide();
        }
    }

    // Old toggleWicketLiveUpdateSettings is effectively replaced by refreshWicketLiveUpdateView
    // for managing summary vs selectors.
    // The individual show/hide of org_uuid_setting within selectors is handled by wicketHandleDataSourceChange.

    function wicketHandleDataSourceChange(dataSource, context = null, isInitialLoad = false) {
        var $context = getContext(context || document.body); // Ensure context

        var $orgUuidFieldLi = $context.find('#wicketLiveUpdateSelectorsWrapper .live_update_organization_uuid_setting');
        var $schemaSlugDropdown = $context.find('#liveUpdateSchemaSlug');
        var $valueKeyDropdown = $context.find('#liveUpdateValueKey');

        // Always reset schema and value key dropdowns when data source changes or is cleared
        $schemaSlugDropdown.html(
            '<option value=\"\"><?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?></option>'
        );
        $valueKeyDropdown.html(
            '<option value=\"\"><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
        );

        if (!isInitialLoad) {
            SetFieldProperty('liveUpdateSchemaSlug', '');
            SetFieldProperty('liveUpdateValueKey', '');
            // If dataSource is being cleared, also clear org UUID property
            if (!dataSource) {
                SetFieldProperty('liveUpdateOrganizationUuid', '');
                $context.find('#liveUpdateOrganizationUuid').val(''); // Clear the input field as well
            }
        }

        if (dataSource === 'organization') {
            $orgUuidFieldLi.show();
            var orgUuid = $context.find('#liveUpdateOrganizationUuid').val();
            if (orgUuid && orgUuid.length > 30) { // Basic check for UUID-like string
                wicketFetchSchemas(dataSource, orgUuid, $context);
            } else if (!orgUuid && !isInitialLoad) {
                 $schemaSlugDropdown.html('<option value=\"\"><?php esc_html_e('Enter Organization UUID', 'wicket-gf'); ?></option>');
            }
        } else {
            $orgUuidFieldLi.hide();
            if (dataSource === 'person') {
                wicketFetchSchemas(dataSource, null, $context);
            }
        }
    }

    function wicketHandleOrgUuidChange(orgUuid, context = null) {
        var $context = getContext(context || document.body); // Ensure context
        var dataSource = $context.find('#liveUpdateDataSource').val();
        var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

        var $schemaSlugDropdown = $context.find('#liveUpdateSchemaSlug');
        var $valueKeyDropdown = $context.find('#liveUpdateValueKey');

        if (dataSource === 'organization' && orgUuid && uuidRegex.test(orgUuid)) {
            wicketFetchSchemas(dataSource, orgUuid, $context);
        } else if (dataSource === 'organization') {
            $schemaSlugDropdown.html(
                '<option value=\"\"><?php esc_html_e('Enter valid Org UUID', 'wicket-gf'); ?></option>'
            );
            $valueKeyDropdown.html(
                '<option value=\"\"><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
            );
            SetFieldProperty('liveUpdateSchemaSlug', ''); // Clear if UUID becomes invalid
            SetFieldProperty('liveUpdateValueKey', '');
        }
    }

    function wicketFetchSchemas(dataSource, orgUuid = null, context = null) {
        var $context = getContext(context || document.body); // Ensure context

        var $schemaSlugDropdown = $context.find('#liveUpdateSchemaSlug');
        $schemaSlugDropdown.html(
            '<option value=\"\"><?php esc_html_e('Loading...', 'wicket-gf'); ?></option>'
        );
        var $valueKeyDropdown = $context.find('#liveUpdateValueKey');
        $valueKeyDropdown.html(
            '<option value=\"\"><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
        );

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gf_wicket_get_mdp_schemas',
                nonce: '<?php echo wp_create_nonce('gf_wicket_mdp_nonce'); ?>',
                data_source: dataSource,
                organization_uuid: orgUuid,
                form_id: typeof form !== 'undefined' ? form.id : 0
            },
            success: function(response) {
                $schemaSlugDropdown.html(
                    '<option value=\"\"><?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?></option>'
                );
                if (response.success && response.data) {
                    jQuery.each(response.data, function(value, text) {
                        $schemaSlugDropdown.append(jQuery('<option></option>').attr('value', value)
                            .text(text));
                    });

                    var currentField = GetSelectedField();
                    var savedSchemaSlug = currentField.liveUpdateSchemaSlug;

                    if (savedSchemaSlug && $schemaSlugDropdown.find('option[value=\"' + savedSchemaSlug + '\"]').length > 0) {
                        $schemaSlugDropdown.val(savedSchemaSlug);
                        // Trigger change to load value keys if a schema was auto-selected
                        wicketHandleSchemaSlugChange(savedSchemaSlug, $context);
                    } else {
                        // If no saved schema or saved schema not in options, ensure value key is reset
                        wicketHandleSchemaSlugChange('', $context);
                    }
                } else {
                    var errorMessage =
                        '<?php esc_html_e('Error: ', 'wicket-gf'); ?>' +
                        (response.data ||
                            '<?php esc_html_e('Could not load schemas.', 'wicket-gf'); ?>'
                        );
                    $schemaSlugDropdown.append(jQuery('<option></option>').attr('value', '').text(
                        errorMessage));
                    wicketHandleSchemaSlugChange('', $context); // Ensure value key is reset on error
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage =
                    '<?php esc_html_e('AJAX Error: ', 'wicket-gf'); ?>' +
                    textStatus + ' - ' + errorThrown;
                $schemaSlugDropdown.html(
                    '<option value=\"\"><?php esc_html_e('Error loading schemas', 'wicket-gf'); ?></option>'
                );
                $schemaSlugDropdown.append(jQuery('<option></option>').attr('value', '').text(errorMessage));
                wicketHandleSchemaSlugChange('', $context); // Ensure value key is reset on error
            }
        });
    }

    function wicketHandleSchemaSlugChange(schemaSlug, context = null) {
        var $context = getContext(context || document.body); // Ensure context

        var $valueKeyDropdown = $context.find('#liveUpdateValueKey');
        $valueKeyDropdown.html( // Set to loading or default
            schemaSlug ? '<option value=\"\"><?php esc_html_e('Loading...', 'wicket-gf'); ?></option>' : '<option value=\"\"><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
        );

        if (!schemaSlug) {
            SetFieldProperty('liveUpdateValueKey', '');
            return;
        }

        var dataSource = $context.find('#liveUpdateDataSource').val();
        var orgUuid = (dataSource === 'organization') ? $context.find('#liveUpdateOrganizationUuid').val() : null;

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gf_wicket_get_mdp_value_keys',
                nonce: '<?php echo wp_create_nonce('gf_wicket_mdp_nonce'); ?>',
                data_source: dataSource,
                schema_data_slug: schemaSlug,
                organization_uuid: orgUuid,
                form_id: typeof form !== 'undefined' ? form.id : 0
            },
            success: function(response) {
                $valueKeyDropdown.html(
                    '<option value=\"\"><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
                );
                if (response.success && response.data) {
                    jQuery.each(response.data, function(value, text) {
                        $valueKeyDropdown.append(jQuery('<option></option>').attr('value', value).text(
                            text));
                        });

                    var currentField = GetSelectedField();
                    var savedValueKey = currentField.liveUpdateValueKey;

                    if (savedValueKey && $valueKeyDropdown.find('option[value=\"' + savedValueKey + '\"]').length > 0) {
                        $valueKeyDropdown.val(savedValueKey);
                    }
                } else {
                    var errorMessage =
                        '<?php esc_html_e('Error: ', 'wicket-gf'); ?>' +
                        (response.data ||
                            '<?php esc_html_e('Could not load value keys.', 'wicket-gf'); ?>'
                        );
                    $valueKeyDropdown.append(jQuery('<option></option>').attr('value', '').text(errorMessage));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage =
                    '<?php esc_html_e('AJAX Error: ', 'wicket-gf'); ?>' +
                    textStatus + ' - ' + errorThrown;
                $valueKeyDropdown.html(
                    '<option value=\"\"><?php esc_html_e('Error loading value keys', 'wicket-gf'); ?></option>'
                );
                $valueKeyDropdown.append(jQuery('<option></option>').attr('value', '').text(errorMessage));
            }
        });
    }

    jQuery(document).on('gform_load_field_settings', function(event, field, form) {
        if (field.type === 'wicket_data_hidden') {
            var $fieldSettingsArea = getContext(jQuery('#liveUpdateEnabled')); // Use a known element within the settings panel for context

            $fieldSettingsArea.find('#liveUpdateEnabled').prop('checked', field.liveUpdateEnabled || false);

            // Set initial values for controls in the selectors wrapper
            // These values will be used by refreshWicketLiveUpdateView if it goes into selector mode,
            // or if it needs to decide if summary mode is possible.
            $fieldSettingsArea.find('#liveUpdateDataSource').val(field.liveUpdateDataSource || '');
            $fieldSettingsArea.find('#liveUpdateOrganizationUuid').val(field.liveUpdateOrganizationUuid || '');
            // SchemaSlug and ValueKey are not directly set here; they are restored by their respective AJAX handlers if needed.

            refreshWicketLiveUpdateView($fieldSettingsArea.find('#liveUpdateEnabled').get(0));
        }
    });

    function handleWicketResetLiveUpdateSettings(buttonElement) {
        var $context = getContext(buttonElement);

        SetFieldProperty('liveUpdateDataSource', '');
        SetFieldProperty('liveUpdateOrganizationUuid', '');
        SetFieldProperty('liveUpdateSchemaSlug', '');
        SetFieldProperty('liveUpdateValueKey', '');

        // Clear UI elements in selectors
        $context.find('#liveUpdateDataSource').val('');
        $context.find('#liveUpdateOrganizationUuid').val('');
        $context.find('#liveUpdateSchemaSlug').html('<option value=""><?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?></option>').val('');
        $context.find('#liveUpdateValueKey').html('<option value=""><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>').val('');

        refreshWicketLiveUpdateView(buttonElement, true); // Pass true to force selector view
    }
</script>
<?php
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
            return '<p>' . esc_html__('Wicket Hidden Data Bind Field: Captures data from Wicket widgets or other sources. Configure in field settings.', 'wicket-gf') . '</p>';
        }
        $id = (int) $this->id;
        $field_id = sprintf('input_%d_%d', $form['id'], $this->id);
        $input_value = esc_attr($value);
        $data_attributes = '';
        $class_attribute = '';

        if (!empty($this->liveUpdateEnabled)) {
            $class_attribute = " class='wicket-gf-live-update-target'";
            $data_attributes .= ' data-live-update-enabled="true"';

            if (!empty($this->liveUpdateDataSource)) { // Changed from liveUpdateWidgetSource
                $data_attributes .= ' data-live-update-data-source="' . esc_attr($this->liveUpdateDataSource) . '"';
            }

            if ($this->liveUpdateDataSource === 'organization' && !empty($this->liveUpdateOrganizationUuid)) { // New
                $data_attributes .= ' data-live-update-organization-uuid="' . esc_attr($this->liveUpdateOrganizationUuid) . '"';
            }

            if (!empty($this->liveUpdateSchemaSlug)) { // Changed from liveUpdateSchemaKey
                $data_attributes .= ' data-live-update-schema-slug="' . esc_attr($this->liveUpdateSchemaSlug) . '"';
            }

            if (isset($this->liveUpdateValueKey)) {
                $data_attributes .= ' data-live-update-value-key="' . esc_attr($this->liveUpdateValueKey) . '"';
            }
        }

        return sprintf("<input name='input_%d' id='%s' type='hidden'%s value='%s'%s />", $id, $field_id, $class_attribute, $input_value, $data_attributes);
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
            if ($data_source === 'person') {
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
                                    $schema_def = $attributes['schema'] ?? null;
                                    if (is_array($schema_def) && isset($schema_def['title'])) {
                                        $title = $schema_def['title'];
                                    }
                                    $ui_schema = $attributes['ui_schema'] ?? null;
                                    if (!$title && is_array($ui_schema)) {
                                        if (isset($ui_schema['ui:i18n']['title']['en'])) {
                                            $title = $ui_schema['ui:i18n']['title']['en'];
                                        } elseif (isset($ui_schema['title'])) {
                                            $title = $ui_schema['title'];
                                        }
                                    }
                                    $option_text = $title ? $title : ucfirst(str_replace('_', ' ', $identifier));
                                    $options[$identifier] = $option_text;
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
            } else {
                wp_send_json_error('Invalid data source specified.');

                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('API Error: ' . $e->getMessage());

            return;
        }

        if (empty($options)) {
            wp_send_json_error('No schemas found for the selected source.');

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
            if ($data_source === 'person') {
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

GF_Fields::register(new GFDataBindHiddenField());
