<?php
/**
 * Define the internationalization functionality.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internationalization class.
 *
 * Handles plugin text domain loading.
 */
class TWEC_I18n {

	/**
	 * Load the plugin text domain for translation.
	 * Note: Since WordPress 4.6, load_plugin_textdomain() is discouraged for WordPress.org plugins.
	 * WordPress automatically loads translations. This is kept for backward compatibility
	 * and custom translation file locations if needed.
	 */
	public function load_plugin_textdomain() {
		// WordPress.org plugins don't need this - WordPress auto-loads translations since 4.6.
		// Only use if you have a specific need (e.g., custom translation file location).
		// If needed, use: plugin_dir_path( __FILE__ ) . '../languages/'
		// or: plugin_dir_path( dirname( __FILE__ ) ) . 'languages/'.
	}
}
