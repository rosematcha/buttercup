<?php

if (!defined('ABSPATH')) {
    exit;
}

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
    if (!wp_attachment_is_image($id)) {
        return new WP_Error('buttercup_invalid_id', __('Attachment must be an image.', 'buttercup'), ['status' => 400]);
    }

    $result = buttercup_generate_square_image($id, 600);
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response($result);
}

function buttercup_rest_homepage_feed_status($request)
{
    $mast_tag_slug = isset($request['mastTagSlug']) ? $request['mastTagSlug'] : 'mast';
    $home_tag_slug = isset($request['homeTagSlug']) ? $request['homeTagSlug'] : 'home';

    $feed = buttercup_homepage_feed_collect($mast_tag_slug, $home_tag_slug);

    $response = [
        'mastTagSlug' => $feed['mast_tag_slug'],
        'homeTagSlug' => $feed['home_tag_slug'],
        'mastCount' => intval($feed['mast_count']),
        'homeCount' => intval($feed['home_count']),
        'mastOverflow' => !empty($feed['mast_overflow']),
        'homeOverflow' => !empty($feed['home_overflow']),
        'mastSelected' => buttercup_homepage_feed_summarize_posts($feed['mast_selected'] ? [$feed['mast_selected']] : []),
        'homeSelected' => buttercup_homepage_feed_summarize_posts($feed['home_selected']),
        'dualTagged' => buttercup_homepage_feed_summarize_posts($feed['dual_posts']),
    ];

    return rest_ensure_response($response);
}

function buttercup_register_rest_routes()
{
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

    register_rest_route('buttercup/v1', '/homepage-feed-status', [
        'methods' => 'GET',
        'callback' => 'buttercup_rest_homepage_feed_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'mastTagSlug' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_title',
            ],
            'homeTagSlug' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_title',
            ],
        ],
    ]);

    register_rest_route('buttercup/v1', '/tag-showcase-status', [
        'methods' => 'GET',
        'callback' => 'buttercup_rest_tag_showcase_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'tagSlugs' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    return sanitize_text_field($value);
                },
            ],
            'tagMatch' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    return sanitize_key($value);
                },
            ],
            'postTypes' => [
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    return sanitize_text_field($value);
                },
            ],
            'excludeCurrentPost' => [
                'type' => 'boolean',
                'required' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'offset' => [
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint',
            ],
            'maxItems' => [
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'buttercup_register_rest_routes');
