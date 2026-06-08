<?php
/**
 * Debug helper for calendar issues.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-debug.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug helper for calendar issues.
 *
 * Provides debugging utilities for calendar queries and event display.
 */
class TWEC_Debug {

	/**
	 * Log calendar query for debugging.
	 *
	 * @param string $view         View type.
	 * @param string $date         Date string.
	 * @param array  $events_found Events found.
	 * @param array  $args         Query arguments.
	 */
	public static function log_calendar_query( $view, $date, $events_found, $args = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		error_log( 'TWEC Calendar Query: View=' . $view . ', Date=' . $date . ', Found=' . count( $events_found ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		if ( ! empty( $args ) ) {
			error_log( 'TWEC Query Args: ' . print_r( $args, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	/**
	 * Get event date info for debugging.
	 *
	 * @param int $event_id Event ID.
	 * @return array Event date information.
	 */
	public static function get_event_date_info( $event_id ) {
		$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
		$end_date   = get_post_meta( $event_id, '_twec_event_end_date', true );

		return array(
			'start_date_raw'    => $start_date,
			'end_date_raw'      => $end_date,
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for parsing stored datetime values in debug output
			'start_date_parsed' => $start_date ? gmdate( 'Y-m-d H:i:s', strtotime( $start_date ) ) : null,
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for parsing stored datetime values in debug output
			'end_date_parsed'   => $end_date ? gmdate( 'Y-m-d H:i:s', strtotime( $end_date ) ) : null,
		);
	}
}
