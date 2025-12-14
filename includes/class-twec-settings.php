<?php
/**
 * Settings functionality.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Settings {

	/**
	 * Get a setting value.
	 */
	public static function get( $key, $default = '' ) {
		$settings = get_option( 'twec_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}

