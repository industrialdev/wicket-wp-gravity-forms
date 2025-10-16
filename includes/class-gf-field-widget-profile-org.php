<?php
class GFWicketFieldWidgetProfileOrg extends GF_Field
{
    public $type = 'wicket_widget_profile_org';
    // Declare custom properties so GF persists them with the field
    public $wwidget_org_profile_uuid = '';
    public $wwidget_org_profile_required_resources = '';

    /**
     * Initialize the widget field and enqueue validation scripts.
     */
    public static function init()
    {
        // Enqueue validation scripts when this widget is used
        add_action('gform_enqueue_scripts', [__CLASS__, 'enqueue_validation_scripts'], 10, 2);
    }

    // Ensure new fields have sane defaults server-side
    public function get_default_properties()
    {
        $defaults = parent::get_default_properties();
        $defaults['wwidget_org_profile_uuid'] = '';
        $defaults['wwidget_org_profile_required_resources'] = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';

        return $defaults;
    }

    // Sanitize and enforce defaults when the form is saved in the editor
    public function sanitize_settings()
    {
        parent::sanitize_settings();

        // UUID is plain text
        if (isset($this->wwidget_org_profile_uuid)) {
            $this->wwidget_org_profile_uuid = sanitize_text_field((string) $this->wwidget_org_profile_uuid);
        } else {
            $this->wwidget_org_profile_uuid = '';
        }

        // Required resources: keep as a raw string (brace/quote content) but strip tags; set default if empty
        $default_required = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';
        if (empty($this->wwidget_org_profile_required_resources)) {
            $this->wwidget_org_profile_required_resources = $default_required;
        } else {
            $raw = (string) $this->wwidget_org_profile_required_resources;
            // Remove any tags while preserving braces/quotes
            $this->wwidget_org_profile_required_resources = wp_kses_post($raw);
            if ($this->wwidget_org_profile_required_resources === '') {
                $this->wwidget_org_profile_required_resources = $default_required;
            }
        }
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Org Profile', 'wicket-gf');
    }

    // Move the field to 'advanced fields'
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
            'description_setting',
            'rules_setting',
            'error_message_setting',
            'css_class_setting',
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

    public static function custom_settings($position, $form_id)
    {
        //create settings on position 25 (right after Field Label)
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
// Use jQuery-based GF editor events for reliability (matches working field patterns)
jQuery(document).ready(function($) {
    var defaultRequired = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';

    // When settings panel loads for a field
    $(document).on('gform_load_field_settings', function(event, field) {
        if (field.type !== 'wicket_widget_profile_org') {
            return;
        }

        // Populate inputs
        $('#wwidget_org_profile_uuid_input').val(field.wwidget_org_profile_uuid || '');

        if (!field.wwidget_org_profile_required_resources) {
            field.wwidget_org_profile_required_resources = defaultRequired;
            SetFieldProperty('wwidget_org_profile_required_resources', defaultRequired);
        }
        $('#wwidget_org_profile_required_resources_input').val(field.wwidget_org_profile_required_resources || '');

        // Bind once: keep model in sync as user types
        var rrSel = '#wwidget_org_profile_required_resources_input';
        if (!$(rrSel).data('bound')) {
            $(rrSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_required_resources', this.value);
            }).data('bound', true);
        }

        // Bind once: keep UUID model in sync
        var uuidSel = '#wwidget_org_profile_uuid_input';
        if (!$(uuidSel).data('bound')) {
            $(uuidSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_uuid', this.value);
            }).data('bound', true);
        }
    });

    // When a new field is added to the form
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

    public static function editor_script()
    {
        // JavaScript now embedded in custom_settings method for better integration
    }

    // Render the field
    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<p>Widget will show here on the frontend</p>';
        }

        $org_uuid = $this->wwidget_org_profile_uuid ?? '';

        // On multi-page forms, the pre-render hook runs too late. We need to get the UUID directly from POST.
        $current_page = GFFormDisplay::get_current_page($form['id']);
        if ($current_page > 1) {
            // Find the org_uuid from the POST data of the previous page
            foreach ($form['fields'] as $field) {
                if ($field->type == 'wicket_org_search_select') {
                    // Use standard GF naming convention
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
            // If admin has not configured requiredResources, use sane defaults with valid types
            // Note: "primary" is a flag, not a type. Valid example types per widget are e.g. "work", "mailing", "website".
            if (empty($org_required_resources)) {
                $org_required_resources = '{ addresses: "mailing", emails: "work", phones: "work", webAddresses: "website" }';
            }
            // Use standard GF naming convention
            $org_info_field_name = 'input_' . $this->id;
            $org_validation_field_name = 'input_' . $this->id . '_validation';

            $component_output = get_component('widget-profile-org', [
                'classes'                    => [],
                'org_info_data_field_name'   => $org_info_field_name,
                'validation_data_field_name' => $org_validation_field_name,
                'org_id'                     => $org_uuid,
                'org_required_resources'     => $org_required_resources,
            ], false);

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
        } else {
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>' . __('Widget-profile-org component is missing. Please update the Wicket Base Plugin.', 'wicket_gf') . '</p></div>';
        }

    }

    // Override how to Save the field value
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        $org_id = '';

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Primary location used by widget payload
                if (!empty($decoded['attributes']['uuid'])) {
                    $org_id = sanitize_text_field((string) $decoded['attributes']['uuid']);
                } elseif (!empty($decoded['uuid'])) {
                    // Fallback if structure differs
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

        $link_to_org = $admin_base . '/organizations/' . $org_id;

        return $link_to_org;
    }

    public function validate($value, $form)
    {
        // Gate validation by user action: only on Next or final submit
        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? (int) rgpost('gform_source_page_number_' . $form['id']) : 1;
        $target_page = rgpost('gform_target_page_number_' . $form['id']) ? (int) rgpost('gform_target_page_number_' . $form['id']) : 0;
        $next_clicked = rgpost('gform_save') === '1' || (rgpost('gform_next_button') !== null);

        $on_next = ($target_page > $current_page && $next_clicked);
        $on_submit = ($target_page == 0); // GF uses 0 when submitting the form

        if (!$on_next && !$on_submit) {
            // Do not validate on initial load or unrelated actions
            return;
        }

        // If this is a multi-step form and we're on final submit, skip validation for this field
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
            // On Next, rely on hidden flag; double-check JSON only if flag is false
            $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
            if ($flag_false) {
                $is_incomplete = true;
                if (!empty($value)) {
                    $value_array = json_decode($value, true);
                    $value_array = is_array($value_array) ? $value_array : [];
                    $fields_incomplete = isset($value_array['incompleteRequiredFields']) && count($value_array['incompleteRequiredFields']) > 0;
                    $resources_incomplete = isset($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0;
                    $is_incomplete = ($fields_incomplete || $resources_incomplete);
                }
                if ($is_incomplete) {
                    $this->failed_validation = true;
                    $this->validation_message = !empty($this->errorMessage)
                        ? $this->errorMessage
                        /* translators: Message displayed when organization profile is incomplete */
                        : __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket_gf');
                }
            }

            // If flag true or missing, allow progression
            return;
        }

        // On final submission, perform checks but avoid false blocking when field isn't on this page
        $value_array = json_decode($value, true);
        $value_array = is_array($value_array) ? $value_array : [];

        // If the hidden flag is posted and explicitly false, block; if it's absent, don't use it to block
        $flag_false_submit = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
        if ($validation_flag !== null && $flag_false_submit) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage)
                ? $this->errorMessage
                : __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket_gf');

            return;
        }

        // If the hidden flag is explicitly true, allow submit (authoritative success from the widget)
        $flag_true_submit = ($validation_flag === true || $validation_flag === 'true' || $validation_flag === '1');
        if ($flag_true_submit) {
            return;
        }

        // If there is no JSON payload at all, allow submit (field likely not present on this page / no new data)
        if (empty($value_array)) {
            return;
        }

        if (isset($value_array['incompleteRequiredFields'])) {
            if (count($value_array['incompleteRequiredFields']) > 0) {
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                } else {
                    $this->validation_message = __('Please complete all required fields in the organization profile.', 'wicket_gf');
                }

                return;
            }
        }

        if (isset($value_array['incompleteRequiredResources'])) {
            if (count($value_array['incompleteRequiredResources']) > 0) {
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                } else {
                    $this->validation_message = __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket_gf');
                }

                return;
            }
        }

        // Fallback enforcement when widget isn't requiring specific types: ensure at least one of each resource
        $has_addresses = isset($value_array['addresses']) && is_array($value_array['addresses']) && count($value_array['addresses']) > 0;
        $has_emails = isset($value_array['emails']) && is_array($value_array['emails']) && count($value_array['emails']) > 0;
        $has_phones = isset($value_array['phones']) && is_array($value_array['phones']) && count($value_array['phones']) > 0;
        $has_webaddresses = isset($value_array['webAddresses']) && is_array($value_array['webAddresses']) && count($value_array['webAddresses']) > 0;
        if (!$has_addresses || !$has_emails || !$has_phones || !$has_webaddresses) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage)
                ? $this->errorMessage
                : _x('Please ensure the organization has at least one address, email, phone, and web address.', 'Validation message for organization profile', 'wicket_gf');

            return;
        }
    }

    /**
     * Enqueue validation scripts for MDP widgets.
     */
    public static function enqueue_validation_scripts($form, $is_ajax)
    {
        // Check if this form contains an org profile widget
        $has_org_widget = false;
        foreach ($form['fields'] as $field) {
            if ($field instanceof self) {
                $has_org_widget = true;
                break;
            }
        }

        if (!$has_org_widget) {
            return;
        }

        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $version = defined('WICKET_WP_GF_VERSION') ? WICKET_WP_GF_VERSION : '1.0.0';

        // Enqueue the validation scripts
        wp_enqueue_script(
            'wicket-gf-automatic-widget-validation',
            $plugin_url . 'assets/js/wicket-gf-automatic-widget-validation.js',
            ['jquery'],
            $version,
            true
        );

        // Pass configuration to automatic validation script
        wp_localize_script(
            'wicket-gf-automatic-widget-validation',
            'WicketMDPAutoValidationConfig',
            [
                'enableLogging' => defined('WP_ENV') && in_array(WP_ENV, ['development', 'staging'], true),
                'enableAutoDetection' => true,
                'debugMode' => defined('WP_ENV') && WP_ENV === 'development',
            ]
        );
    }
}

// Initialize the widget field
GFWicketFieldWidgetProfileOrg::init();
