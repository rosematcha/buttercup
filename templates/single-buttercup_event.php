<?php
/**
 * Template for displaying a single Buttercup event.
 * Styled to match The Events Calendar layout.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $event_meta = buttercup_get_event_meta(get_the_ID());
    $start_ts   = $event_meta['start'] ? buttercup_event_timestamp($event_meta['start']) : 0;
    $end_ts     = $event_meta['end'] ? buttercup_event_timestamp($event_meta['end']) : 0;
    $time_format = get_option('time_format', 'g:i a');

    // Format display values.
    $date_range  = buttercup_format_event_date_range($event_meta['start'], $event_meta['end'], $event_meta['start_allday'], $event_meta['end_allday']);

    // For the details box, separate date and time.
    $detail_date = $start_ts ? wp_date('F j', $start_ts) : '';
    $detail_time = '';
    if ($start_ts && !$event_meta['start_allday']) {
        $detail_time = wp_date($time_format, $start_ts);
        if ($end_ts && !$event_meta['end_allday']) {
            $detail_time .= ' - ' . wp_date($time_format, $end_ts);
        }
    }

    // Check if meta box has any content to show.
    $has_details = $detail_date || $detail_time || !empty($event_meta['url']);
    $has_venue   = !empty($event_meta['location']);
    $show_meta_box = $has_details || $has_venue;

    // Navigation: previous/next events by event start date.
    $prev_event = buttercup_get_adjacent_event(get_the_ID(), 'previous');
    $next_event = buttercup_get_adjacent_event(get_the_ID(), 'next');
?>

<div class="buttercup-single-event">
    <div class="buttercup-single-event__container">

        <nav class="buttercup-single-event__back" aria-label="<?php esc_attr_e('Back to events', 'buttercup'); ?>">
            <a href="<?php echo esc_url(get_post_type_archive_link('buttercup_event')); ?>">
                &laquo; <?php esc_html_e('All Events', 'buttercup'); ?>
            </a>
        </nav>

        <article id="post-<?php the_ID(); ?>" <?php post_class('buttercup-single-event__article'); ?>>

            <header class="buttercup-single-event__header">
                <h1 class="buttercup-single-event__title"><?php the_title(); ?></h1>

                <?php if ($date_range) : ?>
                    <div class="buttercup-single-event__date"><?php echo esc_html($date_range); ?></div>
                <?php endif; ?>
            </header>

            <?php if (has_post_thumbnail()) : ?>
                <div class="buttercup-single-event__image">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>

            <div class="buttercup-single-event__body">
                <?php the_content(); ?>
            </div>

            <?php if (!empty($event_meta['url'])) : ?>
                <div class="buttercup-single-event__cta">
                    <a href="<?php echo esc_url($event_meta['url']); ?>" target="_blank" rel="noopener noreferrer" class="buttercup-single-event__cta-button">
                        <?php echo esc_html($event_meta['url_label']); ?> &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($show_meta_box) : ?>
                <div class="buttercup-single-event__meta-box">
                    <?php if ($has_details) : ?>
                        <div class="buttercup-single-event__details">
                            <h4 class="buttercup-single-event__meta-heading"><?php esc_html_e('DETAILS', 'buttercup'); ?></h4>
                            <dl class="buttercup-single-event__detail-list">
                                <?php if ($detail_date) : ?>
                                    <dt><?php esc_html_e('Date:', 'buttercup'); ?></dt>
                                    <dd><?php echo esc_html($detail_date); ?></dd>
                                <?php endif; ?>

                                <?php if ($detail_time) : ?>
                                    <dt><?php esc_html_e('Time:', 'buttercup'); ?></dt>
                                    <dd><?php echo esc_html($detail_time); ?></dd>
                                <?php endif; ?>

                                <?php if (!empty($event_meta['url'])) : ?>
                                    <dt><?php esc_html_e('Website:', 'buttercup'); ?></dt>
                                    <dd><a href="<?php echo esc_url($event_meta['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($event_meta['url']); ?></a></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <?php if ($has_venue) : ?>
                        <div class="buttercup-single-event__venue">
                            <h4 class="buttercup-single-event__meta-heading"><?php esc_html_e('VENUE', 'buttercup'); ?></h4>
                            <p class="buttercup-single-event__venue-address"><?php echo esc_html($event_meta['location']); ?></p>
                            <?php
                            $maps_query = urlencode($event_meta['location']);
                            ?>
                            <a href="<?php echo esc_url("https://www.google.com/maps/search/?api=1&query={$maps_query}"); ?>" target="_blank" rel="noopener noreferrer" class="buttercup-single-event__maps-link">
                                + <?php esc_html_e('Google Map', 'buttercup'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </article>

        <?php if ($prev_event || $next_event) : ?>
            <nav class="buttercup-single-event__nav" aria-label="<?php esc_attr_e('Event navigation', 'buttercup'); ?>">
                <div class="buttercup-single-event__nav-prev">
                    <?php if ($prev_event) : ?>
                        <a href="<?php echo esc_url(get_permalink($prev_event)); ?>">
                            <span aria-hidden="true">&lsaquo;</span> <?php echo esc_html(get_the_title($prev_event)); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="buttercup-single-event__nav-next">
                    <?php if ($next_event) : ?>
                        <a href="<?php echo esc_url(get_permalink($next_event)); ?>">
                            <?php echo esc_html(get_the_title($next_event)); ?> <span aria-hidden="true">&rsaquo;</span>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>

    </div>
</div>

<?php
endwhile;

get_footer();
