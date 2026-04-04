<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build a MySQL datetime string from separate date and time values.
 */
if (!function_exists('buttercup_build_mysql_datetime')) {
    function buttercup_build_mysql_datetime($date, $time)
    {
        if (!$date) return '';
        $time = $time ?: '00:00';
        return $date . ' ' . $time . ':00';
    }
}

/**
 * Redirect the default edit screen to the edit wizard for template-mode events.
 */
function buttercup_redirect_edit_event()
{
    global $pagenow;

    if ($pagenow !== 'post.php') {
        return;
    }

    if (!isset($_GET['action']) || $_GET['action'] !== 'edit') {
        return;
    }

    $post_id = absint($_GET['post'] ?? 0);
    if (!$post_id) {
        return;
    }

    if (get_post_type($post_id) !== 'buttercup_event') {
        return;
    }

    if (buttercup_get_event_page_mode($post_id) !== 'template') {
        return;
    }

    if (isset($_GET['buttercup_skip_wizard'])) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=buttercup-edit-event&post_id=' . $post_id));
    exit;
}
add_action('admin_init', 'buttercup_redirect_edit_event');

/**
 * Register the hidden submenu page for the edit-event wizard.
 */
function buttercup_edit_event_wizard_menu()
{
    add_submenu_page(
        null, // Hidden from menu.
        __('Edit Event', 'buttercup'),
        __('Edit Event', 'buttercup'),
        'edit_posts',
        'buttercup-edit-event',
        'buttercup_edit_event_wizard_render'
    );
}
add_action('admin_menu', 'buttercup_edit_event_wizard_menu');

/**
 * Handle form submission and render the edit wizard page.
 */
function buttercup_edit_event_wizard_render()
{
    $post_id = absint($_GET['post_id'] ?? 0);
    if (!$post_id) {
        wp_die(__('Missing event ID.', 'buttercup'));
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'buttercup_event') {
        wp_die(__('Invalid event.', 'buttercup'));
    }

    // Enqueue media uploader.
    wp_enqueue_media();

    $error = '';

    // Handle form submission.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buttercup_edit_event_nonce'])) {
        if (!wp_verify_nonce($_POST['buttercup_edit_event_nonce'], 'buttercup_edit_event')) {
            wp_die(__('Security check failed.', 'buttercup'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to edit this event.', 'buttercup'));
        }

        $title = sanitize_text_field($_POST['event_title'] ?? '');
        if (!$title) {
            $error = __('Please enter an event title.', 'buttercup');
        }

        if (!$error) {
            $start_date = sanitize_text_field($_POST['event_start_date'] ?? '');
            $content = wp_kses_post($_POST['event_description'] ?? '');

            wp_update_post([
                'ID'           => $post_id,
                'post_title'   => $title,
                'post_content' => $content,
            ]);

            // Start date/time.
            $start_time = sanitize_text_field($_POST['event_start_time'] ?? '');
            $start_allday = !empty($_POST['event_start_allday']);
            $start = buttercup_build_mysql_datetime($start_date, $start_allday ? '' : $start_time);
            update_post_meta($post_id, '_buttercup_event_start', $start);
            update_post_meta($post_id, '_buttercup_event_start_allday', $start_allday ? '1' : '');

            // End date/time.
            $has_end = !empty($_POST['event_has_end']);
            if ($has_end) {
                $end_date = sanitize_text_field($_POST['event_end_date'] ?? '');
                $end_time = sanitize_text_field($_POST['event_end_time'] ?? '');
                $end_allday = !empty($_POST['event_end_allday']);
                $end = buttercup_build_mysql_datetime($end_date, $end_allday ? '' : $end_time);
                update_post_meta($post_id, '_buttercup_event_end', $end);
                update_post_meta($post_id, '_buttercup_event_end_allday', $end_allday ? '1' : '');
            } else {
                update_post_meta($post_id, '_buttercup_event_end', '');
                update_post_meta($post_id, '_buttercup_event_end_allday', '');
            }

            // Location.
            $location = sanitize_text_field($_POST['event_location'] ?? '');
            update_post_meta($post_id, '_buttercup_event_location', $location);

            // URL.
            $event_url = esc_url_raw($_POST['event_url'] ?? '');
            update_post_meta($post_id, '_buttercup_event_url', $event_url);

            if ($event_url) {
                $url_label = sanitize_key($_POST['event_url_label'] ?? 'more_info');
                if (in_array($url_label, ['more_info', 'get_tickets', 'register', 'custom'], true)) {
                    update_post_meta($post_id, '_buttercup_event_url_label', $url_label);
                }
                if ($url_label === 'custom') {
                    $custom_label = sanitize_text_field($_POST['event_url_label_custom'] ?? '');
                    update_post_meta($post_id, '_buttercup_event_url_label_custom', $custom_label);
                }
            }

            // Page mode.
            $page_mode = sanitize_key($_POST['event_page_mode'] ?? 'template');
            if (in_array($page_mode, ['template', 'editor', 'standalone'], true)) {
                update_post_meta($post_id, '_buttercup_event_page_mode', $page_mode);
            }
            if ($page_mode === 'standalone') {
                $linked_page = absint($_POST['event_linked_page'] ?? 0);
                if ($linked_page) {
                    update_post_meta($post_id, '_buttercup_event_linked_page', $linked_page);
                } else {
                    $custom_slug = sanitize_title($_POST['event_custom_slug'] ?? '');
                    update_post_meta($post_id, '_buttercup_event_custom_slug', $custom_slug);
                }
            }

            // Featured image (cover).
            $cover_id = absint($_POST['event_cover_image_id'] ?? 0);
            if ($cover_id) {
                set_post_thumbnail($post_id, $cover_id);
            } else {
                delete_post_thumbnail($post_id);
            }

            // Homepage column images.
            $mobile_id  = absint($_POST['event_homepage_mobile_id'] ?? 0);
            $desktop_id = absint($_POST['event_homepage_desktop_id'] ?? 0);
            update_post_meta($post_id, 'buttercup_home_mobile_image_id', $mobile_id ?: '');
            update_post_meta($post_id, 'buttercup_home_desktop_image_id', $desktop_id ?: '');

            // Submit action.
            $action = sanitize_key($_POST['_buttercup_submit_action'] ?? 'update');
            if ($action === 'publish') {
                wp_publish_post($post_id);
            } elseif ($action === 'draft') {
                wp_update_post([
                    'ID'          => $post_id,
                    'post_status' => 'draft',
                ]);
            }
            // 'update' — just save, no status change.

            buttercup_bump_cache_version();

            wp_safe_redirect(admin_url('admin.php?page=buttercup-edit-event&post_id=' . $post_id . '&updated=1'));
            exit;
        }
    }

    buttercup_edit_event_wizard_page($post_id, $error);
}

/**
 * Render the edit-event wizard page.
 *
 * @param int    $post_id The event post ID.
 * @param string $error   Optional error message.
 */
function buttercup_edit_event_wizard_page($post_id, $error = '')
{
    $post = get_post($post_id);

    // Gather existing meta.
    $start_raw      = get_post_meta($post_id, '_buttercup_event_start', true);
    $end_raw        = get_post_meta($post_id, '_buttercup_event_end', true);
    $start_allday   = get_post_meta($post_id, '_buttercup_event_start_allday', true);
    $end_allday     = get_post_meta($post_id, '_buttercup_event_end_allday', true);
    $location       = get_post_meta($post_id, '_buttercup_event_location', true);
    $event_url      = get_post_meta($post_id, '_buttercup_event_url', true);
    $url_label      = get_post_meta($post_id, '_buttercup_event_url_label', true) ?: 'more_info';
    $url_label_custom = get_post_meta($post_id, '_buttercup_event_url_label_custom', true);
    $page_mode      = buttercup_get_event_page_mode($post_id);
    $linked_page    = absint(get_post_meta($post_id, '_buttercup_event_linked_page', true));
    $custom_slug    = get_post_meta($post_id, '_buttercup_event_custom_slug', true);
    $cover_id       = get_post_thumbnail_id($post_id);
    $mobile_id      = absint(get_post_meta($post_id, 'buttercup_home_mobile_image_id', true));
    $desktop_id     = absint(get_post_meta($post_id, 'buttercup_home_desktop_image_id', true));

    // Parse start date/time.
    $start_date = '';
    $start_time = '';
    if ($start_raw) {
        $parts = explode(' ', $start_raw);
        $start_date = $parts[0] ?? '';
        if (!empty($parts[1])) {
            $start_time = substr($parts[1], 0, 5); // HH:MM
        }
    }

    // Parse end date/time.
    $end_date = '';
    $end_time = '';
    $has_end  = (bool) $end_raw;
    if ($end_raw) {
        $parts = explode(' ', $end_raw);
        $end_date = $parts[0] ?? '';
        if (!empty($parts[1])) {
            $end_time = substr($parts[1], 0, 5); // HH:MM
        }
    }

    $is_published = $post->post_status === 'publish';
    $status_label = $is_published ? __('Published', 'buttercup') : ucfirst($post->post_status);
    $status_class = $is_published ? 'published' : esc_attr($post->post_status);
    ?>
    <div class="wrap buttercup-wizard">
        <h1><?php esc_html_e('Edit Event', 'buttercup'); ?></h1>

        <div class="buttercup-wizard__status-bar">
            <span class="buttercup-wizard__status-label"><?php esc_html_e('Status:', 'buttercup'); ?></span>
            <span class="buttercup-wizard__status-value buttercup-wizard__status-value--<?php echo $status_class; ?>">
                <?php echo esc_html($status_label); ?>
            </span>
        </div>

        <?php if (!empty($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event updated.', 'buttercup'); ?></p></div>
        <?php endif; ?>

        <?php if ($error) : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <form method="post" class="buttercup-wizard__form" id="buttercup-wizard-edit" enctype="multipart/form-data">
            <?php wp_nonce_field('buttercup_edit_event', 'buttercup_edit_event_nonce'); ?>
            <input type="hidden" name="_buttercup_submit_action" id="buttercup_submit_action" value="update" />

            <div class="buttercup-wizard__section">
                <h2><?php esc_html_e('Event Details', 'buttercup'); ?></h2>

                <div class="buttercup-wizard__field">
                    <label for="event_title"><?php esc_html_e('Event Title', 'buttercup'); ?> <span class="required">*</span></label>
                    <input type="text" name="event_title" id="event_title" class="large-text" required
                           value="<?php echo esc_attr($post->post_title); ?>"
                           placeholder="<?php esc_attr_e('e.g. Annual Fundraiser Gala', 'buttercup'); ?>" />
                </div>

                <div class="buttercup-wizard__field">
                    <label><?php esc_html_e('Start Date & Time', 'buttercup'); ?></label>
                    <div class="buttercup-wizard__field-row">
                        <div class="buttercup-wizard__field buttercup-wizard__field--half">
                            <input type="date" name="event_start_date" id="event_start_date"
                                   value="<?php echo esc_attr($start_date); ?>" />
                        </div>
                        <div class="buttercup-wizard__field buttercup-wizard__field--half" id="start-time-field">
                            <input type="time" name="event_start_time" id="event_start_time"
                                   value="<?php echo esc_attr($start_time); ?>" />
                        </div>
                    </div>
                    <label class="buttercup-wizard__toggle" style="margin-top:8px;">
                        <input type="checkbox" name="event_start_allday" id="event_start_allday" value="1" <?php checked($start_allday); ?> />
                        <?php esc_html_e('All day (no specific time)', 'buttercup'); ?>
                    </label>
                </div>

                <div class="buttercup-wizard__field">
                    <label class="buttercup-wizard__toggle">
                        <input type="checkbox" name="event_has_end" id="event_has_end" value="1" <?php checked($has_end); ?> />
                        <?php esc_html_e('Add end date/time', 'buttercup'); ?>
                    </label>
                    <div id="end-fields" style="margin-top:12px;<?php echo $has_end ? '' : ' display:none;'; ?>">
                        <div class="buttercup-wizard__field-row">
                            <div class="buttercup-wizard__field buttercup-wizard__field--half">
                                <input type="date" name="event_end_date" id="event_end_date"
                                       value="<?php echo esc_attr($end_date); ?>" />
                            </div>
                            <div class="buttercup-wizard__field buttercup-wizard__field--half" id="end-time-field">
                                <input type="time" name="event_end_time" id="event_end_time"
                                       value="<?php echo esc_attr($end_time); ?>" />
                            </div>
                        </div>
                        <label class="buttercup-wizard__toggle" style="margin-top:8px;">
                            <input type="checkbox" name="event_end_allday" id="event_end_allday" value="1" <?php checked($end_allday); ?> />
                            <?php esc_html_e('All day (no specific time)', 'buttercup'); ?>
                        </label>
                    </div>
                </div>

                <div class="buttercup-wizard__field">
                    <label for="event_location"><?php esc_html_e('Location / Venue', 'buttercup'); ?></label>
                    <input type="text" name="event_location" id="event_location" class="large-text"
                           value="<?php echo esc_attr($location); ?>"
                           placeholder="<?php esc_attr_e('e.g. 1126 N St Marys St, San Antonio, TX', 'buttercup'); ?>" />
                </div>

                <div class="buttercup-wizard__field">
                    <label for="event_url"><?php esc_html_e('Event URL (tickets, registration, etc.)', 'buttercup'); ?></label>
                    <input type="url" name="event_url" id="event_url" class="large-text"
                           value="<?php echo esc_attr($event_url); ?>"
                           placeholder="https://" />
                </div>

                <div class="buttercup-wizard__field" id="url-label-field" style="<?php echo $event_url ? '' : 'display:none;'; ?>">
                    <label for="event_url_label"><?php esc_html_e('Button Label', 'buttercup'); ?></label>
                    <select name="event_url_label" id="event_url_label">
                        <option value="more_info" <?php selected($url_label, 'more_info'); ?>><?php esc_html_e('More Info', 'buttercup'); ?></option>
                        <option value="get_tickets" <?php selected($url_label, 'get_tickets'); ?>><?php esc_html_e('Get Tickets', 'buttercup'); ?></option>
                        <option value="register" <?php selected($url_label, 'register'); ?>><?php esc_html_e('Register', 'buttercup'); ?></option>
                        <option value="custom" <?php selected($url_label, 'custom'); ?>><?php esc_html_e('Custom...', 'buttercup'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Text shown on the event page button.', 'buttercup'); ?></p>
                </div>

                <div class="buttercup-wizard__field" id="url-label-custom-field" style="<?php echo ($event_url && $url_label === 'custom') ? '' : 'display:none;'; ?>">
                    <label for="event_url_label_custom"><?php esc_html_e('Custom Button Text', 'buttercup'); ?></label>
                    <input type="text" name="event_url_label_custom" id="event_url_label_custom" class="large-text"
                           value="<?php echo esc_attr($url_label_custom); ?>"
                           placeholder="<?php esc_attr_e('e.g. RSVP Now', 'buttercup'); ?>" />
                </div>

                <div class="buttercup-wizard__field">
                    <label for="event_page_mode"><?php esc_html_e('Page Type', 'buttercup'); ?></label>
                    <select name="event_page_mode" id="event_page_mode">
                        <option value="template" <?php selected($page_mode, 'template'); ?>><?php esc_html_e('Standard Event Page — built-in layout with date, location, and details', 'buttercup'); ?></option>
                        <option value="editor" <?php selected($page_mode, 'editor'); ?>><?php esc_html_e('Custom Layout — design freely with the block editor (still under /events/)', 'buttercup'); ?></option>
                        <option value="standalone" <?php selected($page_mode, 'standalone'); ?>><?php esc_html_e('Dedicated Page — link an existing page or create a new root-level URL', 'buttercup'); ?></option>
                    </select>
                </div>

                <div class="buttercup-wizard__field" id="linked-page-field" style="<?php echo $page_mode === 'standalone' ? '' : 'display:none;'; ?>">
                    <label for="event_linked_page"><?php esc_html_e('Link to an existing page', 'buttercup'); ?></label>
                    <select name="event_linked_page" id="event_linked_page">
                        <option value=""><?php esc_html_e('— Select a page —', 'buttercup'); ?></option>
                        <?php
                        $pages = get_pages(['post_status' => 'publish', 'sort_column' => 'post_title']);
                        foreach ($pages as $page) {
                            printf(
                                '<option value="%d"%s>%s</option>',
                                $page->ID,
                                selected($linked_page, $page->ID, false),
                                esc_html($page->post_title)
                            );
                        }
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e('Visitors will be sent to this page when they click the event in listings.', 'buttercup'); ?></p>
                </div>

                <div class="buttercup-wizard__field" id="custom-slug-field" style="<?php echo ($page_mode === 'standalone' && !$linked_page) ? '' : 'display:none;'; ?>">
                    <label for="event_custom_slug"><?php esc_html_e('Or create a new root URL', 'buttercup'); ?></label>
                    <input type="text" name="event_custom_slug" id="event_custom_slug" class="large-text"
                           value="<?php echo esc_attr($custom_slug); ?>"
                           placeholder="<?php esc_attr_e('e.g. blockparty', 'buttercup'); ?>" />
                    <p class="description"><?php esc_html_e('Creates a page at yoursite.com/blockparty with full block editor control.', 'buttercup'); ?></p>
                </div>

                <div class="buttercup-wizard__field">
                    <label for="event_description"><?php esc_html_e('Description', 'buttercup'); ?></label>
                    <?php
                    wp_editor($post->post_content, 'event_description', [
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
                    <input type="hidden" name="event_cover_image_id" id="event_cover_image_id" value="<?php echo esc_attr($cover_id); ?>" />
                    <div class="buttercup-wizard__image-preview" id="cover-image-preview">
                        <?php if ($cover_id) : ?>
                            <?php $cover_url = wp_get_attachment_image_url($cover_id, 'medium'); ?>
                            <?php if ($cover_url) : ?>
                                <img src="<?php echo esc_url($cover_url); ?>" alt="" />
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <p>
                        <button type="button" class="button buttercup-wizard__image-select" data-target="cover-image">
                            <?php esc_html_e('Select Image', 'buttercup'); ?>
                        </button>
                        <button type="button" class="button-link-delete buttercup-wizard__image-clear" data-target="cover-image" style="<?php echo $cover_id ? '' : 'display:none;'; ?>">
                            <?php esc_html_e('Remove', 'buttercup'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <div class="buttercup-wizard__section">
                <h2><?php esc_html_e('Homepage Feed', 'buttercup'); ?></h2>

                <?php $has_homepage = $mobile_id || $desktop_id; ?>

                <label class="buttercup-wizard__toggle">
                    <input type="checkbox" name="event_homepage_enabled" id="event_homepage_enabled" value="1" <?php checked($has_homepage); ?> />
                    <?php esc_html_e('Feature this event on the homepage', 'buttercup'); ?>
                </label>

                <div class="buttercup-wizard__homepage-images" id="homepage-images-section" style="<?php echo $has_homepage ? '' : 'display:none;'; ?>">
                    <p class="description" style="margin-bottom:16px;">
                        <?php esc_html_e('Upload optimized images for the homepage column layout. These are separate from the cover image.', 'buttercup'); ?>
                    </p>

                    <div class="buttercup-wizard__field-row">
                        <div class="buttercup-wizard__field buttercup-wizard__field--half">
                            <label><?php esc_html_e('Mobile Thumbnail (5:3)', 'buttercup'); ?></label>
                            <p class="description"><?php esc_html_e('Suggested: 2000 × 1200', 'buttercup'); ?></p>
                            <div class="buttercup-wizard__image-field" id="mobile-image-field">
                                <input type="hidden" name="event_homepage_mobile_id" id="event_homepage_mobile_id" value="<?php echo esc_attr($mobile_id ?: ''); ?>" />
                                <div class="buttercup-wizard__image-preview" id="mobile-image-preview">
                                    <?php if ($mobile_id) : ?>
                                        <?php $mobile_url = wp_get_attachment_image_url($mobile_id, 'medium'); ?>
                                        <?php if ($mobile_url) : ?>
                                            <img src="<?php echo esc_url($mobile_url); ?>" alt="" />
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <p>
                                    <button type="button" class="button buttercup-wizard__image-select" data-target="mobile-image">
                                        <?php esc_html_e('Select Image', 'buttercup'); ?>
                                    </button>
                                    <button type="button" class="button-link-delete buttercup-wizard__image-clear" data-target="mobile-image" style="<?php echo $mobile_id ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e('Remove', 'buttercup'); ?>
                                    </button>
                                </p>
                            </div>
                        </div>

                        <div class="buttercup-wizard__field buttercup-wizard__field--half">
                            <label><?php esc_html_e('Desktop Thumbnail (2.74:1)', 'buttercup'); ?></label>
                            <p class="description"><?php esc_html_e('Suggested: 2880 × 1050', 'buttercup'); ?></p>
                            <div class="buttercup-wizard__image-field" id="desktop-image-field">
                                <input type="hidden" name="event_homepage_desktop_id" id="event_homepage_desktop_id" value="<?php echo esc_attr($desktop_id ?: ''); ?>" />
                                <div class="buttercup-wizard__image-preview" id="desktop-image-preview">
                                    <?php if ($desktop_id) : ?>
                                        <?php $desktop_url = wp_get_attachment_image_url($desktop_id, 'medium'); ?>
                                        <?php if ($desktop_url) : ?>
                                            <img src="<?php echo esc_url($desktop_url); ?>" alt="" />
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <p>
                                    <button type="button" class="button buttercup-wizard__image-select" data-target="desktop-image">
                                        <?php esc_html_e('Select Image', 'buttercup'); ?>
                                    </button>
                                    <button type="button" class="button-link-delete buttercup-wizard__image-clear" data-target="desktop-image" style="<?php echo $desktop_id ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e('Remove', 'buttercup'); ?>
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="buttercup-wizard__actions">
                <?php if ($is_published) : ?>
                    <?php submit_button(__('Update', 'buttercup'), 'primary large', 'submit', false, ['id' => 'buttercup-btn-update']); ?>
                    <a href="#" class="button-link-delete" id="buttercup-btn-draft">
                        <?php esc_html_e('Revert to Draft', 'buttercup'); ?>
                    </a>
                <?php else : ?>
                    <button type="submit" class="button button-large" id="buttercup-btn-save-draft">
                        <?php esc_html_e('Save Draft', 'buttercup'); ?>
                    </button>
                    <?php submit_button(__('Publish', 'buttercup'), 'primary large', 'submit', false, ['id' => 'buttercup-btn-publish']); ?>
                <?php endif; ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=buttercup_event')); ?>" class="button button-large">
                    <?php esc_html_e('Cancel', 'buttercup'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $post_id . '&action=edit&buttercup_skip_wizard')); ?>" class="buttercup-wizard__block-editor-link">
                    <?php esc_html_e('Use Block Editor →', 'buttercup'); ?>
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

        <style>
            .buttercup-wizard__block-editor-link {
                color: #888;
                font-size: 13px;
                text-decoration: none;
                margin-left: auto;
            }

            .buttercup-wizard__block-editor-link:hover {
                color: #2271b1;
            }
        </style>

        <script>
        (function() {
            var actionInput = document.getElementById('buttercup_submit_action');

            var publishBtn = document.getElementById('buttercup-btn-publish');
            if (publishBtn) {
                publishBtn.addEventListener('click', function() {
                    actionInput.value = 'publish';
                });
            }

            var saveDraftBtn = document.getElementById('buttercup-btn-save-draft');
            if (saveDraftBtn) {
                saveDraftBtn.addEventListener('click', function() {
                    actionInput.value = 'update';
                });
            }

            var updateBtn = document.getElementById('buttercup-btn-update');
            if (updateBtn) {
                updateBtn.addEventListener('click', function() {
                    actionInput.value = 'update';
                });
            }

            var draftBtn = document.getElementById('buttercup-btn-draft');
            if (draftBtn) {
                draftBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    actionInput.value = 'draft';
                    draftBtn.closest('form').submit();
                });
            }

            // Run initial field visibility on load.
            var urlInput = document.getElementById('event_url');
            if (urlInput) {
                urlInput.dispatchEvent(new Event('input'));
            }

            var pageModeSelect = document.getElementById('event_page_mode');
            if (pageModeSelect) {
                pageModeSelect.dispatchEvent(new Event('change'));
            }
        })();
        </script>
    </div>
    <?php
}
