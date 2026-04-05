# Buttercup

WordPress plugin providing custom Gutenberg blocks and an events system.

## Blocks

| Block | Description |
|---|---|
| **Row Layout / Row Column** | Flexible row-based layouts for organizing content |
| **Homepage Feed** | Dynamic homepage feed with per-item rendering and auto-expand |
| **Tag Showcase** | Display posts by tag in a visual showcase |
| **Team / Team Member** | Showcase team members with optional dedicated member pages |
| **Events Archive** | Display upcoming and past events |

## Events system

- Custom post type with start/end dates and location
- Facebook event sync via the Graph API (WP-Cron)
- iCal feed import
- Dedicated single and archive templates
- Settings page for defaults and display options

## Development

Requires [Node.js](https://nodejs.org/) and [Composer](https://getcomposer.org/).

```sh
npm install
composer install
```

| Command | Purpose |
|---|---|
| `npm start` | Watch mode for block JS/CSS |
| `npm run build` | Production build |
| `npm run lint:js` | Lint JavaScript |
| `npm run lint:css` | Lint CSS |
| `composer lint:php` | Lint PHP (WordPress Coding Standards) |
| `composer test:php` | Run PHPUnit integration tests |
| `npm run plugin-zip` | Create `buttercup.zip` for distribution |

## Requirements

- WordPress 5.3+
- PHP 7.2+

## License

[Unlicense](LICENSE) -- public domain.
