<?php
/**
 * Event custom post type registration, meta fields, and admin columns.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the buttercup_event custom post type.
 */
function buttercup_register_event_post_type() {
	$labels = array(
		'name'               => __( 'Events', 'buttercup' ),
		'singular_name'      => __( 'Event', 'buttercup' ),
		'add_new'            => __( 'Add New Event', 'buttercup' ),
		'add_new_item'       => __( 'Add New Event', 'buttercup' ),
		'edit_item'          => __( 'Edit Event', 'buttercup' ),
		'new_item'           => __( 'New Event', 'buttercup' ),
		'view_item'          => __( 'View Event', 'buttercup' ),
		'search_items'       => __( 'Search Events', 'buttercup' ),
		'not_found'          => __( 'No events found.', 'buttercup' ),
		'not_found_in_trash' => __( 'No events found in Trash.', 'buttercup' ),
		'all_items'          => __( 'All Events', 'buttercup' ),
		'archives'           => __( 'Event Archives', 'buttercup' ),
		'menu_name'          => __( 'Events', 'buttercup' ),
	);

	$slug = get_option( 'buttercup_events_slug', 'events' );
	if ( ! $slug ) {
		$slug = 'events';
	}

	register_post_type(
		'buttercup_event',
		array(
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'taxonomies'   => array( 'post_tag' ),
			'menu_icon'    => 'dashicons-calendar-alt',
			'rewrite'      => array(
				'slug'       => $slug,
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'buttercup_register_event_post_type' );

/**
 * Register event meta fields for the buttercup_event post type.
 */
function buttercup_register_event_meta() {
	$meta_fields = array(
		'_buttercup_event_start',
		'_buttercup_event_end',
		'_buttercup_event_start_allday',
		'_buttercup_event_end_allday',
		'_buttercup_event_location',
		'_buttercup_event_facebook_id',
		'_buttercup_event_url',
		'_buttercup_event_url_label',
		'_buttercup_event_url_label_custom',
		'_buttercup_event_uid',
		'_buttercup_event_page_mode',
		'_buttercup_event_custom_slug',
		'_buttercup_event_linked_page',
	);

	foreach ( $meta_fields as $key ) {
		register_post_meta(
			'buttercup_event',
			$key,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'default'       => '',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'buttercup_register_event_meta' );

/* ── Admin columns ── */

/**
 * Add custom columns to the event admin list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function buttercup_event_admin_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['event_date']     = __( 'Event Date', 'buttercup' );
			$new['event_location'] = __( 'Location', 'buttercup' );
			$new['event_source']   = __( 'Source', 'buttercup' );
		}
	}
	// Remove the publish date column — event date is more useful.
	unset( $new['date'] );
	return $new;
}
add_filter( 'manage_buttercup_event_posts_columns', 'buttercup_event_admin_columns' );

/**
 * Render custom column content for event admin list rows.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function buttercup_event_admin_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'event_date':
			$start        = get_post_meta( $post_id, '_buttercup_event_start', true );
			$end          = get_post_meta( $post_id, '_buttercup_event_end', true );
			$start_allday = (bool) get_post_meta( $post_id, '_buttercup_event_start_allday', true );
			$end_allday   = (bool) get_post_meta( $post_id, '_buttercup_event_end_allday', true );
			if ( $start ) {
				echo esc_html( buttercup_format_event_date_range( $start, $end, $start_allday, $end_allday ) );
				$now = current_time( 'Y-m-d H:i:s' );
				if ( $start <= $now && ( ! $end || $end >= $now ) ) {
					echo ' <span class="dashicons dashicons-yes-alt" title="' . esc_attr__( 'Happening now', 'buttercup' ) . '" style="color:#00a32a;vertical-align:middle;"></span>';
				} elseif ( $start > $now ) {
					echo ' <span style="color:#2271b1;font-size:12px;">(' . esc_html__( 'upcoming', 'buttercup' ) . ')</span>';
				} else {
					echo ' <span style="color:#757575;font-size:12px;">(' . esc_html__( 'past', 'buttercup' ) . ')</span>';
				}
			} else {
				echo '<span style="color:#757575;">&mdash;</span>';
			}
			break;
		case 'event_location':
			$location = get_post_meta( $post_id, '_buttercup_event_location', true );
			echo $location ? esc_html( $location ) : '<span style="color:#757575;">&mdash;</span>';
			break;
		case 'event_source':
			$fb_id = get_post_meta( $post_id, '_buttercup_event_facebook_id', true );
			echo $fb_id
				? '<span class="dashicons dashicons-facebook" title="' . esc_attr__( 'Synced from Facebook', 'buttercup' ) . '" style="color:#1877f2;"></span>'
				: '<span style="color:#757575;font-size:12px;">' . esc_html__( 'Manual', 'buttercup' ) . '</span>';
			break;
	}
}
add_action( 'manage_buttercup_event_posts_custom_column', 'buttercup_event_admin_column_content', 10, 2 );

/**
 * Define which custom columns are sortable.
 *
 * @param array $columns Existing sortable columns.
 * @return array Modified sortable columns.
 */
function buttercup_event_admin_sortable_columns( $columns ) {
	$columns['event_date']     = 'event_date';
	$columns['event_location'] = 'event_location';
	return $columns;
}
add_filter( 'manage_edit-buttercup_event_sortable_columns', 'buttercup_event_admin_sortable_columns' );

/* ── Default admin sort by event start date + sortable column handling ── */

/**
 * Apply default sorting and status filtering to event admin queries.
 *
 * @param WP_Query $query The current query.
 */
function buttercup_event_admin_default_sort( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'buttercup_event' !== $screen->post_type ) {
		return;
	}

	$orderby = $query->get( 'orderby' );

	if ( 'event_date' === $orderby || '' === $orderby ) {
		$query->set( 'meta_key', '_buttercup_event_start' );
		$query->set( 'orderby', 'meta_value' );
		if ( ! $query->get( 'order' ) ) {
			$query->set( 'order', 'DESC' );
		}
	} elseif ( 'event_location' === $orderby ) {
		$query->set( 'meta_key', '_buttercup_event_location' );
		$query->set( 'orderby', 'meta_value' );
	}

	// Handle the event_status filter.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$event_status = isset( $_GET['event_status'] ) ? sanitize_key( $_GET['event_status'] ) : '';
	if ( 'upcoming' === $event_status || 'past' === $event_status ) {
		$now                   = current_time( 'Y-m-d H:i:s' );
		$existing_meta_query   = $query->get( 'meta_query' ) ? $query->get( 'meta_query' ) : array();
		$existing_meta_query[] = array(
			'key'     => '_buttercup_event_start',
			'value'   => $now,
			'compare' => 'upcoming' === $event_status ? '>=' : '<',
			'type'    => 'DATETIME',
		);
		$query->set( 'meta_query', $existing_meta_query );
	}
}
add_action( 'pre_get_posts', 'buttercup_event_admin_default_sort' );

/* ── Admin list filter (upcoming / past) ── */

/**
 * Render the event status filter dropdown on the admin list screen.
 */
function buttercup_event_admin_filter_dropdown() {
	$screen = get_current_screen();
	if ( ! $screen || 'buttercup_event' !== $screen->post_type ) {
		return;
	}

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current = isset( $_GET['event_status'] ) ? sanitize_key( $_GET['event_status'] ) : '';
	?>
	<select name="event_status">
		<option value=""><?php esc_html_e( 'All event dates', 'buttercup' ); ?></option>
		<option value="upcoming" <?php selected( $current, 'upcoming' ); ?>><?php esc_html_e( 'Upcoming', 'buttercup' ); ?></option>
		<option value="past" <?php selected( $current, 'past' ); ?>><?php esc_html_e( 'Past', 'buttercup' ); ?></option>
	</select>
	<?php
}
add_action( 'restrict_manage_posts', 'buttercup_event_admin_filter_dropdown' );

/* ── Schema.org Event JSON-LD structured data ── */

/**
 * Output Schema.org Event JSON-LD structured data on single event pages.
 */
function buttercup_event_schema_jsonld() {
	if ( ! is_singular( 'buttercup_event' ) ) {
		return;
	}

	$post_id = get_the_ID();
	$meta    = buttercup_get_event_meta( $post_id );

	if ( ! $meta['start'] ) {
		return;
	}

	// Build ISO 8601 dates with site timezone offset for structured data.
	$start_ts  = buttercup_event_timestamp( $meta['start'] );
	$start_iso = $meta['start_allday']
		? wp_date( 'Y-m-d', $start_ts )
		: wp_date( 'c', $start_ts );

	$schema = array(
		'@context'  => 'https://schema.org',
		'@type'     => 'Event',
		'name'      => get_the_title( $post_id ),
		'startDate' => $start_iso,
	);

	if ( $meta['end'] ) {
		$end_ts            = buttercup_event_timestamp( $meta['end'] );
		$end_iso           = $meta['end_allday']
			? wp_date( 'Y-m-d', $end_ts )
			: wp_date( 'c', $end_ts );
		$schema['endDate'] = $end_iso;
	}

	if ( $meta['location'] ) {
		$schema['location'] = array(
			'@type' => 'Place',
			'name'  => $meta['location'],
		);
	}

	$description = get_the_excerpt( $post_id );
	if ( $description ) {
		$schema['description'] = wp_strip_all_tags( $description );
	}

	if ( has_post_thumbnail( $post_id ) ) {
		$schema['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
	}

	$event_url = $meta['url'] ?? '';
	if ( $event_url ) {
		$schema['url'] = $event_url;
	} else {
		$schema['url'] = get_permalink( $post_id );
	}

	$schema['eventStatus'] = 'https://schema.org/EventScheduled';

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'buttercup_event_schema_jsonld' );
