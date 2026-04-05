<?php
/**
 * ICalendar (.ics) feed parser for event imports.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse iCalendar (.ics) content into an array of event data.
 *
 * @param string $ics_content Raw iCalendar file contents.
 * @return array Array of parsed events, each with keys:
 *               title, description, start, end, location, url, uid
 */
function buttercup_parse_ical( $ics_content ) {
	// Normalize line endings.
	$content = str_replace( array( "\r\n", "\r" ), "\n", $ics_content );

	// Unfold continuation lines (RFC 5545 §3.1: lines starting with space/tab are continuations).
	$content = preg_replace( '/\n[ \t]/', '', $content );

	// Extract calendar-level default timezone (X-WR-TIMEZONE).
	$default_tz = '';
	if ( preg_match( '/^X-WR-TIMEZONE[;:](.+)$/mi', $content, $tz_match ) ) {
		$default_tz = trim( $tz_match[1] );
	}

	// Extract VEVENT blocks.
	$events  = array();
	$pattern = '/BEGIN:VEVENT\n(.*?)END:VEVENT/s';
	if ( ! preg_match_all( $pattern, $content, $matches ) ) {
		return array();
	}

	foreach ( $matches[1] as $vevent_block ) {
		$event = buttercup_parse_ical_vevent( $vevent_block, $default_tz );
		if ( $event && $event['title'] ) {
			$events[] = $event;
		}
	}

	return $events;
}

/**
 * Parse a single VEVENT block into event data.
 *
 * @param string $block      The content between BEGIN:VEVENT and END:VEVENT.
 * @param string $default_tz Calendar-level default timezone (from X-WR-TIMEZONE).
 * @return array|null Parsed event data or null if invalid.
 */
function buttercup_parse_ical_vevent( $block, $default_tz = '' ) {
	$lines      = explode( "\n", trim( $block ) );
	$properties = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		// Split property name from value at the first colon.
		// But property names can have parameters separated by semicolons before the colon.
		// Example: DTSTART;TZID=America/Chicago:20260415T190000.
		$colon_pos = strpos( $line, ':' );
		if ( false === $colon_pos ) {
			continue;
		}

		$key_part = substr( $line, 0, $colon_pos );
		$value    = substr( $line, $colon_pos + 1 );

		// Extract the base property name (before any ;PARAM=...).
		$semicolon_pos = strpos( $key_part, ';' );
		if ( false !== $semicolon_pos ) {
			$prop_name  = strtoupper( substr( $key_part, 0, $semicolon_pos ) );
			$params_str = substr( $key_part, $semicolon_pos + 1 );
		} else {
			$prop_name  = strtoupper( $key_part );
			$params_str = '';
		}

		$properties[ $prop_name ] = array(
			'value'  => $value,
			'params' => $params_str,
		);
	}

	$title = buttercup_ical_unescape(
		$properties['SUMMARY']['value'] ?? ''
	);

	if ( ! $title ) {
		return null;
	}

	$description = buttercup_ical_unescape(
		$properties['DESCRIPTION']['value'] ?? ''
	);

	$location = buttercup_ical_unescape(
		$properties['LOCATION']['value'] ?? ''
	);

	$url = $properties['URL']['value'] ?? '';
	$uid = $properties['UID']['value'] ?? '';

	// Parse dates.
	$start = buttercup_ical_parse_datetime(
		$properties['DTSTART']['value'] ?? '',
		$properties['DTSTART']['params'] ?? '',
		$default_tz
	);

	$end = buttercup_ical_parse_datetime(
		$properties['DTEND']['value'] ?? '',
		$properties['DTEND']['params'] ?? '',
		$default_tz
	);

	// If no DTEND, check DURATION.
	if ( ! $end && $start && isset( $properties['DURATION'] ) ) {
		$end = buttercup_ical_apply_duration( $start, $properties['DURATION']['value'] );
	}

	return array(
		'title'       => $title,
		'description' => $description,
		'start'       => $start,
		'end'         => $end,
		'location'    => $location,
		'url'         => $url,
		'uid'         => $uid,
		'image_url'   => '',
	);
}

/**
 * Parse an iCal datetime value into MySQL DATETIME format.
 *
 * Handles:
 * - YYYYMMDDTHHMMSS (local time)
 * - YYYYMMDDTHHMMSSZ (UTC)
 * - TZID=America/Chicago:YYYYMMDDTHHMMSS (explicit timezone in params)
 * - YYYYMMDD (all-day event, VALUE=DATE)
 *
 * @param string $value      The datetime value.
 * @param string $params     The parameter string (e.g., "TZID=America/Chicago" or "VALUE=DATE").
 * @param string $default_tz Calendar-level default timezone (from X-WR-TIMEZONE).
 * @return string MySQL DATETIME string in site timezone, or empty string.
 */
function buttercup_ical_parse_datetime( $value, $params = '', $default_tz = '' ) {
	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}

	// Check for VALUE=DATE (all-day event).
	if ( false !== stripos( $params, 'VALUE=DATE' ) && 8 === strlen( $value ) ) {
		// YYYYMMDD to Y-m-d 00:00:00.
		$ts = strtotime( $value );
		return $ts ? wp_date( 'Y-m-d', $ts ) . ' 00:00:00' : '';
	}

	// Extract TZID if present.
	$tzid = '';
	if ( preg_match( '/TZID=([^;:]+)/i', $params, $tz_match ) ) {
		$tzid = $tz_match[1];
	}

	// Normalize the datetime string: YYYYMMDDTHHMMSS to Y-m-d H:i:s.
	$is_utc = substr( $value, -1 ) === 'Z';
	$clean  = rtrim( $value, 'Z' );

	// Parse YYYYMMDDTHHMMSS format.
	if ( preg_match( '/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})$/', $clean, $m ) ) {
		$iso = "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
	} elseif ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $clean, $m ) ) {
		$iso = "{$m[1]}-{$m[2]}-{$m[3]} 00:00:00";
	} else {
		// Try native parsing as a fallback.
		$ts = strtotime( $value );
		return $ts ? wp_date( 'Y-m-d H:i:s', $ts ) : '';
	}

	// Convert to site timezone.
	$site_tz = wp_timezone();

	if ( $is_utc ) {
		$dt = date_create( $iso, new DateTimeZone( 'UTC' ) );
	} elseif ( $tzid ) {
		$source_tz = buttercup_ical_resolve_timezone( $tzid, $site_tz );
		$dt        = date_create( $iso, $source_tz );
	} else {
		// No explicit TZID — use calendar-level default timezone if available,
		// otherwise fall back to site timezone for floating times.
		if ( $default_tz ) {
			$source_tz = buttercup_ical_resolve_timezone( $default_tz, $site_tz );
		} else {
			$source_tz = $site_tz;
		}
		$dt = date_create( $iso, $source_tz );
	}

	if ( ! $dt ) {
		return '';
	}

	$dt->setTimezone( $site_tz );
	return $dt->format( 'Y-m-d H:i:s' );
}

/**
 * Resolve a timezone identifier to a DateTimeZone object.
 *
 * Handles IANA identifiers, Windows timezone names (from Outlook),
 * and path-style identifiers (from Thunderbird/Mozilla).
 *
 * @param string       $tzid    The timezone identifier from the ICS file.
 * @param DateTimeZone $fallback Fallback timezone if resolution fails.
 * @return DateTimeZone
 */
function buttercup_ical_resolve_timezone( $tzid, $fallback ) {
	$tzid = trim( $tzid, '"' );

	// Try the identifier directly first (handles all valid IANA IDs).
	try {
		return new DateTimeZone( $tzid );
	// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	} catch ( \Exception $e ) {
		// Silently continue to fallback strategies.
	}

	// Strip path prefixes from Mozilla/Thunderbird-style TZIDs.
	// Example: "/mozilla.org/20050126_1/America/Chicago" becomes "America/Chicago".
	if ( preg_match( '#(?:^|/)([A-Za-z]+/[A-Za-z_]+(?:/[A-Za-z_]+)?)$#', $tzid, $m ) ) {
		try {
			return new DateTimeZone( $m[1] );
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Silently skip invalid stripped timezone identifier.
		}
	}

	// Map common Windows timezone names to IANA identifiers.
	static $windows_map = array(
		'Eastern Standard Time'          => 'America/New_York',
		'Central Standard Time'          => 'America/Chicago',
		'Mountain Standard Time'         => 'America/Denver',
		'Pacific Standard Time'          => 'America/Los_Angeles',
		'Alaska Standard Time'           => 'America/Anchorage',
		'Hawaiian Standard Time'         => 'Pacific/Honolulu',
		'Atlantic Standard Time'         => 'America/Halifax',
		'Newfoundland Standard Time'     => 'America/St_Johns',
		'GMT Standard Time'              => 'Europe/London',
		'W. Europe Standard Time'        => 'Europe/Berlin',
		'Romance Standard Time'          => 'Europe/Paris',
		'Central European Standard Time' => 'Europe/Warsaw',
		'E. Europe Standard Time'        => 'Europe/Chisinau',
		'FLE Standard Time'              => 'Europe/Kiev',
		'GTB Standard Time'              => 'Europe/Bucharest',
		'Russian Standard Time'          => 'Europe/Moscow',
		'AUS Eastern Standard Time'      => 'Australia/Sydney',
		'China Standard Time'            => 'Asia/Shanghai',
		'Tokyo Standard Time'            => 'Asia/Tokyo',
		'India Standard Time'            => 'Asia/Kolkata',
		'Korea Standard Time'            => 'Asia/Seoul',
		'Singapore Standard Time'        => 'Asia/Singapore',
		'New Zealand Standard Time'      => 'Pacific/Auckland',
		'SA Pacific Standard Time'       => 'America/Bogota',
		'E. South America Standard Time' => 'America/Sao_Paulo',
		'US Eastern Standard Time'       => 'America/Indianapolis',
		'US Mountain Standard Time'      => 'America/Phoenix',
		'Canada Central Standard Time'   => 'America/Regina',
		'Central America Standard Time'  => 'America/Guatemala',
		'Mexico Standard Time'           => 'America/Mexico_City',
	);

	if ( isset( $windows_map[ $tzid ] ) ) {
		try {
			return new DateTimeZone( $windows_map[ $tzid ] );
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Silently skip invalid mapped timezone identifier.
		}
	}

	return $fallback;
}

/**
 * Apply an iCal DURATION to a start datetime.
 *
 * @param string $start    MySQL DATETIME start string.
 * @param string $duration iCal duration (e.g., "PT2H30M", "P1D").
 * @return string MySQL DATETIME end string, or empty.
 */
function buttercup_ical_apply_duration( $start, $duration ) {
	$duration = trim( $duration );
	if ( ! $start || ! $duration ) {
		return '';
	}

	try {
		$dt       = date_create( $start, wp_timezone() );
		$interval = new DateInterval( $duration );
		$dt->add( $interval );
		return $dt->format( 'Y-m-d H:i:s' );
	} catch ( \Exception $e ) {
		return '';
	}
}

/**
 * Unescape iCal text values.
 *
 * @param string $text Escaped iCal text.
 * @return string Unescaped plain text.
 */
function buttercup_ical_unescape( $text ) {
	$text = str_replace( '\\n', "\n", $text );
	$text = str_replace( '\\N', "\n", $text );
	$text = str_replace( '\\,', ',', $text );
	$text = str_replace( '\\;', ';', $text );
	$text = str_replace( '\\\\', '\\', $text );
	return trim( $text );
}
