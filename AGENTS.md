# Repository Guidelines

## Project Structure & Module Organization

This plugin adds Wicket functions to Gravity Forms.
- Core bootstrap: `class-wicket-wp-gf.php`.
- Runtime PHP modules: `src/` (`Admin.php`, `MappingAddOn.php`, the `Mdp*` sync engine family, `Fields/`, `helpers.php`).
- Frontend assets: `assets/js`, `assets/css`, `assets/images`.
- Tests: none in this repo and none may grow here — the stack QA suite (wicket-warden, `qa/` in the workspace) owns tests; run them with `wicket test`.
- Tooling/config: `composer.json`, `phpcs.xml`, `.php-cs-fixer.dist.php`, `.editorconfig`, `.ci/`.

For the package map (field architecture, field slugs, hooks, the MDP JSON sync design, field types and MDP field mapping): `@see atlas://packages/wicket-wp-gravity-forms.md`. Follow PHP doc conventions per `@see atlas://conventions/php-doc-comment-style.md`.

## Documentation

The Atlas knowledge base holds the full engineering and product documentation for the whole Wicket stack. Start at `@see atlas://packages/wicket-wp-gravity-forms.md` for this package. The repo docs/ folder, when present, holds only public end-consumer guides; engineering material never lives beside the code.

## Build, Verify, and Development Commands

Use Composer scripts as the source of truth (`composer.json`):
- `composer install`: install PHP dependencies.
- `composer check`: PHP CS Fixer dry-run over the repo — the pre-PR gate.
- `composer cs:lint` / `composer cs:fix`: standalone formatting check / fix.
- `composer production`: build production vendor tree (`--no-dev`, optimized autoloader).
- `php -l <file>`: fast syntax pass on edited files.

## Coding Style & Naming Conventions

- PHP 8.2+ (see `composer.json`), WordPress-compatible patterns; PSR-12 and the project `.editorconfig` (4 spaces PHP, LF endings, 2 for CSS).
- `declare(strict_types=1);` on new PHP files (the legacy bootstrap predates it).
- Class names PascalCase (`MdpSyncEngine`, `MappingAddOn`); methods snake_case (WordPress style).
- Keep changes minimal and backward compatible.
- Verify MDP sync behavior changes on the Memberships Test staging site when practical, then record the evidence in the PR.

## Security & WordPress Practices

- Gate privileged behavior with capability checks; use nonces for state-changing requests.
- Sanitize input and escape output (`sanitize_*`, `esc_*`, `wp_kses_*`) consistently.

## Release & Branch Workflow

All work happens on branches. `main` is locked; changes land via peer-reviewed Pull Request. Never commit to `main` directly, and never push or open a PR without explicit human approval. PRs use the repo template: problem statement, approach, verification evidence, and the linked task; include screenshots or recordings for admin/UI behavior changes.

Merging a PR to `main` **auto-releases** via the `wicket-release-bot` GitHub App: version bump, `CHANGELOG.md` update, git tag. Never bump versions or create tags by hand. The bump level comes from a marker in the PR title (squash-merge makes it the commit message): _(none)_ / `#patch` = patch, `#minor`, `#major`, or `#norelease` (no release; use for docs/tooling-only merges). Conventional commit prefixes (`feat:`, `fix:`, `docs:`, ...) drive changelog grouping; a `!` (e.g. `feat!:`) flags a BREAKING change. Full rules: `@see atlas://conventions/release-automation.md`.
