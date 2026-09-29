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
    /**
     * Default required resources for new org-profile fields. MUST be strict
     * JSON (quoted keys): the base-plugin widget-profile-org component
     * json_decode()s this value before re-encoding it into the widget init
     * call, so unquoted JS-object-literal keys silently decode to null and
     * the widget enforces nothing.
     */
    private const DEFAULT_REQUIRED_RESOURCES = '{"addresses": "mailing", "emails": "work", "phones": "work", "webAddresses": "website"}';

    /** Human labels for resource keys the widget can report as incomplete. */
    private const INCOMPLETE_RESOURCE_LABELS = [
        'addresses'    => 'an address',
        'emails'       => 'an email address',
        'phones'       => 'a phone number',
        'webAddresses' => 'a web address',
    ];
    public $wwidget_org_profile_uuid = '';
    public $wwidget_org_profile_required_resources = '';

    /** @deprecated Superseded by wwidget_org_profile_mdp_json_config; kept for legacy saved forms. */
    public $wwidget_org_profile_mdp_json_fields = '';
    public $wwidget_org_profile_mdp_json_config = '';

    public static function init(): void
    {
        add_action('gform_enqueue_scripts', [static::class, 'enqueue_validation_scripts'], 10, 2);
    }

    public function get_default_properties()
    {
        $defaults = parent::get_default_properties();
        $defaults['wwidget_org_profile_uuid'] = '';
        $defaults['wwidget_org_profile_required_resources'] = self::DEFAULT_REQUIRED_RESOURCES;
        $defaults['wwidget_org_profile_mdp_json_fields'] = '';
        $defaults['wwidget_org_profile_mdp_json_config'] = '';

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

        $default_required = self::DEFAULT_REQUIRED_RESOURCES;
        if (empty($this->wwidget_org_profile_required_resources)) {
            $this->wwidget_org_profile_required_resources = $default_required;
        } else {
            $raw = wp_kses_post((string) $this->wwidget_org_profile_required_resources);
            $this->wwidget_org_profile_required_resources = $raw !== '' ? $raw : $default_required;
        }

        // Deprecated: wwidget_org_profile_mdp_json_fields is superseded by
        // wwidget_org_profile_mdp_json_config (see get_field_input()); kept working
        // for existing saved forms only.
        if (empty($this->wwidget_org_profile_mdp_json_fields)) {
            $this->wwidget_org_profile_mdp_json_fields = '';
        } else {
            $raw = wp_kses_post((string) $this->wwidget_org_profile_mdp_json_fields);
            $this->wwidget_org_profile_mdp_json_fields = $raw;
            if ($raw !== '' && trim($raw) !== '') {
                json_decode($raw);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    \Wicket()->log()->debug(
                        'Profile Org Widget: invalid MDP JSON Fields saved',
                        ['source' => 'gravityforms-state-debug', 'value' => $raw, 'error' => json_last_error_msg()]
                    );
                    // Fail closed: malformed JSON is never stored. Consumers
                    // see the empty default instead of a value they cannot
                    // decode (WWID-2665).
                    $this->wwidget_org_profile_mdp_json_fields = '';
                }
            }
        }

        if (empty($this->wwidget_org_profile_mdp_json_config)) {
            $this->wwidget_org_profile_mdp_json_config = '';
        } else {
            // Not run through wp_kses_post: this value is only ever
            // json_decode()'d then re-encoded via json_encode() into the
            // widget's JS init call (see the base-plugin components), never
            // echoed as raw HTML. Stripping tags as if this were markup
            // corrupts legitimate JSON string values containing < or >.
            $raw = (string) $this->wwidget_org_profile_mdp_json_config;
            $this->wwidget_org_profile_mdp_json_config = $raw;
            if ($raw !== '' && trim($raw) !== '') {
                json_decode($raw);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    \Wicket()->log()->debug(
                        'Profile Org Widget: invalid MDP Widget Config JSON saved',
                        ['source' => 'gravityforms-state-debug', 'value' => $raw, 'error' => json_last_error_msg()]
                    );
                    // Fail closed: malformed JSON is never stored. Consumers
                    // see the empty default instead of a value they cannot
                    // decode (WWID-2665).
                    $this->wwidget_org_profile_mdp_json_config = '';
                }
            }
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
                field.wwidget_org_profile_required_resources = %s;
                field.wwidget_org_profile_mdp_json_fields = '';
                field.wwidget_org_profile_mdp_json_config = '';
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title()),
            json_encode(self::DEFAULT_REQUIRED_RESOURCES)
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
        <label>MDP Widget Config (JSON):</label>
        <textarea id="wwidget_org_profile_mdp_json_config_input" onkeyup="SetFieldProperty('wwidget_org_profile_mdp_json_config', this.value)" placeholder="{&#10;  &quot;fields&quot;: {...},&#10;  &quot;sections&quot;: {...}&#10;}"></textarea>
        <p class="wwidget_org_profile_mdp_json_config_error" style="display:none; margin-top: 2px; color: #d63638;"><em>Invalid JSON. Correct the syntax before saving.</em></p>
        <p style="margin-top: 2px;"><em>JSON syntax is checked only. Valid JSON does not guarantee the keys and values are valid for your MDP instance. Check resource types and field keys against the widget documentation.</em></p>
        <p style="margin-top: 2px;"><em>This expects JSON data for configuring the widget, supporting all options documented for the MDP JS Widget (<code>fields</code>, <code>sections</code>, <code>resourceLimits</code>, <code>resourcePermissions</code>, and more). Please do not modify unless you know what you are doing.</em></p>
        <p style="margin-top: 2px;"><em>Note: <code>rootEl</code>, <code>apiRoot</code>, <code>accessToken</code>, and <code>orgId</code> are always set automatically and any value provided for them here is ignored.</em></p>
        <p style="margin-top: 2px;"><em>See <a href="https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme-staging.html#editorganizationprofile" target="_blank">full documentation for MDP JS Widgets</a>.</em></p>
    </div>
</li>

<li class="wicket_widget_profile_org_setting field_setting" style="display:none;">
    <div>
        <label>Required Resources:</label>
        <textarea id="wwidget_org_profile_required_resources_input" onkeyup="SetFieldProperty('wwidget_org_profile_required_resources', this.value)" type="text" ></textarea>
        <p class="wwidget_org_profile_required_resources_error" style="display:none; margin-top: 2px; color: #d63638;"><em>Invalid JSON. Correct the syntax before saving.</em></p>
        <p style="margin-top: 2px;"><em>JSON syntax is checked only. Valid JSON does not guarantee the keys and values are valid for your MDP instance. Check resource types and field keys against the widget documentation.</em></p>
        <p style="margin-top: 2px;"><em>Strict JSON only (object keys must be quoted). Example: {"addresses": "work", "phones": ["mobile", "work"]}</em></p>
        <p style="margin-top: 2px;"><em>See <a href="https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme-staging.html" target="_blank">full documentation for MDP JS Widgets</a>.</em></p>
    </div>
</li>

<li class="wicket_widget_profile_org_setting field_setting" id="wwidget_org_profile_mdp_json_fields_li" style="display:none;">
    <div>
        <label>MDP JSON Fields (Deprecated):</label>
        <textarea id="wwidget_org_profile_mdp_json_fields_input" onkeyup="SetFieldProperty('wwidget_org_profile_mdp_json_fields', this.value)" placeholder='{"legalName": {"hidden": true}}'></textarea>
        <p class="wwidget_org_profile_mdp_json_error" style="display:none; margin-top: 2px; color: #d63638;"><em>Invalid JSON. Correct the syntax before saving.</em></p>
        <p style="margin-top: 2px;"><em>JSON syntax is checked only. Valid JSON does not guarantee the keys and values are valid for your MDP instance. Check resource types and field keys against the widget documentation.</em></p>
        <p style="margin-top: 2px;"><em>JSON object passed to the widget's <code>fields</code> property to control per-field behaviour (e.g. <code>{"legalName": {"hidden": true}}</code>). Superseded by the MDP Widget Config setting above &mdash; if that is set, this value is ignored entirely.</em></p>
        <p style="margin-top: 2px;"><em><a href="#" id="wwidget_org_profile_mdp_json_migrate_link">Replace "fields" in MDP Widget Config &rarr;</a></em></p>
        <p style="margin-top: 2px;"><em>See <a href="https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme-staging.html#editorganizationprofile" target="_blank">full documentation for MDP JS Widgets</a>.</em></p>
    </div>
</li>

<script type='text/javascript'>
jQuery(document).ready(function($) {
    var defaultRequired = <?php echo json_encode(self::DEFAULT_REQUIRED_RESOURCES); ?>;

    function validateOrgMdpJson(value, $err, $ta) {
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

    // Once the legacy MDP JSON Fields value has been migrated (or was never
    // set), hide the whole setting row — nothing left to show or edit there.
    function toggleOrgLegacyFieldVisibility(value) {
        var $li = $('#wwidget_org_profile_mdp_json_fields_li');
        if (!value || !value.trim()) {
            $li.hide();
        } else {
            $li.show();
        }
    }

    // The "Replace \"fields\" in MDP Widget Config" migrate link's click handler
    // is temporary migration scaffolding and lives in its own file — see
    // assets/js/wicket_gf_widget_config_migration.js. This file only owns the
    // legacy field's own hide-when-empty behavior and its input bindings.

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
        validateOrgMdpJson(field.wwidget_org_profile_required_resources || '', $('.wwidget_org_profile_required_resources_error'), $('#wwidget_org_profile_required_resources_input'));

        var rrSel = '#wwidget_org_profile_required_resources_input';
        if (!$(rrSel).data('bound')) {
            $(rrSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_required_resources', this.value);
                validateOrgMdpJson(this.value, $('.wwidget_org_profile_required_resources_error'), $(this));
            }).data('bound', true);
        }

        var uuidSel = '#wwidget_org_profile_uuid_input';
        if (!$(uuidSel).data('bound')) {
            $(uuidSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_uuid', this.value);
            }).data('bound', true);
        }

        var mdpVal = field.wwidget_org_profile_mdp_json_fields || '';
        $('#wwidget_org_profile_mdp_json_fields_input').val(mdpVal);
        validateOrgMdpJson(mdpVal, $('.wwidget_org_profile_mdp_json_error'), $('#wwidget_org_profile_mdp_json_fields_input'));
        toggleOrgLegacyFieldVisibility(mdpVal);

        var configVal = field.wwidget_org_profile_mdp_json_config || '';
        $('#wwidget_org_profile_mdp_json_config_input').val(configVal);
        validateOrgMdpJson(configVal, $('.wwidget_org_profile_mdp_json_config_error'), $('#wwidget_org_profile_mdp_json_config_input'));

        var mdpSel = '#wwidget_org_profile_mdp_json_fields_input';
        if (!$(mdpSel).data('bound')) {
            $(mdpSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_mdp_json_fields', this.value);
                validateOrgMdpJson(this.value, $('.wwidget_org_profile_mdp_json_error'), $(this));
                toggleOrgLegacyFieldVisibility(this.value);
            }).data('bound', true);
        }

        var configSel = '#wwidget_org_profile_mdp_json_config_input';
        if (!$(configSel).data('bound')) {
            $(configSel).on('input.wicket-profile-org change.wicket-profile-org', function() {
                SetFieldProperty('wwidget_org_profile_mdp_json_config', this.value);
                validateOrgMdpJson(this.value, $('.wwidget_org_profile_mdp_json_config_error'), $(this));
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
        if (typeof field.wwidget_org_profile_mdp_json_fields === 'undefined') {
            field.wwidget_org_profile_mdp_json_fields = '';
            SetFieldProperty('wwidget_org_profile_mdp_json_fields', '');
        }
        if (typeof field.wwidget_org_profile_mdp_json_config === 'undefined') {
            field.wwidget_org_profile_mdp_json_config = '';
            SetFieldProperty('wwidget_org_profile_mdp_json_config', '');
        }
    });
});
</script>
<style>
.wicket-mdp-json-invalid { border-color: #d63638 !important; box-shadow: 0 0 0 1px #d63638 !important; }
</style>

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
                $org_required_resources = self::DEFAULT_REQUIRED_RESOURCES;
            }

            $component_args = [
                'classes'                    => [],
                'org_info_data_field_name'   => 'input_' . $this->id,
                'validation_data_field_name' => 'input_' . $this->id . '_validation',
                'org_id'                     => $org_uuid,
                'org_required_resources'     => $org_required_resources,
            ];

            $widget_config = json_decode((string) ($this->wwidget_org_profile_mdp_json_config ?? ''), true);

            if (is_array($widget_config) && $widget_config !== [] && !array_is_list($widget_config)) {
                $component_args['widget_config'] = $widget_config;
            } else {
                // Deprecated fallback: wwidget_org_profile_mdp_json_fields only renders
                // when wwidget_org_profile_mdp_json_config is empty/invalid.
                $mdp_json_fields = json_decode((string) ($this->wwidget_org_profile_mdp_json_fields ?? ''), true);
                $component_args['fields'] = is_array($mdp_json_fields) ? $mdp_json_fields : [];
            }

            $component_output = get_component('widget-profile-org', $component_args, false);

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . '</div>';
        }

        return '<div class="gform-theme__disable gform-theme__disable-reset"><p>' . __('Widget-profile-org component is missing. Please update the Wicket Base Plugin.', 'wicket-gf') . '</p></div>';
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
        $on_next = ($target_page > 0 && $target_page > $current_page);
        $on_submit = ($target_page == 0);

        if (!$on_next && !$on_submit) {
            return;
        }

        $field_page = isset($this->pageNumber) ? (int) $this->pageNumber : 1;
        if ($field_page !== $current_page) {
            return;
        }

        $field_id = $this->id ?? null;
        $validation_flag = $field_id !== null ? rgpost('input_' . $field_id . '_validation') : null;

        if ($on_next) {
            $value_array = is_array(json_decode($value, true)) ? json_decode($value, true) : [];
            $fields_incomplete_list = $this->get_filtered_incomplete_required_fields($value_array);
            $resources_incomplete = isset($value_array['incompleteRequiredResources']) && is_array($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0;
            $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
            $required_and_empty = !empty($this->isRequired) && empty($value);
            $is_incomplete = $flag_false || count($fields_incomplete_list) > 0 || $resources_incomplete || $required_and_empty;

            if ($is_incomplete) {
                $this->failed_validation = true;
                if (!empty($this->errorMessage)) {
                    $this->validation_message = $this->errorMessage;
                } elseif ($resources_incomplete) {
                    $this->validation_message = self::incomplete_resources_message($value_array, self::field_required_resources_config($this)) ?? __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket-gf');
                } else {
                    $this->validation_message = __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket-gf');
                }
            }

            return;
        }

        $value_array = is_array(json_decode($value, true)) ? json_decode($value, true) : [];
        $flag_false = ($validation_flag === false || $validation_flag === 'false' || $validation_flag === '0');
        $flag_true = ($validation_flag === true || $validation_flag === 'true' || $validation_flag === '1');

        if ($validation_flag !== null && $flag_false) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket-gf');

            return;
        }

        if ($flag_true || empty($value_array)) {
            return;
        }

        $incomplete = $this->get_filtered_incomplete_required_fields($value_array);
        if (count($incomplete) > 0) {
            $this->failed_validation = true;
            $this->validation_message = !empty($this->errorMessage) ? $this->errorMessage : __('Please complete all required fields in the organization profile.', 'wicket-gf');

            return;
        }

        if (!empty($value_array['incompleteRequiredResources']) && count($value_array['incompleteRequiredResources']) > 0) {
            $this->failed_validation = true;
            if (!empty($this->errorMessage)) {
                $this->validation_message = $this->errorMessage;
            } else {
                $this->validation_message = self::incomplete_resources_message($value_array, self::field_required_resources_config($this)) ?? __('Please ensure the organization has at least one address, email, phone, and web address.', 'wicket-gf');
            }
        }
    }

    /**
     * Build a type-aware validation message from the same required-resources
     * config the widget enforces, naming the missing resource and its required
     * type(s) so users can act. WWID-2641: a "mailing" address requirement was
     * invisible: users saw "Addresses" fail with an address already saved.
     *
     * Returns null when no specific message can be built (no incomplete
     * resources, malformed config, or only unknown resource keys); callers
     * fall back to the generic message. All config-derived type names are
     * escaped because GF renders validation messages as raw HTML.
     */
    public static function incomplete_resources_message(array $value_array, string $required_resources_json): ?string
    {
        $incomplete = $value_array['incompleteRequiredResources'] ?? null;
        if (!is_array($incomplete) || count($incomplete) === 0) {
            return null;
        }

        $available = self::resource_requirement_clauses($required_resources_json);
        if ($available === []) {
            return null;
        }

        $clauses = [];
        foreach ($incomplete as $resource_key) {
            if (!is_string($resource_key) || !isset($available[$resource_key])) {
                continue;
            }

            $clauses[] = $available[$resource_key];
        }

        if ($clauses === []) {
            return null;
        }

        if (count($clauses) === 1) {
            return sprintf(__('The organization profile is missing %s.', 'wicket-gf'), $clauses[0]);
        }

        return sprintf(__('The organization profile is missing: %s.', 'wicket-gf'), implode(', ', $clauses));
    }

    /**
     * Map every known resource key in the config to its humanized clause
     * ("addresses" => 'an address of type "Mailing"'). Shared by the
     * validation message and the client-side banner: the auto-validation JS
     * renders incomplete resource keys itself, so the localized config hands
     * it these clauses instead of letting it print bare key labels that hide
     * the required type (WWID-2641). Type names are escaped because both
     * consumers render the text as HTML.
     */
    public static function resource_requirement_clauses(string $required_resources_json): array
    {
        $config = json_decode($required_resources_json, true);
        if (!is_array($config)) {
            return [];
        }

        $clauses = [];
        foreach ($config as $resource_key => $types) {
            if (!is_string($resource_key) || !isset(self::INCOMPLETE_RESOURCE_LABELS[$resource_key])) {
                continue;
            }

            $clauses[$resource_key] = self::clause_for_resource($resource_key, $types);
        }

        return $clauses;
    }

    /**
     * Merge every widget's required-resources config on the form into one
     * clause map for the shared banner. The banner JS keeps a flat list of
     * incomplete resource keys across all widgets (person, org), so when two
     * configured widgets demand different types for the same resource, the
     * type naming degrades to the typeless clause: a wrong typed demand is
     * more misleading than no type at all.
     *
     * @param array<int, string> $required_resources_json_list Raw JSON configs.
     * @return array<string, string>
     */
    public static function merged_resource_clauses(array $required_resources_json_list): array
    {
        $type_sets = [];
        $conflicted = [];

        foreach ($required_resources_json_list as $config_json) {
            $config = json_decode((string) $config_json, true);
            if (!is_array($config)) {
                continue;
            }

            foreach ($config as $resource_key => $types) {
                if (!is_string($resource_key) || !isset(self::INCOMPLETE_RESOURCE_LABELS[$resource_key])) {
                    continue;
                }

                if (is_string($types)) {
                    $types = [$types];
                }
                if (!is_array($types)) {
                    $types = [];
                }

                $types = array_values(array_unique(array_filter(
                    array_map(static fn ($type): string => is_string($type) ? trim($type) : '', $types),
                    static fn (string $type): bool => $type !== ''
                )));

                if (!array_key_exists($resource_key, $type_sets)) {
                    $type_sets[$resource_key] = $types;
                    continue;
                }

                // Order-insensitive equality: the display keeps the first
                // config's authored order.
                $known = $type_sets[$resource_key];
                $sorted_known = $known;
                $sorted_types = $types;
                sort($sorted_known);
                sort($sorted_types);

                if ($sorted_known !== $sorted_types) {
                    $conflicted[$resource_key] = true;
                }
            }
        }

        $clauses = [];
        foreach ($type_sets as $resource_key => $types) {
            $clauses[$resource_key] = self::clause_for_resource(
                $resource_key,
                isset($conflicted[$resource_key]) ? [] : $types
            );
        }

        return $clauses;
    }

    /** Humanized banner/message clause for one resource: label plus one phrase per required type. The MDP widget demands every listed type (OrganizationProfile.js .every()), so multiple types join with "and" and repeat the resource noun. */
    private static function clause_for_resource(string $resource_key, mixed $types): string
    {
        $clause = self::INCOMPLETE_RESOURCE_LABELS[$resource_key];

        if (is_string($types)) {
            $types = [$types];
        }

        if (is_array($types) && count($types) > 0) {
            $phrases = [];
            foreach (array_unique($types, SORT_STRING) as $type) {
                if (is_string($type) && trim($type) !== '') {
                    $phrases[] = $clause . __(' of type ', 'wicket-gf') . '"' . esc_html(self::humanize_type_slug($type)) . '"';
                }
            }

            if ($phrases !== []) {
                $clause = implode(__(' and ', 'wicket-gf'), $phrases);
            }
        }

        return $clause;
    }

    /** Every required-resources JSON on the form: org fields (with default) plus person profile fields with a custom config. */
    private static function form_required_resource_configs($form): array
    {
        $configs = [];

        foreach ($form['fields'] ?? [] as $field) {
            if ($field instanceof self) {
                $configs[] = self::field_required_resources_config($field);
                continue;
            }

            if (isset($field->wwidget_profile_required_resources)
                && is_string($field->wwidget_profile_required_resources)
                && $field->wwidget_profile_required_resources !== ''
            ) {
                $configs[] = $field->wwidget_profile_required_resources;
            }
        }

        return $configs;
    }

    /** Effective required-resources config for a field instance: admin setting, else the plugin default. */
    public static function field_required_resources_config($field = null): string
    {
        if ($field !== null
            && isset($field->wwidget_org_profile_required_resources)
            && is_string($field->wwidget_org_profile_required_resources)
            && $field->wwidget_org_profile_required_resources !== ''
        ) {
            return $field->wwidget_org_profile_required_resources;
        }

        return self::DEFAULT_REQUIRED_RESOURCES;
    }

    /** "billing_address" -> "Billing Address" (underscore-aware title case). */
    private static function humanize_type_slug(string $slug): string
    {
        $words = explode(' ', str_replace('_', ' ', trim($slug)));
        $words = array_map(static fn (string $word): string => ucfirst(strtolower($word)), $words);

        return implode(' ', array_values(array_filter($words, static fn (string $word): bool => $word !== '')));
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
        $org_field = null;
        foreach ($form['fields'] as $field) {
            if ($field instanceof self) {
                $org_field = $field;
                break;
            }
        }

        if ($org_field === null) {
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
            'i18n'                => wicket_gf_get_frontend_i18n_strings(),
        ]);

        // Four field classes localize the same handle+object name and the
        // last assignment wins execution, so per-field data cannot ride the
        // shared localize: the clause map would be clobbered whenever
        // another widget field localizes after this one. The inline (after)
        // script prints after the localize blocks and the script tag, so it
        // merges over whatever the localize race left behind (WWID-2641).
        // The map merges every configured widget on the form and degrades
        // conflicting type demands to typeless clauses: the banner list is
        // flat across widgets, so attributing one widget's types to another
        // would name a requirement it never made.
        wp_add_inline_script(
            'wicket-gf-automatic-widget-validation',
            sprintf(
                'window.WicketMDPAutoValidationConfig = Object.assign(window.WicketMDPAutoValidationConfig || {}, {resourceClauses: %s});',
                wp_json_encode(
                    self::merged_resource_clauses(self::form_required_resource_configs($form)),
                    JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
                )
            ),
            'after'
        );
    }
}
