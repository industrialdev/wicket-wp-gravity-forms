---
title: "Filters & Actions"
audience: [developer, agent]
source_files: ["class-wicket-wp-gf.php"]
---

# Filters & Actions

## Filters

### `wicket_gf_user_mdp_tags_default_source`

Controls which tag source is used when populating MDP Tags fields dynamically.

**Default:** `combined`

**Arguments:**
- `$source` (string) — one of: `combined`, `segment_tags`, `tags`

```php
// Use only segment_tags instead of combined
add_filter('wicket_gf_user_mdp_tags_default_source', fn() => 'segment_tags');
```

---

### `wicket_gf_should_show_field`

Controls whether a specific Gravity Forms field is rendered for the current user.

**Arguments:**
- `$should_show` (bool)
- `$form_id` (int)
- `$field_id` (int)

---

## Actions

### `gform_wicket_field_submission_processed`

Fires after a Wicket field finishes processing during form submission.

**Arguments:**
- `$entry` (array) — the Gravity Forms entry
- `$field_type` (string) — the Wicket field type that was processed

---

### `gform_wicket_org_selected`

Fires when a user selects an organization in the Org. Search field during form entry.

**Arguments:**
- `$org_uuid` (string)
- `$form_id` (int)

---

## Gravity Forms Hooks Used Internally

| Hook | Purpose |
|---|---|
| `gform_add_field_buttons` | Register the Wicket field group in the form editor |
| `gform_field_standard_settings` | Add per-field custom settings in the editor sidebar |
| `gform_editor_js` | Inject JS for field editor behavior |
| `gform_tooltips` | Register tooltips for custom field settings |
| `gform_pre_render` | Pass `org_uuid` from earlier form pages to widget fields |
| `gform_enqueue_scripts` | Conditionally enqueue live-update and API bind scripts |
| `gform_confirmation_settings_fields` | Add self-redirect and cart-redirect confirmation types |
| `gform_pre_confirmation_save` | Persist custom confirmation types on save |
| `gform_confirmation` | Handle runtime redirect for custom confirmation types |
| `gform_entry_detail_meta_boxes` | Register custom meta boxes on entry detail screen |
| `gform_get_field_value` | Intercept and modify field values on entry display |
| `rest_api_init` | Register `/wicket-gf/v1/resync-member-fields` REST endpoint |
