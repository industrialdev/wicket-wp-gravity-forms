<?php

declare(strict_types=1);

namespace WicketGF\Fields;

if (!defined('ABSPATH')) {
    exit;
}

class WidgetProfileOrg extends \GF_Field
{
    public $type = 'wicket_widget_profile_org';
    private const VALIDATION_IGNORED_HIDDEN_FIELDS = ['type'];
    public $wwidget_org_profile_uuid = '';
    public $wwidget_org_profile_required_resources = '';

    public static function init(): void
    {
        add_action('gform_enqueue_scripts', [static::class, 'enqueue_validation_scripts'], 10, 2);
    }

    public function get_default_properties()
    {
        $defaults = parent::get_default_properties();
        $defaults['wwidget_org_profile_uuid'] = '';
        $defaults['wwidget_org_profile_required_resources'] = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';

        return $defaults;
    }

    public function sanitize_settings()
    {
        parent::sanitize_settings();

        if (isset($this->wwidget_org_profile_uuid)) {
            $this->wwidget_org_profile_uuid = sanitize_text_field((string) $this->wwidget_org_profile_uuid);
        } else {
            $this->wwidget_org_profile_uuid = '';
        }

        $default_required = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';
        if (empty($this->wwidget_org_profile_required_resources)) {
            $this->wwidget_org_profile_required_resources = $default_required;
        } else {
            $raw = wp_kses_post((string) $this->wwidget_org_profile_required_resources);
            $this->wwidget_org_profile_required_resources = $raw !== '' ? $raw : $default_required;
        }
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Org Profile', 'wicket-gf');
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
            'wicket_widget_profile_org_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.wwidget_org_profile_uuid = '';
                field.wwidget_org_profile_required_resources = '{ addresses: \"mailing\", emails: \"work\", phones: \"work\", webAddresses: \"website\" }';
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public static function custom_settings($position, $form_id): void
    {
        if ($position == 25) {
            ob_start(); ?>

<li class="wicket_widget_profile_org_setting field_setting" style="display:none;">
    <div>
        <label>Org UUID:</label>
        <input id="wwidget_org_profile_uuid_input" onkeyup="SetFieldProperty('wwidget_org_profile_uuid', this.value)" type="text"
            placeholder="1234-5678-9100" />
        <p style="margin-top: 2px;"><em>Tip: if using a multi-page form, and a field on a previous page will get populated with the org UUID, you can simply enter that field ID here instead.</em></p>
    </div>
</li>

<li class="wicket_widget_profile_org_setting field_setting" style="display:none;">
    <div>
        <label>Required Resources:</label>
        <textarea id="wwidget_org_profile_required_resources_input" onkeyup="SetFieldProperty('wwidget_org_profile_required_resources', this.value)" type="text" ></textarea>
        <p style="margin-top: 2px;"><em>You can pass required resources like this: { addresses: "work", phones: ["mobile", "work"] }</em></p>
        <p style="margin-top: 2px;"><em>See <a href="https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme-staging.html" target="_blank">full documentation for MDP JS Widgets</a>.</em></p>
    </div>
</li>

<script type='text/javascript'>
jQuery(document).ready(function($) {
    var defaultRequired = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';

    $(document).on('gform_load_field_settings', function(event, field) {
        if (field.type !== 'wicket_widget_profile_org') {
            return;
        }

        $('#wwidget_org_profile_uuid_input').val(field.wwidget_org_profile_uuid || '');

        if (!field.wwidget_org_profile_required_resources) {
            field.wwidget_org_profile_required_resources = defaultRequired;
            SetFieldProperty('wwidget_org_profile_required_resources', defaultRequired);
        }
        $('#wwidget_org_profile_required_resources_input').val(field.wwidget_org_profile_required_resources || '');

        var rrSel = '#wwidget_org_profile_required_resources_input';
        if (!$(rrSel).data('bound')) {
            $(rrSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_required_resources', this.value);
            }).data('bound', true);
        }

        var uuidSel = '#wwidget_org_profile_uuid_input';
        if (!$(uuidSel).data('bound')) {
            $(uuidSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_uuid', this.value);
            }).data('bound', true);
        }
    });

    $(document).on('gform_field_added', function(event, field) {
        if (field.type !== 'wicket_widget_profile_org') {
            return;
        }
        field.label = 'Wicket Widget: Org Profile';
        field.wwidget_org_profile_uuid = field.wwidget_org_profile_uuid || '';
        if (!field.wwidget_org_profile_required_resources) {
            field.wwidget_org_profile_required_resources = defaultRequired;
            SetFieldProperty('wwidget_org_profile_required_resources', defaultRequired);
        }
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

    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<p>Widget will show here on the frontend</p>';
        }

        $org_uuid = $this->wwidget_org_profile_uuid ?? '';

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

        $org_required_resources = $this->wwidget_org_profile_required_resources ?? '';

        if (component_exists('widget-profile-org')) {
            if (empty($org_required_resources)) {
                $org_required_resources = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';
            }

            $component_output = get_component('widget-profile-org', [
                'classes'                    => [],
                'org_info_data_field_name'   => 'input_' . $this->id,
                'validation_data_field_name' => 'input_' . $this->id . '_validation',
                'org_id'                     => $org_uuid,
                'org_required_resources'     => $org_required_resources,
            ], false);

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
        }

        return '<div class="gform-theme__disable gform-theme__disable-reset"><p>' . __('Widget-profile-org component is missing. Please update the Wicket Base Plugin.', 'wicket_gf') . '</p></div>';
    }

    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $org_id = '';

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (!empty($decoded['attributes']['uuid'])) {
                    $org_id = sanitize_text_field((string) $decoded['attributes']['uuid']);
                } elseif (!empty($decoded['uuid'])) {
                    $org_id = sanitize_text_field((string) $decoded['uuid']);
                }
            }
        }

        if ($org_id === '') {
            return '';
        }

        $wicket_settings = get_wicket_settings();
        $admin_base = isset($wicket_settings['wicket_admin']) ? rtrim($wicket_settings['wicket_admin'], '/') : '';
        if ($admin_base === '') {
            return '';
        }

        return $admin_base . '/organizations/' . $org_id;
    }

    public function validate($value, $form): void
    {
        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? (int) rgpost('gform_source_page_number_' . $form['id']) : 1;
        $target_page = rgpost('gform_target_page_number_' . $form['id']) ? (int) rgpost('gform_target_page_number_' . $form['id']) : 0;
        $next_clicked = rgpost('gform_save') === '1' || (rgpost('gform_next_button') !== null);
        $on_next = ($target_page > $current_page && $next_clicked);
        $on_submit = ($target_page == 0);

        if (!$on_next && !$on_submit) {
            return;
        }

        $is_multi_step = false;
        if (!empty($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $f) {
                if ((is_object($f) && isset($f->type) && $f->type === 'page') || (is_array($f) && isset($f['type']) && $f['type'] === 'page')) {
                    $is_multi_step = true;
                    break;
                }
            }
        }

        if ($on_submit && $is_multi_step) {
            return;
        }

        $field_id = $this->id ?? null;
        $validation_flag = $field_id !== null ? rgpost('input_' . $field_id . '_validation') : null;

        if ($on_next) {
            $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
            if ($flag_false) {
                $is_incomplete = true;
                if (!empty($value)) {
                    $value_array = is_array(json_decode($value, true)) ? json_decode($value, true) : [];
                    $fields_incomplete_list = $this->get_filtered_incomplete_required_fields($value_array);
                    $resources_incomplete = isset($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0;
                    $is_incomplete = count($fields_incomplete_list) > 0 || $resources_incomplete;
                }
                if ($is_incomplete) {
                    $this->failed_validation = true;
                    $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket_gf');
                }
            }

            return;
        }

        $value_array = is_array(json_decode($value, true)) ? json_decode($value, true) : [];
        $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
        $flag_true = ($validation_flag === true || $validation_flag === 'true' || $validation_flag === '1');

        if ($validation_flag !== null && $flag_false) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket_gf');

            return;
        }

        if ($flag_true || empty($value_array)) {
            return;
        }

        $incomplete = $this->get_filtered_incomplete_required_fields($value_array);
        if (count($incomplete) > 0) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please complete all required fields in the organization profile.', 'wicket_gf');

            return;
        }

        if (!empty($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket_gf');
        }
    }

    private function get_filtered_incomplete_required_fields(array $value_array): array
    {
        if (empty($value_array['incompleteRequiredFields']) || !is_array($value_array['incompleteRequiredFields'])) {
            return [];
        }

        return array_values(array_filter(
            $value_array['incompleteRequiredFields'],
            static fn ($field_key) => is_string($field_key) && !in_array($field_key, self::VALIDATION_IGNORED_HIDDEN_FIELDS, true)
        ));
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
