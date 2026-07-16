/**
 * Attaches WP-core CodeMirror (with JSON lint) to the MDP Widget Config
 * textareas in the Gravity Forms form editor. Legacy JSON settings
 * (MDP JSON Fields, Required Resources) intentionally stay plain textareas.
 * Cosmetic only — the textarea stays the source of truth for
 * SetFieldProperty/validation, which keep operating on plain
 * `input`/`keyup` events fired on the textarea.
 */
(function($) {
    if (typeof wp === 'undefined' || !wp.codeEditor || typeof WicketGfJsonEditorSettings === 'undefined') {
        return;
    }

    if (!WicketGfJsonEditorSettings || Object.keys(WicketGfJsonEditorSettings).length === 0) {
        // wp_enqueue_code_editor() returned false (e.g. user disabled syntax highlighting
        // in their profile) — fall back to the plain textarea, no CodeMirror.
        return;
    }

    var JSON_TEXTAREA_IDS = [
        'wwidget_profile_mdp_json_config_input',
        'wwidget_org_profile_mdp_json_config_input',
    ];

    var attachedEditors = {};

    // Exposed so other scripts (e.g. the migrate-link handler in
    // WidgetProfile.php / WidgetProfileOrg.php) can push a programmatic value
    // change into CodeMirror's own buffer — writing to the underlying
    // textarea's .val() alone does not update what CodeMirror displays, since
    // it renders from its own internal doc, not the (now-hidden) textarea.
    window.wicketGfSyncCodeMirrorValue = function(id, value) {
        var instance = attachedEditors[id];
        if (instance && instance.codemirror) {
            instance.codemirror.setValue(value || '');
            instance.codemirror.refresh();
        }
    };

    function syncTextareaEvents(textarea, cm) {
        cm.on('change', function() {
            cm.save();
            var event = document.createEvent('Event');
            event.initEvent('input', true, true);
            textarea.dispatchEvent(event);
        });
    }

    function attachEditor(id) {
        var $textarea = $('#' + id);
        if (!$textarea.length || attachedEditors[id]) {
            return;
        }

        var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
        editorSettings.codemirror = _.extend({}, editorSettings.codemirror, WicketGfJsonEditorSettings.codemirror, {
            indentWithTabs: false,
            indentUnit: 2,
            foldGutter: true,
            gutters: (WicketGfJsonEditorSettings.codemirror.gutters || []).concat(['CodeMirror-foldgutter']),
        });

        var instance = wp.codeEditor.initialize($textarea, editorSettings);
        attachedEditors[id] = instance;
        syncTextareaEvents($textarea.get(0), instance.codemirror);

        // GF re-renders the settings panel on field switch; keep the CodeMirror
        // view synced with whatever value GF just placed in the textarea.
        instance.codemirror.refresh();
    }

    function attachAllVisible() {
        JSON_TEXTAREA_IDS.forEach(function(id) {
            var $textarea = $('#' + id);
            if ($textarea.length && $textarea.is(':visible')) {
                if (attachedEditors[id]) {
                    // Field settings panel re-rendered for a different field of the
                    // same type: refresh value + view instead of re-initializing.
                    attachedEditors[id].codemirror.setValue($textarea.val() || '');
                    attachedEditors[id].codemirror.refresh();
                } else {
                    attachEditor(id);
                }
            }
        });
    }

    $(document).on('gform_load_field_settings', function() {
        // Defer to the next tick so GF has finished populating the textarea
        // value via jQuery .val() before CodeMirror reads it.
        setTimeout(attachAllVisible, 0);
    });
})(jQuery);
