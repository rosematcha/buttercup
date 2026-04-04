<?php
/**
 * Archive template for Buttercup events.
 * Provides a TEC-style listing page at /events/.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$view       = isset($_GET['event_view']) ? sanitize_key($_GET['event_view']) : 'upcoming';
$paged      = max(1, get_query_var('paged'));
$per_page   = 10;
$now        = current_time('Y-m-d H:i:s');
$is_past    = ($view === 'past');

$args = [
    'post_type'      => 'buttercup_event',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'post_status'    => 'publish',
    'meta_key'       => '_buttercup_event_start',
    'orderby'        => 'meta_value',
    'order'          => $is_past ? 'DESC' : 'ASC',
    'meta_query'     => [
        [
            'key'     => '_buttercup_event_start',
            'value'   => $now,
            'compare' => $is_past ? '<' : '>=',
            'type'    => 'DATETIME',
        ],
    ],
];

$events_query = new WP_Query($args);
$archive_url  = get_post_type_archive_link('buttercup_event');
?>

<div class="buttercup-events-archive">
    <div class="buttercup-events-archive__container">

        <header class="buttercup-events-archive__header">
            <h1 class="buttercup-events-archive__title"><?php esc_html_e('Events', 'buttercup'); ?></h1>

            <nav class="buttercup-events-archive__view-toggle" aria-label="<?php esc_attr_e('Event view', 'buttercup'); ?>">
                <a href="<?php echo esc_url($archive_url); ?>"
                   class="buttercup-events-archive__view-btn <?php echo !$is_past ? 'active' : ''; ?>"
                   <?php echo !$is_past ? 'aria-current="page"' : ''; ?>>
                    <?php esc_html_e('Upcoming', 'buttercup'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('event_view', 'past', $archive_url)); ?>"
                   class="buttercup-events-archive__view-btn <?php echo $is_past ? 'active' : ''; ?>"
                   <?php echo $is_past ? 'aria-current="page"' : ''; ?>>
                    <?php esc_html_e('Past Events', 'buttercup'); ?>
                </a>
            </nav>
        </header>

        <?php if ($events_query->have_posts()) : ?>

            <div class="buttercup-events" role="list">
                <?php
                $current_month = '';

                while ($events_query->have_posts()) :
                    $events_query->the_post();
                    $post_id  = get_the_ID();
                    $start        = get_post_meta($post_id, '_buttercup_event_start', true);
                    $end          = get_post_meta($post_id, '_buttercup_event_end', true);
                    $start_allday = (bool) get_post_meta($post_id, '_buttercup_event_start_allday', true);
                    $end_allday   = (bool) get_post_meta($post_id, '_buttercup_event_end_allday', true);
                    $location     = get_post_meta($post_id, '_buttercup_event_location', true);
                    $title    = get_the_title();
                    $link     = get_permalink();
                    $excerpt  = get_the_excerpt();

                    $start_ts = $start ? buttercup_event_timestamp($start) : 0;

                    // Month header.
                    if ($start_ts) {
                        $month_year = wp_date('F Y', $start_ts);

                        if ($month_year !== $current_month) {
                            $current_month = $month_year;
                            ?>
                            <div class="buttercup-events__month-header" role="presentation">
                                <span class="buttercup-events__month-label"><?php echo esc_html($month_year); ?></span>
                            </div>
                            <?php
                        }
                    }
                    ?>

                    <article class="buttercup-events__item" role="listitem">
                        <div class="buttercup-events__date-col">
                            <?php if ($start_ts) :
                                $day_abbr = strtoupper(wp_date('D', $start_ts));
                                $day_num  = wp_date('j', $start_ts);
                            ?>
                                <span class="buttercup-events__day-abbr"><?php echo esc_html($day_abbr); ?></span>
                                <span class="buttercup-events__day-num"><?php echo esc_html($day_num); ?></span>
                            <?php else : ?>
                                <span class="buttercup-events__day-abbr">&mdash;</span>
                                <span class="buttercup-events__day-num">&ndash;</span>
                            <?php endif; ?>
                        </div>

                        <div class="buttercup-events__content">
                            <?php if ($start_ts) : ?>
                                <div class="buttercup-events__datetime">
                                    <?php echo esc_html(buttercup_format_event_date_range($start, $end, $start_allday, $end_allday)); ?>
                                </div>
                            <?php endif; ?>

                            <h3 class="buttercup-events__title">
                                <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a>
                            </h3>

                            <?php if ($location) : ?>
                                <div class="buttercup-events__location"><?php echo esc_html($location); ?></div>
                            <?php endif; ?>

                            <?php if ($excerpt) : ?>
                                <div class="buttercup-events__excerpt"><?php echo esc_html(wp_trim_words($excerpt, 30, '...')); ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if (has_post_thumbnail()) :
                            $thumb_id   = get_post_thumbnail_id();
                            $candidates = buttercup_build_image_candidates($thumb_id);
                            $best       = buttercup_pick_best_candidate($candidates, 300);
                            $srcset     = buttercup_build_srcset($candidates);

                            if ($best) :
                        ?>
                            <div class="buttercup-events__image">
                                <a href="<?php echo esc_url($link); ?>" tabindex="-1" aria-hidden="true">
                                    <img src="<?php echo esc_url($best['url']); ?>"
                                        <?php if ($srcset) : ?>
                                            srcset="<?php echo esc_attr($srcset); ?>"
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
            $total_pages = $events_query->max_num_pages;
            if ($total_pages > 1) :
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
                        <?php if ($paged < $total_pages) : ?>
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
                if ($is_past) {
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
