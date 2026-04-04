# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Plugin metadata: minimum WP/PHP versions, license, Update URI
- `uninstall.php` for clean removal of plugin data

### Fixed
- Facebook sync now sends access token via Authorization header instead of URL query string

### Changed
- N/A

## [1.0.0]

### Added
- Homepage feed block with per-item rendering and auto-expand
- Tag showcase block with grid layout
- Team and team-member blocks with individual member pages
- Row layout and row column blocks
- Events custom post type with archive and single templates
- Facebook event sync via Graph API
- iCal event import
- Event creation wizard in wp-admin
- Settings page for events, feed, showcase, and team options
- Transient-based caching with version-bumped invalidation
- PHP testing infrastructure
