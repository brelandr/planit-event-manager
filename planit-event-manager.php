<?php
/**
 * Plugin Name: PlanIt Event Manager
 * Plugin URI: https://wordpress.org/plugins/planit-event-manager
 * Description: A free event calendar plugin with calendar views (day, month), list view, venues, organizers, and more. Upgrade to Premium for advanced features!
 * Version: 1.0.12
 * Author: Land Tech Web Designs, Corp
 * Author URI: https://landtechwebdesigns.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: planit-event-manager
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.2
 *
 * @package The_Event_Calendar
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * This plugin’s directory, basename, and file (always defined for activation hooks and path checks).
 */
define( 'PLANIT_EVENT_MANAGER_VERSION', '1.0.12' );
define( 'PLANIT_EVENT_MANAGER_FILE', __FILE__ );
define( 'PLANIT_EVENT_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLANIT_EVENT_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'PLANIT_EVENT_MANAGER_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'PLANIT_PREMIUM_LIVE_DEMO_URL' ) ) {
	define(
		'PLANIT_PREMIUM_LIVE_DEMO_URL',
		'https://app.instawp.io/launch?s=planit---demo&d=v2'
	);
}

require_once PLANIT_EVENT_MANAGER_DIR . 'includes/planit-event-manager-helpers.php';

/**
 * Whether PlanIt Event Manager Premium is active (site or network).
 *
 * @return bool
 */
function planit_event_manager_premium_is_active() {
	$premium_basename = 'planit-event-manager-premium/planit-event-manager-premium.php';
	$active           = (array) get_option( 'active_plugins', array() );
	if ( in_array( $premium_basename, $active, true ) ) {
		return true;
	}
	if ( is_multisite() ) {
		$network = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( array_key_exists( $premium_basename, $network ) ) {
			return true;
		}
	}
	return false;
}

/**
 * True while the premium plugin is being activated in this request (not yet in active_plugins).
 * Skips loading free’s TWEC so premium can load its own class (avoids redeclare fatals).
 *
 * @return bool
 */
function planit_event_manager_premium_is_being_activated() {
	$premium_basename = 'planit-event-manager-premium/planit-event-manager-premium.php';

	// phpcs:ignore WordPress.Security.NonceVerification -- Request shape only; we do not authenticate activation here.
	$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification
	if ( 'activate' === $get_action && ! empty( $_GET['plugin'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		$plugin = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
		if ( $premium_basename === $plugin ) {
			return true;
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification
	$post_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification
	if ( 'activate-selected' === $post_action && ! empty( $_POST['checked'] ) && is_array( $_POST['checked'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		$checked = array_map( 'sanitize_text_field', wp_unslash( $_POST['checked'] ) );
		if ( in_array( $premium_basename, $checked, true ) ) {
			return true;
		}
	}

	// WP-CLI: wp plugin activate planit-event-manager-premium/...
	if ( defined( 'WP_CLI' ) && WP_CLI && ! empty( $GLOBALS['argv'] ) && is_array( $GLOBALS['argv'] ) ) {
		$args = $GLOBALS['argv'];
		$pos  = array_search( 'activate', $args, true );
		if ( false !== $pos && isset( $args[ $pos + 1 ] ) && is_string( $args[ $pos + 1 ] ) ) {
			$target = $args[ $pos + 1 ];
			if ( false !== strpos( $target, 'planit-event-manager-premium' ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Activation hook.
 */
function twec_activate() {
	require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-activator.php';
	TWEC_Activator::activate();
}
register_activation_hook( __FILE__, 'twec_activate' );

/**
 * Deactivation hook.
 */
function twec_deactivate() {
	require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-deactivator.php';
	TWEC_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'twec_deactivate' );

/**
 * Show a notice on the plugins screen that the free package remains active as a companion to Premium.
 *
 * @return void
 */
function planit_event_manager_companion_admin_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'plugins' !== $screen->id ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( get_user_meta( $user_id, 'planit_free_companion_notice_dismissed', true ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via wp_verify_nonce( wp_unslash( ... ), ... ); companion flag is not persisted raw.
	if ( isset( $_GET['planit_dismiss_companion'], $_GET['_wpnonce'] ) && '1' === (string) wp_unslash( $_GET['planit_dismiss_companion'] ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'planit_dismiss_companion' ) ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to dismiss this notice.', 'planit-event-manager' ), '', array( 'response' => 403 ) );
		}
		update_user_meta( $user_id, 'planit_free_companion_notice_dismissed', '1' );
		if ( ! headers_sent() ) {
			wp_safe_redirect( remove_query_arg( array( 'planit_dismiss_companion', '_wpnonce' ) ) );
			exit;
		}
	}
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$dismiss_url = wp_nonce_url(
		add_query_arg( 'planit_dismiss_companion', '1' ),
		'planit_dismiss_companion'
	);
	echo '<div class="notice notice-info is-dismissible"><p>';
	esc_html_e( 'PlanIt Event Manager: The free plugin must stay active when PlanIt Event Manager Premium is active (WordPress.org updates and shared code). Premium re-activates the free plugin if it is turned off. Do not deactivate the free plugin while using Premium unless you have deactivated Premium first.', 'planit-event-manager' );
	echo ' ';
	echo '<a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'planit-event-manager' ) . '</a>';
	echo '</p></div>';
}

if ( planit_event_manager_premium_is_active() || planit_event_manager_premium_is_being_activated() ) {
	// Show companion notice only once premium is actually active (not mid-activation).
	if ( planit_event_manager_premium_is_active() ) {
		add_action( 'admin_notices', 'planit_event_manager_companion_admin_notice' );
	}
	// PlanIt Event Manager Premium provides the runtime; avoid duplicate classes and admin menus.
	return;
}

/**
 * Core plugin class.
 * Note: The free build uses PLANIT_EVENT_MANAGER_* only so TWEC_* is never set; PlanIt Event Manager
 * Premium can define TWEC_PLUGIN_DIR and related constants for the premium runtime.
 */
if ( class_exists( 'TWEC', false ) ) {
	// PlanIt Event Manager Premium (or a custom load order) registered TWEC first; do not load a second class.
	return;
}
require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec.php';

/**
 * Begins execution of the plugin.
 */
function twec_run() {
	$plugin = new TWEC();
	$plugin->run();
}

// Delay plugin initialization until after WordPress is fully loaded.
// This prevents issues during plugin activation.
add_action( 'plugins_loaded', 'twec_run', 1 );
