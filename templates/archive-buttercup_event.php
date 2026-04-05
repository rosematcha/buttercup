<?php
/**
 * Archive template for Buttercup events.
 * Provides a TEC-style listing page at /events/.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$buttercup_view       = isset($_GET['event_view']) ? sanitize_key($_GET['event_view']) : 'upcoming';
$paged                = max(1, get_query_var('paged'));
$per_page             = 10;
$buttercup_now        = current_time('Y-m-d H:i:s');
$buttercup_is_past    = ($buttercup_view === 'past');

$buttercup_args = [
    'post_type'      => 'buttercup_event',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'post_status'    => 'publish',
    'meta_key'       => '_buttercup_event_start',
    'orderby'        => 'meta_value',
    'order'          => $buttercup_is_past ? 'DESC' : 'ASC',
    'meta_query'     => [
        [
            'key'     => '_buttercup_event_start',
            'value'   => $buttercup_now,
            'compare' => $buttercup_is_past ? '<' : '>=',
            'type'    => 'DATETIME',
        ],
    ],
];

$buttercup_events_query = new WP_Query($buttercup_args);
$buttercup_archive_url  = get_post_type_archive_link('buttercup_event');
?>

<div class="buttercup-events-archive">
    <div class="buttercup-events-archive__container">

        <header class="buttercup-events-archive__header">
            <h1 class="buttercup-events-archive__title"><?php esc_html_e('Events', 'buttercup'); ?></h1>

            <nav class="buttercup-events-archive__view-toggle" aria-label="<?php esc_attr_e('Event view', 'buttercup'); ?>">
                <a href="<?php echo esc_url($buttercup_archive_url); ?>"
                   class="buttercup-events-archive__view-btn <?php echo !$buttercup_is_past ? 'active' : ''; ?>"
                   <?php echo !$buttercup_is_past ? 'aria-current="page"' : ''; ?>>
                    <?php esc_html_e('Upcoming', 'buttercup'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('event_view', 'past', $buttercup_archive_url)); ?>"
                   class="buttercup-events-archive__view-btn <?php echo $buttercup_is_past ? 'active' : ''; ?>"
                   <?php echo $buttercup_is_past ? 'aria-current="page"' : ''; ?>>
                    <?php esc_html_e('Past Events', 'buttercup'); ?>
                </a>
            </nav>
        </header>

        <?php if ($buttercup_events_query->have_posts()) : ?>

            <div class="buttercup-events" role="list">
                <?php
                $buttercup_current_month = '';

                while ($buttercup_events_query->have_posts()) :
                    $buttercup_events_query->the_post();
                    $post_id              = get_the_ID();
                    $buttercup_start      = get_post_meta($post_id, '_buttercup_event_start', true);
                    $buttercup_end        = get_post_meta($post_id, '_buttercup_event_end', true);
                    $buttercup_start_allday = (bool) get_post_meta($post_id, '_buttercup_event_start_allday', true);
                    $buttercup_end_allday   = (bool) get_post_meta($post_id, '_buttercup_event_end_allday', true);
                    $buttercup_location   = get_post_meta($post_id, '_buttercup_event_location', true);
                    $title                = get_the_title();
                    $link                 = get_permalink();
                    $buttercup_excerpt    = get_the_excerpt();

                    $buttercup_start_ts = $buttercup_start ? buttercup_event_timestamp($buttercup_start) : 0;

                    // Month header.
                    if ($buttercup_start_ts) {
                        $buttercup_month_year = wp_date('F Y', $buttercup_start_ts);

                        if ($buttercup_month_year !== $buttercup_current_month) {
                            $buttercup_current_month = $buttercup_month_year;
                            ?>
                            <div class="buttercup-events__month-header" role="presentation">
                                <span class="buttercup-events__month-label"><?php echo esc_html($buttercup_month_year); ?></span>
                            </div>
                            <?php
                        }
                    }
                    ?>

                    <article class="buttercup-events__item" role="listitem">
                        <div class="buttercup-events__date-col">
                            <?php if ($buttercup_start_ts) :
                                $buttercup_day_abbr = strtoupper(wp_date('D', $buttercup_start_ts));
                                $buttercup_day_num  = wp_date('j', $buttercup_start_ts);
                            ?>
                                <span class="buttercup-events__day-abbr"><?php echo esc_html($buttercup_day_abbr); ?></span>
                                <span class="buttercup-events__day-num"><?php echo esc_html($buttercup_day_num); ?></span>
                            <?php else : ?>
                                <span class="buttercup-events__day-abbr">&mdash;</span>
                                <span class="buttercup-events__day-num">&ndash;</span>
                            <?php endif; ?>
                        </div>

                        <div class="buttercup-events__content">
                            <?php if ($buttercup_start_ts) : ?>
                                <div class="buttercup-events__datetime">
                                    <?php echo esc_html(buttercup_format_event_date_range($buttercup_start, $buttercup_end, $buttercup_start_allday, $buttercup_end_allday)); ?>
                                </div>
                            <?php endif; ?>

                            <h3 class="buttercup-events__title">
                                <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a>
                            </h3>

                            <?php if ($buttercup_location) : ?>
                                <div class="buttercup-events__location"><?php echo esc_html($buttercup_location); ?></div>
                            <?php endif; ?>

                            <?php if ($buttercup_excerpt) : ?>
                                <div class="buttercup-events__excerpt"><?php echo esc_html(wp_trim_words($buttercup_excerpt, 30, '...')); ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if (has_post_thumbnail()) :
                            $buttercup_thumb_id   = get_post_thumbnail_id();
                            $buttercup_candidates = buttercup_build_image_candidates($buttercup_thumb_id);
                            $buttercup_best       = buttercup_pick_best_candidate($buttercup_candidates, 300);
                            $buttercup_srcset     = buttercup_build_srcset($buttercup_candidates);

                            if ($buttercup_best) :
                        ?>
                            <div class="buttercup-events__image">
                                <a href="<?php echo esc_url($link); ?>" tabindex="-1" aria-hidden="true">
                                    <img src="<?php echo esc_url($buttercup_best['url']); ?>"
                                        <?php if ($buttercup_srcset) : ?>
                                            srcset="<?php echo esc_attr($buttercup_srcset); ?>"
                                            sizes="(max-width: 600px) 100vw, 250px"
                                        <?php endif; ?>
                                        alt=""
                                        loading="lazy" />
                                </a>
                            </div>
                        <?php
                            endif;
                        endif;
                        ?>
                    </article>

                <?php endwhile; ?>
            </div>

            <?php
            // Pagination.
            $buttercup_total_pages = $buttercup_events_query->max_num_pages;
            if ($buttercup_total_pages > 1) :
            ?>
                <nav class="buttercup-events-archive__pagination" aria-label="<?php esc_attr_e('Events pagination', 'buttercup'); ?>">
                    <div class="buttercup-events-archive__nav-prev">
                        <?php if ($paged > 1) : ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">
                                <span aria-hidden="true">&lsaquo;</span> <?php esc_html_e('Previous Events', 'buttercup'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="buttercup-events-archive__nav-next">
                        <?php if ($paged < $buttercup_total_pages) : ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">
                                <?php esc_html_e('Next Events', 'buttercup'); ?> <span aria-hidden="true">&rsaquo;</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>

        <?php else : ?>
            <p class="buttercup-events__empty">
                <?php
                if ($buttercup_is_past) {
                    esc_html_e('There are no past events.', 'buttercup');
                } else {
                    esc_html_e('There are no upcoming events at this time.', 'buttercup');
                }
                ?>
            </p>
        <?php endif; ?>

    </div>
</div>

<?php
wp_reset_postdata();
get_footer();
