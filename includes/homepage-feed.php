<?php
/**
 * Homepage feed block server rendering.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enable tags on pages so they can appear in the homepage feed.
 *
 * @return void
 */
function buttercup_enable_page_tags() {
	register_taxonomy_for_object_type( 'post_tag', 'page' );
}
add_action( 'init', 'buttercup_enable_page_tags', 5 );

/**
 * Sanitize a tag slug, returning a fallback if empty.
 *
 * @param string $slug     The slug to sanitize.
 * @param string $fallback The fallback slug if sanitized value is empty.
 * @return string Sanitized slug or fallback.
 */
function buttercup_homepage_feed_sanitize_tag_slug( $slug, $fallback ) {
	$clean = sanitize_title( (string) $slug );
	if ( '' === $clean ) {
		return $fallback;
	}
	return $clean;
}

/**
 * Query post IDs that have a given tag slug.
 *
 * @param string $tag_slug The tag slug to query.
 * @param int    $limit    Maximum number of IDs to return.
 * @return int[] Array of post IDs.
 */
function buttercup_homepage_feed_query_post_ids_by_tag( $tag_slug, $limit = 6 ) {
	if ( '' === $tag_slug || $limit <= 0 ) {
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => intval( $limit ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => array( $tag_slug ),
				),
			),
		)
	);

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Count total posts matching a tag slug.
 *
 * @param string $tag_slug The tag slug to count.
 * @return int Total post count.
 */
function buttercup_homepage_feed_count_by_tag( $tag_slug ) {
	if ( '' === $tag_slug ) {
		return 0;
	}

	$query = new WP_Query(
		array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => array( $tag_slug ),
				),
			),
		)
	);

	return intval( $query->found_posts );
}

/**
 * Query post IDs tagged with both mast and home slugs.
 *
 * @param string $mast_tag_slug The mast tag slug.
 * @param string $home_tag_slug The home tag slug.
 * @param int    $limit         Maximum number of IDs to return.
 * @return int[] Array of post IDs.
 */
function buttercup_homepage_feed_query_dual_ids( $mast_tag_slug, $home_tag_slug, $limit = 10 ) {
	if ( '' === $mast_tag_slug || '' === $home_tag_slug || $limit <= 0 ) {
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => intval( $limit ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'relation' => 'AND',
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => array( $mast_tag_slug ),
				),
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => array( $home_tag_slug ),
				),
			),
		)
	);

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Hydrate an array of post IDs into full WP_Post objects.
 *
 * @param int[] $ids Array of post IDs.
 * @return WP_Post[] Array of post objects.
 */
function buttercup_homepage_feed_hydrate_posts( $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	if ( empty( $ids ) ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'           => array( 'post', 'page' ),
			'post_status'         => 'publish',
			'posts_per_page'      => count( $ids ),
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'ignore_sticky_posts' => true,
		)
	);
}

/**
 * Build the feed data structure from a cached snapshot.
 *
 * @param array $snapshot The cached snapshot array.
 * @return array Feed data with mast and home posts.
 */
function buttercup_homepage_feed_build_from_snapshot( $snapshot ) {
	$mast_ids           = array_values( array_unique( array_map( 'intval', (array) ( $snapshot['mast_ids'] ?? array() ) ) ) );
	$home_candidate_ids = array_values( array_unique( array_map( 'intval', (array) ( $snapshot['home_candidate_ids'] ?? array() ) ) ) );
	$home_selected_ids  = array_values( array_unique( array_map( 'intval', (array) ( $snapshot['home_selected_ids'] ?? array() ) ) ) );
	$dual_ids           = array_values( array_unique( array_map( 'intval', (array) ( $snapshot['dual_ids'] ?? array() ) ) ) );
	$mast_selected_id   = intval( $snapshot['mast_selected_id'] ?? 0 );

	$all_ids = array_values(
		array_unique(
			array_filter(
				array_merge(
					$mast_ids,
					$home_candidate_ids,
					$home_selected_ids,
					$dual_ids,
					array( $mast_selected_id )
				)
			)
		)
	);

	$by_id = array();
	foreach ( buttercup_homepage_feed_hydrate_posts( $all_ids ) as $post ) {
		$by_id[ intval( $post->ID ) ] = $post;
	}

	$mast_posts = array();
	foreach ( $mast_ids as $mast_id ) {
		if ( isset( $by_id[ $mast_id ] ) ) {
			$mast_posts[] = $by_id[ $mast_id ];
		}
	}

	$home_posts = array();
	foreach ( $home_candidate_ids as $home_id ) {
		if ( isset( $by_id[ $home_id ] ) ) {
			$home_posts[] = $by_id[ $home_id ];
		}
	}

	$home_selected = array();
	foreach ( $home_selected_ids as $home_selected_id ) {
		if ( isset( $by_id[ $home_selected_id ] ) ) {
			$home_selected[] = $by_id[ $home_selected_id ];
		}
	}

	$dual_posts = array();
	foreach ( $dual_ids as $dual_id ) {
		if ( isset( $by_id[ $dual_id ] ) ) {
			$dual_posts[] = $by_id[ $dual_id ];
		}
	}

	return array(
		'mast_tag_slug' => (string) ( $snapshot['mast_tag_slug'] ?? 'mast' ),
		'home_tag_slug' => (string) ( $snapshot['home_tag_slug'] ?? 'home' ),
		'mast_posts'    => $mast_posts,
		'home_posts'    => $home_posts,
		'mast_selected' => $mast_selected_id > 0 && isset( $by_id[ $mast_selected_id ] ) ? $by_id[ $mast_selected_id ] : null,
		'home_selected' => $home_selected,
		'dual_posts'    => $dual_posts,
		'mast_count'    => intval( $snapshot['mast_count'] ?? count( $mast_posts ) ),
		'home_count'    => intval( $snapshot['home_count'] ?? count( $home_posts ) ),
		'mast_overflow' => ! empty( $snapshot['mast_overflow'] ),
		'home_overflow' => ! empty( $snapshot['home_overflow'] ),
	);
}

/**
 * Collect and cache the homepage feed data for given tag slugs.
 *
 * @param string $mast_tag_slug The mast tag slug.
 * @param string $home_tag_slug The home tag slug.
 * @return array Feed data with mast and home posts.
 */
function buttercup_homepage_feed_collect( $mast_tag_slug = 'mast', $home_tag_slug = 'home' ) {
	static $request_cache = array();

	$mast_tag_slug = buttercup_homepage_feed_sanitize_tag_slug( $mast_tag_slug, 'mast' );
	$home_tag_slug = buttercup_homepage_feed_sanitize_tag_slug( $home_tag_slug, 'home' );
	$request_key   = $mast_tag_slug . '|' . $home_tag_slug;

	if ( isset( $request_cache[ $request_key ] ) ) {
		return $request_cache[ $request_key ];
	}

	$cache_key       = buttercup_build_cache_key( 'homepage_feed_collect', array( $mast_tag_slug, $home_tag_slug ) );
	$cached_snapshot = buttercup_cache_get( $cache_key );
	if ( is_array( $cached_snapshot ) ) {
		$request_cache[ $request_key ] = buttercup_homepage_feed_build_from_snapshot( $cached_snapshot );
		return $request_cache[ $request_key ];
	}

	$mast_ids           = buttercup_homepage_feed_query_post_ids_by_tag( $mast_tag_slug, 2 );
	$home_candidate_ids = buttercup_homepage_feed_query_post_ids_by_tag( $home_tag_slug, 24 );
	$dual_preview_ids   = buttercup_homepage_feed_query_dual_ids( $mast_tag_slug, $home_tag_slug, 10 );

	$dual_home_ids = array();
	if ( ! empty( $home_candidate_ids ) ) {
		$term_rows = wp_get_object_terms( $home_candidate_ids, 'post_tag', array( 'fields' => 'all_with_object_id' ) );
		if ( ! is_wp_error( $term_rows ) ) {
			foreach ( $term_rows as $term_row ) {
				if ( isset( $term_row->slug, $term_row->object_id ) && $term_row->slug === $mast_tag_slug ) {
					$dual_home_ids[ intval( $term_row->object_id ) ] = true;
				}
			}
		}
	}

	$mast_selected_id  = ! empty( $mast_ids ) ? intval( $mast_ids[0] ) : 0;
	$home_selected_ids = array();
	foreach ( $home_candidate_ids as $home_candidate_id ) {
		$home_candidate_id = intval( $home_candidate_id );
		if ( $home_candidate_id <= 0 || $home_candidate_id === $mast_selected_id ) {
			continue;
		}
		if ( isset( $dual_home_ids[ $home_candidate_id ] ) ) {
			continue;
		}
		$home_selected_ids[] = $home_candidate_id;
	}

	$snapshot                  = array(
		'mast_tag_slug'      => $mast_tag_slug,
		'home_tag_slug'      => $home_tag_slug,
		'mast_ids'           => $mast_ids,
		'home_candidate_ids' => $home_candidate_ids,
		'mast_selected_id'   => $mast_selected_id,
		'home_selected_ids'  => $home_selected_ids,
		'dual_ids'           => $dual_preview_ids,
		'mast_count'         => buttercup_homepage_feed_count_by_tag( $mast_tag_slug ),
		'home_count'         => buttercup_homepage_feed_count_by_tag( $home_tag_slug ),
	);
	$snapshot['mast_overflow'] = $snapshot['mast_count'] > 1;
	$snapshot['home_overflow'] = false;

	buttercup_cache_set( $cache_key, $snapshot, 600 );
	$request_cache[ $request_key ] = buttercup_homepage_feed_build_from_snapshot( $snapshot );

	return $request_cache[ $request_key ];
}

/**
 * Summarize an array of posts into lightweight data for REST responses.
 *
 * @param WP_Post[] $posts Array of post objects.
 * @return array[] Array of summary arrays.
 */
function buttercup_homepage_feed_summarize_posts( $posts ) {
	$summary = array();

	foreach ( $posts as $post ) {
		$type_obj  = get_post_type_object( $post->post_type );
		$summary[] = array(
			'id'        => intval( $post->ID ),
			'title'     => get_the_title( $post ),
			'type'      => $post->post_type,
			'typeLabel' => $type_obj ? $type_obj->labels->singular_name : $post->post_type,
			'date'      => mysql_to_rfc3339( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date ),
			'permalink' => get_permalink( $post ),
		);
	}

	return $summary;
}

/**
 * Get attachment data (URL, dimensions, alt) for a given attachment ID.
 *
 * @param int $attachment_id The attachment ID.
 * @return array|null Attachment data array or null if invalid.
 */
function buttercup_homepage_feed_get_attachment_data( $attachment_id ) {
	$attachment_id = intval( $attachment_id );
	if ( $attachment_id <= 0 ) {
		return null;
	}

	$src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $src || empty( $src[0] ) ) {
		return null;
	}

	$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

	return array(
		'id'     => $attachment_id,
		'url'    => $src[0],
		'width'  => intval( $src[1] ),
		'height' => intval( $src[2] ),
		'alt'    => is_string( $alt ) ? trim( $alt ) : '',
	);
}

/**
 * Get mobile and desktop thumbnail data for a post.
 *
 * @param int $post_id The post ID.
 * @return array|null Array with mobile and desktop keys, or null.
 */
function buttercup_homepage_feed_get_thumbnail_pair( $post_id ) {
	$mobile_id  = intval( get_post_meta( $post_id, 'buttercup_home_mobile_image_id', true ) );
	$desktop_id = intval( get_post_meta( $post_id, 'buttercup_home_desktop_image_id', true ) );

	if ( $mobile_id <= 0 && $desktop_id <= 0 ) {
		return null;
	}

	$mobile  = $mobile_id > 0 ? buttercup_homepage_feed_get_attachment_data( $mobile_id ) : null;
	$desktop = $desktop_id > 0 ? buttercup_homepage_feed_get_attachment_data( $desktop_id ) : null;

	if ( ! $mobile && ! $desktop ) {
		return null;
	}

	if ( ! $mobile ) {
		$mobile = $desktop;
	}
	if ( ! $desktop ) {
		$desktop = $mobile;
	}

	return array(
		'mobile'  => $mobile,
		'desktop' => $desktop,
	);
}

/**
 * Render a hero-style feed item with responsive images.
 *
 * @param WP_Post    $post           The post object.
 * @param array|null $thumbnail_pair Thumbnail pair with mobile and desktop data.
 * @param bool       $is_mast        Whether this is a mast item.
 * @return string HTML markup for the hero item.
 */
function buttercup_homepage_feed_render_hero_item( $post, $thumbnail_pair, $is_mast = false ) {
	if ( ! $thumbnail_pair ) {
		return '';
	}

	$mobile  = $thumbnail_pair['mobile'] ?? null;
	$desktop = $thumbnail_pair['desktop'] ?? null;

	if ( ( ! $mobile || empty( $mobile['url'] ) ) && ( ! $desktop || empty( $desktop['url'] ) ) ) {
		return '';
	}

	$post_id = intval( $post->ID );
	$title   = get_the_title( $post );
	$link    = get_permalink( $post );

	$alt = '';
	if ( $mobile && ! empty( $mobile['alt'] ) ) {
		$alt = $mobile['alt'];
	} elseif ( $desktop && ! empty( $desktop['alt'] ) ) {
		$alt = $desktop['alt'];
	} else {
		$alt = $title;
	}

	$classes = array( 'buttercup-homepage-feed__item', 'buttercup-homepage-feed__item--hero' );
	if ( $is_mast ) {
		$classes[] = 'is-mast';
	}

	$mobile_url  = $mobile && ! empty( $mobile['url'] ) ? $mobile['url'] : ( $desktop['url'] ?? '' );
	$desktop_url = $desktop && ! empty( $desktop['url'] ) ? $desktop['url'] : $mobile_url;

	$html  = '<article class="' . esc_attr( implode( ' ', $classes ) ) . '" data-post-id="' . esc_attr( $post_id ) . '">';
	$html .= '<a class="buttercup-homepage-feed__hero-link" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $title ) . '">';
	$html .= '<picture class="buttercup-homepage-feed__picture">';
	if ( '' !== $desktop_url ) {
		$html .= '<source media="(min-width: 1024px)" srcset="' . esc_url( $desktop_url ) . '">';
	}
	$html .= '<img class="buttercup-homepage-feed__hero-image" loading="lazy" decoding="async" src="' . esc_url( $mobile_url ) . '" alt="' . esc_attr( $alt ) . '"';
	if ( ! empty( $mobile['width'] ) && ! empty( $mobile['height'] ) ) {
		$html .= ' width="' . esc_attr( intval( $mobile['width'] ) ) . '" height="' . esc_attr( intval( $mobile['height'] ) ) . '"';
	}
	$html .= '>';
	$html .= '</picture>';
	$html .= '</a>';
	$html .= '</article>';

	return $html;
}

/**
 * Render a split-layout feed item with optional featured image.
 *
 * @param WP_Post $post      The post object.
 * @param string  $cta_label The call-to-action button label.
 * @param bool    $is_mast   Whether this is a mast item.
 * @return string HTML markup for the split item.
 */
function buttercup_homepage_feed_render_split_item( $post, $cta_label, $is_mast = false ) {
	$post_id   = intval( $post->ID );
	$title     = get_the_title( $post );
	$link      = get_permalink( $post );
	$thumb_id  = get_post_thumbnail_id( $post );
	$has_image = $thumb_id ? true : false;

	$classes = array( 'buttercup-homepage-feed__item', 'buttercup-homepage-feed__item--split' );
	if ( ! $has_image ) {
		$classes[] = 'is-text-only';
	}
	if ( $is_mast ) {
		$classes[] = 'is-mast';
	}

	$html = '<article class="' . esc_attr( implode( ' ', $classes ) ) . '" data-post-id="' . esc_attr( $post_id ) . '">';
	if ( $has_image ) {
		$html .= '<div class="buttercup-homepage-feed__split-media">';
		$html .= '<a class="buttercup-homepage-feed__split-image-link" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $title ) . '">';
		$html .= get_the_post_thumbnail(
			$post,
			'large',
			array(
				'class'    => 'buttercup-homepage-feed__split-image',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		$html .= '</a>';
		$html .= '</div>';
	}
	$html .= '<div class="buttercup-homepage-feed__split-content">';
	$html .= '<h2 class="buttercup-homepage-feed__title"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h2>';
	$html .= '<a class="buttercup-homepage-feed__button" href="' . esc_url( $link ) . '">' . esc_html( $cta_label ) . '</a>';
	$html .= '</div>';
	$html .= '</article>';

	return $html;
}

/**
 * Render a single feed item, choosing hero or split layout.
 *
 * @param WP_Post $post      The post object.
 * @param string  $cta_label The call-to-action button label.
 * @param bool    $is_mast   Whether this is a mast item.
 * @return string HTML markup for the feed item.
 */
function buttercup_homepage_feed_render_item( $post, $cta_label, $is_mast = false ) {
	$thumbnail_pair = buttercup_homepage_feed_get_thumbnail_pair( $post->ID );
	if ( $thumbnail_pair ) {
		$hero_html = buttercup_homepage_feed_render_hero_item( $post, $thumbnail_pair, $is_mast );
		if ( '' !== $hero_html ) {
			return $hero_html;
		}
	}

	return buttercup_homepage_feed_render_split_item( $post, $cta_label, $is_mast );
}

/**
 * Render the homepage feed block output.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for the homepage feed.
 */
function buttercup_render_homepage_feed( $attributes ) {
	$cta_label = isset( $attributes['ctaLabel'] ) ? trim( (string) $attributes['ctaLabel'] ) : '';
	if ( '' === $cta_label ) {
		$cta_label = get_option( 'buttercup_feed_cta_label', '' );
	}
	if ( '' === $cta_label ) {
		$cta_label = __( 'Read More', 'buttercup' );
	}

	$default_mast  = get_option( 'buttercup_feed_mast_tag', 'mast' );
	$default_home  = get_option( 'buttercup_feed_home_tag', 'home' );
	$mast_tag_slug = isset( $attributes['mastTagSlug'] ) ? $attributes['mastTagSlug'] : $default_mast;
	$home_tag_slug = isset( $attributes['homeTagSlug'] ) ? $attributes['homeTagSlug'] : $default_home;
	$render_mode   = isset( $attributes['renderMode'] ) ? (string) $attributes['renderMode'] : 'all';
	$home_position = isset( $attributes['homePosition'] ) ? max( 1, intval( $attributes['homePosition'] ) ) : 1;

	$feed       = buttercup_homepage_feed_collect( $mast_tag_slug, $home_tag_slug );
	$mast_post  = $feed['mast_selected'];
	$home_posts = $feed['home_selected'];

	if ( 'mast' === $render_mode ) {
		if ( ! $mast_post ) {
			return '';
		}
		$mast_html = buttercup_homepage_feed_render_item( $mast_post, $cta_label, true );
		if ( '' === $mast_html ) {
			return '';
		}
		$should_detach_mast = buttercup_homepage_feed_should_detach_mast();
		if ( $should_detach_mast ) {
			$classes           = array( 'wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--mast' );
			$mast_section_html = '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $mast_html . '</section>';
			buttercup_homepage_feed_store_detached_mast_html( $mast_section_html );
			return '';
		}
		$classes = array( 'wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--mast' );
		return '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $mast_html . '</section>';
	}

	if ( 'home-all' === $render_mode ) {
		if ( empty( $home_posts ) ) {
			return '';
		}
		$home_html = '';
		foreach ( $home_posts as $home_post ) {
			$home_html .= buttercup_homepage_feed_render_item( $home_post, $cta_label, false );
		}
		if ( '' === $home_html ) {
			return '';
		}
		$classes = array( 'wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--home-all' );
		return '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $home_html . '</section>';
	}

	if ( 'home-item' === $render_mode ) {
		$index = $home_position - 1;
		if ( ! isset( $home_posts[ $index ] ) ) {
			return '';
		}
		$item_html = buttercup_homepage_feed_render_item( $home_posts[ $index ], $cta_label, false );
		if ( '' === $item_html ) {
			return '';
		}
		$classes = array( 'wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--home-item' );
		return '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $item_html . '</section>';
	}

	// Default: render_mode === 'all'.
	if ( ! $mast_post && empty( $home_posts ) ) {
		return '';
	}

	$mast_html = '';
	if ( $mast_post ) {
		$mast_html = buttercup_homepage_feed_render_item( $mast_post, $cta_label, true );
	}

	$home_html = '';
	foreach ( $home_posts as $home_post ) {
		$home_html .= buttercup_homepage_feed_render_item( $home_post, $cta_label, false );
	}

	$should_detach_mast = buttercup_homepage_feed_should_detach_mast();
	if ( $should_detach_mast && '' !== $mast_html ) {
		$classes           = array( 'wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--mast' );
		$mast_section_html = '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $mast_html . '</section>';
		buttercup_homepage_feed_store_detached_mast_html( $mast_section_html );
		$mast_html = '';
	}

	if ( '' === $mast_html && '' === $home_html ) {
		return '';
	}

	$classes = array( 'wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed' );
	if ( $should_detach_mast && $mast_post ) {
		$classes[] = 'is-mast-detached';
	}

	$html  = '<section class="' . esc_attr( implode( ' ', $classes ) ) . '">';
	$html .= $mast_html;
	$html .= $home_html;

	$html .= '</section>';

	return $html;
}

/**
 * Determine whether the mast section should be detached from the block output.
 *
 * @return bool True if the mast should be detached.
 */
function buttercup_homepage_feed_should_detach_mast() {
	if ( is_admin() ) {
		return false;
	}

	if ( ! is_singular() ) {
		return false;
	}

	if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
		return false;
	}

	return true;
}

/**
 * Get the current context post ID from the main query.
 *
 * @return int The post ID, or 0 if unavailable.
 */
function buttercup_homepage_feed_get_context_post_id() {
	$post_id = get_the_ID();
	if ( $post_id > 0 ) {
		return intval( $post_id );
	}

	$queried = get_queried_object();
	if ( is_object( $queried ) && isset( $queried->ID ) ) {
		return intval( $queried->ID );
	}

	return 0;
}

/**
 * Store detached mast HTML in a global for later retrieval.
 *
 * @param string $mast_section_html The mast section HTML to store.
 * @return void
 */
function buttercup_homepage_feed_store_detached_mast_html( $mast_section_html ) {
	if ( ! is_string( $mast_section_html ) || '' === $mast_section_html ) {
		return;
	}

	if ( ! isset( $GLOBALS['buttercup_homepage_feed_detached_mast'] ) || ! is_array( $GLOBALS['buttercup_homepage_feed_detached_mast'] ) ) {
		$GLOBALS['buttercup_homepage_feed_detached_mast'] = array();
	}

	$post_id = buttercup_homepage_feed_get_context_post_id();
	if ( isset( $GLOBALS['buttercup_homepage_feed_detached_mast'][ $post_id ] ) ) {
		return;
	}

	$GLOBALS['buttercup_homepage_feed_detached_mast'][ $post_id ] = $mast_section_html;
}

/**
 * Pop the stored detached mast HTML for the current context.
 *
 * @return string The mast HTML, or empty string if none stored.
 */
function buttercup_homepage_feed_pop_detached_mast_html() {
	if ( ! isset( $GLOBALS['buttercup_homepage_feed_detached_mast'] ) || ! is_array( $GLOBALS['buttercup_homepage_feed_detached_mast'] ) ) {
		return '';
	}

	$post_id = buttercup_homepage_feed_get_context_post_id();
	if ( $post_id > 0 && isset( $GLOBALS['buttercup_homepage_feed_detached_mast'][ $post_id ] ) ) {
		$html = $GLOBALS['buttercup_homepage_feed_detached_mast'][ $post_id ];
		unset( $GLOBALS['buttercup_homepage_feed_detached_mast'][ $post_id ] );
		return $html;
	}

	if ( isset( $GLOBALS['buttercup_homepage_feed_detached_mast'][0] ) ) {
		$html = $GLOBALS['buttercup_homepage_feed_detached_mast'][0];
		unset( $GLOBALS['buttercup_homepage_feed_detached_mast'][0] );
		return $html;
	}

	foreach ( $GLOBALS['buttercup_homepage_feed_detached_mast'] as $key => $html ) {
		unset( $GLOBALS['buttercup_homepage_feed_detached_mast'][ $key ] );
		return is_string( $html ) ? $html : '';
	}

	return '';
}

/**
 * Prepend detached mast HTML to the post content on singular views.
 *
 * @param string $content The post content.
 * @return string Modified content with mast prepended.
 */
function buttercup_homepage_feed_prepend_detached_mast_to_content( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return $content;
	}

	if ( ! is_singular() ) {
		return $content;
	}

	if ( ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$mast_html = buttercup_homepage_feed_pop_detached_mast_html();
	if ( '' === $mast_html ) {
		return $content;
	}

	return $mast_html . $content;
}
add_filter( 'the_content', 'buttercup_homepage_feed_prepend_detached_mast_to_content', 11 );

/**
 * Register post meta fields for homepage feed images.
 *
 * @return void
 */
function buttercup_register_homepage_feed_meta() {
	foreach ( array( 'post', 'page' ) as $post_type ) {
		register_post_meta(
			$post_type,
			'buttercup_home_mobile_image_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_post_meta(
			$post_type,
			'buttercup_home_desktop_image_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'buttercup_register_homepage_feed_meta', 20 );

/**
 * Generate image dimension and ratio warnings for an attachment.
 *
 * @param int    $attachment_id The attachment ID.
 * @param string $label         Human-readable image label.
 * @param float  $target_ratio  The target aspect ratio.
 * @param int    $min_width     Minimum suggested width.
 * @param int    $min_height    Minimum suggested height.
 * @return string[] Array of warning messages.
 */
function buttercup_homepage_feed_image_warnings( $attachment_id, $label, $target_ratio, $min_width, $min_height ) {
	$warnings = array();
	$meta     = wp_get_attachment_metadata( $attachment_id );

	if ( ! $meta || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		$warnings[] = sprintf(
			// translators: %s: image label (e.g. "Featured" or "Thumbnail").
			__( 'Could not read dimensions for the %s image.', 'buttercup' ),
			$label
		);
		return $warnings;
	}

	$width  = intval( $meta['width'] );
	$height = intval( $meta['height'] );
	if ( $width < $min_width || $height < $min_height ) {
		$warnings[] = sprintf(
			// translators: %1$s: image label, %2$d: actual width, %3$d: actual height, %4$d: minimum width, %5$d: minimum height.
			__( '%1$s image is %2$dx%3$d. Suggested minimum is %4$dx%5$d.', 'buttercup' ),
			$label,
			$width,
			$height,
			$min_width,
			$min_height
		);
	}

	$ratio = $height > 0 ? $width / $height : 0;
	if ( $ratio > 0 ) {
		$diff = abs( $ratio - $target_ratio ) / $target_ratio;
		if ( $diff > 0.07 ) {
			$warnings[] = sprintf(
				// translators: %1$s: image label, %2$s: actual aspect ratio, %3$s: suggested aspect ratio.
				__( '%1$s image ratio is about %2$s:1. Suggested ratio is %3$s:1.', 'buttercup' ),
				$label,
				number_format_i18n( $ratio, 2 ),
				number_format_i18n( $target_ratio, 2 )
			);
		}
	}

	return $warnings;
}

/**
 * Render a single image picker field in the meta box.
 *
 * @param int    $post_id        The post ID.
 * @param string $field_key      The meta key for the image ID.
 * @param string $field_label    Human-readable field label.
 * @param string $suggested_size Suggested size description.
 * @param float  $target_ratio   Target aspect ratio.
 * @param int    $min_width      Minimum suggested width.
 * @param int    $min_height     Minimum suggested height.
 * @return void
 */
function buttercup_homepage_feed_render_image_field( $post_id, $field_key, $field_label, $suggested_size, $target_ratio, $min_width, $min_height ) {
	$value        = intval( get_post_meta( $post_id, $field_key, true ) );
	$preview      = $value ? wp_get_attachment_image_src( $value, 'medium' ) : null;
	$warnings     = $value ? buttercup_homepage_feed_image_warnings( $value, $field_label, $target_ratio, $min_width, $min_height ) : array();
	$choose_label = $value ? __( 'Replace Image', 'buttercup' ) : __( 'Select Image', 'buttercup' );

	echo '<div class="buttercup-homepage-image-field"';
	echo ' data-target-ratio="' . esc_attr( $target_ratio ) . '"';
	echo ' data-min-width="' . esc_attr( $min_width ) . '"';
	echo ' data-min-height="' . esc_attr( $min_height ) . '"';
	echo ' data-label="' . esc_attr( $field_label ) . '">';

	echo '<p><strong>' . esc_html( $field_label ) . '</strong></p>';
	echo '<p class="description">' . esc_html( $suggested_size ) . '</p>';

	echo '<input type="hidden" class="buttercup-homepage-image-id" name="' . esc_attr( $field_key ) . '" value="' . esc_attr( $value ) . '">';
	echo '<div class="buttercup-homepage-image-preview">';
	if ( $preview && ! empty( $preview[0] ) ) {
		echo '<img src="' . esc_url( $preview[0] ) . '" alt="" style="max-width:100%;height:auto;">';
	} else {
		echo '<em>' . esc_html__( 'No image selected.', 'buttercup' ) . '</em>';
	}
	echo '</div>';

	echo '<p class="buttercup-homepage-image-actions">';
	echo '<button type="button" class="button buttercup-homepage-image-select">' . esc_html( $choose_label ) . '</button> ';
	echo '<button type="button" class="button-link-delete buttercup-homepage-image-clear"';
	if ( $value <= 0 ) {
		echo ' style="display:none;"';
	}
	echo '>' . esc_html__( 'Clear', 'buttercup' ) . '</button>';
	echo '</p>';

	echo '<div class="buttercup-homepage-image-dimensions">';
	if ( ! empty( $warnings ) ) {
		foreach ( $warnings as $warning ) {
			echo '<p class="description" style="color:#b45309;">' . esc_html( $warning ) . '</p>';
		}
	}
	echo '</div>';
	echo '</div>';
}

/**
 * Render the homepage feed meta box content.
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function buttercup_render_homepage_feed_meta_box( $post ) {
	$status    = buttercup_homepage_feed_collect( 'mast', 'home' );
	$tag_slugs = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $tag_slugs ) ) {
		$tag_slugs = array();
	}

	$has_mast = in_array( 'mast', $tag_slugs, true );
	$has_home = in_array( 'home', $tag_slugs, true );

	wp_nonce_field( 'buttercup_homepage_feed_meta', 'buttercup_homepage_feed_meta_nonce' );

	echo '<p class="description">' . esc_html__( 'Homepage feed rules: up to 1 "mast" item and up to 5 "home" items.', 'buttercup' ) . '</p>';

	if ( $status['mast_overflow'] ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'More than one post/page is tagged "mast". Only the newest will render.', 'buttercup' ) . '</p></div>';
	}
	if ( $status['home_overflow'] ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'More than five posts/pages are tagged "home". Only the five newest will render.', 'buttercup' ) . '</p></div>';
	}
	if ( ! empty( $status['dual_posts'] ) ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Some items are tagged with both "mast" and "home". Dual-tagged items are treated as mast only.', 'buttercup' ) . '</p></div>';
	}
	if ( $has_mast && $has_home ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This item currently has both "mast" and "home". It will only render in the mast position.', 'buttercup' ) . '</p></div>';
	}

	buttercup_homepage_feed_render_image_field(
		$post->ID,
		'buttercup_home_mobile_image_id',
		__( 'Mobile Thumbnail (5:3)', 'buttercup' ),
		__( 'Suggested: 2000 x 1200 (5:3)', 'buttercup' ),
		( 5 / 3 ),
		2000,
		1200
	);

	echo '<hr style="margin:16px 0;">';

	buttercup_homepage_feed_render_image_field(
		$post->ID,
		'buttercup_home_desktop_image_id',
		__( 'Desktop Thumbnail (2.74:1)', 'buttercup' ),
		__( 'Suggested: 2880 x 1050 (2.74:1)', 'buttercup' ),
		2.74,
		2880,
		1050
	);
}

/**
 * Register the homepage feed meta box on supported post types.
 *
 * @return void
 */
function buttercup_add_homepage_feed_meta_box() {
	add_meta_box(
		'buttercup-homepage-feed-images',
		__( 'Buttercup Homepage Images', 'buttercup' ),
		'buttercup_render_homepage_feed_meta_box',
		buttercup_homepage_feed_meta_box_post_types(),
		'side'
	);
}
add_action( 'add_meta_boxes', 'buttercup_add_homepage_feed_meta_box' );

/**
 * Post types that support homepage feed images.
 *
 * @return string[] Array of post type slugs.
 */
function buttercup_homepage_feed_meta_box_post_types() {
	return array( 'post', 'page', 'buttercup_event' );
}

/**
 * Save homepage feed meta box data on post save.
 *
 * @param int $post_id The post ID being saved.
 * @return void
 */
function buttercup_save_homepage_feed_meta_box( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	if ( ! in_array( $post_type, buttercup_homepage_feed_meta_box_post_types(), true ) ) {
		return;
	}

	if ( ! isset( $_POST['buttercup_homepage_feed_meta_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['buttercup_homepage_feed_meta_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'buttercup_homepage_feed_meta' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$mobile_id  = isset( $_POST['buttercup_home_mobile_image_id'] ) ? absint( wp_unslash( $_POST['buttercup_home_mobile_image_id'] ) ) : 0;
	$desktop_id = isset( $_POST['buttercup_home_desktop_image_id'] ) ? absint( wp_unslash( $_POST['buttercup_home_desktop_image_id'] ) ) : 0;

	if ( $mobile_id > 0 ) {
		update_post_meta( $post_id, 'buttercup_home_mobile_image_id', $mobile_id );
	} else {
		delete_post_meta( $post_id, 'buttercup_home_mobile_image_id' );
	}

	if ( $desktop_id > 0 ) {
		update_post_meta( $post_id, 'buttercup_home_desktop_image_id', $desktop_id );
	} else {
		delete_post_meta( $post_id, 'buttercup_home_desktop_image_id' );
	}
}
add_action( 'save_post', 'buttercup_save_homepage_feed_meta_box' );

/**
 * Enqueue media library and meta box scripts on post edit screens.
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function buttercup_enqueue_homepage_feed_meta_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}

	wp_enqueue_media();

	$script_path = BUTTERCUP_PLUGIN_DIR . '/assets/homepage-meta.js';
	if ( file_exists( $script_path ) ) {
		wp_enqueue_script(
			'buttercup-homepage-meta',
			plugins_url( 'assets/homepage-meta.js', BUTTERCUP_PLUGIN_FILE ),
			array( 'jquery' ),
			filemtime( $script_path ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'buttercup_enqueue_homepage_feed_meta_assets' );
