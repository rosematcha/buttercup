<?php

if (!defined('ABSPATH')) {
    exit;
}

function buttercup_tag_showcase_sanitize_css_color($value)
{
    $color = trim((string) $value);
    if ($color === '') {
        return '';
    }

    if (strpos($color, ';') !== false || strpos($color, '{') !== false || strpos($color, '}') !== false) {
        return '';
    }

    if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $color)) {
        return $color;
    }

    $hex = sanitize_hex_color($color);
    if ($hex) {
        return $hex;
    }

    if (preg_match('/^rgba?\([0-9.,%\s]+\)$/', $color)) {
        return $color;
    }

    return '';
}

function buttercup_tag_showcase_get_valid_post_types()
{
    $valid = [];
    $objects = get_post_types(['public' => true], 'objects');

    foreach ($objects as $type => $obj) {
        if (!$obj || empty($obj->show_in_rest)) {
            continue;
        }
        if (in_array($type, ['attachment', 'revision', 'nav_menu_item', 'wp_block'], true)) {
            continue;
        }
        if (!is_object_in_taxonomy($type, 'post_tag')) {
            continue;
        }
        $valid[] = $type;
    }

    return $valid;
}

function buttercup_tag_showcase_get_current_post_id()
{
    $post_id = get_the_ID();
    if ($post_id > 0) {
        return intval($post_id);
    }

    if (isset($_GET['post_id'])) {
        $get_post_id = absint(wp_unslash($_GET['post_id']));
        if ($get_post_id > 0) {
            return $get_post_id;
        }
    }

    if (isset($_GET['post'])) {
        $get_post = absint(wp_unslash($_GET['post']));
        if ($get_post > 0) {
            return $get_post;
        }
    }

    $queried = get_queried_object();
    if (is_object($queried) && isset($queried->ID)) {
        return intval($queried->ID);
    }

    return 0;
}

function buttercup_tag_showcase_sanitize_attributes($attributes)
{
    $defaults = [
        'tagSlugs' => [],
        'tagMatch' => 'any',
        'enableMultiTag' => false,
        'postTypes' => ['post', 'page'],
        'orderBy' => 'date',
        'order' => 'desc',
        'maxItems' => 12,
        'offset' => 0,
        'excludeCurrentPost' => false,
        'showThumbnail' => true,
        'showTitle' => true,
        'showSnippet' => true,
        'showButton' => true,
        'showType' => false,
        'showDate' => false,
        'snippetWords' => 20,
        'buttonLabel' => __('Read More', 'buttercup'),
        'clickMode' => 'card-cta',
        'openInNewTab' => false,
        'buttonStyle' => 'solid',
        'buttonBackground' => '',
        'buttonTextColor' => '',
        'buttonBorderColor' => '',
        'buttonRadius' => 8,
        'buttonPaddingY' => 10,
        'buttonPaddingX' => 16,
        'buttonFontSize' => 16,
        'minWidthDesktop' => 260,
        'minWidthTablet' => 220,
        'minWidthMobile' => 160,
        'maxColsDesktop' => 4,
        'maxColsTablet' => 3,
        'maxColsMobile' => 2,
        'columnGap' => 24,
        'rowGap' => 24,
        'textAlign' => 'left',
        'imageAspectRatio' => '16/9',
        'cardPadding' => 20,
        'cardRadius' => 10,
        'cardBackground' => '',
    ];

    $attrs = wp_parse_args(is_array($attributes) ? $attributes : [], $defaults);

    $tag_slugs = [];
    if (is_array($attrs['tagSlugs'])) {
        foreach ($attrs['tagSlugs'] as $tag_slug) {
            $clean = sanitize_title((string) $tag_slug);
            if ($clean !== '') {
                $tag_slugs[] = $clean;
            }
        }
    }
    $tag_slugs = array_values(array_unique($tag_slugs));

    $enable_multi_tag = !empty($attrs['enableMultiTag']);
    if (!$enable_multi_tag && count($tag_slugs) > 1) {
        $tag_slugs = [reset($tag_slugs)];
    }

    $tag_match = strtolower((string) $attrs['tagMatch']);
    if (!in_array($tag_match, ['any', 'all'], true)) {
        $tag_match = 'any';
    }
    if (!$enable_multi_tag) {
        $tag_match = 'any';
    }

    $valid_post_types = buttercup_tag_showcase_get_valid_post_types();
    $post_types = [];
    if (is_array($attrs['postTypes'])) {
        foreach ($attrs['postTypes'] as $post_type) {
            $post_type = sanitize_key((string) $post_type);
            if (in_array($post_type, $valid_post_types, true)) {
                $post_types[] = $post_type;
            }
        }
    }
    $post_types = array_values(array_unique($post_types));
    if (empty($post_types)) {
        $fallback = array_values(array_intersect(['post', 'page'], $valid_post_types));
        $post_types = !empty($fallback) ? $fallback : $valid_post_types;
    }

    $order_by = strtolower((string) $attrs['orderBy']);
    if (!in_array($order_by, ['date', 'title', 'modified'], true)) {
        $order_by = 'date';
    }

    $order = strtolower((string) $attrs['order']);
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'desc';
    }

    $click_mode = strtolower((string) $attrs['clickMode']);
    if (!in_array($click_mode, ['card-cta', 'cta-only', 'card-only'], true)) {
        $click_mode = 'card-cta';
    }

    $button_style = strtolower((string) $attrs['buttonStyle']);
    if (!in_array($button_style, ['solid', 'outline', 'text'], true)) {
        $button_style = 'solid';
    }

    $text_align = strtolower((string) $attrs['textAlign']);
    if (!in_array($text_align, ['left', 'center', 'right'], true)) {
        $text_align = 'left';
    }

    $image_aspect_ratio = strtolower((string) $attrs['imageAspectRatio']);
    if (!in_array($image_aspect_ratio, ['16/9', '4/3', '3/2', '1/1', '2/3', 'auto'], true)) {
        $image_aspect_ratio = '16/9';
    }

    $button_label = trim((string) $attrs['buttonLabel']);
    if ($button_label === '') {
        $button_label = __('Read More', 'buttercup');
    }

    return [
        'tag_slugs' => $tag_slugs,
        'tag_match' => $tag_match,
        'enable_multi_tag' => $enable_multi_tag,
        'post_types' => $post_types,
        'order_by' => $order_by,
        'order' => $order,
        'max_items' => max(1, min(60, intval($attrs['maxItems']))),
        'offset' => max(0, min(50, intval($attrs['offset']))),
        'exclude_current_post' => !empty($attrs['excludeCurrentPost']),
        'show_thumbnail' => !empty($attrs['showThumbnail']),
        'show_title' => !empty($attrs['showTitle']),
        'show_snippet' => !empty($attrs['showSnippet']),
        'show_button' => !empty($attrs['showButton']),
        'show_type' => !empty($attrs['showType']),
        'show_date' => !empty($attrs['showDate']),
        'snippet_words' => max(5, min(80, intval($attrs['snippetWords']))),
        'button_label' => $button_label,
        'click_mode' => $click_mode,
        'open_in_new_tab' => !empty($attrs['openInNewTab']),
        'button_style' => $button_style,
        'button_background' => buttercup_tag_showcase_sanitize_css_color($attrs['buttonBackground']),
        'button_text_color' => buttercup_tag_showcase_sanitize_css_color($attrs['buttonTextColor']),
        'button_border_color' => buttercup_tag_showcase_sanitize_css_color($attrs['buttonBorderColor']),
        'button_radius' => max(0, min(30, intval($attrs['buttonRadius']))),
        'button_padding_y' => max(0, min(24, intval($attrs['buttonPaddingY']))),
        'button_padding_x' => max(0, min(40, intval($attrs['buttonPaddingX']))),
        'button_font_size' => max(12, min(28, intval($attrs['buttonFontSize']))),
        'min_width_desktop' => max(140, min(600, intval($attrs['minWidthDesktop']))),
        'min_width_tablet' => max(120, min(420, intval($attrs['minWidthTablet']))),
        'min_width_mobile' => max(100, min(320, intval($attrs['minWidthMobile']))),
        'max_cols_desktop' => max(1, min(8, intval($attrs['maxColsDesktop']))),
        'max_cols_tablet' => max(1, min(6, intval($attrs['maxColsTablet']))),
        'max_cols_mobile' => max(1, min(4, intval($attrs['maxColsMobile']))),
        'column_gap' => max(0, min(80, intval($attrs['columnGap']))),
        'row_gap' => max(0, min(100, intval($attrs['rowGap']))),
        'text_align' => $text_align,
        'image_aspect_ratio' => $image_aspect_ratio,
        'card_padding' => max(0, min(64, intval($attrs['cardPadding']))),
        'card_radius' => max(0, min(48, intval($attrs['cardRadius']))),
        'card_background' => buttercup_tag_showcase_sanitize_css_color($attrs['cardBackground']),
    ];
}

function buttercup_tag_showcase_get_query_args($attrs, $for_status = false)
{
    $tax_query = [];
    if (!empty($attrs['tag_slugs'])) {
        if ($attrs['tag_match'] === 'all' && count($attrs['tag_slugs']) > 1) {
            $tax_query['relation'] = 'AND';
            foreach ($attrs['tag_slugs'] as $tag_slug) {
                $tax_query[] = [
                    'taxonomy' => 'post_tag',
                    'field' => 'slug',
                    'terms' => [$tag_slug],
                    'operator' => 'IN',
                ];
            }
        } else {
            $tax_query[] = [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $attrs['tag_slugs'],
                'operator' => 'IN',
            ];
        }
    }

    $query_args = [
        'post_type' => $attrs['post_types'],
        'post_status' => 'publish',
        'ignore_sticky_posts' => true,
        'orderby' => $attrs['order_by'],
        'order' => strtoupper($attrs['order']),
        'offset' => $attrs['offset'],
        'posts_per_page' => $for_status ? 1 : $attrs['max_items'],
        'no_found_rows' => !$for_status,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    if (!empty($tax_query)) {
        $query_args['tax_query'] = $tax_query;
    }

    if ($attrs['exclude_current_post']) {
        $current_post_id = buttercup_tag_showcase_get_current_post_id();
        if ($current_post_id > 0) {
            $query_args['post__not_in'] = [$current_post_id];
        }
    }

    return $query_args;
}

function buttercup_tag_showcase_get_snippet($post, $snippet_words)
{
    $excerpt = get_the_excerpt($post);
    $excerpt = is_string($excerpt) ? trim($excerpt) : '';
    if ($excerpt !== '') {
        return wp_trim_words(wp_strip_all_tags($excerpt), $snippet_words, '...');
    }

    $raw_content = isset($post->post_content) ? (string) $post->post_content : '';
    if ($raw_content === '') {
        return '';
    }

    $content = wp_strip_all_tags(strip_shortcodes($raw_content));
    return wp_trim_words($content, $snippet_words, '...');
}

function buttercup_tag_showcase_build_style_attr($attrs)
{
    $aspect_ratio = $attrs['image_aspect_ratio'] === 'auto'
        ? 'auto'
        : str_replace('/', ' / ', $attrs['image_aspect_ratio']);

    $card_background = $attrs['card_background'] !== '' ? $attrs['card_background'] : 'transparent';

    $styles = [
        '--buttercup-ts-min-width:' . $attrs['min_width_desktop'] . 'px',
        '--buttercup-ts-max-cols:' . $attrs['max_cols_desktop'],
        '--buttercup-ts-min-width-tablet:' . $attrs['min_width_tablet'] . 'px',
        '--buttercup-ts-max-cols-tablet:' . $attrs['max_cols_tablet'],
        '--buttercup-ts-min-width-mobile:' . $attrs['min_width_mobile'] . 'px',
        '--buttercup-ts-max-cols-mobile:' . $attrs['max_cols_mobile'],
        '--buttercup-ts-col-gap:' . $attrs['column_gap'] . 'px',
        '--buttercup-ts-row-gap:' . $attrs['row_gap'] . 'px',
        '--buttercup-ts-card-padding:' . $attrs['card_padding'] . 'px',
        '--buttercup-ts-card-radius:' . $attrs['card_radius'] . 'px',
        '--buttercup-ts-card-bg:' . $card_background,
        '--buttercup-ts-aspect-ratio:' . $aspect_ratio,
        '--buttercup-ts-button-radius:' . $attrs['button_radius'] . 'px',
        '--buttercup-ts-button-padding-y:' . $attrs['button_padding_y'] . 'px',
        '--buttercup-ts-button-padding-x:' . $attrs['button_padding_x'] . 'px',
        '--buttercup-ts-button-font-size:' . $attrs['button_font_size'] . 'px',
    ];

    if ($attrs['button_background'] !== '') {
        $styles[] = '--buttercup-ts-button-bg:' . $attrs['button_background'];
    }
    if ($attrs['button_text_color'] !== '') {
        $styles[] = '--buttercup-ts-button-text:' . $attrs['button_text_color'];
    }
    if ($attrs['button_border_color'] !== '') {
        $styles[] = '--buttercup-ts-button-border:' . $attrs['button_border_color'];
    }

    return implode(';', $styles) . ';';
}

function buttercup_tag_showcase_build_target_attrs($open_in_new_tab)
{
    if (!$open_in_new_tab) {
        return '';
    }

    return ' target="_blank" rel="noopener noreferrer"';
}

function buttercup_render_tag_showcase($attributes)
{
    $attrs = buttercup_tag_showcase_sanitize_attributes($attributes);
    if (empty($attrs['tag_slugs']) || empty($attrs['post_types'])) {
        return '';
    }

    $current_post_id = $attrs['exclude_current_post'] ? buttercup_tag_showcase_get_current_post_id() : 0;
    $render_cache_key = buttercup_build_cache_key('tag_showcase_render', [$attrs, $current_post_id]);
    $cached_html = buttercup_cache_get($render_cache_key);
    if (is_string($cached_html)) {
        return $cached_html;
    }

    $query = new WP_Query(buttercup_tag_showcase_get_query_args($attrs, false));
    if (!$query->have_posts()) {
        wp_reset_postdata();
        return '';
    }

    $wrapper_classes = [
        'wp-block-buttercup-tag-showcase',
        'buttercup-tag-showcase',
        'buttercup-tag-showcase--align-' . $attrs['text_align'],
    ];
    $style_attr = buttercup_tag_showcase_build_style_attr($attrs);
    $target_attrs = buttercup_tag_showcase_build_target_attrs($attrs['open_in_new_tab']);
    $button_class = 'buttercup-tag-showcase__button--' . $attrs['button_style'];
    $button_visual_class = 'buttercup-tag-showcase__button-visual--' . $attrs['button_style'];

    $html = '<section class="' . esc_attr(implode(' ', $wrapper_classes)) . '" style="' . esc_attr($style_attr) . '">';
    $html .= '<div class="buttercup-tag-showcase__grid">';

    while ($query->have_posts()) {
        $query->the_post();
        $post = get_post();
        if (!$post) {
            continue;
        }

        $post_id = intval($post->ID);
        $title = get_the_title($post);
        if (trim($title) === '') {
            $title = __('(Untitled)', 'buttercup');
        }

        $link = get_permalink($post);
        $has_link = is_string($link) && $link !== '';
        $card_classes = ['buttercup-tag-showcase__card', 'is-click-' . $attrs['click_mode']];

        $html .= '<article class="' . esc_attr(implode(' ', $card_classes)) . '" data-post-id="' . esc_attr($post_id) . '">';

        if ($has_link && $attrs['click_mode'] !== 'cta-only') {
            $overlay_label = sprintf(__('View %s', 'buttercup'), $title);
            $html .= '<a class="buttercup-tag-showcase__card-link" href="' . esc_url($link) . '" aria-label="' . esc_attr($overlay_label) . '"' . $target_attrs . '>';
            $html .= esc_html($overlay_label);
            $html .= '</a>';
        }

        if ($attrs['show_thumbnail'] && has_post_thumbnail($post)) {
            $thumb_classes = ['buttercup-tag-showcase__thumb'];
            if ($attrs['image_aspect_ratio'] === 'auto') {
                $thumb_classes[] = 'is-auto';
            }
            $html .= '<figure class="' . esc_attr(implode(' ', $thumb_classes)) . '">';
            $html .= get_the_post_thumbnail($post, 'large', [
                'loading' => 'lazy',
                'decoding' => 'async',
            ]);
            $html .= '</figure>';
        }

        $html .= '<div class="buttercup-tag-showcase__content">';

        if ($attrs['show_type'] || $attrs['show_date']) {
            $html .= '<div class="buttercup-tag-showcase__meta">';

            if ($attrs['show_type']) {
                $type_obj = get_post_type_object($post->post_type);
                $type_label = $type_obj ? $type_obj->labels->singular_name : ucfirst($post->post_type);
                $html .= '<span class="buttercup-tag-showcase__meta-type">' . esc_html($type_label) . '</span>';
            }

            if ($attrs['show_date']) {
                $date_value = get_the_date('', $post);
                $html .= '<span class="buttercup-tag-showcase__meta-date">' . esc_html($date_value) . '</span>';
            }

            $html .= '</div>';
        }

        if ($attrs['show_title']) {
            $html .= '<h3 class="buttercup-tag-showcase__title">' . esc_html($title) . '</h3>';
        }

        if ($attrs['show_snippet']) {
            $snippet = buttercup_tag_showcase_get_snippet($post, $attrs['snippet_words']);
            if ($snippet !== '') {
                $html .= '<p class="buttercup-tag-showcase__snippet">' . esc_html($snippet) . '</p>';
            }
        }

        if ($attrs['show_button']) {
            if ($attrs['click_mode'] === 'card-only' || !$has_link) {
                $html .= '<span class="buttercup-tag-showcase__button-visual ' . esc_attr($button_visual_class) . '">' . esc_html($attrs['button_label']) . '</span>';
            } else {
                $html .= '<a class="buttercup-tag-showcase__button ' . esc_attr($button_class) . '" href="' . esc_url($link) . '"' . $target_attrs . '>';
                $html .= esc_html($attrs['button_label']);
                $html .= '</a>';
            }
        }

        $html .= '</div>';
        $html .= '</article>';
    }

    wp_reset_postdata();

    $html .= '</div>';
    $html .= '</section>';

    buttercup_cache_set($render_cache_key, $html, 300);

    return $html;
}

function buttercup_tag_showcase_get_status_count($attrs)
{
    $current_post_id = $attrs['exclude_current_post'] ? buttercup_tag_showcase_get_current_post_id() : 0;
    $cache_key = buttercup_build_cache_key('tag_showcase_status', [$attrs, $current_post_id]);
    $cached_count = buttercup_cache_get($cache_key);
    if (is_numeric($cached_count)) {
        return intval($cached_count);
    }

    $query = new WP_Query(buttercup_tag_showcase_get_query_args($attrs, true));
    $count = intval($query->found_posts);
    wp_reset_postdata();

    buttercup_cache_set($cache_key, $count, 300);

    return $count;
}

function buttercup_rest_tag_showcase_status($request)
{
    $raw_tag_slugs = isset($request['tagSlugs']) ? explode(',', (string) $request['tagSlugs']) : [];
    $raw_post_types = isset($request['postTypes']) ? explode(',', (string) $request['postTypes']) : [];

    $attrs = buttercup_tag_showcase_sanitize_attributes([
        'tagSlugs' => $raw_tag_slugs,
        'tagMatch' => isset($request['tagMatch']) ? $request['tagMatch'] : 'any',
        'enableMultiTag' => count($raw_tag_slugs) > 1,
        'postTypes' => $raw_post_types,
        'excludeCurrentPost' => !empty($request['excludeCurrentPost']),
        'offset' => isset($request['offset']) ? intval($request['offset']) : 0,
        'maxItems' => isset($request['maxItems']) ? intval($request['maxItems']) : 12,
    ]);

    if (empty($attrs['tag_slugs']) || empty($attrs['post_types'])) {
        return rest_ensure_response([
            'count' => 0,
            'hasResults' => false,
        ]);
    }

    $count = buttercup_tag_showcase_get_status_count($attrs);

    return rest_ensure_response([
        'count' => $count,
        'hasResults' => $count > 0,
    ]);
}

