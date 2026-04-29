<?php

declare(strict_types=1);

namespace WicketGF\Fields;

if (!defined('ABSPATH')) {
    exit;
}

class WidgetAdditionalInfo extends \GF_Field
{
    public $type = 'wicket_widget_ai';

    public static function init(): void
    {
        add_action('gform_enqueue_scripts', [static::class, 'enqueue_validation_scripts'], 10, 2);
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Additional Info', 'wicket-gf');
    }

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
            'admin_label_setting',
            'description_setting',
            'rules_setting',
            'error_message_setting',
            'css_class_setting',
            'visibility_setting',
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

    public static function custom_settings($position, $form_id): void
    {
        if ($position == 25) {
            ob_start(); ?>

<li class="wicket_widget_ai_setting field_setting" style="display:none;">
    <label>Additional Info Type:</label>
    <select id="ai_type_selector" onchange="SetFieldProperty('wwidget_ai_type', this.value)">
        <option value="people">People</option>
        <option value="organizations">Organizations</option>
    </select>

    <div id="ai_org_uuid_wrapper" style="display: none;">
        <label>Org UUID:</label>
        <input id="ai_org_uuid_input" onkeyup="SetFieldProperty('wwidget_ai_org_uuid', this.value)" type="text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
        <p style="margin-top: 2px;"><em>Enter the organization's UUID (recommended). Numeric field IDs are legacy and discouraged.</em></p>
        <p id="ai_org_uuid_warning" style="margin-top: 2px; color: #b32d2e; display: none;"><em>Please enter a UUID, not a numeric field ID.</em></p>
    </div>

    <label>Additional Info Schemas:</label>
    <div id="ai_schema_container"></div>
    <button id="ai_add_schema_button" style="margin-top: 10px; padding: 5px 10px;">Add Schema</button>

    <div style="margin-top: 10px;">
        <input onchange="SetFieldProperty('wwidget_ai_use_slugs', this.checked)" type="checkbox" id="ai_use_slugs" class="ai_use_slugs">
        <label for="ai_use_slugs" class="inline">Use schema slugs instead of IDs</label>
    </div>
</li>

<script type='text/javascript'>
jQuery(document).ready(function($) {
    $(document).on('gform_load_field_settings', function(event, field) {
        if (field.type !== 'wicket_widget_ai') {
            return;
        }

        window.WicketGF = window.WicketGF || {};
        window.WicketGF.AdditionalInfo = {
            schemaArray: [],

            loadFieldSettings: function(field) {
                let fieldDataSchemas = field.wwidget_ai_schemas || [[]];
                if (!fieldDataSchemas.length) {
                    fieldDataSchemas = [[]];
                }
                this.schemaArray = fieldDataSchemas;

                $('#ai_type_selector').val(field.wwidget_ai_type || 'people');
                $('#ai_org_uuid_input').val(field.wwidget_ai_org_uuid || '');
                this.toggleUuidWarning(field.wwidget_ai_org_uuid || '');
                $('#ai_use_slugs').prop('checked', field.wwidget_ai_use_slugs || false);

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

            toggleUuidWarning: function(value) {
                var warning = $('#ai_org_uuid_warning');
                if (!warning.length) {
                    return;
                }
                var trimmed = (value || '').toString().trim();
                var isNumeric = trimmed !== '' && !isNaN(trimmed);
                warning.toggle(isNumeric);
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

                if (!this.schemaArray.length) {
                    this.schemaArray = [[]];
                }

                var self = this;

                for (let i = 0; i < this.schemaArray.length; i++) {
                    let schemaGrouping = this.schemaArray[i];

                    let row = $('<div>').css({
                        'border': '1px solid #ddd',
                        'padding': '15px',
                        'margin-bottom': '10px',
                        'border-radius': '4px',
                        'background': '#f9f9f9',
                        'position': 'relative'
                    });

                    let contentArea = $('<div>').css({ 'margin-right': '60px' });
                    let buttonArea = $('<div>').css({
                        'position': 'absolute',
                        'right': '15px',
                        'top': '15px',
                        'display': 'flex',
                        'flex-direction': 'column',
                        'gap': '5px'
                    });

                    (function(index) {
                        $('<input>').attr({
                            'type': 'text',
                            'placeholder': 'Schema ID',
                            'value': schemaGrouping[0] || '',
                            'style': 'width: 100%; margin-bottom: 8px; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                        }).on('input', function() {
                            self.updateSchemaArray(index, 'schema-id', this.value);
                        }).appendTo(contentArea);

                        $('<input>').attr({
                            'type': 'text',
                            'placeholder': 'Schema override ID (optional)',
                            'value': schemaGrouping[1] || '',
                            'style': 'width: 100%; margin-bottom: 8px; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                        }).on('input', function() {
                            self.updateSchemaArray(index, 'override-id', this.value);
                        }).appendTo(contentArea);

                        $('<input>').attr({
                            'type': 'text',
                            'placeholder': 'Friendly name (optional)',
                            'value': schemaGrouping[2] || '',
                            'style': 'width: 100%; margin-bottom: 8px; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                        }).on('input', function() {
                            self.updateSchemaArray(index, 'friendly-name', this.value);
                        }).appendTo(contentArea);

                        var requiredSelect = $('<select>').attr({
                            'style': 'width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 3px;'
                        }).on('change', function() {
                            self.updateSchemaArray(index, 'show-as-required', this.value === 'required');
                        });

                        requiredSelect.append($('<option>').val('not-required').text("Don't show as required"));
                        requiredSelect.append($('<option>').val('required').text('Show as required'));
                        requiredSelect.val(schemaGrouping[3] ? 'required' : 'not-required');
                        contentArea.append(requiredSelect);

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

        window.WicketGF.AdditionalInfo.loadFieldSettings(field);

        $('#ai_type_selector').off('change.wicket-ai').on('change.wicket-ai', function() {
            window.WicketGF.AdditionalInfo.updateAiType(this.value);
        });

        $('#ai_org_uuid_input').off('input.wicket-ai-uuid change.wicket-ai-uuid').on('input.wicket-ai-uuid change.wicket-ai-uuid', function() {
            window.WicketGF.AdditionalInfo.toggleUuidWarning(this.value);
        });

        $('#ai_add_schema_button').off('click.wicket-ai').on('click.wicket-ai', function(e) {
            e.preventDefault();
            window.WicketGF.AdditionalInfo.addNewSchemaGrouping();
        });
    });
});
</script>

<?php
            echo ob_get_clean();
        }
    }

    public static function editor_script(): void
    {
        // JavaScript embedded in custom_settings()
    }

    public function get_value_submission($field_values, $get_from_post = true)
    {
        if ($get_from_post) {
            return rgpost('input_' . $this->id);
        }

        return parent::get_value_submission($field_values, $get_from_post);
    }

    public function is_value_submission_empty($form_id)
    {
        $value = $this->get_value_submission([], true);

        Wicket()->log()->debug('GF AI Widget is_value_submission_empty called for field ' . $this->id . ' with value: ' . var_export($value, true), ['source' => 'gravityforms-state-debug']);

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return true;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return false;
            }

            return false;
        }

        return parent::is_value_submission_empty($form_id);
    }

    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<p>Widget will show here on the frontend</p>';
        }

        Wicket()->log()->debug('GF AI Widget get_field_input called for field ' . $this->id, ['source' => 'gravityforms-state-debug']);

        $id = (int) $this->id;
        $ai_widget_schemas = $this->wwidget_ai_schemas ?? [[]];
        $ai_type = $this->wwidget_ai_type ?? 'people';
        $org_uuid = $this->wwidget_ai_org_uuid ?? '';
        $use_slugs = $this->wwidget_ai_use_slugs ?? false;

        $current_page = \GFFormDisplay::get_current_page($form['id']);
        if ($current_page > 1) {
            if (is_numeric($org_uuid)) {
                $field_id = (int) $org_uuid;
                $field_name = 'input_' . $field_id;
                if (!empty($_POST[$field_name])) {
                    $org_uuid = sanitize_text_field($_POST[$field_name]);
                }
            }

            foreach ($form['fields'] as $field) {
                if ($field->type == 'wicket_org_search_select') {
                    $field_name = 'input_' . $field->id;
                    if (!empty($_POST[$field_name])) {
                        $org_uuid = sanitize_text_field($_POST[$field_name]);
                        break;
                    }
                }
            }
        }

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
            $component_output = get_component(
                'widget-additional-info',
                [
                    'additional_info_data_field_name' => 'input_' . $id,
                    'validation_data_field_name'      => 'input_' . $id . '_validation',
                    'resource_type'                   => $ai_type,
                    'org_uuid'                        => $org_uuid,
                    'schemas_and_overrides'           => $cleaned_ai_widget_schemas,
                ],
                false
            );

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
        }

        return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-additional-info component is missing. Please update the Wicket Base Plugin.</p></div>';
    }

    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $user_id = wicket_current_person_uuid();
        $wicket_settings = get_wicket_settings();

        return $wicket_settings['wicket_admin'] . '/people/' . $user_id . '/additional_info';
    }

    public function validate($value, $form): void
    {
        Wicket()->log()->debug('GF AI Widget validate called', ['source' => 'gravityforms-state-debug']);

        $value_array = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (!empty($value)) {
                $this->failed_validation = true;
                $this->validation_message = 'Invalid data format submitted.';
            }

            return;
        }

        $invalid = $value_array['invalid'] ?? [];
        $validation = $value_array['validation'] ?? [];

        $has_validation_errors = false;

        if ($this->isRequired) {
            $has_validation_errors = !empty($invalid) || !empty($validation);
        } else {
            $required_schemas_with_errors = false;
            $ai_widget_schemas = $this->wwidget_ai_schemas ?? [[]];

            if (!empty($invalid)) {
                foreach ($invalid as $schema_id => $errors) {
                    foreach ($ai_widget_schemas as $schema_config) {
                        $schema_id_or_slug = $schema_config[0] ?? '';
                        $show_as_required = $schema_config[3] ?? false;

                        if ($schema_id_or_slug == $schema_id && $show_as_required) {
                            $required_schemas_with_errors = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$required_schemas_with_errors && !empty($validation)) {
                foreach ($validation as $schema_id => $schema_validation) {
                    foreach ($ai_widget_schemas as $schema_config) {
                        $schema_id_or_slug = $schema_config[0] ?? '';
                        $show_as_required = $schema_config[3] ?? false;

                        if ($schema_id_or_slug == $schema_id && $show_as_required) {
                            if (!empty($schema_validation['errors'])) {
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
        } else {
            $this->failed_validation = false;
            $this->validation_message = '';
        }
    }

    public static function enqueue_validation_scripts($form, $is_ajax): void
    {
        $has_widget = false;
        foreach ($form['fields'] as $field) {
            if ($field instanceof self) {
                $has_widget = true;
                break;
            }
        }

        if (!$has_widget) {
            return;
        }

        wp_enqueue_script(
            'wicket-gf-automatic-widget-validation',
            WICKET_GF_URL . 'assets/js/wicket-gf-automatic-widget-validation.js',
            ['jquery'],
            WICKET_GF_VERSION,
            true
        );

        wp_localize_script('wicket-gf-automatic-widget-validation', 'WicketMDPAutoValidationConfig', [
            'enableLogging'       => defined('WP_ENV') && in_array(WP_ENV, ['development', 'staging'], true),
            'enableAutoDetection' => true,
            'debugMode'           => defined('WP_ENV') && WP_ENV === 'development',
        ]);
    }
}
