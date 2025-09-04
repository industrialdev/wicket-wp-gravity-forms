<?php

if (!class_exists('GF_Field')) {
    die();
}

/**
 * Class GFWicketFieldOrgSearchSelect.
 *
 * The Wicket Organization Search custom field
 */
class GFWicketFieldOrgSearchSelect extends GF_Field
{
    public $type = 'wicket_org_search_select';

    public static function custom_settings($position, $form_id)
    {
        if ($position == 25) { ?>
            <?php ob_start(); ?>

            <li class="wicket_orgss_setting field_setting" style="display:none;">
                <label>Search Mode</label>
                <select name="orgss_search_mode" id="orgss_search_mode_select" onchange="SetFieldProperty('orgss_search_mode', this.value)">
                    <option value="org" selected>Organizations</option>
                    <option value="groups">Groups (Beta, In Development)</option>
                </select>

                <div id="orgss-org-settings" style="padding: 1em 0; display: block;">
                    <label style="display: block;">Organization Type</label>
                    <input onkeyup="SetFieldProperty('orgss_search_org_type', this.value)" type="text" name="orgss_search_org_type" id="orgss_search_org_type_input" />
                    <p style="margin-top: 2px;margin-bottom: 0px;"><em>If left blank, all organization types will be searchable. If
                            you wish to filter, you'll need to provide the "slug" of the organization type, e.g. "it_company".</em>
                    </p>

                    <label style="margin-top: 1em;display: block;">Relationship Type(s) Upon Org Creation/Selection</label>
                    <input onkeyup="SetFieldProperty('orgss_relationship_type_upon_org_creation', this.value)" type="text" name="orgss_relationship_type_upon_org_creation" id="orgss_relationship_type_upon_org_creation_input" />
                    <p style="margin-top: 2px;margin-bottom: 0px;"><em>This can be a single relationship, or a comma-separated list
                            of multiple relationships (in slug form) that will be created at once.</em></p>

                    <label style="margin-top: 1em;display: block;">Relationship Mode</label>
                    <input onkeyup="SetFieldProperty('orgss_relationship_mode', this.value)" type="text" name="orgss_relationship_mode" id="orgss_relationship_mode_input" />

                    <label style="margin-top: 1em;display: block;">Org Type When User Creates New Org</label>
                    <input onkeyup="SetFieldProperty('orgss_new_org_type_override', this.value)" type="text" name="orgss_new_org_type_override" id="orgss_new_org_type_override_input" />
                    <p style="margin-top: 2px;"><em>If left blank, the user will be allowed to select the organization type
                            themselves from the frontend.</em></p>

                    <label style="margin-top: 1em;display: block;">Org name singular</label>
                    <input onkeyup="SetFieldProperty('orgss_org_term_singular', this.value)" type="text" name="orgss_org_term_singular" id="orgss_org_term_singular_input" />
                    <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organization" or "Chapter". Can
                            be left blank to use default.</em></p>

                    <label style="margin-top: 1em;display: block;">Org name plural</label>
                    <input onkeyup="SetFieldProperty('orgss_org_term_plural', this.value)" type="text" name="orgss_org_term_plural" id="orgss_org_term_plural_input" />
                    <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organizations" or "Chapters".
                            Can be left blank to use default.</em></p>

                    <label style="margin-top: 1em;display: block;">No results found message</label>
                    <input onkeyup="SetFieldProperty('orgss_no_results_message', this.value)" type="text" name="orgss_no_results_message" id="orgss_no_results_message_input" />
                    <p style="margin-top: 2px;"><em>Message that will display if nothing is found by their search. Can be left blank
                            to use default.</em></p>

                    <label style="margin-top: 1em;display: block;">'New Org Created' checkbox ID</label>
                    <input onkeyup="SetFieldProperty('orgss_checkbox_id_new_org', this.value)" type="text" name="orgss_checkbox_id_new_org" id="orgss_checkbox_id_new_org_input" placeholder="E.g. choice_5_12_1" />
                    <p style="margin-top: 2px;"><em>ID of checkbox to be checked if a new org gets created.</em></p>

                    <input onchange="SetFieldProperty('orgss_disable_org_creation', this.checked)" type="checkbox" id="orgss_disable_org_creation" class="orgss_disable_org_creation">
                    <label for="orgss_disable_org_creation" class="inline">Disable ability to create new org/entity?</label>
                    <br />

                    <input onchange="SetFieldProperty('orgss_hide_remove_buttons', this.checked)" type="checkbox" id="orgss_hide_remove_buttons" class="orgss_hide_remove_buttons">
                    <label for="orgss_hide_remove_buttons" class="inline">Hide remove buttons?</label>
                    <br />

                    <input onchange="SetFieldProperty('orgss_hide_select_buttons', this.checked)" type="checkbox" id="orgss_hide_select_buttons" class="orgss_hide_select_buttons">
                    <label for="orgss_hide_select_buttons" class="inline">Hide select buttons?</label>
                    <br />

                    <input onchange="SetFieldProperty('orgss_display_removal_alert_message', this.checked)" type="checkbox" id="orgss_display_removal_alert_message" class="orgss_display_removal_alert_message">
                    <label for="orgss_display_removal_alert_message" class="inline">Display removal alert message?</label>
                    <br />

                    <input onchange="SetFieldProperty('orgss_disable_selecting_orgs_with_active_membership', this.checked);" type="checkbox" id="orgss_disable_selecting_orgs_with_active_membership" class="orgss_disable_selecting_orgs_with_active_membership">
                    <label for="orgss_disable_selecting_orgs_with_active_membership" class="inline">Disable ability to select orgs
                        with active membership?</label>
                    <br />

                    <div id="orgss_active_membership_alert_wrapper" style="margin-left:10px;margin-bottom: 10px;display: none;">
                        <label style="margin-top: 1em;display: block;">Active Membership Alert Title</label>
                        <input onkeyup="SetFieldProperty('orgss_active_membership_alert_title', this.value)" type="text" name="orgss_active_membership_alert_title" id="orgss_active_membership_alert_title_input" />

                        <label style="margin-top: 1em;display: block;">Active Membership Alert Body</label>
                        <textarea onkeyup="SetFieldProperty('orgss_active_membership_alert_body', this.value)" type="text" name="orgss_active_membership_alert_body" id="orgss_active_membership_alert_body_input">
                   </textarea>

                        <label style="margin-top: 1em;display: block;">Active Membership Button 1 Text</label>
                        <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_1_text', this.value)" type="text" name="orgss_active_membership_alert_button_1_text" id="orgss_active_membership_alert_button_1_text_input" />

                        <label style="margin-top: 1em;display: block;">Active Membership Button 1 URL</label>
                        <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_1_url', this.value)" type="text" name="orgss_active_membership_alert_button_1_url" id="orgss_active_membership_alert_button_1_url_input" />
                        <p style="margin-top: 2px;"><em>Set to PROCEED for this button to continue with the usual org selection
                                actions, or BUTTON if you're going to do something fancy with it on the backend.</em></p>

                        <label style="margin-top: 1em;display: block;">Active Membership Button 1 Style</label>
                        <select name="orgss_active_membership_alert_button_1_style" id="orgss_active_membership_alert_button_1_style_select" onchange="SetFieldProperty('orgss_active_membership_alert_button_1_style', this.value)" style="margin-bottom: 1em;">
                            <option value="primary" selected>Primary</option>
                            <option value="secondary" selected>Secondary</option>
                            <option value="ghost" selected>Ghost</option>
                        </select>

                        <input onchange="SetFieldProperty('orgss_active_membership_alert_button_1_new_tab', this.checked)" type="checkbox" id="orgss_active_membership_alert_button_1_new_tab" class="orgss_active_membership_alert_button_1_new_tab">
                        <label for="orgss_active_membership_alert_button_1_new_tab" class="inline">Open Button 1 in New Tab?</label>


                        <label style="margin-top: 1em;display: block;">Active Membership Button 2 Text</label>
                        <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_2_text', this.value)" type="text" name="orgss_active_membership_alert_button_2_text" id="orgss_active_membership_alert_button_2_text_input" />

                        <label style="margin-top: 1em;display: block;">Active Membership Button 2 URL</label>
                        <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_2_url', this.value)" type="text" name="orgss_active_membership_alert_button_2_url" id="orgss_active_membership_alert_button_2_url_input" />
                        <p style="margin-top: 2px;"><em>Set to PROCEED for this button to continue with the usual org selection
                                actions, or BUTTON if you're going to do something fancy with it on the backend.</em></p>

                        <label style="margin-top: 1em;display: block;">Active Membership Button 2 Style</label>
                        <select name="orgss_active_membership_alert_button_2_style" id="orgss_active_membership_alert_button_2_style_select" onchange="SetFieldProperty('orgss_active_membership_alert_button_2_style', this.value)" style="margin-bottom: 1em;">
                            <option value="primary" selected>Primary</option>
                            <option value="secondary" selected>Secondary</option>
                            <option value="ghost" selected>Ghost</option>
                        </select>

                        <input onchange="SetFieldProperty('orgss_active_membership_alert_button_2_new_tab', this.checked)" type="checkbox" id="orgss_active_membership_alert_button_2_new_tab" class="orgss_active_membership_alert_button_2_new_tab">
                        <label for="orgss_active_membership_alert_button_2_new_tab" class="inline">Open Button 2 in New Tab?</label>


                    </div>

                    <input onchange="SetFieldProperty('orgss_grant_roster_man_on_purchase', this.checked)" type="checkbox" id="orgss_grant_roster_man_on_purchase" class="orgss_grant_roster_man_on_purchase">
                    <label for="orgss_grant_roster_man_on_purchase" class="inline">Grant roster management (membership_manager role
                        for selected org) on next purchase?</label>
                    <br />

                    <input onchange="SetFieldProperty('orgss_grant_org_editor_on_select', this.checked)" type="checkbox" id="orgss_grant_org_editor_on_select" class="orgss_grant_org_editor_on_select">
                    <label for="orgss_grant_org_editor_on_select" class="inline">Grant org_editor role on selection (scoped to
                        selected org)?</label>
                    <br />

                    <input onchange="SetFieldProperty('orgss_grant_org_editor_on_purchase', this.checked)" type="checkbox" id="orgss_grant_org_editor_on_purchase" class="orgss_grant_org_editor_on_purchase">
                    <label for="orgss_grant_org_editor_on_purchase" class="inline">Grant org_editor role for selected org on next
                        purchase?</label>
                    <br />

                </div>
                <div id="orgss-groups-settings" style="display: none;">
                    <div>Group settings coming soon.</div>
                </div>
            </li>

            <?php echo ob_get_clean(); ?>

            <script type='text/javascript'>
            // Embed JavaScript directly in field settings to avoid gform_editor_js conflicts
            jQuery(document).ready(function($) {
                // Use the official Gravity Forms API to wait for field settings to load
                $(document).on('gform_load_field_settings', function(event, field) {
                    // Only initialize for our field type
                    if (field.type !== 'wicket_org_search_select') {
                        return;
                    }

                                        // Set up the conditional display for Organizations vs Groups mode
                    var orgssSearchModeSelect = $('#orgss_search_mode_select');
                    var orgSettings = $('#orgss-org-settings');
                    var groupsSettings = $('#orgss-groups-settings');

                    // Function to handle mode switching
                    function updateModeDisplay(selectedMode) {
                        if (selectedMode === 'groups') {
                            orgSettings.hide();
                            groupsSettings.show();
                        } else {
                            orgSettings.show();
                            groupsSettings.hide();
                        }
                    }

                    $('#orgss_search_org_type_input').val(field.orgss_search_org_type || '');
                    $('#orgss_relationship_type_upon_org_creation_input').val(field.orgss_relationship_type_upon_org_creation || 'employee');
                    $('#orgss_relationship_mode_input').val(field.orgss_relationship_mode || 'person_to_organization');
                    $('#orgss_new_org_type_override_input').val(field.orgss_new_org_type_override || '');
                    $('#orgss_org_term_singular_input').val(field.orgss_org_term_singular || 'Organization');
                    $('#orgss_org_term_plural_input').val(field.orgss_org_term_plural || 'Organizations');
                    $('#orgss_no_results_message_input').val(field.orgss_no_results_message || '');
                    $('#orgss_checkbox_id_new_org_input').val(field.orgss_checkbox_id_new_org || '');


                    // Handle checkboxes
                    $('#orgss_disable_org_creation').prop('checked', field.orgss_disable_org_creation || false);
                    $('#orgss_hide_remove_buttons').prop('checked', field.orgss_hide_remove_buttons || false);
                    $('#orgss_hide_select_buttons').prop('checked', field.orgss_hide_select_buttons || false);
                    $('#orgss_display_removal_alert_message').prop('checked', field.orgss_display_removal_alert_message || false);
                    $('#orgss_disable_selecting_orgs_with_active_membership').prop('checked', field.orgss_disable_selecting_orgs_with_active_membership || false);
                    $('#orgss_grant_roster_man_on_purchase').prop('checked', field.orgss_grant_roster_man_on_purchase || false);
                    $('#orgss_grant_org_editor_on_select').prop('checked', field.orgss_grant_org_editor_on_select || false);
                    $('#orgss_grant_org_editor_on_purchase').prop('checked', field.orgss_grant_org_editor_on_purchase || false);

                    // Handle active membership alert fields
                    $('#orgss_active_membership_alert_title_input').val(field.orgss_active_membership_alert_title || '');
                    $('#orgss_active_membership_alert_body_input').val(field.orgss_active_membership_alert_body || '');
                    $('#orgss_active_membership_alert_button_1_text_input').val(field.orgss_active_membership_alert_button_1_text || '');
                    $('#orgss_active_membership_alert_button_1_url_input').val(field.orgss_active_membership_alert_button_1_url || '');
                    $('#orgss_active_membership_alert_button_1_style_select').val(field.orgss_active_membership_alert_button_1_style || 'primary');
                    $('#orgss_active_membership_alert_button_1_new_tab').prop('checked', field.orgss_active_membership_alert_button_1_new_tab || false);
                    $('#orgss_active_membership_alert_button_2_text_input').val(field.orgss_active_membership_alert_button_2_text || '');
                    $('#orgss_active_membership_alert_button_2_url_input').val(field.orgss_active_membership_alert_button_2_url || '');
                    $('#orgss_active_membership_alert_button_2_style_select').val(field.orgss_active_membership_alert_button_2_style || 'primary');
                    $('#orgss_active_membership_alert_button_2_new_tab').prop('checked', field.orgss_active_membership_alert_button_2_new_tab || false);

                    // Set initial state based on field's current value
                    var currentMode = field.orgss_search_mode || 'org';
                    orgssSearchModeSelect.val(currentMode);
                    updateModeDisplay(currentMode);

                    // Show/hide active membership alert fields based on checkbox
                    function updateActiveMembershipDisplay() {
                        var isChecked = $('#orgss_disable_selecting_orgs_with_active_membership').is(':checked');
                        if (isChecked) {
                            $('#orgss_active_membership_alert_wrapper').show();
                        } else {
                            $('#orgss_active_membership_alert_wrapper').hide();
                        }
                    }

                    // Set initial state and handle checkbox changes
                    updateActiveMembershipDisplay();
                    $('#orgss_disable_selecting_orgs_with_active_membership').off('change.wicket-orgss-alert').on('change.wicket-orgss-alert', function() {
                        updateActiveMembershipDisplay();
                    });

                    // Handle dropdown change
                    orgssSearchModeSelect.off('change.wicket-orgss').on('change.wicket-orgss', function() {
                        var selectedMode = $(this).val();
                        updateModeDisplay(selectedMode);
                    });
                });
            });
            </script>

<?php
        }
    }

    public static function editor_script()
    {
        // JavaScript is now embedded directly in the field settings output
        // to avoid conflicts with the gform_editor_js hook
    }

    /**
     * Return the field title, for the form editor.
     */
    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Org Search/Select', 'wicket-gf');
    }

    /**
     * Assign the field button to the Advanced Fields group.
     */
    public function get_form_editor_button()
    {
        return [
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    /**
     * Define the fields settings which should be available on the field in the form editor.
     */
    public function get_form_editor_field_settings()
    {
        return [
            'label_setting',
            'description_setting',
            'rules_setting',
            'error_message_setting',
            'css_class_setting',
            'conditional_logic_field_setting',
            'label_placement_setting',
            'wicket_orgss_setting',
        ];
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        return sprintf(
            "function SetDefaultValues_%s(field) {
                field.label = '%s';
                field.orgss_search_mode = 'org';
                field.orgss_search_org_type = '';
                field.orgss_relationship_type_upon_org_creation = 'employee';
                field.orgss_relationship_mode = 'person_to_organization';
                field.orgss_new_org_type_override = '';
                field.orgss_org_term_singular = 'Organization';
                field.orgss_org_term_plural = 'Organizations';
                field.orgss_no_results_message = '';
                field.orgss_checkbox_id_new_org = '';
                field.orgss_disable_org_creation = false;
                field.orgss_hide_remove_buttons = false;
                field.orgss_hide_select_buttons = false;
                field.orgss_display_removal_alert_message = false;
                field.orgss_disable_selecting_orgs_with_active_membership = false;
                field.orgss_grant_roster_man_on_purchase = false;
                field.orgss_grant_org_editor_on_select = false;
                field.orgss_grant_org_editor_on_purchase = false;
                field.orgss_active_membership_alert_title = '';
                field.orgss_active_membership_alert_body = '';
                field.orgss_active_membership_alert_button_1_text = '';
                field.orgss_active_membership_alert_button_1_url = '';
                field.orgss_active_membership_alert_button_1_style = 'primary';
                field.orgss_active_membership_alert_button_1_new_tab = false;
                field.orgss_active_membership_alert_button_2_text = '';
                field.orgss_active_membership_alert_button_2_url = '';
                field.orgss_active_membership_alert_button_2_style = 'primary';
                field.orgss_active_membership_alert_button_2_new_tab = false;
            }",
            $this->type,
            esc_js($this->get_form_editor_field_title())
        );
    }

    /**
     * Define if conditional logic is supported.
     */
    public function is_conditional_logic_supported()
    {
        return true;
    }

    /**
     * Define if this field supports being used as a conditional logic rule.
     */
    public function is_value_submission_array()
    {
        return false;
    }

    /**
     * Define the field input for the form editor and front end.
     */
    public function get_field_input(
        $form,
        $value = '',
        $entry = null
    ) {
        if ($this->is_form_editor()) {
            return '<p>Org Search/Select widget will show here on the frontend</p>';
        }

        $id = (int) $this->id;
        $field_id = isset($form['id']) ? $form['id'] . '_' . $id : $id;
        $hide_label = isset($this->labelPlacement) && $this->labelPlacement === 'hidden_label';

        $search_mode = 'org';
        $search_org_type = '';
        $relationship_type_upon_org_creation = 'employee';
        $relationship_mode = 'person_to_organization';
        $new_org_type_override = '';
        $org_term_singular = 'Organization';
        $org_term_plural = 'Organizations';
        $orgss_no_results_message = '';
        $disable_org_creation = false;
        $checkbox_id_new_org = '';
        $disable_selecting_orgs_with_active_membership = false;
        $grant_roster_man_on_purchase = false;
        $orgss_grant_org_editor_on_select = false;
        $orgss_grant_org_editor_on_purchase = false;
        $orgss_hide_remove_buttons = false;
        $orgss_hide_select_buttons = false;
        $orgss_display_removal_alert_message = false;
        $orgss_active_membership_alert_title = '';
        $orgss_active_membership_alert_body = '';
        $orgss_active_membership_alert_button_1_text = '';
        $orgss_active_membership_alert_button_1_url = '';
        $orgss_active_membership_alert_button_1_style = '';
        $orgss_active_membership_alert_button_1_new_tab = false;
        $orgss_active_membership_alert_button_2_text = '';
        $orgss_active_membership_alert_button_2_url = '';
        $orgss_active_membership_alert_button_2_style = '';
        $orgss_active_membership_alert_button_2_new_tab = false;

        // Extract field settings
        foreach ($form['fields'] as $field) {
            if (gettype($field) == 'object') {
                if (get_class($field) == 'GFWicketFieldOrgSearchSelect') {
                    if ($field->id == $id) {
                        if (isset($field->orgss_search_mode)) {
                            $search_mode = $field->orgss_search_mode;
                        }
                        if (isset($field->orgss_search_org_type)) {
                            $search_org_type = $field->orgss_search_org_type;
                        }
                        if (isset($field->orgss_relationship_type_upon_org_creation)) {
                            $relationship_type_upon_org_creation = $field->orgss_relationship_type_upon_org_creation;
                        }
                        if (isset($field->orgss_relationship_mode)) {
                            $relationship_mode = $field->orgss_relationship_mode;
                        }
                        if (isset($field->orgss_new_org_type_override)) {
                            $new_org_type_override = $field->orgss_new_org_type_override;
                        }
                        if (isset($field->orgss_org_term_singular)) {
                            $org_term_singular = $field->orgss_org_term_singular;
                        }
                        if (isset($field->orgss_org_term_plural)) {
                            $org_term_plural = $field->orgss_org_term_plural;
                        }
                        if (isset($field->orgss_no_results_message)) {
                            $orgss_no_results_message = $field->orgss_no_results_message;
                        }
                        if (isset($field->orgss_disable_org_creation)) {
                            $disable_org_creation = $field->orgss_disable_org_creation;
                        }
                        if (isset($field->orgss_checkbox_id_new_org)) {
                            $checkbox_id_new_org = $field->orgss_checkbox_id_new_org;
                        }
                        if (isset($field->orgss_disable_selecting_orgs_with_active_membership)) {
                            $disable_selecting_orgs_with_active_membership = $field->orgss_disable_selecting_orgs_with_active_membership;
                        }
                        if (isset($field->orgss_active_membership_alert_title)) {
                            $orgss_active_membership_alert_title = $field->orgss_active_membership_alert_title;
                        }
                        if (isset($field->orgss_active_membership_alert_body)) {
                            $orgss_active_membership_alert_body = $field->orgss_active_membership_alert_body;
                        }
                        if (isset($field->orgss_active_membership_alert_button_1_text)) {
                            $orgss_active_membership_alert_button_1_text = $field->orgss_active_membership_alert_button_1_text;
                        }
                        if (isset($field->orgss_active_membership_alert_button_1_url)) {
                            $orgss_active_membership_alert_button_1_url = $field->orgss_active_membership_alert_button_1_url;
                        }
                        if (isset($field->orgss_active_membership_alert_button_1_style)) {
                            $orgss_active_membership_alert_button_1_style = $field->orgss_active_membership_alert_button_1_style;
                        }
                        if (isset($field->orgss_active_membership_alert_button_1_new_tab)) {
                            $orgss_active_membership_alert_button_1_new_tab = $field->orgss_active_membership_alert_button_1_new_tab;
                        }
                        if (isset($field->orgss_active_membership_alert_button_2_text)) {
                            $orgss_active_membership_alert_button_2_text = $field->orgss_active_membership_alert_button_2_text;
                        }
                        if (isset($field->orgss_active_membership_alert_button_2_url)) {
                            $orgss_active_membership_alert_button_2_url = $field->orgss_active_membership_alert_button_2_url;
                        }
                        if (isset($field->orgss_active_membership_alert_button_2_style)) {
                            $orgss_active_membership_alert_button_2_style = $field->orgss_active_membership_alert_button_2_style;
                        }
                        if (isset($field->orgss_active_membership_alert_button_2_new_tab)) {
                            $orgss_active_membership_alert_button_2_new_tab = $field->orgss_active_membership_alert_button_2_new_tab;
                        }
                        if (isset($field->orgss_grant_roster_man_on_purchase)) {
                            $grant_roster_man_on_purchase = $field->orgss_grant_roster_man_on_purchase;
                        }
                        if (isset($field->orgss_grant_org_editor_on_select)) {
                            $orgss_grant_org_editor_on_select = $field->orgss_grant_org_editor_on_select;
                        }
                        if (isset($field->orgss_grant_org_editor_on_purchase)) {
                            $orgss_grant_org_editor_on_purchase = $field->orgss_grant_org_editor_on_purchase;
                        }
                        if (isset($field->orgss_hide_remove_buttons)) {
                            $orgss_hide_remove_buttons = $field->orgss_hide_remove_buttons;
                        }
                        if (isset($field->orgss_hide_select_buttons)) {
                            $orgss_hide_select_buttons = $field->orgss_hide_select_buttons;
                        }
                        if (isset($field->orgss_display_removal_alert_message)) {
                            $orgss_display_removal_alert_message = $field->orgss_display_removal_alert_message;
                        }
                    }
                }
            }
        }

        if (component_exists('org-search-select')) {
            $params = [
                'classes'                                       => [],
                'search_mode'                                   => $search_mode,
                'search_org_type'                               => $search_org_type,
                'relationship_type_upon_org_creation'           => $relationship_type_upon_org_creation,
                'relationship_mode'                             => $relationship_mode,
                'new_org_type_override'                         => $new_org_type_override,
                // Use a unique name for the component's internal hidden field to avoid colliding with GF's own input_{id}
                // The GF field value should be stored in the hidden input rendered below with name="input_{$id}"
                'selected_uuid_hidden_field_name'               => 'orgss_selected_uuid_' . $id,
                'checkbox_id_new_org'                           => $checkbox_id_new_org,
                'key'                                           => $id,
                'org_term_singular'                             => $org_term_singular,
                'org_term_plural'                               => $org_term_plural,
                'no_results_found_message'                      => $orgss_no_results_message,
                'disable_create_org_ui'                         => $disable_org_creation,
                'disable_selecting_orgs_with_active_membership' => $disable_selecting_orgs_with_active_membership,
                'active_membership_alert_title'                 => $orgss_active_membership_alert_title,
                'active_membership_alert_body'                  => $orgss_active_membership_alert_body,
                'active_membership_alert_button_1_text'         => $orgss_active_membership_alert_button_1_text,
                'active_membership_alert_button_1_url'          => $orgss_active_membership_alert_button_1_url,
                'active_membership_alert_button_1_style'        => $orgss_active_membership_alert_button_1_style,
                'active_membership_alert_button_1_new_tab'      => $orgss_active_membership_alert_button_1_new_tab,
                'active_membership_alert_button_2_text'         => $orgss_active_membership_alert_button_2_text,
                'active_membership_alert_button_2_url'          => $orgss_active_membership_alert_button_2_url,
                'active_membership_alert_button_2_style'        => $orgss_active_membership_alert_button_2_style,
                'active_membership_alert_button_2_new_tab'      => $orgss_active_membership_alert_button_2_new_tab,
                'grant_roster_man_on_purchase'                  => $grant_roster_man_on_purchase,
                'grant_org_editor_on_select'                    => $orgss_grant_org_editor_on_select,
                'grant_org_editor_on_purchase'                  => $orgss_grant_org_editor_on_purchase,
                'hide_remove_buttons'                           => $orgss_hide_remove_buttons,
                'hide_select_buttons'                           => $orgss_hide_select_buttons,
                'display_removal_alert_message'                 => $orgss_display_removal_alert_message,
                'form_id'                                       => $form['id'] ?? 0,
            ];

            $component_output = get_component('org-search-select', $params, false);
            //$component_output .= '<script>console.log("GFWicketFieldOrgSearchSelect PARAMS: ", ' . json_encode($params) . ');</script>';

            // Use a unique hidden field name for the widget to avoid colliding with Gravity Forms' own
            // "input_{id}" fields on the page. Keep the value for backwards compatibility but prefer
            // the orgss_selected_uuid_{id} name.
            $unique_hidden_name = 'orgss_selected_uuid_' . $id;
            $unique_hidden_id = sprintf('orgss_selected_uuid_%s_%d', $form['id'] ?? 0, $id);
            $hidden_field = sprintf(
                '<input type="hidden" name="%s" id="%s" value="%s" class="gf_org_search_select_input" />',
                esc_attr($unique_hidden_name),
                esc_attr($unique_hidden_id),
                esc_attr($value)
            );

            $label_css = $hide_label
                ? sprintf("<style>.gform_wrapper.gravity-theme label[for='input_%s'].gfield_label { display: none; }</style>", $field_id)
                : '';

            $html_output = $label_css . '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . $hidden_field . '</div>';

            return apply_filters('wicket_gf_org_search_select_html_output', $html_output, $this, $form);
        } else {
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Org search/select component is missing. Please update the Wicket Base Plugin.</p></div>';
        }
    }

    /**
     * Add protection class to prevent incorrect hiding by conditional logic conflicts.
     */
    public function get_field_css_class()
    {
        $css_class = parent::get_field_css_class();
        $css_class .= ' wicket-field-protected';

        return $css_class;
    }

    public function get_value_submission($field_values, $get_from_post_global_var = true)
    {
        // Prefer the widget's unique hidden field name, fall back to GF's legacy input_{id}
        $preferred = 'orgss_selected_uuid_' . $this->id;
        $legacy = 'input_' . $this->id;

        if ($get_from_post_global_var) {
            if (isset($_POST[$preferred])) {
                $value = rgpost($preferred);
            } else {
                $value = rgpost($legacy);
            }
        } else {
            if (isset($field_values[$preferred])) {
                $value = $field_values[$preferred];
            } else {
                $value = $field_values[$legacy] ?? '';
            }
        }

        return $value;
    }

    public function get_field_value($value, $form, $input_name, $lead_id, $lead)
    {
        if (empty($value)) {
            return '';
        }

        return $value;
    }

    public function is_value_submission_empty($form_id)
    {
        $preferred = 'orgss_selected_uuid_' . $this->id;
        $legacy = 'input_' . $this->id;
        $value = rgpost($preferred);
        if ($value === null) {
            $value = rgpost($legacy);
        }

        return empty($value);
    }

    public function get_conditional_logic_event($event)
    {
        return 'change';
    }

    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        return $value;
    }

    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen')
    {
        if (empty($value)) {
            return '';
        }

        return esc_html($value);
    }

    public function get_value_entry_list($value, $entry, $field_id, $columns, $form)
    {
        if (empty($value)) {
            return '';
        }

        return esc_html($value);
    }

    /**
     * Sanitize field value.
     */
    public function sanitize_settings()
    {
        parent::sanitize_settings();

        // Sanitize our custom settings
        if (isset($this->orgss_search_mode)) {
            $this->orgss_search_mode = sanitize_text_field($this->orgss_search_mode);
        }
        if (isset($this->orgss_search_org_type)) {
            $this->orgss_search_org_type = sanitize_text_field($this->orgss_search_org_type);
        }
        if (isset($this->orgss_relationship_type_upon_org_creation)) {
            $this->orgss_relationship_type_upon_org_creation = sanitize_text_field($this->orgss_relationship_type_upon_org_creation);
        }
        if (isset($this->orgss_relationship_mode)) {
            $this->orgss_relationship_mode = sanitize_text_field($this->orgss_relationship_mode);
        }
        if (isset($this->orgss_new_org_type_override)) {
            $this->orgss_new_org_type_override = sanitize_text_field($this->orgss_new_org_type_override);
        }
        if (isset($this->orgss_org_term_singular)) {
            $this->orgss_org_term_singular = sanitize_text_field($this->orgss_org_term_singular);
        }
        if (isset($this->orgss_org_term_plural)) {
            $this->orgss_org_term_plural = sanitize_text_field($this->orgss_org_term_plural);
        }
        if (isset($this->orgss_no_results_message)) {
            $this->orgss_no_results_message = sanitize_textarea_field($this->orgss_no_results_message);
        }
    }

    /**
     * Define which field properties should be available in the field object in JavaScript.
     */
    public function get_form_editor_field_description()
    {
        return esc_attr__('Allows users to search for and select organizations or groups.', 'wicket-gf');
    }

    /**
     * Define validation for the field.
     */
    public function validate($value, $form)
    {
        // Basic validation - check if required field has value
        if ($this->isRequired && empty($value)) {
            $this->failed_validation = true;
            $this->validation_message = empty($this->errorMessage) ? esc_html__('This field is required.', 'wicket-gf') : $this->errorMessage;
        }
    }

    /**
     * Handle field choices for compatibility with GF.
     */
    public function get_choices()
    {
        return [];
    }

    /**
     * Define field size.
     */
    public function get_field_size_settings()
    {
        return [
            'size' => 'medium',
        ];
    }
}
