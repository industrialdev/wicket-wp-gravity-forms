/**
 * Wicket GF Form Editor JSON Guard
 *
 * Blocks the Gravity Forms form-editor save when any Wicket profile widget
 * field (Org or Individual) has invalid JSON in a JSON-expecting setting:
 * Required Resources, MDP JSON Fields, or MDP Widget Config.
 *
 * How it hooks in: GF's form-editor save() runs window.UpdateFormObject()
 * (which syncs the editor into window.form.fields) and then calls
 * window.ValidateForm(); if ValidateForm() returns false the save is aborted
 * (SaveAborted). We wrap window.ValidateForm so our JSON check runs as part
 * of that same gate. Wrapping once on load is enough: ValidateForm is a
 * page-load global that GF does not redefine during an AJAX save.
 *
 * Scope: JSON SYNTAX only. A valid JSON string does not mean the keys and
 * values are valid for a given MDP instance (resource types, field keys).
 * The field help text states that distinction; this guard cannot enforce it.
 */
(function () {
    'use strict';

    if (window.wicketGfJsonGuardInstalled) {
        return;
    }

    var BOX_LABELS = {
        wwidget_org_profile_required_resources: 'Required Resources',
        wwidget_org_profile_mdp_json_fields: 'MDP JSON Fields',
        wwidget_org_profile_mdp_json_config: 'MDP Widget Config',
        wwidget_profile_required_resources: 'Required Resources',
        wwidget_profile_mdp_json_fields: 'MDP JSON Fields',
        wwidget_profile_mdp_json_config: 'MDP Widget Config'
    };

    var FIELD_TYPE_PROPS = {
        wicket_widget_profile_org: [
            'wwidget_org_profile_required_resources',
            'wwidget_org_profile_mdp_json_fields',
            'wwidget_org_profile_mdp_json_config'
        ],
        wicket_widget_profile_individual: [
            'wwidget_profile_required_resources',
            'wwidget_profile_mdp_json_fields',
            'wwidget_profile_mdp_json_config'
        ]
    };

    function isValidJson(value) {
        if (value === null || value === undefined) {
            return true;
        }
        value = String(value);
        if (!value.trim()) {
            return true; // empty is allowed: falls back to defaults / legacy
        }
        try {
            JSON.parse(value);
            return true;
        } catch (e) {
            return false;
        }
    }

    function fieldLabel(field) {
        if (!field) {
            return 'Unknown field';
        }
        return field.label || ('Field #' + field.id);
    }

    function findInvalidJsonFields() {
        var offenders = [];
        if (!window.form || !window.form.fields) {
            return offenders;
        }
        window.form.fields.forEach(function (field) {
            var props = FIELD_TYPE_PROPS[field && field.type];
            if (!props) {
                return;
            }
            props.forEach(function (prop) {
                if (!isValidJson(field[prop])) {
                    offenders.push({
                        fieldId: field.id,
                        fieldLabel: fieldLabel(field),
                        box: BOX_LABELS[prop] || prop
                    });
                }
            });
        });
        return offenders;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function ensureBanner() {
        var banner = document.getElementById('wicket-gf-json-guard-banner');
        if (banner) {
            return banner;
        }
        banner = document.createElement('div');
        banner.id = 'wicket-gf-json-guard-banner';
        banner.style.cssText = [
            'display:none',
            'margin:0 0 16px',
            'padding:16px 20px',
            'background:#fff',
            'border:1px solid #d63638',
            'border-left:4px solid #d63638',
            'border-radius:4px',
            'color:#1d2327'
        ].join(';');
        var anchor = document.querySelector('#wpbody-content .wrap') ||
            document.querySelector('#wpbody-content') ||
            document.body;
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(banner, anchor);
        } else {
            document.body.insertBefore(banner, document.body.firstChild);
        }
        return banner;
    }

    function showBlockMessage(offenders) {
        var banner = ensureBanner();
        var items = offenders.map(function (o) {
            return '<li><strong>' + escapeHtml(o.fieldLabel) + '</strong> (' + escapeHtml(o.box) + ')</li>';
        }).join('');

        banner.innerHTML =
            '<div style="font-weight:600;font-size:14px;margin-bottom:6px;color:#b32d2e;">' +
            'Cannot save the form: one or more Wicket widget fields contain invalid JSON' +
            '</div>' +
            '<div style="font-size:13px;margin-bottom:8px;">' +
            'Fix the invalid JSON in the fields below, then save again. Invalid JSON cannot be saved. ' +
            'Note: valid JSON only means the syntax is correct; it does not guarantee the keys and values are valid for your MDP instance.' +
            '</div>' +
            '<ul style="margin:0 0 0 18px;font-size:13px;">' + items + '</ul>';

        banner.style.display = 'block';
        banner.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Open the first offending field's settings so the editor can fix it.
        // GF selects a field by clicking its row (#field_<id>); we mirror that.
        var first = offenders[0];
        if (first && first.fieldId) {
            var row = document.getElementById('field_' + first.fieldId);
            if (row && typeof row.click === 'function') {
                row.click();
            }
        }
    }

    function hideBanner() {
        var banner = document.getElementById('wicket-gf-json-guard-banner');
        if (banner) {
            banner.style.display = 'none';
        }
    }

    function installGate() {
        if (window.wicketGfJsonGuardInstalled) {
            return;
        }
        if (typeof window.ValidateForm !== 'function') {
            // GF defines ValidateForm during editor init; retry until present.
            return window.setTimeout(installGate, 200);
        }
        window.wicketGfJsonGuardInstalled = true;

        var originalValidateForm = window.ValidateForm;
        window.ValidateForm = function () {
            var offenders = findInvalidJsonFields();
            if (offenders.length > 0) {
                showBlockMessage(offenders);
                return false; // GF aborts the save when ValidateForm() is false
            }
            hideBanner();
            return originalValidateForm ? originalValidateForm.apply(this, arguments) : true;
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installGate);
    } else {
        installGate();
    }
})();
