# Buttercup

WordPress plugin providing custom Gutenberg blocks and an events system for Reese's sites.

## Project structure

```
buttercup.php          Main plugin file (block registration, hooks, activation)
includes/              PHP backend — one file per feature area
  cache.php            Transient + object cache layer with versioned invalidation
  events-*.php         Events CPT, rendering, templates, settings, import wizard
  facebook-sync.php    Graph API sync for Facebook events (cron-driven)
  homepage-feed.php    Homepage feed block server rendering
  ical-parser.php      iCal feed parser
  images.php           Image helpers
  member-pages.php     Virtual member pages and routing
  rest.php             REST API endpoints
  tag-showcase.php     Tag showcase block server rendering
src/                   JS/CSS block source (compiled to build/)
  events/              Events archive block
  events-meta/         Events meta sidebar plugin
  homepage-feed/       Homepage feed block
  row-column/          Row column block
  row-layout/          Row layout block
  shared/              Shared utilities (REST query helpers)
  tag-showcase/        Tag showcase block
  team/                Team container block
  team-member/         Team member block
templates/             PHP templates for event single + archive pages
assets/                Static CSS (e.g. single-event.css)
uninstall.php          Cleanup handler — runs when the plugin is deleted
```

## Build and dev

- `npm start` — watch mode for block JS/CSS (runs two wp-scripts processes)
- `npm run build` — production build
- `npm run lint:js` / `npm run lint:css` — linting
- `npm run plugin-zip` — creates `buttercup.zip` for distribution
- `composer test:php` — runs PHPUnit integration tests (requires local WP test suite)

The build uses `@wordpress/scripts`. The `events-meta` block has a separate entry point built to `build/events-meta/`.

## Code style

- PHP: 4-space indent, WordPress-style function naming (`buttercup_` prefix), no namespaces
- JS: tabs, follows `@wordpress/scripts` ESLint config (`.eslintrc.js`)
- All user-facing PHP strings must be wrapped in `__()` or `esc_html__()` with text domain `'buttercup'`
- Output escaping is mandatory — use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate
- REST endpoints must declare `sanitize_callback` on all arguments
- Capability checks (`current_user_can()`) are required before any privileged operation
- Secrets (API tokens, access keys) go in Authorization headers, never in URL query strings

## Versioning

Follow semantic versioning for the plugin version number:

- **MAJOR** (2.0.0) — breaking changes to block markup, REST API, or stored data that require migration
- **MINOR** (1.1.0) — new features, blocks, or settings
- **PATCH** (1.0.1) — bug fixes, security patches, styling tweaks

When bumping the version, update it in **both** places:
1. `buttercup.php` plugin header (`Version:` field)
2. `package.json` (`"version"` field)

## Changelog

Maintain `CHANGELOG.md` with every version bump. Format:

```markdown
## [X.Y.Z]

### Added / Changed / Fixed / Removed
- Description of change
```

Keep an `[Unreleased]` section at the top for in-progress work. Move its contents into a versioned section when cutting a release. When committing changes, always update the `[Unreleased]` section of `CHANGELOG.md` in the same commit.

## WordPress compatibility

- **Requires at least:** WordPress 5.3 (uses `wp_date()`, `wp_timezone()`)
- **Requires PHP:** 7.2
- These minimums are declared in the plugin header and enforced by WordPress on activation

If you introduce a function from a newer WordPress version, update the `Requires at least` header in `buttercup.php` accordingly.

## Caching

The plugin uses a versioned cache strategy (`buttercup_cache_version` option). When data changes, call `buttercup_bump_cache_version()` to invalidate all cached views rather than deleting individual transients. Cache invalidation hooks are already registered for post saves and tag changes.

## Events system

- Custom post type: `buttercup_event`
- Meta fields: `_buttercup_event_start`, `_buttercup_event_end`, `_buttercup_event_location`, `_buttercup_event_facebook_id`
- Facebook sync runs on a WP-Cron schedule (`buttercup_facebook_sync_events`)
- Templates in `templates/` override theme templates for the event CPT

## Common tasks

**Adding a new block:**
1. Create `src/<block-name>/` with `block.json`, `index.js`, `edit.js`
2. If it needs server rendering, add a render callback in `includes/` and register it in `buttercup.php`
3. If it has a separate entry point (like events-meta), add it to both the `build` and `start` scripts in `package.json`

**Adding a new option:**
1. Register the setting and field in `includes/events-settings.php`
2. Add the option key to the `$options` array in `uninstall.php`

**Adding a REST endpoint:**
1. Add the route in `includes/rest.php` with `sanitize_callback` on every argument
2. Use `permission_callback` with an appropriate capability check
