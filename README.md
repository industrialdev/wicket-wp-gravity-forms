# Wicket WP Gravity Forms

Integration plugin that connects Wicket's member data platform with Gravity Forms, providing custom fields and data synchronization capabilities.

## Features

- **Wicket Data Type for Populate Anything**: Query people and organization data live
- **Wicket Settings Page**: Map slugs to Gravity Form IDs for easier management
- **Slug-based Form Lookup**: `wicket_gf_get_form_id_by_slug($slug)` function
- **Enhanced Shortcode**: `[wicket_gravityform]` accepts `slug` parameter instead of `id`
- **Custom Fields**: Organization search, MDP tags, profile widgets, and data binding fields
- **REST API**: Endpoints for member field synchronization

## Requirements

- **WordPress**: 6.0+
- **PHP**: 8.2+
- **Gravity Forms**: 2.5+
- **Composer**: For dependency management

## Installation

This plugin is not available in the WordPress.org plugin repository. Install via:

```bash
cd wp-content/plugins
git clone https://github.com/industrialdev/wicket-wp-gravity-forms.git
```

Then activate through WordPress admin.

## Usage

### Using Slugs Instead of IDs

Define form slugs in **Wicket Settings** under the Gravity Forms menu:

```php
// Lookup form ID by slug
$form_id = wicket_gf_get_form_id_by_slug('my-form');

// Use shortcode with slug
[wicket_gravityform slug="my-form"]

// Combine with other parameters
[wicket_gravityform slug="my-form" title="false" description="false"]
```

This allows you to:
- Set the shortcode once on a page/template
- Update the form ID later without touching the shortcode
- Switch between development and production forms easily

### REST API

Synchronize member fields:

```bash
POST /wp-json/wicket-gf/v1/resync-member-fields
```

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

**Playwright Setup:** Install Playwright globally if desired: `npm install -g playwright@latest && npx playwright install`

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

### Available Fields

The plugin registers these custom Gravity Forms fields:

| Field Class | Type | Description |
|------------|------|-------------|
| `GFWicketFieldOrgSearchSelect` | `wicket_org_search_select` | Organization search dropdown |
| `GFWicketFieldMdpTags` | `wicket_mdp_tags` | MDP tag selection |
| `GFWicketFieldProfileWidgets` | `wicket_profile_widgets` | Profile widget display |
| `GFWicketFieldDataBind` | `wicket_data_bind` | Data binding field |
| `GFWicketFieldHidden` | `wicket_hidden` | Hidden input field |
| `GFWicketFieldHtml` | `wicket_html` | HTML content field |
| `GFWicketFieldValidation` | `wicket_validation` | Validation field |
| `GFWicketFieldWpData` | `wicket_wp_data` | WordPress data field |

## Hooks & Filters

### Actions

```php
// Fired after custom fields are registered
do_action('wicket_gf_fields_registered');
```

### Filters

```php
// Modify allowed form statuses for guest checkout
apply_filters('wicket_gf_allowed_form_statuses', $statuses);
```

## Support

- **Issues**: [GitHub Issues](https://github.com/industrialdev/wicket-wp-gravity-forms/issues)
- **Documentation**: [Wicket Developer Docs](https://wicket.io/docs)

## License

GPL v2 or later

## Credits

Developed by [Wicket](https://wicket.io)
