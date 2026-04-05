<?php

if (!defined('ABSPATH')) {
    exit;
}

/* ── Admin menu ── */

function buttercup_admin_menu()
{
    add_menu_page(
        __('Buttercup', 'buttercup'),
        __('Buttercup', 'buttercup'),
        'manage_options',
        'buttercup',
        'buttercup_settings_page_render',
        'dashicons-buddicons-buddypress-logo',
        30
    );

    // Rename the auto-created first submenu item from "Buttercup" to "Settings".
    add_submenu_page(
        'buttercup',
        __('Buttercup Settings', 'buttercup'),
        __('Settings', 'buttercup'),
        'manage_options',
        'buttercup',
        'buttercup_settings_page_render'
    );

    add_submenu_page(
        'buttercup',
        __('Events Sync', 'buttercup'),
        __('Events Sync', 'buttercup'),
        'manage_options',
        'buttercup-events-sync',
        'buttercup_events_sync_page_render'
    );
}
add_action('admin_menu', 'buttercup_admin_menu');

/* ── Settings registration ── */

function buttercup_events_settings_init()
{
    register_setting('buttercup_events_sync', 'buttercup_fb_app_id', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('buttercup_events_sync', 'buttercup_fb_page_access_token', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('buttercup_events_sync', 'buttercup_fb_page_id', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('buttercup_events_sync', 'buttercup_fb_sync_interval', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 21600,
    ]);

    add_settings_section(
        'buttercup_fb_sync_section',
        __('Facebook Page Integration', 'buttercup'),
        'buttercup_fb_sync_section_render',
        'buttercup-events-sync'
    );

    add_settings_field(
        'buttercup_fb_app_id',
        __('App ID', 'buttercup'),
        'buttercup_fb_field_text_render',
        'buttercup-events-sync',
        'buttercup_fb_sync_section',
        ['option' => 'buttercup_fb_app_id']
    );

    add_settings_field(
        'buttercup_fb_page_id',
        __('Page ID', 'buttercup'),
        'buttercup_fb_field_text_render',
        'buttercup-events-sync',
        'buttercup_fb_sync_section',
        ['option' => 'buttercup_fb_page_id']
    );

    add_settings_field(
        'buttercup_fb_page_access_token',
        __('Page Access Token', 'buttercup'),
        'buttercup_fb_field_token_render',
        'buttercup-events-sync',
        'buttercup_fb_sync_section'
    );

    add_settings_field(
        'buttercup_fb_sync_interval',
        __('Sync Interval', 'buttercup'),
        'buttercup_fb_field_interval_render',
        'buttercup-events-sync',
        'buttercup_fb_sync_section'
    );

    // ── General settings ──

    register_setting('buttercup_general', 'buttercup_cache_ttl', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 600,
    ]);
    register_setting('buttercup_general', 'buttercup_events_per_page', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 6,
    ]);
    register_setting('buttercup_general', 'buttercup_events_slug', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_title',
        'default'           => 'events',
    ]);

    // Homepage Feed settings.
    register_setting('buttercup_general', 'buttercup_feed_cta_label', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('buttercup_general', 'buttercup_feed_mast_tag', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_title',
        'default'           => 'mast',
    ]);
    register_setting('buttercup_general', 'buttercup_feed_home_tag', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_title',
        'default'           => 'home',
    ]);

    // Tag Showcase settings.
    register_setting('buttercup_general', 'buttercup_showcase_button_label', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('buttercup_general', 'buttercup_showcase_max_items', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 12,
    ]);
    register_setting('buttercup_general', 'buttercup_showcase_aspect_ratio', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '16/9',
    ]);

    // Team settings.
    register_setting('buttercup_general', 'buttercup_team_image_shape', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'squircle',
    ]);
    register_setting('buttercup_general', 'buttercup_team_image_size', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 192,
    ]);

    // ── Sections and fields ──

    // Cache section.
    add_settings_section(
        'buttercup_cache_section',
        __('Cache', 'buttercup'),
        'buttercup_cache_section_render',
        'buttercup'
    );

    add_settings_field(
        'buttercup_cache_ttl',
        __('Cache Duration', 'buttercup'),
        'buttercup_cache_ttl_render',
        'buttercup',
        'buttercup_cache_section'
    );

    // Events section.
    add_settings_section(
        'buttercup_events_section',
        __('Events', 'buttercup'),
        'buttercup_events_section_render',
        'buttercup'
    );

    add_settings_field(
        'buttercup_events_per_page',
        __('Events Per Page', 'buttercup'),
        'buttercup_events_per_page_render',
        'buttercup',
        'buttercup_events_section'
    );

    add_settings_field(
        'buttercup_events_slug',
        __('Archive URL Slug', 'buttercup'),
        'buttercup_events_slug_render',
        'buttercup',
        'buttercup_events_section'
    );

    // Homepage Feed section.
    add_settings_section(
        'buttercup_feed_section',
        __('Homepage Feed', 'buttercup'),
        'buttercup_feed_section_render',
        'buttercup'
    );

    add_settings_field(
        'buttercup_feed_cta_label',
        __('CTA Button Label', 'buttercup'),
        'buttercup_feed_cta_label_render',
        'buttercup',
        'buttercup_feed_section'
    );

    add_settings_field(
        'buttercup_feed_mast_tag',
        __('Mast Tag Slug', 'buttercup'),
        'buttercup_feed_mast_tag_render',
        'buttercup',
        'buttercup_feed_section'
    );

    add_settings_field(
        'buttercup_feed_home_tag',
        __('Home Tag Slug', 'buttercup'),
        'buttercup_feed_home_tag_render',
        'buttercup',
        'buttercup_feed_section'
    );

    // Tag Showcase section.
    add_settings_section(
        'buttercup_showcase_section',
        __('Tag Showcase', 'buttercup'),
        'buttercup_showcase_section_render',
        'buttercup'
    );

    add_settings_field(
        'buttercup_showcase_button_label',
        __('Button Label', 'buttercup'),
        'buttercup_showcase_button_label_render',
        'buttercup',
        'buttercup_showcase_section'
    );

    add_settings_field(
        'buttercup_showcase_max_items',
        __('Max Items', 'buttercup'),
        'buttercup_showcase_max_items_render',
        'buttercup',
        'buttercup_showcase_section'
    );

    add_settings_field(
        'buttercup_showcase_aspect_ratio',
        __('Image Aspect Ratio', 'buttercup'),
        'buttercup_showcase_aspect_ratio_render',
        'buttercup',
        'buttercup_showcase_section'
    );

    // Team section.
    add_settings_section(
        'buttercup_team_section',
        __('Team', 'buttercup'),
        'buttercup_team_section_render',
        'buttercup'
    );

    add_settings_field(
        'buttercup_team_image_shape',
        __('Image Shape', 'buttercup'),
        'buttercup_team_image_shape_render',
        'buttercup',
        'buttercup_team_section'
    );

    add_settings_field(
        'buttercup_team_image_size',
        __('Image Size', 'buttercup'),
        'buttercup_team_image_size_render',
        'buttercup',
        'buttercup_team_section'
    );
}
add_action('admin_init', 'buttercup_events_settings_init');

/**
 * Flush rewrite rules when the event slug changes.
 */
function buttercup_on_events_slug_update($old_value, $new_value)
{
    if ($old_value !== $new_value) {
        buttercup_schedule_rewrite_flush();
    }
}
add_action('update_option_buttercup_events_slug', 'buttercup_on_events_slug_update', 10, 2);

/* ── Section and field renderers ── */

function buttercup_fb_sync_section_render()
{
    echo '<p>' . esc_html__('Connect a Facebook Page to automatically import its events.', 'buttercup') . '</p>';
}

function buttercup_fb_field_text_render($args)
{
    $option = $args['option'];
    $value  = get_option($option, '');
    echo '<input type="text" name="' . esc_attr($option) . '" value="' . esc_attr($value) . '" class="regular-text" />';
}

function buttercup_fb_field_token_render()
{
    $value = get_option('buttercup_fb_page_access_token', '');
    echo '<input type="password" name="buttercup_fb_page_access_token" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    echo '<p class="description">' . esc_html__('A long-lived Page Access Token. This value is stored in the database.', 'buttercup') . '</p>';
}

function buttercup_fb_field_interval_render()
{
    $value   = intval(get_option('buttercup_fb_sync_interval', 21600));
    $options = [
        3600  => __('Every hour', 'buttercup'),
        10800 => __('Every 3 hours', 'buttercup'),
        21600 => __('Every 6 hours', 'buttercup'),
        43200 => __('Every 12 hours', 'buttercup'),
        86400 => __('Once a day', 'buttercup'),
    ];

    echo '<select name="buttercup_fb_sync_interval">';
    foreach ($options as $seconds => $label) {
        $selected = selected($value, $seconds, false);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() is a safe core function
        echo '<option value="' . esc_attr($seconds) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

/* ── General settings field renderers ── */

function buttercup_cache_section_render()
{
    echo '<p>' . esc_html__('Control how long rendered block output is cached before being regenerated.', 'buttercup') . '</p>';
}

function buttercup_cache_ttl_render()
{
    $value = intval(get_option('buttercup_cache_ttl', 600));
    $options = [
        60   => __('1 minute', 'buttercup'),
        300  => __('5 minutes', 'buttercup'),
        600  => __('10 minutes (default)', 'buttercup'),
        1800 => __('30 minutes', 'buttercup'),
        3600 => __('1 hour', 'buttercup'),
    ];

    echo '<select name="buttercup_cache_ttl">';
    foreach ($options as $seconds => $label) {
        echo '<option value="' . esc_attr($seconds) . '"' . selected($value, $seconds, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Shorter durations show content changes faster but increase server load.', 'buttercup') . '</p>';
}

function buttercup_events_section_render()
{
    echo '<p>' . esc_html__('Configure how events are displayed on your site.', 'buttercup') . '</p>';
}

function buttercup_events_per_page_render()
{
    $value = intval(get_option('buttercup_events_per_page', 6));
    echo '<input type="number" name="buttercup_events_per_page" value="' . esc_attr($value) . '" min="1" max="50" step="1" class="small-text" />';
    echo '<p class="description">' . esc_html__('Number of events shown by default in the events block and on the archive page.', 'buttercup') . '</p>';
}

function buttercup_events_slug_render()
{
    $value = get_option('buttercup_events_slug', 'events');
    echo '<input type="text" name="buttercup_events_slug" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">';
    echo esc_html(sprintf(
        /* translators: %s: example URL */
        __('The URL base for the events archive and single events. Example: %s', 'buttercup'),
        home_url('/' . ($value ?: 'events') . '/')
    ));
    echo '</p>';
}

/* ── Homepage Feed field renderers ── */

function buttercup_feed_section_render()
{
    echo '<p>' . esc_html__('Default settings for the Homepage Feed block. Individual blocks can still override these.', 'buttercup') . '</p>';
}

function buttercup_feed_cta_label_render()
{
    $value = get_option('buttercup_feed_cta_label', '');
    echo '<input type="text" name="buttercup_feed_cta_label" value="' . esc_attr($value) . '" class="regular-text" placeholder="Read More" />';
    echo '<p class="description">' . esc_html__('Default call-to-action button text. Leave blank for "Read More".', 'buttercup') . '</p>';
}

function buttercup_feed_mast_tag_render()
{
    $value = get_option('buttercup_feed_mast_tag', 'mast');
    echo '<input type="text" name="buttercup_feed_mast_tag" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Tag slug used to select the featured (mast) post.', 'buttercup') . '</p>';
}

function buttercup_feed_home_tag_render()
{
    $value = get_option('buttercup_feed_home_tag', 'home');
    echo '<input type="text" name="buttercup_feed_home_tag" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Tag slug used to select home feed posts.', 'buttercup') . '</p>';
}

/* ── Tag Showcase field renderers ── */

function buttercup_showcase_section_render()
{
    echo '<p>' . esc_html__('Default settings for the Tag Showcase block. Individual blocks can still override these.', 'buttercup') . '</p>';
}

function buttercup_showcase_button_label_render()
{
    $value = get_option('buttercup_showcase_button_label', '');
    echo '<input type="text" name="buttercup_showcase_button_label" value="' . esc_attr($value) . '" class="regular-text" placeholder="Read More" />';
    echo '<p class="description">' . esc_html__('Default button text for showcase cards. Leave blank for "Read More".', 'buttercup') . '</p>';
}

function buttercup_showcase_max_items_render()
{
    $value = intval(get_option('buttercup_showcase_max_items', 12));
    echo '<input type="number" name="buttercup_showcase_max_items" value="' . esc_attr($value) . '" min="1" max="60" step="1" class="small-text" />';
    echo '<p class="description">' . esc_html__('Default number of items to display in a showcase grid.', 'buttercup') . '</p>';
}

function buttercup_showcase_aspect_ratio_render()
{
    $value = get_option('buttercup_showcase_aspect_ratio', '16/9');
    $options = [
        '16/9' => '16:9',
        '4/3'  => '4:3',
        '3/2'  => '3:2',
        '1/1'  => '1:1 ' . __('(Square)', 'buttercup'),
        '2/3'  => '2:3 ' . __('(Portrait)', 'buttercup'),
        'auto' => __('Auto (original)', 'buttercup'),
    ];

    echo '<select name="buttercup_showcase_aspect_ratio">';
    foreach ($options as $ratio => $label) {
        echo '<option value="' . esc_attr($ratio) . '"' . selected($value, $ratio, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Default aspect ratio for thumbnail images in showcase cards.', 'buttercup') . '</p>';
}

/* ── Team field renderers ── */

function buttercup_team_section_render()
{
    echo '<p>' . esc_html__('Default settings for the Team block. Individual blocks can still override these.', 'buttercup') . '</p>';
}

function buttercup_team_image_shape_render()
{
    $value = get_option('buttercup_team_image_shape', 'squircle');
    $options = [
        'squircle' => __('Squircle', 'buttercup'),
        'circle'   => __('Circle', 'buttercup'),
        'square'   => __('Square', 'buttercup'),
    ];

    echo '<select name="buttercup_team_image_shape">';
    foreach ($options as $shape => $label) {
        echo '<option value="' . esc_attr($shape) . '"' . selected($value, $shape, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

function buttercup_team_image_size_render()
{
    $value = intval(get_option('buttercup_team_image_size', 192));
    echo '<input type="number" name="buttercup_team_image_size" value="' . esc_attr($value) . '" min="40" max="600" step="1" class="small-text" />';
    echo '<span class="description"> px</span>';
}

/* ── Page renderers ── */

function buttercup_settings_page_render()
{
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Buttercup Settings', 'buttercup') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('buttercup_general');
    do_settings_sections('buttercup');
    submit_button();
    echo '</form>';
    echo '</div>';
}

function buttercup_events_sync_page_render()
{
    $last_sync  = get_option('buttercup_fb_last_sync', '');
    $last_error = get_option('buttercup_fb_sync_last_error', '');
    $next       = wp_next_scheduled('buttercup_facebook_sync_events');

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Events Sync', 'buttercup') . '</h1>';

    // Status info.
    echo '<div class="buttercup-sync-status" style="margin-bottom: 20px; padding: 12px; background: #f0f0f1; border-left: 4px solid #2271b1;">';
    if ($last_sync) {
        echo '<p><strong>' . esc_html__('Last sync:', 'buttercup') . '</strong> ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))) . '</p>';
    }
    if ($next) {
        echo '<p><strong>' . esc_html__('Next scheduled sync:', 'buttercup') . '</strong> ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next)) . '</p>';
    }
    if ($last_error) {
        echo '<div class="notice notice-error inline"><p>' . esc_html($last_error) . '</p></div>';
    }
    echo '</div>';

    // Settings form.
    echo '<form method="post" action="options.php">';
    settings_fields('buttercup_events_sync');
    do_settings_sections('buttercup-events-sync');
    submit_button();
    echo '</form>';

    // Manual sync button.
    echo '<hr />';
    echo '<h2>' . esc_html__('Manual Sync', 'buttercup') . '</h2>';
    echo '<p>' . esc_html__('Trigger an immediate sync from your Facebook Page.', 'buttercup') . '</p>';
    echo '<button type="button" class="button button-secondary" id="buttercup-sync-now">';
    echo esc_html__('Sync Now', 'buttercup');
    echo '</button>';
    echo '<span id="buttercup-sync-result" style="margin-left: 12px;"></span>';

    ?>
    <script>
    (function() {
        var btn = document.getElementById('buttercup-sync-now');
        var result = document.getElementById('buttercup-sync-result');
        if (!btn) return;
        btn.addEventListener('click', function() {
            btn.disabled = true;
            result.textContent = '<?php echo esc_js(__('Syncing...', 'buttercup')); ?>';
            fetch('<?php echo esc_url(rest_url('buttercup/v1/events-sync')); ?>', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>',
                    'Content-Type': 'application/json',
                },
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (data.synced !== undefined) {
                    result.textContent = data.synced + ' <?php echo esc_js(__('events synced.', 'buttercup')); ?>';
                } else if (data.message) {
                    result.textContent = data.message;
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                result.textContent = '<?php echo esc_js(__('Sync failed.', 'buttercup')); ?>';
            });
        });
    })();
    </script>
    <?php
    echo '</div>';
}
