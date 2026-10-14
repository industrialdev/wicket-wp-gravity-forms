# Changelog

All notable changes to this plugin are documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).

<!-- new releases inserted below this line -->

## [2.8.2] - 2026-10-14

### Fixed
- **uploads:** keep the capability filter fail-closed and fix message i18n (WWID-2732)
- **fields:** surface server error reasons in the editor loaders (WWID-2732)
- **uploads:** cover secure downloads in the capability fallback (WWID-2732)
- **fields:** gate editor capability checks through current_user_can_any (WWID-2732)

### Maintenance
- **fields:** harden %s substitution and tighten the filter docs (WWID-2732)


## [2.8.1] - 2026-10-13

### Fixed
- **apidatabind:** move save capture to get_value_save_input (WWID-2748)
- **apidatabind:** sanitize save path and rework required handling (WWID-2748)
- **apidatabind:** capture entry value server-side at save (WWID-2748)


## [2.8.0] - 2026-10-13

### Added
- **orgss:** add org card display toggles to the field (WWID-2710)

### Fixed
- **orgss:** restore Display Org Type checkbox state on load (WWID-2710)


## [2.7.1] - 2026-10-06

### Maintenance
- **logger:** flag MdpSyncLogger as dead code in source (WWID-2665)
- **admin:** remove dead retention setting remnants; mark org-validation duplication deliberate (WWID-2665)
- **ci:** fail closed when main moves before release push


## [2.7.0] - 2026-09-30

### Added
- **import:** add opt-in update-existing-form mode (WWID-2663)

### Fixed
- **import:** harden update importer per review (WWID-2663)
- **fields:** fail closed on malformed widget JSON settings (WWID-2665)


## [2.6.4] - 2026-09-29

### Fixed
- state multi-type resource requirements with "and"


## [2.6.3] - 2026-09-28

### Fixed
- name the required type in the widget validation banner
- name the missing resource type in org profile validation


## [2.6.2] - 2026-09-28

### Fixed
- derive widget validation state from latest per-widget payload


## [2.6.1] - 2026-09-28

### Fixed
- serve cached MDP discovery over fail flag, summarize API errors

### Documentation
- align AGENTS.md with actual layout, scripts, and test policy


## [2.6.0] - 2026-09-25

### Added
- add lifecycle logging for MDP sync drain, pushes, and resync

### Fixed
- drain MDP syncs only on manager page loads, max five per request
- guard payload build in sync push, surface resync failures
- default MDP field sync to synchronous submission-time push
- gate resync-member-fields route behind GF edit capability
- recover syncs stranded by disabled WP-Cron; checkbox first-wins


## [2.5.4] - 2026-09-01

### Fixed
- stop widget validation payloads clobbering API Data Bind fields


## [2.5.3] - 2026-08-31

### Added
- **mdp:** complete field mapping sync per WWID-812 spec

### Fixed
- **mdp:** harden sync per peer review of WWID-812


## [2.5.2] - 2026-08-28

### Fixed
- **orgss:** keep Next hidden on fresh ORGSS page load

### Documentation
- add PR description template #norelease


## [2.5.1] - 2026-08-21

### Fixed
- **orgss:** stop checkbox script fighting GF footer visibility


## [2.5.0] - 2026-08-17

### Added
- **fields:** add service identity data source to API Data Bind

### Fixed
- **fields:** scope ORGSS binding to organization source, editor hardening
- **fields:** capability-check ApiDataBind AJAX handlers
- **fields:** service identity empty path and editor reopen bugs


## [2.4.19] - 2026-08-13

### Added
- **gravity-forms:** block invalid JSON saves and clarify JSON vs MDP validity

### Fixed
- **gravity-forms:** enforce strict JSON for Required Resources


## [2.4.18] - 2026-08-07

### Added
- **orgss:** pass auto_advance to the org-search-select component

### Fixed
- **WWID-2118:** make required Additional Info widget field fail-closed


## [2.4.17] - 2026-07-28

### Fixed
- **i18n:** make hardcoded frontend JS/PHP strings translatable


## [2.4.16] - 2026-07-27

### Fixed
- **autoload:** exclude vendored Google Sheet class from classmap


## [2.4.15] - 2026-07-20

### Added
- add MDP Widget Config setting to widget-profile GF fields

### Fixed
- stop running mdp_json_config through wp_kses_post
- reject list-shaped JSON as widget_config in both GF fields

### Documentation
- clarify migrate link replaces, not merges, the fields key
- add automated release process to AGENTS.md #norelease
- self-contained release automation reference #norelease
- add release automation reference #norelease


## [2.4.14] - 2026-07-09

### Added
- **ci:** auto version bump, tag, and changelog on merge to main

