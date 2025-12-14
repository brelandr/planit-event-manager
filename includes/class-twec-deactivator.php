<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}
