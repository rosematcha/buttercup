<?php
/**
 * Plugin Name:       Buttercup
 * Description:       Custom blocks for Reese's sites.
 * Version:           1.0.0
 * Author:            Reese Lundquist
 * Text Domain:       buttercup
 */

if (!defined('ABSPATH')) {
    exit;
}

function buttercup_build_image_candidates($attachment_id) {
    $meta = wp_get_attachment_metadata($attachment_id);
    if (!$meta || empty($meta['width']) || empty($meta['height'])) {
        return [];
    }

    $upload_dir = wp_get_upload_dir();
    $base_url = isset($upload_dir['baseurl']) ? $upload_dir['baseurl'] : '';
    $base_url = $base_url !== '' ? trailingslashit($base_url) : '';
    $file = isset($meta['file']) ? $meta['file'] : '';
    $file_dir = $file ? trailingslashit(dirname($file)) : '';

    $candidates = [];
    $full_url = wp_get_attachment_url($attachment_id);
    if ($full_url) {
        $candidates[] = [
            'url' => $full_url,
            'width' => intval($meta['width']),
            'height' => intval($meta['height']),
            'crop' => false,
        ];
    }

    if (!empty($meta['sizes']) && $base_url !== '' && $file_dir !== '') {
        foreach ($meta['sizes'] as $size) {
            if (empty($size['file']) || empty($size['width']) || empty($size['height'])) {
                continue;
            }
            $candidates[] = [
                'url' => $base_url . $file_dir . $size['file'],
                'width' => intval($size['width']),
                'height' => intval($size['height']),
                'crop' => !empty($size['crop']),
            ];
        }
    }

    return $candidates;
}

function buttercup_filter_uncropped_candidates($candidates, $orig_ratio) {
    $filtered = [];
    $ratio_tolerance = 0.08;
    $orig_is_square = abs($orig_ratio - 1) <= 0.05;

    foreach ($candidates as $candidate) {
        if (empty($candidate['width']) || empty($candidate['height'])) {
            continue;
        }
        if (!empty($candidate['crop'])) {
            continue;
        }
        if ($orig_ratio > 0) {
            $ratio = $candidate['width'] / $candidate['height'];
            if (!$orig_is_square) {
                if (abs($ratio - $orig_ratio) / $orig_ratio > $ratio_tolerance) {
                    continue;
                }
            }
        }
        $filtered[] = $candidate;
    }

    return $filtered;
}

function buttercup_pick_best_candidate($candidates, $target_width) {
    if (empty($candidates)) {
        return null;
    }
    usort($candidates, function ($a, $b) {
        return $a['width'] <=> $b['width'];
    });
    foreach ($candidates as $candidate) {
        if ($candidate['width'] >= $target_width) {
            return $candidate;
        }
    }
    return $candidates[count($candidates) - 1];
}

function buttercup_build_srcset($candidates) {
    if (empty($candidates)) {
        return '';
    }
    usort($candidates, function ($a, $b) {
        return $a['width'] <=> $b['width'];
    });
    $seen = [];
    $parts = [];
    foreach ($candidates as $candidate) {
        $url = $candidate['url'];
        if (!$url || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $parts[] = esc_url($url) . ' ' . intval($candidate['width']) . 'w';
    }
    return implode(', ', $parts);
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

function buttercup_homepage_feed_query_posts_by_tag($tag_slug)
{
    if ($tag_slug === '') {
        return [];
    }

    return get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'ignore_sticky_posts' => true,
        'tax_query' => [
            [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => [$tag_slug],
            ],
        ],
    ]);
}

function buttercup_homepage_feed_collect($mast_tag_slug = 'mast', $home_tag_slug = 'home')
{
    $mast_tag_slug = buttercup_homepage_feed_sanitize_tag_slug($mast_tag_slug, 'mast');
    $home_tag_slug = buttercup_homepage_feed_sanitize_tag_slug($home_tag_slug, 'home');

    $mast_posts = buttercup_homepage_feed_query_posts_by_tag($mast_tag_slug);
    $home_posts = buttercup_homepage_feed_query_posts_by_tag($home_tag_slug);

    $mast_ids = array_map('intval', wp_list_pluck($mast_posts, 'ID'));
    $home_ids = array_map('intval', wp_list_pluck($home_posts, 'ID'));
    $dual_ids = array_values(array_intersect($mast_ids, $home_ids));

    $by_id = [];
    foreach (array_merge($mast_posts, $home_posts) as $entry) {
        $by_id[intval($entry->ID)] = $entry;
    }

    $dual_posts = [];
    foreach ($dual_ids as $dual_id) {
        $dual_key = intval($dual_id);
        if (isset($by_id[$dual_key])) {
            $dual_posts[] = $by_id[$dual_key];
        }
    }

    $mast_selected = !empty($mast_posts) ? $mast_posts[0] : null;
    $exclude_ids = $dual_ids;
    if ($mast_selected) {
        $exclude_ids[] = intval($mast_selected->ID);
    }
    $exclude_ids = array_values(array_unique(array_map('intval', $exclude_ids)));

    $home_filtered = [];
    foreach ($home_posts as $home_post) {
        if (in_array(intval($home_post->ID), $exclude_ids, true)) {
            continue;
        }
        $home_filtered[] = $home_post;
    }

    $home_selected = array_slice($home_filtered, 0, 5);

    return [
        'mast_tag_slug' => $mast_tag_slug,
        'home_tag_slug' => $home_tag_slug,
        'mast_posts' => $mast_posts,
        'home_posts' => $home_posts,
        'mast_selected' => $mast_selected,
        'home_selected' => $home_selected,
        'dual_posts' => $dual_posts,
        'mast_count' => count($mast_posts),
        'home_count' => count($home_posts),
        'mast_overflow' => count($mast_posts) > 1,
        'home_overflow' => count($home_posts) > 5,
    ];
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

    $feed = buttercup_homepage_feed_collect($mast_tag_slug, $home_tag_slug);
    $mast_post = $feed['mast_selected'];
    $home_posts = $feed['home_selected'];

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

    $script_path = __DIR__ . '/assets/homepage-meta.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'buttercup-homepage-meta',
            plugins_url('assets/homepage-meta.js', __FILE__),
            ['jquery'],
            filemtime($script_path),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'buttercup_enqueue_homepage_feed_meta_assets');

function buttercup_tag_showcase_sanitize_css_color($value)
{
    $color = trim((string) $value);
    if ($color === '') {
        return '';
    }

    if (strpos($color, ';') !== false || strpos($color, '{') !== false || strpos($color, '}') !== false) {
        return '';
    }

    if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $color)) {
        return $color;
    }

    $hex = sanitize_hex_color($color);
    if ($hex) {
        return $hex;
    }

    if (preg_match('/^rgba?\([0-9.,%\s]+\)$/', $color)) {
        return $color;
    }

    return '';
}

function buttercup_tag_showcase_get_valid_post_types()
{
    $valid = [];
    $objects = get_post_types(['public' => true], 'objects');

    foreach ($objects as $type => $obj) {
        if (!$obj || empty($obj->show_in_rest)) {
            continue;
        }
        if (in_array($type, ['attachment', 'revision', 'nav_menu_item', 'wp_block'], true)) {
            continue;
        }
        if (!is_array($obj->taxonomies) || !in_array('post_tag', $obj->taxonomies, true)) {
            continue;
        }
        $valid[] = $type;
    }

    return $valid;
}

function buttercup_tag_showcase_get_current_post_id()
{
    $post_id = get_the_ID();
    if ($post_id > 0) {
        return intval($post_id);
    }

    if (isset($_GET['post_id'])) {
        $get_post_id = absint(wp_unslash($_GET['post_id']));
        if ($get_post_id > 0) {
            return $get_post_id;
        }
    }

    if (isset($_GET['post'])) {
        $get_post = absint(wp_unslash($_GET['post']));
        if ($get_post > 0) {
            return $get_post;
        }
    }

    $queried = get_queried_object();
    if (is_object($queried) && isset($queried->ID)) {
        return intval($queried->ID);
    }

    return 0;
}

function buttercup_tag_showcase_sanitize_attributes($attributes)
{
    $defaults = [
        'tagSlugs' => [],
        'tagMatch' => 'any',
        'enableMultiTag' => false,
        'postTypes' => ['post', 'page'],
        'orderBy' => 'date',
        'order' => 'desc',
        'maxItems' => 12,
        'offset' => 0,
        'excludeCurrentPost' => false,
        'showThumbnail' => true,
        'showTitle' => true,
        'showSnippet' => true,
        'showButton' => true,
        'showType' => false,
        'showDate' => false,
        'snippetWords' => 20,
        'buttonLabel' => __('Read More', 'buttercup'),
        'clickMode' => 'card-cta',
        'openInNewTab' => false,
        'buttonStyle' => 'solid',
        'minWidthDesktop' => 260,
        'minWidthTablet' => 220,
        'minWidthMobile' => 160,
        'maxColsDesktop' => 4,
        'maxColsTablet' => 3,
        'maxColsMobile' => 2,
        'columnGap' => 24,
        'rowGap' => 24,
        'textAlign' => 'left',
        'imageAspectRatio' => '16/9',
        'cardPadding' => 20,
        'cardRadius' => 12,
        'cardBackground' => '',
    ];

    $attrs = wp_parse_args(is_array($attributes) ? $attributes : [], $defaults);

    $tag_slugs = [];
    if (is_array($attrs['tagSlugs'])) {
        foreach ($attrs['tagSlugs'] as $tag_slug) {
            $clean = sanitize_title((string) $tag_slug);
            if ($clean !== '') {
                $tag_slugs[] = $clean;
            }
        }
    }
    $tag_slugs = array_values(array_unique($tag_slugs));

    $enable_multi_tag = !empty($attrs['enableMultiTag']);
    if (!$enable_multi_tag && count($tag_slugs) > 1) {
        $tag_slugs = [reset($tag_slugs)];
    }

    $tag_match = strtolower((string) $attrs['tagMatch']);
    if (!in_array($tag_match, ['any', 'all'], true)) {
        $tag_match = 'any';
    }
    if (!$enable_multi_tag) {
        $tag_match = 'any';
    }

    $valid_post_types = buttercup_tag_showcase_get_valid_post_types();
    $post_types = [];
    if (is_array($attrs['postTypes'])) {
        foreach ($attrs['postTypes'] as $post_type) {
            $post_type = sanitize_key((string) $post_type);
            if (in_array($post_type, $valid_post_types, true)) {
                $post_types[] = $post_type;
            }
        }
    }
    $post_types = array_values(array_unique($post_types));
    if (empty($post_types)) {
        $fallback = array_values(array_intersect(['post', 'page'], $valid_post_types));
        $post_types = !empty($fallback) ? $fallback : $valid_post_types;
    }

    $order_by = strtolower((string) $attrs['orderBy']);
    if (!in_array($order_by, ['date', 'title', 'modified'], true)) {
        $order_by = 'date';
    }

    $order = strtolower((string) $attrs['order']);
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'desc';
    }

    $click_mode = strtolower((string) $attrs['clickMode']);
    if (!in_array($click_mode, ['card-cta', 'cta-only', 'card-only'], true)) {
        $click_mode = 'card-cta';
    }

    $button_style = strtolower((string) $attrs['buttonStyle']);
    if (!in_array($button_style, ['solid', 'outline', 'text'], true)) {
        $button_style = 'solid';
    }

    $text_align = strtolower((string) $attrs['textAlign']);
    if (!in_array($text_align, ['left', 'center', 'right'], true)) {
        $text_align = 'left';
    }

    $image_aspect_ratio = strtolower((string) $attrs['imageAspectRatio']);
    if (!in_array($image_aspect_ratio, ['16/9', '4/3', '3/2', '1/1', '2/3', 'auto'], true)) {
        $image_aspect_ratio = '16/9';
    }

    $button_label = trim((string) $attrs['buttonLabel']);
    if ($button_label === '') {
        $button_label = __('Read More', 'buttercup');
    }

    return [
        'tag_slugs' => $tag_slugs,
        'tag_match' => $tag_match,
        'enable_multi_tag' => $enable_multi_tag,
        'post_types' => $post_types,
        'order_by' => $order_by,
        'order' => $order,
        'max_items' => max(1, min(60, intval($attrs['maxItems']))),
        'offset' => max(0, min(50, intval($attrs['offset']))),
        'exclude_current_post' => !empty($attrs['excludeCurrentPost']),
        'show_thumbnail' => !empty($attrs['showThumbnail']),
        'show_title' => !empty($attrs['showTitle']),
        'show_snippet' => !empty($attrs['showSnippet']),
        'show_button' => !empty($attrs['showButton']),
        'show_type' => !empty($attrs['showType']),
        'show_date' => !empty($attrs['showDate']),
        'snippet_words' => max(5, min(80, intval($attrs['snippetWords']))),
        'button_label' => $button_label,
        'click_mode' => $click_mode,
        'open_in_new_tab' => !empty($attrs['openInNewTab']),
        'button_style' => $button_style,
        'min_width_desktop' => max(140, min(600, intval($attrs['minWidthDesktop']))),
        'min_width_tablet' => max(120, min(420, intval($attrs['minWidthTablet']))),
        'min_width_mobile' => max(100, min(320, intval($attrs['minWidthMobile']))),
        'max_cols_desktop' => max(1, min(8, intval($attrs['maxColsDesktop']))),
        'max_cols_tablet' => max(1, min(6, intval($attrs['maxColsTablet']))),
        'max_cols_mobile' => max(1, min(4, intval($attrs['maxColsMobile']))),
        'column_gap' => max(0, min(80, intval($attrs['columnGap']))),
        'row_gap' => max(0, min(100, intval($attrs['rowGap']))),
        'text_align' => $text_align,
        'image_aspect_ratio' => $image_aspect_ratio,
        'card_padding' => max(0, min(64, intval($attrs['cardPadding']))),
        'card_radius' => max(0, min(48, intval($attrs['cardRadius']))),
        'card_background' => buttercup_tag_showcase_sanitize_css_color($attrs['cardBackground']),
    ];
}

function buttercup_tag_showcase_get_query_args($attrs, $for_status = false)
{
    $tax_query = [];
    if (!empty($attrs['tag_slugs'])) {
        if ($attrs['tag_match'] === 'all' && count($attrs['tag_slugs']) > 1) {
            $tax_query['relation'] = 'AND';
            foreach ($attrs['tag_slugs'] as $tag_slug) {
                $tax_query[] = [
                    'taxonomy' => 'post_tag',
                    'field' => 'slug',
                    'terms' => [$tag_slug],
                    'operator' => 'IN',
                ];
            }
        } else {
            $tax_query[] = [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $attrs['tag_slugs'],
                'operator' => 'IN',
            ];
        }
    }

    $query_args = [
        'post_type' => $attrs['post_types'],
        'post_status' => 'publish',
        'ignore_sticky_posts' => true,
        'orderby' => $attrs['order_by'],
        'order' => strtoupper($attrs['order']),
        'offset' => $attrs['offset'],
        'posts_per_page' => $for_status ? 1 : $attrs['max_items'],
        'no_found_rows' => !$for_status,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    if (!empty($tax_query)) {
        $query_args['tax_query'] = $tax_query;
    }

    if ($attrs['exclude_current_post']) {
        $current_post_id = buttercup_tag_showcase_get_current_post_id();
        if ($current_post_id > 0) {
            $query_args['post__not_in'] = [$current_post_id];
        }
    }

    return $query_args;
}

function buttercup_tag_showcase_get_snippet($post, $snippet_words)
{
    $excerpt = get_the_excerpt($post);
    $excerpt = is_string($excerpt) ? trim($excerpt) : '';
    if ($excerpt !== '') {
        return wp_trim_words(wp_strip_all_tags($excerpt), $snippet_words, '...');
    }

    $raw_content = isset($post->post_content) ? (string) $post->post_content : '';
    if ($raw_content === '') {
        return '';
    }

    $content = wp_strip_all_tags(strip_shortcodes($raw_content));
    return wp_trim_words($content, $snippet_words, '...');
}

function buttercup_tag_showcase_build_style_attr($attrs)
{
    $aspect_ratio = $attrs['image_aspect_ratio'] === 'auto'
        ? 'auto'
        : str_replace('/', ' / ', $attrs['image_aspect_ratio']);

    $card_background = $attrs['card_background'] !== '' ? $attrs['card_background'] : 'transparent';

    $styles = [
        '--buttercup-ts-min-width:' . $attrs['min_width_desktop'] . 'px',
        '--buttercup-ts-max-cols:' . $attrs['max_cols_desktop'],
        '--buttercup-ts-min-width-tablet:' . $attrs['min_width_tablet'] . 'px',
        '--buttercup-ts-max-cols-tablet:' . $attrs['max_cols_tablet'],
        '--buttercup-ts-min-width-mobile:' . $attrs['min_width_mobile'] . 'px',
        '--buttercup-ts-max-cols-mobile:' . $attrs['max_cols_mobile'],
        '--buttercup-ts-col-gap:' . $attrs['column_gap'] . 'px',
        '--buttercup-ts-row-gap:' . $attrs['row_gap'] . 'px',
        '--buttercup-ts-card-padding:' . $attrs['card_padding'] . 'px',
        '--buttercup-ts-card-radius:' . $attrs['card_radius'] . 'px',
        '--buttercup-ts-card-bg:' . $card_background,
        '--buttercup-ts-aspect-ratio:' . $aspect_ratio,
    ];

    return implode(';', $styles) . ';';
}

function buttercup_tag_showcase_build_target_attrs($open_in_new_tab)
{
    if (!$open_in_new_tab) {
        return '';
    }

    return ' target="_blank" rel="noopener noreferrer"';
}

function buttercup_render_tag_showcase($attributes)
{
    $attrs = buttercup_tag_showcase_sanitize_attributes($attributes);
    if (empty($attrs['tag_slugs']) || empty($attrs['post_types'])) {
        return '';
    }

    $query = new WP_Query(buttercup_tag_showcase_get_query_args($attrs, false));
    if (!$query->have_posts()) {
        wp_reset_postdata();
        return '';
    }

    $wrapper_classes = [
        'wp-block-buttercup-tag-showcase',
        'buttercup-tag-showcase',
        'buttercup-tag-showcase--align-' . $attrs['text_align'],
    ];
    $style_attr = buttercup_tag_showcase_build_style_attr($attrs);
    $target_attrs = buttercup_tag_showcase_build_target_attrs($attrs['open_in_new_tab']);
    $button_class = 'buttercup-tag-showcase__button--' . $attrs['button_style'];
    $button_visual_class = 'buttercup-tag-showcase__button-visual--' . $attrs['button_style'];

    $html = '<section class="' . esc_attr(implode(' ', $wrapper_classes)) . '" style="' . esc_attr($style_attr) . '">';
    $html .= '<div class="buttercup-tag-showcase__grid">';

    while ($query->have_posts()) {
        $query->the_post();
        $post = get_post();
        if (!$post) {
            continue;
        }

        $post_id = intval($post->ID);
        $title = get_the_title($post);
        if (trim($title) === '') {
            $title = __('(Untitled)', 'buttercup');
        }

        $link = get_permalink($post);
        $has_link = is_string($link) && $link !== '';
        $card_classes = ['buttercup-tag-showcase__card', 'is-click-' . $attrs['click_mode']];

        $html .= '<article class="' . esc_attr(implode(' ', $card_classes)) . '" data-post-id="' . esc_attr($post_id) . '">';

        if ($has_link && $attrs['click_mode'] !== 'cta-only') {
            $overlay_label = sprintf(__('View %s', 'buttercup'), $title);
            $html .= '<a class="buttercup-tag-showcase__card-link" href="' . esc_url($link) . '" aria-label="' . esc_attr($overlay_label) . '"' . $target_attrs . '>';
            $html .= esc_html($overlay_label);
            $html .= '</a>';
        }

        if ($attrs['show_thumbnail'] && has_post_thumbnail($post)) {
            $thumb_classes = ['buttercup-tag-showcase__thumb'];
            if ($attrs['image_aspect_ratio'] === 'auto') {
                $thumb_classes[] = 'is-auto';
            }
            $html .= '<figure class="' . esc_attr(implode(' ', $thumb_classes)) . '">';
            $html .= get_the_post_thumbnail($post, 'large', [
                'loading' => 'lazy',
                'decoding' => 'async',
            ]);
            $html .= '</figure>';
        }

        $html .= '<div class="buttercup-tag-showcase__content">';

        if ($attrs['show_type'] || $attrs['show_date']) {
            $html .= '<div class="buttercup-tag-showcase__meta">';

            if ($attrs['show_type']) {
                $type_obj = get_post_type_object($post->post_type);
                $type_label = $type_obj ? $type_obj->labels->singular_name : ucfirst($post->post_type);
                $html .= '<span class="buttercup-tag-showcase__meta-type">' . esc_html($type_label) . '</span>';
            }

            if ($attrs['show_date']) {
                $date_value = get_the_date('', $post);
                $html .= '<span class="buttercup-tag-showcase__meta-date">' . esc_html($date_value) . '</span>';
            }

            $html .= '</div>';
        }

        if ($attrs['show_title']) {
            $html .= '<h3 class="buttercup-tag-showcase__title">' . esc_html($title) . '</h3>';
        }

        if ($attrs['show_snippet']) {
            $snippet = buttercup_tag_showcase_get_snippet($post, $attrs['snippet_words']);
            if ($snippet !== '') {
                $html .= '<p class="buttercup-tag-showcase__snippet">' . esc_html($snippet) . '</p>';
            }
        }

        if ($attrs['show_button']) {
            if ($attrs['click_mode'] === 'card-only' || !$has_link) {
                $html .= '<span class="buttercup-tag-showcase__button-visual ' . esc_attr($button_visual_class) . '">' . esc_html($attrs['button_label']) . '</span>';
            } else {
                $html .= '<a class="buttercup-tag-showcase__button ' . esc_attr($button_class) . '" href="' . esc_url($link) . '"' . $target_attrs . '>';
                $html .= esc_html($attrs['button_label']);
                $html .= '</a>';
            }
        }

        $html .= '</div>';
        $html .= '</article>';
    }

    wp_reset_postdata();

    $html .= '</div>';
    $html .= '</section>';

    return $html;
}

function buttercup_rest_tag_showcase_status($request)
{
    $raw_tag_slugs = isset($request['tagSlugs']) ? explode(',', (string) $request['tagSlugs']) : [];
    $raw_post_types = isset($request['postTypes']) ? explode(',', (string) $request['postTypes']) : [];

    $attrs = buttercup_tag_showcase_sanitize_attributes([
        'tagSlugs' => $raw_tag_slugs,
        'tagMatch' => isset($request['tagMatch']) ? $request['tagMatch'] : 'any',
        'enableMultiTag' => count($raw_tag_slugs) > 1,
        'postTypes' => $raw_post_types,
        'excludeCurrentPost' => !empty($request['excludeCurrentPost']),
        'offset' => isset($request['offset']) ? intval($request['offset']) : 0,
        'maxItems' => isset($request['maxItems']) ? intval($request['maxItems']) : 12,
    ]);

    if (empty($attrs['tag_slugs']) || empty($attrs['post_types'])) {
        return rest_ensure_response([
            'count' => 0,
            'hasResults' => false,
        ]);
    }

    $query = new WP_Query(buttercup_tag_showcase_get_query_args($attrs, true));
    $count = intval($query->found_posts);
    wp_reset_postdata();

    return rest_ensure_response([
        'count' => $count,
        'hasResults' => $count > 0,
    ]);
}

function buttercup_blocks_init()
{
    register_block_type(__DIR__ . '/build/team');
    register_block_type(__DIR__ . '/build/team-member');
    register_block_type(__DIR__ . '/build/row-layout');
    register_block_type(__DIR__ . '/build/row-column');
    register_block_type(__DIR__ . '/build/homepage-feed', [
        'render_callback' => 'buttercup_render_homepage_feed',
    ]);
    register_block_type(__DIR__ . '/build/tag-showcase', [
        'render_callback' => 'buttercup_render_tag_showcase',
    ]);
}
add_action('init', 'buttercup_blocks_init');

function buttercup_enqueue_dashicons()
{
    if (!is_admin()) {
        wp_enqueue_style('dashicons');
    }
}
add_action('enqueue_block_assets', 'buttercup_enqueue_dashicons');

function buttercup_register_member_routes()
{
    add_rewrite_tag('%buttercup_member%', '([^&]+)');
    $bases = get_option('buttercup_member_bases', []);
    if (!is_array($bases)) {
        return;
    }

    foreach ($bases as $base) {
        $path = isset($base['path']) ? trim($base['path'], '/') : '';
        $page_id = isset($base['id']) ? intval($base['id']) : 0;
        if ($path === '' || $page_id <= 0) {
            continue;
        }

        $pattern = '^' . preg_quote($path, '#') . '/([^/]+)/?$';
        add_rewrite_rule($pattern, 'index.php?page_id=' . $page_id . '&buttercup_member=$matches[1]', 'top');
    }
}
add_action('init', 'buttercup_register_member_routes');

function buttercup_member_bases_changed($next, $prev)
{
    if (!is_array($next) || !is_array($prev)) {
        return true;
    }

    $normalize = function ($bases) {
        usort($bases, function ($a, $b) {
            return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
        });
        return $bases;
    };

    return $normalize($next) !== $normalize($prev);
}

function buttercup_blocks_have_member_pages($blocks)
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (($block['blockName'] ?? '') === 'core/block') {
            $ref = intval($block['attrs']['ref'] ?? 0);
            if ($ref > 0) {
                $ref_post = get_post($ref);
                if ($ref_post && $ref_post->post_type === 'wp_block') {
                    if (buttercup_blocks_have_member_pages(parse_blocks($ref_post->post_content))) {
                        return true;
                    }
                }
            }
        }

        if (($block['blockName'] ?? '') === 'buttercup/team') {
            $attrs = $block['attrs'] ?? [];
            $enabled = !array_key_exists('enableMemberPages', $attrs) || $attrs['enableMemberPages'];
            if ($enabled) {
                return true;
            }
        }

        if (!empty($block['innerBlocks']) && buttercup_blocks_have_member_pages($block['innerBlocks'])) {
            return true;
        }
    }

    return false;
}

function buttercup_refresh_member_bases()
{
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'suppress_filters' => false,
    ]);

    $bases = [];
    foreach ($pages as $page) {
        if (strpos($page->post_content, 'buttercup/team') === false) {
            continue;
        }

        if (!buttercup_blocks_have_member_pages(parse_blocks($page->post_content))) {
            continue;
        }

        $path = get_page_uri($page->ID);
        if ($path === '') {
            continue;
        }

        $bases[] = [
            'id' => $page->ID,
            'path' => $path,
        ];
    }

    $prev = get_option('buttercup_member_bases', []);
    if (buttercup_member_bases_changed($bases, $prev)) {
        update_option('buttercup_member_bases', $bases, false);
        flush_rewrite_rules();
    }
}

function buttercup_update_member_base_for_page($post_id)
{
    $page = get_post($post_id);
    $bases = get_option('buttercup_member_bases', []);
    if (!is_array($bases)) {
        $bases = [];
    }

    $keep = [];
    foreach ($bases as $base) {
        if (intval($base['id'] ?? 0) !== intval($post_id)) {
            $keep[] = $base;
        }
    }

    $should_add = false;
    $path = '';

    if ($page && $page->post_type === 'page' && $page->post_status === 'publish') {
        if (strpos($page->post_content, 'buttercup/team') !== false) {
            if (buttercup_blocks_have_member_pages(parse_blocks($page->post_content))) {
                $path = get_page_uri($page->ID);
                if ($path !== '') {
                    $should_add = true;
                }
            }
        }
    }

    if ($should_add) {
        $keep[] = [
            'id' => $page->ID,
            'path' => $path,
        ];
    }

    if (buttercup_member_bases_changed($keep, $bases)) {
        update_option('buttercup_member_bases', $keep, false);
        flush_rewrite_rules();
    }
}

function buttercup_handle_page_save($post_id)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'page') {
        return;
    }

    buttercup_update_member_base_for_page($post_id);
}
add_action('save_post_page', 'buttercup_handle_page_save');
add_action('deleted_post', 'buttercup_update_member_base_for_page');

function buttercup_admin_refresh_member_bases()
{
    if (!is_admin()) {
        return;
    }

    if (get_option('buttercup_member_bases', null) === null) {
        buttercup_refresh_member_bases();
    }
}
add_action('admin_init', 'buttercup_admin_refresh_member_bases');

function buttercup_get_request_path()
{
    if (!isset($_SERVER['REQUEST_URI'])) {
        return '';
    }

    $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (!$request_path) {
        return '';
    }

    $request_path = trim($request_path, '/');
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path !== '' && strpos($request_path, $home_path) === 0) {
        $request_path = trim(substr($request_path, strlen($home_path)), '/');
    }

    return $request_path;
}

function buttercup_page_has_member_pages($page_id)
{
    $page = get_post($page_id);
    if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
        return false;
    }

    if (strpos($page->post_content, 'buttercup/team') === false) {
        return false;
    }

    return buttercup_blocks_have_member_pages(parse_blocks($page->post_content));
}

function buttercup_match_member_request($request_path)
{
    if ($request_path === '') {
        return null;
    }

    $bases = get_option('buttercup_member_bases', []);
    if (is_array($bases)) {
        foreach ($bases as $base) {
            $path = isset($base['path']) ? trim($base['path'], '/') : '';
            $page_id = isset($base['id']) ? intval($base['id']) : 0;
            if ($path === '' || $page_id <= 0) {
                continue;
            }

            if (strpos($request_path, $path . '/') !== 0) {
                continue;
            }

            $slug = trim(substr($request_path, strlen($path) + 1), '/');
            if ($slug === '' || strpos($slug, '/') !== false) {
                continue;
            }

            return [
                'page_id' => $page_id,
                'slug' => $slug,
            ];
        }
    }

    $parts = explode('/', $request_path);
    if (count($parts) < 2) {
        return null;
    }

    $slug = array_pop($parts);
    $base_path = implode('/', $parts);
    if ($base_path === '') {
        return null;
    }

    $page = get_page_by_path($base_path, OBJECT, 'page');
    if (!$page) {
        return null;
    }

    if (!buttercup_page_has_member_pages($page->ID)) {
        return null;
    }

    return [
        'page_id' => $page->ID,
        'slug' => $slug,
    ];
}

function buttercup_parse_member_request($wp)
{
    if (!empty($wp->query_vars['buttercup_member'])) {
        return;
    }

    $request_path = buttercup_get_request_path();
    $match = buttercup_match_member_request($request_path);
    if (!$match) {
        return;
    }

    $wp->query_vars['page_id'] = $match['page_id'];
    $wp->query_vars['pagename'] = get_page_uri($match['page_id']);
    $wp->query_vars['buttercup_member'] = $match['slug'];
}
add_action('parse_request', 'buttercup_parse_member_request', 5);

function buttercup_disable_member_canonical($redirect_url, $requested_url)
{
    if (get_query_var('buttercup_member')) {
        return false;
    }

    $request_path = parse_url($requested_url, PHP_URL_PATH);
    if (!$request_path) {
        return $redirect_url;
    }

    $request_path = trim($request_path, '/');
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path !== '' && strpos($request_path, $home_path) === 0) {
        $request_path = trim(substr($request_path, strlen($home_path)), '/');
    }

    if (buttercup_match_member_request($request_path)) {
        return false;
    }

    return $redirect_url;
}
add_filter('redirect_canonical', 'buttercup_disable_member_canonical', 10, 2);

function buttercup_activation_refresh()
{
    buttercup_refresh_member_bases();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'buttercup_activation_refresh');

function buttercup_register_query_vars($vars)
{
    $vars[] = 'buttercup_member';
    return $vars;
}
add_filter('query_vars', 'buttercup_register_query_vars');

function buttercup_clean_name($name)
{
    $clean = wp_strip_all_tags($name);
    $clean = trim(preg_replace('/\s+/', ' ', $clean));
    return $clean;
}

function buttercup_build_member_slugs_for_page($entries)
{
    $counts = [];
    $prepared = [];
    $used = [];

    foreach ($entries as $index => $entry) {
        $member = $entry['member'] ?? [];
        $name = buttercup_clean_name($member['name'] ?? '');
        $tokens = $name === '' ? [] : preg_split('/\s+/', $name);
        $first = $tokens[0] ?? '';
        $last = count($tokens) > 1 ? $tokens[count($tokens) - 1] : '';
        $key = strtolower($first);
        if ($key !== '') {
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $prepared[$index] = [
            'first' => $first,
            'last' => $last,
        ];

        $existing = isset($member['memberSlug']) ? sanitize_title($member['memberSlug']) : '';
        if ($existing !== '') {
            $entries[$index]['slug'] = $existing;
            $used[$existing] = true;
        }
    }

    foreach ($entries as $index => $entry) {
        if (!empty($entries[$index]['slug'])) {
            continue;
        }

        $first = $prepared[$index]['first'];
        $last = $prepared[$index]['last'];
        $base = '';

        if ($first !== '') {
            $needs_last = ($counts[strtolower($first)] ?? 0) > 1;
            $base = $needs_last && $last !== '' ? $first . ' ' . $last : $first;
        }

        $slug = $base !== '' ? sanitize_title($base) : '';
        if ($slug !== '') {
            $unique = $slug;
            $i = 2;
            while (isset($used[$unique])) {
                $unique = $slug . '-' . $i;
                $i += 1;
            }
            $used[$unique] = true;
            $slug = $unique;
        }

        $entries[$index]['slug'] = $slug;
    }

    return $entries;
}

function buttercup_collect_member_entries($blocks, &$entries)
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (($block['blockName'] ?? '') === 'core/block') {
            $ref = intval($block['attrs']['ref'] ?? 0);
            if ($ref > 0) {
                $ref_post = get_post($ref);
                if ($ref_post && $ref_post->post_type === 'wp_block') {
                    buttercup_collect_member_entries(parse_blocks($ref_post->post_content), $entries);
                }
            }
        }

        if (($block['blockName'] ?? '') === 'buttercup/team') {
            $attrs = $block['attrs'] ?? [];
            $enabled = !array_key_exists('enableMemberPages', $attrs) || $attrs['enableMemberPages'];
            if ($enabled) {
                foreach ($block['innerBlocks'] ?? [] as $inner) {
                    if (($inner['blockName'] ?? '') === 'buttercup/team-member') {
                        $member_attrs = $inner['attrs'] ?? [];
                        if (!empty($member_attrs['disableMemberPage'])) {
                            continue;
                        }
                        $entries[] = [
                            'member' => $member_attrs,
                            'team' => $attrs,
                        ];
                    }
                }
            }
        }

        if (!empty($block['innerBlocks'])) {
            buttercup_collect_member_entries($block['innerBlocks'], $entries);
        }
    }

    return $entries;
}

function buttercup_find_member_in_blocks($blocks, $slug)
{
    $entries = [];
    buttercup_collect_member_entries($blocks, $entries);
    if (empty($entries)) {
        return null;
    }

    $entries = buttercup_build_member_slugs_for_page($entries);
    foreach ($entries as $entry) {
        if (($entry['slug'] ?? '') === $slug) {
            return $entry;
        }
    }

    return null;
}

function buttercup_member_page_contact_link($label, $href, $text = '')
{
    $display = $text !== '' ? $text : $href;
    return '<div><strong>' . esc_html($label) . ':</strong> <a href="' . esc_url($href) . '">' . esc_html($display) . '</a></div>';
}

function buttercup_enqueue_member_assets()
{
    $style_path = __DIR__ . '/build/team-member/style-index.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'buttercup-team-member',
            plugins_url('build/team-member/style-index.css', __FILE__),
            [],
            filemtime($style_path)
        );
    }
}

function buttercup_render_member_page()
{
    global $post, $wp_query;
    if (isset($_GET['buttercup_debug'])) {
        $request_path = buttercup_get_request_path();
        $match = buttercup_match_member_request($request_path);
        $debug_page_id = $match['page_id'] ?? ($post ? $post->ID : 0);
        $debug_page = $debug_page_id ? get_post($debug_page_id) : null;
        $entries = [];
        $slugs = [];
        $found_member = null;

        if ($debug_page) {
            $entries = [];
            buttercup_collect_member_entries(parse_blocks($debug_page->post_content), $entries);
            $entries = buttercup_build_member_slugs_for_page($entries);
            $slugs = array_map(function ($entry) {
                return $entry['slug'] ?? '';
            }, $entries);
            if ($match && $match['slug']) {
                foreach ($entries as $entry) {
                    if (($entry['slug'] ?? '') === $match['slug']) {
                        $found_member = $entry;
                        break;
                    }
                }
            }
        }

        $payload = [
            'request_path' => $request_path,
            'match' => $match,
            'page_id' => $debug_page_id,
            'page_title' => $debug_page ? $debug_page->post_title : '',
            'page_has_team' => $debug_page ? (strpos($debug_page->post_content, 'buttercup/team') !== false) : false,
            'entries_count' => count($entries),
            'slugs' => $slugs,
            'found_member' => $found_member ? ($found_member['member']['name'] ?? '') : '',
        ];

        wp_die('<pre>' . esc_html(print_r($payload, true)) . '</pre>', 'Buttercup Debug');
    }

    $slug = get_query_var('buttercup_member');
    $page_id = 0;

    if (!$slug) {
        $request_path = buttercup_get_request_path();
        $match = buttercup_match_member_request($request_path);
        if ($match) {
            $slug = $match['slug'];
            $page_id = $match['page_id'];
        }
    } else {
        $page_id = intval(get_query_var('page_id'));
        if ($page_id <= 0 && $post) {
            $page_id = $post->ID;
        }

        if ($page_id <= 0) {
            $request_path = buttercup_get_request_path();
            $match = buttercup_match_member_request($request_path);
            if ($match) {
                $page_id = $match['page_id'];
            }
        }
    }

    if (!$slug) {
        return;
    }

    if ($page_id > 0) {
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return;
        }
        $post = $page;
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
            $wp_query->queried_object = $post;
            $wp_query->queried_object_id = $post->ID;
        }
        setup_postdata($post);
    }

    $blocks = parse_blocks($post->post_content);
    $match = buttercup_find_member_in_blocks($blocks, $slug);

    if (!$match) {
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }

    $member = $match['member'];
    $team = $match['team'];

    $show_pronouns = !array_key_exists('showPronouns', $team) || $team['showPronouns'];
    $show_bio = !array_key_exists('showBio', $team) || $team['showBio'];
    $legacy_social = !array_key_exists('showSocial', $team) || $team['showSocial'];
    $show_social = array_key_exists('showSocialMemberPage', $team)
        ? $team['showSocialMemberPage']
        : $legacy_social;
    $show_member_bio = !array_key_exists('showBio', $member) || $member['showBio'];

    $name = buttercup_clean_name($member['name'] ?? '');
    $pronouns = isset($member['pronouns']) ? trim($member['pronouns']) : '';
    $position = isset($member['position']) ? trim($member['position']) : '';
    $email = isset($member['email']) ? trim($member['email']) : '';
    $phone = isset($member['phone']) ? trim($member['phone']) : '';
    $location = isset($member['location']) ? trim($member['location']) : '';
    $image_url = isset($member['profileImageUrl']) ? $member['profileImageUrl'] : '';
    $image_id = isset($member['profileImageId']) ? intval($member['profileImageId']) : 0;
    $image_alt = isset($member['profileImageAlt']) ? trim($member['profileImageAlt']) : '';
    $image_source = isset($member['profileImageSource']) ? $member['profileImageSource'] : '';

    $long_bio = isset($member['longBio']) ? trim($member['longBio']) : '';
    $short_bio = isset($member['bio']) ? $member['bio'] : '';
    $bio_html = '';

    if ($long_bio !== '') {
        $bio_html = wpautop(esc_html($long_bio));
    } elseif ($show_bio && $show_member_bio && $short_bio !== '') {
        $bio_html = wp_kses_post($short_bio);
    }

    $image_shape = $team['imageShape'] ?? 'circle';
    $image_size = isset($team['imageSize']) ? intval($team['imageSize']) : 600;
    $squircle_radius = isset($team['squircleRadius']) ? floatval($team['squircleRadius']) : 22;
    $member_card_bg = isset($team['memberPageCardBackground']) ? trim($team['memberPageCardBackground']) : '';
    $member_card_radius = isset($team['memberPageCardRadius']) ? intval($team['memberPageCardRadius']) : 20;
    $member_card_padding = isset($team['memberPageCardPadding']) ? intval($team['memberPageCardPadding']) : 24;
    $member_gap = isset($team['memberPageGap']) ? intval($team['memberPageGap']) : 32;
    $member_left = isset($team['memberPageLeftWidth']) ? intval($team['memberPageLeftWidth']) : 280;
    $member_shadow = $team['memberPageCardShadow'] ?? 'none';

    $member_title = $name !== '' ? $name : __('Team Member', 'buttercup');
    add_filter('document_title_parts', function ($parts) use ($member_title) {
        $parts['title'] = $member_title;
        return $parts;
    });
    add_filter('body_class', function ($classes) {
        $classes[] = 'buttercup-member-page';
        return $classes;
    });

    buttercup_enqueue_member_assets();

    $contact = '';
    if ($email !== '' && is_email($email)) {
        $contact .= buttercup_member_page_contact_link(__('Email', 'buttercup'), 'mailto:' . $email, $email);
    }
    if ($phone !== '') {
        $tel = preg_replace('/[^0-9+]/', '', $phone);
        if ($tel !== '') {
            $contact .= buttercup_member_page_contact_link(__('Phone', 'buttercup'), 'tel:' . $tel, $phone);
        }
    }
    if ($location !== '') {
        $contact .= '<div><strong>' . esc_html__('Location', 'buttercup') . ':</strong> ' . esc_html($location) . '</div>';
    }

    $social_html = '';
    $social_links = isset($member['socialLinks']) && is_array($member['socialLinks']) ? $member['socialLinks'] : [];
    foreach ($social_links as $link) {
        $url = isset($link['url']) ? trim($link['url']) : '';
        $platform = isset($link['platform']) ? $link['platform'] : '';
        if ($url === '') {
            continue;
        }
        if ($platform === 'email') {
            $href = strpos($url, 'mailto:') === 0 ? $url : 'mailto:' . $url;
        } else {
            $href = $url;
        }
        $label = $platform !== '' ? ucfirst($platform) : __('Social', 'buttercup');
        $social_html .= '<div><a href="' . esc_url($href) . '">' . esc_html($label) . '</a></div>';
    }

    status_header(200);
    nocache_headers();

    $member_back_label = isset($team['memberBackLabel']) ? trim($team['memberBackLabel']) : '';
    $member_intro = isset($team['memberPageIntro']) ? trim($team['memberPageIntro']) : '';

    $page_style = sprintf(
        '--buttercup-member-gap:%dpx; --buttercup-member-left:%dpx; --buttercup-member-card-radius:%dpx; --buttercup-member-card-padding:%dpx;',
        $member_gap,
        $member_left,
        $member_card_radius,
        $member_card_padding
    );
    if ($member_card_bg !== '') {
        $page_style .= ' --buttercup-member-card-bg:' . esc_attr($member_card_bg) . ';';
    }

    $shadow_class = $member_shadow && $member_shadow !== 'none'
        ? ' buttercup-team-member-page--shadow-' . esc_attr($member_shadow)
        : '';

    get_header();
    echo '<main class="buttercup-team-member-page" style="' . esc_attr($page_style) . '">';
    echo '<div class="buttercup-team-member-page__back">';
    $back_label = $member_back_label !== '' ? $member_back_label : sprintf(__('Back to %s', 'buttercup'), $post->post_title);
    echo '<a href="' . esc_url(get_permalink($post)) . '">' . esc_html($back_label) . '</a>';
    echo '</div>';
    echo '<div class="buttercup-team-member-page__layout">';
    echo '<aside class="buttercup-team-member-page__card buttercup-team-member-page--' . esc_attr($image_shape) . $shadow_class . '"';
    echo ' style="--buttercup-img-size:' . esc_attr($image_size) . 'px; --buttercup-squircle-radius:' . esc_attr($squircle_radius) . '%;">';
    if ($image_url || $image_id) {
        echo '<div class="buttercup-team-member__image-wrap">';
        if ($image_source === 'square-600' && $image_url) {
            $image_alt = $image_alt !== '' ? $image_alt : sprintf(__('Photo of %s', 'buttercup'), $member_title);
            echo '<img class="buttercup-team-member__image" loading="lazy" decoding="async"';
            echo ' style="object-fit:contain;object-position:center;"';
            echo ' src="' . esc_url($image_url) . '" width="600" height="600" alt="' . esc_attr($image_alt) . '">';
        } elseif ($image_id) {
            $meta = wp_get_attachment_metadata($image_id);
            $orig_ratio = 0;
            if ($meta && !empty($meta['width']) && !empty($meta['height'])) {
                $orig_ratio = $meta['width'] / $meta['height'];
            }
            $candidates = buttercup_build_image_candidates($image_id);
            $candidates = buttercup_filter_uncropped_candidates($candidates, $orig_ratio);
            $best = buttercup_pick_best_candidate($candidates, $image_size);
            $srcset = buttercup_build_srcset($candidates);
            $sizes_attr = $image_size . 'px';

            $final_url = $best ? $best['url'] : $image_url;
            $final_width = $best ? $best['width'] : 0;
            $final_height = $best ? $best['height'] : 0;

            if ($image_alt === '') {
                $image_alt = sprintf(__('Photo of %s', 'buttercup'), $member_title);
            }

            echo '<img class="buttercup-team-member__image" loading="lazy" decoding="async" style="object-fit:contain;object-position:center;"';
            echo ' src="' . esc_url($final_url) . '"';
            if ($srcset !== '') {
                echo ' srcset="' . esc_attr($srcset) . '" sizes="' . esc_attr($sizes_attr) . '"';
            }
            if ($final_width && $final_height) {
                echo ' width="' . esc_attr($final_width) . '" height="' . esc_attr($final_height) . '"';
            }
            echo ' alt="' . esc_attr($image_alt) . '">';
        } else {
            echo '<img class="buttercup-team-member__image" loading="lazy" decoding="async" style="object-fit:contain;object-position:center;" src="' . esc_url($image_url) . '" alt="' . esc_attr(sprintf(__('Photo of %s', 'buttercup'), $member_title)) . '">';
        }
        echo '</div>';
    }
    echo '<h1 class="buttercup-team-member-page__name">' . esc_html($member_title) . '</h1>';
    if ($show_pronouns && $pronouns !== '') {
        echo '<p class="buttercup-team-member-page__pronouns">' . esc_html($pronouns) . '</p>';
    }
    if ($position !== '') {
        echo '<p class="buttercup-team-member__position">' . esc_html($position) . '</p>';
    }
    if ($contact !== '') {
        echo '<div class="buttercup-team-member-page__contact">' . $contact . '</div>';
    }
    if ($show_social && $social_html !== '') {
        echo '<div class="buttercup-team-member-page__social">' . $social_html . '</div>';
    }
    echo '</aside>';
    echo '<section class="buttercup-team-member-page__bio">';
    if ($member_intro !== '') {
        echo '<div class="buttercup-team-member-page__intro">' . wpautop(esc_html($member_intro)) . '</div>';
    }
    echo $bio_html !== '' ? $bio_html : '';
    echo '</section>';
    echo '</div>';
    echo '</main>';
    get_footer();
    exit;
}
add_action('template_redirect', 'buttercup_render_member_page');

function buttercup_generate_square_image($attachment_id, $size = 600) {
    $file = get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) {
        return new WP_Error('buttercup_missing_file', __('Image file not found.', 'buttercup'));
    }

    $type = wp_check_filetype($file);
    $ext = strtolower($type['ext'] ?? '');
    $mime = $type['type'] ?? '';
    if (!$ext || !$mime) {
        return new WP_Error('buttercup_invalid_type', __('Unsupported image type.', 'buttercup'));
    }

    $info = pathinfo($file);
    $dir = $info['dirname'];
    $name = $info['filename'];
    $target = $dir . '/' . $name . '-buttercup-crop-' . intval($size) . 'x' . intval($size) . '.' . $ext;

    if (file_exists($target)) {
        $upload_dir = wp_get_upload_dir();
        $relative = str_replace($upload_dir['basedir'], '', $target);
        $url = trailingslashit($upload_dir['baseurl']) . ltrim($relative, '/');
        return [
            'url' => $url,
            'width' => $size,
            'height' => $size,
        ];
    }

    $image_info = getimagesize($file);
    if (!$image_info) {
        return new WP_Error('buttercup_invalid_image', __('Invalid image data.', 'buttercup'));
    }

    $src_width = intval($image_info[0]);
    $src_height = intval($image_info[1]);
    if (!$src_width || !$src_height) {
        return new WP_Error('buttercup_invalid_image', __('Invalid image size.', 'buttercup'));
    }

    $crop_size = min($src_width, $src_height);
    $src_x = intval(round(($src_width - $crop_size) / 2));
    $src_y = intval(round(($src_height - $crop_size) / 2));

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = imagecreatefromjpeg($file);
            break;
        case 'png':
            $src = imagecreatefrompng($file);
            break;
        case 'gif':
            $src = imagecreatefromgif($file);
            break;
        case 'webp':
            if (!function_exists('imagecreatefromwebp')) {
                return new WP_Error('buttercup_no_webp', __('WebP is not supported on this server.', 'buttercup'));
            }
            $src = imagecreatefromwebp($file);
            break;
        default:
            return new WP_Error('buttercup_invalid_type', __('Unsupported image type.', 'buttercup'));
    }

    if (!$src) {
        return new WP_Error('buttercup_invalid_image', __('Unable to load image.', 'buttercup'));
    }

    $dst = imagecreatetruecolor($size, $size);
    if (!$dst) {
        imagedestroy($src);
        return new WP_Error('buttercup_invalid_image', __('Unable to create image canvas.', 'buttercup'));
    }

    if ($ext === 'png' || $ext === 'gif' || $ext === 'webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, $src_x, $src_y, $size, $size, $crop_size, $crop_size);

    $saved = false;
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $saved = imagejpeg($dst, $target, 90);
            break;
        case 'png':
            $saved = imagepng($dst, $target, 6);
            break;
        case 'gif':
            $saved = imagegif($dst, $target);
            break;
        case 'webp':
            $saved = imagewebp($dst, $target, 85);
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);

    if (!$saved) {
        return new WP_Error('buttercup_save_failed', __('Unable to save image.', 'buttercup'));
    }

    $meta = wp_get_attachment_metadata($attachment_id);
    if (is_array($meta)) {
        $meta['sizes']['buttercup-square-600'] = [
            'file' => basename($target),
            'width' => $size,
            'height' => $size,
            'mime-type' => $mime,
        ];
        wp_update_attachment_metadata($attachment_id, $meta);
    }

    $upload_dir = wp_get_upload_dir();
    $relative = str_replace($upload_dir['basedir'], '', $target);
    $url = trailingslashit($upload_dir['baseurl']) . ltrim($relative, '/');

    return [
        'url' => $url,
        'width' => $size,
        'height' => $size,
    ];
}

function buttercup_rest_square_image($request) {
    $id = intval($request['id']);
    if (!$id) {
        return new WP_Error('buttercup_invalid_id', __('Invalid image ID.', 'buttercup'), ['status' => 400]);
    }

    $result = buttercup_generate_square_image($id, 600);
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response($result);
}

function buttercup_rest_homepage_feed_status($request)
{
    $mast_tag_slug = isset($request['mastTagSlug']) ? $request['mastTagSlug'] : 'mast';
    $home_tag_slug = isset($request['homeTagSlug']) ? $request['homeTagSlug'] : 'home';

    $feed = buttercup_homepage_feed_collect($mast_tag_slug, $home_tag_slug);

    $response = [
        'mastTagSlug' => $feed['mast_tag_slug'],
        'homeTagSlug' => $feed['home_tag_slug'],
        'mastCount' => intval($feed['mast_count']),
        'homeCount' => intval($feed['home_count']),
        'mastOverflow' => !empty($feed['mast_overflow']),
        'homeOverflow' => !empty($feed['home_overflow']),
        'mastSelected' => buttercup_homepage_feed_summarize_posts($feed['mast_selected'] ? [$feed['mast_selected']] : []),
        'homeSelected' => buttercup_homepage_feed_summarize_posts($feed['home_selected']),
        'dualTagged' => buttercup_homepage_feed_summarize_posts($feed['dual_posts']),
    ];

    return rest_ensure_response($response);
}

function buttercup_register_rest_routes()
{
    register_rest_route('buttercup/v1', '/square-image', [
        'methods' => 'POST',
        'callback' => 'buttercup_rest_square_image',
        'permission_callback' => function () {
            return current_user_can('upload_files');
        },
        'args' => [
            'id' => [
                'type' => 'integer',
                'required' => true,
            ],
        ],
    ]);

    register_rest_route('buttercup/v1', '/homepage-feed-status', [
        'methods' => 'GET',
        'callback' => 'buttercup_rest_homepage_feed_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'mastTagSlug' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_title',
            ],
            'homeTagSlug' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_title',
            ],
        ],
    ]);

    register_rest_route('buttercup/v1', '/tag-showcase-status', [
        'methods' => 'GET',
        'callback' => 'buttercup_rest_tag_showcase_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'tagSlugs' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    return sanitize_text_field($value);
                },
            ],
            'tagMatch' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    return sanitize_key($value);
                },
            ],
            'postTypes' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    return sanitize_text_field($value);
                },
            ],
            'excludeCurrentPost' => [
                'type' => 'boolean',
                'required' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'offset' => [
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint',
            ],
            'maxItems' => [
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'buttercup_register_rest_routes');
