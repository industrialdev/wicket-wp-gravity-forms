// ---------------------------------
// Alpine
// ---------------------------------

import Alpine from "alpinejs";

if(!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}

// -----------
// Editor code
// ----------- 

// Check if we're currently looking at one of our elements, and if so show the settings for it,
// otherwise hide the settings
let orgss_settings_panes = document.querySelectorAll('.wicket_orgss_setting');
let wwidget_ai_settings = document.querySelectorAll('.wicket_widget_ai_setting');
let wwidget_org_profile_settings = document.querySelectorAll('.wicket_widget_org_profile_setting');
let wwidget_person_pref_settings = document.querySelectorAll('.wicket_widget_person_prefs_setting');
let gf_fields_wrapper = document.querySelector('#gform_fields');
let gf_edit_field_button = document.querySelector('.gfield-field-action.gfield-edit');
let wicket_global_settings_hide_label = document.querySelector('.wicket_global_custom_settings #hide_label');

jQuery(document).on('gform_load_field_settings', conditionallyShowElementControls);
gf_fields_wrapper.addEventListener('click', conditionallyShowElementControls);
gf_edit_field_button.addEventListener('click', conditionallyShowElementControls);

function conditionallyShowElementControls (event, field, form) {
    let selectedField = GetSelectedField(); // GF editor function
    //console.log(event.target);
    //console.log(selectedField);

    // Org search/select
    if( selectedField.type == "wicket_org_search_select" ) {
        for (let orgss_settings_pane of orgss_settings_panes) {
            orgss_settings_pane.style.display = "block";
        }
    } else {
        for (let orgss_settings_pane of orgss_settings_panes) {
            orgss_settings_pane.style.display = "none";
        }
    }
    // AI widget
    if( selectedField.type == "wicket_widget_ai" ) {
        for (let wwidget_ai_setting of wwidget_ai_settings) {
            wwidget_ai_setting.style.display = "block";
        }
    } else {
        for (let wwidget_ai_setting of wwidget_ai_settings) {
            wwidget_ai_setting.style.display = "none";
        }
    }
    // Org profile widget
    if( selectedField.type == "wicket_widget_profile_org" ) {
        for (let wwidget_org_profile_setting of wwidget_org_profile_settings) {
            wwidget_org_profile_setting.style.display = "block";
        }
    } else {
        for (let wwidget_org_profile_setting of wwidget_org_profile_settings) {
            wwidget_org_profile_setting.style.display = "none";
        }
    }
    // Person pref widget
    if( selectedField.type == "wicket_widget_prefs" ) {
        for (let wwidget_person_pref_setting of wwidget_person_pref_settings) {
            wwidget_person_pref_setting.style.display = "block";
        }
    } else {
        for (let wwidget_person_pref_setting of wwidget_person_pref_settings) {
            wwidget_person_pref_setting.style.display = "none";
        }
    }

    // Reload values of global custom settings on tab change so they're correctly reflected
    // Using try/catch in case property doesn't exist on GF object
    try {
        if( rgar( field, 'hide_label' ) ) {
            wicket_global_settings_hide_label.checked = true;
        }
    }
    catch(err) {
        wicket_global_settings_hide_label.checked = false;
    }
                        
}
