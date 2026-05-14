---
title: "Field & Form Slug System"
audience: [developer, agent]
php_class: Wicket_Gf_Main
source_files: ["src/helpers.php", "class-wicket-wp-gf.php", "src/Admin.php"]
---

# Field & Form Slug System

The slug system assigns stable, human-readable identifiers to Gravity Forms forms
and individual fields. Slugs survive field reordering, ID changes during import/export,
and form duplication (import collision detection clears duplicates).

## Form Slugs

Each form can carry a `wicket_mdp_form_slug` property (stored in GF form meta).
This replaces the legacy global `wicket_gf_slug_mapping` option with per-form storage.

### Admin UI

- WP Admin → Forms → [form] → Settings → **Wicket** tab
- Set the **Form Slug** field (must be unique across all forms)

The legacy **Wicket Slugs** page (`admin.php?page=wicket_gf`) now redirects to
`admin.php?page=gf_settings&subview=wicket`.

### Lookup Helpers

```php
// Form ID by slug
$form_id = wicket_gf_get_form_id_by_slug('member-registration');
// Resolve any identifier (array, ID, or slug) to a form array
$form = wicket_gf_resolve_form('member-registration');
```

`wicket_gf_get_form_id_by_slug()` queries per-form `wicket_mdp_form_slug` first
(transient-cached for 1 hour), then falls back to the legacy
`wicket_gf_slug_mapping` option.

### Cache Management

```php
wicket_gf_flush_slug_cache();        // Flush all slug transients
wicket_gf_flush_slug_cache('my-slug'); // Flush a specific slug
```

The cache is flushed automatically when any form is saved (`gform_after_save_form`).

### Import Collision Protection

On form import (`gform_after_import_form`), if the imported form's slug collides
with an existing form, the imported slug is cleared to prevent ambiguous lookups.

---

## Field Slugs

Each field carries a `wicket_field_slug` property (string, empty by default).
Stored directly on the `GF_Field` object in form meta.

### Admin UI

In the form editor, when a field is selected, the **Field Slug** setting appears
in the right sidebar:

- **View mode**: Shows the current slug (green badge) or a dash (grey, no slug set)
- **Copy button**: Copies the slug to clipboard
- **Edit button** (pencil): Toggles inline edit mode
- **Edit mode**: Text input with confirm/cancel buttons
- **Validation**: Client-side uniqueness check + AJAX server-side check
  (`wp_ajax_wicket_gf_validate_field_slug`)

The form editor toolbar (`#gf_toolbar_buttons_container`) also displays a badge
with the selected field's slug and a copy icon.

Slugs are saved as part of the form's field meta. A server-side deduplication
pass (`sanitize_mdp_field_mappings_on_save`) strips conflicting slugs from all
but the first occurrence.

### JavaScript Slug Normalization

In the editor, slugs are normalized client-side before saving:
- Lowercased
- Non-alphanumeric characters replaced with hyphens
- Consecutive hyphens collapsed
- Leading/trailing hyphens stripped

This matches the PHP-side `wicket_gf_normalize_field_slug()`.

### PHP Helpers

```php
// Normalize a raw string into a valid slug
$slug = wicket_gf_normalize_field_slug('First Name');  // → "first-name"

// Find a field by its slug
$field = wicket_gf_get_field_by_slug($form, 'first-name');
if ($field) {
    echo $field->label; // "First Name"
}

// Get just the numeric field ID from a slug
$field_id = wicket_gf_get_field_id_by_slug($form, 'first-name');
// → 3 (or null if not found)
```

Both `wicket_gf_get_field_by_slug()` and `wicket_gf_get_field_id_by_slug()`
accept `$form` as a form array, numeric form ID, or form slug — they all resolve
through `wicket_gf_resolve_form()` internally.

---

## Helper Function Reference

### `wicket_gf_normalize_field_slug(string $slug): string`

Normalizes a string for use as a field slug. Delegates to `sanitize_title()`.

| | |
|---|---|
| Source | `src/helpers.php` |

### `wicket_gf_resolve_form(array|int|string $form): ?array`

Resolves a form identifier to a GF form array.

| | |
|---|---|
| Source | `src/helpers.php` |

### `wicket_gf_get_form_id_by_slug(string $slug): int|false`

Resolves a form slug to a numeric form ID. Transient-cached (1 hour) with
legacy `wicket_gf_slug_mapping` fallback.

| | |
|---|---|
| Source | `src/helpers.php` |

### `wicket_gf_get_field_by_slug(array|int|string $form, string $slug): ?GF_Field`

Returns the first field whose `wicket_field_slug` property matches the given slug.

| | |
|---|---|
| Source | `src/helpers.php` |

### `wicket_gf_get_field_id_by_slug(array|int|string $form, string $slug): ?int`

Returns the numeric field ID for a given slug. Convenience wrapper around
`wicket_gf_get_field_by_slug()`.

| | |
|---|---|
| Source | `src/helpers.php` |

### `wicket_gf_flush_slug_cache(?string $slug = null): void`

Deletes slug lookup transients. Pass a specific slug to flush one; pass `null`
to flush all.

| | |
|---|---|
| Source | `src/helpers.php` |

---

## AJAX Endpoints

### `wp_ajax_wicket_gf_validate_field_slug`

Validates field slug uniqueness server-side. Called from the form editor slug input.

**Request:**
- `action`: `wicket_gf_validate_field_slug`
- `nonce`: `wicket_gf_field_slug`
- `form_id`: int
- `field_id`: int
- `slug`: string (pre-normalized)

**Response (valid):**
```json
{"success": true, "data": {"valid": true, "slug": "first-name"}}
```

**Response (conflict):**
```json
{"success": false, "data": {"message": "This slug is already used by another field in this form."}}
```
