<?php
/**
 * Image helpers for building responsive image candidates and srcsets.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build an array of image size candidates for an attachment.
 *
 * @param int $attachment_id Attachment post ID.
 * @return array Array of candidate arrays with url, width, height, and crop keys.
 */
function buttercup_build_image_candidates( $attachment_id ) {
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! $meta || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return array();
	}

	$upload_dir = wp_get_upload_dir();
	$base_url   = isset( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : '';
	$base_url   = '' !== $base_url ? trailingslashit( $base_url ) : '';
	$file       = isset( $meta['file'] ) ? $meta['file'] : '';
	$file_dir   = $file ? trailingslashit( dirname( $file ) ) : '';

	$candidates = array();
	$full_url   = wp_get_attachment_url( $attachment_id );
	if ( $full_url ) {
		$candidates[] = array(
			'url'    => $full_url,
			'width'  => intval( $meta['width'] ),
			'height' => intval( $meta['height'] ),
			'crop'   => false,
		);
	}

	if ( ! empty( $meta['sizes'] ) && '' !== $base_url && '' !== $file_dir ) {
		foreach ( $meta['sizes'] as $size ) {
			if ( empty( $size['file'] ) || empty( $size['width'] ) || empty( $size['height'] ) ) {
				continue;
			}
			$candidates[] = array(
				'url'    => $base_url . $file_dir . $size['file'],
				'width'  => intval( $size['width'] ),
				'height' => intval( $size['height'] ),
				'crop'   => ! empty( $size['crop'] ),
			);
		}
	}

	return $candidates;
}

/**
 * Filter candidates to only uncropped images matching the original aspect ratio.
 *
 * @param array $candidates Array of image candidate arrays.
 * @param float $orig_ratio Original image aspect ratio (width / height).
 * @return array Filtered array of candidates.
 */
function buttercup_filter_uncropped_candidates( $candidates, $orig_ratio ) {
	$filtered        = array();
	$ratio_tolerance = 0.08;
	$orig_is_square  = abs( $orig_ratio - 1 ) <= 0.05;

	foreach ( $candidates as $candidate ) {
		if ( empty( $candidate['width'] ) || empty( $candidate['height'] ) ) {
			continue;
		}
		if ( ! empty( $candidate['crop'] ) ) {
			continue;
		}
		if ( $orig_ratio > 0 ) {
			$ratio = $candidate['width'] / $candidate['height'];
			if ( ! $orig_is_square ) {
				if ( abs( $ratio - $orig_ratio ) / $orig_ratio > $ratio_tolerance ) {
					continue;
				}
			}
		}
		$filtered[] = $candidate;
	}

	return $filtered;
}

/**
 * Pick the best candidate image for a given target width.
 *
 * @param array $candidates   Array of image candidate arrays.
 * @param int   $target_width Desired minimum width in pixels.
 * @return array|null Best candidate array or null if none available.
 */
function buttercup_pick_best_candidate( $candidates, $target_width ) {
	if ( empty( $candidates ) ) {
		return null;
	}
	usort(
		$candidates,
		function ( $a, $b ) {
			return $a['width'] <=> $b['width'];
		}
	);
	foreach ( $candidates as $candidate ) {
		if ( $candidate['width'] >= $target_width ) {
			return $candidate;
		}
	}
	return $candidates[ count( $candidates ) - 1 ];
}

/**
 * Build an srcset string from image candidates.
 *
 * @param array $candidates Array of image candidate arrays.
 * @return string Comma-separated srcset string.
 */
function buttercup_build_srcset( $candidates ) {
	if ( empty( $candidates ) ) {
		return '';
	}
	usort(
		$candidates,
		function ( $a, $b ) {
			return $a['width'] <=> $b['width'];
		}
	);
	$seen  = array();
	$parts = array();
	foreach ( $candidates as $candidate ) {
		$url = $candidate['url'];
		if ( ! $url || isset( $seen[ $url ] ) ) {
			continue;
		}
		$seen[ $url ] = true;
		$parts[]      = esc_url( $url ) . ' ' . intval( $candidate['width'] ) . 'w';
	}
	return implode( ', ', $parts );
}
