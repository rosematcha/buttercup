<?php

if (!defined('ABSPATH')) {
    exit;
}

function buttercup_register_member_routes()
{
    add_rewrite_tag('%buttercup_member%', '([a-z0-9-]+)');
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

function buttercup_blocks_have_member_pages($blocks, $visited_refs = [], $depth = 0)
{
    if ($depth > BUTTERCUP_MAX_BLOCK_DEPTH) {
        return false;
    }

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (($block['blockName'] ?? '') === 'core/block') {
            $ref = intval($block['attrs']['ref'] ?? 0);
            if ($ref > 0) {
                if (isset($visited_refs[$ref])) {
                    continue;
                }
                $visited_refs[$ref] = true;
                $ref_post = get_post($ref);
                if ($ref_post && $ref_post->post_type === 'wp_block') {
                    if (buttercup_blocks_have_member_pages(parse_blocks($ref_post->post_content), $visited_refs, $depth + 1)) {
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

        if (!empty($block['innerBlocks']) && buttercup_blocks_have_member_pages($block['innerBlocks'], $visited_refs, $depth + 1)) {
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
        buttercup_schedule_rewrite_flush();
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
        buttercup_schedule_rewrite_flush();
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

    $request_path = wp_parse_url( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
    if (!$request_path) {
        return '';
    }

    $request_path = trim($request_path, '/');
    $home_path = trim(wp_parse_url(home_url(), PHP_URL_PATH), '/');
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
    $request_path = trim((string) $request_path, '/');
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
            $slug = buttercup_normalize_member_slug($slug);
            if (!buttercup_is_valid_member_slug($slug)) {
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
    $slug = buttercup_normalize_member_slug($slug);
    if (!buttercup_is_valid_member_slug($slug)) {
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
        $incoming_slug = buttercup_normalize_member_slug($wp->query_vars['buttercup_member']);
        if (!buttercup_is_valid_member_slug($incoming_slug)) {
            unset($wp->query_vars['buttercup_member']);
        } else {
            $wp->query_vars['buttercup_member'] = $incoming_slug;
            return;
        }
    }

    $match = buttercup_get_member_match_cache(null);
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
    $query_slug = buttercup_normalize_member_slug(get_query_var('buttercup_member'));
    if (buttercup_is_valid_member_slug($query_slug)) {
        return false;
    }

    $request_path = wp_parse_url($requested_url, PHP_URL_PATH);
    if (!$request_path) {
        return $redirect_url;
    }

    $request_path = trim($request_path, '/');
    $home_path = trim(wp_parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path !== '' && strpos($request_path, $home_path) === 0) {
        $request_path = trim(substr($request_path, strlen($home_path)), '/');
    }

    if (buttercup_get_member_match_cache($request_path)) {
        return false;
    }

    return $redirect_url;
}
add_filter('redirect_canonical', 'buttercup_disable_member_canonical', 10, 2);

function buttercup_activation_refresh()
{
    buttercup_refresh_member_bases();
    buttercup_fb_sync_activate();
    flush_rewrite_rules();
    delete_option('buttercup_needs_rewrite_flush');
}

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

function buttercup_collect_member_entries($blocks, &$entries, $visited_refs = [], $depth = 0)
{
    if ($depth > BUTTERCUP_MAX_BLOCK_DEPTH) {
        return $entries;
    }

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (($block['blockName'] ?? '') === 'core/block') {
            $ref = intval($block['attrs']['ref'] ?? 0);
            if ($ref > 0) {
                if (isset($visited_refs[$ref])) {
                    continue;
                }
                $visited_refs[$ref] = true;
                $ref_post = get_post($ref);
                if ($ref_post && $ref_post->post_type === 'wp_block') {
                    buttercup_collect_member_entries(parse_blocks($ref_post->post_content), $entries, $visited_refs, $depth + 1);
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
                        $member_page_disabled = false;
                        if (array_key_exists('enableMemberPage', $member_attrs)) {
                            $member_page_disabled = !$member_attrs['enableMemberPage'];
                        } elseif (!empty($member_attrs['disableMemberPage'])) {
                            $member_page_disabled = true;
                        }
                        if ($member_page_disabled) {
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
            buttercup_collect_member_entries($block['innerBlocks'], $entries, $visited_refs, $depth + 1);
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
    $style_path = BUTTERCUP_PLUGIN_DIR . '/build/team-member/style-index.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'buttercup-team-member',
            plugins_url('build/team-member/style-index.css', BUTTERCUP_PLUGIN_FILE),
            [],
            filemtime($style_path)
        );
    }
}

function buttercup_render_member_page()
{
    global $post, $wp_query;
    $can_debug = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug flag is a read-only GET param for display, gated by WP_DEBUG and manage_options capability.
    if ($can_debug && isset($_GET['buttercup_debug'])) {
        $request_path = buttercup_get_request_path();
        $match = buttercup_get_member_match_cache($request_path);
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

        wp_die('<pre>' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT)) . '</pre>', 'Buttercup Debug');
    }

    $slug = get_query_var('buttercup_member');
    $page_id = 0;

    if (!$slug) {
        $match = buttercup_get_member_match_cache(null);
        if ($match) {
            $slug = $match['slug'];
            $page_id = $match['page_id'];
        }
    } else {
        $slug = buttercup_normalize_member_slug($slug);
        $page_id = intval(get_query_var('page_id'));
        if ($page_id <= 0 && $post) {
            $page_id = $post->ID;
        }

        if ($page_id <= 0) {
            $match = buttercup_get_member_match_cache(null);
            if ($match) {
                $page_id = $match['page_id'];
            }
        }
    }

    if (!buttercup_is_valid_member_slug($slug)) {
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

    $clamp_int = static function ($value, $min, $max) {
        $value = intval($value);
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    };

    $default_shape = get_option('buttercup_team_image_shape', 'squircle');
    $default_size  = intval(get_option('buttercup_team_image_size', 192));
    $image_shape = $team['imageShape'] ?? $default_shape;
    $image_size = $clamp_int(isset($team['imageSize']) ? $team['imageSize'] : $default_size, 40, 600);
    $squircle_radius = isset($team['squircleRadius']) ? floatval($team['squircleRadius']) : 22;
    $member_card_bg = isset($team['memberPageCardBackground']) ? trim($team['memberPageCardBackground']) : '';
    $member_card_radius = $clamp_int(isset($team['memberPageCardRadius']) ? $team['memberPageCardRadius'] : 20, 0, 32);
    $member_card_padding = $clamp_int(isset($team['memberPageCardPadding']) ? $team['memberPageCardPadding'] : 24, 12, 40);
    $member_gap = $clamp_int(isset($team['memberPageGap']) ? $team['memberPageGap'] : 32, 16, 64);
    $member_left = $clamp_int(isset($team['memberPageLeftWidth']) ? $team['memberPageLeftWidth'] : 280, 220, 360);
    $member_shadow = $team['memberPageCardShadow'] ?? 'none';

    // Keep profile image within the card content area even with extreme settings.
    $image_size_cap = max(40, $member_left - (2 * $member_card_padding));
    $image_size = min($image_size, $image_size_cap);

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
    // translators: %s: the page title the user will navigate back to.
    $back_label = $member_back_label !== '' ? $member_back_label : sprintf(__('Back to %s', 'buttercup'), $post->post_title);
    echo '<a href="' . esc_url(get_permalink($post)) . '">' . esc_html($back_label) . '</a>';
    echo '</div>';
    echo '<div class="buttercup-team-member-page__layout">';
    echo '<aside class="buttercup-team-member-page__card buttercup-team-member-page--' . esc_attr($image_shape) . esc_attr($shadow_class) . '"';
    echo ' style="--buttercup-img-size:' . esc_attr($image_size) . 'px; --buttercup-squircle-radius:' . esc_attr($squircle_radius) . '%;">';
    if ($image_url || $image_id) {
        echo '<div class="buttercup-team-member__image-wrap">';
        if ($image_source === 'square-600' && $image_url) {
            // translators: %s: the team member's name.
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
                // translators: %s: the team member's name.
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
            // translators: %s: the team member's name.
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
        echo '<div class="buttercup-team-member-page__contact">' . wp_kses_post($contact) . '</div>';
    }
    if ($show_social && $social_html !== '') {
        echo '<div class="buttercup-team-member-page__social">' . wp_kses_post($social_html) . '</div>';
    }
    echo '</aside>';
    echo '<section class="buttercup-team-member-page__bio">';
    if ($member_intro !== '') {
        echo '<div class="buttercup-team-member-page__intro">' . wp_kses_post(wpautop(esc_html($member_intro))) . '</div>';
    }
    echo $bio_html !== '' ? wp_kses_post($bio_html) : '';
    echo '</section>';
    echo '</div>';
    echo '</main>';
    get_footer();
    exit;
}
add_action('template_redirect', 'buttercup_render_member_page');
