<?php
/**
 * Shared helpers (performance, prefixes). Loaded from the main plugin file before Premium short-circuit.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return PHP timezone identifiers with a 24-hour transient cache (avoids rebuilding a large list on every admin render).
 *
 * Transient key is prefixed with the plugin slug to avoid collisions.
 *
 * @return string[]
 */
function planit_event_manager_get_timezone_identifiers() {
	$key    = 'planit_event_manager_tz_identifiers_v1';
	$cached = get_transient( $key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$list = function_exists( 'timezone_identifiers_list' ) ? timezone_identifiers_list() : array();
	if ( ! is_array( $list ) ) {
		return array();
	}

	set_transient( $key, $list, DAY_IN_SECONDS );

	return $list;
}
