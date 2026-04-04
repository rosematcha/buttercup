<?php
/**
 * Buttercup uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Removes all plugin options, transients, cron events, and custom post type data.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/* ── Options ── */

$options = [
    // Cache & rewrite.
    'buttercup_cache_version',
    'buttercup_cache_ttl',
    'buttercup_needs_rewrite_flush',

    // Homepage feed.
    'buttercup_feed_cta_label',
    'buttercup_feed_mast_tag',
    'buttercup_feed_home_tag',

    // Tag showcase.
    'buttercup_showcase_button_label',
    'buttercup_showcase_max_items',
    'buttercup_showcase_aspect_ratio',

    // Team.
    'buttercup_team_image_shape',
    'buttercup_team_image_size',
    'buttercup_member_bases',

    // Events.
    'buttercup_events_per_page',
    'buttercup_events_slug',

    // Facebook sync.
    'buttercup_fb_app_id',
    'buttercup_fb_page_access_token',
    'buttercup_fb_page_id',
    'buttercup_fb_sync_interval',
    'buttercup_fb_last_sync',
    'buttercup_fb_sync_last_error',

    // Standalone event slugs.
    '_buttercup_standalone_slugs',
];

foreach ($options as $option) {
    delete_option($option);
}

/* ── Cron events ── */

wp_clear_scheduled_hook('buttercup_facebook_sync_events');

/* ── Custom post type data (buttercup_event) ── */

$events = get_posts([
    'post_type'      => 'buttercup_event',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);

foreach ($events as $event_id) {
    wp_delete_post($event_id, true);
}

/* ── Transients ── */

global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '%\_transient\_buttercup:%'
        OR option_name LIKE '%\_transient\_timeout\_buttercup:%'
        OR option_name LIKE '%\_transient\_buttercup\_wizard\_%'
        OR option_name LIKE '%\_transient\_timeout\_buttercup\_wizard\_%'"
);

/* ── Post meta (home feed images) ── */

$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key IN (
         'buttercup_home_mobile_image_id',
         'buttercup_home_desktop_image_id'
     )"
);

/* ── Flush rewrite rules to remove custom post type permalinks ── */

flush_rewrite_rules(false);
