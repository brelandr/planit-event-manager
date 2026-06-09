<?php
/**
 * The core plugin class.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWEC', false ) ) {
	/**
	 * Core orchestration: dependency loading, loader wiring, and hooks.
	 */
	class TWEC {

		/**
		 * The loader that's responsible for maintaining and registering all hooks.
		 *
		 * @var TWEC_Loader
		 */
		protected $loader;

		/**
		 * Define the core functionality of the plugin.
		 */
		public function __construct() {
			$this->load_dependencies();

			add_action(
				'plugins_loaded',
				static function () {
					if ( class_exists( 'TWEC_Payment_Log', false ) ) {
						TWEC_Payment_Log::maybe_install();
					}
				},
				5,
				0
			);

			$this->define_admin_hooks();
			$this->define_public_hooks();
			// Flush rewrite rules after hooks are defined.
			add_action( 'init', array( $this, 'flush_rewrite_rules_if_needed' ), 999 );
		}

		/**
		 * Flush rewrite rules if needed after activation.
		 */
		public function flush_rewrite_rules_if_needed() {
			if ( get_transient( 'twec_flush_rewrite_rules' ) ) {
				flush_rewrite_rules();
				delete_transient( 'twec_flush_rewrite_rules' );
			}
		}

		/**
		 * Load the required dependencies for this plugin.
		 */
		private function load_dependencies() {
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/twec-interactivity.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/twec-premium-compat.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-event-datetime.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-payment-log.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-privacy.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-email-templates.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-loader.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-i18n.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-premium.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'admin/class-twec-admin.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'public/class-twec-public.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-post-types.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-settings.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-shortcodes.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-widget.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'admin/class-twec-meta-boxes.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-search.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-review-request.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-seo.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-permalinks.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-breadcrumbs.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-rest.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-view-counter.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-reminders.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-premium-pillars.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-payments-stripe.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-payments-paypal.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-woocommerce.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-woo-series-pass.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-collab-rd.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-ai.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-abilities.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'admin/class-twec-dashboard.php';
			require_once PLANIT_EVENT_MANAGER_DIR . 'admin/class-twec-onboarding.php';

			// Premium features - only load if premium is available.
			if ( TWEC_Premium::is_available( 'import' ) ) {
				require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-importer.php';
			}
			if ( TWEC_Premium::is_available( 'recurring' ) ) {
				require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-recurring.php';
			}
			if ( TWEC_Premium::is_available( 'custom_fields' ) ) {
				require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-custom-fields.php';
			}
			if ( TWEC_Premium::is_available( 'pro_features' ) ) {
				require_once PLANIT_EVENT_MANAGER_DIR . 'includes/class-twec-pro-features.php';
				// Initialize pro features.
				new TWEC_Pro_Features();
			}
			if ( TWEC_Premium::is_available( 'recurring' ) ) {
				// Initialize recurring events.
				new TWEC_Recurring();
			}
			if ( TWEC_Premium::is_available( 'custom_fields' ) ) {
				// Initialize custom fields.
				new TWEC_Custom_Fields();
			}
			if ( TWEC_Premium::is_available( 'import' ) ) {
				// Initialize importer.
				new TWEC_Importer();
			}

			$this->loader = new TWEC_Loader();

			// Initialize post types and taxonomies.
			$post_types = new TWEC_Post_Types();
			$this->loader->add_action( 'init', $post_types, 'register_post_types' );
			$this->loader->add_action( 'init', $post_types, 'register_taxonomies' );
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
			$this->loader->add_filter( 'plugin_action_links_' . PLANIT_EVENT_MANAGER_BASENAME, $plugin_admin, 'add_plugin_action_links' );
			$this->loader->add_filter( 'plugin_row_meta', $plugin_admin, 'add_plugin_row_meta', 10, 2 );
			$this->loader->add_action( 'admin_notices', $plugin_admin, 'render_plugins_screen_premium_cta' );
			$this->loader->add_filter( 'post_row_actions', $plugin_admin, 'event_duplicate_row_action', 10, 2 );
			$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_event_duplicate' );

			// Initialize meta boxes only in admin.
			// Use add_action to delay initialization until after activation.
			$this->loader->add_action( 'admin_init', $this, 'init_meta_boxes', 1 );

			// Initialize review request notice (admin only).
			new TWEC_Review_Request();

			TWEC_Dashboard::init();
			TWEC_Onboarding::init();
		}

		/**
		 * Register all of the hooks related to the public-facing functionality.
		 */
		private function define_public_hooks() {
			$plugin_public = new TWEC_Public();

			// Store instance globally for use in templates.
			global $twec_public_instance;
			$twec_public_instance = $plugin_public;

			$this->loader->add_filter( 'query_vars', $plugin_public, 'register_query_vars' );
			$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 100 );
			$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
			$this->loader->add_filter( 'template_include', $plugin_public, 'event_template' );
			$this->loader->add_action( 'pre_get_posts', $plugin_public, 'modify_event_query' );
			$this->loader->add_action( 'wp_ajax_twec_get_calendar', $plugin_public, 'ajax_get_calendar' );
			$this->loader->add_action( 'wp_ajax_nopriv_twec_get_calendar', $plugin_public, 'ajax_get_calendar' );
			$this->loader->add_action( 'init', $plugin_public, 'handle_ical_export' );
			$this->loader->add_action( 'init', $plugin_public, 'handle_google_calendar_export' );
			$this->loader->add_action( 'init', $plugin_public, 'handle_ics_subscribe_feed', 5 );
			$this->loader->add_action( 'save_post_twec_event', $plugin_public, 'maybe_bump_public_ics_feed_cache', 10, 1 );

			// Initialize search and shortcodes.
			$search     = new TWEC_Search();
			$shortcodes = new TWEC_Shortcodes();

			TWEC_SEO::init();
			TWEC_REST::init();
			TWEC_View_Counter::init();
			TWEC_Premium_Pillars::init();
			if ( class_exists( 'TWEC_Payments_Stripe' ) ) {
				TWEC_Payments_Stripe::init();
			}
			if ( class_exists( 'TWEC_Payments_PayPal' ) ) {
				TWEC_Payments_PayPal::init();
			}
			if ( class_exists( 'TWEC_WooCommerce' ) ) {
				TWEC_WooCommerce::init();
			}
			if ( class_exists( 'TWEC_Woo_Series_Pass' ) ) {
				TWEC_Woo_Series_Pass::init();
			}
			TWEC_Collab_RD::init();
			TWEC_AI::init();
			TWEC_Abilities::init();
			TWEC_Reminders::init();
			TWEC_Privacy::init();
		}

		/**
		 * Run the loader to execute all of the hooks with WordPress.
		 */
		public function run() {
			$this->set_locale();
			$this->loader->run();
		}

		/**
		 * Initialize meta boxes.
		 * Called via admin_init hook to avoid issues during activation.
		 */
		public function init_meta_boxes() {
			TWEC_Meta_Boxes::init();
		}

		/**
		 * Define the locale for this plugin for internationalization.
		 */
		private function set_locale() {
			$plugin_i18n = new TWEC_i18n();
			$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
		}
	}
}
