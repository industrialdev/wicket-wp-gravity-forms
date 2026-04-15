---
title: "Wicket Gravity Forms Overview"
audience: [implementer, support]
wp_admin_path: "Forms → Wicket Settings"
php_class: Wicket_Gf_Main
db_option_prefix: wicket_gf_
---

# Overview

Wicket Gravity Forms bridges Gravity Forms with the Wicket Member Data Platform (MDP). It adds custom field types to the Gravity Forms editor that read and write directly to Wicket, and a feed addon that maps form submissions to Wicket endpoints.

## What It Does

- Adds a **Wicket** group to the Gravity Forms field picker, with 8 custom field types
- Provides a **Gravity Forms feed addon** (Wicket Member Mapping) for mapping form fields to Wicket API endpoints
- Adds **confirmation types** for same-page redirects and WooCommerce cart redirects
- Supports **conditional logic** for all custom Wicket fields

## Requirements

- WordPress 6.6+
- PHP 8.1+
- Gravity Forms (paid or free)
- `wicket-wp-base-plugin` must be active

## Key Field Types

| Field | Purpose |
|---|---|
| **Org. Search** | Search and select an organization from the MDP |
| **MDP Tags** | Populate field with the current user's Wicket tags |
| **Profile Widget** | Embedded Wicket profile editor for individuals |
| **Org. Profile W.** | Embedded Wicket org profile editor within a form |
| **Add. Info. W.** | Embedded Wicket additional info widget |
| **Preferences** | Embedded Wicket communication preferences widget |
| **JS Data Bind** | Auto-populate a hidden field with Wicket profile data |
| **API Data Bind** | Populate field with live data from the Wicket API |

## Admin Settings

The plugin adds a **Wicket Settings** page under the Gravity Forms menu (`gf_edit_forms → Wicket Settings`). Options:

- **Pagination Sidebar Layout** — toggles a CSS rule that displays Gravity Forms multi-page form pagination as a sidebar instead of top steps

## Documentation Links

- [Field Types Reference](product/field-types.md) — all custom fields and their settings
- [Engineering: Field Architecture](engineering/field-architecture.md) — how custom fields are structured
- [Engineering: Hooks](engineering/hooks.md) — filters and actions
