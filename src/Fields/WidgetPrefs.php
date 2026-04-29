<?php

declare(strict_types=1);

namespace WicketGF\Fields;

if (!defined('ABSPATH')) {
    exit;
}

class WidgetPrefs extends \GF_Field
{
    public $type = 'wicket_widget_prefs';

    public static function init(): void
    {
        add_action('gform_enqueue_scripts', [static::class, 'enqueue_validation_scripts'], 10, 2);
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Person Preferences', 'wicket-gf');
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
            'wicket_widget_person_prefs_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.wwidget_prefs_hide_comm = false;
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public static function custom_settings($position, $form_id): void
    {
        if ($position == 25) {
            ob_start(); ?>

<li class="wicket_widget_person_prefs_setting field_setting" style="display:none;margin-bottom: 10px;">
    <input onchange="SetFieldProperty('wwidget_prefs_hide_comm', this.checked)"
        type="checkbox" id="wwidget_prefs_hide_comm" class="wwidget_prefs_hide_comm">
    <label for="wwidget_prefs_hide_comm" class="inline">Disable communication preferences?</label>
</li>

<script type='text/javascript'>
    jQuery(document).ready(function($) {
        $(document).on('gform_load_field_settings', function(event, field) {
            if (field.type !== 'wicket_widget_prefs') {
                return;
            }
            $('#wwidget_prefs_hide_comm').prop('checked', field.wwidget_prefs_hide_comm || false);
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
        $hide_comm_prefs = false;

        foreach ($form['fields'] as $field) {
            if ($field instanceof self && $field->id == $id && isset($field->wwidget_prefs_hide_comm)) {
                $hide_comm_prefs = (bool) $field->wwidget_prefs_hide_comm;
            }
        }

        $person_id = $this->get_user_mdp_uuid(rgar($entry, 'created_by')) ?? '';

        if (component_exists('widget-prefs-person')) {
            ob_start();
            get_component('widget-prefs-person', [
                'classes'                     => [],
                'hide_comm_prefs'             => $hide_comm_prefs,
                'preferences_data_field_name' => 'wicket_prefs_data_' . $id,
                'person_id'                   => $person_id,
            ], true);

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . ob_get_clean() . '</div>';
        }

        return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Widget-prefs-person component is missing. Please update the Wicket Base Plugin.</p></div>';
    }

    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $user_id = $this->get_user_mdp_uuid(rgar($lead, 'created_by')) ?? '';
        $wicket_settings = get_wicket_settings();

        return $wicket_settings['wicket_admin'] . '/people/' . $user_id . '/preferences';
    }

    public function validate($value, $form): void
    {
        // Preferences widget does not require validation
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
            'enableLogging'      => defined('WP_ENV') && in_array(WP_ENV, ['development', 'staging'], true),
            'enableAutoDetection'=> true,
            'debugMode'          => defined('WP_ENV') && WP_ENV === 'development',
        ]);
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
}
