<?php
/**
 * Shared Event Data datetime normalization and range validation.
 *
 * Used by classic metabox save, REST quick-add, and REST API pre-dispatch checks.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize and validate event start/end storage (matches metabox + Quick Add storage shape).
 */
class TWEC_Event_Datetime {

	/**
	 * Normalize a time fragment to H:i:s for storage.
	 *
	 * @param string $t        Raw time (may be HH:MM or HH:MM:SS).
	 * @param string $fallback Default H:i:s when empty/invalid.
	 * @return string
	 */
	public static function normalize_time_for_storage( $t, $fallback ) {
		$t = trim( (string) $t );
		if ( '' === $t ) {
			return $fallback;
		}
		if ( preg_match( '/^\d{2}:\d{2}$/', $t ) ) {
			return $t . ':00';
		}
		if ( preg_match( '/^\d{2}:\d{2}:\d{2}$/', $t ) ) {
			return $t;
		}
		return $fallback;
	}

	/**
	 * Whether the string is a valid Y-m-d date.
	 *
	 * @param string $d Date string.
	 * @return bool
	 */
	public static function is_valid_ymd( $d ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d );
	}

	/**
	 * Build storage fields from raw inputs (all-day vs timed).
	 *
	 * @param bool|string $all_day        True/'1'/false/'0'.
	 * @param string      $start_date     Y-m-d.
	 * @param string      $end_date       Y-m-d.
	 * @param string      $start_time_raw Time fragment or empty.
	 * @param string      $end_time_raw   Time fragment or empty.
	 * @return array<string,string|bool>|WP_Error {
	 *     @type string $start_dt Full start datetime string.
	 *     @type string $end_dt   Full end datetime string.
	 *     @type string $start_t  _twec_event_start_time.
	 *     @type string $end_t    _twec_event_end_time.
	 *     @type string $all_day_meta '1' or '0'.
	 * }
	 */
	public static function validate_and_build_storage( $all_day, $start_date, $end_date, $start_time_raw, $end_time_raw ) {
		$start_date = trim( (string) $start_date );
		$end_date   = trim( (string) $end_date );

		if ( ! self::is_valid_ymd( $start_date ) || ! self::is_valid_ymd( $end_date ) ) {
			return new WP_Error(
				'twec_event_dates',
				__( 'Start and end dates must use Y-m-d.', 'planit-event-manager' ),
				array( 'status' => 400 )
			);
		}

		$all_day_b = ( true === $all_day ) || in_array( (string) $all_day, array( '1', 'yes', 'true' ), true );

		if ( $all_day_b ) {
			$start_t = '00:00:00';
			$end_t   = '23:59:59';
		} else {
			$start_t = self::normalize_time_for_storage( $start_time_raw, '00:00:00' );
			$end_t   = self::normalize_time_for_storage( $end_time_raw, '23:59:59' );
		}

		$start_dt = $start_date . ' ' . $start_t;
		$end_dt   = $end_date . ' ' . $end_t;

		if ( strtotime( $start_dt ) > strtotime( $end_dt ) ) {
			return new WP_Error(
				'twec_event_range',
				__( 'End must be on or after the start.', 'planit-event-manager' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'start_dt'     => $start_dt,
			'end_dt'       => $end_dt,
			'start_t'      => $start_t,
			'end_t'        => $end_t,
			'all_day_meta' => $all_day_b ? '1' : '0',
		);
	}

	/**
	 * Merge request meta into existing meta and validate Event Data datetime range when relevant keys appear.
	 *
	 * @param array<string,mixed> $existing_meta Current postmeta map (underscore keys).
	 * @param array<string,mixed> $request_meta   Incoming meta patch from REST.
	 * @return true|WP_Error
	 */
	public static function validate_merged_rest_meta( array $existing_meta, array $request_meta ) {
		$keys  = array(
			'_twec_event_all_day',
			'_twec_event_start_date',
			'_twec_event_end_date',
			'_twec_event_start_time',
			'_twec_event_end_time',
		);
		$touch = false;
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $request_meta ) ) {
				$touch = true;
				break;
			}
		}
		if ( ! $touch ) {
			return true;
		}

		$all_day = isset( $request_meta['_twec_event_all_day'] )
			? $request_meta['_twec_event_all_day']
			: ( isset( $existing_meta['_twec_event_all_day'] ) ? $existing_meta['_twec_event_all_day'] : '0' );

		// Derive dates from *_date meta (stored as full datetime string) or reconstruct.
		$start_full = isset( $request_meta['_twec_event_start_date'] )
			? (string) $request_meta['_twec_event_start_date']
			: ( isset( $existing_meta['_twec_event_start_date'] ) ? (string) $existing_meta['_twec_event_start_date'] : '' );
		$end_full   = isset( $request_meta['_twec_event_end_date'] )
			? (string) $request_meta['_twec_event_end_date']
			: ( isset( $existing_meta['_twec_event_end_date'] ) ? (string) $existing_meta['_twec_event_end_date'] : '' );

		if ( '' === trim( $start_full ) || '' === trim( $end_full ) ) {
			return true;
		}

		$start_d = self::ymd_from_stored_datetime( $start_full );
		$end_d   = self::ymd_from_stored_datetime( $end_full );

		if ( '' === $start_d || '' === $end_d ) {
			return true;
		}

		$start_time_raw = isset( $request_meta['_twec_event_start_time'] )
			? (string) $request_meta['_twec_event_start_time']
			: ( isset( $existing_meta['_twec_event_start_time'] ) ? (string) $existing_meta['_twec_event_start_time'] : '' );
		$end_time_raw   = isset( $request_meta['_twec_event_end_time'] )
			? (string) $request_meta['_twec_event_end_time']
			: ( isset( $existing_meta['_twec_event_end_time'] ) ? (string) $existing_meta['_twec_event_end_time'] : '' );

		$result = self::validate_and_build_storage( $all_day, $start_d, $end_d, $start_time_raw, $end_time_raw );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	/**
	 * Extract Y-m-d from stored datetime or return the string if already a date.
	 *
	 * @param string $stored Value like "2025-01-15 10:00:00" or "2025-01-15".
	 * @return string
	 */
	public static function ymd_from_stored_datetime( $stored ) {
		$stored = trim( (string) $stored );
		if ( '' === $stored ) {
			return '';
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $stored, $m ) ) {
			return $m[1];
		}
		return '';
	}
}
