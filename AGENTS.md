# Repository Guidelines

## Project Structure & Module Organization
This plugin lives at `src/web/app/plugins/wicket-wp-gravity-forms`.
- Core bootstrap: `class-wicket-wp-gf.php`
- Runtime PHP modules: `includes/`
- Admin-specific code: `admin/`
- Frontend assets: `assets/js`, `assets/css`, `assets/images`
- Tests: `tests/unit` (PHPUnit-style `*Test.php`), `tests/Browser` (Pest browser `*.pest.php`)
- Tooling/config: `composer.json`, `phpunit.xml`, `phpcs.xml`, `.php-cs-fixer.dist.php`, `.ci/`

## Build, Test, and Development Commands
Use Composer scripts as the source of truth:
- `composer install`: install PHP dependencies.
- `composer test`: run default Pest suite.
- `composer test:unit`: run unit tests only.
- `composer test:browser`: run browser tests (loads `.env` if present).
- `composer test:coverage`: generate HTML coverage in `coverage/`.
- `composer lint`: run PHP CS Fixer in dry-run mode.
- `composer format` or `composer cs:fix`: apply formatting fixes.
- `composer check`: run lint + tests.
- `composer production`: build production vendor tree (`--no-dev`, optimized autoloader).

## Coding Style & Naming Conventions
- PHP target is 8.2+ (see `composer.json`), WordPress-compatible patterns.
- Follow PSR-12 and project `.editorconfig` (4 spaces for PHP, LF endings; CSS uses 2 spaces).
- Prefer `declare(strict_types=1);` for new PHP files.
- Class names: PascalCase (`WicketGfMainTest`); methods: snake_case in legacy classes may remain for compatibility, new test methods use descriptive `test_*` names.
- Keep changes minimal and backward compatible.

## Testing Guidelines
- Unit suite is defined in `phpunit.xml` under `tests/unit` with `*Test.php` suffix.
- Browser suite is `tests/Browser` with `.pest.php` suffix and requires local WP + Playwright.
- For browser tests, copy `.env.example` to `.env` and set `WICKET_BROWSER_BASE_URL` and credentials.
- Add/adjust tests for behavior changes; run `composer check` before opening a PR.

## Commit & Pull Request Guidelines
Recent history favors short, imperative commit subjects (examples: `restore format`, `fixes improper org uuid`, `Orgss tests added`).
- Keep commit titles concise, action-first, and scoped to one change.
- PRs should include: problem statement, approach, test evidence (`composer test`/`composer check` output), and linked issue.
- Include screenshots or recordings for admin/UI behavior changes.

## Security & WordPress Practices
- Gate privileged behavior with capability checks.
- Use nonces for state-changing requests.
- Sanitize input and escape output (`sanitize_*`, `esc_*`, `wp_kses_*`) consistently.
