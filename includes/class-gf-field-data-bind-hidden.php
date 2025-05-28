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
        // By returning 'text', we make it more likely for Gravity Forms to include this field
        // as a source for conditional logic, as 'hidden' input types are often excluded.
        // The actual rendered input will still be type='hidden' via get_field_input().
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
            'live_update_widget_source_setting' => [
                'label'   => esc_html__('Live Update: Widget Source', 'wicket-gf'),
                'tooltip' => '<h6>' . esc_html__('Widget Source', 'wicket-gf') . '</h6>' .
                    esc_html__('Select the type of Wicket Widget (e.g., Profile, Additional Info) that will provide the data. This helps target events from specific widgets if multiple are on the page.', 'wicket-gf'),
            ],
            'live_update_schema_key_setting' => [
                'type'    => 'text', // Add type hint for persistence
                'label'   => esc_html__('Live Update: Schema/Data Key', 'wicket-gf'),
                'tooltip' => '<h6>' . esc_html__('Schema/Data Key', 'wicket-gf') . '</h6>' .
                    esc_html__('Enter the schema key or data field key from the Wicket widget\'s SAVE_SUCCESS event payload (e.g., \'fte\', \'primary_email\', \'custom_fields.your_field_machine_name\').', 'wicket-gf'),
            ],
            'live_update_value_key_setting' => [
                'label'   => esc_html__('Live Update: Value Key (Optional)', 'wicket-gf'),
                'tooltip' => '<h6>' . esc_html__('Value Key (Optional)', 'wicket-gf') . '</h6>' .
                    esc_html__('If the data is nested within the Schema/Data Key, enter the sub-key to extract the specific value (e.g., if Schema Key is \'primary_email\', Value Key might be \'address\'). Leave blank or use \'value\' to use the direct value of the Schema/Data Key.', 'wicket-gf'),
            ],
        ];
    }

    public static function render_wicket_live_update_settings($position, $form_id): void
    {
        if ($position == 25) {
            ?>
            <li class="live_update_enable_setting field_setting">
                <input type="checkbox" id="liveUpdateEnabled" onclick="SetFieldProperty('liveUpdateEnabled', this.checked); toggleWicketLiveUpdateSettings(this.checked, this);" />
                <label for="liveUpdateEnabled" class="inline">
                    <?php esc_html_e('Enable Live Update', 'wicket-gf'); ?>
                    <?php gform_tooltip('live_update_enable_setting'); ?>
                </label>
            </li>

            <li class="live_update_widget_source_setting field_setting wicket-gf-dependent-setting">
                <label for="liveUpdateWidgetSource" class="section_label">
                    <?php esc_html_e('Live Update: Widget Source', 'wicket-gf'); ?>
                    <?php gform_tooltip('live_update_widget_source_setting'); ?>
                </label>
                <select id="liveUpdateWidgetSource" onchange="SetFieldProperty('liveUpdateWidgetSource', this.value);">
                    <option value=""><?php esc_html_e('Select Widget Source', 'wicket-gf'); ?></option>
                    <option value="additional_info"><?php esc_html_e('Additional Info', 'wicket-gf'); ?></option>
                    <option value="profile"><?php esc_html_e('Profile', 'wicket-gf'); ?></option>
                </select>
            </li>

            <li class="live_update_schema_key_setting field_setting wicket-gf-dependent-setting">
                <label for="liveUpdateSchemaKey" class="section_label">
                    <?php esc_html_e('Live Update: Schema/Data Key', 'wicket-gf'); ?>
                    <?php gform_tooltip('live_update_schema_key_setting'); ?>
                </label>
                <input type="text" id="liveUpdateSchemaKey" class="fieldwidth-3" onkeyup="SetFieldProperty('liveUpdateSchemaKey', this.value);" />
            </li>

            <li class="live_update_value_key_setting field_setting wicket-gf-dependent-setting">
                <label for="liveUpdateValueKey" class="section_label">
                    <?php esc_html_e('Live Update: Value Key (Optional)', 'wicket-gf'); ?>
                    <?php gform_tooltip('live_update_value_key_setting'); ?>
                </label>
                <input type="text" id="liveUpdateValueKey" class="fieldwidth-3" onkeyup="SetFieldProperty('liveUpdateValueKey', this.value);" />
            </li>
        <?php
        }
    }

    public static function editor_script(): void
    {
        ?>
        <script type="text/javascript">
            // Placed toggleWicketLiveUpdateSettings first to ensure it's defined when called.
            function toggleWicketLiveUpdateSettings(enabled, checkboxElement = null) {
                var $context = jQuery(document); // Default context
                if (checkboxElement) {
                    var $panel = jQuery(checkboxElement).closest('.gform_editor_panel_content');
                    if ($panel.length) {
                        $context = $panel;
                    } else {
                        var $ul = jQuery(checkboxElement).closest('ul');
                        if ($ul.length) $context = $ul;
                    }
                } else {
                    // Attempt to find a general context for wicket_data_hidden if no element is passed
                    var $activeWicketPanel = jQuery('#gform_fields .field_selected.gf_wicket_data_hidden_admin .settings_panel_content');
                    if ($activeWicketPanel.length) {
                        $context = $activeWicketPanel;
                    } else {
                        // Fallback if a very specific panel isn't found but an ID is available
                        var $anyLiveUpdateCheckbox = jQuery('#liveUpdateEnabled');
                        if ($anyLiveUpdateCheckbox.length) {
                            var $panelFallback = $anyLiveUpdateCheckbox.first().closest('.gform_editor_panel_content');
                            if ($panelFallback.length) $context = $panelFallback;
                            else {
                                var $ulFallback = $anyLiveUpdateCheckbox.first().closest('ul');
                                if ($ulFallback.length) $context = $ulFallback;
                            }
                        }
                    }
                }
                //console.log('GFDataBindHiddenField: toggleWicketLiveUpdateSettings context:', $context.length ? $context.get(0) : 'Not Found', 'Enabled:', enabled);
                $context.find('.wicket-gf-dependent-setting').css('display', enabled ? '' : 'none');
            }

            jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                //console.log('GFDataBindHiddenField: gform_load_field_settings event. Field Type: ' + field.type, 'Field Object:', field);

                if (field.type === 'wicket_data_hidden') {
                    //console.log('GFDataBindHiddenField: Processing settings for wicket_data_hidden.');

                    // It's possible that IDs are not unique if GF reuses templates before assigning field-specific IDs.
                    // Try to scope the search for #liveUpdateEnabled to the current field's settings area if possible.
                    // Formidable Forms, for example, wraps settings in a div with id like 'frm_field_settings_field_XYZ'
                    // Gravity Forms might have something like 'field_settings_for_FIELDID' or within #gform_selected_field_settings
                    var $fieldSpecificSettingsArea = jQuery('#gform_selected_field_settings'); // A common GF container for the selected field
                    if (!$fieldSpecificSettingsArea.length) {
                        // Fallback if the above is not found, try a broader context
                        $fieldSpecificSettingsArea = jQuery('#field_settings_tab_container .gform_editor_panel_content');
                        //console.log('GFDataBindHiddenField: Using fallback settings area:', $fieldSpecificSettingsArea.length ? $fieldSpecificSettingsArea.get(0) : 'Fallback not found');
                    }
                    if (!$fieldSpecificSettingsArea.length) {
                        $fieldSpecificSettingsArea = jQuery(document); // Absolute fallback
                        //console.warn('GFDataBindHiddenField: Could not find specific settings area, using document scope. This might lead to issues if multiple such fields exist.');
                    }

                    var $checkbox = $fieldSpecificSettingsArea.find('#liveUpdateEnabled');
                    //console.log('GFDataBindHiddenField: Checkbox #liveUpdateEnabled found in scope:', $checkbox.length ? $checkbox.get(0) : 'Not found in scope');

                    var $liveUpdateEnableLi = $checkbox.closest('li.live_update_enable_setting');
                    //console.log('GFDataBindHiddenField: li.live_update_enable_setting (closest to checkbox):', $liveUpdateEnableLi.length ? $liveUpdateEnableLi.get(0) : 'Not found');

                    if ($liveUpdateEnableLi.length) {
                        //console.log('GFDataBindHiddenField: Attempting to show li.live_update_enable_setting. Current style:', $liveUpdateEnableLi.attr('style'));
                        $liveUpdateEnableLi.css('display', ''); // Force display by removing style or setting to default
                        $liveUpdateEnableLi.show(); // jQuery's .show()
                        //console.log('GFDataBindHiddenField: li.live_update_enable_setting style after .css("display", "") and .show():', $liveUpdateEnableLi.attr('style'));
                    } else {
                        //console.error('GFDataBindHiddenField: CRITICAL - Could not find li.live_update_enable_setting to make it visible.');
                    }

                    if ($checkbox.length) {
                        $checkbox.prop('checked', field.liveUpdateEnabled || false);
                        //console.log('GFDataBindHiddenField: Checkbox #liveUpdateEnabled checked state set to:', field.liveUpdateEnabled || false);

                        // Scope other field settings to the parent of the LI or the specific settings area
                        var $settingsScope = $liveUpdateEnableLi.length ? $liveUpdateEnableLi.parent() : $fieldSpecificSettingsArea;

                        $settingsScope.find('#liveUpdateWidgetSource').val(field.liveUpdateWidgetSource || '');
                        if (typeof field.liveUpdateSchemaKey !== 'undefined') {
                            jQuery('#liveUpdateSchemaKey').val(field.liveUpdateSchemaKey);
                            //console.log('GFDataBindHiddenField: #liveUpdateSchemaKey value set from field.liveUpdateSchemaKey:', field.liveUpdateSchemaKey);
                        } else {
                            //console.log('GFDataBindHiddenField: field.liveUpdateSchemaKey is undefined.');
                        }
                        $settingsScope.find('#liveUpdateValueKey').val(field.liveUpdateValueKey || '');
                        //console.log('GFDataBindHiddenField: Dependent field values set.');

                        var liveUpdateCheckboxElement = $checkbox.get(0);
                        if (typeof toggleWicketLiveUpdateSettings === 'function') {
                            //console.log('GFDataBindHiddenField: Calling toggleWicketLiveUpdateSettings.');
                            toggleWicketLiveUpdateSettings(field.liveUpdateEnabled || false, liveUpdateCheckboxElement);
                        } else {
                            //console.error('GFDataBindHiddenField: toggleWicketLiveUpdateSettings function is undefined!');
                        }
                    } else {
                        //console.error('GFDataBindHiddenField: Checkbox #liveUpdateEnabled not found. Cannot set initial values or toggle dependents.');
                    }
                    //console.log('GFDataBindHiddenField: Finished processing settings for wicket_data_hidden.');
                }
            });
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
        $class_attribute = ''; // Initialize class attribute

        if (!empty($this->liveUpdateEnabled)) {
            $class_attribute = " class='wicket-gf-live-update-target'"; // Add the target class
            $data_attributes .= ' data-live-update-enabled="true"';
            if (!empty($this->liveUpdateWidgetSource)) {
                $data_attributes .= ' data-live-update-widget-source="' . esc_attr($this->liveUpdateWidgetSource) . '"';
            }

            if (!empty($this->liveUpdateSchemaKey)) {
                // The user is expected to enter the schema SLUG directly in the field settings.
                $data_attributes .= ' data-live-update-schema-key="' . esc_attr($this->liveUpdateSchemaKey) . '"';
            }

            if (isset($this->liveUpdateValueKey)) {
                $data_attributes .= ' data-live-update-value-key="' . esc_attr($this->liveUpdateValueKey) . '"';
            }
        }

        // Add $class_attribute to the sprintf
        return sprintf("<input name='input_%d' id='%s' type='hidden'%s value='%s'%s />", $id, $field_id, $class_attribute, $input_value, $data_attributes);
    }
}

GF_Fields::register(new GFDataBindHiddenField());
