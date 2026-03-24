#!/usr/bin/env bash
set -euo pipefail

WP_TESTS_DIR="${1:-${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}}"
WP_CORE_DIR="${2:-${WP_CORE_DIR:-/tmp/wordpress}}"
DB_NAME="${3:-${WP_TESTS_DB_NAME:-wordpress_test}}"
DB_USER="${4:-${WP_TESTS_DB_USER:-root}}"
DB_PASS="${5:-${WP_TESTS_DB_PASS:-root}}"
DB_HOST="${6:-${WP_TESTS_DB_HOST:-127.0.0.1:3306}}"
WP_VERSION="${7:-${WP_VERSION:-latest}}"
WP_CLI_PHP_ARGS="${WP_CLI_PHP_ARGS:--d memory_limit=512M}"

export WP_CLI_PHP_ARGS

if ! command -v wp >/dev/null 2>&1; then
	echo "wp-cli is required. Install it before running this script." >&2
	exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
	echo "curl is required. Install it before running this script." >&2
	exit 1
fi

mkdir -p "$WP_TESTS_DIR"
mkdir -p "$WP_CORE_DIR"

WP_BIN="$(command -v wp)"

run_wp() {
	php -d memory_limit=512M "$WP_BIN" "$@"
}

if [ ! -f "$WP_CORE_DIR/wp-load.php" ]; then
	echo "Downloading WordPress core ($WP_VERSION) to $WP_CORE_DIR"
	run_wp core download --path="$WP_CORE_DIR" --version="$WP_VERSION" --force --quiet
fi

if [ ! -f "$WP_CORE_DIR/wp-config.php" ]; then
	echo "Creating WordPress config"
	run_wp config create \
		--path="$WP_CORE_DIR" \
		--dbname="$DB_NAME" \
		--dbuser="$DB_USER" \
		--dbpass="$DB_PASS" \
		--dbhost="$DB_HOST" \
		--skip-check \
		--force \
		--quiet
fi

echo "Ensuring test database exists"
tries=0
until run_wp db create --path="$WP_CORE_DIR" --quiet >/dev/null 2>&1 || run_wp db check --path="$WP_CORE_DIR" --quiet >/dev/null 2>&1; do
	tries=$((tries + 1))
	if [ "$tries" -ge 20 ]; then
		echo "Could not connect to MySQL at $DB_HOST after $tries attempts." >&2
		exit 1
	fi
	sleep 3
done

run_wp core install \
	--path="$WP_CORE_DIR" \
	--url="http://localhost" \
	--title="Buttercup Test Site" \
	--admin_user="admin" \
	--admin_password="password" \
	--admin_email="admin@example.org" \
	--skip-email \
	--quiet >/dev/null 2>&1 || true

archive_url=""
archive_root=""

if [ "$WP_VERSION" = "latest" ]; then
	archive_url="https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz"
	archive_root="wordpress-develop-trunk"
else
	archive_url="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz"
	archive_root="wordpress-develop-${WP_VERSION}"
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

echo "Downloading WordPress test suite files"
curl -sSL "$archive_url" -o "$tmp_dir/wordpress-develop.tar.gz"
tar -xzf "$tmp_dir/wordpress-develop.tar.gz" -C "$tmp_dir"

rm -rf "$WP_TESTS_DIR/includes" "$WP_TESTS_DIR/data"
cp -R "$tmp_dir/$archive_root/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
cp -R "$tmp_dir/$archive_root/tests/phpunit/data" "$WP_TESTS_DIR/data"

core_path="${WP_CORE_DIR%/}/"

cat > "$WP_TESTS_DIR/wp-tests-config.php" <<PHP
<?php

define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'ABSPATH', '${core_path}' );

define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

\$table_prefix = 'wptests_';
PHP

echo "WordPress test environment is ready."
