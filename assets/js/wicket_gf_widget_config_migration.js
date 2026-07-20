/**
 * TEMPORARY MIGRATION SCAFFOLDING — safe to delete, file and all, once no
 * Gravity Forms form anywhere has a populated legacy "MDP JSON Fields" value
 * left to migrate.
 *
 * Both Wicket widget-profile fields (individual: wwidget_profile_mdp_json_fields,
 * org: wwidget_org_profile_mdp_json_fields) predate the newer open-ended
 * "MDP Widget Config" setting (wwidget_profile_mdp_json_config /
 * wwidget_org_profile_mdp_json_config). The legacy fields still work — a form
 * saved with only a legacy value renders exactly as before, with zero help
 * from this script, via the fallback path in WidgetProfile.php /
 * WidgetProfileOrg.php's get_field_input(). They're deprecated: once the
 * config box is set, the legacy value is ignored entirely (not merged).
 *
 * This script exists purely to make that migration painless for form
 * builders who already have a legacy value saved: it injects a `Replace
 * "fields" in MDP Widget Config` link under each legacy field's settings box.
 * Clicking it JSON-parses the legacy value and writes it under the `fields`
 * key of whatever's already in the Widget Config box, replacing any existing
 * value under that key wholesale (not merging it) while leaving other keys
 * already typed there (e.g. resourceLimits) untouched, then clears + hides
 * the legacy box.
 *
 * None of this is enforced server-side — it's pure editor-UX sugar on top of
 * the precedence rule that already lives in get_field_input() for both
 * fields. Deleting this file removes the migrate links and the legacy-box
 * auto-hide-when-empty behavior; it does not change what either field renders
 * on the frontend.
 */
(function($) {
    var FIELD_CONFIGS = {
        wicket_widget_profile_individual: {
            legacyInputId: 'wwidget_profile_mdp_json_fields_input',
            legacyLiId: 'wwidget_profile_mdp_json_fields_li',
            legacyProperty: 'wwidget_profile_mdp_json_fields',
            configInputId: 'wwidget_profile_mdp_json_config_input',
            configProperty: 'wwidget_profile_mdp_json_config',
            configErrorClass: 'wwidget_profile_mdp_json_config_error',
            migrateLinkId: 'wwidget_profile_mdp_json_migrate_link',
        },
        wicket_widget_profile_org: {
            legacyInputId: 'wwidget_org_profile_mdp_json_fields_input',
            legacyLiId: 'wwidget_org_profile_mdp_json_fields_li',
            legacyProperty: 'wwidget_org_profile_mdp_json_fields',
            configInputId: 'wwidget_org_profile_mdp_json_config_input',
            configProperty: 'wwidget_org_profile_mdp_json_config',
            configErrorClass: 'wwidget_org_profile_mdp_json_config_error',
            migrateLinkId: 'wwidget_org_profile_mdp_json_migrate_link',
        },
    };

    function validateConfigJson(value, $errorEl, $textarea) {
        if (!value || !value.trim()) {
            $errorEl.hide();
            $textarea.removeClass('wicket-mdp-json-invalid');
            return;
        }
        try {
            JSON.parse(value);
            $errorEl.hide();
            $textarea.removeClass('wicket-mdp-json-invalid');
        } catch (e) {
            $errorEl.show();
            $textarea.addClass('wicket-mdp-json-invalid');
        }
    }

    // The legacy field's own hide-when-empty lifecycle is owned by
    // WidgetProfile.php / WidgetProfileOrg.php's inline settings-panel script
    // (toggleLegacyFieldVisibility / toggleOrgLegacyFieldVisibility) — this
    // script only needs to trigger it after a successful migrate, via the
    // legacy input's own change event, so it doesn't duplicate that logic.
    function hideLegacyFieldAfterMigrate(cfg) {
        $('#' + cfg.legacyInputId).trigger('input').trigger('change');
    }

    function bindMigrateLink(cfg) {
        var $link = $('#' + cfg.migrateLinkId);
        if ($link.data('bound')) {
            return;
        }
        $link.on('click', function(e) {
            e.preventDefault();
            var $legacyInput = $('#' + cfg.legacyInputId);
            var legacyVal = $legacyInput.val();
            if (!legacyVal || !legacyVal.trim()) {
                return;
            }
            var parsedFields;
            try {
                parsedFields = JSON.parse(legacyVal);
            } catch (err) {
                return;
            }

            var $configInput = $('#' + cfg.configInputId);
            var existingConfigVal = $configInput.val();
            var configObj = {};
            if (existingConfigVal && existingConfigVal.trim()) {
                try {
                    configObj = JSON.parse(existingConfigVal);
                } catch (err) {
                    configObj = {};
                }
            }
            configObj.fields = parsedFields;

            var wrapped = JSON.stringify(configObj, null, 2);
            $configInput.val(wrapped);
            SetFieldProperty(cfg.configProperty, wrapped);
            validateConfigJson(wrapped, $('.' + cfg.configErrorClass), $configInput);
            if (window.wicketGfSyncCodeMirrorValue) {
                window.wicketGfSyncCodeMirrorValue(cfg.configInputId, wrapped);
            }

            $legacyInput.val('');
            SetFieldProperty(cfg.legacyProperty, '');
            hideLegacyFieldAfterMigrate(cfg);
        }).data('bound', true);
    }

    $(document).on('gform_load_field_settings', function(event, field) {
        var cfg = FIELD_CONFIGS[field.type];
        if (!cfg) {
            return;
        }
        bindMigrateLink(cfg);
    });
})(jQuery);
