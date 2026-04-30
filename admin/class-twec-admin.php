<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    The_Event_Calendar
 * @subpackage admin
 * @since      1.0.0
 * @file       class-twec-admin.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Handles admin area functionality including settings, diagnostics, and imports.
 */
class TWEC_Admin {

	/**
	 * Enqueue styles for the admin area.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'twec-admin',
			PLANIT_EVENT_MANAGER_URL . 'admin/css/twec-admin.css',
			array(),
			PLANIT_EVENT_MANAGER_VERSION,
			'all'
		);
	}

	/**
	 * Enqueue scripts for the admin area.
	 *
	 * @param string $hook Current admin page hook (unused but required by WordPress hook).
	 */
	public function enqueue_scripts( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by WordPress hook signature.
		global $post_type;

		$editor_types = array( 'twec_event', 'twec_venue', 'twec_organizer' );
		$screen       = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ptype        = '';

		if ( $screen && ! empty( $screen->post_type ) ) {
			$ptype = (string) $screen->post_type;
		} elseif ( is_string( $post_type ) && '' !== $post_type ) {
			$ptype = $post_type;
		} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin script routing only (no privileged action).
			$ptype = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		} elseif ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin script routing only (no privileged action).
			$edit_id = absint( wp_unslash( $_GET['post'] ) );
			$edited  = $edit_id ? get_post_type( $edit_id ) : '';
			if ( is_string( $edited ) && '' !== $edited ) {
				$ptype = $edited;
			}
		}

		if ( $ptype && in_array( $ptype, $editor_types, true ) ) {
			wp_enqueue_script(
				'twec-admin',
				PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-admin.js',
				array( 'jquery' ),
				PLANIT_EVENT_MANAGER_VERSION,
				true
			);
		}

		// Enqueue admin script on settings page for delete confirmation.
		$screen = get_current_screen();
		if ( $screen && 'twec_event_page_twec-settings' === $screen->id ) {
			wp_enqueue_script(
				'twec-admin',
				PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-admin.js',
				array( 'jquery' ),
				PLANIT_EVENT_MANAGER_VERSION,
				true
			);
			wp_localize_script(
				'twec-admin',
				'twecAdminData',
				array(
					'deleteTestEventsConfirm' => esc_js( __( 'Are you sure you want to delete all test events? This action cannot be undone.', 'planit-event-manager' ) ),
				)
			);
		}
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=twec_event&page=twec-settings' ) ) . '">' . esc_html__( 'Settings', 'planit-event-manager' ) . '</a>';
		$docs_link     = '<a href="' . esc_url( admin_url( 'edit.php?post_type=twec_event&page=twec-documentation' ) ) . '">' . esc_html__( 'Documentation', 'planit-event-manager' ) . '</a>';
		$upgrade_link  = '<a href="' . esc_url( TWEC_Premium::UPGRADE_URL ) . '" target="_blank" rel="noopener" style="color: #f56e28; font-weight: 600;">' . esc_html__( 'Upgrade to Premium', 'planit-event-manager' ) . '</a>';

		array_unshift( $links, $settings_link, $docs_link );
		$links[] = $upgrade_link;

		return $links;
	}

	/**
	 * Add plugin row meta links.
	 *
	 * @param array  $links Existing links.
	 * @param string $file  Plugin file.
	 * @return array Modified links.
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( PLANIT_EVENT_MANAGER_BASENAME === $file ) {
			// Add View Details link in row meta - only show if plugin is in WordPress.org repository.
			// For now, we'll add a link to the plugin's readme/support page.
			$view_details_url  = admin_url( 'plugin-install.php?tab=plugin-information&plugin=planit-event-manager&TB_iframe=true&width=600&height=550' );
			$view_details_link = '<a href="' . esc_url( $view_details_url ) . '" class="thickbox open-plugin-details-modal" aria-label="' . esc_attr__( 'More information about PlanIt Event Manager', 'planit-event-manager' ) . '" data-title="' . esc_attr__( 'PlanIt Event Manager', 'planit-event-manager' ) . '">' . esc_html__( 'View Details', 'planit-event-manager' ) . '</a>';

			array_unshift( $links, $view_details_link );

			$docs_link  = '<a href="' . esc_url( admin_url( 'edit.php?post_type=twec_event&page=twec-documentation' ) ) . '">' . esc_html__( 'Documentation', 'planit-event-manager' ) . '</a>';
			$wporg_link = '<a href="' . esc_url( 'https://wordpress.org/plugins/planit-event-manager/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'WordPress.org plugin page', 'planit-event-manager' ) . '</a>';
			$links[]   = $docs_link;
			$links[]   = $wporg_link;
			if ( defined( 'PLANIT_PREMIUM_LIVE_DEMO_URL' ) && is_string( PLANIT_PREMIUM_LIVE_DEMO_URL ) && '' !== PLANIT_PREMIUM_LIVE_DEMO_URL ) {
				$links[] = '<a href="' . esc_url( PLANIT_PREMIUM_LIVE_DEMO_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Premium live demo (InstaWP)', 'planit-event-manager' ) . '</a>';
			}
		}

		return $links;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'Event Calendar Settings', 'planit-event-manager' ),
			__( 'Settings', 'planit-event-manager' ),
			'manage_options',
			'twec-settings',
			array( $this, 'display_settings_page' )
		);

		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'PlanIt Event Manager Documentation', 'planit-event-manager' ),
			__( 'Documentation', 'planit-event-manager' ),
			(string) apply_filters( 'twec_manage_documentation_cap', 'manage_options' ),
			'twec-documentation',
			array( $this, 'display_documentation_page' )
		);

		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'Event Calendar Diagnostics', 'planit-event-manager' ),
			__( 'Diagnostics', 'planit-event-manager' ),
			'manage_options',
			'twec-diagnostics',
			array( $this, 'display_diagnostics_page' )
		);

		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'PlanIt Payments', 'planit-event-manager' ),
			__( 'Payments', 'planit-event-manager' ),
			(string) apply_filters( 'twec_manage_payment_log_cap', 'manage_options' ),
			'twec-payments',
			array( $this, 'display_payment_log_page' )
		);

		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'PlanIt Emails', 'planit-event-manager' ),
			__( 'Emails', 'planit-event-manager' ),
			(string) apply_filters( 'twec_manage_emails_cap', 'manage_options' ),
			'twec-emails',
			array( $this, 'display_emails_page' )
		);

		if ( class_exists( 'WooCommerce' ) && class_exists( 'TWEC_WooCommerce' ) && TWEC_WooCommerce::is_feature_enabled() ) {
			add_submenu_page(
				'edit.php?post_type=twec_event',
				__( 'Woo ticket orders', 'planit-event-manager' ),
				__( 'Woo ticket orders', 'planit-event-manager' ),
				'manage_woocommerce',
				'twec-wc-ticket-orders',
				array( $this, 'display_wc_ticket_orders_page' )
			);
		}

		// Add upgrade menu item.
		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'Upgrade to Premium', 'planit-event-manager' ),
			'<span class="twec-premium-menu-item">★ ' . esc_html__( 'Upgrade to Premium', 'planit-event-manager' ) . '</span>',
			'manage_options',
			'twec-upgrade',
			array( $this, 'display_upgrade_page' )
		);
	}

	/**
	 * Display in-admin documentation (tabbed).
	 *
	 * @return void
	 */
	public function display_documentation_page() {
		if ( ! current_user_can( (string) apply_filters( 'twec_manage_documentation_cap', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'planit-event-manager' ), 403 );
		}
		require_once PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-documentation.php';
	}

	/**
	 * Display upgrade page.
	 */
	public function display_upgrade_page() {
		include PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-upgrade.php';
	}

	/**
	 * Payment history (Stripe / PayPal rows).
	 *
	 * @return void
	 */
	public function display_payment_log_page() {
		if ( ! current_user_can( (string) apply_filters( 'twec_manage_payment_log_cap', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'planit-event-manager' ), 403 );
		}
		require_once PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-payments.php';
	}

	/**
	 * Configure reminder + receipt email copy.
	 *
	 * @return void
	 */
	public function display_emails_page() {
		if ( ! current_user_can( (string) apply_filters( 'twec_manage_emails_cap', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'planit-event-manager' ), 403 );
		}
		require_once PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-emails.php';
	}

	/**
	 * WooCommerce orders filtered by linked event ticket product.
	 *
	 * @return void
	 */
	public function display_wc_ticket_orders_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'planit-event-manager' ), 403 );
		}
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'TWEC_WooCommerce' ) ) {
			wp_die( esc_html__( 'WooCommerce is not active.', 'planit-event-manager' ), 403 );
		}
		require_once PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-wc-ticket-orders.php';
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'twec_settings_group',
			'twec_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		/**
		 * Allow the Emails screen to submit with a custom capability via `twec_manage_emails_cap`
		 * without granting full `manage_options`.
		 *
		 * @param string $required Default capability required to save PlanIt settings.
		 */
		add_filter(
			'option_page_capability_twec_settings_group',
			function ( $required ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missed -- options.php verifies nonce before capability filter runs.
				if ( isset( $_POST['twec_settings']['_twec_emails_form'] ) ) {
					return apply_filters( 'twec_manage_emails_cap', is_string( $required ) ? $required : 'manage_options' );
				}
				return $required;
			},
			5,
			1
		);

		// Add flush rewrite rules button. Nonce verification first, then capability (authorization).
		if ( isset( $_POST['twec_flush_rewrite_rules'] ) ) {
			if ( ! check_admin_referer( 'twec_flush_rewrite_rules', '_wpnonce', false ) ) {
				wp_die( esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
			}
			flush_rewrite_rules();
			add_settings_error( 'twec_settings', 'twec_flush_success', esc_html__( 'Permalink structure flushed successfully. Event pages should now work correctly.', 'planit-event-manager' ), 'updated' );
		}

		// Handle CSV template download.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Download link verified via capability check and nonce in URL.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'twec_download_csv_template' === $action ) {
			// Verify nonce first (CSRF), then capability (authorization).
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified with wp_verify_nonce( wp_unslash( ... ), ... ).
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'twec_download_csv_template' ) ) {
				wp_die( esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
			}
			$this->download_csv_template();
		}

		// Handle test events creation. Nonce verification first, then capability.
		if ( isset( $_POST['twec_create_test_events'] ) ) {
			if ( ! check_admin_referer( 'twec_create_test_events', '_wpnonce', false ) ) {
				wp_die( esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
			}
			$this->create_test_events();
		}

		// Handle test events deletion. Nonce verification first, then capability.
		if ( isset( $_POST['twec_delete_test_events'] ) ) {
			if ( ! check_admin_referer( 'twec_delete_test_events', '_wpnonce', false ) ) {
				wp_die( esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
			}
			$this->delete_test_events();
		}
	}

	/**
	 * Create test events.
	 */
	private function create_test_events() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
		}

		// Check if test events already exist.
		// Optimized: Use meta_key/meta_value instead of meta_query for single key lookup (faster).
		$existing_test_events = get_posts(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => 1, // Only need to check if any exist, not get all.
				'fields'         => 'ids', // Only need IDs for existence check.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for checking test events efficiently. Optimized with fields => 'ids' and posts_per_page => 1.
				'meta_key'       => '_twec_is_test_event',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for checking test events efficiently. Optimized with fields => 'ids' and posts_per_page => 1.
				'meta_value'     => '1',
			)
		);

		if ( ! empty( $existing_test_events ) ) {
			add_settings_error( 'twec_settings', 'twec_test_events_exist', esc_html__( 'Test events already exist. Please delete them first before creating new ones.', 'planit-event-manager' ), 'error' );
			return;
		}

		$current_date = new DateTime();
		$test_events  = array(
			array(
				'title'       => __( 'Community Workshop', 'planit-event-manager' ),
				'description' => __( 'Join us for an interactive community workshop where we\'ll discuss local initiatives and community projects.', 'planit-event-manager' ),
				'excerpt'     => __( 'Interactive community workshop', 'planit-event-manager' ),
				'days_offset' => -5, // 5 days ago
				'time'        => '14:00:00',
				'end_time'    => '16:00:00',
			),
			array(
				'title'       => __( 'Tech Meetup', 'planit-event-manager' ),
				'description' => __( 'Monthly tech meetup featuring guest speakers, networking opportunities, and discussions about the latest technology trends.', 'planit-event-manager' ),
				'excerpt'     => __( 'Monthly tech meetup with guest speakers', 'planit-event-manager' ),
				'days_offset' => 3, // 3 days from now
				'time'        => '18:00:00',
				'end_time'    => '20:00:00',
			),
			array(
				'title'       => __( 'Art Gallery Opening', 'planit-event-manager' ),
				'description' => __( 'Opening night of our new art gallery featuring works from local artists. Refreshments will be served.', 'planit-event-manager' ),
				'excerpt'     => __( 'New art gallery opening with local artists', 'planit-event-manager' ),
				'days_offset' => 7, // 7 days from now
				'time'        => '19:00:00',
				'end_time'    => '21:00:00',
			),
			array(
				'title'       => __( 'Yoga Class', 'planit-event-manager' ),
				'description' => __( 'Beginner-friendly yoga class in the park. All skill levels welcome. Please bring your own mat.', 'planit-event-manager' ),
				'excerpt'     => __( 'Beginner-friendly yoga class in the park', 'planit-event-manager' ),
				'days_offset' => 10, // 10 days from now
				'time'        => '09:00:00',
				'end_time'    => '10:30:00',
			),
			array(
				'title'       => __( 'Music Festival', 'planit-event-manager' ),
				'description' => __( 'Annual music festival featuring local bands, food vendors, and family-friendly activities. All ages welcome!', 'planit-event-manager' ),
				'excerpt'     => __( 'Annual music festival with local bands', 'planit-event-manager' ),
				'days_offset' => 14, // 14 days from now
				'time'        => '12:00:00',
				'end_time'    => '22:00:00',
			),
		);

		$created_count = 0;
		foreach ( $test_events as $event_data ) {
			$event_date = clone $current_date;
			$event_date->modify( '+' . $event_data['days_offset'] . ' days' );

			$start_datetime = $event_date->format( 'Y-m-d' ) . ' ' . $event_data['time'];
			$end_datetime   = $event_date->format( 'Y-m-d' ) . ' ' . $event_data['end_time'];

			$post_id = wp_insert_post(
				array(
					'post_title'   => $event_data['title'],
					'post_content' => $event_data['description'],
					'post_excerpt' => $event_data['excerpt'],
					'post_status'  => 'publish',
					'post_type'    => 'twec_event',
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Mark as test event.
				update_post_meta( $post_id, '_twec_is_test_event', '1' );

				// Set event dates.
				update_post_meta( $post_id, '_twec_event_start_date', $start_datetime );
				update_post_meta( $post_id, '_twec_event_end_date', $end_datetime );
				update_post_meta( $post_id, '_twec_event_start_time', $event_data['time'] );
				update_post_meta( $post_id, '_twec_event_end_time', $event_data['end_time'] );
				update_post_meta( $post_id, '_twec_all_day', '0' );

				++$created_count;
			}
		}

		if ( $created_count > 0 ) {
			/* translators: %d: Number of test events created */
			add_settings_error( 'twec_settings', 'twec_test_events_created', sprintf( esc_html__( 'Successfully created %d test events!', 'planit-event-manager' ), absint( $created_count ) ), 'updated' );
		} else {
			add_settings_error( 'twec_settings', 'twec_test_events_error', esc_html__( 'Failed to create test events. Please try again.', 'planit-event-manager' ), 'error' );
		}
	}

	/**
	 * Delete test events.
	 */
	private function delete_test_events() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
		}

		// Optimized: Use meta_key/meta_value instead of meta_query for single key lookup (faster).
		$test_events = get_posts(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids', // Only need IDs for deletion.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for deleting test events efficiently. Optimized with fields => 'ids'.
				'meta_key'       => '_twec_is_test_event',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for deleting test events efficiently. Optimized with fields => 'ids'.
				'meta_value'     => '1',
			)
		);

		$deleted_count = 0;
		foreach ( $test_events as $event_id ) {
			if ( wp_delete_post( (int) $event_id, true ) ) {
				++$deleted_count;
			}
		}

		if ( $deleted_count > 0 ) {
			/* translators: %d: Number of test events deleted */
			add_settings_error( 'twec_settings', 'twec_test_events_deleted', sprintf( esc_html__( 'Successfully deleted %d test events.', 'planit-event-manager' ), absint( $deleted_count ) ), 'updated' );
		} else {
			add_settings_error( 'twec_settings', 'twec_test_events_not_found', esc_html__( 'No test events found to delete.', 'planit-event-manager' ), 'info' );
		}
	}

	/**
	 * Get count of test events.
	 */
	public function get_test_events_count() {
		// Optimized: Use meta_key/meta_value instead of meta_query, fields => 'ids' for count.
		$test_events = get_posts(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids', // Only need IDs for count, faster.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for counting test events efficiently. Optimized with fields => 'ids'.
				'meta_key'       => '_twec_is_test_event',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for counting test events efficiently. Optimized with fields => 'ids'.
				'meta_value'     => '1',
			)
		);

		return count( $test_events );
	}

	/**
	 * Download CSV template file.
	 */
	public function download_csv_template() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
		}

		$filename = 'twec-import-template.csv';
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		// Header row.
		fputcsv(
			$output,
			array(
				'title',
				'description',
				'excerpt',
				'start_date',
				'start_time',
				'end_date',
				'end_time',
				'all_day',
				'venue',
				'venue_address',
				'venue_city',
				'venue_state',
				'venue_zip',
				'venue_country',
				'organizer',
				'organizer_phone',
				'organizer_email',
				'categories',
				'tags',
				'status',
			)
		);

		// Sample row.
		fputcsv(
			$output,
			array(
				'Sample Event',
				'This is a sample event description',
				'Short excerpt',
				'2024-01-15',
				'10:00:00',
				'2024-01-15',
				'12:00:00',
				'no',
				'Sample Venue',
				'123 Main St',
				'City',
				'State',
				'12345',
				'Country',
				'Sample Organizer',
				'555-1234',
				'organizer@example.com',
				'Music,Concert',
				'free,outdoor',
				'publish',
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct file download, WP_Filesystem not applicable
		fclose( $output );
		exit;
	}

	/**
	 * Display settings page.
	 */
	public function display_settings_page() {
		// Make instance available to template.
		global $twec_admin_instance;
		$twec_admin_instance = $this;

		require_once PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-settings.php';
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$previous = (array) get_option( 'twec_settings', array() );

		$emails_cap     = (string) apply_filters( 'twec_manage_emails_cap', 'manage_options' );
		$is_emails_form = ! empty( $input['_twec_emails_form'] );
		$can_options    = current_user_can( 'manage_options' );
		$can_emails_cap = current_user_can( $emails_cap );

		if ( ! $can_options ) {
			if ( $is_emails_form && $can_emails_cap ) {
				return array_merge( $previous, $this->sanitize_email_only_settings_fields( $input ) );
			}
			return $previous;
		}

		// Do not call check_admin_referer() here: it can invoke wp_nonce_ays / die, and the option sanitize
		// callback may run in non-form contexts. CSRF for the options form is handled in wp-admin/options.php
		// when option_page and _wpnonce are present.

		$sanitized = array();

		// 3. Ensure all input variables are unslashed and sanitized before use.
		// Note: WordPress Settings API automatically unslashes, but we ensure proper sanitization.
		if ( isset( $input['hide_past_events'] ) ) {
			$sanitized['hide_past_events'] = sanitize_text_field( wp_unslash( $input['hide_past_events'] ) );
		}

		if ( isset( $input['events_per_page'] ) ) {
			$sanitized['events_per_page'] = absint( wp_unslash( $input['events_per_page'] ) );
		}

		if ( isset( $input['google_maps_api_key'] ) ) {
			$sanitized['google_maps_api_key'] = sanitize_text_field( wp_unslash( $input['google_maps_api_key'] ) );
		}

		if ( isset( $input['seo_json_ld'] ) ) {
			$j = sanitize_text_field( wp_unslash( $input['seo_json_ld'] ) );
			$sanitized['seo_json_ld'] = in_array( $j, array( 'yes', 'no' ), true ) ? $j : 'yes';
		} else {
			$sanitized['seo_json_ld'] = 'yes';
		}

		if ( isset( $input['seo_og'] ) ) {
			$o = sanitize_text_field( wp_unslash( $input['seo_og'] ) );
			$sanitized['seo_og'] = in_array( $o, array( 'yes', 'no' ), true ) ? $o : 'yes';
		} else {
			$sanitized['seo_og'] = 'yes';
		}

		if ( isset( $input['seo_json_ld_graph'] ) ) {
			$g = sanitize_text_field( wp_unslash( $input['seo_json_ld_graph'] ) );
			$sanitized['seo_json_ld_graph'] = in_array( $g, array( 'yes', 'no' ), true ) ? $g : 'no';
		}

		if ( isset( $input['hierarchical_event_urls'] ) ) {
			$h = sanitize_text_field( wp_unslash( $input['hierarchical_event_urls'] ) );
			$sanitized['hierarchical_event_urls'] = in_array( $h, array( 'yes', 'no' ), true ) ? $h : 'no';
		}

		if ( isset( $input['seo_breadcrumb_json_ld'] ) ) {
			$b = sanitize_text_field( wp_unslash( $input['seo_breadcrumb_json_ld'] ) );
			$sanitized['seo_breadcrumb_json_ld'] = in_array( $b, array( 'yes', 'no' ), true ) ? $b : 'yes';
		} else {
			$sanitized['seo_breadcrumb_json_ld'] = 'yes';
		}

		if ( isset( $input['calendar_interactivity'] ) ) {
			$c = sanitize_text_field( wp_unslash( $input['calendar_interactivity'] ) );
			$sanitized['calendar_interactivity'] = in_array( $c, array( 'yes', 'no' ), true ) ? $c : 'yes';
		} else {
			$sanitized['calendar_interactivity'] = 'yes';
		}

		if ( isset( $input['cookieless_view_counter'] ) ) {
			$v = sanitize_text_field( wp_unslash( $input['cookieless_view_counter'] ) );
			$sanitized['cookieless_view_counter'] = in_array( $v, array( 'yes', 'no' ), true ) ? $v : 'no';
		} else {
			$sanitized['cookieless_view_counter'] = 'no';
		}
		if ( isset( $input['payment_gateway'] ) ) {
			$g = sanitize_text_field( wp_unslash( $input['payment_gateway'] ) );
			$sanitized['payment_gateway'] = in_array( $g, array( 'none', 'stripe', 'paypal' ), true ) ? $g : 'none';
		}
		if ( isset( $input['payment_mode'] ) ) {
			$pm = sanitize_text_field( wp_unslash( $input['payment_mode'] ) );
			$sanitized['payment_mode'] = in_array( $pm, array( 'test', 'live' ), true ) ? $pm : 'test';
		}

		$stripe_str = function ( $k ) use ( $input, $previous ) {
			if ( ! isset( $input[ $k ] ) ) {
				return null;
			}
			$v = (string) wp_unslash( $input[ $k ] );
			if ( '' === $v && isset( $previous[ $k ] ) && is_string( $previous[ $k ] ) ) {
				return (string) $previous[ $k ];
			}
			return $v;
		};

		$p = $stripe_str( 'stripe_test_publishable_key' );
		if ( null !== $p ) {
			$sanitized['stripe_test_publishable_key'] = sanitize_text_field( $p );
		}
		$p = $stripe_str( 'stripe_test_secret_key' );
		if ( null !== $p ) {
			$sanitized['stripe_test_secret_key'] = sanitize_text_field( $p );
		}
		$p = $stripe_str( 'stripe_live_publishable_key' );
		if ( null !== $p ) {
			$sanitized['stripe_live_publishable_key'] = sanitize_text_field( $p );
		}
		$p = $stripe_str( 'stripe_live_secret_key' );
		if ( null !== $p ) {
			$sanitized['stripe_live_secret_key'] = sanitize_text_field( $p );
		}
		if ( isset( $input['stripe_webhook_secret'] ) ) {
			$w = (string) wp_unslash( $input['stripe_webhook_secret'] );
			if ( '' === $w && ! empty( $previous['stripe_webhook_secret'] ) ) {
				$sanitized['stripe_webhook_secret'] = (string) $previous['stripe_webhook_secret'];
			} elseif ( '' !== $w ) {
				$w2 = sanitize_text_field( $w );
				if ( 0 === strpos( $w2, 'whsec_' ) ) {
					$sanitized['stripe_webhook_secret'] = $w2;
				} elseif ( ! empty( $previous['stripe_webhook_secret'] ) && $w2 === (string) $previous['stripe_webhook_secret'] ) {
					$sanitized['stripe_webhook_secret'] = (string) $previous['stripe_webhook_secret'];
				}
			}
		}
		if ( isset( $input['stripe_feature_price_minor'] ) ) {
			$sanitized['stripe_feature_price_minor'] = max( 0, (int) wp_unslash( $input['stripe_feature_price_minor'] ) );
		}
		if ( isset( $input['stripe_currency'] ) ) {
			$c = strtolower( preg_replace( '/[^a-z]/', '', (string) wp_unslash( $input['stripe_currency'] ) ) );
			$sanitized['stripe_currency'] = 3 === strlen( $c ) ? $c : 'usd';
		}
		if ( isset( $input['stripe_product_name'] ) ) {
			$sanitized['stripe_product_name'] = sanitize_text_field( wp_unslash( $input['stripe_product_name'] ) );
		}
		if ( isset( $input['stripe_checkout_success_url'] ) ) {
			$u = trim( (string) wp_unslash( $input['stripe_checkout_success_url'] ) );
			$sanitized['stripe_checkout_success_url'] = ( '' === $u ) ? '' : esc_url_raw( $u );
		}
		if ( isset( $input['stripe_checkout_cancel_url'] ) ) {
			$u = trim( (string) wp_unslash( $input['stripe_checkout_cancel_url'] ) );
			$sanitized['stripe_checkout_cancel_url'] = ( '' === $u ) ? '' : esc_url_raw( $u );
		}
		$p = $stripe_str( 'paypal_test_client_id' );
		if ( null !== $p ) {
			$sanitized['paypal_test_client_id'] = sanitize_text_field( $p );
		}
		$p = $stripe_str( 'paypal_test_client_secret' );
		if ( null !== $p ) {
			$sanitized['paypal_test_client_secret'] = sanitize_text_field( $p );
		}
		$p = $stripe_str( 'paypal_live_client_id' );
		if ( null !== $p ) {
			$sanitized['paypal_live_client_id'] = sanitize_text_field( $p );
		}
		$p = $stripe_str( 'paypal_live_client_secret' );
		if ( null !== $p ) {
			$sanitized['paypal_live_client_secret'] = sanitize_text_field( $p );
		}
		if ( isset( $input['paypal_webhook_id'] ) ) {
			$sanitized['paypal_webhook_id'] = sanitize_text_field( wp_unslash( (string) $input['paypal_webhook_id'] ) );
		}
		if ( isset( $input['paypal_checkout_success_url'] ) ) {
			$u = trim( (string) wp_unslash( $input['paypal_checkout_success_url'] ) );
			$sanitized['paypal_checkout_success_url'] = ( '' === $u ) ? '' : esc_url_raw( $u );
		}
		if ( isset( $input['paypal_checkout_cancel_url'] ) ) {
			$u = trim( (string) wp_unslash( $input['paypal_checkout_cancel_url'] ) );
			$sanitized['paypal_checkout_cancel_url'] = ( '' === $u ) ? '' : esc_url_raw( $u );
		}
		$sanitized['woocommerce_tickets_enabled']       = ( ! empty( $input['woocommerce_tickets_enabled'] ) ) ? 'yes' : 'no';
		$sanitized['woocommerce_ticket_cta_list']       = ( ! empty( $input['woocommerce_ticket_cta_list'] ) ) ? 'yes' : 'no';
		$sanitized['woocommerce_ticket_cta_calendar']   = ( ! empty( $input['woocommerce_ticket_cta_calendar'] ) ) ? 'yes' : 'no';
		$sanitized['woocommerce_ticket_require_buyer_details'] = ( ! empty( $input['woocommerce_ticket_require_buyer_details'] ) ) ? 'yes' : 'no';
		$sanitized['woocommerce_ticket_show_view_cart'] = ( ! empty( $input['woocommerce_ticket_show_view_cart'] ) ) ? 'yes' : 'no';
		if ( isset( $input['woocommerce_ticket_btn_style'] ) ) {
			$st = strtolower( sanitize_key( (string) wp_unslash( $input['woocommerce_ticket_btn_style'] ) ) );
			$sanitized['woocommerce_ticket_btn_style'] = in_array( $st, array( 'theme', 'solid', 'outline', 'custom' ), true ) ? $st : 'solid';
		}
		if ( isset( $input['woocommerce_ticket_btn_primary_bg'] ) ) {
			$h = strtolower( trim( (string) wp_unslash( $input['woocommerce_ticket_btn_primary_bg'] ) ) );
			$v = sanitize_hex_color( $h );
			$sanitized['woocommerce_ticket_btn_primary_bg'] = ( is_string( $v ) && '' !== $v ) ? $v : '#2271b1';
		}
		if ( isset( $input['woocommerce_ticket_btn_primary_text'] ) ) {
			$h = strtolower( trim( (string) wp_unslash( $input['woocommerce_ticket_btn_primary_text'] ) ) );
			$v = sanitize_hex_color( $h );
			$sanitized['woocommerce_ticket_btn_primary_text'] = ( is_string( $v ) && '' !== $v ) ? $v : '#ffffff';
		}
		if ( isset( $input['woocommerce_ticket_btn_radius'] ) ) {
			$sanitized['woocommerce_ticket_btn_radius'] = max( 0, min( 32, (int) wp_unslash( $input['woocommerce_ticket_btn_radius'] ) ) );
		}
		if ( isset( $input['woocommerce_ticket_btn_secondary_mode'] ) ) {
			$m = sanitize_key( (string) wp_unslash( $input['woocommerce_ticket_btn_secondary_mode'] ) );
			$sanitized['woocommerce_ticket_btn_secondary_mode'] = in_array( $m, array( 'outline', 'ghost', 'muted' ), true ) ? $m : 'outline';
		}
		$sanitized['event_reminders_enabled']     = ( ! empty( $input['event_reminders_enabled'] ) ) ? 'yes' : 'no';
		if ( isset( $input['reminder_offset_hours'] ) ) {
			$ro = max( 1, min( 168, (int) wp_unslash( $input['reminder_offset_hours'] ) ) );
			$sanitized['reminder_offset_hours'] = $ro;
		}

		if ( $is_emails_form ) {
			$sanitized = array_merge( $sanitized, $this->sanitize_email_only_settings_fields( $input ) );
		}

		$merged   = array_merge( $previous, $sanitized );
		if ( isset( $sanitized['hierarchical_event_urls'] ) && isset( $previous['hierarchical_event_urls'] ) && $sanitized['hierarchical_event_urls'] !== $previous['hierarchical_event_urls'] ) {
			$merged['_twec_flush_rewrite_flag'] = 1;
		}
		if ( class_exists( 'TWEC_Reminders' ) && is_callable( array( 'TWEC_Reminders', 'sync_for_settings' ) ) ) {
			TWEC_Reminders::sync_for_settings( $merged );
		}
		return $merged;
	}

	/**
	 * Display diagnostics page.
	 */
	public function display_diagnostics_page() {
		require_once PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-diagnostics.php';
	}

	/**
	 * Add a Duplicate link to the event list table.
	 *
	 * @param string[] $actions Post row actions.
	 * @param WP_Post  $post    The post.
	 * @return string[]
	 */
	public function event_duplicate_row_action( $actions, $post ) {
		if ( 'twec_event' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'post_type'     => 'twec_event',
					'twec_duplicate' => (int) $post->ID,
				),
				admin_url( 'edit.php' )
			),
			'twec_duplicate_event_' . (int) $post->ID
		);
		$actions['twec_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'planit-event-manager' ) . '</a>';
		return $actions;
	}

	/**
	 * Duplicate a published or draft event into a new draft, copying meta. Adjusts start/end by 7 days when parsable.
	 *
	 * @return void
	 */
	public function handle_event_duplicate() {
		if ( ! isset( $_GET['twec_duplicate'] ) || ! is_admin() ) {
			return;
		}
		$id = absint( wp_unslash( $_GET['twec_duplicate'] ) );
		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid event.', 'planit-event-manager' ), esc_html__( 'Duplicate event', 'planit-event-manager' ), array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET action verified below with intent-specific nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified with wp_verify_nonce( wp_unslash( ... ), ... ).
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'twec_duplicate_event_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ), esc_html__( 'Duplicate event', 'planit-event-manager' ), array( 'response' => 403 ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ), esc_html__( 'Duplicate event', 'planit-event-manager' ), array( 'response' => 403 ) );
		}
		$original = get_post( $id );
		if ( ! $original || 'twec_event' !== $original->post_type ) {
			return;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => sprintf(
					/* translators: %s: original event title */
					__( 'Copy of %s', 'planit-event-manager' ),
					$original->post_title
				),
				'post_content' => $original->post_content,
				'post_excerpt' => $original->post_excerpt,
				'post_status'  => 'draft',
				'post_type'    => 'twec_event',
			),
			true
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return;
		}

		$all_meta = get_post_custom( $id );
		foreach ( $all_meta as $key => $values ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) {
				continue;
			}
			if ( is_array( $values ) ) {
				foreach ( $values as $val ) {
					add_post_meta( $new_id, $key, maybe_unserialize( $val ) );
				}
			}
		}

		$start = get_post_meta( $new_id, '_twec_event_start_date', true );
		$end   = get_post_meta( $new_id, '_twec_event_end_date', true );
		if ( $start ) {
			$ts = strtotime( $start );
			if ( $ts ) {
				$start_new = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days', $ts ) );
				update_post_meta( $new_id, '_twec_event_start_date', $start_new );
			}
		}
		if ( $end ) {
			$ts2 = strtotime( $end );
			if ( $ts2 ) {
				$end_new = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days', $ts2 ) );
				update_post_meta( $new_id, '_twec_event_end_date', $end_new );
			}
		}

		wp_delete_post_meta( $new_id, '_twec_is_test_event' );

		wp_safe_redirect( get_edit_post_link( (int) $new_id, 'raw' ) );
		exit;
	}

	/**
	 * Sanitize PlanIt Email settings page fields (stored in twec_settings).
	 *
	 * @param array<string,mixed> $input Raw settings input.
	 * @return array<string,mixed>
	 */
	private function sanitize_email_only_settings_fields( array $input ) {
		$out = array();
		if ( isset( $input['reminder_email_subject'] ) ) {
			$out['reminder_email_subject'] = sanitize_text_field( wp_unslash( (string) $input['reminder_email_subject'] ) );
		}
		if ( isset( $input['reminder_email_body_html'] ) ) {
			$out['reminder_email_body_html'] = wp_kses_post( wp_unslash( (string) $input['reminder_email_body_html'] ) );
		}
		$out['payment_receipt_enabled'] = ( ! empty( $input['payment_receipt_enabled'] ) && 'yes' === (string) $input['payment_receipt_enabled'] ) ? 'yes' : 'no';
		if ( isset( $input['payment_receipt_subject'] ) ) {
			$out['payment_receipt_subject'] = sanitize_text_field( wp_unslash( (string) $input['payment_receipt_subject'] ) );
		}
		if ( isset( $input['payment_receipt_body_html'] ) ) {
			$out['payment_receipt_body_html'] = wp_kses_post( wp_unslash( (string) $input['payment_receipt_body_html'] ) );
		}
		if ( isset( $input['payment_receipt_bcc_admin'] ) ) {
			$bcc = sanitize_email( wp_unslash( (string) $input['payment_receipt_bcc_admin'] ) );
			$out['payment_receipt_bcc_admin'] = ( '' !== $bcc && is_email( $bcc ) ) ? $bcc : '';
		}
		return $out;
	}
}
