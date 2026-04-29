<?php

declare(strict_types=1);

namespace WicketGF\Fields;

if (!defined('ABSPATH')) {
    exit;
}

class WidgetProfile extends \GF_Field
{
    public $type = 'wicket_widget_profile_individual';
    public $wwidget_profile_required_resources = '';
    private const VALIDATION_IGNORED_HIDDEN_FIELDS = ['personType'];

    public static function init(): void
    {
        add_action('gform_enqueue_scripts', [static::class, 'enqueue_validation_scripts'], 10, 2);
    }

    public function get_default_properties()
    {
        $defaults = parent::get_default_properties();
        $defaults['wwidget_profile_required_resources'] = '';

        return $defaults;
    }

    public function sanitize_settings()
    {
        parent::sanitize_settings();

        if (empty($this->wwidget_profile_required_resources)) {
            $this->wwidget_profile_required_resources = '';
        } else {
            $raw = wp_kses_post((string) $this->wwidget_profile_required_resources);
            $this->wwidget_profile_required_resources = $raw !== '' ? $raw : '';
        }
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Profile', 'wicket-gf');
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
            'wicket_widget_profile_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.wwidget_profile_required_resources = '';
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public static function custom_settings($position, $form_id): void
    {
        if ($position == 25) {
            ob_start(); ?>

<li class="wicket_widget_profile_setting field_setting" style="display:none;">
    <div>
        <label>Required Resources:</label>
        <textarea id="wwidget_profile_required_resources_input" onkeyup="SetFieldProperty('wwidget_profile_required_resources', this.value)" type="text"></textarea>
        <p style="margin-top: 2px;"><em>You can pass required resources like this: { addresses: "work", phones: ["mobile", "work"], webAddresses: "website" }</em></p>
        <p style="margin-top: 2px;"><em>See <a href="https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme-staging.html#createpersonprofile" target="_blank">full documentation for MDP JS Widgets</a>.</em></p>
    </div>
</li>

<script type='text/javascript'>
jQuery(document).ready(function($) {
    var defaultRequired = '';

    $(document).on('gform_load_field_settings', function(event, field) {
        if (field.type !== 'wicket_widget_profile_individual') {
            return;
        }

        if (!field.wwidget_profile_required_resources) {
            field.wwidget_profile_required_resources = defaultRequired;
            SetFieldProperty('wwidget_profile_required_resources', defaultRequired);
        }
        $('#wwidget_profile_required_resources_input').val(field.wwidget_profile_required_resources || '');

        var rrSel = '#wwidget_profile_required_resources_input';
        if (!$(rrSel).data('bound')) {
            $(rrSel).on('input.wicket-profile change.wicket-profile', function() {
                SetFieldProperty('wwidget_profile_required_resources', this.value);
            }).data('bound', true);
        }
    });

    $(document).on('gform_field_added', function(event, field) {
        if (field.type !== 'wicket_widget_profile_individual') {
            return;
        }
        field.label = 'Wicket Widget: Individual Profile';
        if (!field.wwidget_profile_required_resources) {
            field.wwidget_profile_required_resources = defaultRequired;
            SetFieldProperty('wwidget_profile_required_resources', defaultRequired);
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

        $id = (int) $this->id;

        if (!component_exists('widget-profile-individual')) {
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-profile-individual component is missing. Please update the Wicket Base Plugin.</p></div>';
        }

        $component_args = [
            'classes'                    => [],
            'user_info_data_field_name'  => 'wicket_user_info_data_' . $id,
            'hidden_fields'              => ['personType'],
            'validation_data_field_name' => 'input_' . $this->id . '_validation',
            'profile_required_resources' => $this->wwidget_profile_required_resources ?? '',
            'person_id'                  => $this->get_user_mdp_uuid(rgar($entry, 'created_by')) ?? '',
        ];

        if (isset($this->org_uuid)) {
            $component_args['org_id'] = $this->org_uuid;
        }

        $component_args = apply_filters('wicket_gf_widget_profile_component_args', $component_args, $form, $this, $id);
        $component_output = get_component('widget-profile-individual', $component_args, false);
        $output = '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';

        do_action('wicket_gf_widget_profile_output_after', $output, $component_output, $form, $this, $id);

        return $output;
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

    public function get_value_submission($field_values, $get_from_post_global_var = true)
    {
        $component_field = 'wicket_user_info_data_' . $this->id;
        $legacy = 'input_' . $this->id;

        if ($get_from_post_global_var) {
            return isset($_POST[$component_field]) ? rgpost($component_field) : rgpost($legacy);
        }

        return $field_values[$component_field] ?? $field_values[$legacy] ?? '';
    }

    public function is_value_submission_empty($form_id)
    {
        $component_field = 'wicket_user_info_data_' . $this->id;
        $value = rgpost($component_field);
        if ($value !== null) {
            return empty($value);
        }

        return empty(rgpost('input_' . $this->id));
    }

    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        if (empty($value)) {
            $user_id = $this->get_user_mdp_uuid(rgar($lead, 'created_by')) ?? wicket_current_person_uuid();
        } else {
            $value_array = json_decode($value);
            $user_id = $value_array->attributes->uuid ?? wicket_current_person_uuid();
        }

        if (empty($user_id)) {
            return '';
        }

        $wicket_settings = get_wicket_settings();

        return $wicket_settings['wicket_admin'] . '/people/' . $user_id;
    }

    public function validate($value, $form): void
    {
        Wicket()->log()->debug('Profile Individual Widget validate called for field ' . $this->id, ['source' => 'gravityforms-state-debug']);
        Wicket()->log()->debug('Profile Individual Widget validate value: ' . var_export($value, true), ['source' => 'gravityforms-state-debug']);

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
                    $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please fill out all required fields in your profile.', 'wicket_gf');
                }
            }

            return;
        }

        $value_array = is_array(json_decode($value, true)) ? json_decode($value, true) : [];
        $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
        $flag_true = ($validation_flag === true || $validation_flag === 'true' || $validation_flag === '1');

        if ($validation_flag !== null && $flag_false) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please fill out all required fields in your profile.', 'wicket_gf');

            return;
        }

        if ($flag_true || empty($value_array)) {
            return;
        }

        $incomplete = $this->get_filtered_incomplete_required_fields($value_array);
        if (count($incomplete) > 0) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please fill out all required fields in your profile.', 'wicket_gf');

            return;
        }

        if (!empty($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please fill out all required fields in your profile.', 'wicket_gf');
        }
    }

    private function get_user_mdp_uuid($user_id): ?string
    {
        if ($user_id > 0) {
            $user_info = get_userdata($user_id);
            if ($user_info && isset($user_info->user_login) && isValidUuid($user_info->user_login)) {
                return $user_info->user_login;
            }
        }

        return null;
    }

    private function get_filtered_incomplete_required_fields(array $value_array): array
    {
        if (empty($value_array['incompleteRequiredFields']) || !is_array($value_array['incompleteRequiredFields'])) {
            return [];
        }

        return array_values(array_filter(
            $value_array['incompleteRequiredFields'],
            static fn ($key) => is_string($key) && !in_array($key, self::VALIDATION_IGNORED_HIDDEN_FIELDS, true)
        ));
    }
}
