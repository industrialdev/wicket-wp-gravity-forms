# Repository Guidelines

## Project Structure & Module Organization
This plugin lives at `src/web/app/plugins/wicket-wp-gravity-forms`.
- Core bootstrap: `class-wicket-wp-gf.php`
- Runtime PHP modules: `src/` (`Admin.php`, `MappingAddOn.php`, the `Mdp*` sync engine family, `Fields/`, `helpers.php`)
- Frontend assets: `assets/js`, `assets/css`, `assets/images`
- Tests: none in this repo. Stack tests live in the `qa/` workspace (wicket-warden); do not add tests here.
- Tooling/config: `composer.json`, `phpcs.xml`, `.php-cs-fixer.dist.php`, `.editorconfig`, `.ci/`

## Build, Verify, and Development Commands
Use Composer scripts as the source of truth (`composer.json`):
- `composer install`: install PHP dependencies.
- `composer check`: PHP CS Fixer dry-run over the repo. This is the pre-PR gate.
- `composer cs:lint`: same formatting check, standalone.
- `composer cs:format` or `composer cs:fix`: apply formatting fixes.
- `composer production`: build production vendor tree (`--no-dev`, optimized autoloader).

## Coding Style & Naming Conventions
- PHP target is 8.2+ (see `composer.json`), WordPress-compatible patterns.
- Follow PSR-12 and project `.editorconfig` (4 spaces for PHP, LF endings; CSS uses 2 spaces).
- `declare(strict_types=1);` on new PHP files (the legacy bootstrap and helper scripts predate it).
- Class names: PascalCase (`MdpSyncEngine`, `MappingAddOn`); methods: snake_case (WordPress style).
- Keep changes minimal and backward compatible.

## Testing and Verification
- This repo has no test suite and must not grow one. The stack QA suite (wicket-warden) owns tests: see `qa/` in the `wicket-wp-stack` workspace, run with `wicket test` from there.
- Run `composer check` before opening a PR. `php -l` on edited files is the fast syntax pass.
- Verify MDP sync behavior changes on the Memberships Test staging site when practical, then record the evidence in the PR.

## Commit & Pull Request Guidelines
- Use Conventional Commits prefixes (`feat:`, `fix:`, `docs:`, `chore:`, `perf:`, `refactor:`); the release bot groups changelog entries by prefix.
- Keep commit titles concise, action-first, and scoped to one change.
- PRs use the repo template: problem statement, approach, verification evidence, and the linked task.
- Include screenshots or recordings for admin/UI behavior changes.

## Release Process (Automated)

Releases are **fully automated**. Merging a PR to `main` cuts a release via the `wicket-release-bot` GitHub App: it bumps the version, prepends `CHANGELOG.md`, commits `chore(release): x.y.z`, and pushes the matching git tag. No one needs push access to `main`.

**Never do these by hand:** bump the version, edit `composer.json` / the main file header / `*_VERSION` constants (and `style.css` for the theme), or create git tags. The bot owns all of that after merge.

### Releasing (default behavior)

Every PR merged to `main` releases automatically with a **patch** bump. Control the bump by putting a marker in the **PR title** (squash-merge makes the title the commit message):

| Marker | Result |
|---|---|
| _(none)_ | patch (`2.4.10` -> `2.4.11`) |
| `#minor` | minor (`2.4.10` -> `2.5.0`) |
| `#major` | major (`2.4.10` -> `3.0.0`) |
| `#norelease` | no bump, no tag |

### Not releasing

Add `#norelease` to the PR title for docs/tooling-only changes that should not cut a version. **Every merge releases unless the message contains `#norelease`.**

### Commit conventions that affect the changelog

- Use conventional prefixes: `feat:`, `fix:`, `docs:`, `chore:`, `perf:`, `refactor:`, etc. The changelog groups entries by prefix.
- `feat!:` (or any `!:`) flags a **BREAKING** change in the changelog.
- **Squash-merge** yields the cleanest changelog (one PR = one line). Merge commits list each individual commit.
- A release lists **everything merged since the last tag**, not just the triggering PR. Catch-up is expected.

### Local version bump (optional)

`composer version-bump` (or `php .ci/version-bump.php`) edits version files only; it never commits or tags. Use it to preview, not to release.

Full details, markers, and troubleshooting: [`docs/engineering/release-automation.md`](docs/engineering/release-automation.md).

## Security & WordPress Practices
- Gate privileged behavior with capability checks.
- Use nonces for state-changing requests.
- Sanitize input and escape output (`sanitize_*`, `esc_*`, `wp_kses_*`) consistently.
