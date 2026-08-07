# Changelog

All notable changes to this plugin are documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).

<!-- new releases inserted below this line -->

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

