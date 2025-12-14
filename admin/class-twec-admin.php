<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage admin
 */
class TWEC_Admin {

	/**
	 * Enqueue styles for the admin area.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'twec-admin',
			TWEC_PLUGIN_URL . 'admin/css/twec-admin.css',
			array(),
			TWEC_VERSION,
			'all'
		);
	}

	/**
	 * Enqueue scripts for the admin area.
	 */
	public function enqueue_scripts( $hook ) {
		global $post_type;
		
		if ( 'twec_event' === $post_type || 'twec_venue' === $post_type || 'twec_organizer' === $post_type ) {
			wp_enqueue_script(
				'twec-admin',
				TWEC_PLUGIN_URL . 'admin/js/twec-admin.js',
				array( 'jquery' ),
				TWEC_VERSION,
				true
			);
		}
	}

	/**
	 * Add plugin action links.
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=twec_event&page=twec-settings' ) ) . '">' . esc_html__( 'Settings', 'the-wordpress-event-calendar' ) . '</a>';
		$upgrade_link = '<a href="' . esc_url( TWEC_Premium::UPGRADE_URL ) . '" target="_blank" rel="noopener" style="color: #f56e28; font-weight: 600;">' . esc_html__( 'Upgrade to Premium', 'the-wordpress-event-calendar' ) . '</a>';
		
		array_unshift( $links, $settings_link );
		$links[] = $upgrade_link;
		
		return $links;
	}
	
	/**
	 * Add plugin row meta links.
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( $file === TWEC_PLUGIN_BASENAME ) {
			// Add View Details link in row meta - only show if plugin is in WordPress.org repository
			// For now, we'll add a link to the plugin's readme/support page
			$view_details_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=the-wordpress-event-calendar&TB_iframe=true&width=600&height=550' );
			$view_details_link = '<a href="' . esc_url( $view_details_url ) . '" class="thickbox open-plugin-details-modal" aria-label="' . esc_attr__( 'More information about The WordPress Event Calendar', 'the-wordpress-event-calendar' ) . '" data-title="' . esc_attr__( 'The WordPress Event Calendar', 'the-wordpress-event-calendar' ) . '">' . esc_html__( 'View Details', 'the-wordpress-event-calendar' ) . '</a>';
			
			array_unshift( $links, $view_details_link );
			
			// Add documentation link (will work once plugin is in repository)
			$docs_link = '<a href="' . esc_url( 'https://wordpress.org/plugins/the-wordpress-event-calendar/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Documentation', 'the-wordpress-event-calendar' ) . '</a>';
			$links[] = $docs_link;
		}
		
		return $links;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'Event Calendar Settings', 'the-wordpress-event-calendar' ),
			__( 'Settings', 'the-wordpress-event-calendar' ),
			'manage_options',
			'twec-settings',
			array( $this, 'display_settings_page' )
		);

		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'Event Calendar Diagnostics', 'the-wordpress-event-calendar' ),
			__( 'Diagnostics', 'the-wordpress-event-calendar' ),
			'manage_options',
			'twec-diagnostics',
			array( $this, 'display_diagnostics_page' )
		);
		
		// Add upgrade menu item
		add_submenu_page(
			'edit.php?post_type=twec_event',
			__( 'Upgrade to Premium', 'the-wordpress-event-calendar' ),
			'<span class="twec-premium-menu-item">★ ' . esc_html__( 'Upgrade to Premium', 'the-wordpress-event-calendar' ) . '</span>',
			'manage_options',
			'twec-upgrade',
			array( $this, 'display_upgrade_page' )
		);
	}
	
	/**
	 * Display upgrade page.
	 */
	public function display_upgrade_page() {
		include TWEC_PLUGIN_DIR . 'admin/partials/twec-admin-upgrade.php';
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'twec_settings_group', 'twec_settings' );
		
		// Add flush rewrite rules button
		if ( isset( $_POST['twec_flush_rewrite_rules'] ) && check_admin_referer( 'twec_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			add_settings_error( 'twec_settings', 'twec_flush_success', __( 'Permalink structure flushed successfully. Event pages should now work correctly.', 'the-wordpress-event-calendar' ), 'updated' );
		}
		
		// Handle CSV template download
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'twec_download_csv_template' ) {
			$this->download_csv_template();
		}
		
		// Handle test events creation
		if ( isset( $_POST['twec_create_test_events'] ) && check_admin_referer( 'twec_test_events' ) ) {
			$this->create_test_events();
		}
		
		// Handle test events deletion
		if ( isset( $_POST['twec_delete_test_events'] ) && check_admin_referer( 'twec_test_events' ) ) {
			$this->delete_test_events();
		}
	}
	
	/**
	 * Create test events.
	 */
	private function create_test_events() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'the-wordpress-event-calendar' ) );
		}
		
		// Check if test events already exist
		$existing_test_events = get_posts( array(
			'post_type' => 'twec_event',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_twec_is_test_event',
					'value' => '1',
					'compare' => '=',
				),
			),
		) );
		
		if ( ! empty( $existing_test_events ) ) {
			add_settings_error( 'twec_settings', 'twec_test_events_exist', __( 'Test events already exist. Please delete them first before creating new ones.', 'the-wordpress-event-calendar' ), 'error' );
			return;
		}
		
		$current_date = new DateTime();
		$test_events = array(
			array(
				'title' => __( 'Community Workshop', 'the-wordpress-event-calendar' ),
				'description' => __( 'Join us for an interactive community workshop where we\'ll discuss local initiatives and community projects.', 'the-wordpress-event-calendar' ),
				'excerpt' => __( 'Interactive community workshop', 'the-wordpress-event-calendar' ),
				'days_offset' => -5, // 5 days ago
				'time' => '14:00:00',
				'end_time' => '16:00:00',
			),
			array(
				'title' => __( 'Tech Meetup', 'the-wordpress-event-calendar' ),
				'description' => __( 'Monthly tech meetup featuring guest speakers, networking opportunities, and discussions about the latest technology trends.', 'the-wordpress-event-calendar' ),
				'excerpt' => __( 'Monthly tech meetup with guest speakers', 'the-wordpress-event-calendar' ),
				'days_offset' => 3, // 3 days from now
				'time' => '18:00:00',
				'end_time' => '20:00:00',
			),
			array(
				'title' => __( 'Art Gallery Opening', 'the-wordpress-event-calendar' ),
				'description' => __( 'Opening night of our new art gallery featuring works from local artists. Refreshments will be served.', 'the-wordpress-event-calendar' ),
				'excerpt' => __( 'New art gallery opening with local artists', 'the-wordpress-event-calendar' ),
				'days_offset' => 7, // 7 days from now
				'time' => '19:00:00',
				'end_time' => '21:00:00',
			),
			array(
				'title' => __( 'Yoga Class', 'the-wordpress-event-calendar' ),
				'description' => __( 'Beginner-friendly yoga class in the park. All skill levels welcome. Please bring your own mat.', 'the-wordpress-event-calendar' ),
				'excerpt' => __( 'Beginner-friendly yoga class in the park', 'the-wordpress-event-calendar' ),
				'days_offset' => 10, // 10 days from now
				'time' => '09:00:00',
				'end_time' => '10:30:00',
			),
			array(
				'title' => __( 'Music Festival', 'the-wordpress-event-calendar' ),
				'description' => __( 'Annual music festival featuring local bands, food vendors, and family-friendly activities. All ages welcome!', 'the-wordpress-event-calendar' ),
				'excerpt' => __( 'Annual music festival with local bands', 'the-wordpress-event-calendar' ),
				'days_offset' => 14, // 14 days from now
				'time' => '12:00:00',
				'end_time' => '22:00:00',
			),
		);
		
		$created_count = 0;
		foreach ( $test_events as $event_data ) {
			$event_date = clone $current_date;
			$event_date->modify( '+' . $event_data['days_offset'] . ' days' );
			
			$start_datetime = $event_date->format( 'Y-m-d' ) . ' ' . $event_data['time'];
			$end_datetime = $event_date->format( 'Y-m-d' ) . ' ' . $event_data['end_time'];
			
			$post_id = wp_insert_post( array(
				'post_title' => $event_data['title'],
				'post_content' => $event_data['description'],
				'post_excerpt' => $event_data['excerpt'],
				'post_status' => 'publish',
				'post_type' => 'twec_event',
			) );
			
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Mark as test event
				update_post_meta( $post_id, '_twec_is_test_event', '1' );
				
				// Set event dates
				update_post_meta( $post_id, '_twec_event_start_date', $start_datetime );
				update_post_meta( $post_id, '_twec_event_end_date', $end_datetime );
				update_post_meta( $post_id, '_twec_event_start_time', $event_data['time'] );
				update_post_meta( $post_id, '_twec_event_end_time', $event_data['end_time'] );
				update_post_meta( $post_id, '_twec_all_day', '0' );
				
				$created_count++;
			}
		}
		
		if ( $created_count > 0 ) {
			/* translators: %d: Number of test events created */
			add_settings_error( 'twec_settings', 'twec_test_events_created', sprintf( __( 'Successfully created %d test events!', 'the-wordpress-event-calendar' ), absint( $created_count ) ), 'updated' );
		} else {
			add_settings_error( 'twec_settings', 'twec_test_events_error', __( 'Failed to create test events. Please try again.', 'the-wordpress-event-calendar' ), 'error' );
		}
	}
	
	/**
	 * Delete test events.
	 */
	private function delete_test_events() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'the-wordpress-event-calendar' ) );
		}
		
		$test_events = get_posts( array(
			'post_type' => 'twec_event',
			'posts_per_page' => -1,
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key' => '_twec_is_test_event',
					'value' => '1',
					'compare' => '=',
				),
			),
		) );
		
		$deleted_count = 0;
		foreach ( $test_events as $event ) {
			if ( wp_delete_post( $event->ID, true ) ) {
				$deleted_count++;
			}
		}
		
		if ( $deleted_count > 0 ) {
			/* translators: %d: Number of test events deleted */
			add_settings_error( 'twec_settings', 'twec_test_events_deleted', sprintf( __( 'Successfully deleted %d test events.', 'the-wordpress-event-calendar' ), absint( $deleted_count ) ), 'updated' );
		} else {
			add_settings_error( 'twec_settings', 'twec_test_events_not_found', __( 'No test events found to delete.', 'the-wordpress-event-calendar' ), 'info' );
		}
	}
	
	/**
	 * Get count of test events.
	 */
	public function get_test_events_count() {
		$test_events = get_posts( array(
			'post_type' => 'twec_event',
			'posts_per_page' => -1,
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key' => '_twec_is_test_event',
					'value' => '1',
					'compare' => '=',
				),
			),
		) );
		
		return count( $test_events );
	}
	
	/**
	 * Download CSV template file.
	 */
	public function download_csv_template() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'the-wordpress-event-calendar' ) );
		}
		
		$filename = 'twec-import-template.csv';
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Header row
		fputcsv( $output, array(
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
			'status'
		) );
		
		// Sample row
		fputcsv( $output, array(
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
			'publish'
		) );
		
		fclose( $output );
		exit;
	}

	/**
	 * Display settings page.
	 */
	public function display_settings_page() {
		// Make instance available to template
		global $twec_admin_instance;
		$twec_admin_instance = $this;
		
		require_once TWEC_PLUGIN_DIR . 'admin/partials/twec-admin-settings.php';
	}

	/**
	 * Display diagnostics page.
	 */
	public function display_diagnostics_page() {
		require_once TWEC_PLUGIN_DIR . 'admin/partials/twec-admin-diagnostics.php';
	}
}

