# Testing

## Prerequisites

- `composer` (v2+)
- `wp` (WP-CLI)
- MySQL server reachable from your machine

## JavaScript checks

```bash
npm run lint:js
npm run lint:css
npm run test:unit
```

## PHP integration tests

Install WordPress core + test suite dependencies:

```bash
composer install
composer test:php:install
```

Run the integration suite:

```bash
composer test:php
```

## Environment variable overrides

`composer test:php:install` accepts these environment variables:

- `WP_TESTS_DIR` (default: `/tmp/wordpress-tests-lib`)
- `WP_CORE_DIR` (default: `/tmp/wordpress`)
- `WP_TESTS_DB_NAME` (default: `wordpress_test`)
- `WP_TESTS_DB_USER` (default: `root`)
- `WP_TESTS_DB_PASS` (default: `root`)
- `WP_TESTS_DB_HOST` (default: `127.0.0.1:3306`)
- `WP_VERSION` (default: `latest`)

Example:

```bash
WP_TESTS_DB_NAME=buttercup_test \
WP_TESTS_DB_USER=buttercup \
WP_TESTS_DB_PASS=secret \
WP_TESTS_DB_HOST=127.0.0.1:3306 \
composer test:php:install
```
