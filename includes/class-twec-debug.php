<?php
/**
 * Debug helper for calendar issues.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Debug {

	/**
	 * Log calendar query for debugging.
	 */
	public static function log_calendar_query( $view, $date, $events_found, $args = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		
		error_log( 'TWEC Calendar Query: View=' . $view . ', Date=' . $date . ', Found=' . count( $events_found ) );
		if ( ! empty( $args ) ) {
			error_log( 'TWEC Query Args: ' . print_r( $args, true ) );
		}
	}
	
	/**
	 * Get event date info for debugging.
	 */
	public static function get_event_date_info( $event_id ) {
		$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
		$end_date = get_post_meta( $event_id, '_twec_event_end_date', true );
		
		return array(
			'start_date_raw' => $start_date,
			'end_date_raw' => $end_date,
			'start_date_parsed' => $start_date ? date( 'Y-m-d H:i:s', strtotime( $start_date ) ) : null,
			'end_date_parsed' => $end_date ? date( 'Y-m-d H:i:s', strtotime( $end_date ) ) : null,
		);
	}
}

