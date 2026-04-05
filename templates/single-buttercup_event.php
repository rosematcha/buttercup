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

    $buttercup_event_meta = buttercup_get_event_meta(get_the_ID());
    $buttercup_start_ts   = $buttercup_event_meta['start'] ? buttercup_event_timestamp($buttercup_event_meta['start']) : 0;
    $buttercup_end_ts     = $buttercup_event_meta['end'] ? buttercup_event_timestamp($buttercup_event_meta['end']) : 0;
    $buttercup_time_format = get_option('time_format', 'g:i a');

    // Format display values.
    $buttercup_date_range  = buttercup_format_event_date_range($buttercup_event_meta['start'], $buttercup_event_meta['end'], $buttercup_event_meta['start_allday'], $buttercup_event_meta['end_allday']);

    // For the details box, separate date and time.
    $buttercup_detail_date = $buttercup_start_ts ? wp_date('F j', $buttercup_start_ts) : '';
    $buttercup_detail_time = '';
    if ($buttercup_start_ts && !$buttercup_event_meta['start_allday']) {
        $buttercup_detail_time = wp_date($buttercup_time_format, $buttercup_start_ts);
        if ($buttercup_end_ts && !$buttercup_event_meta['end_allday']) {
            $buttercup_detail_time .= ' - ' . wp_date($buttercup_time_format, $buttercup_end_ts);
        }
    }

    // Check if meta box has any content to show.
    $buttercup_has_details = $buttercup_detail_date || $buttercup_detail_time || !empty($buttercup_event_meta['url']);
    $buttercup_has_venue   = !empty($buttercup_event_meta['location']);
    $buttercup_show_meta_box = $buttercup_has_details || $buttercup_has_venue;

    // Navigation: previous/next events by event start date.
    $buttercup_prev_event = buttercup_get_adjacent_event(get_the_ID(), 'previous');
    $buttercup_next_event = buttercup_get_adjacent_event(get_the_ID(), 'next');
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

                <?php if ($buttercup_date_range) : ?>
                    <div class="buttercup-single-event__date"><?php echo esc_html($buttercup_date_range); ?></div>
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

            <?php if (!empty($buttercup_event_meta['url'])) : ?>
                <div class="buttercup-single-event__cta">
                    <a href="<?php echo esc_url($buttercup_event_meta['url']); ?>" target="_blank" rel="noopener noreferrer" class="buttercup-single-event__cta-button">
                        <?php echo esc_html($buttercup_event_meta['url_label']); ?> &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($buttercup_show_meta_box) : ?>
                <div class="buttercup-single-event__meta-box">
                    <?php if ($buttercup_has_details) : ?>
                        <div class="buttercup-single-event__details">
                            <h4 class="buttercup-single-event__meta-heading"><?php esc_html_e('DETAILS', 'buttercup'); ?></h4>
                            <dl class="buttercup-single-event__detail-list">
                                <?php if ($buttercup_detail_date) : ?>
                                    <dt><?php esc_html_e('Date:', 'buttercup'); ?></dt>
                                    <dd><?php echo esc_html($buttercup_detail_date); ?></dd>
                                <?php endif; ?>

                                <?php if ($buttercup_detail_time) : ?>
                                    <dt><?php esc_html_e('Time:', 'buttercup'); ?></dt>
                                    <dd><?php echo esc_html($buttercup_detail_time); ?></dd>
                                <?php endif; ?>

                                <?php if (!empty($buttercup_event_meta['url'])) : ?>
                                    <dt><?php esc_html_e('Website:', 'buttercup'); ?></dt>
                                    <dd><a href="<?php echo esc_url($buttercup_event_meta['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($buttercup_event_meta['url']); ?></a></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <?php if ($buttercup_has_venue) : ?>
                        <div class="buttercup-single-event__venue">
                            <h4 class="buttercup-single-event__meta-heading"><?php esc_html_e('VENUE', 'buttercup'); ?></h4>
                            <p class="buttercup-single-event__venue-address"><?php echo esc_html($buttercup_event_meta['location']); ?></p>
                            <?php
                            $buttercup_maps_query = rawurlencode($buttercup_event_meta['location']);
                            ?>
                            <a href="<?php echo esc_url("https://www.google.com/maps/search/?api=1&query={$buttercup_maps_query}"); ?>" target="_blank" rel="noopener noreferrer" class="buttercup-single-event__maps-link">
                                + <?php esc_html_e('Google Map', 'buttercup'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </article>

        <?php if ($buttercup_prev_event || $buttercup_next_event) : ?>
            <nav class="buttercup-single-event__nav" aria-label="<?php esc_attr_e('Event navigation', 'buttercup'); ?>">
                <div class="buttercup-single-event__nav-prev">
                    <?php if ($buttercup_prev_event) : ?>
                        <a href="<?php echo esc_url(get_permalink($buttercup_prev_event)); ?>">
                            <span aria-hidden="true">&lsaquo;</span> <?php echo esc_html(get_the_title($buttercup_prev_event)); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="buttercup-single-event__nav-next">
                    <?php if ($buttercup_next_event) : ?>
                        <a href="<?php echo esc_url(get_permalink($buttercup_next_event)); ?>">
                            <?php echo esc_html(get_the_title($buttercup_next_event)); ?> <span aria-hidden="true">&rsaquo;</span>
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
