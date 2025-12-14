<?php
/**
 * The core plugin class.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->flush_rewrite_rules_if_needed();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Flush rewrite rules if needed after activation.
	 */
	private function flush_rewrite_rules_if_needed() {
		if ( get_transient( 'twec_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'twec_flush_rewrite_rules' );
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-loader.php';
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-i18n.php';
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-premium.php';
		require_once TWEC_PLUGIN_DIR . 'admin/class-twec-admin.php';
		require_once TWEC_PLUGIN_DIR . 'public/class-twec-public.php';
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-post-types.php';
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-settings.php';
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-shortcodes.php';
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-widget.php';
		require_once TWEC_PLUGIN_DIR . 'admin/class-twec-meta-boxes.php';
		require_once TWEC_PLUGIN_DIR . 'includes/class-twec-search.php';
		
		// Premium features - only load if premium is available
		if ( TWEC_Premium::is_available( 'import' ) ) {
			require_once TWEC_PLUGIN_DIR . 'includes/class-twec-importer.php';
		}
		if ( TWEC_Premium::is_available( 'recurring' ) ) {
			require_once TWEC_PLUGIN_DIR . 'includes/class-twec-recurring.php';
		}
		if ( TWEC_Premium::is_available( 'custom_fields' ) ) {
			require_once TWEC_PLUGIN_DIR . 'includes/class-twec-custom-fields.php';
		}
		if ( TWEC_Premium::is_available( 'pro_features' ) ) {
			require_once TWEC_PLUGIN_DIR . 'includes/class-twec-pro-features.php';
		}

		$this->loader = new TWEC_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new TWEC_Admin();
		
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_filter( 'plugin_action_links_' . TWEC_PLUGIN_BASENAME, $plugin_admin, 'add_plugin_action_links' );
		$this->loader->add_filter( 'plugin_row_meta', $plugin_admin, 'add_plugin_row_meta', 10, 2 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new TWEC_Public();
		
		// Store instance globally for use in templates
		global $twec_public_instance;
		$twec_public_instance = $plugin_public;
		
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_filter( 'template_include', $plugin_public, 'event_template' );
		$this->loader->add_action( 'pre_get_posts', $plugin_public, 'modify_event_query' );
		$this->loader->add_action( 'wp_ajax_twec_get_calendar', $plugin_public, 'ajax_get_calendar' );
		$this->loader->add_action( 'wp_ajax_nopriv_twec_get_calendar', $plugin_public, 'ajax_get_calendar' );
		$this->loader->add_action( 'init', $plugin_public, 'handle_ical_export' );
		$this->loader->add_action( 'init', $plugin_public, 'handle_google_calendar_export' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->set_locale();
		$this->loader->run();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$plugin_i18n = new TWEC_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}
}

