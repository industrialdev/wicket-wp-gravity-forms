<?php
class GFWicketFieldOrgSearchSelect extends GF_Field
{
    public $type = 'wicket_org_search_select';

    public static function custom_settings($position, $form_id)
    {
        //create settings on position 25 (right after Field Label)
        if ($position == 25) { ?>
<?php ob_start(); ?>

<li class="wicket_orgss_setting field_setting" style="display:none;">
    <label>Search Mode</label>
    <select name="orgss_search_mode" id="orgss_search_mode_select" onchange="window.WicketGF.OrgSearch.updateSearchMode(this.value)">
        <option value="org" selected>Organizations</option>
        <option value="groups">Groups (Beta, In Development)</option>
    </select>

    <div id="orgss-org-settings" style="padding: 1em 0;">
        <label style="display: block;">Organization Type</label>
        <input onkeyup="SetFieldProperty('orgss_search_org_type', this.value)"
            type="text" name="orgss_search_org_type" id="orgss_search_org_type_input" />
        <p style="margin-top: 2px;margin-bottom: 0px;"><em>If left blank, all organization types will be searchable. If
                you wish to filter, you'll need to provide the "slug" of the organization type, e.g. "it_company".</em>
        </p>

        <label style="margin-top: 1em;display: block;">Relationship Type(s) Upon Org Creation/Selection</label>
        <input onkeyup="SetFieldProperty('orgss_relationship_type_upon_org_creation', this.value)"
             type="text"
            name="orgss_relationship_type_upon_org_creation" id="orgss_relationship_type_upon_org_creation_input" />
        <p style="margin-top: 2px;margin-bottom: 0px;"><em>This can be a single relationship, or a comma-separated list
                of multiple relationships (in slug form) that will be created at once.</em></p>

        <label style="margin-top: 1em;display: block;">Relationship Mode</label>
        <input onkeyup="SetFieldProperty('orgss_relationship_mode', this.value)"
            type="text" name="orgss_relationship_mode" id="orgss_relationship_mode_input" />

        <label style="margin-top: 1em;display: block;">Org Type When User Creates New Org</label>
        <input onkeyup="SetFieldProperty('orgss_new_org_type_override', this.value)"
             type="text" name="orgss_new_org_type_override"
            id="orgss_new_org_type_override_input" />
        <p style="margin-top: 2px;"><em>If left blank, the user will be allowed to select the organization type
                themselves from the frontend.</em></p>

        <label style="margin-top: 1em;display: block;">Org name singular</label>
        <input onkeyup="SetFieldProperty('orgss_org_term_singular', this.value)"
            type="text" name="orgss_org_term_singular" id="orgss_org_term_singular_input" />
        <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organization" or "Chapter". Can
                be left blank to use default.</em></p>

        <label style="margin-top: 1em;display: block;">Org name plural</label>
        <input onkeyup="SetFieldProperty('orgss_org_term_plural', this.value)"
            type="text" name="orgss_org_term_plural" id="orgss_org_term_plural_input" />
        <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organizations" or "Chapters".
                Can be left blank to use default.</em></p>

        <label style="margin-top: 1em;display: block;">No results found message</label>
        <input onkeyup="SetFieldProperty('orgss_no_results_message', this.value)"
            type="text" name="orgss_no_results_message" id="orgss_no_results_message_input" />
        <p style="margin-top: 2px;"><em>Message that will display if nothing is found by their search. Can be left blank
                to use default.</em></p>

        <label style="margin-top: 1em;display: block;">'New Org Created' checkbox ID</label>
        <input onkeyup="SetFieldProperty('orgss_checkbox_id_new_org', this.value)"
             type="text" name="orgss_checkbox_id_new_org"
            id="orgss_checkbox_id_new_org_input" placeholder="E.g. choice_5_12_1" />
        <p style="margin-top: 2px;"><em>ID of checkbox to be checked if a new org gets created.</em></p>

        <input onchange="SetFieldProperty('orgss_disable_org_creation', this.checked)"
             type="checkbox" id="orgss_disable_org_creation"
            class="orgss_disable_org_creation">
        <label for="orgss_disable_org_creation" class="inline">Disable ability to create new org/entity?</label>
        <br />

        <input onchange="SetFieldProperty('orgss_hide_remove_buttons', this.checked)"
             type="checkbox" id="orgss_hide_remove_buttons"
            class="orgss_hide_remove_buttons">
        <label for="orgss_hide_remove_buttons" class="inline">Hide remove buttons?</label>
        <br />

        <input onchange="SetFieldProperty('orgss_hide_select_buttons', this.checked)"
             type="checkbox" id="orgss_hide_select_buttons"
            class="orgss_hide_select_buttons">
        <label for="orgss_hide_select_buttons" class="inline">Hide select buttons?</label>
        <br />

        <input onchange="SetFieldProperty('orgss_display_removal_alert_message', this.checked)"
             type="checkbox" id="orgss_display_removal_alert_message"
            class="orgss_display_removal_alert_message">
        <label for="orgss_display_removal_alert_message" class="inline">Display removal alert message?</label>
        <br />

        <input
            onchange="SetFieldProperty('orgss_disable_selecting_orgs_with_active_membership', this.checked);window.WicketGF.OrgSearch.toggleActiveMembershipAlert(this.checked);"
             type="checkbox"
            id="orgss_disable_selecting_orgs_with_active_membership"
            class="orgss_disable_selecting_orgs_with_active_membership">
        <label for="orgss_disable_selecting_orgs_with_active_membership" class="inline">Disable ability to select orgs
            with active membership?</label>
        <br />

        <div id="orgss_active_membership_alert_wrapper" style="margin-left:10px;margin-bottom: 10px;">
            <label style="margin-top: 1em;display: block;">Active Membership Alert Title</label>
            <input onkeyup="SetFieldProperty('orgss_active_membership_alert_title', this.value)"
                 type="text"
                name="orgss_active_membership_alert_title" id="orgss_active_membership_alert_title_input" />

            <label style="margin-top: 1em;display: block;">Active Membership Alert Body</label>
            <textarea onkeyup="SetFieldProperty('orgss_active_membership_alert_body', this.value)"
                 type="text" name="orgss_active_membership_alert_body"
                id="orgss_active_membership_alert_body_input">
                  </textarea>

            <label style="margin-top: 1em;display: block;">Active Membership Button 1 Text</label>
            <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_1_text', this.value)"
                 type="text"
                name="orgss_active_membership_alert_button_1_text"
                id="orgss_active_membership_alert_button_1_text_input" />

            <label style="margin-top: 1em;display: block;">Active Membership Button 1 URL</label>
            <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_1_url', this.value)"
                 type="text"
                name="orgss_active_membership_alert_button_1_url" id="orgss_active_membership_alert_button_1_url_input" />
            <p style="margin-top: 2px;"><em>Set to PROCEED for this button to continue with the usual org selection
                    actions, or BUTTON if you're going to do something fancy with it on the backend.</em></p>

            <label style="margin-top: 1em;display: block;">Active Membership Button 1 Style</label>
            <select name="orgss_active_membership_alert_button_1_style"
                id="orgss_active_membership_alert_button_1_style_select"
                onchange="SetFieldProperty('orgss_active_membership_alert_button_1_style', this.value)"
                style="margin-bottom: 1em;">
                <option value="primary" selected>Primary</option>
                <option value="secondary" selected>Secondary</option>
                <option value="ghost" selected>Ghost</option>
            </select>

            <input onchange="SetFieldProperty('orgss_active_membership_alert_button_1_new_tab', this.checked)"
                 type="checkbox"
                id="orgss_active_membership_alert_button_1_new_tab"
                class="orgss_active_membership_alert_button_1_new_tab">
            <label for="orgss_active_membership_alert_button_1_new_tab" class="inline">Open Button 1 in New Tab?</label>


            <label style="margin-top: 1em;display: block;">Active Membership Button 2 Text</label>
            <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_2_text', this.value)"
                 type="text"
                name="orgss_active_membership_alert_button_2_text"
                id="orgss_active_membership_alert_button_2_text_input" />

            <label style="margin-top: 1em;display: block;">Active Membership Button 2 URL</label>
            <input onkeyup="SetFieldProperty('orgss_active_membership_alert_button_2_url', this.value)"
                 type="text"
                name="orgss_active_membership_alert_button_2_url" id="orgss_active_membership_alert_button_2_url_input" />
            <p style="margin-top: 2px;"><em>Set to PROCEED for this button to continue with the usual org selection
                    actions, or BUTTON if you're going to do something fancy with it on the backend.</em></p>

            <label style="margin-top: 1em;display: block;">Active Membership Button 2 Style</label>
            <select name="orgss_active_membership_alert_button_2_style"
                id="orgss_active_membership_alert_button_2_style_select"
                onchange="SetFieldProperty('orgss_active_membership_alert_button_2_style', this.value)"
                style="margin-bottom: 1em;">
                <option value="primary" selected>Primary</option>
                <option value="secondary" selected>Secondary</option>
                <option value="ghost" selected>Ghost</option>
            </select>

            <input onchange="SetFieldProperty('orgss_active_membership_alert_button_2_new_tab', this.checked)"
                 type="checkbox"
                id="orgss_active_membership_alert_button_2_new_tab"
                class="orgss_active_membership_alert_button_2_new_tab">
            <label for="orgss_active_membership_alert_button_2_new_tab" class="inline">Open Button 2 in New Tab?</label>


        </div>

        <input onchange="SetFieldProperty('orgss_grant_roster_man_on_purchase', this.checked)"
             type="checkbox" id="orgss_grant_roster_man_on_purchase"
            class="orgss_grant_roster_man_on_purchase">
        <label for="orgss_grant_roster_man_on_purchase" class="inline">Grant roster management (membership_manager role
            for selected org) on next purchase?</label>
        <br />

        <input onchange="SetFieldProperty('orgss_grant_org_editor_on_select', this.checked)"
             type="checkbox" id="orgss_grant_org_editor_on_select"
            class="orgss_grant_org_editor_on_select">
        <label for="orgss_grant_org_editor_on_select" class="inline">Grant org_editor role on selection (scoped to
            selected org)?</label>
        <br />

        <input onchange="SetFieldProperty('orgss_grant_org_editor_on_purchase', this.checked)"
             type="checkbox" id="orgss_grant_org_editor_on_purchase"
            class="orgss_grant_org_editor_on_purchase">
        <label for="orgss_grant_org_editor_on_purchase" class="inline">Grant org_editor role for selected org on next
            purchase?</label>
        <br />

    </div>
    <div id="orgss-groups-settings">
        <div>Group settings coming soon.</div>
    </div>
</li>

<?php echo ob_get_clean(); ?>

<?php
        }
    }

    public static function editor_script()
    {
        ?>
<script>
    window.WicketGF = window.WicketGF || {};
    window.WicketGF.OrgSearch = window.WicketGF.OrgSearch || {};

    // Initialize the OrgSearch object with the functions that can be called from HTML
    window.WicketGF.OrgSearch.updateSearchMode = function(mode) {
        document.getElementById('orgss-org-settings').style.display = mode === 'org' ? 'block' : 'none';
        document.getElementById('orgss-groups-settings').style.display = mode === 'groups' ? 'block' : 'none';
    };

    window.WicketGF.OrgSearch.toggleActiveMembershipAlert = function(show) {
        document.getElementById('orgss_active_membership_alert_wrapper').style.display = show ? 'block' : 'none';
    };

    // Now extend the object with the rest of the functionality
    Object.assign(window.WicketGF.OrgSearch, {
        init: function() {
            gform.addAction( 'gform_load_field_settings', function( field ) {
                if ( field.type === 'wicket_org_search_select' ) {
                    window.WicketGF.OrgSearch.loadFieldSettings( field );
                }
            });
        },
        loadFieldSettings: function(field) {
            const searchModeSelect = document.getElementById('orgss_search_mode_select');
            if (searchModeSelect) {
                searchModeSelect.value = field.orgss_search_mode || 'org';
            }

            const searchOrgTypeInput = document.getElementById('orgss_search_org_type_input');
            if (searchOrgTypeInput) {
                searchOrgTypeInput.value = field.orgss_search_org_type || '';
            }

            const relationshipTypeInput = document.getElementById('orgss_relationship_type_upon_org_creation_input');
            if (relationshipTypeInput) {
                relationshipTypeInput.value = field.orgss_relationship_type_upon_org_creation || 'employee';
            }

            const relationshipModeInput = document.getElementById('orgss_relationship_mode_input');
            if (relationshipModeInput) {
                relationshipModeInput.value = field.orgss_relationship_mode || 'person_to_organization';
            }

            const newOrgTypeInput = document.getElementById('orgss_new_org_type_override_input');
            if (newOrgTypeInput) {
                newOrgTypeInput.value = field.orgss_new_org_type_override || '';
            }

            const orgTermSingularInput = document.getElementById('orgss_org_term_singular_input');
            if (orgTermSingularInput) {
                orgTermSingularInput.value = field.orgss_org_term_singular || 'Organization';
            }

            const orgTermPluralInput = document.getElementById('orgss_org_term_plural_input');
            if (orgTermPluralInput) {
                orgTermPluralInput.value = field.orgss_org_term_plural || 'Organizations';
            }

            const noResultsMessageInput = document.getElementById('orgss_no_results_message_input');
            if (noResultsMessageInput) {
                noResultsMessageInput.value = field.orgss_no_results_message || '';
            }

            const checkboxIdNewOrgInput = document.getElementById('orgss_checkbox_id_new_org_input');
            if (checkboxIdNewOrgInput) {
                checkboxIdNewOrgInput.value = field.orgss_checkbox_id_new_org || '';
            }

            const disableOrgCreationCheckbox = document.getElementById('orgss_disable_org_creation');
            if (disableOrgCreationCheckbox) {
                disableOrgCreationCheckbox.checked = field.orgss_disable_org_creation || false;
            }

            const hideRemoveButtonsCheckbox = document.getElementById('orgss_hide_remove_buttons');
            if (hideRemoveButtonsCheckbox) {
                hideRemoveButtonsCheckbox.checked = field.orgss_hide_remove_buttons || false;
            }

            const hideSelectButtonsCheckbox = document.getElementById('orgss_hide_select_buttons');
            if (hideSelectButtonsCheckbox) {
                hideSelectButtonsCheckbox.checked = field.orgss_hide_select_buttons || false;
            }

            const displayRemovalAlertCheckbox = document.getElementById('orgss_display_removal_alert_message');
            if (displayRemovalAlertCheckbox) {
                displayRemovalAlertCheckbox.checked = field.orgss_display_removal_alert_message || false;
            }

            // Additional elements with null checking
            const activeMembershipCheckbox = document.getElementById('orgss_disable_selecting_orgs_with_active_membership');
            if (activeMembershipCheckbox) {
                activeMembershipCheckbox.checked = field.orgss_disable_selecting_orgs_with_active_membership || false;
            }

            const activeMembershipTitleInput = document.getElementById('orgss_active_membership_alert_title_input');
            if (activeMembershipTitleInput) {
                activeMembershipTitleInput.value = field.orgss_active_membership_alert_title || '';
            }

            const activeMembershipBodyInput = document.getElementById('orgss_active_membership_alert_body_input');
            if (activeMembershipBodyInput) {
                activeMembershipBodyInput.value = field.orgss_active_membership_alert_body || '';
            }

            const activeMembershipButton1TextInput = document.getElementById('orgss_active_membership_alert_button_1_text_input');
            if (activeMembershipButton1TextInput) {
                activeMembershipButton1TextInput.value = field.orgss_active_membership_alert_button_1_text || '';
            }

            const activeMembershipButton1UrlInput = document.getElementById('orgss_active_membership_alert_button_1_url_input');
            if (activeMembershipButton1UrlInput) {
                activeMembershipButton1UrlInput.value = field.orgss_active_membership_alert_button_1_url || '';
            }

            const activeMembershipButton1StyleSelect = document.getElementById('orgss_active_membership_alert_button_1_style_select');
            if (activeMembershipButton1StyleSelect) {
                activeMembershipButton1StyleSelect.value = field.orgss_active_membership_alert_button_1_style || 'primary';
            }

            const activeMembershipButton1NewTabCheckbox = document.getElementById('orgss_active_membership_alert_button_1_new_tab');
            if (activeMembershipButton1NewTabCheckbox) {
                activeMembershipButton1NewTabCheckbox.checked = field.orgss_active_membership_alert_button_1_new_tab || false;
            }

            const activeMembershipButton2TextInput = document.getElementById('orgss_active_membership_alert_button_2_text_input');
            if (activeMembershipButton2TextInput) {
                activeMembershipButton2TextInput.value = field.orgss_active_membership_alert_button_2_text || '';
            }

            const activeMembershipButton2UrlInput = document.getElementById('orgss_active_membership_alert_button_2_url_input');
            if (activeMembershipButton2UrlInput) {
                activeMembershipButton2UrlInput.value = field.orgss_active_membership_alert_button_2_url || '';
            }

            const activeMembershipButton2StyleSelect = document.getElementById('orgss_active_membership_alert_button_2_style_select');
            if (activeMembershipButton2StyleSelect) {
                activeMembershipButton2StyleSelect.value = field.orgss_active_membership_alert_button_2_style || 'secondary';
            }

            const activeMembershipButton2NewTabCheckbox = document.getElementById('orgss_active_membership_alert_button_2_new_tab');
            if (activeMembershipButton2NewTabCheckbox) {
                activeMembershipButton2NewTabCheckbox.checked = field.orgss_active_membership_alert_button_2_new_tab || false;
            }

            const grantRosterManCheckbox = document.getElementById('orgss_grant_roster_man_on_purchase');
            if (grantRosterManCheckbox) {
                grantRosterManCheckbox.checked = field.orgss_grant_roster_man_on_purchase || false;
            }

            const grantOrgEditorOnSelectCheckbox = document.getElementById('orgss_grant_org_editor_on_select');
            if (grantOrgEditorOnSelectCheckbox) {
                grantOrgEditorOnSelectCheckbox.checked = field.orgss_grant_org_editor_on_select || false;
            }

            const grantOrgEditorOnPurchaseCheckbox = document.getElementById('orgss_grant_org_editor_on_purchase');
            if (grantOrgEditorOnPurchaseCheckbox) {
                grantOrgEditorOnPurchaseCheckbox.checked = field.orgss_grant_org_editor_on_purchase || false;
            }

            // Call the update functions
            window.WicketGF.OrgSearch.updateSearchMode(field.orgss_search_mode || 'org');
            window.WicketGF.OrgSearch.toggleActiveMembershipAlert(field.orgss_disable_selecting_orgs_with_active_membership || false);
        }
    });

    // Initialize the field settings handler
    window.WicketGF.OrgSearch.init();
</script>

<?php
    }

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Org Search/Select', 'wicket-gf');
    }

    // Move the field to 'advanced fields'
    public function get_form_editor_button()
    {
        return [
            'group' => 'wicket_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    // Declare that this field supports conditional logic
    public function is_conditional_logic_supported()
    {
        return true;
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
            'label_placement_setting',
            'wicket_orgss_setting',
        ];
    }

    // Render the field
    public function get_field_input($form, $value = '', $entry = null)
    {
        if ($this->is_form_editor()) {
            return '<p>Org Search/Select UI will show here on the frontend.</p><p><strong>Note:</strong> This
        element does <strong><em>not</em></strong> display correctly in the GF Preview mode and will appear broken; it\'s recommended
        to create a test page with this form on it to properly test the Org Search/Select functionality.</p>';
        }

        $id = (int) $this->id;

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
            $component_output = get_component('org-search-select', [
                'classes'                                       => [],
                'search_mode'                                   => $search_mode,
                'search_org_type'                               => $search_org_type,
                'relationship_type_upon_org_creation'           => $relationship_type_upon_org_creation,
                'relationship_mode'                             => $relationship_mode,
                'new_org_type_override'                         => $new_org_type_override,
                'selected_uuid_hidden_field_name'               => 'input_' . $id,
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
            ], false);

            // Hidden field for Gravity Forms conditional logic
            $hidden_field = sprintf(
                '<input type="hidden" name="input_%d" id="input_%s_%d" value="%s" class="gf_org_search_select_input" />',
                $id,
                $form['id'] ?? 0,
                $id,
                esc_attr($value)
            );

            return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . $hidden_field . '</div>';
        } else {
            return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Org search/select component is missing. Please update the Wicket Base Plugin.</p></div>';
        }
    }

    // Make sure the field value is properly recognized for conditional logic
    public function get_value_submission($field_values, $get_from_post_global_var = true)
    {
        $input_name = 'input_' . $this->id;

        if ($get_from_post_global_var) {
            // Get value from the $_POST
            $value = rgpost($input_name);
        } else {
            // Get value from the provided array
            $value = $field_values[$input_name] ?? '';
        }

        return $value;
    }

    // This function is needed to ensure field inputs are properly processed for conditional logic
    public function get_field_value($value, $form, $input_name, $lead_id, $lead)
    {
        if (empty($value)) {
            return '';
        }

        return $value;
    }

    // Helper method to ensure conditional logic sees the value correctly
    public function is_value_submission_empty($form_id)
    {
        $input_name = 'input_' . $this->id;
        $value = rgpost($input_name);

        return empty($value);
    }

    // Specify which event to listen for with conditional logic
    public function get_conditional_logic_event($event)
    {
        return 'change';
    }

    // Return the current field value for conditional logic
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        return $value;
    }

    // Make the field compatible with Gravity Forms entry details
    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen')
    {
        if (empty($value)) {
            return '';
        }

        // You could format the UUID to be more readable here if desired
        return esc_html($value);
    }

    // Ensure the field works properly in entry list views
    public function get_value_entry_list($value, $entry, $field_id, $columns, $form)
    {
        if (empty($value)) {
            return '';
        }

        return esc_html($value);
    }

    // This function isn't needed, as Gravity Forms will already flag the field if its marked
    // as 'required' but the user doesn't provide a value
    // public function validate( $value, $form ) {
    //   if (strlen(trim($value)) <= 0) {
    //     $this->failed_validation = true;
    //     if ( ! empty( $this->errorMessage ) ) {
    //         $this->validation_message = $this->errorMessage;
    //     }
    //   }
    // }

    // Functions for how the field value gets displayed on the backend
    // public function get_value_entry_list($value, $entry, $field_id, $columns, $form) {
    //   return __('Enter details', 'txtdomain');
    // }
    // public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen') {
    //     return '';
    // }

    // Edit merge tag
    // public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br) {
    //   return $this->prettyListOutput($value);
    // }

}
?>
