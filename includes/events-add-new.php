<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show a success notice after creating a linked-page event.
 */
function buttercup_linked_event_created_notice()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'buttercup_event') {
        return;
    }

    $created_id = absint($_GET['buttercup_created'] ?? 0);
    if (!$created_id) {
        return;
    }

    $title     = get_the_title($created_id);
    $linked_id = absint(get_post_meta($created_id, '_buttercup_event_linked_page', true));
    $page_title = $linked_id ? get_the_title($linked_id) : '';

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        sprintf(
            /* translators: 1: event title, 2: linked page title */
            esc_html__('Event "%1$s" published and linked to the "%2$s" page.', 'buttercup'),
            esc_html($title),
            esc_html($page_title)
        )
    );
}
add_action('admin_notices', 'buttercup_linked_event_created_notice');

/**
 * Redirect the default "Add New Event" screen to our wizard.
 */
function buttercup_redirect_add_new_event()
{
    global $pagenow;

    if ($pagenow !== 'post-new.php') {
        return;
    }

    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'buttercup_event') {
        return;
    }

    // Don't redirect if coming back from the wizard (flag set).
    if (isset($_GET['buttercup_skip_wizard'])) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=buttercup-add-event'));
    exit;
}
add_action('admin_init', 'buttercup_redirect_add_new_event');

/**
 * Register the hidden submenu page for the add-event wizard.
 */
function buttercup_add_event_wizard_menu()
{
    add_submenu_page(
        null, // Hidden from menu.
        __('Add New Event', 'buttercup'),
        __('Add New Event', 'buttercup'),
        'edit_posts',
        'buttercup-add-event',
        'buttercup_add_event_wizard_render'
    );
}
add_action('admin_menu', 'buttercup_add_event_wizard_menu');

/**
 * Handle form submission and render the wizard page.
 */
function buttercup_add_event_wizard_render()
{
    // Enqueue media uploader.
    wp_enqueue_media();

    $error   = '';
    $success = '';

    // Handle form submission.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buttercup_add_event_nonce'])) {
        if (!wp_verify_nonce($_POST['buttercup_add_event_nonce'], 'buttercup_add_event')) {
            wp_die(__('Security check failed.', 'buttercup'));
        }

        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to create events.', 'buttercup'));
        }

        $mode = sanitize_key($_POST['buttercup_event_mode'] ?? 'manual');

        if ($mode === 'ical') {
            $result = buttercup_wizard_handle_ical();
        } else {
            $result = buttercup_wizard_handle_manual();
        }

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
        } elseif (is_int($result)) {
            // If event links to an existing page, it's already published —
            // go to the events list so they can see it.
            $linked = absint(get_post_meta($result, '_buttercup_event_linked_page', true));
            if ($linked) {
                wp_safe_redirect(admin_url(
                    'edit.php?post_type=buttercup_event&buttercup_created=' . $result
                ));
            } else {
                wp_safe_redirect(get_edit_post_link($result, 'raw'));
            }
            exit;
        }
    }

    buttercup_add_event_wizard_page($error);
}

/**
 * Handle manual event creation from the wizard form.
 *
 * @return int|WP_Error Post ID on success, WP_Error on failure.
 */
function buttercup_wizard_handle_manual()
{
    $title = sanitize_text_field($_POST['event_title'] ?? '');
    if (!$title) {
        return new WP_Error('missing_title', __('Please enter an event title.', 'buttercup'));
    }

    $start_date = sanitize_text_field($_POST['event_start_date'] ?? '');
    $start_time = sanitize_text_field($_POST['event_start_time'] ?? '');
    $start_allday = !empty($_POST['event_start_allday']);
    $start = buttercup_build_mysql_datetime($start_date, $start_allday ? '' : $start_time);

    $has_end = !empty($_POST['event_has_end']);
    $end = '';
    $end_allday = false;
    if ($has_end) {
        $end_date = sanitize_text_field($_POST['event_end_date'] ?? '');
        $end_time = sanitize_text_field($_POST['event_end_time'] ?? '');
        $end_allday = !empty($_POST['event_end_allday']);
        $end = buttercup_build_mysql_datetime($end_date, $end_allday ? '' : $end_time);
    }

    $location  = sanitize_text_field($_POST['event_location'] ?? '');
    $event_url = esc_url_raw($_POST['event_url'] ?? '');
    $content   = wp_kses_post($_POST['event_description'] ?? '');

    $submit_action = sanitize_key($_POST['_buttercup_submit_action'] ?? 'draft');
    $post_status = ($submit_action === 'publish') ? 'publish' : 'draft';

    $post_id = wp_insert_post([
        'post_type'    => 'buttercup_event',
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => $post_status,
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // Set meta.
    if ($start) {
        update_post_meta($post_id, '_buttercup_event_start', $start);
    }
    if ($start_allday) {
        update_post_meta($post_id, '_buttercup_event_start_allday', '1');
    }
    if ($end) {
        update_post_meta($post_id, '_buttercup_event_end', $end);
    }
    if ($end_allday) {
        update_post_meta($post_id, '_buttercup_event_end_allday', '1');
    }
    if ($location) {
        update_post_meta($post_id, '_buttercup_event_location', $location);
    }
    if ($event_url) {
        update_post_meta($post_id, '_buttercup_event_url', $event_url);

        $url_label = sanitize_key($_POST['event_url_label'] ?? 'more_info');
        if (in_array($url_label, ['more_info', 'get_tickets', 'register', 'custom'], true)) {
            update_post_meta($post_id, '_buttercup_event_url_label', $url_label);
        }
        if ($url_label === 'custom') {
            $custom_label = sanitize_text_field($_POST['event_url_label_custom'] ?? '');
            if ($custom_label) {
                update_post_meta($post_id, '_buttercup_event_url_label_custom', $custom_label);
            }
        }
    }

    // Page mode.
    $page_mode = sanitize_key($_POST['event_page_mode'] ?? 'template');
    if (in_array($page_mode, ['editor', 'standalone'], true)) {
        update_post_meta($post_id, '_buttercup_event_page_mode', $page_mode);
    }
    if ($page_mode === 'standalone') {
        $linked_page = absint($_POST['event_linked_page'] ?? 0);
        if ($linked_page) {
            update_post_meta($post_id, '_buttercup_event_linked_page', $linked_page);
            // Auto-publish — the real content lives on the linked page,
            // so there's nothing for the user to edit here.
            wp_publish_post($post_id);
        } else {
            $custom_slug = sanitize_title($_POST['event_custom_slug'] ?? '');
            if ($custom_slug) {
                update_post_meta($post_id, '_buttercup_event_custom_slug', $custom_slug);
            }
        }
    }

    // Featured image (cover).
    $cover_id = absint($_POST['event_cover_image_id'] ?? 0);
    if ($cover_id) {
        set_post_thumbnail($post_id, $cover_id);
    }

    // Homepage column images.
    if (!empty($_POST['event_homepage_enabled'])) {
        $mobile_id  = absint($_POST['event_homepage_mobile_id'] ?? 0);
        $desktop_id = absint($_POST['event_homepage_desktop_id'] ?? 0);

        if ($mobile_id) {
            update_post_meta($post_id, 'buttercup_home_mobile_image_id', $mobile_id);
        }
        if ($desktop_id) {
            update_post_meta($post_id, 'buttercup_home_desktop_image_id', $desktop_id);
        }
    }

    buttercup_bump_cache_version();

    return $post_id;
}

/**
 * Handle iCal file import from the wizard.
 *
 * @return int|WP_Error Post ID on success, WP_Error on failure.
 */
function buttercup_wizard_handle_ical()
{
    $ics_content = '';

    if (!empty($_FILES['ical_file']['tmp_name']) && $_FILES['ical_file']['error'] === UPLOAD_ERR_OK) {
        $ics_content = file_get_contents($_FILES['ical_file']['tmp_name']);
    } elseif (!empty($_POST['ical_url'])) {
        $url = esc_url_raw($_POST['ical_url']);
        if (!$url) {
            return new WP_Error('invalid_url', __('Please enter a valid URL.', 'buttercup'));
        }
        $response = wp_remote_get($url, ['timeout' => 30]);
        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('fetch_failed', __('Could not fetch the iCal URL.', 'buttercup'));
        }
        $ics_content = wp_remote_retrieve_body($response);
    }

    if (!$ics_content) {
        return new WP_Error('no_file', __('Please upload an .ics file or enter a URL.', 'buttercup'));
    }

    $events = buttercup_parse_ical($ics_content);
    if (empty($events)) {
        return new WP_Error('no_events', __('No events found in the iCal file.', 'buttercup'));
    }

    // If multiple events, redirect to bulk import.
    if (count($events) > 1) {
        // Save the content temporarily and redirect to bulk import.
        set_transient('buttercup_wizard_ical_data', $ics_content, 300);
        wp_safe_redirect(admin_url('admin.php?page=buttercup-import-events&tab=ical&wizard_batch=1'));
        exit;
    }

    $event = $events[0];

    $post_id = wp_insert_post([
        'post_type'    => 'buttercup_event',
        'post_title'   => sanitize_text_field($event['title'] ?? __('Untitled Event', 'buttercup')),
        'post_content' => wp_kses_post($event['description'] ?? ''),
        'post_status'  => 'draft',
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $start    = sanitize_text_field($event['start'] ?? '');
    $end      = sanitize_text_field($event['end'] ?? '');
    $location = sanitize_text_field($event['location'] ?? '');
    $url      = esc_url_raw($event['url'] ?? '');
    $uid      = sanitize_text_field($event['uid'] ?? '');

    update_post_meta($post_id, '_buttercup_event_start', $start);
    if ($end) {
        update_post_meta($post_id, '_buttercup_event_end', $end);
    }
    if ($location) {
        update_post_meta($post_id, '_buttercup_event_location', $location);
    }
    if ($url) {
        update_post_meta($post_id, '_buttercup_event_url', $url);
    }
    if ($uid) {
        update_post_meta($post_id, '_buttercup_event_uid', $uid);
    }

    // Download cover image if available.
    if (!empty($event['image_url'])) {
        buttercup_import_event_image($event['image_url'], $post_id);
    }

    buttercup_bump_cache_version();

    return $post_id;
}

/**
 * Build a MySQL datetime string from separate date and time values.
 *
 * @param string $date Date string "YYYY-MM-DD".
 * @param string $time Time string "HH:MM" (optional).
 * @return string MySQL datetime or empty string.
 */
function buttercup_build_mysql_datetime($date, $time = '')
{
    if (!$date) {
        return '';
    }
    $time = $time ?: '00:00';
    return $date . ' ' . $time . ':00';
}

/**
 * Render the add-event wizard page.
 */
function buttercup_add_event_wizard_page($error = '')
{
    ?>
    <div class="wrap buttercup-wizard">
        <h1><?php esc_html_e('Add New Event', 'buttercup'); ?></h1>

        <?php if ($error) : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <div class="buttercup-wizard__modes">
            <button type="button" class="buttercup-wizard__mode-btn active" data-mode="manual">
                <span class="dashicons dashicons-edit-large"></span>
                <?php esc_html_e('Create Manually', 'buttercup'); ?>
            </button>
            <button type="button" class="buttercup-wizard__mode-btn" data-mode="ical">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e('Import from .ics', 'buttercup'); ?>
            </button>
        </div>

        <!-- Manual form -->
        <form method="post" class="buttercup-wizard__form" id="buttercup-wizard-manual" enctype="multipart/form-data">
            <?php wp_nonce_field('buttercup_add_event', 'buttercup_add_event_nonce'); ?>
            <input type="hidden" name="buttercup_event_mode" value="manual" />

            <div class="buttercup-wizard__section">
                <h2><?php esc_html_e('Event Details', 'buttercup'); ?></h2>

                <div class="buttercup-wizard__field">
                    <label for="event_title"><?php esc_html_e('Event Title', 'buttercup'); ?> <span class="required">*</span></label>
                    <input type="text" name="event_title" id="event_title" class="large-text" required placeholder="<?php esc_attr_e('e.g. Annual Fundraiser Gala', 'buttercup'); ?>" />
                </div>

                <?php
                // Smart default: next Saturday.
                $next_saturday = date('Y-m-d', strtotime('next Saturday'));
                ?>
                <div class="buttercup-wizard__field-row">
                    <div class="buttercup-wizard__field buttercup-wizard__field--half">
                        <label for="event_start_date"><?php esc_html_e('Start Date', 'buttercup'); ?></label>
                        <input type="date" name="event_start_date" id="event_start_date" value="<?php echo esc_attr($next_saturday); ?>" />
                    </div>
                    <div class="buttercup-wizard__field buttercup-wizard__field--half" id="start-time-field">
                        <label for="event_start_time"><?php esc_html_e('Start Time', 'buttercup'); ?></label>
                        <input type="time" name="event_start_time" id="event_start_time" />
                    </div>
                </div>
                <div class="buttercup-wizard__field">
                    <label class="buttercup-wizard__toggle">
                        <input type="checkbox" name="event_start_allday" id="event_start_allday" value="1" />
                        <?php esc_html_e('All day (no specific time)', 'buttercup'); ?>
                    </label>
                </div>

                <div class="buttercup-wizard__field">
                    <label class="buttercup-wizard__toggle">
                        <input type="checkbox" name="event_has_end" id="event_has_end" value="1" />
                        <?php esc_html_e('Add end date/time', 'buttercup'); ?>
                    </label>
                </div>
                <div id="end-fields" style="display:none;">
                    <div class="buttercup-wizard__field-row">
                        <div class="buttercup-wizard__field buttercup-wizard__field--half">
                            <label for="event_end_date"><?php esc_html_e('End Date', 'buttercup'); ?></label>
                            <input type="date" name="event_end_date" id="event_end_date" />
                        </div>
                        <div class="buttercup-wizard__field buttercup-wizard__field--half" id="end-time-field">
                            <label for="event_end_time"><?php esc_html_e('End Time', 'buttercup'); ?></label>
                            <input type="time" name="event_end_time" id="event_end_time" />
                        </div>
                    </div>
                    <div class="buttercup-wizard__field">
                        <label class="buttercup-wizard__toggle">
                            <input type="checkbox" name="event_end_allday" id="event_end_allday" value="1" />
                            <?php esc_html_e('All day (no specific time)', 'buttercup'); ?>
                        </label>
                    </div>
                </div>

                <?php
                // Smart default: suggest the most recently used location.
                $recent_location = '';
                $recent_query = new WP_Query([
                    'post_type'      => 'buttercup_event',
                    'posts_per_page' => 1,
                    'post_status'    => 'any',
                    'meta_key'       => '_buttercup_event_start',
                    'orderby'        => 'meta_value',
                    'order'          => 'DESC',
                    'meta_query'     => [
                        [
                            'key'     => '_buttercup_event_location',
                            'value'   => '',
                            'compare' => '!=',
                        ],
                    ],
                ]);
                if ($recent_query->have_posts()) {
                    $recent_location = get_post_meta($recent_query->posts[0]->ID, '_buttercup_event_location', true);
                }
                wp_reset_postdata();
                ?>

                <div class="buttercup-wizard__field">
                    <label for="event_location"><?php esc_html_e('Location / Venue', 'buttercup'); ?></label>
                    <input type="text" name="event_location" id="event_location" class="large-text" placeholder="<?php echo esc_attr($recent_location ?: __('e.g. 1126 N St Marys St, San Antonio, TX', 'buttercup')); ?>" />
                    <?php if ($recent_location) : ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: location name */
                                esc_html__('Recent: %s', 'buttercup'),
                                '<a href="#" id="buttercup-use-recent-location" data-location="' . esc_attr($recent_location) . '">' . esc_html($recent_location) . '</a>'
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="buttercup-wizard__field">
                    <label for="event_url"><?php esc_html_e('Event URL (tickets, registration, etc.)', 'buttercup'); ?></label>
                    <input type="url" name="event_url" id="event_url" class="large-text" placeholder="https://" />
                </div>

                <div class="buttercup-wizard__field" id="url-label-field" style="display:none;">
                    <label for="event_url_label"><?php esc_html_e('Button Label', 'buttercup'); ?></label>
                    <select name="event_url_label" id="event_url_label">
                        <option value="more_info"><?php esc_html_e('More Info', 'buttercup'); ?></option>
                        <option value="get_tickets"><?php esc_html_e('Get Tickets', 'buttercup'); ?></option>
                        <option value="register"><?php esc_html_e('Register', 'buttercup'); ?></option>
                        <option value="custom"><?php esc_html_e('Custom...', 'buttercup'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Text shown on the event page button.', 'buttercup'); ?></p>
                </div>

                <div class="buttercup-wizard__field" id="url-label-custom-field" style="display:none;">
                    <label for="event_url_label_custom"><?php esc_html_e('Custom Button Text', 'buttercup'); ?></label>
                    <input type="text" name="event_url_label_custom" id="event_url_label_custom" class="large-text" placeholder="<?php esc_attr_e('e.g. RSVP Now', 'buttercup'); ?>" />
                </div>

                <div class="buttercup-wizard__field">
                    <label for="event_page_mode"><?php esc_html_e('Page Type', 'buttercup'); ?></label>
                    <select name="event_page_mode" id="event_page_mode">
                        <option value="template"><?php esc_html_e('Standard Event Page — built-in layout with date, location, and details', 'buttercup'); ?></option>
                        <option value="editor"><?php esc_html_e('Custom Layout — design freely with the block editor (still under /events/)', 'buttercup'); ?></option>
                        <option value="standalone"><?php esc_html_e('Dedicated Page — link an existing page or create a new root-level URL', 'buttercup'); ?></option>
                    </select>
                </div>

                <div class="buttercup-wizard__field" id="linked-page-field" style="display:none;">
                    <label for="event_linked_page"><?php esc_html_e('Link to an existing page', 'buttercup'); ?></label>
                    <select name="event_linked_page" id="event_linked_page">
                        <option value=""><?php esc_html_e('— Select a page —', 'buttercup'); ?></option>
                        <?php
                        $pages = get_pages(['post_status' => 'publish', 'sort_column' => 'post_title']);
                        foreach ($pages as $page) {
                            printf(
                                '<option value="%d">%s</option>',
                                $page->ID,
                                esc_html($page->post_title)
                            );
                        }
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e('Visitors will be sent to this page when they click the event in listings.', 'buttercup'); ?></p>
                </div>

                <div class="buttercup-wizard__field" id="custom-slug-field" style="display:none;">
                    <label for="event_custom_slug"><?php esc_html_e('Or create a new root URL', 'buttercup'); ?></label>
                    <input type="text" name="event_custom_slug" id="event_custom_slug" class="large-text" placeholder="<?php esc_attr_e('e.g. blockparty', 'buttercup'); ?>" />
                    <p class="description"><?php esc_html_e('Creates a page at yoursite.com/blockparty with full block editor control.', 'buttercup'); ?></p>
                </div>

                <div class="buttercup-wizard__field">
                    <label for="event_description"><?php esc_html_e('Description', 'buttercup'); ?></label>
                    <?php
                    wp_editor('', 'event_description', [
                        'media_buttons' => false,
                        'textarea_rows' => 8,
                        'teeny'         => true,
                        'quicktags'     => true,
                    ]);
                    ?>
                </div>
            </div>

            <div class="buttercup-wizard__section">
                <h2><?php esc_html_e('Cover Image', 'buttercup'); ?></h2>
                <p class="description"><?php esc_html_e('This image displays on the event page and in event listings.', 'buttercup'); ?></p>

                <div class="buttercup-wizard__image-field" id="cover-image-field">
                    <input type="hidden" name="event_cover_image_id" id="event_cover_image_id" value="" />
                    <div class="buttercup-wizard__image-preview" id="cover-image-preview"></div>
                    <p>
                        <button type="button" class="button buttercup-wizard__image-select" data-target="cover-image">
                            <?php esc_html_e('Select Image', 'buttercup'); ?>
                        </button>
                        <button type="button" class="button-link-delete buttercup-wizard__image-clear" data-target="cover-image" style="display:none;">
                            <?php esc_html_e('Remove', 'buttercup'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <div class="buttercup-wizard__section">
                <h2><?php esc_html_e('Homepage Feed', 'buttercup'); ?></h2>

                <label class="buttercup-wizard__toggle">
                    <input type="checkbox" name="event_homepage_enabled" id="event_homepage_enabled" value="1" />
                    <?php esc_html_e('Feature this event on the homepage', 'buttercup'); ?>
                </label>

                <div class="buttercup-wizard__homepage-images" id="homepage-images-section" style="display:none;">
                    <p class="description" style="margin-bottom:16px;">
                        <?php esc_html_e('Upload optimized images for the homepage column layout. These are separate from the cover image.', 'buttercup'); ?>
                    </p>

                    <div class="buttercup-wizard__field-row">
                        <div class="buttercup-wizard__field buttercup-wizard__field--half">
                            <label><?php esc_html_e('Mobile Thumbnail (5:3)', 'buttercup'); ?></label>
                            <p class="description"><?php esc_html_e('Suggested: 2000 × 1200', 'buttercup'); ?></p>
                            <div class="buttercup-wizard__image-field" id="mobile-image-field">
                                <input type="hidden" name="event_homepage_mobile_id" id="event_homepage_mobile_id" value="" />
                                <div class="buttercup-wizard__image-preview" id="mobile-image-preview"></div>
                                <p>
                                    <button type="button" class="button buttercup-wizard__image-select" data-target="mobile-image">
                                        <?php esc_html_e('Select Image', 'buttercup'); ?>
                                    </button>
                                    <button type="button" class="button-link-delete buttercup-wizard__image-clear" data-target="mobile-image" style="display:none;">
                                        <?php esc_html_e('Remove', 'buttercup'); ?>
                                    </button>
                                </p>
                            </div>
                        </div>

                        <div class="buttercup-wizard__field buttercup-wizard__field--half">
                            <label><?php esc_html_e('Desktop Thumbnail (2.74:1)', 'buttercup'); ?></label>
                            <p class="description"><?php esc_html_e('Suggested: 2880 × 1050', 'buttercup'); ?></p>
                            <div class="buttercup-wizard__image-field" id="desktop-image-field">
                                <input type="hidden" name="event_homepage_desktop_id" id="event_homepage_desktop_id" value="" />
                                <div class="buttercup-wizard__image-preview" id="desktop-image-preview"></div>
                                <p>
                                    <button type="button" class="button buttercup-wizard__image-select" data-target="desktop-image">
                                        <?php esc_html_e('Select Image', 'buttercup'); ?>
                                    </button>
                                    <button type="button" class="button-link-delete buttercup-wizard__image-clear" data-target="desktop-image" style="display:none;">
                                        <?php esc_html_e('Remove', 'buttercup'); ?>
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="buttercup-wizard__actions">
                <input type="hidden" name="_buttercup_submit_action" id="buttercup_submit_action" value="draft" />
                <?php submit_button(__('Create as Draft', 'buttercup'), 'secondary large', 'submit', false); ?>
                <button type="submit" class="button button-primary button-large" id="buttercup-publish-btn">
                    <?php esc_html_e('Create & Publish', 'buttercup'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=buttercup_event')); ?>" class="button button-large">
                    <?php esc_html_e('Cancel', 'buttercup'); ?>
                </a>
            </div>
        </form>

        <!-- iCal import form -->
        <form method="post" class="buttercup-wizard__form" id="buttercup-wizard-ical" style="display:none;" enctype="multipart/form-data">
            <?php wp_nonce_field('buttercup_add_event', 'buttercup_add_event_nonce'); ?>
            <input type="hidden" name="buttercup_event_mode" value="ical" />

            <div class="buttercup-wizard__section">
                <h2><?php esc_html_e('Import from Calendar File', 'buttercup'); ?></h2>
                <p class="description"><?php esc_html_e('Upload an .ics or .ical file. If it contains a single event, you\'ll be taken to the editor. Multiple events will go to bulk import.', 'buttercup'); ?></p>

                <div class="buttercup-wizard__field">
                    <label for="wizard_ical_file"><?php esc_html_e('Calendar File (.ics)', 'buttercup'); ?></label>
                    <input type="file" name="ical_file" id="wizard_ical_file" accept=".ics,.ical,.ifb,.icalendar" />
                </div>

                <div class="buttercup-wizard__divider">
                    <span><?php esc_html_e('or', 'buttercup'); ?></span>
                </div>

                <div class="buttercup-wizard__field">
                    <label for="wizard_ical_url"><?php esc_html_e('Calendar URL', 'buttercup'); ?></label>
                    <input type="url" name="ical_url" id="wizard_ical_url" class="large-text" placeholder="https://example.com/event.ics" />
                </div>
            </div>

            <div class="buttercup-wizard__actions">
                <?php submit_button(__('Import Event', 'buttercup'), 'primary large', 'submit', false); ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=buttercup_event')); ?>" class="button button-large">
                    <?php esc_html_e('Cancel', 'buttercup'); ?>
                </a>
            </div>
        </form>

        <?php
        if (function_exists('buttercup_wizard_shared_styles')) {
            buttercup_wizard_shared_styles();
        }
        if (function_exists('buttercup_wizard_shared_scripts')) {
            buttercup_wizard_shared_scripts();
        }
        ?>

        <script>
        (function() {
            // Mode switching (add-new wizard specific).
            var modeBtns = document.querySelectorAll('.buttercup-wizard__mode-btn');
            var manualForm = document.getElementById('buttercup-wizard-manual');
            var icalForm = document.getElementById('buttercup-wizard-ical');

            modeBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    modeBtns.forEach(function(b) { b.classList.remove('active'); });
                    btn.classList.add('active');

                    var mode = btn.getAttribute('data-mode');
                    if (mode === 'ical') {
                        manualForm.style.display = 'none';
                        icalForm.style.display = '';
                    } else {
                        manualForm.style.display = '';
                        icalForm.style.display = 'none';
                    }
                });
            });

            // Publish button sets submit action to 'publish'.
            var publishBtn = document.getElementById('buttercup-publish-btn');
            var submitAction = document.getElementById('buttercup_submit_action');
            if (publishBtn && submitAction) {
                publishBtn.addEventListener('click', function() {
                    submitAction.value = 'publish';
                });
            }

            // "Use recent location" link.
            var recentLink = document.getElementById('buttercup-use-recent-location');
            if (recentLink) {
                recentLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    var locationInput = document.getElementById('event_location');
                    if (locationInput) {
                        locationInput.value = recentLink.getAttribute('data-location');
                    }
                });
            }
        })();
        </script>
    </div>
    <?php
}
