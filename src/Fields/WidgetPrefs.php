<?php

declare(strict_types=1);

namespace WicketGF\Fields;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wicket Widget: Person Preferences.
 *
 * Renders the MDP editPersonPreferences widget through the base plugin's
 * widget-prefs-person component, configured by the wwidget_prefs_mdp_json_config
 * field setting.
 *
 * @see atlas://plans/feature-widget-prefs-json-config.md
 */
class WidgetPrefs extends \GF_Field
{
    /**
     * Base plugin capability: widget-prefs-person accepts widget_config.
     */
    public const BASE_CAPABILITY = 'base-plugin.components.widget_prefs_person_config';

    public $type = 'wicket_widget_prefs';

    public $wwidget_prefs_mdp_json_config = '';

    public static function init(): void
    {
        add_action('gform_enqueue_scripts', [static::class, 'enqueue_validation_scripts'], 10, 2);
        add_filter('gform_form_post_get_meta', [static::class, 'migrate_legacy_settings']);
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
                field.wwidget_prefs_mdp_json_config = '';
                field.wwidget_prefs_hide_comm = false;
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<p>Widget will show here on the frontend</p>';
        }

        $id = (int) $this->id;
        $source = $this;
        foreach ($form['fields'] as $field) {
            if ($field instanceof self && $field->id == $id) {
                $source = $field;
            }
        }

        $widget_config = $source->resolve_config();
        $person_id = $this->get_user_mdp_uuid(rgar($entry, 'created_by')) ?? '';

        if (component_exists('widget-prefs-person')) {
            ob_start();
            get_component('widget-prefs-person', [
                'classes'                     => [],
                // An old base plugin ignores widget_config but still applies this.
                'hide_comm_prefs'             => !empty($widget_config['hideCommunicationPreferences']),
                'widget_config'               => $widget_config,
                'preferences_data_field_name' => 'wicket_prefs_data_' . $id,
                'person_id'                   => $person_id,
            ], true);

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . ob_get_clean() . '</div>';
        }

        return '<div class="gform-theme__disable gform-theme__disable-reset"><p>' . __('Widget-prefs-person component is missing. Please update the Wicket Base Plugin.', 'wicket-gf') . '</p></div>';
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

    /**
     * Store the JSON config as entered, or empty when it is invalid or list-shaped.
     *
     * Not run through wp_kses_post: the value is only decoded and re-encoded
     * into the widget init, never echoed as HTML.
     */
    public function sanitize_settings()
    {
        parent::sanitize_settings();

        $raw = is_string($this->wwidget_prefs_mdp_json_config) ? $this->wwidget_prefs_mdp_json_config : '';
        if (trim($raw) !== '' && self::decode_config($raw) === [] && trim($raw) !== '{}') {
            \Wicket()->log()->debug(
                'Person Preferences Widget: invalid MDP Widget Config JSON saved',
                ['source' => 'gravityforms-state-debug', 'value' => $raw, 'error' => json_last_error_msg()]
            );
            $raw = '';
        }

        $this->wwidget_prefs_mdp_json_config = $raw;
    }

    // -------------------------------------------------------------------------
    // Field settings
    // -------------------------------------------------------------------------

    public static function custom_settings($position, $form_id): void
    {
        if ($position == 25) {
            ob_start();
            if (self::base_supports_config()) {
                self::render_config_setting();
            } else {
                self::render_legacy_setting();
            }
            echo ob_get_clean();
        }
    }

    /**
     * MDP Widget Config (JSON) setting, shown when the base plugin can apply it.
     */
    private static function render_config_setting(): void
    {
        ?>

<li class="wicket_widget_person_prefs_setting field_setting" style="display:none;margin-bottom: 10px;">
    <div>
        <label>MDP Widget Config (JSON):</label>
        <textarea id="wwidget_prefs_mdp_json_config_input" onkeyup="SetFieldProperty('wwidget_prefs_mdp_json_config', this.value)" placeholder="{&#10;  &quot;hidePrimaryLanguage&quot;: true&#10;}"></textarea>
        <p class="wwidget_prefs_mdp_json_config_error" style="display:none; margin-top: 2px; color: #d63638;"><em>Invalid JSON. Correct the syntax before saving.</em></p>
        <p style="margin-top: 2px;"><em>This expects JSON data for configuring the widget, supporting all options documented for the MDP JS Widget (<code>hidePrimaryLanguage</code>, <code>hideCommunicationPreferences</code>, <code>primaryLanguageLabel</code>, and more). Please do not modify unless you know what you are doing.</em></p>
        <p style="margin-top: 2px;"><em>Note: <code>rootEl</code>, <code>apiRoot</code>, <code>accessToken</code>, <code>personId</code>, and <code>lang</code> are always set automatically and any value provided for them here is ignored.</em></p>
        <p style="margin-top: 2px;"><em>See <a href="https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme-staging.html#editpersonpreferences" target="_blank">full documentation for MDP JS Widgets</a>.</em></p>
    </div>
</li>

<script type='text/javascript'>
    jQuery(document).ready(function($) {
        function validatePrefsJson(value) {
            var $err = $('.wwidget_prefs_mdp_json_config_error');
            var $ta = $('#wwidget_prefs_mdp_json_config_input');
            if (!value || !value.trim()) {
                $err.hide();
                $ta.removeClass('wicket-mdp-json-invalid');
                return;
            }
            try {
                JSON.parse(value);
                $err.hide();
                $ta.removeClass('wicket-mdp-json-invalid');
            } catch (e) {
                $err.show();
                $ta.addClass('wicket-mdp-json-invalid');
            }
        }

        $(document).on('gform_load_field_settings', function(event, field) {
            if (field.type !== 'wicket_widget_prefs') {
                return;
            }

            var configVal = field.wwidget_prefs_mdp_json_config || '';
            $('#wwidget_prefs_mdp_json_config_input').val(configVal);
            validatePrefsJson(configVal);

            var sel = '#wwidget_prefs_mdp_json_config_input';
            if (!$(sel).data('bound')) {
                $(sel).on('input.wicket-prefs change.wicket-prefs', function() {
                    SetFieldProperty('wwidget_prefs_mdp_json_config', this.value);
                    validatePrefsJson(this.value);
                }).data('bound', true);
            }
        });
    });
</script>

<?php
    }

    public static function editor_script(): void
    {
        // JavaScript embedded in custom_settings()
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
            'i18n'               => wicket_gf_get_frontend_i18n_strings(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Widget options for this field: the JSON config, or the legacy setting
     * when the config is empty, invalid or list-shaped. With an older base
     * plugin the checkbox is the editor setting, so a saved checkbox wins.
     *
     * @return array<string, mixed>
     */
    public function resolve_config(): array
    {
        if (!self::base_supports_config() && isset($this->wwidget_prefs_hide_comm)) {
            return self::legacy_to_config($this->wwidget_prefs_hide_comm);
        }

        $config = self::decode_config($this->wwidget_prefs_mdp_json_config ?? '');

        return $config !== [] ? $config : self::legacy_to_config($this->wwidget_prefs_hide_comm ?? false);
    }

    /**
     * Decode a saved JSON config. Empty, invalid or list-shaped JSON returns [].
     *
     * @param mixed $raw Saved setting value.
     * @return array<string, mixed>
     */
    public static function decode_config(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && $decoded !== [] && !array_is_list($decoded) ? $decoded : [];
    }

    /**
     * True when the base widget-prefs-person component accepts widget_config.
     */
    private static function base_supports_config(): bool
    {
        return function_exists('wicket_supports') && wicket_supports(self::BASE_CAPABILITY);
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

    // -------------------------------------------------------------------------
    // Legacy support
    //
    // Forms saved before the JSON config have a wwidget_prefs_hide_comm
    // checkbox. With a base plugin that supports widget_config, every form
    // load moves it into the JSON config in memory, so the form editor shows
    // it there and the next form save (editor, form duplication, or import)
    // stores the JSON and drops the old value. With an older base plugin, the
    // checkbox stays the editor setting and nothing migrates. Remove this
    // section once every site runs a base release with widget_config.
    // -------------------------------------------------------------------------

    /**
     * Original "Disable communication preferences?" setting, shown while the
     * base plugin cannot apply the JSON config.
     */
    private static function render_legacy_setting(): void
    {
        ?>

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
    }

    /**
     * Map the legacy wwidget_prefs_hide_comm value to widget options.
     *
     * @param mixed $hide_comm Saved checkbox value.
     * @return array<string, bool>
     */
    public static function legacy_to_config(mixed $hide_comm): array
    {
        return $hide_comm ? ['hideCommunicationPreferences' => true] : [];
    }

    /**
     * Move the legacy checkbox into the JSON config on every form load, when
     * the base plugin can apply the JSON.
     *
     * A valid JSON config wins and the checkbox is dropped. An empty, invalid
     * or list-shaped config is replaced by the migrated checkbox, if it was on.
     *
     * @param array $form Gravity Forms form.
     * @return array
     */
    public static function migrate_legacy_settings($form)
    {
        if (!is_array($form) || empty($form['fields']) || !is_array($form['fields']) || !self::base_supports_config()) {
            return $form;
        }

        foreach ($form['fields'] as $field) {
            if (!$field instanceof self || !isset($field->wwidget_prefs_hide_comm)) {
                continue;
            }

            if (self::decode_config($field->wwidget_prefs_mdp_json_config ?? '') === []) {
                $legacy = self::legacy_to_config($field->wwidget_prefs_hide_comm);
                if ($legacy !== []) {
                    $field->wwidget_prefs_mdp_json_config = (string) wp_json_encode($legacy, JSON_PRETTY_PRINT);
                }
            }

            unset($field->wwidget_prefs_hide_comm);
        }

        return $form;
    }
}
