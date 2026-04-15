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
