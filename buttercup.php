<?php
/**
 * Plugin Name:       Buttercup
 * Description:       Custom blocks for Reese's sites.
 * Version:           1.0.0
 * Author:            Reese Lundquist
 * Text Domain:       buttercup
 */

if (!defined('ABSPATH')) {
    exit;
}

function buttercup_build_image_candidates($attachment_id) {
    $meta = wp_get_attachment_metadata($attachment_id);
    if (!$meta || empty($meta['width']) || empty($meta['height'])) {
        return [];
    }

    $upload_dir = wp_get_upload_dir();
    $base_url = isset($upload_dir['baseurl']) ? $upload_dir['baseurl'] : '';
    $base_url = $base_url !== '' ? trailingslashit($base_url) : '';
    $file = isset($meta['file']) ? $meta['file'] : '';
    $file_dir = $file ? trailingslashit(dirname($file)) : '';

    $candidates = [];
    $full_url = wp_get_attachment_url($attachment_id);
    if ($full_url) {
        $candidates[] = [
            'url' => $full_url,
            'width' => intval($meta['width']),
            'height' => intval($meta['height']),
            'crop' => false,
        ];
    }

    if (!empty($meta['sizes']) && $base_url !== '' && $file_dir !== '') {
        foreach ($meta['sizes'] as $size) {
            if (empty($size['file']) || empty($size['width']) || empty($size['height'])) {
                continue;
            }
            $candidates[] = [
                'url' => $base_url . $file_dir . $size['file'],
                'width' => intval($size['width']),
                'height' => intval($size['height']),
                'crop' => !empty($size['crop']),
            ];
        }
    }

    return $candidates;
}

function buttercup_filter_uncropped_candidates($candidates, $orig_ratio) {
    $filtered = [];
    $ratio_tolerance = 0.08;
    $orig_is_square = abs($orig_ratio - 1) <= 0.05;

    foreach ($candidates as $candidate) {
        if (empty($candidate['width']) || empty($candidate['height'])) {
            continue;
        }
        if (!empty($candidate['crop'])) {
            continue;
        }
        if ($orig_ratio > 0) {
            $ratio = $candidate['width'] / $candidate['height'];
            if (!$orig_is_square) {
                if (abs($ratio - $orig_ratio) / $orig_ratio > $ratio_tolerance) {
                    continue;
                }
            }
        }
        $filtered[] = $candidate;
    }

    return $filtered;
}

function buttercup_pick_best_candidate($candidates, $target_width) {
    if (empty($candidates)) {
        return null;
    }
    usort($candidates, function ($a, $b) {
        return $a['width'] <=> $b['width'];
    });
    foreach ($candidates as $candidate) {
        if ($candidate['width'] >= $target_width) {
            return $candidate;
        }
    }
    return $candidates[count($candidates) - 1];
}

function buttercup_build_srcset($candidates) {
    if (empty($candidates)) {
        return '';
    }
    usort($candidates, function ($a, $b) {
        return $a['width'] <=> $b['width'];
    });
    $seen = [];
    $parts = [];
    foreach ($candidates as $candidate) {
        $url = $candidate['url'];
        if (!$url || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $parts[] = esc_url($url) . ' ' . intval($candidate['width']) . 'w';
    }
    return implode(', ', $parts);
}

function buttercup_blocks_init()
{
    register_block_type(__DIR__ . '/build/team');
    register_block_type(__DIR__ . '/build/team-member');
    register_block_type(__DIR__ . '/build/row-layout');
    register_block_type(__DIR__ . '/build/row-column');
}
add_action('init', 'buttercup_blocks_init');

function buttercup_enqueue_dashicons()
{
    if (!is_admin()) {
        wp_enqueue_style('dashicons');
    }
}
add_action('enqueue_block_assets', 'buttercup_enqueue_dashicons');

function buttercup_register_member_routes()
{
    add_rewrite_tag('%buttercup_member%', '([^&]+)');
    $bases = get_option('buttercup_member_bases', []);
    if (!is_array($bases)) {
        return;
    }

    foreach ($bases as $base) {
        $path = isset($base['path']) ? trim($base['path'], '/') : '';
        $page_id = isset($base['id']) ? intval($base['id']) : 0;
        if ($path === '' || $page_id <= 0) {
            continue;
        }

        $pattern = '^' . preg_quote($path, '#') . '/([^/]+)/?$';
        add_rewrite_rule($pattern, 'index.php?page_id=' . $page_id . '&buttercup_member=$matches[1]', 'top');
    }
}
add_action('init', 'buttercup_register_member_routes');

function buttercup_member_bases_changed($next, $prev)
{
    if (!is_array($next) || !is_array($prev)) {
        return true;
    }

    $normalize = function ($bases) {
        usort($bases, function ($a, $b) {
            return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
        });
        return $bases;
    };

    return $normalize($next) !== $normalize($prev);
}

function buttercup_blocks_have_member_pages($blocks)
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (($block['blockName'] ?? '') === 'core/block') {
            $ref = intval($block['attrs']['ref'] ?? 0);
            if ($ref > 0) {
                $ref_post = get_post($ref);
                if ($ref_post && $ref_post->post_type === 'wp_block') {
                    if (buttercup_blocks_have_member_pages(parse_blocks($ref_post->post_content))) {
                        return true;
                    }
                }
            }
        }

        if (($block['blockName'] ?? '') === 'buttercup/team') {
            $attrs = $block['attrs'] ?? [];
            $enabled = !array_key_exists('enableMemberPages', $attrs) || $attrs['enableMemberPages'];
            if ($enabled) {
                return true;
            }
        }

        if (!empty($block['innerBlocks']) && buttercup_blocks_have_member_pages($block['innerBlocks'])) {
            return true;
        }
    }

    return false;
}

function buttercup_refresh_member_bases()
{
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'suppress_filters' => false,
    ]);

    $bases = [];
    foreach ($pages as $page) {
        if (strpos($page->post_content, 'buttercup/team') === false) {
            continue;
        }

        if (!buttercup_blocks_have_member_pages(parse_blocks($page->post_content))) {
            continue;
        }

        $path = get_page_uri($page->ID);
        if ($path === '') {
            continue;
        }

        $bases[] = [
            'id' => $page->ID,
            'path' => $path,
        ];
    }

    $prev = get_option('buttercup_member_bases', []);
    if (buttercup_member_bases_changed($bases, $prev)) {
        update_option('buttercup_member_bases', $bases, false);
        flush_rewrite_rules();
    }
}

function buttercup_update_member_base_for_page($post_id)
{
    $page = get_post($post_id);
    $bases = get_option('buttercup_member_bases', []);
    if (!is_array($bases)) {
        $bases = [];
    }

    $keep = [];
    foreach ($bases as $base) {
        if (intval($base['id'] ?? 0) !== intval($post_id)) {
            $keep[] = $base;
        }
    }

    $should_add = false;
    $path = '';

    if ($page && $page->post_type === 'page' && $page->post_status === 'publish') {
        if (strpos($page->post_content, 'buttercup/team') !== false) {
            if (buttercup_blocks_have_member_pages(parse_blocks($page->post_content))) {
                $path = get_page_uri($page->ID);
                if ($path !== '') {
                    $should_add = true;
                }
            }
        }
    }

    if ($should_add) {
        $keep[] = [
            'id' => $page->ID,
            'path' => $path,
        ];
    }

    if (buttercup_member_bases_changed($keep, $bases)) {
        update_option('buttercup_member_bases', $keep, false);
        flush_rewrite_rules();
    }
}

function buttercup_handle_page_save($post_id)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'page') {
        return;
    }

    buttercup_update_member_base_for_page($post_id);
}
add_action('save_post_page', 'buttercup_handle_page_save');
add_action('deleted_post', 'buttercup_update_member_base_for_page');

function buttercup_admin_refresh_member_bases()
{
    if (!is_admin()) {
        return;
    }

    if (get_option('buttercup_member_bases', null) === null) {
        buttercup_refresh_member_bases();
    }
}
add_action('admin_init', 'buttercup_admin_refresh_member_bases');

function buttercup_get_request_path()
{
    if (!isset($_SERVER['REQUEST_URI'])) {
        return '';
    }

    $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (!$request_path) {
        return '';
    }

    $request_path = trim($request_path, '/');
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path !== '' && strpos($request_path, $home_path) === 0) {
        $request_path = trim(substr($request_path, strlen($home_path)), '/');
    }

    return $request_path;
}

function buttercup_page_has_member_pages($page_id)
{
    $page = get_post($page_id);
    if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
        return false;
    }

    if (strpos($page->post_content, 'buttercup/team') === false) {
        return false;
    }

    return buttercup_blocks_have_member_pages(parse_blocks($page->post_content));
}

function buttercup_match_member_request($request_path)
{
    if ($request_path === '') {
        return null;
    }

    $bases = get_option('buttercup_member_bases', []);
    if (is_array($bases)) {
        foreach ($bases as $base) {
            $path = isset($base['path']) ? trim($base['path'], '/') : '';
            $page_id = isset($base['id']) ? intval($base['id']) : 0;
            if ($path === '' || $page_id <= 0) {
                continue;
            }

            if (strpos($request_path, $path . '/') !== 0) {
                continue;
            }

            $slug = trim(substr($request_path, strlen($path) + 1), '/');
            if ($slug === '' || strpos($slug, '/') !== false) {
                continue;
            }

            return [
                'page_id' => $page_id,
                'slug' => $slug,
            ];
        }
    }

    $parts = explode('/', $request_path);
    if (count($parts) < 2) {
        return null;
    }

    $slug = array_pop($parts);
    $base_path = implode('/', $parts);
    if ($base_path === '') {
        return null;
    }

    $page = get_page_by_path($base_path, OBJECT, 'page');
    if (!$page) {
        return null;
    }

    if (!buttercup_page_has_member_pages($page->ID)) {
        return null;
    }

    return [
        'page_id' => $page->ID,
        'slug' => $slug,
    ];
}

function buttercup_parse_member_request($wp)
{
    if (!empty($wp->query_vars['buttercup_member'])) {
        return;
    }

    $request_path = buttercup_get_request_path();
    $match = buttercup_match_member_request($request_path);
    if (!$match) {
        return;
    }

    $wp->query_vars['page_id'] = $match['page_id'];
    $wp->query_vars['pagename'] = get_page_uri($match['page_id']);
    $wp->query_vars['buttercup_member'] = $match['slug'];
}
add_action('parse_request', 'buttercup_parse_member_request', 5);

function buttercup_disable_member_canonical($redirect_url, $requested_url)
{
    if (get_query_var('buttercup_member')) {
        return false;
    }

    $request_path = parse_url($requested_url, PHP_URL_PATH);
    if (!$request_path) {
        return $redirect_url;
    }

    $request_path = trim($request_path, '/');
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path !== '' && strpos($request_path, $home_path) === 0) {
        $request_path = trim(substr($request_path, strlen($home_path)), '/');
    }

    if (buttercup_match_member_request($request_path)) {
        return false;
    }

    return $redirect_url;
}
add_filter('redirect_canonical', 'buttercup_disable_member_canonical', 10, 2);

function buttercup_activation_refresh()
{
    buttercup_refresh_member_bases();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'buttercup_activation_refresh');

function buttercup_register_query_vars($vars)
{
    $vars[] = 'buttercup_member';
    return $vars;
}
add_filter('query_vars', 'buttercup_register_query_vars');

function buttercup_clean_name($name)
{
    $clean = wp_strip_all_tags($name);
    $clean = trim(preg_replace('/\s+/', ' ', $clean));
    return $clean;
}

function buttercup_build_member_slugs_for_page($entries)
{
    $counts = [];
    $prepared = [];
    $used = [];

    foreach ($entries as $index => $entry) {
        $member = $entry['member'] ?? [];
        $name = buttercup_clean_name($member['name'] ?? '');
        $tokens = $name === '' ? [] : preg_split('/\s+/', $name);
        $first = $tokens[0] ?? '';
        $last = count($tokens) > 1 ? $tokens[count($tokens) - 1] : '';
        $key = strtolower($first);
        if ($key !== '') {
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $prepared[$index] = [
            'first' => $first,
            'last' => $last,
        ];

        $existing = isset($member['memberSlug']) ? sanitize_title($member['memberSlug']) : '';
        if ($existing !== '') {
            $entries[$index]['slug'] = $existing;
            $used[$existing] = true;
        }
    }

    foreach ($entries as $index => $entry) {
        if (!empty($entries[$index]['slug'])) {
            continue;
        }

        $first = $prepared[$index]['first'];
        $last = $prepared[$index]['last'];
        $base = '';

        if ($first !== '') {
            $needs_last = ($counts[strtolower($first)] ?? 0) > 1;
            $base = $needs_last && $last !== '' ? $first . ' ' . $last : $first;
        }

        $slug = $base !== '' ? sanitize_title($base) : '';
        if ($slug !== '') {
            $unique = $slug;
            $i = 2;
            while (isset($used[$unique])) {
                $unique = $slug . '-' . $i;
                $i += 1;
            }
            $used[$unique] = true;
            $slug = $unique;
        }

        $entries[$index]['slug'] = $slug;
    }

    return $entries;
}

function buttercup_collect_member_entries($blocks, &$entries)
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (($block['blockName'] ?? '') === 'core/block') {
            $ref = intval($block['attrs']['ref'] ?? 0);
            if ($ref > 0) {
                $ref_post = get_post($ref);
                if ($ref_post && $ref_post->post_type === 'wp_block') {
                    buttercup_collect_member_entries(parse_blocks($ref_post->post_content), $entries);
                }
            }
        }

        if (($block['blockName'] ?? '') === 'buttercup/team') {
            $attrs = $block['attrs'] ?? [];
            $enabled = !array_key_exists('enableMemberPages', $attrs) || $attrs['enableMemberPages'];
            if ($enabled) {
                foreach ($block['innerBlocks'] ?? [] as $inner) {
                    if (($inner['blockName'] ?? '') === 'buttercup/team-member') {
                        $member_attrs = $inner['attrs'] ?? [];
                        if (!empty($member_attrs['disableMemberPage'])) {
                            continue;
                        }
                        $entries[] = [
                            'member' => $member_attrs,
                            'team' => $attrs,
                        ];
                    }
                }
            }
        }

        if (!empty($block['innerBlocks'])) {
            buttercup_collect_member_entries($block['innerBlocks'], $entries);
        }
    }

    return $entries;
}

function buttercup_find_member_in_blocks($blocks, $slug)
{
    $entries = [];
    buttercup_collect_member_entries($blocks, $entries);
    if (empty($entries)) {
        return null;
    }

    $entries = buttercup_build_member_slugs_for_page($entries);
    foreach ($entries as $entry) {
        if (($entry['slug'] ?? '') === $slug) {
            return $entry;
        }
    }

    return null;
}

function buttercup_member_page_contact_link($label, $href, $text = '')
{
    $display = $text !== '' ? $text : $href;
    return '<div><strong>' . esc_html($label) . ':</strong> <a href="' . esc_url($href) . '">' . esc_html($display) . '</a></div>';
}

function buttercup_enqueue_member_assets()
{
    $style_path = __DIR__ . '/build/team-member/style-index.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'buttercup-team-member',
            plugins_url('build/team-member/style-index.css', __FILE__),
            [],
            filemtime($style_path)
        );
    }
}

function buttercup_render_member_page()
{
    global $post, $wp_query;
    if (isset($_GET['buttercup_debug'])) {
        $request_path = buttercup_get_request_path();
        $match = buttercup_match_member_request($request_path);
        $debug_page_id = $match['page_id'] ?? ($post ? $post->ID : 0);
        $debug_page = $debug_page_id ? get_post($debug_page_id) : null;
        $entries = [];
        $slugs = [];
        $found_member = null;

        if ($debug_page) {
            $entries = [];
            buttercup_collect_member_entries(parse_blocks($debug_page->post_content), $entries);
            $entries = buttercup_build_member_slugs_for_page($entries);
            $slugs = array_map(function ($entry) {
                return $entry['slug'] ?? '';
            }, $entries);
            if ($match && $match['slug']) {
                foreach ($entries as $entry) {
                    if (($entry['slug'] ?? '') === $match['slug']) {
                        $found_member = $entry;
                        break;
                    }
                }
            }
        }

        $payload = [
            'request_path' => $request_path,
            'match' => $match,
            'page_id' => $debug_page_id,
            'page_title' => $debug_page ? $debug_page->post_title : '',
            'page_has_team' => $debug_page ? (strpos($debug_page->post_content, 'buttercup/team') !== false) : false,
            'entries_count' => count($entries),
            'slugs' => $slugs,
            'found_member' => $found_member ? ($found_member['member']['name'] ?? '') : '',
        ];

        wp_die('<pre>' . esc_html(print_r($payload, true)) . '</pre>', 'Buttercup Debug');
    }

    $slug = get_query_var('buttercup_member');
    $page_id = 0;

    if (!$slug) {
        $request_path = buttercup_get_request_path();
        $match = buttercup_match_member_request($request_path);
        if ($match) {
            $slug = $match['slug'];
            $page_id = $match['page_id'];
        }
    } else {
        $page_id = intval(get_query_var('page_id'));
        if ($page_id <= 0 && $post) {
            $page_id = $post->ID;
        }

        if ($page_id <= 0) {
            $request_path = buttercup_get_request_path();
            $match = buttercup_match_member_request($request_path);
            if ($match) {
                $page_id = $match['page_id'];
            }
        }
    }

    if (!$slug) {
        return;
    }

    if ($page_id > 0) {
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return;
        }
        $post = $page;
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
            $wp_query->queried_object = $post;
            $wp_query->queried_object_id = $post->ID;
        }
        setup_postdata($post);
    }

    $blocks = parse_blocks($post->post_content);
    $match = buttercup_find_member_in_blocks($blocks, $slug);

    if (!$match) {
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }

    $member = $match['member'];
    $team = $match['team'];

    $show_pronouns = !array_key_exists('showPronouns', $team) || $team['showPronouns'];
    $show_bio = !array_key_exists('showBio', $team) || $team['showBio'];
    $legacy_social = !array_key_exists('showSocial', $team) || $team['showSocial'];
    $show_social = array_key_exists('showSocialMemberPage', $team)
        ? $team['showSocialMemberPage']
        : $legacy_social;
    $show_member_bio = !array_key_exists('showBio', $member) || $member['showBio'];

    $name = buttercup_clean_name($member['name'] ?? '');
    $pronouns = isset($member['pronouns']) ? trim($member['pronouns']) : '';
    $position = isset($member['position']) ? trim($member['position']) : '';
    $email = isset($member['email']) ? trim($member['email']) : '';
    $phone = isset($member['phone']) ? trim($member['phone']) : '';
    $location = isset($member['location']) ? trim($member['location']) : '';
    $image_url = isset($member['profileImageUrl']) ? $member['profileImageUrl'] : '';
    $image_id = isset($member['profileImageId']) ? intval($member['profileImageId']) : 0;
    $image_alt = isset($member['profileImageAlt']) ? trim($member['profileImageAlt']) : '';
    $image_source = isset($member['profileImageSource']) ? $member['profileImageSource'] : '';

    $long_bio = isset($member['longBio']) ? trim($member['longBio']) : '';
    $short_bio = isset($member['bio']) ? $member['bio'] : '';
    $bio_html = '';

    if ($long_bio !== '') {
        $bio_html = wpautop(esc_html($long_bio));
    } elseif ($show_bio && $show_member_bio && $short_bio !== '') {
        $bio_html = wp_kses_post($short_bio);
    }

    $image_shape = $team['imageShape'] ?? 'circle';
    $image_size = isset($team['imageSize']) ? intval($team['imageSize']) : 600;
    $squircle_radius = isset($team['squircleRadius']) ? floatval($team['squircleRadius']) : 22;
    $member_card_bg = isset($team['memberPageCardBackground']) ? trim($team['memberPageCardBackground']) : '';
    $member_card_radius = isset($team['memberPageCardRadius']) ? intval($team['memberPageCardRadius']) : 20;
    $member_card_padding = isset($team['memberPageCardPadding']) ? intval($team['memberPageCardPadding']) : 24;
    $member_gap = isset($team['memberPageGap']) ? intval($team['memberPageGap']) : 32;
    $member_left = isset($team['memberPageLeftWidth']) ? intval($team['memberPageLeftWidth']) : 280;
    $member_shadow = $team['memberPageCardShadow'] ?? 'none';

    $member_title = $name !== '' ? $name : __('Team Member', 'buttercup');
    add_filter('document_title_parts', function ($parts) use ($member_title) {
        $parts['title'] = $member_title;
        return $parts;
    });
    add_filter('body_class', function ($classes) {
        $classes[] = 'buttercup-member-page';
        return $classes;
    });

    buttercup_enqueue_member_assets();

    $contact = '';
    if ($email !== '' && is_email($email)) {
        $contact .= buttercup_member_page_contact_link(__('Email', 'buttercup'), 'mailto:' . $email, $email);
    }
    if ($phone !== '') {
        $tel = preg_replace('/[^0-9+]/', '', $phone);
        if ($tel !== '') {
            $contact .= buttercup_member_page_contact_link(__('Phone', 'buttercup'), 'tel:' . $tel, $phone);
        }
    }
    if ($location !== '') {
        $contact .= '<div><strong>' . esc_html__('Location', 'buttercup') . ':</strong> ' . esc_html($location) . '</div>';
    }

    $social_html = '';
    $social_links = isset($member['socialLinks']) && is_array($member['socialLinks']) ? $member['socialLinks'] : [];
    foreach ($social_links as $link) {
        $url = isset($link['url']) ? trim($link['url']) : '';
        $platform = isset($link['platform']) ? $link['platform'] : '';
        if ($url === '') {
            continue;
        }
        if ($platform === 'email') {
            $href = strpos($url, 'mailto:') === 0 ? $url : 'mailto:' . $url;
        } else {
            $href = $url;
        }
        $label = $platform !== '' ? ucfirst($platform) : __('Social', 'buttercup');
        $social_html .= '<div><a href="' . esc_url($href) . '">' . esc_html($label) . '</a></div>';
    }

    status_header(200);
    nocache_headers();

    $member_back_label = isset($team['memberBackLabel']) ? trim($team['memberBackLabel']) : '';
    $member_intro = isset($team['memberPageIntro']) ? trim($team['memberPageIntro']) : '';

    $page_style = sprintf(
        '--buttercup-member-gap:%dpx; --buttercup-member-left:%dpx; --buttercup-member-card-radius:%dpx; --buttercup-member-card-padding:%dpx;',
        $member_gap,
        $member_left,
        $member_card_radius,
        $member_card_padding
    );
    if ($member_card_bg !== '') {
        $page_style .= ' --buttercup-member-card-bg:' . esc_attr($member_card_bg) . ';';
    }

    $shadow_class = $member_shadow && $member_shadow !== 'none'
        ? ' buttercup-team-member-page--shadow-' . esc_attr($member_shadow)
        : '';

    get_header();
    echo '<main class="buttercup-team-member-page" style="' . esc_attr($page_style) . '">';
    echo '<div class="buttercup-team-member-page__back">';
    $back_label = $member_back_label !== '' ? $member_back_label : sprintf(__('Back to %s', 'buttercup'), $post->post_title);
    echo '<a href="' . esc_url(get_permalink($post)) . '">' . esc_html($back_label) . '</a>';
    echo '</div>';
    echo '<div class="buttercup-team-member-page__layout">';
    echo '<aside class="buttercup-team-member-page__card buttercup-team-member-page--' . esc_attr($image_shape) . $shadow_class . '"';
    echo ' style="--buttercup-img-size:' . esc_attr($image_size) . 'px; --buttercup-squircle-radius:' . esc_attr($squircle_radius) . '%;">';
    if ($image_url || $image_id) {
        echo '<div class="buttercup-team-member__image-wrap">';
        if ($image_source === 'square-600' && $image_url) {
            $image_alt = $image_alt !== '' ? $image_alt : sprintf(__('Photo of %s', 'buttercup'), $member_title);
            echo '<img class="buttercup-team-member__image" loading="lazy" decoding="async"';
            echo ' style="object-fit:contain;object-position:center;"';
            echo ' src="' . esc_url($image_url) . '" width="600" height="600" alt="' . esc_attr($image_alt) . '">';
        } elseif ($image_id) {
            $meta = wp_get_attachment_metadata($image_id);
            $orig_ratio = 0;
            if ($meta && !empty($meta['width']) && !empty($meta['height'])) {
                $orig_ratio = $meta['width'] / $meta['height'];
            }
            $candidates = buttercup_build_image_candidates($image_id);
            $candidates = buttercup_filter_uncropped_candidates($candidates, $orig_ratio);
            $best = buttercup_pick_best_candidate($candidates, $image_size);
            $srcset = buttercup_build_srcset($candidates);
            $sizes_attr = $image_size . 'px';

            $final_url = $best ? $best['url'] : $image_url;
            $final_width = $best ? $best['width'] : 0;
            $final_height = $best ? $best['height'] : 0;

            if ($image_alt === '') {
                $image_alt = sprintf(__('Photo of %s', 'buttercup'), $member_title);
            }

            echo '<img class="buttercup-team-member__image" loading="lazy" decoding="async" style="object-fit:contain;object-position:center;"';
            echo ' src="' . esc_url($final_url) . '"';
            if ($srcset !== '') {
                echo ' srcset="' . esc_attr($srcset) . '" sizes="' . esc_attr($sizes_attr) . '"';
            }
            if ($final_width && $final_height) {
                echo ' width="' . esc_attr($final_width) . '" height="' . esc_attr($final_height) . '"';
            }
            echo ' alt="' . esc_attr($image_alt) . '">';
        } else {
            echo '<img class="buttercup-team-member__image" loading="lazy" decoding="async" style="object-fit:contain;object-position:center;" src="' . esc_url($image_url) . '" alt="' . esc_attr(sprintf(__('Photo of %s', 'buttercup'), $member_title)) . '">';
        }
        echo '</div>';
    }
    echo '<h1 class="buttercup-team-member-page__name">' . esc_html($member_title) . '</h1>';
    if ($show_pronouns && $pronouns !== '') {
        echo '<p class="buttercup-team-member-page__pronouns">' . esc_html($pronouns) . '</p>';
    }
    if ($position !== '') {
        echo '<p class="buttercup-team-member__position">' . esc_html($position) . '</p>';
    }
    if ($contact !== '') {
        echo '<div class="buttercup-team-member-page__contact">' . $contact . '</div>';
    }
    if ($show_social && $social_html !== '') {
        echo '<div class="buttercup-team-member-page__social">' . $social_html . '</div>';
    }
    echo '</aside>';
    echo '<section class="buttercup-team-member-page__bio">';
    if ($member_intro !== '') {
        echo '<div class="buttercup-team-member-page__intro">' . wpautop(esc_html($member_intro)) . '</div>';
    }
    echo $bio_html !== '' ? $bio_html : '';
    echo '</section>';
    echo '</div>';
    echo '</main>';
    get_footer();
    exit;
}
add_action('template_redirect', 'buttercup_render_member_page');

function buttercup_generate_square_image($attachment_id, $size = 600) {
    $file = get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) {
        return new WP_Error('buttercup_missing_file', __('Image file not found.', 'buttercup'));
    }

    $type = wp_check_filetype($file);
    $ext = strtolower($type['ext'] ?? '');
    $mime = $type['type'] ?? '';
    if (!$ext || !$mime) {
        return new WP_Error('buttercup_invalid_type', __('Unsupported image type.', 'buttercup'));
    }

    $info = pathinfo($file);
    $dir = $info['dirname'];
    $name = $info['filename'];
    $target = $dir . '/' . $name . '-buttercup-crop-' . intval($size) . 'x' . intval($size) . '.' . $ext;

    if (file_exists($target)) {
        $upload_dir = wp_get_upload_dir();
        $relative = str_replace($upload_dir['basedir'], '', $target);
        $url = trailingslashit($upload_dir['baseurl']) . ltrim($relative, '/');
        return [
            'url' => $url,
            'width' => $size,
            'height' => $size,
        ];
    }

    $image_info = getimagesize($file);
    if (!$image_info) {
        return new WP_Error('buttercup_invalid_image', __('Invalid image data.', 'buttercup'));
    }

    $src_width = intval($image_info[0]);
    $src_height = intval($image_info[1]);
    if (!$src_width || !$src_height) {
        return new WP_Error('buttercup_invalid_image', __('Invalid image size.', 'buttercup'));
    }

    $crop_size = min($src_width, $src_height);
    $src_x = intval(round(($src_width - $crop_size) / 2));
    $src_y = intval(round(($src_height - $crop_size) / 2));

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = imagecreatefromjpeg($file);
            break;
        case 'png':
            $src = imagecreatefrompng($file);
            break;
        case 'gif':
            $src = imagecreatefromgif($file);
            break;
        case 'webp':
            if (!function_exists('imagecreatefromwebp')) {
                return new WP_Error('buttercup_no_webp', __('WebP is not supported on this server.', 'buttercup'));
            }
            $src = imagecreatefromwebp($file);
            break;
        default:
            return new WP_Error('buttercup_invalid_type', __('Unsupported image type.', 'buttercup'));
    }

    if (!$src) {
        return new WP_Error('buttercup_invalid_image', __('Unable to load image.', 'buttercup'));
    }

    $dst = imagecreatetruecolor($size, $size);
    if (!$dst) {
        imagedestroy($src);
        return new WP_Error('buttercup_invalid_image', __('Unable to create image canvas.', 'buttercup'));
    }

    if ($ext === 'png' || $ext === 'gif' || $ext === 'webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, $src_x, $src_y, $size, $size, $crop_size, $crop_size);

    $saved = false;
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $saved = imagejpeg($dst, $target, 90);
            break;
        case 'png':
            $saved = imagepng($dst, $target, 6);
            break;
        case 'gif':
            $saved = imagegif($dst, $target);
            break;
        case 'webp':
            $saved = imagewebp($dst, $target, 85);
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);

    if (!$saved) {
        return new WP_Error('buttercup_save_failed', __('Unable to save image.', 'buttercup'));
    }

    $meta = wp_get_attachment_metadata($attachment_id);
    if (is_array($meta)) {
        $meta['sizes']['buttercup-square-600'] = [
            'file' => basename($target),
            'width' => $size,
            'height' => $size,
            'mime-type' => $mime,
        ];
        wp_update_attachment_metadata($attachment_id, $meta);
    }

    $upload_dir = wp_get_upload_dir();
    $relative = str_replace($upload_dir['basedir'], '', $target);
    $url = trailingslashit($upload_dir['baseurl']) . ltrim($relative, '/');

    return [
        'url' => $url,
        'width' => $size,
        'height' => $size,
    ];
}

function buttercup_rest_square_image($request) {
    $id = intval($request['id']);
    if (!$id) {
        return new WP_Error('buttercup_invalid_id', __('Invalid image ID.', 'buttercup'), ['status' => 400]);
    }

    $result = buttercup_generate_square_image($id, 600);
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response($result);
}

add_action('rest_api_init', function () {
    register_rest_route('buttercup/v1', '/square-image', [
        'methods' => 'POST',
        'callback' => 'buttercup_rest_square_image',
        'permission_callback' => function () {
            return current_user_can('upload_files');
        },
        'args' => [
            'id' => [
                'type' => 'integer',
                'required' => true,
            ],
        ],
    ]);
});
