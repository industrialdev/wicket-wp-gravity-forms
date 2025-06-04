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
            'live_update_enable_setting',
            'live_update_summary_setting',
            'live_update_data_source_setting',
            'live_update_organization_uuid_setting',
            'live_update_schema_slug_setting',
            'live_update_value_key_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.liveUpdateEnabled = false;
                field.liveUpdateDataSource = '';
                field.liveUpdateOrganizationUuid = '';
                field.liveUpdateSchemaSlug = '';
                field.liveUpdateValueKey = '';
            }",
            $this->type,
            $this->get_form_editor_field_title()
        );
    }

    public static function render_wicket_live_update_settings($position, $form_id): void
    {
        if ($position == 25) {
            ?>
<li class="live_update_enable_setting field_setting">
    <input type="checkbox" id="liveUpdateEnabled"
        onclick="SetFieldProperty('liveUpdateEnabled', this.checked); refreshWicketLiveUpdateView(jQuery(this).closest('ul.gform-settings-panel__fields'));" />
    <label for="liveUpdateEnabled" class="inline">
        <?php esc_html_e('Enable Data Bind', 'wicket-gf'); ?>
        <?php gform_tooltip('live_update_enable_setting'); ?>
    </label>
</li>

<?php // Summary View (initially hidden, shown by JS)?>
<li class="live_update_summary_setting field_setting" id="liveUpdateSummaryContainer" style="display:none;">
    <label
        class="section_label"><?php esc_html_e('Current Data Bind Configuration', 'wicket-gf'); ?></label>
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
    <button type="button" id="wicketResetLiveUpdateSettingsButton" class="button gf_input_button"
        onclick="handleWicketResetLiveUpdateSettings(this)"><?php esc_html_e('Change Settings', 'wicket-gf'); ?></button>
</li>

<?php // Selector View (conditionally hidden/shown by JS)?>
<div id="wicketLiveUpdateSelectorsWrapper">
    <li class="live_update_data_source_setting field_setting">
        <label for="liveUpdateDataSource" class="section_label">
            <?php esc_html_e('Bind: Data Source', 'wicket-gf'); ?>
            <?php gform_tooltip('live_update_data_source_setting'); ?>
        </label>
        <select id="liveUpdateDataSource"
            onchange="SetFieldProperty('liveUpdateDataSource', this.value); wicketHandleDataSourceChange(this.value, jQuery(this).closest('ul.gform-settings-panel__fields'));">
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
        </select>
    </li>

    <li class="live_update_organization_uuid_setting field_setting wicket-gf-org-setting" style="display:none;">
        <label for="liveUpdateOrganizationUuid" class="section_label">
            <?php esc_html_e('Bind: Organization UUID', 'wicket-gf'); ?>
            <?php gform_tooltip('live_update_organization_uuid_setting'); ?>
        </label>
        <input type="text" id="liveUpdateOrganizationUuid" class="fieldwidth-3"
            onkeyup="SetFieldProperty('liveUpdateOrganizationUuid', this.value); wicketHandleOrgUuidChange(this.value, jQuery(this).closest('ul.gform-settings-panel__fields'));" />
    </li>

    <li class="live_update_schema_slug_setting field_setting">
        <label for="liveUpdateSchemaSlug" class="section_label">
            <?php esc_html_e('Bind: Schema/Data Slug', 'wicket-gf'); ?>
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
            <?php esc_html_e('Bind: Value Key', 'wicket-gf'); ?>
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
    // Use GF's native hooks and APIs for better integration

    // Register field type with GF's field system
    if (typeof gform !== 'undefined' && gform.addFilter) {
        gform.addFilter('gform_form_editor_can_field_be_added', function(canAdd, fieldType) {
            if (fieldType === 'wicket_data_hidden') {
                return true;
            }
            return canAdd;
        });
    }

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
        var $orgUuidFieldLi = $selectorsWrapper.find(
        '.live_update_organization_uuid_setting'); // Org UUID LI within selectors
        var $orgSummaryContainerP = $summaryContainer.find('#summaryOrgUuidContainer');

        if (field.liveUpdateEnabled) {
            // Determine if we should be in summary mode
            var canShowSummary = field.liveUpdateDataSource && field.liveUpdateSchemaSlug && field.liveUpdateValueKey;

            if (canShowSummary && !forceSwitchToSelectors) { // Check forceSwitchToSelectors
                // Summary Mode
                var dataSourceDisplay = field.liveUpdateDataSource;
                if (field.liveUpdateDataSource === 'person_addinfo') dataSourceDisplay =
                    '<?php esc_html_e('Person Add. Info. (Current User)', 'wicket-gf'); ?>';
                if (field.liveUpdateDataSource === 'person_profile') dataSourceDisplay =
                    '<?php esc_html_e('Person Profile (Current User)', 'wicket-gf'); ?>'; // New display text
                if (field.liveUpdateDataSource === 'organization') dataSourceDisplay =
                    '<?php esc_html_e('Organization', 'wicket-gf'); ?>';
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
                    $context.find('#liveUpdateSchemaSlug').html(
                        '<option value=""><?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?></option>'
                        );
                    $context.find('#liveUpdateValueKey').html(
                        '<option value=""><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
                        );
                    // Ensure Org UUID field is hidden if no data source implies it shouldn't be shown
                    // (this is typically handled by the check on currentDataSourceInDropdown for $orgUuidFieldLi visibility)
                }
            }
        } else {
            // Data Bind Disabled
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
                $schemaSlugDropdown.html(
                    '<option value=\"\"><?php esc_html_e('Enter Organization UUID', 'wicket-gf'); ?></option>'
                    );
            }
        } else {
            $orgUuidFieldLi.hide();
            if (dataSource === 'person_addinfo' || dataSource === 'person_profile') { // Added person_profile
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
        var $context = getContext(context || document.body);
        var $schemaSlugDropdown = $context.find('#liveUpdateSchemaSlug');
        var $valueKeyDropdown = $context.find('#liveUpdateValueKey');

        // Use GF's loading pattern
        $schemaSlugDropdown.html('<option value=""><?php esc_html_e('Loading...', 'wicket-gf'); ?></option>');
        $valueKeyDropdown.html('<option value=""><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>');

        // Use native GF form reference if available
        var formId = 0;
        if (typeof form !== 'undefined' && form.id) {
            formId = form.id;
        } else if (typeof gf_form_editor !== 'undefined' && gf_form_editor.form && gf_form_editor.form.id) {
            formId = gf_form_editor.form.id;
        }

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gf_wicket_get_mdp_schemas',
                nonce: '<?php echo wp_create_nonce('gf_wicket_mdp_nonce'); ?>',
                data_source: dataSource,
                organization_uuid: orgUuid,
                form_id: formId
            },
            success: function(response) {
                $schemaSlugDropdown.html('<option value=""><?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?></option>');

                if (response.success && response.data) {
                    // Use GF's choice loading pattern
                    jQuery.each(response.data, function(value, text) {
                        $schemaSlugDropdown.append(jQuery('<option></option>').attr('value', value).text(text));
                    });

                    // Restore saved value using GF's field system
                    var currentField = GetSelectedField();
                    var savedSchemaSlug = currentField.liveUpdateSchemaSlug;

                    if (savedSchemaSlug && $schemaSlugDropdown.find('option[value="' + savedSchemaSlug + '"]').length > 0) {
                        $schemaSlugDropdown.val(savedSchemaSlug);
                        wicketHandleSchemaSlugChange(savedSchemaSlug, $context);
                    } else {
                        wicketHandleSchemaSlugChange('', $context);
                    }
                } else {
                    var errorMessage = '<?php esc_html_e('Error: ', 'wicket-gf'); ?>' +
                        (response.data || '<?php esc_html_e('Could not load schemas.', 'wicket-gf'); ?>');
                    $schemaSlugDropdown.append(jQuery('<option></option>').attr('value', '').text(errorMessage));
                    wicketHandleSchemaSlugChange('', $context);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage = '<?php esc_html_e('AJAX Error: ', 'wicket-gf'); ?>' + textStatus + ' - ' + errorThrown;
                $schemaSlugDropdown.html('<option value=""><?php esc_html_e('Error loading schemas', 'wicket-gf'); ?></option>');
                $schemaSlugDropdown.append(jQuery('<option></option>').attr('value', '').text(errorMessage));
                wicketHandleSchemaSlugChange('', $context);
            }
        });
    }

    function wicketHandleSchemaSlugChange(schemaSlug, context = null) {
        var $context = getContext(context || document.body);
        var $valueKeyDropdown = $context.find('#liveUpdateValueKey');

        $valueKeyDropdown.html(
            schemaSlug ?
            '<option value=""><?php esc_html_e('Loading...', 'wicket-gf'); ?></option>' :
            '<option value=""><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
        );

        if (!schemaSlug) {
            SetFieldProperty('liveUpdateValueKey', '');
            return;
        }

        var dataSource = $context.find('#liveUpdateDataSource').val();
        var orgUuid = (dataSource === 'organization') ? $context.find('#liveUpdateOrganizationUuid').val() : null;

        // Use GF's form ID reference
        var formId = 0;
        if (typeof form !== 'undefined' && form.id) {
            formId = form.id;
        } else if (typeof gf_form_editor !== 'undefined' && gf_form_editor.form && gf_form_editor.form.id) {
            formId = gf_form_editor.form.id;
        }

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gf_wicket_get_mdp_value_keys',
                nonce: '<?php echo wp_create_nonce('gf_wicket_mdp_nonce'); ?>',
                data_source: dataSource,
                schema_data_slug: schemaSlug,
                organization_uuid: orgUuid,
                form_id: formId
            },
            success: function(response) {
                $valueKeyDropdown.html('<option value=""><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>');

                if (response.success && response.data) {
                    // Use GF's choice loading pattern
                    jQuery.each(response.data, function(value, text) {
                        $valueKeyDropdown.append(jQuery('<option></option>').attr('value', value).text(text));
                    });

                    // Restore saved value using GF's field system
                    var currentField = GetSelectedField();
                    var savedValueKey = currentField.liveUpdateValueKey;

                    if (savedValueKey && $valueKeyDropdown.find('option[value="' + savedValueKey + '"]').length > 0) {
                        $valueKeyDropdown.val(savedValueKey);
                    }
                } else {
                    var errorMessage = '<?php esc_html_e('Error: ', 'wicket-gf'); ?>' +
                        (response.data || '<?php esc_html_e('Could not load value keys.', 'wicket-gf'); ?>');
                    $valueKeyDropdown.append(jQuery('<option></option>').attr('value', '').text(errorMessage));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage = '<?php esc_html_e('AJAX Error: ', 'wicket-gf'); ?>' + textStatus + ' - ' + errorThrown;
                $valueKeyDropdown.html('<option value=""><?php esc_html_e('Error loading value keys', 'wicket-gf'); ?></option>');
                $valueKeyDropdown.append(jQuery('<option></option>').attr('value', '').text(errorMessage));
            }
        });
    }

    // Use GF's native field settings event system
    jQuery(document).on('gform_load_field_settings', function(event, field, form) {
        if (field.type === 'wicket_data_hidden') {
            var $fieldSettingsArea = getContext(jQuery('#liveUpdateEnabled'));

            $fieldSettingsArea.find('#liveUpdateEnabled').prop('checked', field.liveUpdateEnabled || false);
            $fieldSettingsArea.find('#liveUpdateDataSource').val(field.liveUpdateDataSource || '');
            $fieldSettingsArea.find('#liveUpdateOrganizationUuid').val(field.liveUpdateOrganizationUuid || '');

            refreshWicketLiveUpdateView($fieldSettingsArea.find('#liveUpdateEnabled').get(0));
        }
    });

    // Use GF's field property change system
    if (typeof gform !== 'undefined' && gform.addAction) {
        gform.addAction('gform_post_set_field_property', function(field, property, value) {
            if (field.type === 'wicket_data_hidden') {
                // Handle property changes that affect our UI
                if (['liveUpdateEnabled', 'liveUpdateDataSource', 'liveUpdateOrganizationUuid',
                     'liveUpdateSchemaSlug', 'liveUpdateValueKey'].includes(property)) {

                    var $context = getContext(jQuery('#liveUpdateEnabled'));
                    setTimeout(function() {
                        refreshWicketLiveUpdateView($context.find('#liveUpdateEnabled').get(0));
                    }, 100); // Small delay to ensure property is set
                }
            }
        });
    }

    // Add GF-native hooks for better integration
    if (typeof gform !== 'undefined') {
        // Use GF's field added hook to initialize our field
        if (gform.addAction) {
            gform.addAction('gform_field_added', function(form, field) {
                if (field.type === 'wicket_data_hidden') {
                    // Initialize field with proper defaults
                    field.liveUpdateEnabled = false;
                    field.liveUpdateDataSource = '';
                    field.liveUpdateOrganizationUuid = '';
                    field.liveUpdateSchemaSlug = '';
                    field.liveUpdateValueKey = '';
                }
            });

            // Hook into editor field settings to show/hide our panels
            gform.addAction('gform_editor_field_settings', function(field) {
                if (field && field.type === 'wicket_data_hidden') {
                    // Ensure our custom settings are visible
                    jQuery('.live_update_enable_setting').show();
                    refreshWicketLiveUpdateView(jQuery('#liveUpdateEnabled').get(0));
                }
            });

            // Hook into form editor field property validation
            gform.addFilter('gform_is_valid_formula_form_editor', function(isValid, formula, field) {
                if (field && field.type === 'wicket_data_hidden') {
                    // Custom validation for our field if needed
                    return isValid;
                }
                return isValid;
            });
        }

        // Use GF's filter to customize field choices loading
        if (gform.addFilter) {
            gform.addFilter('gform_load_field_choices', function(choices, field) {
                // This could be used for dynamic choice loading if needed
                return choices;
            });

            // Hook into form saving to validate our field configuration
            gform.addFilter('gform_pre_form_editor_save', function(form) {
                // Validate wicket_data_hidden fields before saving
                if (form && form.fields) {
                    jQuery.each(form.fields, function(index, field) {
                        if (field.type === 'wicket_data_hidden' && field.liveUpdateEnabled) {
                            if (!field.liveUpdateDataSource) {
                                // Could add validation here
                            }
                        }
                    });
                }
                return form;
            });
        }
    }

    function handleWicketResetLiveUpdateSettings(buttonElement) {
        var $context = getContext(buttonElement);

        SetFieldProperty('liveUpdateDataSource', '');
        SetFieldProperty('liveUpdateOrganizationUuid', '');
        SetFieldProperty('liveUpdateSchemaSlug', '');
        SetFieldProperty('liveUpdateValueKey', '');

        // Clear UI elements in selectors
        $context.find('#liveUpdateDataSource').val('');
        $context.find('#liveUpdateOrganizationUuid').val('');
        $context.find('#liveUpdateSchemaSlug').html(
            '<option value=""><?php esc_html_e('Select Schema/Data Slug', 'wicket-gf'); ?></option>'
            ).val('');
        $context.find('#liveUpdateValueKey').html(
            '<option value=""><?php esc_html_e('Select Value Key', 'wicket-gf'); ?></option>'
            ).val('');

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
            $class_attribute = " class='wicket-gf-hidden-data-bind-target'";
            $data_attributes .= ' data-hidden-data-bind-enabled="true"';

            if (!empty($this->liveUpdateDataSource)) { // Changed from liveUpdateWidgetSource
                $data_attributes .= ' data-hidden-data-bind-data-source="' . esc_attr($this->liveUpdateDataSource) . '"';
            }

            if ($this->liveUpdateDataSource === 'organization' && !empty($this->liveUpdateOrganizationUuid)) { // New
                $data_attributes .= ' data-hidden-data-bind-organization-uuid="' . esc_attr($this->liveUpdateOrganizationUuid) . '"';
            }

            if (!empty($this->liveUpdateSchemaSlug)) { // Changed from liveUpdateSchemaKey
                $data_attributes .= ' data-hidden-data-bind-schema-slug="' . esc_attr($this->liveUpdateSchemaSlug) . '"';
            }

            if (isset($this->liveUpdateValueKey)) {
                $data_attributes .= ' data-hidden-data-bind-value-key="' . esc_attr($this->liveUpdateValueKey) . '"';
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
            if ($data_source === 'person_addinfo') {
                // Check if wicket helper function exists
                if (!function_exists('wicket_current_person_uuid')) {
                    // $logger = wc_get_logger();
                    // $logger->error('wicket_current_person_uuid function not found', ['source' => 'wicket-gf']);
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
                    // $logger = wc_get_logger();
                    // $logger->debug('Processing included items for person_addinfo schemas', ['source' => 'wicket-gf', 'count' => count($included_items_array)]);

                    foreach ($included_items_array as $item) {
                        $item_arr = is_object($item) ? (array) $item : $item;

                        if (isset($item_arr['type']) && $item_arr['type'] === 'json_schemas') {
                            $attributes = $item_arr['attributes'] ?? null;
                            if (is_array($attributes)) {
                                $identifier = $attributes['slug'] ?? $attributes['key'] ?? null;
                                if ($identifier) {
                                    $title = null;

                                    // Log the full attributes for debugging
                                    // $logger->debug('Processing schema item', [
                                    //     'source' => 'wicket-gf',
                                    //     'identifier' => $identifier,
                                    //     'attributes_keys' => array_keys($attributes),
                                    //     'full_attributes' => $attributes // Temporarily add full dump
                                    // ]);

                                    // PRIORITY 1: Try to extract title from ui_schema (most user-friendly)
                                    $ui_schema = $attributes['ui_schema'] ?? null;
                                    if (is_array($ui_schema)) {
                                        if (isset($ui_schema['ui:i18n']['title']['en'])) {
                                            $title = $ui_schema['ui:i18n']['title']['en'];
                                            // $logger->debug('Found title in ui_schema.ui:i18n.title.en', ['source' => 'wicket-gf', 'title' => $title]);
                                        } elseif (isset($ui_schema['title'])) {
                                            $title = $ui_schema['title'];
                                            // $logger->debug('Found title in ui_schema.title', ['source' => 'wicket-gf', 'title' => $title]);
                                        } elseif (isset($ui_schema['ui:title'])) {
                                            $title = $ui_schema['ui:title'];
                                            // $logger->debug('Found title in ui_schema.ui:title', ['source' => 'wicket-gf', 'title' => $title]);
                                        }
                                    }

                                    // PRIORITY 2: Try to extract title from attributes directly
                                    if (!$title && isset($attributes['title'])) {
                                        $title = $attributes['title'];
                                        // $logger->debug('Found title in attributes.title', ['source' => 'wicket-gf', 'title' => $title]);
                                    }

                                    // PRIORITY 3: Try to extract title from attributes.name
                                    if (!$title && isset($attributes['name'])) {
                                        $title = $attributes['name'];
                                        // $logger->debug('Found title in attributes.name', ['source' => 'wicket-gf', 'title' => $title]);
                                    }

                                    // PRIORITY 4: Try label field
                                    if (!$title && isset($attributes['label'])) {
                                        $title = $attributes['label'];
                                        // $logger->debug('Found title in attributes.label', ['source' => 'wicket-gf', 'title' => $title]);
                                    }

                                    // PRIORITY 5: Try to extract title from schema definition (fallback only)
                                    if (!$title) {
                                        $schema_def = $attributes['schema'] ?? null;
                                        if (is_array($schema_def)) {
                                            if (isset($schema_def['title']) && $schema_def['title'] !== $identifier) {
                                                // Only use schema title if it's different from the identifier
                                                $title = $schema_def['title'];
                                                // $logger->debug('Found title in schema.title', ['source' => 'wicket-gf', 'title' => $title]);
                                            } elseif (isset($schema_def['description'])) {
                                                $title = $schema_def['description'];
                                                // $logger->debug('Found title in schema.description', ['source' => 'wicket-gf', 'title' => $title]);
                                            }
                                        }
                                    }

                                    // Enhanced fallback: create better human-readable names from slugs
                                    if (!$title) {
                                        $title = ucwords(str_replace(['_', '-'], ' ', $identifier));
                                        // $logger->debug('Using fallback title', ['source' => 'wicket-gf', 'title' => $title]);
                                    }

                                    $options[$identifier] = $title;
                                    // $logger->debug('Added schema option', ['source' => 'wicket-gf', 'identifier' => $identifier, 'title' => $title]);
                                }
                            }
                        }
                    }

                    // $logger->debug('Final options for person_addinfo', ['source' => 'wicket-gf', 'options' => $options]);
                }
            } elseif ($data_source === 'person_profile') {
                // Check if wicket helper function exists
                if (!function_exists('wicket_current_person_uuid')) {
                    // $logger = wc_get_logger();
                    // $logger->error('wicket_current_person_uuid function not found for person_profile', ['source' => 'wicket-gf']);
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

                // $logger = wc_get_logger();
                // $logger->debug('Person data response type: ' . gettype($person_data_response), ['source' => 'wicket-gf']);

                // if (is_object($person_data_response)) {
                //     $logger->debug('Person response class: ' . get_class($person_data_response), ['source' => 'wicket-gf']);
                //     $logger->debug('Person response methods: ' . print_r(get_class_methods($person_data_response), true), ['source' => 'wicket-gf']);
                // }

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

                // Check for relationships and add collection options
                if ($person_data && isset($person_data['relationships'])) {
                    $relationships = $person_data['relationships'];

                    $relationship_mappings = [
                        'organizations' => 'Organizations',
                        'addresses' => 'Addresses',
                        'emails' => 'Emails',
                        'phones' => 'Phones',
                        'web_addresses' => 'Web Addresses',
                    ];

                    foreach ($relationship_mappings as $rel_key => $label) {
                        if (isset($relationships[$rel_key]['data']) && !empty($relationships[$rel_key]['data'])) {
                            $options['profile_' . $rel_key] = $label;
                        }
                    }
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
            if ($data_source === 'person_addinfo') {
                // Check if wicket helper function exists
                if (!function_exists('wicket_current_person_uuid')) {
                    // $logger = wc_get_logger();
                    // $logger->error('wicket_current_person_uuid function not found in value_keys', ['source' => 'wicket-gf']);
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
                    // Fetch person data to get available primary address attributes dynamically
                    $person_uuid = wicket_current_person_uuid();
                    if (empty($person_uuid)) {
                        wp_send_json_error('Could not retrieve current person UUID.');

                        return;
                    }

                    $person_data_response = wicket_get_person_by_id($person_uuid, 'addresses');
                    if (!$person_data_response || is_wp_error($person_data_response)) {
                        wp_send_json_error('Failed to fetch person profile data.');

                        return;
                    }

                    // Extract included address data using same pattern as schemas
                    $included_items_array = null;
                    if (is_object($person_data_response)) {
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

                        if (is_null($included_items_array) && property_exists($person_data_response, 'included')) {
                            $included_data_prop = $person_data_response->included;
                            if ($included_data_prop instanceof Illuminate\Support\Collection) {
                                $included_items_array = $included_data_prop->all();
                            } elseif (is_array($included_data_prop)) {
                                $included_items_array = $included_data_prop;
                            }
                        }

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
