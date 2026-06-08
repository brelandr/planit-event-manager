<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-deactivator.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 *
 * Handles cleanup tasks when the plugin is deactivated.
 */
class TWEC_Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
