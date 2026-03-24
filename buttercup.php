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

if (!defined('BUTTERCUP_MAX_BLOCK_DEPTH')) {
    define('BUTTERCUP_MAX_BLOCK_DEPTH', 40);
}

function buttercup_cache_version()
{
    return intval(get_option('buttercup_cache_version', 1));
}

function buttercup_build_cache_key($namespace, $parts = [])
{
    $version = buttercup_cache_version();
    $payload = wp_json_encode([$namespace, $version, $parts]);
    return 'buttercup:' . md5((string) $payload);
}

function buttercup_cache_get($key)
{
    $cached = wp_cache_get($key, 'buttercup');
    if ($cached !== false) {
        return $cached;
    }

    $cached = get_transient($key);
    if ($cached !== false) {
        wp_cache_set($key, $cached, 'buttercup', 600);
        return $cached;
    }

    return null;
}

function buttercup_cache_set($key, $value, $ttl = 600)
{
    wp_cache_set($key, $value, 'buttercup', $ttl);
    set_transient($key, $value, $ttl);
}

function buttercup_bump_cache_version()
{
    $next_version = max(2, buttercup_cache_version() + 1);
    update_option('buttercup_cache_version', $next_version, false);
}

function buttercup_invalidate_cached_views($post_id = 0)
{
    if ($post_id > 0 && (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id))) {
        return;
    }
    buttercup_bump_cache_version();
}

function buttercup_invalidate_cached_views_for_terms($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
{
    if ($taxonomy !== 'post_tag') {
        return;
    }
    buttercup_bump_cache_version();
}

function buttercup_schedule_rewrite_flush()
{
    update_option('buttercup_needs_rewrite_flush', 1, false);
}

function buttercup_maybe_flush_rewrite_rules()
{
    static $flushed = false;

    if ($flushed) {
        return;
    }

    if (intval(get_option('buttercup_needs_rewrite_flush', 0)) !== 1) {
        return;
    }

    $flushed = true;
    flush_rewrite_rules(false);
    delete_option('buttercup_needs_rewrite_flush');
}
add_action('shutdown', 'buttercup_maybe_flush_rewrite_rules');
add_action('save_post', 'buttercup_invalidate_cached_views');
add_action('deleted_post', 'buttercup_invalidate_cached_views');
add_action('set_object_terms', 'buttercup_invalidate_cached_views_for_terms', 10, 6);

function buttercup_normalize_member_slug($slug)
{
    return sanitize_title((string) $slug);
}

function buttercup_is_valid_member_slug($slug)
{
    return is_string($slug) && $slug !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function buttercup_get_member_match_cache($request_path = null, $refresh = false)
{
    static $memo = [];
    $path = $request_path === null ? buttercup_get_request_path() : trim((string) $request_path, '/');
    $cache_key = $path === '' ? '__empty__' : $path;

    if (!$refresh && array_key_exists($cache_key, $memo)) {
        return $memo[$cache_key];
    }

    $memo[$cache_key] = buttercup_match_member_request($path);
    return $memo[$cache_key];
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


if (!defined('BUTTERCUP_PLUGIN_FILE')) {
    define('BUTTERCUP_PLUGIN_FILE', __FILE__);
}

if (!defined('BUTTERCUP_PLUGIN_DIR')) {
    define('BUTTERCUP_PLUGIN_DIR', __DIR__);
}

require_once BUTTERCUP_PLUGIN_DIR . '/includes/homepage-feed.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/tag-showcase.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/member-pages.php';
require_once BUTTERCUP_PLUGIN_DIR . '/includes/rest.php';

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

register_activation_hook(BUTTERCUP_PLUGIN_FILE, 'buttercup_activation_refresh');
