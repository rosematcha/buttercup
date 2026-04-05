<?php
// This file is the PHPUnit bootstrap for integration tests and is not loaded by WordPress.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

declare(strict_types=1);

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
}

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

$functions_file = $_tests_dir . '/includes/functions.php';
if (!file_exists($functions_file)) {
	fwrite(
		STDERR,
		"WordPress test suite not found at {$functions_file}. Run composer test:php:install first.\n"
	);
	exit(1);
}

require_once $functions_file;

tests_add_filter('muplugins_loaded', static function (): void {
	require dirname(__DIR__, 2) . '/buttercup.php';
});

require $_tests_dir . '/includes/bootstrap.php';
