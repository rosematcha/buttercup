<?php
/**
 * Facebook Graph API event sync via WP-Cron.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the custom cron schedule for Facebook sync.
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified schedules with buttercup_fb_sync added.
 */
function buttercup_fb_sync_cron_schedules( $schedules ) {
	$interval = intval( get_option( 'buttercup_fb_sync_interval', 21600 ) );
	if ( $interval < 3600 ) {
		$interval = 21600;
	}

	$schedules['buttercup_fb_sync'] = array(
		'interval' => $interval,
		'display'  => __( 'Buttercup FB Sync Interval', 'buttercup' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'buttercup_fb_sync_cron_schedules' );

/**
 * Schedule the Facebook sync cron event on activation.
 *
 * @return void
 */
function buttercup_fb_sync_activate() {
	if ( ! wp_next_scheduled( 'buttercup_facebook_sync_events' ) ) {
		wp_schedule_event( time(), 'buttercup_fb_sync', 'buttercup_facebook_sync_events' );
	}
}

/**
 * Clear the Facebook sync cron event on deactivation.
 *
 * @return void
 */
function buttercup_fb_sync_deactivate() {
	wp_clear_scheduled_hook( 'buttercup_facebook_sync_events' );
}

/**
 * Reschedule the cron event when the sync interval option changes.
 *
 * @param mixed $old_value Previous option value.
 * @param mixed $new_value New option value.
 * @return void
 */
function buttercup_fb_sync_reschedule( $old_value, $new_value ) {
	if ( intval( $old_value ) === intval( $new_value ) ) {
		return;
	}
	wp_clear_scheduled_hook( 'buttercup_facebook_sync_events' );
	wp_schedule_event( time(), 'buttercup_fb_sync', 'buttercup_facebook_sync_events' );
}
add_action( 'update_option_buttercup_fb_sync_interval', 'buttercup_fb_sync_reschedule', 10, 2 );

/* ── Cron callback ── */

add_action( 'buttercup_facebook_sync_events', 'buttercup_facebook_sync_run' );

/**
 * Main sync function — imports events from a Facebook Page via the Graph API.
 *
 * @return array { synced: int, errors: string[] }
 */
function buttercup_facebook_sync_run() {
	$app_id = get_option( 'buttercup_fb_app_id', '' );
	$token  = get_option( 'buttercup_fb_page_access_token', '' );
	$page   = get_option( 'buttercup_fb_page_id', '' );

	if ( ! $app_id || ! $token || ! $page ) {
		$msg = __( 'Facebook sync skipped: missing App ID, Page Access Token, or Page ID.', 'buttercup' );
		update_option( 'buttercup_fb_sync_last_error', $msg, false );
		return array(
			'synced' => 0,
			'errors' => array( $msg ),
		);
	}

	$url = 'https://graph.facebook.com/v19.0/' . rawurlencode( $page ) . '/events';
	$url = add_query_arg(
		array(
			'fields' => 'id,name,description,start_time,end_time,place,cover',
			'limit'  => 100,
		),
		$url
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$msg = $response->get_error_message();
		update_option( 'buttercup_fb_sync_last_error', $msg, false );
		return array(
			'synced' => 0,
			'errors' => array( $msg ),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $body ) ) {
		$msg = $body['error']['message'] ?? __( 'Facebook API returned an unexpected response.', 'buttercup' );
		update_option( 'buttercup_fb_sync_last_error', $msg, false );
		return array(
			'synced' => 0,
			'errors' => array( $msg ),
		);
	}

	$events = $body['data'] ?? array();
	$synced = 0;
	$errors = array();

	foreach ( $events as $fb_event ) {
		$result = buttercup_fb_sync_single_event( $fb_event );
		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
		} else {
			++$synced;
		}
	}

	update_option( 'buttercup_fb_last_sync', current_time( 'c' ), false );
	if ( empty( $errors ) ) {
		delete_option( 'buttercup_fb_sync_last_error' );
	} else {
		update_option( 'buttercup_fb_sync_last_error', implode( '; ', $errors ), false );
	}

	if ( $synced > 0 ) {
		buttercup_bump_cache_version();
	}

	return array(
		'synced' => $synced,
		'errors' => $errors,
	);
}

/**
 * Import or update a single Facebook event.
 *
 * @param array $fb_event Facebook event data from the Graph API.
 * @return int|WP_Error Post ID on success, WP_Error on failure.
 */
function buttercup_fb_sync_single_event( $fb_event ) {
	$fb_id = $fb_event['id'] ?? '';
	if ( ! $fb_id ) {
		return new WP_Error( 'buttercup_fb_no_id', __( 'Event missing Facebook ID.', 'buttercup' ) );
	}

	$title       = $fb_event['name'] ?? '';
	$description = $fb_event['description'] ?? '';
	$start_time  = $fb_event['start_time'] ?? '';
	$end_time    = $fb_event['end_time'] ?? '';
	$place_name  = $fb_event['place']['name'] ?? '';
	$cover_url   = $fb_event['cover']['source'] ?? '';

	// Convert Facebook ISO times to site timezone in MySQL DATETIME format.
	$start_iso = $start_time ? wp_date( 'Y-m-d H:i:s', strtotime( $start_time ) ) : '';
	$end_iso   = $end_time ? wp_date( 'Y-m-d H:i:s', strtotime( $end_time ) ) : '';

	// Check for existing post by Facebook ID.
	$existing = new WP_Query(
		array(
			'post_type'      => 'buttercup_event',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'   => '_buttercup_event_facebook_id',
					'value' => $fb_id,
				),
			),
			'fields'         => 'ids',
		)
	);

	$post_data = array(
		'post_type'    => 'buttercup_event',
		'post_title'   => sanitize_text_field( $title ),
		'post_content' => wp_kses_post( $description ),
		'post_status'  => 'publish',
	);

	if ( $existing->have_posts() ) {
		// Update existing.
		$post_id         = $existing->posts[0];
		$post_data['ID'] = $post_id;
		$result          = wp_update_post( $post_data, true );
	} else {
		// Create new.
		$result = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$post_id = is_int( $result ) ? $result : $post_data['ID'];

	// Update meta.
	update_post_meta( $post_id, '_buttercup_event_start', $start_iso );
	update_post_meta( $post_id, '_buttercup_event_end', $end_iso );
	update_post_meta( $post_id, '_buttercup_event_location', sanitize_text_field( $place_name ) );
	update_post_meta( $post_id, '_buttercup_event_facebook_id', sanitize_text_field( $fb_id ) );

	// Download cover image if available and not already set.
	if ( $cover_url && ! has_post_thumbnail( $post_id ) ) {
		buttercup_fb_sync_download_cover( $cover_url, $post_id );
	}

	return $post_id;
}

/**
 * Download a Facebook cover image and set it as the post's featured image.
 *
 * @param string $url     Cover image URL.
 * @param int    $post_id The event post ID.
 */
function buttercup_fb_sync_download_cover( $url, $post_id ) {
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$attachment_id = media_sideload_image( $url, $post_id, '', 'id' );
	if ( ! is_wp_error( $attachment_id ) ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}
}
