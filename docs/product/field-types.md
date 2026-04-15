---
title: "Gravity Forms Field Types"
audience: [implementer, support]
php_class: Wicket_Gf_Main
db_option_prefix: wicket_gf_
---

# Gravity Forms Field Types

Eight custom field types appear in the Gravity Forms editor under the **Wicket** group. All are available in the standard Gravity Forms field picker. Fields prefixed **Widget** render a full Wicket UI component inside the form.

---

## Org. Search (`wicket_org_search_select`)

Lets users search for and select an organization from the MDP, or create a new one.

### Settings

| Setting | Description |
|---|---|
| **Search Mode** | Organizations or Groups (Beta) |
| **Organization Type** | Filter results by org type slug. Leave blank for all types. Comma-separated for multiple |
| **Display Org Fields** | `name`, `name + location`, or `name + address` |
| **Display Org Type?** | Toggle org type badge display |
| **Relationship Type(s) Upon Creation/Selection** | Comma-separated list of relationship slugs created when a person selects or creates an org |
| **Relationship Mode** | Controls how relationships are set |
| **Org Type When User Creates New Org** | Override org type for new orgs. Comma-separated for multiple options |

---

## MDP Tags (`wicket_user_mdp_tags`)

Dynamically populates a field with the current user's Wicket tags.

### Settings

Populated at render time. Supports dynamic parameter `user_mdp_tags` in Gravity Forms field value population.

---

## Profile Widget (`wicket_widget_profile_individual`)

Embedded Wicket profile editor for individual person data. Renders inside the form and allows the user to edit their own profile.

### Settings

| Setting | Description |
|---|---|
| **Required Resources** | JSON list of required profile sections to load |

---

## Org. Profile W. (`wicket_widget_profile_org`)

Embedded Wicket organization profile editor. Must be preceded by an **Org. Search** field in the form so it knows which org to load.

### Settings

| Setting | Description |
|---|---|
| **Hide Label** | Suppress the field label in the form |
| **Org UUID** | (Usually set dynamically via Org. Search field) |

---

## Add. Info. W. (`wicket_widget_ai`)

Embedded Wicket additional information schema editor. Used for custom data fields defined in the MDP.

### Settings

| Setting | Description |
|---|---|
| **Hide Label** | Suppress the field label |

Requires an **Org. Search** field earlier in the form to provide the `org_uuid` context.

---

## Preferences (`wicket_widget_prefs`)

Embedded Wicket communication preferences widget.

### Settings

| Setting | Description |
|---|---|
| **Hide Label** | Suppress the field label |

---

## JS Data Bind (`wicket_data_hidden`)

Hidden field that automatically populates with Wicket profile or organization data as the user fills the form. Works with Gravity Forms conditional logic.

### Settings

| Setting | Description |
|---|---|
| **Enable Live Update** | Enable data binding to auto-populate field value |
| **Data Source** | `person` (current user) or `organization` |
| **Organization UUID** | Required when source is `organization` |
| **Schema Slug** | MDP data category to bind from |
| **Value Key** | Specific field within the schema to bind |

---

## API Data Bind (`wicket_api_data_bind`)

Hidden field that populates from the Wicket API at render time. More flexible than JS Data Bind — supports ORGSS (Org Search Select) field binding.

### Settings

| Setting | Description |
|---|---|
| **Enable Live Update** | Enable data binding |
| **Data Source** | `person` or `organization` |
| **Organization UUID Source** | `ORGSS` field (from form) or static value |
| **Schema Slug** | MDP schema/category to query |
| **Value Key** | Specific field key to bind |

Supports **conditional logic** in Gravity Forms.
