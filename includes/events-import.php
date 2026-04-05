<?php
/**
 * Events import page and handlers.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Admin submenu ── */

/**
 * Register the Import Events submenu page.
 */
function buttercup_events_import_menu() {
	add_submenu_page(
		'buttercup',
		__( 'Import Events', 'buttercup' ),
		__( 'Import Events', 'buttercup' ),
		'manage_options',
		'buttercup-import-events',
		'buttercup_events_import_page_render'
	);
}
add_action( 'admin_menu', 'buttercup_events_import_menu' );

/* ── Page renderer ── */

/**
 * Render the import events admin page.
 */
function buttercup_events_import_page_render() {
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'ical';
	$results    = null;

	// Handle form submission.
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['buttercup_import_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['buttercup_import_nonce'] ) ), 'buttercup_import_events' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'buttercup' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import events.', 'buttercup' ) );
		}

		$action = sanitize_key( $_POST['import_action'] ?? '' );

		if ( 'ical_file' === $action || 'ical_url' === $action ) {
			$results    = buttercup_handle_ical_import( $action );
			$active_tab = 'ical';
		} elseif ( 'facebook_html' === $action ) {
			$results    = buttercup_handle_facebook_html_import();
			$active_tab = 'facebook';
		}
	}

	$tabs = array(
		'ical'     => __( 'iCal Import', 'buttercup' ),
		'facebook' => __( 'Facebook HTML', 'buttercup' ),
	);

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Import Events', 'buttercup' ) . '</h1>';

	// Tab navigation.
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $tab_key => $tab_label ) {
		$class = $tab_key === $active_tab ? 'nav-tab nav-tab-active' : 'nav-tab';
		$url   = admin_url( 'admin.php?page=buttercup-import-events&tab=' . $tab_key );
		echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $tab_label ) . '</a>';
	}
	echo '</nav>';

	// Show results if any.
	if ( null !== $results ) {
		buttercup_render_import_results( $results );
	}

	// Tab content.
	echo '<div style="margin-top: 20px;">';
	if ( 'ical' === $active_tab ) {
		buttercup_render_ical_import_form();
	} else {
		buttercup_render_facebook_import_form();
	}
	echo '</div>';

	echo '</div>';
}

/* ── Form renderers ── */

/**
 * Render the iCal import form.
 */
function buttercup_render_ical_import_form() {
	?>
	<h2><?php esc_html_e( 'Import from iCal File', 'buttercup' ); ?></h2>
	<p><?php esc_html_e( 'Upload an .ics file to import events.', 'buttercup' ); ?></p>
	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'buttercup_import_events', 'buttercup_import_nonce' ); ?>
		<input type="hidden" name="import_action" value="ical_file" />
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ical_file"><?php esc_html_e( 'iCal File', 'buttercup' ); ?></label></th>
				<td>
					<input type="file" name="ical_file" id="ical_file" accept=".ics,.ical,.ifb,.icalendar" />
					<p class="description"><?php esc_html_e( 'Accepts .ics and .ical files.', 'buttercup' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Import from File', 'buttercup' ) ); ?>
	</form>

	<hr />

	<h2><?php esc_html_e( 'Import from iCal URL', 'buttercup' ); ?></h2>
	<p><?php esc_html_e( 'Paste an iCal feed URL to fetch and import events.', 'buttercup' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'buttercup_import_events', 'buttercup_import_nonce' ); ?>
		<input type="hidden" name="import_action" value="ical_url" />
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ical_url"><?php esc_html_e( 'iCal URL', 'buttercup' ); ?></label></th>
				<td>
					<input type="url" name="ical_url" id="ical_url" class="regular-text" placeholder="https://example.com/events.ics" />
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Import from URL', 'buttercup' ) ); ?>
	</form>
	<?php
}

/**
 * Render the Facebook HTML import form.
 */
function buttercup_render_facebook_import_form() {
	?>
	<h2><?php esc_html_e( 'Import from Facebook Page HTML', 'buttercup' ); ?></h2>
	<div class="notice notice-info inline" style="margin-bottom: 16px;">
		<p><strong><?php esc_html_e( 'How to save your Facebook events page:', 'buttercup' ); ?></strong></p>
		<ol>
			<li><?php esc_html_e( 'Visit your Facebook Page\'s Events tab in your browser.', 'buttercup' ); ?></li>
			<li><?php esc_html_e( 'Click "Past Events" if you want to import past events — scroll down to load all of them.', 'buttercup' ); ?></li>
			<li>
			<?php
				printf(
					/* translators: %s: keyboard shortcut */
					esc_html__( 'Press %s to save the page as an HTML file.', 'buttercup' ),
					'<kbd>Ctrl+S</kbd> / <kbd>Cmd+S</kbd>'
				);
			?>
			</li>
			<li><?php esc_html_e( 'Choose "Webpage, Complete" or "Webpage, HTML Only" as the save format.', 'buttercup' ); ?></li>
			<li><?php esc_html_e( 'Upload the saved .html file below.', 'buttercup' ); ?></li>
		</ol>
	</div>
	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'buttercup_import_events', 'buttercup_import_nonce' ); ?>
		<input type="hidden" name="import_action" value="facebook_html" />
		<table class="form-table">
			<tr>
				<th scope="row"><label for="facebook_html_file"><?php esc_html_e( 'HTML File', 'buttercup' ); ?></label></th>
				<td>
					<input type="file" name="facebook_html_file" id="facebook_html_file" accept=".html,.htm" />
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Import from Facebook HTML', 'buttercup' ) ); ?>
	</form>
	<?php
}

/* ── Import handlers ── */

/**
 * Handle iCal import from file or URL.
 *
 * @param string $action The import action type ('ical_file' or 'ical_url').
 * @return array Results array with imported/skipped/failed counts or error.
 */
function buttercup_handle_ical_import( $action ) {
	$ics_content = '';

	if ( 'ical_file' === $action ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in buttercup_events_import_page_render() before calling this function.
		if ( empty( $_FILES['ical_file']['tmp_name'] ) || ! isset( $_FILES['ical_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['ical_file']['error'] ) {
			return array( 'error' => __( 'No file uploaded or upload error.', 'buttercup' ) );
		}
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- nonce verified in caller; reading local temp file.
		$ics_content = file_get_contents( sanitize_text_field( $_FILES['ical_file']['tmp_name'] ) );
	} elseif ( 'ical_url' === $action ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in buttercup_events_import_page_render() before calling this function.
		$url = esc_url_raw( wp_unslash( $_POST['ical_url'] ?? '' ) );
		if ( ! $url ) {
			return array( 'error' => __( 'Please enter a valid URL.', 'buttercup' ) );
		}
		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			// translators: %d is the HTTP status code returned by the URL.
			return array( 'error' => sprintf( __( 'URL returned HTTP %d.', 'buttercup' ), $code ) );
		}
		$ics_content = wp_remote_retrieve_body( $response );
	}

	if ( ! $ics_content ) {
		return array( 'error' => __( 'File is empty.', 'buttercup' ) );
	}

	$events = buttercup_parse_ical( $ics_content );
	if ( empty( $events ) ) {
		return array( 'error' => __( 'No events found in the iCal file.', 'buttercup' ) );
	}

	return buttercup_import_parsed_events( $events );
}

/**
 * Handle Facebook HTML file import.
 *
 * @return array Results array with imported/skipped/failed counts or error.
 */
function buttercup_handle_facebook_html_import() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in buttercup_events_import_page_render() before calling this function.
	if ( empty( $_FILES['facebook_html_file']['tmp_name'] ) || ! isset( $_FILES['facebook_html_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['facebook_html_file']['error'] ) {
		return array( 'error' => __( 'No file uploaded or upload error.', 'buttercup' ) );
	}

    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- nonce verified in caller; reading local temp file.
	$html = file_get_contents( sanitize_text_field( $_FILES['facebook_html_file']['tmp_name'] ) );
	if ( ! $html ) {
		return array( 'error' => __( 'File is empty.', 'buttercup' ) );
	}

	$parsed = buttercup_parse_facebook_events_html( $html );

	if ( empty( $parsed['events'] ) ) {
		$warnings = $parsed['warnings'] ?? array();
		$msg      = ! empty( $warnings )
			? implode( ' ', $warnings )
			: __( 'No events found in the HTML file.', 'buttercup' );
		return array( 'error' => $msg );
	}

	$results = buttercup_import_parsed_events( $parsed['events'] );
	if ( ! empty( $parsed['warnings'] ) ) {
		$results['warnings'] = array_merge( $results['warnings'] ?? array(), $parsed['warnings'] );
	}

	return $results;
}

/* ── Shared import logic ── */

/**
 * Import an array of parsed events into buttercup_event posts.
 *
 * @param array $events Array of event arrays with keys:
 *                      title, description, start, end, location, url, uid, image_url.
 * @return array Results with imported, skipped, failed counts and details.
 */
function buttercup_import_parsed_events( $events ) {
	$imported = 0;
	$skipped  = 0;
	$failed   = 0;
	$details  = array();
	$warnings = array();

	foreach ( $events as $event ) {
		$title = sanitize_text_field( $event['title'] ?? '' );
		if ( ! $title ) {
			++$failed;
			continue;
		}

		$uid = sanitize_text_field( $event['uid'] ?? '' );

		// Check for duplicates by UID.
		if ( $uid ) {
			$existing = new WP_Query(
				array(
					'post_type'      => 'buttercup_event',
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'meta_query'     => array(
						array(
							'key'   => '_buttercup_event_uid',
							'value' => $uid,
						),
					),
					'fields'         => 'ids',
				)
			);

			if ( $existing->have_posts() ) {
				++$skipped;
				$details[] = array(
					'title'  => $title,
					'status' => 'skipped',
					'reason' => __( 'Duplicate (UID match)', 'buttercup' ),
				);
				continue;
			}
		}

		// Also check by Facebook ID for backward compat.
		if ( $uid && strpos( $uid, 'fb-' ) === 0 ) {
			$fb_id       = substr( $uid, 3 );
			$existing_fb = new WP_Query(
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

			if ( $existing_fb->have_posts() ) {
				++$skipped;
				$details[] = array(
					'title'  => $title,
					'status' => 'skipped',
					'reason' => __( 'Duplicate (Facebook ID match)', 'buttercup' ),
				);
				continue;
			}
		}

		// Fallback dedup: title + start date.
		$start = sanitize_text_field( $event['start'] ?? '' );
		if ( ! $uid && $start ) {
			$existing_title = new WP_Query(
				array(
					'post_type'      => 'buttercup_event',
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'title'          => $title,
					'meta_query'     => array(
						array(
							'key'   => '_buttercup_event_start',
							'value' => $start,
						),
					),
					'fields'         => 'ids',
				)
			);

			if ( $existing_title->have_posts() ) {
				++$skipped;
				$details[] = array(
					'title'  => $title,
					'status' => 'skipped',
					'reason' => __( 'Duplicate (title + date match)', 'buttercup' ),
				);
				continue;
			}
		}

		// Create the event post.
		$description = $event['description'] ?? '';
		$post_data   = array(
			'post_type'    => 'buttercup_event',
			'post_title'   => $title,
			'post_content' => wp_kses_post( $description ),
			'post_status'  => 'publish',
		);

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			++$failed;
			$details[] = array(
				'title'  => $title,
				'status' => 'failed',
				'reason' => $post_id->get_error_message(),
			);
			continue;
		}

		// Set meta.
		$end      = sanitize_text_field( $event['end'] ?? '' );
		$location = sanitize_text_field( $event['location'] ?? '' );
		$url      = esc_url_raw( $event['url'] ?? '' );

		update_post_meta( $post_id, '_buttercup_event_start', $start );
		update_post_meta( $post_id, '_buttercup_event_end', $end );
		update_post_meta( $post_id, '_buttercup_event_location', $location );
		update_post_meta( $post_id, '_buttercup_event_url', $url );

		if ( $uid ) {
			update_post_meta( $post_id, '_buttercup_event_uid', $uid );
		}

		// Set Facebook ID if this is from Facebook.
		if ( $uid && strpos( $uid, 'fb-' ) === 0 ) {
			update_post_meta( $post_id, '_buttercup_event_facebook_id', substr( $uid, 3 ) );
		}

		// Download cover image if available.
		$image_url = $event['image_url'] ?? '';
		if ( $image_url ) {
			buttercup_import_event_image( $image_url, $post_id );
		}

		++$imported;
		$details[] = array(
			'title'  => $title,
			'status' => 'imported',
			'reason' => '',
		);
	}

	if ( $imported > 0 ) {
		buttercup_bump_cache_version();
	}

	return array(
		'imported' => $imported,
		'skipped'  => $skipped,
		'failed'   => $failed,
		'details'  => $details,
		'warnings' => $warnings,
	);
}

/**
 * Download an image and set it as a post's featured image.
 *
 * @param string $url     The image URL to download.
 * @param int    $post_id The post ID to attach the image to.
 */
function buttercup_import_event_image( $url, $post_id ) {
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

/* ── Results display ── */

/**
 * Render the import results summary and detail table.
 *
 * @param array $results Results array from the import handler.
 */
function buttercup_render_import_results( $results ) {
	if ( isset( $results['error'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $results['error'] ) . '</p></div>';
		return;
	}

	// Summary.
	$class = $results['failed'] > 0 ? 'notice-warning' : 'notice-success';
	echo '<div class="notice ' . esc_attr( $class ) . '">';
	echo '<p><strong>' . esc_html__( 'Import complete:', 'buttercup' ) . '</strong> ';
	printf(
		/* translators: 1: imported count, 2: skipped count, 3: failed count */
		esc_html__( '%1$d imported, %2$d skipped, %3$d failed', 'buttercup' ),
		intval( $results['imported'] ),
		intval( $results['skipped'] ),
		intval( $results['failed'] )
	);
	echo '</p></div>';

	// Warnings.
	if ( ! empty( $results['warnings'] ) ) {
		foreach ( $results['warnings'] as $warning ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $warning ) . '</p></div>';
		}
	}

	// Detail table.
	if ( ! empty( $results['details'] ) ) {
		echo '<table class="widefat striped" style="margin-top: 16px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Event', 'buttercup' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'buttercup' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'buttercup' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $results['details'] as $detail ) {
			$badge = '';
			switch ( $detail['status'] ) {
				case 'imported':
					$badge = '<span style="color:#00a32a;">&#10003; ' . esc_html__( 'Imported', 'buttercup' ) . '</span>';
					break;
				case 'skipped':
					$badge = '<span style="color:#dba617;">' . esc_html__( 'Skipped', 'buttercup' ) . '</span>';
					break;
				case 'failed':
					$badge = '<span style="color:#cc1818;">' . esc_html__( 'Failed', 'buttercup' ) . '</span>';
					break;
			}

			echo '<tr>';
			echo '<td>' . esc_html( $detail['title'] ) . '</td>';
			echo '<td>' . wp_kses_post( $badge ) . '</td>';
			echo '<td>' . esc_html( $detail['reason'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
