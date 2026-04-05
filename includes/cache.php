<?php
/**
 * Transient and object cache layer with versioned invalidation.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the current cache version number.
 *
 * @return int Cache version.
 */
function buttercup_cache_version() {
	return intval( get_option( 'buttercup_cache_version', 1 ) );
}

/**
 * Build a versioned cache key from a namespace and parts.
 *
 * @param string $namespace Cache namespace.
 * @param array  $parts     Additional key parts.
 * @return string Hashed cache key.
 */
function buttercup_build_cache_key( $namespace, $parts = array() ) {
	$version = buttercup_cache_version();
	$payload = wp_json_encode( array( $namespace, $version, $parts ) );
	return 'buttercup:' . md5( (string) $payload );
}

/**
 * Get the cache TTL in seconds.
 *
 * @return int TTL in seconds, minimum 60.
 */
function buttercup_cache_ttl() {
	return max( 60, intval( get_option( 'buttercup_cache_ttl', 600 ) ) );
}

/**
 * Retrieve a value from the object cache or transient cache.
 *
 * @param string $key Cache key.
 * @return mixed|null Cached value or null if not found.
 */
function buttercup_cache_get( $key ) {
	$cached = wp_cache_get( $key, 'buttercup' );
	if ( false !== $cached ) {
		return $cached;
	}

	$ttl    = buttercup_cache_ttl();
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		wp_cache_set( $key, $cached, 'buttercup', $ttl );
		return $cached;
	}

	return null;
}

/**
 * Store a value in both the object cache and transient cache.
 *
 * @param string   $key   Cache key.
 * @param mixed    $value Value to cache.
 * @param int|null $ttl   Optional TTL in seconds.
 * @return void
 */
function buttercup_cache_set( $key, $value, $ttl = null ) {
	if ( null === $ttl ) {
		$ttl = buttercup_cache_ttl();
	}
	wp_cache_set( $key, $value, 'buttercup', $ttl );
	set_transient( $key, $value, $ttl );
}

/**
 * Increment the cache version to invalidate all cached views.
 *
 * @return void
 */
function buttercup_bump_cache_version() {
	$next_version = max( 2, buttercup_cache_version() + 1 );
	update_option( 'buttercup_cache_version', $next_version, false );
}

/**
 * Invalidate cached views when a post is saved or deleted.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function buttercup_invalidate_cached_views( $post_id = 0 ) {
	if ( $post_id > 0 && ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) ) {
		return;
	}
	buttercup_bump_cache_version();
}

/**
 * Invalidate cached views when post_tag terms are changed.
 *
 * @param int    $object_id  Object ID.
 * @param array  $terms      Array of term IDs.
 * @param array  $tt_ids     Array of term taxonomy IDs.
 * @param string $taxonomy   Taxonomy slug.
 * @param bool   $append     Whether terms were appended.
 * @param array  $old_tt_ids Previous term taxonomy IDs.
 * @return void
 */
function buttercup_invalidate_cached_views_for_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
	if ( 'post_tag' !== $taxonomy ) {
		return;
	}
	buttercup_bump_cache_version();
}
