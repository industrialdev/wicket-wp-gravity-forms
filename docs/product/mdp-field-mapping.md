---
title: "MDP Field Mapping Guide"
audience: [implementer, support]
wp_admin_path: "Forms → Edit Form → Wicket Settings / Wicket Member Mapping"
php_class: Wicket_Gf_Main
db_option_prefix: wicket_gf_
source_files: ["class-wicket-wp-gf.php", "src/MdpFieldDiscovery.php", "src/MdpSyncEngine.php", "src/MdpTypeCompatibility.php"]
---

# MDP Field Mapping Guide

This guide covers configuring Gravity Forms to push submitted values to the Wicket Member Data Platform (MDP) API. Three steps: set the UUID source, map fields, and verify results.

## Overview

When MDP mapping is enabled on a form, each submission sends field values to Wicket after the entry is saved. The sync runs asynchronously (WP-Cron) and falls back to synchronous processing if scheduling fails.

## Step 1: Configure Form-Level Settings

Open the form editor and click the **Wicket** tab in the form settings navigation.

### Entity Type

Select **Person** or **Org**. This determines which MDP target objects are available for field mapping.

### UUID Source Field

Select the form field that supplies the entity UUID. This is how the sync engine knows which Wicket record to update.

The dropdown only includes fields that can hold a UUID value — layout fields (HTML, Page, Section, Captcha) and Wicket widgets are excluded. Common choices:

- A **Hidden** field populated dynamically (e.g. via JS Data Bind or URL parameter)
- A **Text** or **Hidden** field bound to Org. Search
- Any standard input field that receives a UUID string

> Without a UUID source, MDP field mapping is disabled at runtime.

Save the form after setting these values.

## Step 2: Map Individual Fields

For each form field whose value should sync to Wicket:

1. Select the field in the form editor.
2. In the field settings panel, check **Enable MDP Mapping**.
3. Select a **Target Object** from the dropdown (filtered by Entity Type):
   - **Person** → Person Profile, Additional Info, Preferences
   - **Org** → Org Profile
4. Select a **Target Field** (populated based on the chosen Target Object).

### Target Objects

| Target Object | Description | Example Fields |
|---|---|---|
| **Person Profile** | Top-level person attributes | First Name, Last Name, Job Title, Language |
| **Additional Info** | Custom schema-based fields from Wicket | Discovers available schemas via MDP API |
| **Preferences** | Communication opt-in/sublist toggles | Email Opt-in, specific communication sublists |
| **Org Profile** | Organization attributes | Legal Name |

> Additional Info and Preferences require an active MDP API connection to discover available fields. If no fields appear, verify the Wicket base plugin is configured with valid API credentials.

### Type Compatibility Warnings

When a multi-value field (Checkbox, Multi Select, Post Category) is mapped to a boolean target (e.g. Email Opt-in), a warning appears:

> Multi-value field mapped to a boolean target. Only the first selected value will be sent.

This is a non-blocking warning. If the mapping is intentional, proceed. Otherwise, use a single-value field (Radio, Dropdown).

### Validation Rules

The form cannot be saved if MDP mapping is enabled but:

- Entity Type is not set
- UUID Source Field is not set
- Target Object is not selected
- Target Object is not yet supported by field discovery
- Target Field is not selected or is invalid for the chosen Target Object

## Step 3: Verify Sync Results

### Entry-Level Status

After a submission, open the GF entry detail page. The sync status is recorded as entry meta:

| Status | Meaning |
|---|---|
| **Success** | Values were sent to MDP successfully |
| **Failed** | API call failed — check error message |
| **Skipped** | Form had no MDP config or no mapped values |
| **Pending** | Sync is scheduled (async, usually completes within seconds) |

### Wicket Logs

Sync events are written to the Wicket log file via `Wicket()->log()`. Log entries include:

- Source: `wicket-gf-mdp-sync`
- Form ID, Entry ID, Entity Type, UUID
- Status (success/failed/skipped)
- Error message on failure

Log location is configured in the Wicket Base Plugin settings.

### Common Issues

| Symptom | Cause | Fix |
|---|---|---|
| Status stays **Pending** | WP-Cron not firing | Ensure WP-Cron is enabled; check server cron if `DISABLE_WP_CRON` is set |
| Status is **Skipped** | No UUID source configured | Set Entity Type and UUID Source Field in Form Settings |
| Status is **Failed** with API error | Invalid credentials or network issue | Check Wicket base plugin API configuration |
| **Failed** with "Could not resolve entity UUID" | Source field was empty on submission | Ensure the UUID source field receives a value before form submit |
| Target Field dropdown is empty | MDP API unreachable or no schemas/preferences defined | Verify API connectivity; additional info and preferences are discovered dynamically |

## Re-Sync Wicket Member Fields

The Wicket Member Mapping subview has a **Re-Sync Wicket Member Fields** button. This refreshes the available Wicket field options from the MDP API when new schemas or preferences are added on the Wicket side.
