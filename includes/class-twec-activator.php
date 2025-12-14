<?php
/**
 * Fired during plugin activation.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		// Register post types first
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-post-types.php';
		$post_types = new TWEC_Post_Types();
		$post_types->register_post_types();
		$post_types->register_taxonomies();
		
		// Flush rewrite rules
		flush_rewrite_rules();
		
		// Set default options
		$default_options = array(
			'hide_past_events' => 'no',
			'events_per_page' => 10,
			'date_format' => 'F j, Y',
			'time_format' => 'g:i A',
			'google_maps_api_key' => '',
		);
		
		add_option( 'twec_settings', $default_options );
		
		// Set a flag to flush rewrite rules on next page load
		set_transient( 'twec_flush_rewrite_rules', 1, 60 );
	}
}
