<?php
/**
 * Plugin Name:       Buttercup
 * Plugin URI:        https://github.com/rosematcha/buttercup/
 * Description:       Custom blocks for Reese's sites.
 * Version:           1.0.0
 * Author:            Reese Lundquist
 * Author URI:        https://rosematcha.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       buttercup
 * Requires at least: 5.3
 * Requires PHP:      7.2
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BUTTERCUP_MAX_BLOCK_DEPTH' ) ) {
	define( 'BUTTERCUP_MAX_BLOCK_DEPTH', 40 );
}

if ( ! defined( 'BUTTERCUP_PLUGIN_FILE' ) ) {
	define( 'BUTTERCUP_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'BUTTERCUP_PLUGIN_DIR' ) ) {
	define( 'BUTTERCUP_PLUGIN_DIR', __DIR__ );
}

/* ── Includes ── */

require_once BUTTERCUP_PLUGIN_DIR . '/includes/cache.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/images.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/homepage-feed.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/tag-showcase.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/member-pages.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/rest.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-settings.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-cpt.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-render.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-template.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/facebook-sync.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/ical-parser.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/facebook-html-parser.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-import.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-wizard-common.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-add-new.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/events-edit-wizard.php';

/* ── Member slug helpers ── */

/**
 * Normalize a member slug using sanitize_title.
 *
 * @param string $slug Raw slug value.
 * @return string Sanitized slug.
 */
function buttercup_normalize_member_slug( $slug ) {
	return sanitize_title( (string) $slug );
}

/**
 * Check whether a slug is a valid member slug.
 *
 * @param string $slug Slug to validate.
 * @return bool True if the slug is valid.
 */
function buttercup_is_valid_member_slug( $slug ) {
	return is_string( $slug ) && '' !== $slug && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
}

/**
 * Get cached member match result for a request path.
 *
 * @param string|null $request_path Optional request path override.
 * @param bool        $refresh      Whether to bypass the memo cache.
 * @return mixed Cached member match result.
 */
function buttercup_get_member_match_cache( $request_path = null, $refresh = false ) {
	static $memo = array();
	$path        = null === $request_path ? buttercup_get_request_path() : trim( (string) $request_path, '/' );
	$cache_key   = '' === $path ? '__empty__' : $path;

	if ( ! $refresh && array_key_exists( $cache_key, $memo ) ) {
		return $memo[ $cache_key ];
	}

	$memo[ $cache_key ] = buttercup_match_member_request( $path );
	return $memo[ $cache_key ];
}

/* ── Rewrite flush scheduling ── */

/**
 * Schedule a rewrite rules flush on shutdown.
 *
 * @return void
 */
function buttercup_schedule_rewrite_flush() {
	update_option( 'buttercup_needs_rewrite_flush', 1, false );
}

/**
 * Flush rewrite rules if a flush has been scheduled.
 *
 * @return void
 */
function buttercup_maybe_flush_rewrite_rules() {
	static $flushed = false;

	if ( $flushed ) {
		return;
	}

	if ( 1 !== intval( get_option( 'buttercup_needs_rewrite_flush', 0 ) ) ) {
		return;
	}

	$flushed = true;
	flush_rewrite_rules( false );
	delete_option( 'buttercup_needs_rewrite_flush' );
}
add_action( 'shutdown', 'buttercup_maybe_flush_rewrite_rules' );

/* ── Cache invalidation hooks ── */

add_action( 'save_post', 'buttercup_invalidate_cached_views' );
add_action( 'deleted_post', 'buttercup_invalidate_cached_views' );
add_action( 'set_object_terms', 'buttercup_invalidate_cached_views_for_terms', 10, 6 );

/* ── Block registration ── */

/**
 * Register all Buttercup Gutenberg blocks.
 *
 * @return void
 */
function buttercup_blocks_init() {
	register_block_type( __DIR__ . '/build/team' );
	register_block_type( __DIR__ . '/build/team-member' );
	register_block_type( __DIR__ . '/build/row-layout' );
	register_block_type( __DIR__ . '/build/row-column' );
	register_block_type(
		__DIR__ . '/build/homepage-feed',
		array(
			'render_callback' => 'buttercup_render_homepage_feed',
		)
	);
	register_block_type(
		__DIR__ . '/build/tag-showcase',
		array(
			'render_callback' => 'buttercup_render_tag_showcase',
		)
	);
	register_block_type(
		__DIR__ . '/build/events',
		array(
			'render_callback' => 'buttercup_render_events',
		)
	);
}
add_action( 'init', 'buttercup_blocks_init' );

/* ── Event editor sidebar panel ── */

/**
 * Enqueue the event meta sidebar script on event editor screens.
 *
 * @return void
 */
function buttercup_enqueue_event_meta_sidebar() {
	$screen = get_current_screen();
	if ( ! $screen || 'buttercup_event' !== $screen->post_type ) {
		return;
	}

	$asset_file = BUTTERCUP_PLUGIN_DIR . '/build/events-meta/index.asset.php';
	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;
	wp_enqueue_script(
		'buttercup-event-meta',
		plugins_url( 'build/events-meta/index.js', BUTTERCUP_PLUGIN_FILE ),
		$asset['dependencies'],
		$asset['version'],
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'buttercup_enqueue_event_meta_sidebar' );

/* ── Pass plugin defaults to the block editor ── */

/**
 * Localize plugin default settings for the block editor.
 *
 * @return void
 */
function buttercup_localize_block_defaults() {
	$defaults = array(
		'feed'     => array(
			'ctaLabel'    => get_option( 'buttercup_feed_cta_label', '' ),
			'mastTagSlug' => get_option( 'buttercup_feed_mast_tag', 'mast' ),
			'homeTagSlug' => get_option( 'buttercup_feed_home_tag', 'home' ),
		),
		'showcase' => array(
			'buttonLabel'      => get_option( 'buttercup_showcase_button_label', '' ),
			'maxItems'         => intval( get_option( 'buttercup_showcase_max_items', 12 ) ),
			'imageAspectRatio' => get_option( 'buttercup_showcase_aspect_ratio', '16/9' ),
		),
		'team'     => array(
			'imageShape' => get_option( 'buttercup_team_image_shape', 'squircle' ),
			'imageSize'  => intval( get_option( 'buttercup_team_image_size', 192 ) ),
		),
		'events'   => array(
			'eventsPerPage' => intval( get_option( 'buttercup_events_per_page', 6 ) ),
		),
	);

	wp_add_inline_script(
		'wp-block-editor',
		'window.buttercupDefaults = ' . wp_json_encode( $defaults ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'buttercup_localize_block_defaults' );

/* ── Conditional dashicon enqueue ── */

/**
 * Enqueue dashicons on the front end when team blocks are present.
 *
 * @return void
 */
function buttercup_enqueue_dashicons() {
	if ( is_admin() ) {
		return;
	}

	global $post;
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$needs_dashicons = has_block( 'buttercup/team', $post )
		|| has_block( 'buttercup/team-member', $post );

	if ( $needs_dashicons ) {
		wp_enqueue_style( 'dashicons' );
	}
}
add_action( 'enqueue_block_assets', 'buttercup_enqueue_dashicons' );

/* ── Activation ── */

register_activation_hook( BUTTERCUP_PLUGIN_FILE, 'buttercup_activation_refresh' );
register_deactivation_hook( BUTTERCUP_PLUGIN_FILE, 'buttercup_fb_sync_deactivate' );
