<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse a saved Facebook Page events HTML file into event data.
 *
 * Strategy:
 * 1. Check for JSON-LD structured data (most reliable if present)
 * 2. Fall back to parsing the DOM for event cards
 *
 * @param string $html Raw HTML content of the saved Facebook events page.
 * @return array {
 *     @type array[] $events  Parsed events (title, start, end, location, url, uid, image_url, description).
 *     @type string[] $warnings  Non-fatal issues encountered during parsing.
 * }
 */
function buttercup_parse_facebook_events_html($html)
{
    $events   = [];
    $warnings = [];

    // Attempt 1: JSON-LD structured data.
    $jsonld_events = buttercup_fb_extract_jsonld_events($html);
    if (!empty($jsonld_events)) {
        return ['events' => $jsonld_events, 'warnings' => []];
    }

    // Attempt 2: Parse event data from embedded JSON in the HTML.
    $json_events = buttercup_fb_extract_json_events($html);
    if (!empty($json_events)) {
        return ['events' => $json_events, 'warnings' => []];
    }

    // Attempt 3: DOM-based extraction.
    $dom_events = buttercup_fb_extract_dom_events($html);
    if (!empty($dom_events)) {
        if (count($dom_events) < 3) {
            $warnings[] = __('Only a few events were found. Facebook may have loaded more events dynamically that were not captured in the saved HTML. Try scrolling to the bottom of the page before saving.', 'buttercup');
        }
        return ['events' => $dom_events, 'warnings' => $warnings];
    }

    $warnings[] = __('No events could be extracted from this HTML file. The Facebook page structure may have changed. Try saving the page again or check that you saved the Events tab.', 'buttercup');
    return ['events' => [], 'warnings' => $warnings];
}

/**
 * Extract events from JSON-LD <script> blocks.
 */
function buttercup_fb_extract_jsonld_events($html)
{
    $events = [];

    if (!preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
        return [];
    }

    foreach ($matches[1] as $json_str) {
        $data = json_decode(trim($json_str), true);
        if (!is_array($data)) {
            continue;
        }

        // Could be a single object or an array.
        $items = isset($data['@type']) ? [$data] : (isset($data[0]) ? $data : []);

        foreach ($items as $item) {
            $type = $item['@type'] ?? '';
            if ($type !== 'Event') {
                continue;
            }

            $event = buttercup_fb_normalize_event([
                'title'     => $item['name'] ?? '',
                'start'     => $item['startDate'] ?? '',
                'end'       => $item['endDate'] ?? '',
                'location'  => is_array($item['location'] ?? null)
                    ? ($item['location']['name'] ?? ($item['location']['address'] ?? ''))
                    : ($item['location'] ?? ''),
                'url'       => $item['url'] ?? '',
                'image_url' => is_array($item['image'] ?? null)
                    ? ($item['image']['url'] ?? ($item['image'][0] ?? ''))
                    : ($item['image'] ?? ''),
                'description' => $item['description'] ?? '',
            ]);

            if ($event) {
                $events[] = $event;
            }
        }
    }

    return $events;
}

/**
 * Extract events from embedded JSON data structures in the HTML.
 * Facebook embeds event data in various JSON payloads within <script> tags.
 */
function buttercup_fb_extract_json_events($html)
{
    $events = [];

    // Look for event-like JSON objects with common Facebook patterns.
    // Facebook often embeds data as {"event_id":"...", "name":"...", "start_timestamp":...}
    // or in relay-style data.

    // Pattern: find all JSON-like objects containing event fields.
    if (preg_match_all('/"event_(?:id|ID)":\s*"(\d+)"/', $html, $id_matches)) {
        foreach ($id_matches[1] as $event_id) {
            $event = buttercup_fb_extract_event_by_id($html, $event_id);
            if ($event) {
                $events[] = $event;
            }
        }
    }

    // Also check for structured data in __comet_data or require() payloads.
    if (preg_match_all('/\{"__typename":\s*"Event"[^}]*"id":\s*"(\d+)"[^}]*\}/s', $html, $matches)) {
        foreach ($matches[0] as $json_fragment) {
            // Try to decode the fragment — it may be incomplete, so wrap in braces.
            $data = json_decode($json_fragment, true);
            if (!$data) {
                continue;
            }
            $event = buttercup_fb_normalize_event([
                'title'       => $data['name'] ?? $data['event_name'] ?? '',
                'start'       => isset($data['start_timestamp']) ? gmdate('Y-m-d H:i:s', intval($data['start_timestamp'])) : '',
                'end'         => isset($data['end_timestamp']) ? gmdate('Y-m-d H:i:s', intval($data['end_timestamp'])) : '',
                'location'    => $data['event_place']['name'] ?? '',
                'url'         => 'https://www.facebook.com/events/' . ($data['id'] ?? ''),
                'image_url'   => '',
                'description' => $data['description']['text'] ?? '',
            ]);
            if ($event) {
                $events[] = $event;
            }
        }
    }

    // Deduplicate by URL.
    return buttercup_fb_deduplicate_events($events);
}

/**
 * Try to extract event data for a specific Facebook event ID from the HTML.
 */
function buttercup_fb_extract_event_by_id($html, $event_id)
{
    $title = '';
    $start = '';
    $end   = '';
    $location = '';

    // Try to find a name near this event_id reference.
    $id_pattern = '/"event_(?:id|ID)":\s*"' . preg_quote($event_id, '/') . '"[^}]{0,2000}/s';
    if (preg_match($id_pattern, $html, $context)) {
        $chunk = $context[0];
        if (preg_match('/"(?:name|event_name)":\s*"([^"]+)"/', $chunk, $nm)) {
            $title = buttercup_fb_decode_unicode($nm[1]);
        }
        if (preg_match('/"start_timestamp":\s*(\d+)/', $chunk, $ts)) {
            $start = gmdate('Y-m-d H:i:s', intval($ts[1]));
        }
        if (preg_match('/"end_timestamp":\s*(\d+)/', $chunk, $ts)) {
            $end = gmdate('Y-m-d H:i:s', intval($ts[1]));
        }
    }

    if (!$title) {
        return null;
    }

    return buttercup_fb_normalize_event([
        'title'       => $title,
        'start'       => $start,
        'end'         => $end,
        'location'    => $location,
        'url'         => 'https://www.facebook.com/events/' . $event_id,
        'image_url'   => '',
        'description' => '',
    ]);
}

/**
 * DOM-based extraction fallback. Parses event cards from visible HTML.
 */
function buttercup_fb_extract_dom_events($html)
{
    $events = [];

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Facebook event links typically match /events/DIGITS/.
    $links = $xpath->query('//a[contains(@href, "/events/")]');
    $seen_ids = [];

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (!preg_match('/\/events\/(\d{5,})/', $href, $m)) {
            continue;
        }

        $event_id = $m[1];
        if (isset($seen_ids[$event_id])) {
            continue;
        }
        $seen_ids[$event_id] = true;

        // Walk up to find the parent container card for this event link.
        $card = buttercup_fb_find_event_card($link);
        $title = '';
        $date_text = '';
        $location_text = '';
        $image_url = '';

        // Title: the link text itself, or nearby heading.
        $title = trim($link->textContent);
        if (!$title || strlen($title) < 3) {
            // Look for a nearby heading or strong text.
            if ($card) {
                $headings = $xpath->query('.//span[string-length(text()) > 3] | .//h2 | .//h3', $card);
                foreach ($headings as $h) {
                    $text = trim($h->textContent);
                    if (strlen($text) > 3 && strlen($text) < 200) {
                        $title = $text;
                        break;
                    }
                }
            }
        }

        if (!$title) {
            continue;
        }

        // Extract date and location from surrounding text in the card.
        if ($card) {
            $spans = $xpath->query('.//span', $card);
            foreach ($spans as $span) {
                $text = trim($span->textContent);
                if (!$text || $text === $title) {
                    continue;
                }
                // Date-like text: contains month names or day abbreviations.
                if (!$date_text && buttercup_fb_looks_like_date($text)) {
                    $date_text = $text;
                } elseif (!$location_text && strlen($text) > 3 && strlen($text) < 150 && !buttercup_fb_looks_like_date($text)) {
                    $location_text = $text;
                }
            }

            // Look for cover image.
            $imgs = $xpath->query('.//img', $card);
            foreach ($imgs as $img) {
                $src = $img->getAttribute('src');
                if ($src && strpos($src, 'scontent') !== false) {
                    $image_url = $src;
                    break;
                }
            }
        }

        $start = buttercup_fb_parse_date_text($date_text);

        $event = buttercup_fb_normalize_event([
            'title'       => $title,
            'start'       => $start,
            'end'         => '',
            'location'    => $location_text,
            'url'         => 'https://www.facebook.com/events/' . $event_id,
            'image_url'   => $image_url,
            'description' => '',
        ]);

        if ($event) {
            $events[] = $event;
        }
    }

    return $events;
}

/**
 * Walk up the DOM to find a likely event card container.
 */
function buttercup_fb_find_event_card($node)
{
    $current = $node->parentNode;
    $depth = 0;

    while ($current && $depth < 10) {
        if ($current instanceof DOMElement) {
            $role = $current->getAttribute('role');
            if ($role === 'article' || $role === 'listitem') {
                return $current;
            }
            // Look for div containers with multiple child elements (card-like).
            if ($current->tagName === 'div') {
                $child_count = 0;
                foreach ($current->childNodes as $child) {
                    if ($child instanceof DOMElement) {
                        $child_count++;
                    }
                }
                if ($child_count >= 2 && $depth >= 3) {
                    return $current;
                }
            }
        }
        $current = $current->parentNode;
        $depth++;
    }

    return $node->parentNode;
}

/**
 * Check if a string looks like a date/time.
 */
function buttercup_fb_looks_like_date($text)
{
    $months = 'JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC';
    $days = 'MON|TUE|WED|THU|FRI|SAT|SUN';
    $pattern = '/\b(' . $months . '|' . $days . '|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\b/i';
    return (bool) preg_match($pattern, $text);
}

/**
 * Parse a Facebook date text string into MySQL DATETIME.
 *
 * Handles patterns like:
 * - "SAT, JUN 15 AT 7:00 PM"
 * - "SATURDAY, JUNE 15, 2024 AT 7:00 PM"
 * - "JUN 15, 2024"
 * - "June 15 at 7 PM"
 *
 * @param string $text Date text from Facebook.
 * @return string MySQL DATETIME or empty string.
 */
function buttercup_fb_parse_date_text($text)
{
    if (!$text) {
        return '';
    }

    // Remove leading day-of-week abbreviation: "SAT, " or "SATURDAY, ".
    $clean = preg_replace('/^[A-Z]{2,10},?\s*/i', '', trim($text));

    // Replace " AT " with " " for strtotime.
    $clean = preg_replace('/\s+AT\s+/i', ' ', $clean);

    // Remove ordinal suffixes (1st, 2nd, 3rd, etc.).
    $clean = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $clean);

    // Try strtotime.
    $ts = strtotime($clean);
    if ($ts) {
        return wp_date('Y-m-d H:i:s', $ts);
    }

    // Last resort: try the original text.
    $ts = strtotime($text);
    if ($ts) {
        return wp_date('Y-m-d H:i:s', $ts);
    }

    return '';
}

/**
 * Normalize an event data array into standard format.
 *
 * @param array $raw Raw event data.
 * @return array|null Normalized event or null if title is missing.
 */
function buttercup_fb_normalize_event($raw)
{
    $title = trim($raw['title'] ?? '');
    if (!$title) {
        return null;
    }

    // Convert ISO dates to MySQL DATETIME if needed.
    $start = $raw['start'] ?? '';
    $end   = $raw['end'] ?? '';

    if ($start && strpos($start, 'T') !== false) {
        $ts = strtotime($start);
        $start = $ts ? wp_date('Y-m-d H:i:s', $ts) : '';
    }
    if ($end && strpos($end, 'T') !== false) {
        $ts = strtotime($end);
        $end = $ts ? wp_date('Y-m-d H:i:s', $ts) : '';
    }

    // Extract event ID from URL for uid.
    $url = $raw['url'] ?? '';
    $uid = '';
    if (preg_match('/\/events\/(\d+)/', $url, $m)) {
        $uid = 'fb-' . $m[1];
    }

    return [
        'title'       => $title,
        'description' => trim($raw['description'] ?? ''),
        'start'       => $start,
        'end'         => $end,
        'location'    => trim($raw['location'] ?? ''),
        'url'         => $url,
        'uid'         => $uid,
        'image_url'   => trim($raw['image_url'] ?? ''),
    ];
}

/**
 * Decode unicode escape sequences in a string.
 */
function buttercup_fb_decode_unicode($str)
{
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
        return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE');
    }, $str);
}

/**
 * Deduplicate events by URL.
 */
function buttercup_fb_deduplicate_events($events)
{
    $seen = [];
    $unique = [];

    foreach ($events as $event) {
        $key = $event['url'] ?: $event['title'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $event;
    }

    return $unique;
}
