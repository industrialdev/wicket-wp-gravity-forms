---
title: "Field Architecture"
audience: [developer, agent]
php_class: Wicket_Gf_Main
source_files: [
  "includes/class-gf-field-org-search-select.php",
  "includes/class-gf-field-widget-profile.php",
  "includes/class-gf-field-widget-profile-org.php",
  "includes/class-gf-field-widget-additional-info.php",
  "includes/class-gf-field-widget-prefs.php",
  "includes/class-gf-field-data-bind-hidden.php",
  "includes/class-gf-field-api-data-bind.php",
  "includes/class-gf-field-user-mdp-tags.php",
  "includes/class-gf-mapping-addon.php"
]
---

# Field Architecture

Custom fields extend `GF_Field` and are registered in `GF_Fields`. Each field type lives in its own file under `includes/`.

## Registering Fields

`Wicket_Gf_Main::register_custom_fields()` calls `GF_Fields::register()` for each field:

```php
GF_Fields::register(new GFWicketFieldOrgSearchSelect());
GF_Fields::register(new GFWicketFieldUserMdpTags());
GF_Fields::register(new GFWicketFieldWidgetProfile());
GF_Fields::register(new GFDataBindHiddenField());
GF_Fields::register(new GFApiDataBindField());
GF_Fields::register(new GFWicketFieldWidgetProfileOrg());
GF_Fields::register(new GFWicketFieldWidgetAdditionalInfo());
GF_Fields::register(new GFWicketFieldWidgetPrefs());
```

This happens on the `gform_loaded` hook (Gravity Forms' own lifecycle hook).

## Base Pattern

Each field extends `GF_Field`:

```php
class GFWicketFieldWidgetProfile extends GF_Field
{
    public $type = 'wicket_widget_profile_individual';

    public function get_form_editor_field_title()
    {
        return esc_attr__('Wicket Widget: Profile', 'wicket-gf');
    }

    public function get_form_editor_button()
    {
        return [
            'group' => 'wicket_fields', // Appears under the Wicket group
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    public function get_form_editor_field_settings()
    {
        // Add settings shown in the form editor sidebar
    }
}
```

## Field Groups

The field picker group is registered in `plugin_setup()` via `gform_add_field_buttons`:

```php
add_filter('gform_add_field_buttons', function ($field_groups) {
    $field_groups[] = [
        'name'   => 'wicket_fields',
        'label'  => __('Wicket', 'wicket-gf'),
        'fields' => [ /* button definitions */ ],
    ];
    return $field_groups;
});
```

## Custom Settings

Per-field settings are registered in `register_field_settings()` at position 25 (after the field label). Each field type can opt in:

```php
public function register_field_settings($position, $form_id)
{
    if (class_exists('GFWicketFieldOrgSearchSelect')) {
        GFWicketFieldOrgSearchSelect::custom_settings($position, $form_id);
    }
    // ...
}
```

## Widget Fields

Fields prefixed `wicket_widget_*` render a full Wicket UI component (loaded from `widgets.js`). They require the current user's context (`personUuid`) and optionally an `orgUuid` from an earlier Org. Search field on multi-page forms.

The `gf_custom_pre_render` filter passes `org_uuid` from the POST data of previous pages to these fields.

## MDP Widget Config (open-ended passthrough)

`GFWicketFieldWidgetProfile` (individual profile field, `src/Fields/WidgetProfile.php`) and
`GFWicketFieldWidgetProfileOrg` (org profile field, `src/Fields/WidgetProfileOrg.php`) each
carry a `*_mdp_json_config` property (`wwidget_profile_mdp_json_config` /
`wwidget_org_profile_mdp_json_config`) — a JSON textarea forwarded verbatim as the
`widget_config` arg to the base plugin's `widget-profile-individual` /
`widget-profile-org` components, which pass it through to the MDP widget
(`createPersonProfile` / `editOrganizationProfile`) after stripping a small blocklist of
server-owned connection keys (`rootEl`, `apiRoot`, `accessToken`, `personId`/`orgId`,
plus prototype-pollution keys). No option-name validation — any current or future MDP
widget option (`fields`, `sections`, `resourceLimits`, `resourcePermissions`, ...) works
with zero plugin changes.

**Precedence (exclusive, not merged):** when the config box is non-empty, it is passed
as `widget_config` and the legacy `*_mdp_json_fields` property is skipped entirely — a
populated legacy box sits inert. When the config box is empty or invalid JSON, the field
falls back to the legacy `fields`-only path unchanged.

**Required Resources interplay:** the separate `*_required_resources` property is
untouched by this feature and keeps emitting its own `requiredResources` key regardless
of the config box. If the config box supplies its own `requiredResources` key, that
value wins (last-wins JS object-literal emit order in the component); otherwise the
legacy Required Resources box still applies even with a config set.

**Editor UX:** MDP Widget Config is the top-most setting in the field's settings panel;
the legacy Required Resources and MDP JSON Fields boxes sit below it, each with
"(Deprecated)" appended to their label — plain-text labeling, no colored notices. Legacy
boxes stay plain `<textarea>` (no CodeMirror; see below). The MDP JSON Fields box hides
itself once empty (owned by `WidgetProfile.php`/`WidgetProfileOrg.php`'s own inline
settings-panel script, not scaffolding — it's the legacy field's own lifecycle).

**Migration scaffolding (temporary, deletable):** a "Copy this value into MDP Widget
Config" link under the legacy MDP JSON Fields box merges its value under a `fields` key
into whatever's already in the config box (preserving any other keys already typed
there, e.g. `resourceLimits`), clears the legacy box, and triggers its hide-when-empty
behavior — click-triggered only, never automatic; nothing persists until the form is
saved. This entire feature lives in its own file,
`assets/js/wicket_gf_widget_config_migration.js`, enqueued alongside the CodeMirror
script (`class-wicket-wp-gf.php::enqueue_scripts_styles()`, same form-editor gate) —
deliberately separated so it can be deleted, file and enqueue call alike, once no form
has a populated legacy value left to migrate, without touching either field's
permanent settings-panel code.

**Unsatisfiable configs (accepted risk):** because the config is schema-agnostic, it can
express combinations the MDP widget cannot resolve — e.g. a field marked both required
and hidden/limited/denied — which can block GF submission indefinitely. Neither the
field nor the component detects or warns about this beyond JSON-syntax validation; it is
the config author's responsibility. See
[refactor-gf-mdp-widget-config.md](../../../../wicket-atlas/plans/refactor-gf-mdp-widget-config.md)
for the full risk writeup.

Source: `WidgetProfile.php` (`sanitize_settings()`, `custom_settings()`,
`get_field_input()`); `WidgetProfileOrg.php` (same pattern); base plugin component arg
docs in [`wicket-wp-base-plugin.md`](../../../../../wicket-atlas/packages/wicket-wp-base-plugin.md).

**Editor UI (CodeMirror, config field only):** only the `*_mdp_json_config` textareas (on
both the individual and org fields) get WP core's bundled CodeMirror (line numbers, JSON
syntax coloring, live lint) in the form editor — the deprecated legacy boxes (`MDP JSON
Fields`, `Required Resources`) intentionally stay plain `<textarea>` per their
deprecated/plain-text treatment. `class-wicket-wp-gf.php::enqueue_scripts_styles()` calls
`wp_enqueue_code_editor(['type' => 'application/json'])` gated on
`GFCommon::is_form_editor()`, and `assets/js/wicket_gf_json_editor.js` attaches it on
`gform_load_field_settings`. The textarea remains the source of truth — CodeMirror pushes
a synthetic `input` event on change so the existing `SetFieldProperty` bindings and
JSON-validation warnings keep firing unchanged. Falls back to a plain textarea when
`wp_enqueue_code_editor()` returns `false` (user has "Disable syntax highlighting when
editing code" set in their profile).
