<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render callback for the buttercup/events block.
 */
function buttercup_render_events($attributes, $content, $block)
{
    $display_mode   = $attributes['displayMode'] ?? 'upcoming';
    $default_count  = intval(get_option('buttercup_events_per_page', 6));
    $events_to_show = max(1, min(50, intval($attributes['eventsToShow'] ?? $default_count)));
    $show_past_link = !empty($attributes['showPastEventsLink']);

    $cache_key = buttercup_build_cache_key('events', [$display_mode, $events_to_show, $show_past_link]);
    $cached    = buttercup_cache_get($cache_key);
    if ($cached !== null) {
        return $cached;
    }

    if ($display_mode === 'past') {
        $html = buttercup_render_events_past($events_to_show);
    } else {
        $html = buttercup_render_events_upcoming($events_to_show, $show_past_link);
    }

    buttercup_cache_set($cache_key, $html);
    return $html;
}

/**
 * Render upcoming events.
 */
function buttercup_render_events_upcoming($count, $show_past_link = true)
{
    $now = current_time('Y-m-d H:i:s');

    $query = new WP_Query([
        'post_type'      => 'buttercup_event',
        'posts_per_page' => $count,
        'post_status'    => 'publish',
        'meta_key'       => '_buttercup_event_start',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => '_buttercup_event_start',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ],
        ],
    ]);

    $html = '<div class="buttercup-events buttercup-events--upcoming">';

    if ($query->have_posts()) {
        $html .= buttercup_render_event_list($query);
    } else {
        $html .= '<p class="buttercup-events__empty">' . esc_html__('There are no upcoming events at this time.', 'buttercup') . '</p>';
    }

    if ($show_past_link) {
        $archive_url = get_post_type_archive_link('buttercup_event');
        if ($archive_url) {
            $html .= '<div class="buttercup-events__nav">';
            $html .= '<span class="buttercup-events__nav-prev"></span>';
            $html .= '<a href="' . esc_url($archive_url) . '?event_view=past" class="buttercup-events__nav-next">';
            $html .= esc_html__('Previous Events', 'buttercup');
            $html .= ' <span aria-hidden="true">&rsaquo;</span></a>';
            $html .= '</div>';
        }
    }

    $html .= '</div>';
    wp_reset_postdata();

    return $html;
}

/**
 * Render past events.
 */
function buttercup_render_events_past($count)
{
    $now = current_time('Y-m-d H:i:s');

    $query = new WP_Query([
        'post_type'      => 'buttercup_event',
        'posts_per_page' => $count,
        'post_status'    => 'publish',
        'meta_key'       => '_buttercup_event_start',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'     => '_buttercup_event_start',
                'value'   => $now,
                'compare' => '<',
                'type'    => 'DATETIME',
            ],
        ],
    ]);

    $html = '<div class="buttercup-events buttercup-events--past">';

    if ($query->have_posts()) {
        $html .= buttercup_render_event_list($query);
    } else {
        $html .= '<p class="buttercup-events__empty">' . esc_html__('There are no past events.', 'buttercup') . '</p>';
    }

    $html .= '</div>';
    wp_reset_postdata();

    return $html;
}

/**
 * Render a list of events grouped by month, TEC-style.
 */
function buttercup_render_event_list($query)
{
    $html = '';
    $current_month = '';

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $start   = get_post_meta($post_id, '_buttercup_event_start', true);

        // Group by month header.
        if ($start) {
            $start_ts   = buttercup_event_timestamp($start);
            $month_year = wp_date('F Y', $start_ts);

            if ($month_year !== $current_month) {
                $current_month = $month_year;
                $html .= '<div class="buttercup-events__month-header">';
                $html .= '<span class="buttercup-events__month-label">' . esc_html($month_year) . '</span>';
                $html .= '</div>';
            }
        }

        $html .= buttercup_render_event_card($post_id);
    }

    return $html;
}

/**
 * Render a single event card in TEC list style.
 */
function buttercup_render_event_card($post_id)
{
    $start        = get_post_meta($post_id, '_buttercup_event_start', true);
    $end          = get_post_meta($post_id, '_buttercup_event_end', true);
    $start_allday = (bool) get_post_meta($post_id, '_buttercup_event_start_allday', true);
    $end_allday   = (bool) get_post_meta($post_id, '_buttercup_event_end_allday', true);
    $location     = get_post_meta($post_id, '_buttercup_event_location', true);
    $title        = get_the_title($post_id);
    $link         = get_permalink($post_id);
    $excerpt      = get_the_excerpt($post_id);

    $html = '<article class="buttercup-events__item">';

    // Date column (always present for consistent layout).
    $html .= '<div class="buttercup-events__date-col">';
    if ($start) {
        $start_ts = buttercup_event_timestamp($start);
        $day_abbr = wp_date('D', $start_ts);
        $day_num  = wp_date('j', $start_ts);

        $html .= '<span class="buttercup-events__day-abbr">' . esc_html(strtoupper($day_abbr)) . '</span>';
        $html .= '<span class="buttercup-events__day-num">' . esc_html($day_num) . '</span>';
    } else {
        $html .= '<span class="buttercup-events__day-abbr">&mdash;</span>';
        $html .= '<span class="buttercup-events__day-num">&ndash;</span>';
    }
    $html .= '</div>';

    // Content column (middle).
    $html .= '<div class="buttercup-events__content">';

    // Date/time range.
    if ($start) {
        $html .= '<div class="buttercup-events__datetime">';
        $html .= esc_html(buttercup_format_event_date_range($start, $end, $start_allday, $end_allday));
        $html .= '</div>';
    }

    // Title.
    $html .= '<h3 class="buttercup-events__title">';
    $html .= '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>';
    $html .= '</h3>';

    // Location.
    if ($location) {
        $html .= '<div class="buttercup-events__location">' . esc_html($location) . '</div>';
    }

    // Excerpt.
    if ($excerpt) {
        $html .= '<div class="buttercup-events__excerpt">' . esc_html(wp_trim_words($excerpt, 30, '...')) . '</div>';
    }

    $html .= '</div>';

    // Thumbnail (right side).
    if (has_post_thumbnail($post_id)) {
        $thumb_id   = get_post_thumbnail_id($post_id);
        $candidates = buttercup_build_image_candidates($thumb_id);
        $best       = buttercup_pick_best_candidate($candidates, 300);
        $srcset     = buttercup_build_srcset($candidates);

        if ($best) {
            $html .= '<div class="buttercup-events__image">';
            $html .= '<a href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true">';
            $html .= '<img src="' . esc_url($best['url']) . '"';
            if ($srcset) {
                $html .= ' srcset="' . esc_attr($srcset) . '"';
                $html .= ' sizes="(max-width: 600px) 100vw, 250px"';
            }
            $html .= ' alt="" loading="lazy" />';
            $html .= '</a></div>';
        }
    }

    $html .= '</article>';

    return $html;
}
