# Wicket WP Gravity Forms

Integration plugin that connects Wicket's Member Data Platform (MDP) with Gravity Forms. Adds Wicket-aware field types, syncs form submissions back to MDP, and provides slug-based form lookup and other helpers for member-facing forms.

## Features

- **Custom field types** — 8 Gravity Forms field types for organization search, MDP tags, embedded Wicket widgets (profile, org profile, additional info, preferences), and data-binding
- **MDP Sync Engine** — bind any Gravity Forms field to a Wicket MDP target (Person Profile, Additional Info, Preferences, Org Profile) directly in the field editor; submissions sync to MDP asynchronously via WP-Cron with sync status recorded as entry meta
- **Wicket Member Mapping feed add-on** — alternative `GFFeedAddOn`-based mapping (per-feed mapping of GF fields to Wicket member schema paths)
- **Slug-based form lookup** — `wicket_gf_get_form_id_by_slug($slug)` and an enhanced `[wicket_gravityform slug="..."]` shortcode, backed by a central `wicket_gf_slug_mapping` option editable from both a global page and per-form
- **Field slug system** — stable, human-readable identifiers for individual fields, with AJAX uniqueness validation, a copy-to-clipboard pill in the editor, and a `data-slug` attribute on the rendered field container
- **Custom confirmation types** — Same Page redirect, Cart redirect (WooCommerce), Checkout Link redirect (WooCommerce Blocks)
- **Consent field extension** — adds a "Sync option to MDP" toggle to Gravity Forms' built-in Consent field that updates Wicket communication preferences on opt-in
- **Pagination sidebar layout** — opt-in CSS/JS that renders multi-page form pagination as a sidebar instead of top steps
- **REST API** — endpoint for resyncing Wicket member fields

## Requirements

- **WordPress**: 6.6+
- **PHP**: 8.2+
- **Gravity Forms**: active install
- **wicket-wp-base-plugin**: must be active (declared in `Requires Plugins:`)
- **Composer**: for dependency management

## Installation

This plugin is not available in the WordPress.org plugin repository. Install via:

```bash
cd wp-content/plugins
git clone https://github.com/industrialdev/wicket-wp-gravity-forms.git
cd wicket-wp-gravity-forms
composer install
```

Then activate through WordPress admin.

## Usage

### Using Slugs Instead of IDs

Manage form slugs in **Forms → Settings → Wicket** (global) or **Forms → [form] → Settings → Wicket** (per-form). Both UIs read and write the same `wicket_gf_slug_mapping` option.

```php
// Lookup form ID by slug
$form_id = wicket_gf_get_form_id_by_slug('my-form');

// Or resolve any identifier (ID, slug, or form array) to a form array
$form = wicket_gf_resolve_form('my-form');

// Use shortcode with slug
[wicket_gravityform slug="my-form"]

// Combine with other parameters
[wicket_gravityform slug="my-form" title="false" description="false"]
```

This allows you to:

- Set the shortcode once on a page/template
- Update the form ID later without touching the shortcode
- Switch between development and production forms easily

### MDP Field Mapping

Each Gravity Forms field can be mapped to a Wicket MDP target object via the field settings panel's **Enable MDP Mapping** checkbox. Supported target objects:

| Target Object | Entity | Description |
|---|---|---|
| Person Profile | Person | Top-level person attributes (name, job title, language) |
| Additional Info | Person | Custom schema-based fields discovered via MDP API |
| Preferences | Person | Communication opt-ins and sublists |
| Org Profile | Organization | Organization attributes |

Submissions sync asynchronously via WP-Cron (`wicket_gf_mdp_sync_process` cron hook), with a synchronous fallback if scheduling fails. Each entry records a `wicket_mdp_sync_status` meta value (`pending` / `success` / `failed` / `skipped`). See [docs/product/mdp-field-mapping.md](docs/product/mdp-field-mapping.md) for the full configuration walkthrough.

### Custom Confirmation Types

The plugin adds three confirmation types to Gravity Forms' **Type** dropdown under **Form Settings → Confirmations**:

- **Same Page redirect** — redirects to the page that hosted the form (with optional query string)
- **Cart redirect** — redirects to `wc_get_cart_url()` (WooCommerce only)
- **Checkout Link redirect** — redirects to the WooCommerce Blocks `/checkout-link/` route

### REST API

```bash
POST /wp-json/wicket-gf/v1/resync-member-fields
```

Refreshes the cached Wicket member-data schema used by the Mapping Add-On.

## Available Fields

The plugin registers these custom Gravity Forms field types:

| Class (`WicketGF\Fields\…`) | GF Field Type | Description |
|---|---|---|
| `OrgSearchSelect` | `wicket_org_search_select` | Organization search and selection |
| `UserMdpTags` | `wicket_user_mdp_tags` | MDP tags for the current user |
| `WidgetProfile` | `wicket_widget_profile_individual` | Embedded individual profile editor widget |
| `WidgetProfileOrg` | `wicket_widget_profile_org` | Embedded organization profile editor widget |
| `WidgetAdditionalInfo` | `wicket_widget_ai` | Additional information widget |
| `WidgetPrefs` | `wicket_widget_prefs` | Communication preferences widget |
| `DataBindHidden` | `wicket_data_hidden` | Hidden field with live data binding |
| `ApiDataBind` | `wicket_api_data_bind` | API data binding field |

`WicketGF\Fields\ConsentFieldExtension` extends the built-in Gravity Forms Consent field with a "Sync option to MDP" setting rather than registering a new type.

## Admin Settings

Settings page: **Forms → Settings → Wicket** tab.

- **Form Slug mappings** — central JSON map of slug → form ID
- **Use Sidebar Pagination Layout** — when enabled, converts Gravity Forms multi-page top-step pagination into a sidebar layout (`wicket_gf_pagination_sidebar_layout` option)

Per-form settings: **Forms → [form] → Settings → Wicket** tab — sets that form's slug (writes to the same central mapping).

## Development

### Setup

```bash
# Install dependencies
composer install
```

### ⚠️ IMPORTANT: Before Tagging a New Version

**Always run `composer production` before tagging a new version.** This command:
- Removes development dependencies
- Optimizes autoloader for production
- Generates a clean build without dev packages

```bash
composer production
```

Without this step, the plugin will include unnecessary dev dependencies in the release.

Tags use bare version numbers (e.g. `2.4.8`), not a `v` prefix.

### Running Tests

The plugin uses **PEST** and **PHPUnit** for testing.

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run tests with coverage report
composer test:coverage

# Run browser tests (requires local WordPress instance with Wicket Docker setup)
composer test:browser

# Run specific test file
./vendor/bin/pest tests/unit/WicketGfMainTest.php
```

**Note:** Browser tests require a local WordPress instance running with the Wicket Docker setup. Ensure you have a site (e.g., PACE or any other) running locally before executing browser tests.

Before running browser tests, create a local `.env` file in the plugin root:

```bash
cp .env.example .env
```

Then update the values in `.env`:

- `WICKET_BROWSER_BASE_URL` (usually `https://localhost`)
- `WICKET_BROWSER_IGNORE_HTTPS_ERRORS` (`1` for local/self-signed SSL, otherwise `0`)
- `WICKET_BROWSER_USERNAME`
- `WICKET_BROWSER_PASSWORD`
- `WICKET_BROWSER_ORGSS_SCENARIO_1_PATH`

The `composer test:browser` script automatically loads `.env` if present.

**Playwright Setup:** Install Playwright: `npm install playwright@latest && npx playwright install`

### Writing New Tests

1. **Create test file** in `tests/unit/` with pattern `*Test.php`
2. **Extend AbstractTestCase** for WordPress function mocking

```php
<?php

declare(strict_types=1);

namespace WicketGF\Tests;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('Wicket_Gf_Main')]
class MyNewTest extends AbstractTestCase
{
    public function test_something(): void
    {
        $this->assertTrue(true);
    }
}
```

3. **Use Brain Monkey** to mock WordPress functions:

```php
\Brain\Monkey\Functions\stubs([
    'shortcode_exists' => true,
    'rest_get_server' => $mock_server,
]);
```

4. **Run tests** - PHPUnit auto-discovers test files matching `*Test.php`

### Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap with WordPress mocks
└── unit/
    ├── AbstractTestCase.php   # Base test class with Brain Monkey setup
    ├── WicketGfMainTest.php   # Main class tests
    ├── GFWicketFieldOrgSearchSelectTest.php
    ├── ShortcodeTest.php      # Shortcode tests
    ├── RestRoutesTest.php     # REST API tests
    └── WicketGfVersionTest.php
```

### Code Style

```bash
# Check code style
composer lint

# Fix code style automatically
composer format
```

### Available Composer Scripts

```bash
composer production       # Build for production (remove dev deps, optimize autoload)
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:coverage   # Run tests with HTML coverage report
composer test:browser    # Run browser tests
composer lint            # Check code style
composer format          # Fix code style
composer check           # Run lint + test
composer version-bump    # Bump plugin version
```

## Documentation

Full documentation lives under [`docs/`](docs/index.md):

- **Product** — [Overview](docs/product/overview.md), [Field Types](docs/product/field-types.md), [MDP Field Mapping Guide](docs/product/mdp-field-mapping.md)
- **Engineering** — [Field Architecture](docs/engineering/field-architecture.md), [Field & Form Slug System](docs/engineering/field-slugs.md), [Hooks: Filters & Actions](docs/engineering/hooks.md)
- **Guides** — [Add a Wicket Widget Field to a Form](docs/guides/add-widget-to-form.md)

## Support

- **Issues**: [GitHub Issues](https://github.com/industrialdev/wicket-wp-gravity-forms/issues)
- **Wicket developer docs**: https://wicket.io/docs

## License

GPL v2 or later

## Credits

Developed by [Wicket](https://wicket.io)
