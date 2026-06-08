<?php
/**
 * Settings functionality.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-settings.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings functionality.
 *
 * Handles plugin settings retrieval and management.
 */
class TWEC_Settings {

	/**
	 * Get a setting value.
	 *
	 * @param string $key          Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed Setting value.
	 */
	public static function get( $key, $default_value = '' ) {
		$settings = get_option( 'twec_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
	}
}
