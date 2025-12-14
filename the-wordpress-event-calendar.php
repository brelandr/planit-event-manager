<?php
/**
 * Plugin Name: The WordPress Event Calendar
 * Plugin URI: https://landtechwebdesigns.com
 * Description: A free WordPress event calendar plugin with calendar views (day, month), list view, venues, organizers, and more. Upgrade to Premium for advanced features!
 * Version: 1.0.0
 * Author: Land Tech Web Designs, Corp
 * Author URI: https://landtechwebdesigns.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: the-wordpress-event-calendar
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package The_WordPress_Event_Calendar
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'TWEC_VERSION', '1.0.0' );
define( 'TWEC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TWEC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TWEC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activation hook.
 */
function twec_activate() {
	require_once TWEC_PLUGIN_DIR . 'includes/class-twec-activator.php';
	TWEC_Activator::activate();
}
register_activation_hook( __FILE__, 'twec_activate' );

/**
 * Deactivation hook.
 */
function twec_deactivate() {
	require_once TWEC_PLUGIN_DIR . 'includes/class-twec-deactivator.php';
	TWEC_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'twec_deactivate' );

/**
 * Core plugin class.
 */
require_once TWEC_PLUGIN_DIR . 'includes/class-twec.php';

/**
 * Begins execution of the plugin.
 */
function twec_run() {
	$plugin = new TWEC();
	$plugin->run();
}
twec_run();
