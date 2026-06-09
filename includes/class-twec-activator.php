<?php
/**
 * Fired during plugin activation.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-activator.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * Handles activation tasks including post type registration and default options setup.
 */
if ( ! class_exists( 'TWEC_Activator', false ) ) {

class TWEC_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-payment-log.php';
		TWEC_Payment_Log::install();
		update_option( TWEC_Payment_Log::OPTION_VERSION, TWEC_Payment_Log::DB_VERSION );

		// Register post types first.
		require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-post-types.php';
		$post_types = new TWEC_Post_Types();
		$post_types->register_post_types();
		$post_types->register_taxonomies();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set default options.
		$default_options = array(
			'hide_past_events'    => 'no',
			'events_per_page'     => 10,
			'date_format'         => 'F j, Y',
			'time_format'         => 'g:i A',
			'google_maps_api_key' => '',
		);

		add_option( 'twec_settings', $default_options );

		// Set install date for review request (only on first activation).
		add_option( 'twec_install_date', time() );

		// Set a flag to flush rewrite rules on next page load.
		set_transient( 'twec_flush_rewrite_rules', 1, 60 );

		/**
		 * Performance Note: For optimal query performance with large event databases,
		 * consider adding indexes to the wp_postmeta table for the following meta keys:
		 * - _twec_event_start_date
		 * - _twec_event_end_date
		 * - _twec_is_test_event
		 * - _twec_is_featured
		 *
		 * These indexes can be added via a database management tool or custom SQL:
		 * CREATE INDEX idx_twec_start_date ON wp_postmeta(meta_key, meta_value(20)) WHERE meta_key = '_twec_event_start_date';
		 * CREATE INDEX idx_twec_end_date ON wp_postmeta(meta_key, meta_value(20)) WHERE meta_key = '_twec_event_end_date';
		 *
		 * Note: Index names and table prefix may vary. Use $wpdb->prefix for the correct table name.
		 */
	}
}

}
