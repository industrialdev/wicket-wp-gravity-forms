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

Populates a field (usually hidden) with Wicket data **client-side**, by reading from a Wicket widget rendered on the same form. Works with Gravity Forms conditional logic.

> Important: JS Data Bind reads from the Wicket **widget on the page**, not from a standalone API call. A field can only bind values whose Additional Info / Profile widget is present on that same form. If the widget for the chosen schema is not on the form, the field stays empty. (To fetch from the API without a widget on the form, use **API Data Bind** instead.)

### Settings

| Setting | Description |
|---|---|
| **Enable JS Data Bind** | Enable data binding to auto-populate the field value |
| **Display Mode** | `Hidden` (default), `Read-only`, `Editable`, or `Static` text |
| **Data Source** | `Person Add. Info.`, `Person Profile`, `Organization`, or `Organization Profile` |
| **Organization UUID** | Required when the source is an organization |
| **Schema/Data Slug** | The MDP schema/category to bind from |
| **Value Key** | The field within the schema to bind |

### Nested value keys

For schemas whose fields are grouped into sections (section -> field), the Value Key uses **dot notation** to reach a nested field, e.g. `about-self.uniafil`. The picker lists these as `Section -> Field`. Flat schemas keep their plain key (e.g. `certify`, `traineepgy`). Bound values are the stored slug (e.g. `university-of-western-ontario`), not the display label, so compare against the slug in conditional logic.

---

## API Data Bind (`wicket_api_data_bind`)

Pulls a single value **from** the Wicket API into a form field at render time, addressed by a dot-notation **field path**. Most often used as a hidden field whose value drives Gravity Forms conditional logic (e.g. show a section only when the member's city is "Toronto"). It reads only — it never writes back to Wicket. To write submitted values back, see [MDP Field Mapping](mdp-field-mapping.md).

### Settings

| Setting | Description |
|---|---|
| **API Data Source** | `Person Profile (Current User)` or `Organization` |
| **Organization UUID Source** | (Organization only) `Static UUID`, or `Bind to ORGSS Field` to pull the UUID from an Org. Search field in the same form |
| **Organization UUID** | (Static source) the org UUID to fetch from |
| **Select ORGSS Field** | (ORGSS source) which Org. Search field supplies the UUID |
| **Field Path** | Dot-notation path to the value to fetch (see below) |
| **Display Mode** | `Hidden` (default), `Read-only`, `Editable`, or `Static` text |
| **Fallback Value** | Used when the API returns nothing or the call fails |

Supports **conditional logic** in Gravity Forms.

### Field Path syntax

The field path addresses a value inside the Wicket record. Use **Browse Available Fields** in the editor to pick from discovered paths, or type one directly.

| Path | Returns |
|---|---|
| `attributes.given_name` | A top-level person attribute (first name) |
| `attributes.primary_email_address` | The member's primary email |
| `attributes.full_name` | Full name |
| `addresses.primary.city` | City of the **primary** address (recommended) |
| `addresses.type:work.city` | City of the address whose type is `work` |
| `addresses.0.city` | City of the **first** address by API order (positional — not recommended) |
| `organizations.0.legal_name` | Legal name of the member's first connected organization |
| `data_fields.{schema_slug}.value.{field_name}` | A custom additional-info field |

Relationship collections (addresses, emails, phones, web_addresses) can hold several records. Target one with:

- **`.primary`** — the record flagged primary; order-independent and recommended. A bare path like `addresses.city` defaults to the primary record.
- **`.type:<value>`** — the record matching a known type, e.g. `addresses.type:work.city`.
- **`.0` / `.1`** — positional, in whatever order the API returns. Avoid for anything that must be stable.

> Organizations are connected to a person via **roles** scoped to an organization. A member with no org-scoped role returns nothing for `organizations.*`.

### Compared to JS Data Bind

Both read MDP data into a (usually hidden) field for conditional logic. **API Data Bind** fetches server-side at render via a field path, and can bind an Organization UUID to an Org. Search (ORGSS) field. **JS Data Bind** populates client-side from rendered Wicket widgets using a schema slug + value key. Prefer API Data Bind for person/organization attribute and relationship data; use JS Data Bind when binding to data shown by an on-page widget.

---

## Reading vs. writing Wicket data

Three features move data between a form and the MDP, in different directions. Don't confuse them:

| Feature | Direction | When | Configured by |
|---|---|---|---|
| **API Data Bind** (field) | Read: MDP → form | Page render (server-side) | Field Path |
| **JS Data Bind** (field) | Read: MDP → form | As the user fills the form (client-side) | Schema slug + value key |
| **[MDP Field Mapping](mdp-field-mapping.md)** (per-field checkbox) | Write: form → MDP | After submit (async via WP-Cron) | Target Object + Target Field |

The two Data Bind fields **pull** existing member data into the form (to prefill or to drive conditional logic). MDP Field Mapping **pushes** what the user submitted back onto the member or organization record. They are complementary: you can prefill a field with API Data Bind, let the user edit it, and write the change back with MDP Field Mapping.
