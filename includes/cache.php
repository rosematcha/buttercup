<?php

if (!defined('ABSPATH')) {
    exit;
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

function buttercup_cache_ttl()
{
    return max(60, intval(get_option('buttercup_cache_ttl', 600)));
}

function buttercup_cache_get($key)
{
    $cached = wp_cache_get($key, 'buttercup');
    if ($cached !== false) {
        return $cached;
    }

    $ttl    = buttercup_cache_ttl();
    $cached = get_transient($key);
    if ($cached !== false) {
        wp_cache_set($key, $cached, 'buttercup', $ttl);
        return $cached;
    }

    return null;
}

function buttercup_cache_set($key, $value, $ttl = null)
{
    if ($ttl === null) {
        $ttl = buttercup_cache_ttl();
    }
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
