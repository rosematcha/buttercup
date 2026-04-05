# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Plugin metadata: minimum WP/PHP versions, license, Update URI
- `uninstall.php` for clean removal of plugin data
- Events system: custom post type, archive/single templates, settings page, creation wizard, iCal import
- Events REST endpoints (`events-status`, `events-sync`) with editor sidebar block
- Facebook event sync via Graph API on WP-Cron schedule
- Homepage feed `home-all` render mode for displaying all home-tagged posts
- Homepage feed images meta box now available on `buttercup_event` posts
- Deprecated v1 save handler for team-member block backward compatibility
- `enableMemberPage` attribute on team-member block (replaces `disableMemberPage`)
- Caching layer with versioned transient invalidation

### Fixed
- Facebook sync now sends access token via Authorization header instead of URL query string
- JS lint errors in `event-details-panel.js`: JSDoc alignment, `@returns` → `@return`, missing braces, missing `@param` for `LinkedPageCard`, unused variable, conditional hook call, nested ternary
- Nested ternary in `homepage-feed/edit.js` (introduced by prettier autofix)
- Composer lock file: pin `doctrine/instantiator` to PHP 8.2-compatible version via platform config

### Changed
- Homepage feed, tag showcase, and team blocks now pull defaults from plugin settings
- Removed hard cap of 5 home-tagged posts in homepage feed collection
- Build scripts updated to include events-meta entry point

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
