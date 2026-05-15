---
title: "MDP Field Mapping Guide"
audience: [implementer, support]
wp_admin_path: "Forms → Edit Form → Field Settings → MDP Mapping"
php_class: Wicket_Gf_Main
db_option_prefix: wicket_gf_
source_files: ["class-wicket-wp-gf.php", "src/MdpFieldDiscovery.php", "src/MdpSyncEngine.php", "src/MdpTypeCompatibility.php"]
---

# MDP Field Mapping Guide

This guide covers configuring Gravity Forms to push submitted values to the Wicket Member Data Platform (MDP) API. Two steps: map fields, verify results.

## Overview

When MDP mapping is enabled on a form field, each submission sends that field's value to Wicket after the entry is saved. The sync runs asynchronously (WP-Cron) and falls back to synchronous processing if scheduling fails.

## Step 1: Map Fields in the Field Editor

All MDP configuration happens in the field settings panel when you select a field in the form editor.

### Enable MDP Mapping

1. Click any field in the form editor.
2. In the field settings panel, check **Enable MDP Mapping**.
3. The MDP configuration section appears below the checkbox.

### Entity Type

Select **Person** or **Organization**. This determines which MDP target objects are available. Entity Type is a form-level setting (shared across all fields), but is configured inline for convenience. Setting it on any field applies it to the entire form.

### Target Object

Select a target object from the dropdown (filtered by Entity Type):

| Target Object | Entity | Description | Example Fields |
|---|---|---|---|
| **Person Profile** | Person | Top-level person attributes | First Name, Last Name, Job Title, Language |
| **Additional Info** | Person | Custom schema-based fields from Wicket | Discovers available schemas via MDP API |
| **Preferences** | Person | Communication opt-in/sublist toggles | Email Opt-in, specific communication sublists |
| **Org Profile** | Organization | Organization attributes | Legal Name |

> Additional Info and Preferences require an active MDP API connection to discover available fields. If no fields appear, verify the Wicket base plugin is configured with valid API credentials.

### Target Field

Select a specific field from the chosen Target Object. The dropdown populates dynamically based on the selected Target Object.

### Type Compatibility Warnings

When a multi-value field (Checkbox, Multi Select, Post Category) is mapped to a boolean target (e.g. Email Opt-in), a warning appears:

> Multi-value field mapped to a boolean target. Only the first selected value will be sent.

This is a non-blocking warning. If the mapping is intentional, proceed. Otherwise, use a single-value field (Radio, Dropdown).

### Validation Rules

The form cannot be saved if MDP mapping is enabled but:

- Entity Type is not set
- Target Object is not selected
- Target Object is not yet supported by field discovery
- Target Field is not selected or is invalid for the chosen Target Object

## Step 2: Verify Sync Results

### Entry-Level Status

After a submission, open the GF entry detail page. The sync status is recorded as entry meta:

| Status | Meaning |
|---|---|
| **Success** | Values were sent to MDP successfully |
| **Failed** | API call failed — check error message |
| **Skipped** | Form had no MDP config or no mapped values |
| **Pending** | Sync is scheduled (async, usually completes within seconds) |

### Wicket Settings Tab (Read-Only Summary)

The **Wicket** tab in form settings displays a read-only summary of all mapped fields. This is for quick reference only; all configuration is done in the field editor.

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
| Status is **Skipped** | No Entity Type configured | Set Entity Type when enabling MDP Mapping on a field |
| Status is **Failed** with API error | Invalid credentials or network issue | Check Wicket base plugin API configuration |
| **Failed** with "Could not resolve entity UUID" | UUID source was empty at submission time | Ensure the UUID source field receives a value before form submit |
| Target Field dropdown is empty | MDP API unreachable or no schemas/preferences defined | Verify API connectivity; additional info and preferences are discovered dynamically |

## Re-Sync Wicket Member Fields

The Wicket Member Mapping subview has a **Re-Sync Wicket Member Fields** button. This refreshes the available Wicket field options from the MDP API when new schemas or preferences are added on the Wicket side.
