<?php
/**
 * Define the internationalization functionality.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_i18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'the-wordpress-event-calendar',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
