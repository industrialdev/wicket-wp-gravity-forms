# Gravity Forms JSON Sync - Design Document

## Overview
Implement ACF-style Local JSON for Gravity Forms as a **soft compatibility layer**. JSON files provide a code-based source of truth for development teams while Gravity Forms continues normal database operations. This enables Git tracking, faster development workflows, and team collaboration without disrupting GF's core functionality.

## Architecture Reference
Based on [ACF Local JSON](https://www.advancedcustomfields.com/resources/local-json/)

## Core Components

### 1. JSON Storage Engine (Soft Compatibility Layer)
**File**: `includes/class-gf-json-sync.php`

**Philosophy**: Two-way sync, not replacement. GF and JSON stay synchronized.

**Save Flow** (when admin saves form):
```
1. Admin saves form in GF admin
2. GF saves to database (normal operation ✅)
3. Hook: gform_after_save_form triggers
4. Our layer exports to JSON (no DB disruption ✅)
```

**Load Flow** (when form is requested):
```
1. Check if JSON exists for form
2. Compare timestamps (JSON file mtime vs DB date_updated)
3. If JSON is newer → load from JSON, optionally sync to DB
4. If DB is newer or no JSON → load from DB (normal operation ✅)
5. Return form to GF for processing
```

**Hooks**:
- `gform_after_save_form` - Export to JSON after DB save (priority: 999, runs AFTER GF saves)
- `gform_form_update` - Intercept form updates to load from JSON if newer
- `gform_pre_render` - Load form from JSON before rendering (if newer)
- `gform_get_form` - Filter to return JSON version when appropriate

**JSON Structure** (Complete form object):
```json
{
  "id": 1,
  "title": "Contact Form",
  "date_created": "2026-04-17 12:00:00",
  "date_updated": "2026-04-17 14:30:00",
  "is_active": "1",
  "is_trash": "0",
  
  "fields": [
    {
      "id": 1,
      "type": "text",
      "label": "Name",
      "isRequired": true,
      "size": "medium"
    }
  ],
  
  "confirmations": {
    "confirmation_1": {
      "id": "confirmation_1",
      "name": "Default Confirmation",
      "type": "message",
      "message": "Thanks for contacting us!",
      "isDefault": true
    }
  },
  
  "notifications": {
    "notification_1": {
      "id": "notification_1",
      "name": "Admin Notification",
      "to": "{admin_email}",
      "subject": "New Submission: {form_title}",
      "message": "{all_fields}",
      "isActive": true
    }
  },
  
  "labelPlacement": "top_label",
  "descriptionPlacement": "below",
  "button": {
    "type": "text",
    "text": "Submit",
    "imageUrl": ""
  },
  "enableHoneypot": true,
  "enableAnimation": true
}
```

### 2. File Structure
**Default Location**: Plugin directory (with theme auto-detection)

```
wp-content/plugins/wicket-wp-gravity-forms/
├── gf-json/                    # Default: plugin directory
│   ├── forms/
│   │   ├── form-1.json
│   │   ├── contact-form.json
│   │   └── .gitkeep
│   ├── index.php (security)
│   └── .gitkeep

# OR if theme directory detected (auto-override):
wp-content/themes/your-theme/
├── gf-json/                    # Theme override (if exists)
│   ├── forms/
│   │   └── ...
```

**Storage Priority** (auto-detection):
1. **Check theme directory first** → `get_stylesheet_directory() . '/gf-json'`
2. **Fallback to plugin directory** → `plugin_dir_path() . 'gf-json'`

**Rationale for plugin-first approach**:
- ✅ **Plugin owns the feature** (forms ship with plugin)
- ✅ **Works out of the box** (no theme setup needed)
- ✅ **Git-tracked by default** (plugin files are in version control)
- ✅ **Theme override available** (flexibility for custom themes)
- ✅ **Multi-site friendly** (each site can use theme-specific override)

**Why NOT uploads folder**:
- ❌ Uploads folder is typically `.gitignore`d
- ❌ Contains user-generated content (media, uploads)
- ❌ Not suitable for code/configuration files
- ❌ Would be excluded from Git operations

**Path Detection Logic** (automatic):
```php
function gf_json_sync_get_save_path() {
    // 1. Check if theme directory has gf-json folder
    $theme_path = get_stylesheet_directory() . '/gf-json';
    if (file_exists($theme_path)) {
        return $theme_path;
    }
    
    // 2. Default to plugin directory
    return plugin_dir_path(__FILE__) . 'gf-json';
}

function gf_json_sync_get_load_paths() {
    $paths = [];
    
    // 1. Check theme directory first (highest priority)
    $theme_path = get_stylesheet_directory() . '/gf-json';
    if (file_exists($theme_path)) {
        $paths[] = $theme_path;
    }
    
    // 2. Check parent theme (for child themes)
    $parent_path = get_template_directory() . '/gf-json';
    if ($parent_path !== $theme_path && file_exists($parent_path)) {
        $paths[] = $parent_path;
    }
    
    // 3. Always include plugin directory (fallback)
    $paths[] = plugin_dir_path(__FILE__) . 'gf-json';
    
    return $paths;
}
```

**Path Filters** (still available for advanced customization):
```php
// Override: Force plugin directory only
add_filter('gf/settings/save_json', function($path) {
    return plugin_dir_path(__FILE__) . 'gf-json';
});

// Override: Custom theme location
add_filter('gf/settings/save_json', function($path) {
    return get_stylesheet_directory() . '/config/forms';
});

// Override: Multiple load paths
add_filter('gf/settings/load_json', function($paths) {
    $paths[] = WP_CONTENT_DIR . '/shared-forms';
    return $paths;
});
```

### 3. Feature Flag Implementation

**Option Registration** (in `class-wicket-gf-admin.php`):
```php
// Register the setting
public static function register_settings() {
    // Existing settings...
    register_setting('wicket_gf_options_group', 'wicket_gf_slug_mapping', [...]);
    register_setting('wicket_gf_options_group', 'wicket_gf_pagination_sidebar_layout', null);

    // NEW: JSON sync setting
    register_setting(
        'wicket_gf_options_group',
        'wicket_gf_json_sync_enabled',
        [
            'sanitize_callback' => function($value) {
                return $value ? '1' : '0';
            }
        ]
    );
}
```

**Conditional Hook Registration** (in `class-gf-json-sync.php`):
```php
public function init() {
    // Only register hooks if feature flag is enabled
    if ($this->is_json_sync_enabled()) {
        add_action('gform_after_save_form', [$this, 'export_form_to_json'], 999, 2);
        add_filter('gform_form_update', [$this, 'maybe_load_from_json'], 10, 1);
        add_filter('gform_pre_render', [$this, 'maybe_load_from_json'], 10, 1);
    }
}

private function is_json_sync_enabled() {
    // Check admin setting first
    $enabled = get_option('wicket_gf_json_sync_enabled', '0');

    // Allow wp-config.php override
    if (defined('GF_JSON_SYNC_ENABLED')) {
        return GF_JSON_SYNC_ENABLED;
    }

    return $enabled === '1';
}
```

**Performance** (zero overhead when disabled):
```php
// When disabled:
// - No hooks registered
// - No file system checks
// - No timestamp comparisons
// - Zero performance impact
```

**Never Disrupt Gravity Forms**:
- ✅ GF saves to DB normally (we hook AFTER save)
- ✅ GF loads from DB normally (we provide JSON as optional override)
- ✅ Can disable JSON sync anytime (forms still work)
- ✅ No schema changes, no table modifications
- ✅ Existing sites unaffected (opt-in feature)

**Two-Way Synchronization**:
```
┌─────────────────────────────────────────┐
│         Admin Saves Form               │
└──────────────┬──────────────────────────┘
               │
               ├─→ Database (GF saves normally) ✅
               │
               └─→ JSON File (we export after) ✅

┌─────────────────────────────────────────┐
│         Form Requested                  │
└──────────────┬──────────────────────────┘
               │
               ├─→ Check JSON timestamp
               │   │
               │   ├─ JSON newer? → Load JSON ✅
               │   │
               │   └─ DB newer? → Load DB ✅
               │
               └─→ Return form (transparently)
```

**Key Implementation Details**:

```php
// Save: Hook AFTER GF saves to DB
add_action('gform_after_save_form', 'gf_json_sync_export', 999, 2);
function gf_json_sync_export($form, $is_update) {
    // GF already saved to DB
    // Now we ALSO export to JSON
    gf_json_sync_save_to_file($form);
}

// Load: Intercept get_form calls
add_filter('gform_form_update', 'gf_json_sync_load_from_file', 10, 1);
function gf_json_sync_load_from_file($form) {
    // Check if JSON exists and is newer
    if ($json_form = gf_json_sync_get_json_if_newer($form['id'])) {
        return $json_form; // Use JSON version
    }
    return $form; // Use DB version (normal GF behavior)
}
```

### 4. Sync System
**Admin Page**: Forms > JSON Sync
- List forms with newer JSON versions
- Bulk import from JSON
- Export all forms to JSON
- Status indicators (✓ in sync, ↻ pending sync)

**Comparison Logic**:
```php
$json_modified = filemtime($json_file);
$db_modified = strtotime($form['date_updated']);

if ($json_modified > $db_modified) {
    // JSON is newer - can sync to DB
    return 'json_newer';
} elseif ($db_modified > $json_modified) {
    // DB is newer - can export to JSON
    return 'db_newer';
} else {
    // In sync
    return 'synced';
}
```

**Sync Strategies**:

1. **Auto-Sync** (recommended for development):
   - JSON newer → Auto-import to DB on load
   - DB newer → Auto-export to JSON on save

2. **Manual Sync** (safer for production):
   - Show "Sync Available" notice in admin
   - User chooses which direction to sync
   - Prevent accidental overwrites

3. **Directional Lock** (team workflows):
   - Lock JSON as source of truth (JSON → DB only)
   - Lock DB as source of truth (DB → JSON only)

### 5. Filters (ACF-compatible)

**Save Path Filters**:
- `gf/settings/save_json` - Universal save path
- `gf/settings/save_json/form_id={$id}` - Per-form path
- `gf/settings/save_json/slug={$slug}` - Per-slug path

**Load Path Filters**:
- `gf/settings/load_json` - Array of load paths

**Filename Filter**:
- `gf/json/save_file_name` - Custom filename pattern

### 6. CLI Commands
```bash
wp gf json export [--form_id=<id>] [--path=<path>]
wp gf json import [--form_id=<id>] [--path=<path>]
wp gf json sync [--force]
wp gf json status
```

## Implementation Phases

### Phase 1: Core Engine with Feature Flag
- [x] Design document
- [ ] Add feature flag option to settings page
- [ ] Register `wicket_gf_json_sync_enabled` setting
- [ ] Create `class-gf-json-sync.php`
- [ ] Implement conditional hook registration (feature flag check)
- [ ] Implement `export_form_to_json()`
- [ ] Implement `import_form_from_json()`
- [ ] Implement `get_json_forms_list()`
- [ ] Add path filters

### Phase 2: Admin UI
- [ ] Create sync page under Forms > JSON Sync
- [ ] Build forms list table with sync status
- [ ] Add bulk actions (Export All, Import Selected)
- [ ] Add individual form sync actions
- [ ] Add status indicators

### Phase 3: CLI Commands
- [ ] Register WP-CLI commands
- [ ] Implement export command
- [ ] Implement import command
- [ ] Implement sync command
- [ ] Implement status command

### Phase 4: Testing
- [ ] Unit tests for export/import
- [ ] Tests for form modification detection
- [ ] Tests for path filters
- [ ] Browser tests for admin UI
- [ ] Integration tests with CLI

## Data Coverage Analysis

### ✅ What We Capture in JSON

**From `rg_forms` table**:
- `id` - Form ID
- `title` - Form title
- `date_created` - Creation timestamp
- `date_updated` - Last modified timestamp (for sync detection)
- `is_active` - Active status
- `is_trash` - Trashed status

**From `rg_form_meta.display_meta`**:
- ✅ All form fields (including custom Wicket fields)
- ✅ Field settings (validation, conditional logic, visibility)
- ✅ Form layout settings (label placement, description placement)
- ✅ Button settings
- ✅ Save and Continue settings
- ✅ Pagination settings
- ✅ Anti-spam settings (honeypot)
- ✅ Form scheduling settings
- ✅ Entry limits settings
- ✅ All other form configuration options

**From `rg_form_meta.confirmations`**:
- ✅ All confirmation types (message, redirect, page)
- ✅ Conditional logic for confirmations
- ✅ Query string parameters
- ✅ All confirmation settings

**From `rg_form_meta.notifications`**:
- ✅ All notifications (to, from, subject, message)
- ✅ Notification routing rules
- ✅ Conditional logic for notifications
- ✅ All notification settings

### ❌ What Stays in Database

**Entry submissions** (`rg_entries` / `gf_entries`):
- Form submission data
- Entry metadata
- Entry notes
- Payment transactions

**Why entries stay in DB**:
- User-submitted data (not code)
- Constantly changing
- Too large for JSON
- Privacy/security concerns

## Key Differences from ACF

1. **Forms vs Field Groups**: GF forms are more complex (fields, confirmations, notifications)
2. **Entries**: Form submissions stay in DB (never in JSON)
3. **Form IDs**: Must preserve ID mapping between environments
4. **Custom Fields**: Our custom Wicket fields serialize perfectly in `display_meta`

## Security Considerations

1. Add `index.php` to `gf-json/` directory
2. Validate JSON structure before import
3. Check file permissions (755)
4. Sanitize all form data on export/import
5. Nonce verification for admin actions
6. Capability checks (`manage_options`)

## Performance Benefits

**When JSON Sync is Enabled**:

1. **Faster Form Loading** (when JSON is newer)
   - JSON files read from disk vs DB queries
   - Opcode cache (OPcache) caches parsed JSON
   - Reduced database load on high-traffic sites

2. **Development Workflow Speed**
   - Deploy form changes via Git (no admin access needed)
   - Roll back forms instantly with Git revert
   - Compare form changes with `git diff`
   - Merge form changes across branches

3. **Team Collaboration**
   - Multiple devs work on same forms
   - Git resolves merge conflicts
   - No "who overwrote my form" issues
   - Code review for form changes

**When JSON Sync is Disabled**:
- Zero performance impact (hooks not registered)
- GF works exactly as before

## Feature Flag: User Interface

### Settings Page Implementation

**File**: `admin/class-wicket-gf-admin.php`

**Add to options_page() method**:
```php
<div class="wicket_json_sync_settings">
    <h3><?php _e('Form JSON Sync', 'wicket-gf'); ?></h3>

    <p>
        <?php _e('Store form configurations as JSON files for version control and team collaboration.', 'wicket-gf'); ?>
        <br>
        <small><?php _e('JSON files are saved to:', 'wicket-gf'); ?>
            <code><?php echo gf_json_sync_get_save_path(); ?></code>
            <br>
            <?php _e('Theme override detected if', 'wicket-gf'); ?>
            <code><?php echo get_stylesheet_directory(); ?>/gf-json/</code>
            <?php _e('exists.', 'wicket-gf'); ?>
        </small>
    </p>

    <label for="wicket_gf_json_sync_enabled" class="inline">
        <input type="checkbox"
               name="wicket_gf_json_sync_enabled"
               id="wicket_gf_json_sync_enabled"
               value="1"
               <?php checked(get_option('wicket_gf_json_sync_enabled', '0'), '1'); ?>>
        <?php _e('Enable JSON Sync', 'wicket-gf'); ?>
    </label>

    <p class="description">
        <?php _e('When enabled, forms will be exported to JSON files on save. Forms will load from JSON if the file is newer than the database version.', 'wicket-gf'); ?>
    </p>
</div>
```

**Help Text**:
```php
<?php _e('
<strong>JSON Sync Benefits:</strong>
• Track form changes in Git
• Deploy forms via code
• Team collaboration without conflicts
• Roll back forms instantly

<strong>Storage Location:</strong>
• Default: Plugin directory (wicket-wp-gravity-forms/gf-json/)
• Auto-detects theme override if gf-json/ exists in active theme
• Both locations are Git-tracked by default

<strong>Important:</strong>
• Forms continue to save to database normally
• Can be disabled anytime (no data loss)
• Existing forms unaffected until saved
', 'wicket-gf'); ?>
```

### Feature Flag States

| State | Behavior |
|-------|----------|
| **Disabled** (default) | Forms work normally (DB only) |
| **Enabled** | Forms export to JSON on save, load from JSON if newer |
| **Disabled after enabled** | Forms continue working (DB only, JSON ignored) |

## Backward Compatibility & Safety

**What We DON'T Do**:
- ❌ Don't modify GF's database schema
- ❌ Don't prevent GF from saving to DB
- ❌ Don't hijack GF's save process
- ❌ Don't require schema migrations
- ❌ Don't break if JSON files deleted
- ❌ Don't require existing forms to have JSON

**What We DO**:
- ✅ Hook AFTER GF saves (non-blocking)
- ✅ Provide JSON as alternative load source
- ✅ Gracefully fall back to DB if JSON missing
- ✅ Validate JSON before using
- ✅ Can be disabled anytime (no cleanup needed)

### Opt-In Feature

**Default Behavior**: DISABLED
- Sites without JSON work exactly as before
- No performance impact when disabled
- Zero configuration required for basic usage

**Enable When Ready**:
```php
// wp-config.php
define('GF_JSON_SYNC_ENABLED', true);
```

### Migration Path

**Existing Sites**:
1. Install plugin (JSON sync disabled)
2. Enable when ready: `define('GF_JSON_SYNC_ENABLED', true);`
3. Export existing forms to JSON (one-time or individually)
4. Forms now have JSON versions alongside DB
5. Continue normal operations

**New Sites**:
1. Enable from start: `define('GF_JSON_SYNC_ENABLED', true);`
2. Create forms in admin
3. JSON files created automatically
4. Track in Git from day one

**Disable Anytime**:
1. Set: `define('GF_JSON_SYNC_ENABLED', false);`
2. GF continues working normally (DB only)
3. JSON files ignored (can delete or keep for reference)

## Configuration

### Feature Flag (Required)

**Default**: DISABLED (opt-in feature)

**Enable via Admin UI**:
1. Go to **Forms > Wicket Settings**
2. Check **"Enable JSON Sync"** checkbox
3. Save settings
4. JSON sync now active

**Enable via wp-config.php** (optional override):
```php
// Force enable (overrides admin setting)
define('GF_JSON_SYNC_ENABLED', true);
```

**Enable programmatically**:
```php
// Update the option directly
update_option('wicket_gf_json_sync_enabled', '1');
```

### Storage Path Configuration

**Default**: Plugin directory with theme auto-detection
```php
// Auto-detection priority:
// 1. Theme directory (if gf-json/ exists)
// 2. Plugin directory (fallback)
//
// No configuration needed - works automatically
```

**How Auto-Detection Works**:
```php
// Save logic:
$save_path = gf_json_sync_get_save_path();
// Returns: /path/to/theme/gf-json/ (if exists)
//       OR: /path/to/plugin/gf-json/ (default)

// Load logic (checks all paths):
$load_paths = gf_json_sync_get_load_paths();
// Returns array:
// [
//   '/path/to/theme/gf-json/',      // if exists (highest priority)
//   '/path/to/parent-theme/gf-json/', // if child theme & exists
//   '/path/to/plugin/gf-json/'      // always included (fallback)
// ]
```

**Manual override via filters**:
```php
// Force plugin directory only (disable auto-detection)
add_filter('gf/settings/save_json', function($path) {
    return plugin_dir_path(__FILE__) . 'gf-json';
});

// Force theme directory (even if folder doesn't exist yet)
add_filter('gf/settings/save_json', function($path) {
    return get_stylesheet_directory() . '/gf-json';
});

// Custom: Multiple load paths (parent + child theme)
add_filter('gf/settings/load_json', function($paths) {
    // Load from parent theme first
    $paths[] = get_template_directory() . '/gf-json';
    // Then child theme (overrides parent)
    $paths[] = get_stylesheet_directory() . '/gf-json';
    return $paths;
});

// Custom: wp-content directory
add_filter('gf/settings/save_json', function($path) {
    return WP_CONTENT_DIR . '/gf-json';
});
```

### Admin Settings Integration

**Location**: Forms > Wicket Settings (existing page)

**New Option**:
```php
// Option name: wicket_gf_json_sync_enabled
// Type: checkbox
// Default: 0 (disabled)
// Stored in wp_options table
```

**Settings Page Structure**:
```
┌─────────────────────────────────────┐
│  Wicket Gravity Forms Settings      │
├─────────────────────────────────────┤
│                                     │
│  Form Slug ID Mapping               │
│  ┌─────────────────────────────┐   │
│  │ [slug] → [form ID]          │   │
│  │ [+ Add Row] [- Remove Row]  │   │
│  └─────────────────────────────┘   │
│                                     │
│  General Gravity Forms Settings     │
│  ☑ Use Sidebar Pagination Layout   │
│                                     │
│  Form JSON Sync (NEW)               │
│  ☑ Enable JSON Sync                │
│  ℹ Store forms as JSON files in:   │
│    /plugins/wicket-wp-gravity-     │
│    forms/gf-json/                   │
│                                     │
│  ℹ Theme override: Active if       │
│    /themes/your-theme/gf-json/     │
│    exists                          │
│                                     │
│  [Save Settings]                   │
└─────────────────────────────────────┘
```

## Soft Compatibility Layer: Technical Implementation

### Hook Timing & Priorities

**Save Flow** (no disruption to GF):
```php
// GF's save process (we don't touch this):
// 1. gform_pre_validation
// 2. gform_validation
// 3. gform_after_submission
// 4. gform_save_form → GF saves to DB ✅

// Our layer (runs AFTER GF finishes):
add_action('gform_after_save_form', 'gf_json_sync_export', 999, 2);
// Priority 999 ensures GF has completely finished saving
// We then export the saved form to JSON
```

**Load Flow** (transparent override):
```php
// GF's load process:
// 1. Request form
// 2. gform_form_update filter
// 3. GF loads from DB normally ✅

// Our layer (provides JSON if newer):
add_filter('gform_form_update', 'gf_json_sync_maybe_replace', 10, 1);
// If JSON exists and is newer, replace DB result with JSON
// If not, return DB result unchanged
// GF never knows the difference
```

### Key Design Principles

**1. Non-Invasive**
```php
// ✅ GOOD: Filter after GF does its work
add_filter('gform_form_update', 'gf_json_sync_maybe_replace', 10, 1);

// ❌ BAD: Prevent GF from saving (don't do this)
add_filter('gform_pre_save_form', 'prevent_db_save'); // NEVER!
```

**2. Fail-Safe**
```php
function gf_json_sync_maybe_replace($form) {
    $json = gf_json_sync_load_from_file($form['id']);

    // If JSON missing or invalid, return DB version
    if (!$json || !gf_json_sync_validate($json)) {
        return $form; // Fall back to DB (safe!)
    }

    // Compare timestamps
    if (gf_json_sync_is_newer($json, $form)) {
        return $json; // Use JSON
    }

    return $form; // Use DB (default behavior)
}
```

**3. Optional**
```php
// Only register hooks if enabled
if (defined('GF_JSON_SYNC_ENABLED') && GF_JSON_SYNC_ENABLED) {
    add_action('gform_after_save_form', 'gf_json_sync_export', 999, 2);
    add_filter('gform_form_update', 'gf_json_sync_maybe_replace', 10, 1);
}
// When disabled: Zero hooks registered, zero overhead
```

### Data Integrity

**Always Maintain DB as Backup**:
- JSON files can be deleted (forms still work)
- JSON files can be corrupted (DB still has valid copy)
- Git merge conflicts? Use DB version
- File permission issues? Use DB version

**Validation Before JSON Load**:
```php
function gf_json_sync_validate($json) {
    // Must be array
    if (!is_array($json)) return false;

    // Must have required keys
    $required = ['id', 'title', 'fields'];
    foreach ($required as $key) {
        if (!isset($json[$key])) return false;
    }

    // Must have valid form ID
    if (!is_numeric($json['id'])) return false;

    // Fields must be array
    if (!is_array($json['fields'])) return false;

    return true; // Valid!
}
```

## Next Steps

1. ✅ Design approval (with soft compatibility layer)
2. ⏳ Implement Phase 1 (Core Engine)
3. ⏳ Implement Phase 2 (Admin UI)
4. ⏳ Implement Phase 3 (CLI)
5. ⏳ Implement Phase 4 (Testing)

## Sources
- [ACF Local JSON Documentation](https://www.advancedcustomfields.com/resources/local-json/)
