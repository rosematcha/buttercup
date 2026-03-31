<?php

if (!defined('ABSPATH')) {
    exit;
}

function buttercup_enable_page_tags()
{
    register_taxonomy_for_object_type('post_tag', 'page');
}
add_action('init', 'buttercup_enable_page_tags', 5);

function buttercup_homepage_feed_sanitize_tag_slug($slug, $fallback)
{
    $clean = sanitize_title((string) $slug);
    if ($clean === '') {
        return $fallback;
    }
    return $clean;
}

function buttercup_homepage_feed_query_post_ids_by_tag($tag_slug, $limit = 6)
{
    if ($tag_slug === '' || $limit <= 0) {
        return [];
    }

    $ids = get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => intval($limit),
        'orderby' => 'date',
        'order' => 'DESC',
        'ignore_sticky_posts' => true,
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query' => [
            [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => [$tag_slug],
            ],
        ],
    ]);

    return array_values(array_unique(array_map('intval', $ids)));
}

function buttercup_homepage_feed_count_by_tag($tag_slug)
{
    if ($tag_slug === '') {
        return 0;
    }

    $query = new WP_Query([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'ignore_sticky_posts' => true,
        'fields' => 'ids',
        'no_found_rows' => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query' => [
            [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => [$tag_slug],
            ],
        ],
    ]);

    return intval($query->found_posts);
}

function buttercup_homepage_feed_query_dual_ids($mast_tag_slug, $home_tag_slug, $limit = 10)
{
    if ($mast_tag_slug === '' || $home_tag_slug === '' || $limit <= 0) {
        return [];
    }

    $ids = get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => intval($limit),
        'orderby' => 'date',
        'order' => 'DESC',
        'ignore_sticky_posts' => true,
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query' => [
            'relation' => 'AND',
            [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => [$mast_tag_slug],
            ],
            [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => [$home_tag_slug],
            ],
        ],
    ]);

    return array_values(array_unique(array_map('intval', $ids)));
}

function buttercup_homepage_feed_hydrate_posts($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    if (empty($ids)) {
        return [];
    }

    return get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => count($ids),
        'post__in' => $ids,
        'orderby' => 'post__in',
        'ignore_sticky_posts' => true,
    ]);
}

function buttercup_homepage_feed_build_from_snapshot($snapshot)
{
    $mast_ids = array_values(array_unique(array_map('intval', (array) ($snapshot['mast_ids'] ?? []))));
    $home_candidate_ids = array_values(array_unique(array_map('intval', (array) ($snapshot['home_candidate_ids'] ?? []))));
    $home_selected_ids = array_values(array_unique(array_map('intval', (array) ($snapshot['home_selected_ids'] ?? []))));
    $dual_ids = array_values(array_unique(array_map('intval', (array) ($snapshot['dual_ids'] ?? []))));
    $mast_selected_id = intval($snapshot['mast_selected_id'] ?? 0);

    $all_ids = array_values(array_unique(array_filter(array_merge(
        $mast_ids,
        $home_candidate_ids,
        $home_selected_ids,
        $dual_ids,
        [$mast_selected_id]
    ))));

    $by_id = [];
    foreach (buttercup_homepage_feed_hydrate_posts($all_ids) as $post) {
        $by_id[intval($post->ID)] = $post;
    }

    $mast_posts = [];
    foreach ($mast_ids as $mast_id) {
        if (isset($by_id[$mast_id])) {
            $mast_posts[] = $by_id[$mast_id];
        }
    }

    $home_posts = [];
    foreach ($home_candidate_ids as $home_id) {
        if (isset($by_id[$home_id])) {
            $home_posts[] = $by_id[$home_id];
        }
    }

    $home_selected = [];
    foreach ($home_selected_ids as $home_selected_id) {
        if (isset($by_id[$home_selected_id])) {
            $home_selected[] = $by_id[$home_selected_id];
        }
    }

    $dual_posts = [];
    foreach ($dual_ids as $dual_id) {
        if (isset($by_id[$dual_id])) {
            $dual_posts[] = $by_id[$dual_id];
        }
    }

    return [
        'mast_tag_slug' => (string) ($snapshot['mast_tag_slug'] ?? 'mast'),
        'home_tag_slug' => (string) ($snapshot['home_tag_slug'] ?? 'home'),
        'mast_posts' => $mast_posts,
        'home_posts' => $home_posts,
        'mast_selected' => $mast_selected_id > 0 && isset($by_id[$mast_selected_id]) ? $by_id[$mast_selected_id] : null,
        'home_selected' => $home_selected,
        'dual_posts' => $dual_posts,
        'mast_count' => intval($snapshot['mast_count'] ?? count($mast_posts)),
        'home_count' => intval($snapshot['home_count'] ?? count($home_posts)),
        'mast_overflow' => !empty($snapshot['mast_overflow']),
        'home_overflow' => !empty($snapshot['home_overflow']),
    ];
}

function buttercup_homepage_feed_collect($mast_tag_slug = 'mast', $home_tag_slug = 'home')
{
    static $request_cache = [];

    $mast_tag_slug = buttercup_homepage_feed_sanitize_tag_slug($mast_tag_slug, 'mast');
    $home_tag_slug = buttercup_homepage_feed_sanitize_tag_slug($home_tag_slug, 'home');
    $request_key = $mast_tag_slug . '|' . $home_tag_slug;

    if (isset($request_cache[$request_key])) {
        return $request_cache[$request_key];
    }

    $cache_key = buttercup_build_cache_key('homepage_feed_collect', [$mast_tag_slug, $home_tag_slug]);
    $cached_snapshot = buttercup_cache_get($cache_key);
    if (is_array($cached_snapshot)) {
        $request_cache[$request_key] = buttercup_homepage_feed_build_from_snapshot($cached_snapshot);
        return $request_cache[$request_key];
    }

    $mast_ids = buttercup_homepage_feed_query_post_ids_by_tag($mast_tag_slug, 2);
    $home_candidate_ids = buttercup_homepage_feed_query_post_ids_by_tag($home_tag_slug, 24);
    $dual_preview_ids = buttercup_homepage_feed_query_dual_ids($mast_tag_slug, $home_tag_slug, 10);

    $dual_home_ids = [];
    if (!empty($home_candidate_ids)) {
        $term_rows = wp_get_object_terms($home_candidate_ids, 'post_tag', ['fields' => 'all_with_object_id']);
        if (!is_wp_error($term_rows)) {
            foreach ($term_rows as $term_row) {
                if (isset($term_row->slug, $term_row->object_id) && $term_row->slug === $mast_tag_slug) {
                    $dual_home_ids[intval($term_row->object_id)] = true;
                }
            }
        }
    }

    $mast_selected_id = !empty($mast_ids) ? intval($mast_ids[0]) : 0;
    $home_selected_ids = [];
    foreach ($home_candidate_ids as $home_candidate_id) {
        $home_candidate_id = intval($home_candidate_id);
        if ($home_candidate_id <= 0 || $home_candidate_id === $mast_selected_id) {
            continue;
        }
        if (isset($dual_home_ids[$home_candidate_id])) {
            continue;
        }
        $home_selected_ids[] = $home_candidate_id;
        if (count($home_selected_ids) >= 5) {
            break;
        }
    }

    $snapshot = [
        'mast_tag_slug' => $mast_tag_slug,
        'home_tag_slug' => $home_tag_slug,
        'mast_ids' => $mast_ids,
        'home_candidate_ids' => $home_candidate_ids,
        'mast_selected_id' => $mast_selected_id,
        'home_selected_ids' => $home_selected_ids,
        'dual_ids' => $dual_preview_ids,
        'mast_count' => buttercup_homepage_feed_count_by_tag($mast_tag_slug),
        'home_count' => buttercup_homepage_feed_count_by_tag($home_tag_slug),
    ];
    $snapshot['mast_overflow'] = $snapshot['mast_count'] > 1;
    $snapshot['home_overflow'] = $snapshot['home_count'] > 5;

    buttercup_cache_set($cache_key, $snapshot, 600);
    $request_cache[$request_key] = buttercup_homepage_feed_build_from_snapshot($snapshot);

    return $request_cache[$request_key];
}

function buttercup_homepage_feed_summarize_posts($posts)
{
    $summary = [];

    foreach ($posts as $post) {
        $type_obj = get_post_type_object($post->post_type);
        $summary[] = [
            'id' => intval($post->ID),
            'title' => get_the_title($post),
            'type' => $post->post_type,
            'typeLabel' => $type_obj ? $type_obj->labels->singular_name : $post->post_type,
            'date' => mysql_to_rfc3339($post->post_date_gmt ? $post->post_date_gmt : $post->post_date),
            'permalink' => get_permalink($post),
        ];
    }

    return $summary;
}

function buttercup_homepage_feed_get_attachment_data($attachment_id)
{
    $attachment_id = intval($attachment_id);
    if ($attachment_id <= 0) {
        return null;
    }

    $src = wp_get_attachment_image_src($attachment_id, 'full');
    if (!$src || empty($src[0])) {
        return null;
    }

    $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

    return [
        'id' => $attachment_id,
        'url' => $src[0],
        'width' => intval($src[1]),
        'height' => intval($src[2]),
        'alt' => is_string($alt) ? trim($alt) : '',
    ];
}

function buttercup_homepage_feed_get_thumbnail_pair($post_id)
{
    $mobile_id = intval(get_post_meta($post_id, 'buttercup_home_mobile_image_id', true));
    $desktop_id = intval(get_post_meta($post_id, 'buttercup_home_desktop_image_id', true));

    if ($mobile_id <= 0 && $desktop_id <= 0) {
        return null;
    }

    $mobile = $mobile_id > 0 ? buttercup_homepage_feed_get_attachment_data($mobile_id) : null;
    $desktop = $desktop_id > 0 ? buttercup_homepage_feed_get_attachment_data($desktop_id) : null;

    if (!$mobile && !$desktop) {
        return null;
    }

    if (!$mobile) {
        $mobile = $desktop;
    }
    if (!$desktop) {
        $desktop = $mobile;
    }

    return [
        'mobile' => $mobile,
        'desktop' => $desktop,
    ];
}

function buttercup_homepage_feed_render_hero_item($post, $thumbnail_pair, $is_mast = false)
{
    if (!$thumbnail_pair) {
        return '';
    }

    $mobile = $thumbnail_pair['mobile'] ?? null;
    $desktop = $thumbnail_pair['desktop'] ?? null;

    if ((!$mobile || empty($mobile['url'])) && (!$desktop || empty($desktop['url']))) {
        return '';
    }

    $post_id = intval($post->ID);
    $title = get_the_title($post);
    $link = get_permalink($post);

    $alt = '';
    if ($mobile && !empty($mobile['alt'])) {
        $alt = $mobile['alt'];
    } elseif ($desktop && !empty($desktop['alt'])) {
        $alt = $desktop['alt'];
    } else {
        $alt = $title;
    }

    $classes = ['buttercup-homepage-feed__item', 'buttercup-homepage-feed__item--hero'];
    if ($is_mast) {
        $classes[] = 'is-mast';
    }

    $mobile_url = $mobile && !empty($mobile['url']) ? $mobile['url'] : ($desktop['url'] ?? '');
    $desktop_url = $desktop && !empty($desktop['url']) ? $desktop['url'] : $mobile_url;

    $html = '<article class="' . esc_attr(implode(' ', $classes)) . '" data-post-id="' . esc_attr($post_id) . '">';
    $html .= '<a class="buttercup-homepage-feed__hero-link" href="' . esc_url($link) . '" aria-label="' . esc_attr($title) . '">';
    $html .= '<picture class="buttercup-homepage-feed__picture">';
    if ($desktop_url !== '') {
        $html .= '<source media="(min-width: 1024px)" srcset="' . esc_url($desktop_url) . '">';
    }
    $html .= '<img class="buttercup-homepage-feed__hero-image" loading="lazy" decoding="async" src="' . esc_url($mobile_url) . '" alt="' . esc_attr($alt) . '"';
    if (!empty($mobile['width']) && !empty($mobile['height'])) {
        $html .= ' width="' . esc_attr(intval($mobile['width'])) . '" height="' . esc_attr(intval($mobile['height'])) . '"';
    }
    $html .= '>';
    $html .= '</picture>';
    $html .= '</a>';
    $html .= '</article>';

    return $html;
}

function buttercup_homepage_feed_render_split_item($post, $cta_label, $is_mast = false)
{
    $post_id = intval($post->ID);
    $title = get_the_title($post);
    $link = get_permalink($post);
    $thumb_id = get_post_thumbnail_id($post);
    $has_image = $thumb_id ? true : false;

    $classes = ['buttercup-homepage-feed__item', 'buttercup-homepage-feed__item--split'];
    if (!$has_image) {
        $classes[] = 'is-text-only';
    }
    if ($is_mast) {
        $classes[] = 'is-mast';
    }

    $html = '<article class="' . esc_attr(implode(' ', $classes)) . '" data-post-id="' . esc_attr($post_id) . '">';
    if ($has_image) {
        $html .= '<div class="buttercup-homepage-feed__split-media">';
        $html .= '<a class="buttercup-homepage-feed__split-image-link" href="' . esc_url($link) . '" aria-label="' . esc_attr($title) . '">';
        $html .= get_the_post_thumbnail($post, 'large', [
            'class' => 'buttercup-homepage-feed__split-image',
            'loading' => 'lazy',
            'decoding' => 'async',
        ]);
        $html .= '</a>';
        $html .= '</div>';
    }
    $html .= '<div class="buttercup-homepage-feed__split-content">';
    $html .= '<h2 class="buttercup-homepage-feed__title"><a href="' . esc_url($link) . '">' . esc_html($title) . '</a></h2>';
    $html .= '<a class="buttercup-homepage-feed__button" href="' . esc_url($link) . '">' . esc_html($cta_label) . '</a>';
    $html .= '</div>';
    $html .= '</article>';

    return $html;
}

function buttercup_homepage_feed_render_item($post, $cta_label, $is_mast = false)
{
    $thumbnail_pair = buttercup_homepage_feed_get_thumbnail_pair($post->ID);
    if ($thumbnail_pair) {
        $hero_html = buttercup_homepage_feed_render_hero_item($post, $thumbnail_pair, $is_mast);
        if ($hero_html !== '') {
            return $hero_html;
        }
    }

    return buttercup_homepage_feed_render_split_item($post, $cta_label, $is_mast);
}

function buttercup_render_homepage_feed($attributes)
{
    $cta_label = isset($attributes['ctaLabel']) ? trim((string) $attributes['ctaLabel']) : '';
    if ($cta_label === '') {
        $cta_label = __('Read More', 'buttercup');
    }

    $mast_tag_slug = isset($attributes['mastTagSlug']) ? $attributes['mastTagSlug'] : 'mast';
    $home_tag_slug = isset($attributes['homeTagSlug']) ? $attributes['homeTagSlug'] : 'home';
    $render_mode = isset($attributes['renderMode']) ? (string) $attributes['renderMode'] : 'all';
    $home_position = isset($attributes['homePosition']) ? max(1, intval($attributes['homePosition'])) : 1;

    $feed = buttercup_homepage_feed_collect($mast_tag_slug, $home_tag_slug);
    $mast_post = $feed['mast_selected'];
    $home_posts = $feed['home_selected'];

    if ($render_mode === 'mast') {
        if (!$mast_post) {
            return '';
        }
        $mast_html = buttercup_homepage_feed_render_item($mast_post, $cta_label, true);
        if ($mast_html === '') {
            return '';
        }
        $should_detach_mast = buttercup_homepage_feed_should_detach_mast();
        if ($should_detach_mast) {
            $classes = ['wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--mast'];
            $mast_section_html = '<section class="' . esc_attr(implode(' ', $classes)) . '">' . $mast_html . '</section>';
            buttercup_homepage_feed_store_detached_mast_html($mast_section_html);
            return '';
        }
        $classes = ['wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--mast'];
        return '<section class="' . esc_attr(implode(' ', $classes)) . '">' . $mast_html . '</section>';
    }

    if ($render_mode === 'home-item') {
        $index = $home_position - 1;
        if (!isset($home_posts[$index])) {
            return '';
        }
        $item_html = buttercup_homepage_feed_render_item($home_posts[$index], $cta_label, false);
        if ($item_html === '') {
            return '';
        }
        $classes = ['wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--home-item'];
        return '<section class="' . esc_attr(implode(' ', $classes)) . '">' . $item_html . '</section>';
    }

    // Default: render_mode === 'all'
    if (!$mast_post && empty($home_posts)) {
        return '';
    }

    $mast_html = '';
    if ($mast_post) {
        $mast_html = buttercup_homepage_feed_render_item($mast_post, $cta_label, true);
    }

    $home_html = '';
    foreach ($home_posts as $home_post) {
        $home_html .= buttercup_homepage_feed_render_item($home_post, $cta_label, false);
    }

    $should_detach_mast = buttercup_homepage_feed_should_detach_mast();
    if ($should_detach_mast && $mast_html !== '') {
        $classes = ['wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed', 'buttercup-homepage-feed--mast'];
        $mast_section_html = '<section class="' . esc_attr(implode(' ', $classes)) . '">' . $mast_html . '</section>';
        buttercup_homepage_feed_store_detached_mast_html($mast_section_html);
        $mast_html = '';
    }

    if ($mast_html === '' && $home_html === '') {
        return '';
    }

    $classes = ['wp-block-buttercup-homepage-feed', 'buttercup-homepage-feed'];
    if ($should_detach_mast && $mast_post) {
        $classes[] = 'is-mast-detached';
    }

    $html = '<section class="' . esc_attr(implode(' ', $classes)) . '">';
    $html .= $mast_html;
    $html .= $home_html;

    $html .= '</section>';

    return $html;
}

function buttercup_homepage_feed_should_detach_mast()
{
    if (is_admin()) {
        return false;
    }

    if (!is_singular()) {
        return false;
    }

    if (function_exists('wp_is_json_request') && wp_is_json_request()) {
        return false;
    }

    return true;
}

function buttercup_homepage_feed_get_context_post_id()
{
    $post_id = get_the_ID();
    if ($post_id > 0) {
        return intval($post_id);
    }

    $queried = get_queried_object();
    if (is_object($queried) && isset($queried->ID)) {
        return intval($queried->ID);
    }

    return 0;
}

function buttercup_homepage_feed_store_detached_mast_html($mast_section_html)
{
    if (!is_string($mast_section_html) || $mast_section_html === '') {
        return;
    }

    if (!isset($GLOBALS['buttercup_homepage_feed_detached_mast']) || !is_array($GLOBALS['buttercup_homepage_feed_detached_mast'])) {
        $GLOBALS['buttercup_homepage_feed_detached_mast'] = [];
    }

    $post_id = buttercup_homepage_feed_get_context_post_id();
    if (isset($GLOBALS['buttercup_homepage_feed_detached_mast'][$post_id])) {
        return;
    }

    $GLOBALS['buttercup_homepage_feed_detached_mast'][$post_id] = $mast_section_html;
}

function buttercup_homepage_feed_pop_detached_mast_html()
{
    if (!isset($GLOBALS['buttercup_homepage_feed_detached_mast']) || !is_array($GLOBALS['buttercup_homepage_feed_detached_mast'])) {
        return '';
    }

    $post_id = buttercup_homepage_feed_get_context_post_id();
    if ($post_id > 0 && isset($GLOBALS['buttercup_homepage_feed_detached_mast'][$post_id])) {
        $html = $GLOBALS['buttercup_homepage_feed_detached_mast'][$post_id];
        unset($GLOBALS['buttercup_homepage_feed_detached_mast'][$post_id]);
        return $html;
    }

    if (isset($GLOBALS['buttercup_homepage_feed_detached_mast'][0])) {
        $html = $GLOBALS['buttercup_homepage_feed_detached_mast'][0];
        unset($GLOBALS['buttercup_homepage_feed_detached_mast'][0]);
        return $html;
    }

    foreach ($GLOBALS['buttercup_homepage_feed_detached_mast'] as $key => $html) {
        unset($GLOBALS['buttercup_homepage_feed_detached_mast'][$key]);
        return is_string($html) ? $html : '';
    }

    return '';
}

function buttercup_homepage_feed_prepend_detached_mast_to_content($content)
{
    if (!is_string($content) || $content === '') {
        return $content;
    }

    if (!is_singular()) {
        return $content;
    }

    if (!in_the_loop() || !is_main_query()) {
        return $content;
    }

    $mast_html = buttercup_homepage_feed_pop_detached_mast_html();
    if ($mast_html === '') {
        return $content;
    }

    return $mast_html . $content;
}
add_filter('the_content', 'buttercup_homepage_feed_prepend_detached_mast_to_content', 11);

function buttercup_register_homepage_feed_meta()
{
    foreach (['post', 'page'] as $post_type) {
        register_post_meta($post_type, 'buttercup_home_mobile_image_id', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta($post_type, 'buttercup_home_desktop_image_id', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}
add_action('init', 'buttercup_register_homepage_feed_meta', 20);

function buttercup_homepage_feed_image_warnings($attachment_id, $label, $target_ratio, $min_width, $min_height)
{
    $warnings = [];
    $meta = wp_get_attachment_metadata($attachment_id);

    if (!$meta || empty($meta['width']) || empty($meta['height'])) {
        $warnings[] = sprintf(
            __('Could not read dimensions for the %s image.', 'buttercup'),
            $label
        );
        return $warnings;
    }

    $width = intval($meta['width']);
    $height = intval($meta['height']);
    if ($width < $min_width || $height < $min_height) {
        $warnings[] = sprintf(
            __('%1$s image is %2$dx%3$d. Suggested minimum is %4$dx%5$d.', 'buttercup'),
            $label,
            $width,
            $height,
            $min_width,
            $min_height
        );
    }

    $ratio = $height > 0 ? $width / $height : 0;
    if ($ratio > 0) {
        $diff = abs($ratio - $target_ratio) / $target_ratio;
        if ($diff > 0.07) {
            $warnings[] = sprintf(
                __('%1$s image ratio is about %2$s:1. Suggested ratio is %3$s:1.', 'buttercup'),
                $label,
                number_format_i18n($ratio, 2),
                number_format_i18n($target_ratio, 2)
            );
        }
    }

    return $warnings;
}

function buttercup_homepage_feed_render_image_field($post_id, $field_key, $field_label, $suggested_size, $target_ratio, $min_width, $min_height)
{
    $value = intval(get_post_meta($post_id, $field_key, true));
    $preview = $value ? wp_get_attachment_image_src($value, 'medium') : null;
    $warnings = $value ? buttercup_homepage_feed_image_warnings($value, $field_label, $target_ratio, $min_width, $min_height) : [];
    $choose_label = $value ? __('Replace Image', 'buttercup') : __('Select Image', 'buttercup');

    echo '<div class="buttercup-homepage-image-field"';
    echo ' data-target-ratio="' . esc_attr($target_ratio) . '"';
    echo ' data-min-width="' . esc_attr($min_width) . '"';
    echo ' data-min-height="' . esc_attr($min_height) . '"';
    echo ' data-label="' . esc_attr($field_label) . '">';

    echo '<p><strong>' . esc_html($field_label) . '</strong></p>';
    echo '<p class="description">' . esc_html($suggested_size) . '</p>';

    echo '<input type="hidden" class="buttercup-homepage-image-id" name="' . esc_attr($field_key) . '" value="' . esc_attr($value) . '">';
    echo '<div class="buttercup-homepage-image-preview">';
    if ($preview && !empty($preview[0])) {
        echo '<img src="' . esc_url($preview[0]) . '" alt="" style="max-width:100%;height:auto;">';
    } else {
        echo '<em>' . esc_html__('No image selected.', 'buttercup') . '</em>';
    }
    echo '</div>';

    echo '<p class="buttercup-homepage-image-actions">';
    echo '<button type="button" class="button buttercup-homepage-image-select">' . esc_html($choose_label) . '</button> ';
    echo '<button type="button" class="button-link-delete buttercup-homepage-image-clear"';
    if ($value <= 0) {
        echo ' style="display:none;"';
    }
    echo '>' . esc_html__('Clear', 'buttercup') . '</button>';
    echo '</p>';

    echo '<div class="buttercup-homepage-image-dimensions">';
    if (!empty($warnings)) {
        foreach ($warnings as $warning) {
            echo '<p class="description" style="color:#b45309;">' . esc_html($warning) . '</p>';
        }
    }
    echo '</div>';
    echo '</div>';
}

function buttercup_render_homepage_feed_meta_box($post)
{
    $status = buttercup_homepage_feed_collect('mast', 'home');
    $tag_slugs = wp_get_post_terms($post->ID, 'post_tag', ['fields' => 'slugs']);
    if (is_wp_error($tag_slugs)) {
        $tag_slugs = [];
    }

    $has_mast = in_array('mast', $tag_slugs, true);
    $has_home = in_array('home', $tag_slugs, true);

    wp_nonce_field('buttercup_homepage_feed_meta', 'buttercup_homepage_feed_meta_nonce');

    echo '<p class="description">' . esc_html__('Homepage feed rules: up to 1 "mast" item and up to 5 "home" items.', 'buttercup') . '</p>';

    if ($status['mast_overflow']) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('More than one post/page is tagged "mast". Only the newest will render.', 'buttercup') . '</p></div>';
    }
    if ($status['home_overflow']) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('More than five posts/pages are tagged "home". Only the five newest will render.', 'buttercup') . '</p></div>';
    }
    if (!empty($status['dual_posts'])) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('Some items are tagged with both "mast" and "home". Dual-tagged items are treated as mast only.', 'buttercup') . '</p></div>';
    }
    if ($has_mast && $has_home) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('This item currently has both "mast" and "home". It will only render in the mast position.', 'buttercup') . '</p></div>';
    }

    buttercup_homepage_feed_render_image_field(
        $post->ID,
        'buttercup_home_mobile_image_id',
        __('Mobile Thumbnail (5:3)', 'buttercup'),
        __('Suggested: 2000 x 1200 (5:3)', 'buttercup'),
        (5 / 3),
        2000,
        1200
    );

    echo '<hr style="margin:16px 0;">';

    buttercup_homepage_feed_render_image_field(
        $post->ID,
        'buttercup_home_desktop_image_id',
        __('Desktop Thumbnail (2.74:1)', 'buttercup'),
        __('Suggested: 2880 x 1050 (2.74:1)', 'buttercup'),
        2.74,
        2880,
        1050
    );
}

function buttercup_add_homepage_feed_meta_box()
{
    add_meta_box(
        'buttercup-homepage-feed-images',
        __('Buttercup Homepage Images', 'buttercup'),
        'buttercup_render_homepage_feed_meta_box',
        ['post', 'page'],
        'side'
    );
}
add_action('add_meta_boxes', 'buttercup_add_homepage_feed_meta_box');

function buttercup_save_homepage_feed_meta_box($post_id)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    if (!in_array($post_type, ['post', 'page'], true)) {
        return;
    }

    if (!isset($_POST['buttercup_homepage_feed_meta_nonce'])) {
        return;
    }
    $nonce = sanitize_text_field(wp_unslash($_POST['buttercup_homepage_feed_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'buttercup_homepage_feed_meta')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $mobile_id = isset($_POST['buttercup_home_mobile_image_id']) ? absint(wp_unslash($_POST['buttercup_home_mobile_image_id'])) : 0;
    $desktop_id = isset($_POST['buttercup_home_desktop_image_id']) ? absint(wp_unslash($_POST['buttercup_home_desktop_image_id'])) : 0;

    if ($mobile_id > 0) {
        update_post_meta($post_id, 'buttercup_home_mobile_image_id', $mobile_id);
    } else {
        delete_post_meta($post_id, 'buttercup_home_mobile_image_id');
    }

    if ($desktop_id > 0) {
        update_post_meta($post_id, 'buttercup_home_desktop_image_id', $desktop_id);
    } else {
        delete_post_meta($post_id, 'buttercup_home_desktop_image_id');
    }
}
add_action('save_post', 'buttercup_save_homepage_feed_meta_box');

function buttercup_enqueue_homepage_feed_meta_assets($hook)
{
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['post', 'page'], true)) {
        return;
    }

    wp_enqueue_media();

    $script_path = BUTTERCUP_PLUGIN_DIR . '/assets/homepage-meta.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'buttercup-homepage-meta',
            plugins_url('assets/homepage-meta.js', BUTTERCUP_PLUGIN_FILE),
            ['jquery'],
            filemtime($script_path),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'buttercup_enqueue_homepage_feed_meta_assets');
