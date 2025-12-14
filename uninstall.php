<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    The_WordPress_Event_Calendar
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'twec_settings' );

// Flush rewrite rules
flush_rewrite_rules();
