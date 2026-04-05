# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- `readme.txt` for WordPress.org plugin directory compliance
- Plugin metadata: minimum WP/PHP versions, license
- PHPCS (`phpcs.xml`) with WordPress and PHPCompatibilityWP rulesets; `composer lint:php` script
- `README.md` for GitHub
- CI jobs for PHPCS and the WordPress Plugin Checker (`wordpress/plugin-check-action`)
- `/* translators: */` comments on all JS `__()` calls with printf-style placeholders

### Changed
- Reformat all PHP to WordPress Coding Standards (tabs, Yoda conditions, K&R braces, `array()`, spaces inside parens, `/** */` doc blocks on all functions)
- Relicense from MIT/GPL-2.0/ISC to Unlicense (public domain)

### Fixed
- Fix test slug case mismatch: force generated page slug to lowercase to match WordPress post_name storage
- Add ABSPATH guard to `uninstall.php` for plugin checker direct-access check
- Split plugin checker exclusions into `exclude-files` and `exclude-directories` (directories were silently ignored in `exclude-files`)
- Fix `trim()` deprecation on PHP 8.2: guard `wp_parse_url()` null return with `?? ''` in member-pages path stripping
- Update `readme.txt` "Tested up to" to 6.9
- Remove `Update URI` header (not allowed for WordPress.org-hosted plugins)
- Add output escaping (`esc_html__`, `esc_attr`, `wp_kses_post`, `intval`) across events, member pages, and import wizards
- Add `wp_unslash()` before sanitization on all `$_POST`/`$_SERVER` superglobal reads
- Add `isset()` checks on `$_SERVER['REQUEST_METHOD']` accesses
- Add `// translators:` comments on all `__()` calls with placeholders
- Replace `parse_url()` with `wp_parse_url()` in member pages
- Replace `date()` with `wp_date()` in events add-new
- Replace `print_r()` with `wp_json_encode()` in member pages debug output
- Prefix template and uninstall global variables with `buttercup_`
- Add `phpcs:ignore` annotations for intentional nonce-free GET reads and direct DB queries in uninstall
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
