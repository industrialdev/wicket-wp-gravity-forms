<?php 

if (class_exists('GF_Field')) {
	class GFWicketFieldOrgSearchSelect extends GF_Field {
    // Ref for example: https://awhitepixel.com/tutorial-create-an-advanced-custom-gravity-forms-field-type-and-how-to-handle-multiple-input-values/

		public $type = 'wicket_org_search_select';

    public static function custom_settings( $position, $form_id ) {
      //create settings on position 25 (right after Field Label)
      if ( $position == 25 ) { ?>
        <?php ob_start(); ?>

        <div 
          class="wicket_orgss_setting"
          style="display:none;" 
          x-data="orgssData"
          x-on:gf-orgss-field-settings.window="loadFieldSettings" >
          <label>Search Mode</label>
          <select
            name="orgss_search_mode"
            class="orgss_search_mode" 
            x-model="searchMode" 
          >
            <option value="org" selected>Organizations</option>
            <option value="groups">Groups (Beta, In Development)</option>
          </select>

          <div x-show=" searchMode == 'org' " class="orgss-org-settings" style="padding: 1em 0;">
            <label style="display: block;">Organization Type</label>
            <input 
              @keyup="SetFieldProperty('orgss_search_org_type', $el.value)" x-bind:value="orgss_search_org_type"
              type="text" name="orgss_search_org_type" class="orgss_search_org_type" />
            <p style="margin-top: 2px;margin-bottom: 0px;"><em>If left blank, all organization types will be searchable. If you wish to filter, you'll need to provide the "slug" of the organization type, e.g. "it_company".</em></p>

            <label style="margin-top: 1em;display: block;">Relationship Type(s) Upon Org Creation/Selection</label>
            <input 
              @keyup="SetFieldProperty('orgss_relationship_type_upon_org_creation', $el.value)" x-bind:value="orgss_relationship_type_upon_org_creation"
              type="text" name="orgss_relationship_type_upon_org_creation" class="orgss_relationship_type_upon_org_creation" />
              <p style="margin-top: 2px;margin-bottom: 0px;"><em>This can be a single relationship, or a comma-separated list of multiple relationships (in slug form) that will be created at once.</em></p>

            <label style="margin-top: 1em;display: block;">Relationship Mode</label>
            <input 
              @keyup="SetFieldProperty('orgss_relationship_mode', $el.value)" x-bind:value="orgss_relationship_mode"
              type="text" name="orgss_relationship_mode" class="orgss_relationship_mode" />

            <label style="margin-top: 1em;display: block;">Org Type When User Creates New Org</label>
            <input 
              @keyup="SetFieldProperty('orgss_new_org_type_override', $el.value)" x-bind:value="orgss_new_org_type_override"
              type="text" name="orgss_new_org_type_override" class="orgss_new_org_type_override" />
            <p style="margin-top: 2px;"><em>If left blank, the user will be allowed to select the organization type themselves from the frontend.</em></p>
          
            <label style="margin-top: 1em;display: block;">Org name singular</label>
            <input 
              @keyup="SetFieldProperty('orgss_org_term_singular', $el.value)" x-bind:value="orgss_org_term_singular"
              type="text" name="orgss_org_term_singular" class="orgss_org_term_singular" />
            <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organization" or "Chapter". Can be left blank to use default.</em></p>

            <label style="margin-top: 1em;display: block;">Org name plural</label>
            <input 
              @keyup="SetFieldProperty('orgss_org_term_plural', $el.value)" x-bind:value="orgss_org_term_plural"
              type="text" name="orgss_org_term_plural" class="orgss_org_term_plural" />
            <p style="margin-top: 2px;"><em>How the org will be shown on the frontend, e.g. "Organizations" or "Chapters". Can be left blank to use default.</em></p>

            <label style="margin-top: 1em;display: block;">No results found message</label>
            <input 
              @keyup="SetFieldProperty('orgss_no_results_message', $el.value)" x-bind:value="orgss_no_results_message"
              type="text" name="orgss_no_results_message" class="orgss_no_results_message" />
            <p style="margin-top: 2px;"><em>Message that will display if nothing is found by their search. Can be left blank to use default.</em></p>

            <label style="margin-top: 1em;display: block;">'New Org Created' checkbox ID</label>
            <input 
              @keyup="SetFieldProperty('orgss_checkbox_id_new_org', $el.value)" x-bind:value="orgss_checkbox_id_new_org"
              type="text" name="orgss_checkbox_id_new_org" class="orgss_checkbox_id_new_org" placeholder="E.g. choice_5_12_1" />
            <p style="margin-top: 2px;"><em>ID of checkbox to be checked if a new org gets created.</em></p>
            
            <input 
              @change="SetFieldProperty('orgss_disable_org_creation', $el.checked)" x-bind:value="orgss_disable_org_creation"
              type="checkbox" id="orgss_disable_org_creation" class="orgss_disable_org_creation">
					  <label for="orgss_disable_org_creation" class="inline">Disable ability to create new org/entity?</label>
            <br />

            <input 
              @change="SetFieldProperty('orgss_hide_remove_buttons', $el.checked)" x-bind:value="orgss_hide_remove_buttons"
              type="checkbox" id="orgss_hide_remove_buttons" class="orgss_hide_remove_buttons">
					  <label for="orgss_hide_remove_buttons" class="inline">Hide remove buttons?</label>
            <br />

            <input 
              @change="SetFieldProperty('orgss_hide_select_buttons', $el.checked)" x-bind:value="orgss_hide_select_buttons"
              type="checkbox" id="orgss_hide_select_buttons" class="orgss_hide_select_buttons">
					  <label for="orgss_hide_select_buttons" class="inline">Hide select buttons?</label>
            <br />

            <input 
              @change="SetFieldProperty('orgss_display_removal_alert_message', $el.checked)" x-bind:value="orgss_display_removal_alert_message"
              type="checkbox" id="orgss_display_removal_alert_message" class="orgss_display_removal_alert_message">
					  <label for="orgss_display_removal_alert_message" class="inline">Display removal alert message?</label>
            <br />

            <input 
              @change="SetFieldProperty('orgss_disable_selecting_orgs_with_active_membership', $el.checked);orgss_disable_selecting_orgs_with_active_membership = $el.checked;" x-bind:value="orgss_disable_selecting_orgs_with_active_membership"
              type="checkbox" id="orgss_disable_selecting_orgs_with_active_membership" class="orgss_disable_selecting_orgs_with_active_membership">
					  <label for="orgss_disable_selecting_orgs_with_active_membership" class="inline">Disable ability to select orgs with active membership?</label>
            <br />

            <div x-show="orgss_disable_selecting_orgs_with_active_membership" style="margin-left:10px;margin-bottom: 10px;">
              <label style="margin-top: 1em;display: block;">Active Membership Alert Title</label>
              <input 
                @keyup="SetFieldProperty('orgss_active_membership_alert_title', $el.value)" x-bind:value="orgss_active_membership_alert_title"
                type="text" name="orgss_active_membership_alert_title" class="orgss_active_membership_alert_title" />

                <label style="margin-top: 1em;display: block;">Active Membership Alert Body</label>
                <textarea
                  @keyup="SetFieldProperty('orgss_active_membership_alert_body', $el.value)" x-bind:value="orgss_active_membership_alert_body"
                  type="text" name="orgss_active_membership_alert_body" class="orgss_active_membership_alert_body">
                  </textarea>
            
                <label style="margin-top: 1em;display: block;">Active Membership Button 1 Text</label>
                <input 
                  @keyup="SetFieldProperty('orgss_active_membership_alert_button_1_text', $el.value)" x-bind:value="orgss_active_membership_alert_button_1_text"
                    type="text" name="orgss_active_membership_alert_button_1_text" class="orgss_active_membership_alert_button_1_text" />
            
                <label style="margin-top: 1em;display: block;">Active Membership Button 1 URL</label>
                <input 
                  @keyup="SetFieldProperty('orgss_active_membership_alert_button_1_url', $el.value)" x-bind:value="orgss_active_membership_alert_button_1_url"
                  type="text" name="orgss_active_membership_alert_button_1_url" class="orgss_active_membership_alert_button_1_url" />
                <p style="margin-top: 2px;"><em>Set to PROCEED for this button to continue with the usual org selection actions, or BUTTON if you're going to do something fancy with it on the backend.</em></p>

                <label style="margin-top: 1em;display: block;">Active Membership Button 1 Style</label>
                <select
                  name="orgss_active_membership_alert_button_1_style"
                  class="orgss_active_membership_alert_button_1_style" 
                  x-model="orgss_active_membership_alert_button_1_style"
                  x-init="SetFieldProperty('orgss_active_membership_alert_button_1_style', $el.value)" 
                  @change="SetFieldProperty('orgss_active_membership_alert_button_1_style', $el.value)" 
                  style="margin-bottom: 1em;"
                >
                  <option value="primary" selected>Primary</option>
                  <option value="secondary" selected>Secondary</option>
                  <option value="ghost" selected>Ghost</option>
                </select>

                <input 
                  @change="SetFieldProperty('orgss_active_membership_alert_button_1_new_tab', $el.checked)" x-bind:value="orgss_active_membership_alert_button_1_new_tab"
                  type="checkbox" id="orgss_active_membership_alert_button_1_new_tab" class="orgss_active_membership_alert_button_1_new_tab">
                <label for="orgss_active_membership_alert_button_1_new_tab" class="inline">Open Button 1 in New Tab?</label>


                <label style="margin-top: 1em;display: block;">Active Membership Button 2 Text</label>
                <input 
                  @keyup="SetFieldProperty('orgss_active_membership_alert_button_2_text', $el.value)" x-bind:value="orgss_active_membership_alert_button_2_text"
                    type="text" name="orgss_active_membership_alert_button_2_text" class="orgss_active_membership_alert_button_2_text" />
            
                <label style="margin-top: 1em;display: block;">Active Membership Button 2 URL</label>
                <input 
                  @keyup="SetFieldProperty('orgss_active_membership_alert_button_2_url', $el.value)" x-bind:value="orgss_active_membership_alert_button_2_url"
                  type="text" name="orgss_active_membership_alert_button_2_url" class="orgss_active_membership_alert_button_2_url" />
                <p style="margin-top: 2px;"><em>Set to PROCEED for this button to continue with the usual org selection actions, or BUTTON if you're going to do something fancy with it on the backend.</em></p>

                <label style="margin-top: 1em;display: block;">Active Membership Button 2 Style</label>
                <select
                  name="orgss_active_membership_alert_button_2_style"
                  class="orgss_active_membership_alert_button_2_style" 
                  x-model="orgss_active_membership_alert_button_2_style" 
                  x-init="SetFieldProperty('orgss_active_membership_alert_button_2_style', $el.value)" 
                  @change="SetFieldProperty('orgss_active_membership_alert_button_2_style', $el.value)" 
                  style="margin-bottom: 1em;"
                >
                  <option value="primary" selected>Primary</option>
                  <option value="secondary" selected>Secondary</option>
                  <option value="ghost" selected>Ghost</option>
                </select>

                <input 
                  @change="SetFieldProperty('orgss_active_membership_alert_button_2_new_tab', $el.checked)" x-bind:value="orgss_active_membership_alert_button_2_new_tab"
                  type="checkbox" id="orgss_active_membership_alert_button_2_new_tab" class="orgss_active_membership_alert_button_2_new_tab">
                <label for="orgss_active_membership_alert_button_2_new_tab" class="inline">Open Button 2 in New Tab?</label>


              </div>

            <input 
              @change="SetFieldProperty('orgss_grant_roster_man_on_purchase', $el.checked)" x-bind:value="orgss_grant_roster_man_on_purchase"
              type="checkbox" id="orgss_grant_roster_man_on_purchase" class="orgss_grant_roster_man_on_purchase">
					  <label for="orgss_grant_roster_man_on_purchase" class="inline">Grant roster management (membership_manager role for selected org) on next purchase?</label>
            <br />

            <input 
              @change="SetFieldProperty('orgss_grant_org_editor_on_select', $el.checked)" x-bind:value="orgss_grant_org_editor_on_select"
              type="checkbox" id="orgss_grant_org_editor_on_select" class="orgss_grant_org_editor_on_select">
					  <label for="orgss_grant_org_editor_on_select" class="inline">Grant org_editor role on selection (scoped to selected org)?</label>
            <br />

            <input 
              @change="SetFieldProperty('orgss_grant_org_editor_on_purchase', $el.checked)" x-bind:value="orgss_grant_org_editor_on_purchase"
              type="checkbox" id="orgss_grant_org_editor_on_purchase" class="orgss_grant_org_editor_on_purchase">
					  <label for="orgss_grant_org_editor_on_purchase" class="inline">Grant org_editor role for selected org on next purchase?</label>
            <br />

          </div>
          <div x-show=" searchMode == 'groups' " class="orgss-groups-settings">
            <div>Group settings coming soon.</div>
          </div>
        </div>

        <?php echo ob_get_clean(); ?>

        <?php
      }
    }

    public static function editor_script(){
      ?>
      <script>
      document.addEventListener('alpine:init', () => {
          Alpine.data('orgssData', () => ({
          searchMode: 'org',
          orgss_search_org_type: '',
          orgss_relationship_type_upon_org_creation: 'employee',
          orgss_relationship_mode: 'person_to_organization',
          orgss_new_org_type_override: '',
          orgss_org_term_singular: '',
          orgss_org_term_plural: '',
          orgss_no_results_message: '',
          orgss_checkbox_id_new_org: '',
          orgss_disable_org_creation: false,
          orgss_disable_selecting_orgs_with_active_membership: false,
          orgss_grant_roster_man_on_purchase: false,
          orgss_grant_org_editor_on_select: false,
          orgss_grant_org_editor_on_purchase: false,
          orgss_hide_remove_buttons: false,
          orgss_hide_select_buttons: false,
          orgss_display_removal_alert_message: false,
          orgss_active_membership_alert_title: '',
          orgss_active_membership_alert_body: '',
          orgss_active_membership_alert_button_1_text: '',
          orgss_active_membership_alert_button_1_url: '',
          orgss_active_membership_alert_button_1_style: 'primary',
          orgss_active_membership_alert_button_1_new_tab: false,
          orgss_active_membership_alert_button_2_text: '',
          orgss_active_membership_alert_button_2_url: '',
          orgss_active_membership_alert_button_2_style: 'secondary',
          orgss_active_membership_alert_button_2_new_tab: false,

          loadFieldSettings(event) {
            let fieldData = event.detail;
            
            if( Object.hasOwn(fieldData, 'orgss_search_org_type') ) {
              this.orgss_search_org_type = fieldData.orgss_search_org_type;
            }
            if( Object.hasOwn(fieldData, 'orgss_relationship_type_upon_org_creation') ) {
              if(fieldData.orgss_relationship_type_upon_org_creation) {
                this.orgss_relationship_type_upon_org_creation = fieldData.orgss_relationship_type_upon_org_creation;
              } else {
                this.orgss_relationship_type_upon_org_creation = 'employee';
              }
            }
            if( Object.hasOwn(fieldData, 'orgss_relationship_mode') ) {
              if(fieldData.orgss_relationship_mode) {
                this.orgss_relationship_mode = fieldData.orgss_relationship_mode;
              } else {
                this.orgss_relationship_mode = 'person_to_organization';
              }
            }
            if( Object.hasOwn(fieldData, 'orgss_new_org_type_override') ) {
              this.orgss_new_org_type_override = fieldData.orgss_new_org_type_override;
            }
            if( Object.hasOwn(fieldData, 'orgss_org_term_singular') ) {
              if(fieldData.orgss_org_term_singular) {
                this.orgss_org_term_singular = fieldData.orgss_org_term_singular;
              } else {
                this.orgss_org_term_singular = 'Organization';
              }
            }
            if( Object.hasOwn(fieldData, 'orgss_org_term_plural') ) {
              if(fieldData.orgss_org_term_plural) {
                this.orgss_org_term_plural = fieldData.orgss_org_term_plural;
              } else {
                this.orgss_org_term_plural = 'Organizations';
              }
            }
            if( Object.hasOwn(fieldData, 'orgss_no_results_message') ) {
              this.orgss_no_results_message = fieldData.orgss_no_results_message;
            }
            if( Object.hasOwn(fieldData, 'orgss_checkbox_id_new_org') ) {
              this.orgss_checkbox_id_new_org = fieldData.orgss_checkbox_id_new_org;
            }
            if( Object.hasOwn(fieldData, 'orgss_disable_org_creation') ) {
              // Handle checkboxes slightly differently
              this.orgss_disable_org_creation = fieldData.orgss_disable_org_creation ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_disable_selecting_orgs_with_active_membership') ) {
              // Handle checkboxes slightly differently
              this.orgss_disable_selecting_orgs_with_active_membership = fieldData.orgss_disable_selecting_orgs_with_active_membership ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_title') ) {
              this.orgss_active_membership_alert_title = fieldData.orgss_active_membership_alert_title;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_body') ) {
              this.orgss_active_membership_alert_body = fieldData.orgss_active_membership_alert_body;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_1_text') ) {
              this.orgss_active_membership_alert_button_1_text = fieldData.orgss_active_membership_alert_button_1_text;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_1_url') ) {
              this.orgss_active_membership_alert_button_1_url = fieldData.orgss_active_membership_alert_button_1_url;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_1_style') ) {
              this.orgss_active_membership_alert_button_1_style = fieldData.orgss_active_membership_alert_button_1_style;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_1_new_tab') ) {
              // Handle checkboxes slightly differently
              this.orgss_active_membership_alert_button_1_new_tab = fieldData.orgss_active_membership_alert_button_1_new_tab ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_2_text') ) {
              this.orgss_active_membership_alert_button_2_text = fieldData.orgss_active_membership_alert_button_2_text;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_2_url') ) {
              this.orgss_active_membership_alert_button_2_url = fieldData.orgss_active_membership_alert_button_2_url;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_2_style') ) {
              this.orgss_active_membership_alert_button_2_style = fieldData.orgss_active_membership_alert_button_2_style;
            }
            if( Object.hasOwn(fieldData, 'orgss_active_membership_alert_button_2_new_tab') ) {
              // Handle checkboxes slightly differently
              this.orgss_active_membership_alert_button_2_new_tab = fieldData.orgss_active_membership_alert_button_2_new_tab ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_grant_roster_man_on_purchase') ) {
              // Handle checkboxes slightly differently
              this.orgss_grant_roster_man_on_purchase = fieldData.orgss_grant_roster_man_on_purchase ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_grant_org_editor_on_select') ) {
              // Handle checkboxes slightly differently
              this.orgss_grant_org_editor_on_select = fieldData.orgss_grant_org_editor_on_select ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_grant_org_editor_on_purchase') ) {
              // Handle checkboxes slightly differently
              this.orgss_grant_org_editor_on_purchase = fieldData.orgss_grant_org_editor_on_purchase ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_hide_remove_buttons') ) {
              // Handle checkboxes slightly differently
              this.orgss_hide_remove_buttons = fieldData.orgss_hide_remove_buttons ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_hide_select_buttons') ) {
              // Handle checkboxes slightly differently
              this.orgss_hide_select_buttons = fieldData.orgss_hide_select_buttons ? true : false;
            }
            if( Object.hasOwn(fieldData, 'orgss_display_removal_alert_message') ) {
              // Handle checkboxes slightly differently
              this.orgss_display_removal_alert_message = fieldData.orgss_display_removal_alert_message ? true : false;
            }
            
            
          },
        }))
      });

      // Catching GF event via jQuery (which it uses) and re-dispatching needed values for easier use
      jQuery(document).on('gform_load_field_settings', (event, field, form) => {
        let detailPayload = {
          orgss_search_mode: rgar( field, 'orgss_search_mode' ),
          orgss_search_org_type: rgar( field, 'orgss_search_org_type' ),
          orgss_relationship_type_upon_org_creation: rgar( field, 'orgss_relationship_type_upon_org_creation' ),
          orgss_relationship_mode: rgar( field, 'orgss_relationship_mode' ),
          orgss_new_org_type_override: rgar( field, 'orgss_new_org_type_override' ),
          orgss_org_term_singular: rgar( field, 'orgss_org_term_singular' ),
          orgss_org_term_plural: rgar( field, 'orgss_org_term_plural' ),
          orgss_no_results_message: rgar( field, 'orgss_no_results_message' ),
          orgss_checkbox_id_new_org: rgar( field, 'orgss_checkbox_id_new_org' ),
          orgss_disable_org_creation: rgar( field, 'orgss_disable_org_creation' ),
          orgss_disable_selecting_orgs_with_active_membership: rgar( field, 'orgss_disable_selecting_orgs_with_active_membership' ),
          orgss_grant_roster_man_on_purchase: rgar( field, 'orgss_grant_roster_man_on_purchase' ),
          orgss_grant_org_editor_on_select: rgar( field, 'orgss_grant_org_editor_on_select' ),
          orgss_grant_org_editor_on_purchase: rgar( field, 'orgss_grant_org_editor_on_purchase' ),
          orgss_hide_remove_buttons: rgar( field, 'orgss_hide_remove_buttons' ),
          orgss_hide_select_buttons: rgar( field, 'orgss_hide_select_buttons' ),
          orgss_display_removal_alert_message: rgar( field, 'orgss_display_removal_alert_message' ),
          orgss_active_membership_alert_title: rgar( field, 'orgss_active_membership_alert_title' ),
          orgss_active_membership_alert_body: rgar( field, 'orgss_active_membership_alert_body' ),
          orgss_active_membership_alert_button_1_text: rgar( field, 'orgss_active_membership_alert_button_1_text' ),
          orgss_active_membership_alert_button_1_url: rgar( field, 'orgss_active_membership_alert_button_1_url' ),
          orgss_active_membership_alert_button_1_style: rgar( field, 'orgss_active_membership_alert_button_1_style' ),
          orgss_active_membership_alert_button_1_new_tab: rgar( field, 'orgss_active_membership_alert_button_1_new_tab' ),
          orgss_active_membership_alert_button_2_text: rgar( field, 'orgss_active_membership_alert_button_2_text' ),
          orgss_active_membership_alert_button_2_url: rgar( field, 'orgss_active_membership_alert_button_2_url' ),
          orgss_active_membership_alert_button_2_style: rgar( field, 'orgss_active_membership_alert_button_2_style' ),
          orgss_active_membership_alert_button_2_new_tab: rgar( field, 'orgss_active_membership_alert_button_2_new_tab' ),
        };
        // console.log('Detail payload:');
        // console.log(detailPayload);
        // console.log('Field:');
        // console.log(field);
        let customEvent = new CustomEvent("gf-orgss-field-settings", {
          detail: detailPayload
        });

        window.dispatchEvent(customEvent);
      });
    </script>

    <?php
    }
 
		public function get_form_editor_field_title() {
      return esc_attr__('Wicket Org Search/Select', 'wicket-gf');
    }

    // Move the field to 'advanced fields'
    public function get_form_editor_button() {
      return [
        'group' => 'advanced_fields',
        'text'  => $this->get_form_editor_field_title(),
      ];
    }

    // Declare that this field supports conditional logic
    public function is_conditional_logic_supported() {
      return true;
    }

    function get_form_editor_field_settings() {
      return [
        'label_setting',
        'description_setting',
        'rules_setting',
        'error_message_setting',
        'css_class_setting',
        'conditional_logic_field_setting',
        'label_placement_setting',
      ];
    }

    // Render the field
    public function get_field_input($form, $value = '', $entry = null) {
      if ( $this->is_form_editor() ) {
        return '<p>Org Search/Select UI will show here on the frontend.</p><p><strong>Note:</strong> This 
        element does <strong><em>not</em></strong> display correctly in the GF Preview mode and will appear broken; it\'s recommended 
        to create a test page with this form on it to properly test the Org Search/Select functionality.</p>';
      }

      $id = (int) $this->id;

      $search_mode                                   = 'org';
      $search_org_type                               = '';
      $relationship_type_upon_org_creation           = 'employee';
      $relationship_mode                             = 'person_to_organization';
      $new_org_type_override                         = '';
      $org_term_singular                             = 'Organization';
      $org_term_plural                               = 'Organizations';
      $orgss_no_results_message                      = '';
      $disable_org_creation                          = false;
      $checkbox_id_new_org                           = '';
      $disable_selecting_orgs_with_active_membership = false;
      $grant_roster_man_on_purchase                  = false;
      $orgss_grant_org_editor_on_select              = false;
      $orgss_grant_org_editor_on_purchase            = false;
      $orgss_hide_remove_buttons                     = false;
      $orgss_hide_select_buttons                     = false;
      $orgss_display_removal_alert_message           = false;
      $orgss_active_membership_alert_title           = '';
      $orgss_active_membership_alert_body            = '';
      $orgss_active_membership_alert_button_1_text   = '';
      $orgss_active_membership_alert_button_1_url    = '';
      $orgss_active_membership_alert_button_1_style  = '';
      $orgss_active_membership_alert_button_1_new_tab = false;
      $orgss_active_membership_alert_button_2_text   = '';
      $orgss_active_membership_alert_button_2_url    = '';
      $orgss_active_membership_alert_button_2_style  = '';
      $orgss_active_membership_alert_button_2_new_tab = false;

      //wicket_gf_write_log($form, true);

      foreach( $form['fields'] as $field ) {
        if( gettype( $field ) == 'object' ) {
          if( get_class( $field ) == 'GFWicketFieldOrgSearchSelect' ) {
            if( $field->id == $id ) {
              if( isset( $field->orgss_search_mode ) ) {
                $search_mode = $field->orgss_search_mode;
              }
              if( isset( $field->orgss_search_org_type ) ) {
                $search_org_type = $field->orgss_search_org_type;
              }
              if( isset( $field->orgss_relationship_type_upon_org_creation ) ) {
                $relationship_type_upon_org_creation = $field->orgss_relationship_type_upon_org_creation;
              }
              if( isset( $field->orgss_relationship_mode ) ) {
                $relationship_mode = $field->orgss_relationship_mode;
              }
              if( isset( $field->orgss_new_org_type_override ) ) {
                $new_org_type_override = $field->orgss_new_org_type_override;
              }
              if( isset( $field->orgss_org_term_singular ) ) {
                $org_term_singular = $field->orgss_org_term_singular;
              }
              if( isset( $field->orgss_org_term_plural ) ) {
                $org_term_plural = $field->orgss_org_term_plural;
              }
              if( isset( $field->orgss_no_results_message ) ) {
                $orgss_no_results_message = $field->orgss_no_results_message;
              }
              if( isset( $field->orgss_disable_org_creation ) ) {
                $disable_org_creation = $field->orgss_disable_org_creation;
              }
              if( isset( $field->orgss_checkbox_id_new_org ) ) {
                $checkbox_id_new_org = $field->orgss_checkbox_id_new_org;
              }
              if( isset( $field->orgss_disable_selecting_orgs_with_active_membership ) ) {
                $disable_selecting_orgs_with_active_membership = $field->orgss_disable_selecting_orgs_with_active_membership;
              }
              if( isset( $field->orgss_active_membership_alert_title ) ) {
                $orgss_active_membership_alert_title = $field->orgss_active_membership_alert_title;
              }
              if( isset( $field->orgss_active_membership_alert_body ) ) {
                $orgss_active_membership_alert_body = $field->orgss_active_membership_alert_body;
              }
              if( isset( $field->orgss_active_membership_alert_button_1_text ) ) {
                $orgss_active_membership_alert_button_1_text = $field->orgss_active_membership_alert_button_1_text;
              }
              if( isset( $field->orgss_active_membership_alert_button_1_url ) ) {
                $orgss_active_membership_alert_button_1_url = $field->orgss_active_membership_alert_button_1_url;
              }
              if( isset( $field->orgss_active_membership_alert_button_1_style ) ) {
                $orgss_active_membership_alert_button_1_style = $field->orgss_active_membership_alert_button_1_style;
              }
              if( isset( $field->orgss_active_membership_alert_button_1_new_tab ) ) {
                $orgss_active_membership_alert_button_1_new_tab = $field->orgss_active_membership_alert_button_1_new_tab;
              }
              if( isset( $field->orgss_active_membership_alert_button_2_text ) ) {
                $orgss_active_membership_alert_button_2_text = $field->orgss_active_membership_alert_button_2_text;
              }
              if( isset( $field->orgss_active_membership_alert_button_2_url ) ) {
                $orgss_active_membership_alert_button_2_url = $field->orgss_active_membership_alert_button_2_url;
              }
              if( isset( $field->orgss_active_membership_alert_button_2_style ) ) {
                $orgss_active_membership_alert_button_2_style = $field->orgss_active_membership_alert_button_2_style;
              }
              if( isset( $field->orgss_active_membership_alert_button_2_new_tab ) ) {
                $orgss_active_membership_alert_button_2_new_tab = $field->orgss_active_membership_alert_button_2_new_tab;
              }
              if( isset( $field->orgss_grant_roster_man_on_purchase ) ) {
                $grant_roster_man_on_purchase = $field->orgss_grant_roster_man_on_purchase;
              }
              if( isset( $field->orgss_grant_org_editor_on_select ) ) {
                $orgss_grant_org_editor_on_select = $field->orgss_grant_org_editor_on_select;
              }
              if( isset( $field->orgss_grant_org_editor_on_purchase ) ) {
                $orgss_grant_org_editor_on_purchase = $field->orgss_grant_org_editor_on_purchase;
              }
              if( isset( $field->orgss_hide_remove_buttons ) ) {
                $orgss_hide_remove_buttons = $field->orgss_hide_remove_buttons;
              }
              if( isset( $field->orgss_hide_select_buttons ) ) {
                $orgss_hide_select_buttons = $field->orgss_hide_select_buttons;
              }
              if( isset( $field->orgss_display_removal_alert_message ) ) {
                $orgss_display_removal_alert_message = $field->orgss_display_removal_alert_message;
              }
            }
          }
        }
      }

      if( component_exists('org-search-select') ) {
        $component_output = get_component( 'org-search-select', [ 
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
          'form_id'                                       => isset($form['id']) ? $form['id'] : 0,
        ], false );
        
        // Hidden field for Gravity Forms conditional logic
        $hidden_field = sprintf(
          '<input type="hidden" name="input_%d" id="input_%s_%d" value="%s" class="gf_org_search_select_input" />',
          $id,
          isset($form['id']) ? $form['id'] : 0,
          $id,
          esc_attr($value)
        );
        
        return '<div class="gform-theme__disable gform-theme__disable-reset">' . $component_output . $hidden_field . '</div>';
      } else {
        return '<div class="gform-theme__disable gform-theme__disable-reset"><p>Org search/select component is missing. Please update the Wicket Base Plugin.</p></div>';
      } 
    }

    // Make sure the field value is properly recognized for conditional logic
    public function get_value_submission($field_values, $get_from_post_global_var = true) {
      $input_name = 'input_' . $this->id;
      
      if ($get_from_post_global_var) {
        // Get value from the $_POST
        $value = rgpost($input_name);
      } else {
        // Get value from the provided array
        $value = isset($field_values[$input_name]) ? $field_values[$input_name] : '';
      }
      
      return $value;
    }
    
    // This function is needed to ensure field inputs are properly processed for conditional logic
    public function get_field_value($value, $form, $input_name, $lead_id, $lead) {
      if (empty($value)) {
        return '';
      }
      return $value;
    }
    
    // Helper method to ensure conditional logic sees the value correctly
    public function is_value_submission_empty($form_id) {
      $input_name = 'input_' . $this->id;
      $value = rgpost($input_name);
      return empty($value);
    }

    // Specify which event to listen for with conditional logic
    public function get_conditional_logic_event($event) {
      return 'change';
    }
    
    // Return the current field value for conditional logic
    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead) {
      return $value;
    }
    
    // Make the field compatible with Gravity Forms entry details
    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen') {
      if (empty($value)) {
        return '';
      }
      
      // You could format the UUID to be more readable here if desired
      return esc_html($value);
    }
    
    // Ensure the field works properly in entry list views
    public function get_value_entry_list($value, $entry, $field_id, $columns, $form) {
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
	GF_Fields::register(new GFWicketFieldOrgSearchSelect());
}