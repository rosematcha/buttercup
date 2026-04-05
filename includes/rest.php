<?php
/**
 * REST API endpoint registration and callbacks.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate a square-cropped version of an image attachment.
 *
 * @param int $attachment_id Attachment post ID.
 * @param int $size          Target square dimension in pixels.
 * @return array|WP_Error Array with url, width, height on success.
 */
function buttercup_generate_square_image( $attachment_id, $size = 600 ) {
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		return new WP_Error( 'buttercup_missing_file', __( 'Image file not found.', 'buttercup' ) );
	}

	$type = wp_check_filetype( $file );
	$ext  = strtolower( $type['ext'] ?? '' );
	$mime = $type['type'] ?? '';
	if ( ! $ext || ! $mime ) {
		return new WP_Error( 'buttercup_invalid_type', __( 'Unsupported image type.', 'buttercup' ) );
	}

	$info   = pathinfo( $file );
	$dir    = $info['dirname'];
	$name   = $info['filename'];
	$target = $dir . '/' . $name . '-buttercup-crop-' . intval( $size ) . 'x' . intval( $size ) . '.' . $ext;

	if ( file_exists( $target ) ) {
		$upload_dir = wp_get_upload_dir();
		$relative   = str_replace( $upload_dir['basedir'], '', $target );
		$url        = trailingslashit( $upload_dir['baseurl'] ) . ltrim( $relative, '/' );
		return array(
			'url'    => $url,
			'width'  => $size,
			'height' => $size,
		);
	}

	$image_info = getimagesize( $file );
	if ( ! $image_info ) {
		return new WP_Error( 'buttercup_invalid_image', __( 'Invalid image data.', 'buttercup' ) );
	}

	$src_width  = intval( $image_info[0] );
	$src_height = intval( $image_info[1] );
	if ( ! $src_width || ! $src_height ) {
		return new WP_Error( 'buttercup_invalid_image', __( 'Invalid image size.', 'buttercup' ) );
	}

	$crop_size = min( $src_width, $src_height );
	$src_x     = intval( round( ( $src_width - $crop_size ) / 2 ) );
	$src_y     = intval( round( ( $src_height - $crop_size ) / 2 ) );

	switch ( $ext ) {
		case 'jpg':
		case 'jpeg':
			$src = imagecreatefromjpeg( $file );
			break;
		case 'png':
			$src = imagecreatefrompng( $file );
			break;
		case 'gif':
			$src = imagecreatefromgif( $file );
			break;
		case 'webp':
			if ( ! function_exists( 'imagecreatefromwebp' ) ) {
				return new WP_Error( 'buttercup_no_webp', __( 'WebP is not supported on this server.', 'buttercup' ) );
			}
			$src = imagecreatefromwebp( $file );
			break;
		default:
			return new WP_Error( 'buttercup_invalid_type', __( 'Unsupported image type.', 'buttercup' ) );
	}

	if ( ! $src ) {
		return new WP_Error( 'buttercup_invalid_image', __( 'Unable to load image.', 'buttercup' ) );
	}

	$dst = imagecreatetruecolor( $size, $size );
	if ( ! $dst ) {
		return new WP_Error( 'buttercup_invalid_image', __( 'Unable to create image canvas.', 'buttercup' ) );
	}

	if ( 'png' === $ext || 'gif' === $ext || 'webp' === $ext ) {
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		$transparent = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
		imagefilledrectangle( $dst, 0, 0, $size, $size, $transparent );
	}

	imagecopyresampled( $dst, $src, 0, 0, $src_x, $src_y, $size, $size, $crop_size, $crop_size );

	$saved = false;
	switch ( $ext ) {
		case 'jpg':
		case 'jpeg':
			$saved = imagejpeg( $dst, $target, 90 );
			break;
		case 'png':
			$saved = imagepng( $dst, $target, 6 );
			break;
		case 'gif':
			$saved = imagegif( $dst, $target );
			break;
		case 'webp':
			$saved = imagewebp( $dst, $target, 85 );
			break;
	}

	if ( ! $saved ) {
		return new WP_Error( 'buttercup_save_failed', __( 'Unable to save image.', 'buttercup' ) );
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( is_array( $meta ) ) {
		$meta['sizes']['buttercup-square-600'] = array(
			'file'      => basename( $target ),
			'width'     => $size,
			'height'    => $size,
			'mime-type' => $mime,
		);
		wp_update_attachment_metadata( $attachment_id, $meta );
	}

	$upload_dir = wp_get_upload_dir();
	$relative   = str_replace( $upload_dir['basedir'], '', $target );
	$url        = trailingslashit( $upload_dir['baseurl'] ) . ltrim( $relative, '/' );

	return array(
		'url'    => $url,
		'width'  => $size,
		'height' => $size,
	);
}

/**
 * REST callback to generate a square-cropped image.
 *
 * @param WP_REST_Request $request REST request with 'id' parameter.
 * @return WP_REST_Response|WP_Error Response with image data or error.
 */
function buttercup_rest_square_image( $request ) {
	$id = intval( $request['id'] );
	if ( ! $id ) {
		return new WP_Error( 'buttercup_invalid_id', __( 'Invalid image ID.', 'buttercup' ), array( 'status' => 400 ) );
	}
	if ( ! wp_attachment_is_image( $id ) ) {
		return new WP_Error( 'buttercup_invalid_id', __( 'Attachment must be an image.', 'buttercup' ), array( 'status' => 400 ) );
	}

	$result = buttercup_generate_square_image( $id, 600 );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response( $result );
}

/**
 * REST callback for the homepage feed status endpoint.
 *
 * @param WP_REST_Request $request REST request object.
 * @return WP_REST_Response Response with feed status data.
 */
function buttercup_rest_homepage_feed_status( $request ) {
	$mast_tag_slug = isset( $request['mastTagSlug'] ) ? $request['mastTagSlug'] : 'mast';
	$home_tag_slug = isset( $request['homeTagSlug'] ) ? $request['homeTagSlug'] : 'home';

	$feed = buttercup_homepage_feed_collect( $mast_tag_slug, $home_tag_slug );

	$response = array(
		'mastTagSlug'  => $feed['mast_tag_slug'],
		'homeTagSlug'  => $feed['home_tag_slug'],
		'mastCount'    => intval( $feed['mast_count'] ),
		'homeCount'    => intval( $feed['home_count'] ),
		'mastOverflow' => ! empty( $feed['mast_overflow'] ),
		'homeOverflow' => ! empty( $feed['home_overflow'] ),
		'mastSelected' => buttercup_homepage_feed_summarize_posts( $feed['mast_selected'] ? array( $feed['mast_selected'] ) : array() ),
		'homeSelected' => buttercup_homepage_feed_summarize_posts( $feed['home_selected'] ),
		'dualTagged'   => buttercup_homepage_feed_summarize_posts( $feed['dual_posts'] ),
	);

	return rest_ensure_response( $response );
}

/**
 * Register all Buttercup REST API routes.
 *
 * @return void
 */
function buttercup_register_rest_routes() {
	register_rest_route(
		'buttercup/v1',
		'/square-image',
		array(
			'methods'             => 'POST',
			'callback'            => 'buttercup_rest_square_image',
			'permission_callback' => function () {
				return current_user_can( 'upload_files' );
			},
			'args'                => array(
				'id' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
		)
	);

	register_rest_route(
		'buttercup/v1',
		'/homepage-feed-status',
		array(
			'methods'             => 'GET',
			'callback'            => 'buttercup_rest_homepage_feed_status',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'mastTagSlug' => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_title',
				),
				'homeTagSlug' => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_title',
				),
			),
		)
	);

	register_rest_route(
		'buttercup/v1',
		'/tag-showcase-status',
		array(
			'methods'             => 'GET',
			'callback'            => 'buttercup_rest_tag_showcase_status',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'tagSlugs'           => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => function ( $value ) {
						return sanitize_text_field( $value );
					},
				),
				'tagMatch'           => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => function ( $value ) {
						return sanitize_key( $value );
					},
				),
				'postTypes'          => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => function ( $value ) {
						return sanitize_text_field( $value );
					},
				),
				'excludeCurrentPost' => array(
					'type'              => 'boolean',
					'required'          => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
				'offset'             => array(
					'type'              => 'integer',
					'required'          => false,
					'sanitize_callback' => 'absint',
				),
				'maxItems'           => array(
					'type'              => 'integer',
					'required'          => false,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
	register_rest_route(
		'buttercup/v1',
		'/events-status',
		array(
			'methods'             => 'GET',
			'callback'            => 'buttercup_rest_events_status',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'displayMode'  => array(
					'type'              => 'string',
					'required'          => false,
					'default'           => 'upcoming',
					'sanitize_callback' => function ( $value ) {
						return in_array( $value, array( 'upcoming', 'past' ), true ) ? $value : 'upcoming';
					},
				),
				'eventsToShow' => array(
					'type'              => 'integer',
					'required'          => false,
					'default'           => 6,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	register_rest_route(
		'buttercup/v1',
		'/events-sync',
		array(
			'methods'             => 'POST',
			'callback'            => 'buttercup_rest_events_sync',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'buttercup_register_rest_routes' );

/**
 * REST callback for the events status endpoint.
 *
 * @param WP_REST_Request $request REST request object.
 * @return WP_REST_Response Response with events count and list.
 */
function buttercup_rest_events_status( $request ) {
	$display_mode   = $request['displayMode'] ?? 'upcoming';
	$default_count  = intval( get_option( 'buttercup_events_per_page', 6 ) );
	$events_to_show = max( 1, min( 50, intval( $request['eventsToShow'] ?? $default_count ) ) );
	$now            = current_time( 'Y-m-d H:i:s' );

	$query_args = array(
		'post_type'      => 'buttercup_event',
		'posts_per_page' => $events_to_show,
		'post_status'    => 'publish',
		'meta_key'       => '_buttercup_event_start',
		'orderby'        => 'meta_value',
		'order'          => 'past' === $display_mode ? 'DESC' : 'ASC',
		'meta_query'     => array(
			array(
				'key'     => '_buttercup_event_start',
				'value'   => $now,
				'compare' => 'past' === $display_mode ? '<' : '>=',
				'type'    => 'DATETIME',
			),
		),
	);

	$query  = new WP_Query( $query_args );
	$events = array();

	while ( $query->have_posts() ) {
		$query->the_post();
		$post_id      = get_the_ID();
		$start        = get_post_meta( $post_id, '_buttercup_event_start', true );
		$end          = get_post_meta( $post_id, '_buttercup_event_end', true );
		$start_allday = (bool) get_post_meta( $post_id, '_buttercup_event_start_allday', true );
		$end_allday   = (bool) get_post_meta( $post_id, '_buttercup_event_end_allday', true );

		$events[] = array(
			'id'       => $post_id,
			'title'    => get_the_title(),
			'start'    => $start ? buttercup_format_event_date_range( $start, $end, $start_allday, $end_allday ) : '',
			'location' => get_post_meta( $post_id, '_buttercup_event_location', true ),
		);
	}
	wp_reset_postdata();

	return rest_ensure_response(
		array(
			'count'  => $query->found_posts,
			'events' => $events,
		)
	);
}

/**
 * REST callback to trigger a manual Facebook events sync.
 *
 * @return WP_REST_Response Response with sync results.
 */
function buttercup_rest_events_sync() {
	$result = buttercup_facebook_sync_run();
	return rest_ensure_response( $result );
}
