<?php
/**
 * Event template filters, rewrite rules, and date formatting.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Page mode helpers ── */

/**
 * Get the page mode for an event.
 *
 * @param int $post_id Event post ID.
 * @return string 'template', 'editor', or 'standalone'.
 */
function buttercup_get_event_page_mode( $post_id ) {
	$mode = get_post_meta( $post_id, '_buttercup_event_page_mode', true );
	if ( in_array( $mode, array( 'editor', 'standalone' ), true ) ) {
		return $mode;
	}
	return 'template';
}

/* ── Single template filter ── */

/**
 * Filter the single template for buttercup_event posts.
 * Only applies the TEC-style template when page mode is 'template'.
 *
 * @param string $template Path to the template file.
 * @return string Filtered template path.
 */
function buttercup_event_single_template( $template ) {
	if ( ! is_singular( 'buttercup_event' ) ) {
		return $template;
	}

	$mode = buttercup_get_event_page_mode( get_the_ID() );

	// 'editor' and 'standalone' modes use the theme's default template
	// so the block editor content renders with full control.
	if ( 'template' !== $mode ) {
		return $template;
	}

	// If the theme provides a template, defer to it.
	$theme_template = locate_template( 'single-buttercup_event.php' );
	if ( $theme_template ) {
		return $theme_template;
	}

	$plugin_template = BUTTERCUP_PLUGIN_DIR . '/templates/single-buttercup_event.php';
	if ( file_exists( $plugin_template ) ) {
		return $plugin_template;
	}

	return $template;
}
add_filter( 'single_template', 'buttercup_event_single_template' );

/* ── Archive template filter ── */

/**
 * Filter the archive template for buttercup_event posts.
 *
 * @param string $template Path to the template file.
 * @return string Filtered template path.
 */
function buttercup_event_archive_template( $template ) {
	if ( ! is_post_type_archive( 'buttercup_event' ) ) {
		return $template;
	}

	$theme_template = locate_template( 'archive-buttercup_event.php' );
	if ( $theme_template ) {
		return $theme_template;
	}

	$plugin_template = BUTTERCUP_PLUGIN_DIR . '/templates/archive-buttercup_event.php';
	if ( file_exists( $plugin_template ) ) {
		return $plugin_template;
	}

	return $template;
}
add_filter( 'archive_template', 'buttercup_event_archive_template' );

/* ── Standalone event rewrite rules ── */

/**
 * Get the map of custom slugs → event post IDs for standalone events.
 * Stored as an option for performance (no DB query on every page load).
 */
function buttercup_get_standalone_event_slugs() {
	return (array) get_option( '_buttercup_standalone_slugs', array() );
}

/**
 * Register rewrite rules for standalone events (root-level URLs).
 */
function buttercup_register_standalone_rewrites() {
	$slugs = buttercup_get_standalone_event_slugs();

	foreach ( $slugs as $slug => $post_id ) {
		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/?$',
			'index.php?post_type=buttercup_event&p=' . intval( $post_id ),
			'top'
		);
	}
}
add_action( 'init', 'buttercup_register_standalone_rewrites', 20 );

/**
 * Filter the permalink for standalone events to use root-level URLs.
 *
 * @param string  $post_link The post's permalink.
 * @param WP_Post $post      The post object.
 * @return string Filtered permalink.
 */
function buttercup_event_permalink( $post_link, $post ) {
	if ( 'buttercup_event' !== $post->post_type ) {
		return $post_link;
	}

	$mode = get_post_meta( $post->ID, '_buttercup_event_page_mode', true );
	if ( 'standalone' !== $mode ) {
		return $post_link;
	}

	// If linked to an existing page, use that page's permalink.
	$linked_page = absint( get_post_meta( $post->ID, '_buttercup_event_linked_page', true ) );
	if ( $linked_page && get_post_status( $linked_page ) === 'publish' ) {
		return get_permalink( $linked_page );
	}

	// Otherwise fall back to custom root slug.
	$custom_slug = get_post_meta( $post->ID, '_buttercup_event_custom_slug', true );
	if ( ! $custom_slug ) {
		return $post_link;
	}

	return home_url( '/' . $custom_slug . '/' );
}
add_filter( 'post_type_link', 'buttercup_event_permalink', 10, 2 );

/**
 * Auto-publish events that link to an existing page.
 * The real content lives on the linked page, so there's nothing to draft.
 *
 * @param int $post_id The event post ID.
 */
function buttercup_auto_publish_linked_events( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'buttercup_event' ) {
		return;
	}

	$linked = absint( get_post_meta( $post_id, '_buttercup_event_linked_page', true ) );
	if ( ! $linked ) {
		return;
	}

	$post = get_post( $post_id );
	if ( $post && 'draft' === $post->post_status ) {
		// Unhook to avoid infinite loop, publish, re-hook.
		remove_action( 'save_post_buttercup_event', 'buttercup_auto_publish_linked_events' );
		wp_publish_post( $post_id );
		add_action( 'save_post_buttercup_event', 'buttercup_auto_publish_linked_events' );
	}
}
add_action( 'save_post_buttercup_event', 'buttercup_auto_publish_linked_events' );

/**
 * Rebuild the standalone slugs option when an event is saved.
 *
 * @param int $post_id The event post ID.
 */
function buttercup_update_standalone_slugs( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'buttercup_event' ) {
		return;
	}

	// Rebuild the full map (not just this post, in case slug was removed).
	$query = new WP_Query(
		array(
			'post_type'      => 'buttercup_event',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'   => '_buttercup_event_page_mode',
					'value' => 'standalone',
				),
			),
			'fields'         => 'ids',
		)
	);

	$slugs = array();
	foreach ( $query->posts as $id ) {
		$slug = get_post_meta( $id, '_buttercup_event_custom_slug', true );
		if ( $slug ) {
			$slugs[ sanitize_title( $slug ) ] = $id;
		}
	}

	$old = get_option( '_buttercup_standalone_slugs', array() );
	if ( $slugs !== $old ) {
		update_option( '_buttercup_standalone_slugs', $slugs, false );
		buttercup_schedule_rewrite_flush();
	}
}
add_action( 'save_post_buttercup_event', 'buttercup_update_standalone_slugs' );
add_action( 'trashed_post', 'buttercup_update_standalone_slugs' );
add_action( 'untrashed_post', 'buttercup_update_standalone_slugs' );

/* ── Frontend styles ── */

/**
 * Enqueue frontend styles for single and archive event pages.
 */
function buttercup_event_frontend_styles() {
	if ( ! is_singular( 'buttercup_event' ) && ! is_post_type_archive( 'buttercup_event' ) ) {
		return;
	}

	wp_enqueue_style(
		'buttercup-single-event',
		plugins_url( '/assets/single-event.css', __DIR__ ),
		array(),
		filemtime( BUTTERCUP_PLUGIN_DIR . '/assets/single-event.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'buttercup_event_frontend_styles' );

/* ── Date formatting ── */

/**
 * Parse a stored event datetime string into a Unix timestamp, treating the
 * value as being in the site's configured timezone (not UTC).
 *
 * WordPress sets PHP's default timezone to UTC, so plain strtotime() would
 * interpret stored local times as UTC and shift them by the site's offset.
 * This helper avoids that by explicitly specifying wp_timezone().
 *
 * @param string $datetime_str MySQL-style datetime "YYYY-MM-DD HH:MM:SS".
 * @return int|false Unix timestamp, or false on failure.
 */
function buttercup_event_timestamp( $datetime_str ) {
	if ( ! $datetime_str ) {
		return false;
	}
	$tz = wp_timezone();
	$dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime_str, $tz );
	if ( $dt ) {
		return $dt->getTimestamp();
	}
	// Fallback for any non-standard format.
	return strtotime( $datetime_str );
}

/**
 * Format a date range for display.
 *
 * @param string $start        Start datetime string.
 * @param string $end          End datetime string (optional).
 * @param bool   $start_allday Whether the start has no specific time.
 * @param bool   $end_allday   Whether the end has no specific time.
 * @return string Formatted date range.
 */
function buttercup_format_event_date_range( $start, $end = '', $start_allday = false, $end_allday = false ) {
	if ( ! $start ) {
		return '';
	}

	$start_ts = buttercup_event_timestamp( $start );
	if ( ! $start_ts ) {
		return '';
	}

	$date_format = get_option( 'date_format', 'F j, Y' );
	$time_format = get_option( 'time_format', 'g:i a' );

	$start_date = wp_date( $date_format, $start_ts );

	if ( $start_allday ) {
		if ( ! $end ) {
			return $start_date;
		}
		$end_ts = buttercup_event_timestamp( $end );
		if ( ! $end_ts ) {
			return $start_date;
		}
		$end_date = wp_date( $date_format, $end_ts );
		if ( $start_date === $end_date ) {
			return $start_date;
		}
		return $start_date . ' - ' . $end_date;
	}

	$start_time = wp_date( $time_format, $start_ts );

	if ( ! $end ) {
		return $start_date . ' @ ' . $start_time;
	}

	$end_ts = buttercup_event_timestamp( $end );
	if ( ! $end_ts ) {
		return $start_date . ' @ ' . $start_time;
	}

	$end_date = wp_date( $date_format, $end_ts );

	if ( $end_allday ) {
		if ( $start_date === $end_date ) {
			return $start_date . ' @ ' . $start_time;
		}
		return $start_date . ' @ ' . $start_time . ' - ' . $end_date;
	}

	$end_time = wp_date( $time_format, $end_ts );

	// Same day: "April 3 @ 6:00 pm - 8:00 pm".
	if ( $start_date === $end_date ) {
		return $start_date . ' @ ' . $start_time . ' - ' . $end_time;
	}

	// Different days: "April 3 @ 6:00 pm - April 4 @ 10:00 pm".
	return $start_date . ' @ ' . $start_time . ' - ' . $end_date . ' @ ' . $end_time;
}

/* ── Adjacent event navigation ── */

/**
 * Get the adjacent event by event start date (not post_date).
 *
 * @param int    $post_id   Current event post ID.
 * @param string $direction 'previous' or 'next'.
 * @return int|null Adjacent event post ID, or null.
 */
function buttercup_get_adjacent_event( $post_id, $direction = 'next' ) {
	$current_start = get_post_meta( $post_id, '_buttercup_event_start', true );
	if ( ! $current_start ) {
		return null;
	}

	$is_next = ( 'next' === $direction );

	$query = new WP_Query(
		array(
			'post_type'      => 'buttercup_event',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'post__not_in'   => array( $post_id ),
			'meta_key'       => '_buttercup_event_start',
			'orderby'        => 'meta_value',
			'order'          => $is_next ? 'ASC' : 'DESC',
			'meta_query'     => array(
				array(
					'key'     => '_buttercup_event_start',
					'value'   => $current_start,
					'compare' => $is_next ? '>=' : '<=',
					'type'    => 'DATETIME',
				),
			),
			'fields'         => 'ids',
		)
	);

	if ( $query->have_posts() ) {
		return $query->posts[0];
	}

	return null;
}

/* ── Event meta helper ── */

/**
 * Get all event meta for a given post.
 *
 * @param int $post_id The event post ID.
 * @return array Associative array of event meta.
 */
function buttercup_get_event_meta( $post_id ) {
	return array(
		'start'        => get_post_meta( $post_id, '_buttercup_event_start', true ),
		'end'          => get_post_meta( $post_id, '_buttercup_event_end', true ),
		'start_allday' => (bool) get_post_meta( $post_id, '_buttercup_event_start_allday', true ),
		'end_allday'   => (bool) get_post_meta( $post_id, '_buttercup_event_end_allday', true ),
		'location'     => get_post_meta( $post_id, '_buttercup_event_location', true ),
		'facebook_id'  => get_post_meta( $post_id, '_buttercup_event_facebook_id', true ),
		'url'          => get_post_meta( $post_id, '_buttercup_event_url', true ),
		'url_label'    => buttercup_get_event_url_label( $post_id ),
	);
}

/**
 * Get the resolved button label for an event's URL.
 *
 * @param int $post_id The event post ID.
 * @return string Human-readable button label.
 */
function buttercup_get_event_url_label( $post_id ) {
	$key = get_post_meta( $post_id, '_buttercup_event_url_label', true );

	$labels = array(
		'more_info'   => __( 'More Info', 'buttercup' ),
		'get_tickets' => __( 'Get Tickets', 'buttercup' ),
		'register'    => __( 'Register', 'buttercup' ),
	);

	if ( 'custom' === $key ) {
		$custom = get_post_meta( $post_id, '_buttercup_event_url_label_custom', true );
		return $custom ? $custom : $labels['more_info'];
	}

	return $labels[ $key ] ?? $labels['more_info'];
}
