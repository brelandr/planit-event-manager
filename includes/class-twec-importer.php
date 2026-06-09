<?php
/**
 * Import functionality for events.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-importer.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import functionality for events.
 *
 * Handles importing events from CSV files and from The Events Calendar plugin.
 */
class TWEC_Importer {

	/**
	 * Initialize importer.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_import_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_imports' ) );
	}

	/**
	 * Add import menu.
	 */
	public function add_import_menu() {
		// Only show import menu if premium is available.
		if ( ! TWEC_Premium::is_available( 'import' ) ) {
			add_submenu_page(
				'edit.php?post_type=twec_event',
				__( 'Import Events', 'planit-event-manager' ),
				'<span style="color: #f56e28;">★ ' . __( 'Import', 'planit-event-manager' ) . ' <span class="twec-premium-badge">PRO</span></span>',
				'manage_options',
				'twec-import',
				array( $this, 'display_import_page' )
			);
		} else {
			add_submenu_page(
				'edit.php?post_type=twec_event',
				__( 'Import Events', 'planit-event-manager' ),
				__( 'Import', 'planit-event-manager' ),
				'manage_options',
				'twec-import',
				array( $this, 'display_import_page' )
			);
		}
	}

	/**
	 * Display import page.
	 */
	public function display_import_page() {
		require_once PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-import.php';
	}

	/**
	 * Handle import requests.
	 */
	public function handle_imports() {
		// Check if this is an import request before processing.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Early return check, nonce verified below
		if ( ! isset( $_POST['twec_import_action'] ) ) {
			return;
		}

		twec_verify_post_nonce_or_die( '_wpnonce', 'twec_import' );

		// Check if premium is available.
		if ( ! TWEC_Premium::is_available( 'import' ) ) {
			add_settings_error( 'twec_import', 'twec_premium_required', esc_html__( 'Import functionality is a premium feature. Please upgrade to use this feature.', 'planit-event-manager' ), 'error' );
			return;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'planit-event-manager' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['twec_import_action'] ) );

		switch ( $action ) {
			case 'import_tec':
				$this->import_from_events_calendar();
				break;
			case 'import_csv':
				$this->import_from_csv();
				break;
		}
	}

	/**
	 * Import events from The Events Calendar plugin.
	 */
	private function import_from_events_calendar() {
		if ( ! post_type_exists( 'tribe_events' ) ) {
			wp_die( esc_html__( 'The Events Calendar plugin is not installed or activated.', 'planit-event-manager' ) );
		}

		$events = get_posts(
			array(
				'post_type'      => 'tribe_events',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$imported = 0;
		$skipped  = 0;

		// Optimize: Get all already imported event IDs in a single query to avoid N+1 queries.
		$already_imported = get_posts(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for checking imported events efficiently. Optimized with fields => 'ids' to avoid N+1 queries.
				'meta_key'       => '_twec_imported_from_tec',
			)
		);

		// Create a lookup array for O(1) checks instead of O(n) queries.
		$imported_tec_ids = array();
		if ( ! empty( $already_imported ) ) {
			foreach ( $already_imported as $imported_event_id ) {
				$tec_id = get_post_meta( $imported_event_id, '_twec_imported_from_tec', true );
				if ( $tec_id ) {
					$imported_tec_ids[ $tec_id ] = true;
				}
			}
		}

		foreach ( $events as $event ) {
			// Check if already imported using optimized lookup.
			if ( isset( $imported_tec_ids[ $event->ID ] ) ) {
				++$skipped;
				continue;
			}

			// Create new event.
			$new_event_id = wp_insert_post(
				array(
					'post_title'   => $event->post_title,
					'post_content' => $event->post_content,
					'post_excerpt' => $event->post_excerpt,
					'post_status'  => $event->post_status,
					'post_type'    => 'twec_event',
					'post_date'    => $event->post_date,
				)
			);

			if ( is_wp_error( $new_event_id ) ) {
				continue;
			}

			// Import featured image.
			$thumbnail_id = get_post_thumbnail_id( $event->ID );
			if ( $thumbnail_id ) {
				set_post_thumbnail( $new_event_id, $thumbnail_id );
			}

			// Import event meta.
			$start_date = get_post_meta( $event->ID, '_EventStartDate', true );
			$end_date   = get_post_meta( $event->ID, '_EventEndDate', true );
			$all_day    = get_post_meta( $event->ID, '_EventAllDay', true );

			if ( $start_date ) {
				$start_time = get_post_meta( $event->ID, '_EventStartDate', true );
				$end_time   = get_post_meta( $event->ID, '_EventEndDate', true );

				update_post_meta( $new_event_id, '_twec_event_start_date', $start_date );
				update_post_meta( $new_event_id, '_twec_event_end_date', $end_date );
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for extracting time from stored datetime
				update_post_meta( $new_event_id, '_twec_event_start_time', gmdate( 'H:i:s', strtotime( $start_date ) ) );
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for extracting time from stored datetime
				update_post_meta( $new_event_id, '_twec_event_end_time', gmdate( 'H:i:s', strtotime( $end_date ) ) );
				update_post_meta( $new_event_id, '_twec_event_all_day', $all_day ? '1' : '0' );
			}

			// Import venue.
			$venue_id = get_post_meta( $event->ID, '_EventVenueID', true );
			if ( $venue_id ) {
				$venue = get_post( $venue_id );
				if ( $venue && 'tribe_venue' === $venue->post_type ) {
					$new_venue_id = $this->import_venue_from_tec( $venue_id );
					if ( $new_venue_id ) {
						update_post_meta( $new_event_id, '_twec_event_venue', $new_venue_id );
					}
				}
			}

			// Import organizer.
			$organizer_ids = get_post_meta( $event->ID, '_EventOrganizerID', false );
			if ( ! empty( $organizer_ids ) && is_array( $organizer_ids ) ) {
				$organizer_id = $organizer_ids[0];
				$organizer    = get_post( $organizer_id );
				if ( $organizer && 'tribe_organizer' === $organizer->post_type ) {
					$new_organizer_id = $this->import_organizer_from_tec( $organizer_id );
					if ( $new_organizer_id ) {
						update_post_meta( $new_event_id, '_twec_event_organizer', $new_organizer_id );
					}
				}
			}

			// Import categories.
			$categories = wp_get_post_terms( $event->ID, 'tribe_events_cat' );
			if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
				$category_ids = array();
				foreach ( $categories as $category ) {
					$new_term = wp_insert_term(
						$category->name,
						'twec_event_category',
						array(
							'description' => $category->description,
							'slug'        => $category->slug,
						)
					);
					if ( ! is_wp_error( $new_term ) ) {
						$category_ids[] = $new_term['term_id'];
					}
				}
				if ( ! empty( $category_ids ) ) {
					wp_set_post_terms( $new_event_id, $category_ids, 'twec_event_category' );
				}
			}

			// Import tags.
			$tags = wp_get_post_terms( $event->ID, 'post_tag' );
			if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
				$tag_ids = array();
				foreach ( $tags as $tag ) {
					$new_term = wp_insert_term(
						$tag->name,
						'twec_event_tag',
						array(
							'description' => $tag->description,
							'slug'        => $tag->slug,
						)
					);
					if ( ! is_wp_error( $new_term ) ) {
						$tag_ids[] = $new_term['term_id'];
					}
				}
				if ( ! empty( $tag_ids ) ) {
					wp_set_post_terms( $new_event_id, $tag_ids, 'twec_event_tag' );
				}
			}

			// Mark as imported.
			update_post_meta( $new_event_id, '_twec_imported_from_tec', $event->ID );

			++$imported;
		}

		add_settings_error(
			'twec_import',
			'twec_import_success',
			sprintf(
				/* translators: %1$d: Number of imported events, %2$d: Number of skipped events */
				esc_html__( 'Imported %1$d events. %2$d events were skipped (already imported).', 'planit-event-manager' ),
				absint( $imported ),
				absint( $skipped )
			),
			'updated'
		);
	}

	/**
	 * Import venue from The Events Calendar.
	 *
	 * @param int $venue_id Venue ID from TEC.
	 * @return int|false New venue ID or false on failure.
	 */
	private function import_venue_from_tec( $venue_id ) {
		// Check if already imported using optimized query.
		$existing = get_posts(
			array(
				'post_type'      => 'twec_venue',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for checking imported venues efficiently. Optimized with fields => 'ids' and posts_per_page => 1.
				'meta_key'       => '_twec_imported_from_tec_venue',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for checking imported venues efficiently. Optimized with fields => 'ids' and posts_per_page => 1.
				'meta_value'     => $venue_id,
			)
		);

		if ( ! empty( $existing ) ) {
			return $existing[0];
		}

		$venue = get_post( $venue_id );
		if ( ! $venue ) {
			return false;
		}

		$new_venue_id = wp_insert_post(
			array(
				'post_title'   => $venue->post_title,
				'post_content' => $venue->post_content,
				'post_status'  => 'publish',
				'post_type'    => 'twec_venue',
			)
		);

		if ( is_wp_error( $new_venue_id ) ) {
			return false;
		}

			// Import venue meta.
		$address = get_post_meta( $venue_id, '_VenueAddress', true );
		$city    = get_post_meta( $venue_id, '_VenueCity', true );
		$state   = get_post_meta( $venue_id, '_VenueState', true );
		$zip     = get_post_meta( $venue_id, '_VenueZip', true );
		$country = get_post_meta( $venue_id, '_VenueCountry', true );
		$phone   = get_post_meta( $venue_id, '_VenuePhone', true );
		$website = get_post_meta( $venue_id, '_VenueURL', true );
		$lat     = get_post_meta( $venue_id, '_VenueLat', true );
		$lng     = get_post_meta( $venue_id, '_VenueLng', true );

		if ( $address ) {
			update_post_meta( $new_venue_id, '_twec_venue_address', $address );
		}
		if ( $city ) {
			update_post_meta( $new_venue_id, '_twec_venue_city', $city );
		}
		if ( $state ) {
			update_post_meta( $new_venue_id, '_twec_venue_state', $state );
		}
		if ( $zip ) {
			update_post_meta( $new_venue_id, '_twec_venue_zip', $zip );
		}
		if ( $country ) {
			update_post_meta( $new_venue_id, '_twec_venue_country', $country );
		}
		if ( $phone ) {
			update_post_meta( $new_venue_id, '_twec_venue_phone', $phone );
		}
		if ( $website ) {
			update_post_meta( $new_venue_id, '_twec_venue_website', $website );
		}
		if ( $lat ) {
			update_post_meta( $new_venue_id, '_twec_venue_latitude', $lat );
		}
		if ( $lng ) {
			update_post_meta( $new_venue_id, '_twec_venue_longitude', $lng );
		}

		update_post_meta( $new_venue_id, '_twec_imported_from_tec_venue', $venue_id );

		return $new_venue_id;
	}

	/**
	 * Import organizer from The Events Calendar.
	 *
	 * @param int $organizer_id Organizer ID from TEC.
	 * @return int|false New organizer ID or false on failure.
	 */
	private function import_organizer_from_tec( $organizer_id ) {
		// Check if already imported using optimized query.
		$existing = get_posts(
			array(
				'post_type'      => 'twec_organizer',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for checking imported organizers efficiently. Optimized with fields => 'ids' and posts_per_page => 1.
				'meta_key'       => '_twec_imported_from_tec_organizer',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for checking imported organizers efficiently. Optimized with fields => 'ids' and posts_per_page => 1.
				'meta_value'     => $organizer_id,
			)
		);

		if ( ! empty( $existing ) ) {
			return $existing[0];
		}

		$organizer = get_post( $organizer_id );
		if ( ! $organizer ) {
			return false;
		}

		$new_organizer_id = wp_insert_post(
			array(
				'post_title'   => $organizer->post_title,
				'post_content' => $organizer->post_content,
				'post_status'  => 'publish',
				'post_type'    => 'twec_organizer',
			)
		);

		if ( is_wp_error( $new_organizer_id ) ) {
			return false;
		}

			// Import organizer meta.
		$phone   = get_post_meta( $organizer_id, '_OrganizerPhone', true );
		$email   = get_post_meta( $organizer_id, '_OrganizerEmail', true );
		$website = get_post_meta( $organizer_id, '_OrganizerWebsite', true );

		if ( $phone ) {
			update_post_meta( $new_organizer_id, '_twec_organizer_phone', $phone );
		}
		if ( $email ) {
			update_post_meta( $new_organizer_id, '_twec_organizer_email', $email );
		}
		if ( $website ) {
			update_post_meta( $new_organizer_id, '_twec_organizer_website', $website );
		}

		update_post_meta( $new_organizer_id, '_twec_imported_from_tec_organizer', $organizer_id );

		return $new_organizer_id;
	}

	/**
	 * Import events from CSV file.
	 * Note: Nonce verification is performed in handle_imports() before this method is called.
	 */
	private function import_from_csv() {
		// Check file upload.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_imports()
		if ( ! isset( $_FILES['csv_file'] ) ) {
			add_settings_error(
				'twec_import',
				'twec_csv_upload_error',
				__( 'No file was uploaded.', 'planit-event-manager' ),
				'error'
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_imports(), $_FILES['error'] is an integer constant
		if ( ! isset( $_FILES['csv_file']['error'] ) || UPLOAD_ERR_OK !== (int) $_FILES['csv_file']['error'] ) {
			$error_message = esc_html__( 'Error uploading CSV file.', 'planit-event-manager' );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_imports(), $_FILES['error'] is an integer constant
			$file_error = isset( $_FILES['csv_file']['error'] ) ? (int) $_FILES['csv_file']['error'] : UPLOAD_ERR_NO_FILE;
			switch ( $file_error ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$error_message = esc_html__( 'The uploaded file exceeds the maximum file size.', 'planit-event-manager' );
					break;
				case UPLOAD_ERR_PARTIAL:
					$error_message = esc_html__( 'The file was only partially uploaded.', 'planit-event-manager' );
					break;
				case UPLOAD_ERR_NO_FILE:
					$error_message = esc_html__( 'No file was uploaded.', 'planit-event-manager' );
					break;
				case UPLOAD_ERR_NO_TMP_DIR:
					$error_message = esc_html__( 'Missing a temporary folder.', 'planit-event-manager' );
					break;
				case UPLOAD_ERR_CANT_WRITE:
					$error_message = esc_html__( 'Failed to write file to disk.', 'planit-event-manager' );
					break;
				case UPLOAD_ERR_EXTENSION:
					$error_message = esc_html__( 'File upload stopped by extension.', 'planit-event-manager' );
					break;
			}
			add_settings_error(
				'twec_import',
				'twec_csv_upload_error',
				$error_message,
				'error'
			);
			return;
		}

		// Validate file type.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_imports()
		$file_name = isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ) ) : '';
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $file_ext ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_type_error',
				esc_html__( 'Please upload a CSV file.', 'planit-event-manager' ),
				'error'
			);
			return;
		}

		// Get tmp_name - must use is_uploaded_file() for security, don't sanitize path.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_imports(), $_FILES['tmp_name'] is validated with is_uploaded_file()
		$file = isset( $_FILES['csv_file']['tmp_name'] ) ? $_FILES['csv_file']['tmp_name'] : '';

		// Validate tmp_name is actually an uploaded file (critical security check).
		if ( empty( $file ) || ! is_uploaded_file( $file ) ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_security_error',
				__( 'Invalid file upload.', 'planit-event-manager' ),
				'error'
			);
			return;
		}

		// Check if file exists and is readable.
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_read_error',
				esc_html__( 'Error reading CSV file. File may not exist or is not readable.', 'planit-event-manager' ),
				'error'
			);
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading uploaded CSV file, WP_Filesystem not applicable for file uploads
		$handle = fopen( $file, 'r' );

		if ( ! $handle ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_open_error',
				__( 'Error opening CSV file.', 'planit-event-manager' ),
				'error'
			);
			return;
		}

		// Read header row.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv -- Reading uploaded CSV file, WP_Filesystem not applicable
		$headers = fgetcsv( $handle );
		if ( ! $headers || empty( $headers ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded CSV file, WP_Filesystem not applicable
			fclose( $handle );
			add_settings_error(
				'twec_import',
				'twec_csv_format_error',
				__( 'Invalid CSV file format. Could not read header row.', 'planit-event-manager' ),
				'error'
			);
			return;
		}

		// Normalize headers (trim whitespace, lowercase).
		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		$imported   = 0;
		$errors     = 0;
		$row_number = 1; // Start at 1 since header is row 0.

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv -- Reading uploaded CSV file, WP_Filesystem not applicable
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Assignment in while condition is intentional for CSV reading.
		while ( false !== ( $data = fgetcsv( $handle ) ) ) {
			++$row_number;

			// Skip empty rows.
			if ( empty( array_filter( $data ) ) ) {
				continue;
			}

			// Handle rows with different column counts.
			if ( count( $data ) !== count( $headers ) ) {
				// Pad or trim data to match headers.
				if ( count( $data ) < count( $headers ) ) {
					$data = array_pad( $data, count( $headers ), '' );
				} else {
					$data = array_slice( $data, 0, count( $headers ) );
				}
			}

			$row = array_combine( $headers, $data );
			if ( false === $row ) {
				++$errors;
				continue;
			}

			// If array_combine fails, skip this row.
			if ( ! is_array( $row ) ) {
				++$errors;
				continue;
			}

			// Required fields.
			if ( empty( $row['title'] ) || empty( $row['start_date'] ) ) {
				++$errors;
				continue;
			}

			// Validate date format.
			$start_date_test = strtotime( $row['start_date'] );
			if ( false === $start_date_test ) {
				++$errors;
				continue;
			}

			// Create event.
			$event_data = array(
				'post_title'   => sanitize_text_field( $row['title'] ),
				'post_content' => isset( $row['description'] ) ? wp_kses_post( $row['description'] ) : '',
				'post_excerpt' => isset( $row['excerpt'] ) ? sanitize_text_field( $row['excerpt'] ) : '',
				'post_status'  => isset( $row['status'] ) ? sanitize_text_field( $row['status'] ) : 'publish',
				'post_type'    => 'twec_event',
			);

			$event_id = wp_insert_post( $event_data );

			if ( is_wp_error( $event_id ) ) {
				++$errors;
				continue;
			}

			// Set dates.
			$start_date = sanitize_text_field( $row['start_date'] );
			$start_time = isset( $row['start_time'] ) ? sanitize_text_field( $row['start_time'] ) : '00:00:00';
			$end_date   = isset( $row['end_date'] ) ? sanitize_text_field( $row['end_date'] ) : $start_date;
			$end_time   = isset( $row['end_time'] ) ? sanitize_text_field( $row['end_time'] ) : '23:59:59';
			$all_day    = isset( $row['all_day'] ) && ( '1' === $row['all_day'] || 'yes' === strtolower( $row['all_day'] ) );

			$start_datetime = $start_date . ' ' . $start_time;
			$end_datetime   = $end_date . ' ' . $end_time;

			update_post_meta( $event_id, '_twec_event_start_date', $start_datetime );
			update_post_meta( $event_id, '_twec_event_end_date', $end_datetime );
			update_post_meta( $event_id, '_twec_event_start_time', $start_time );
			update_post_meta( $event_id, '_twec_event_end_time', $end_time );
			update_post_meta( $event_id, '_twec_event_all_day', $all_day ? '1' : '0' );

			// Import venue if provided.
			if ( ! empty( $row['venue'] ) ) {
				$venue_id = $this->get_or_create_venue( sanitize_text_field( $row['venue'] ), $row );
				if ( $venue_id ) {
					update_post_meta( $event_id, '_twec_event_venue', $venue_id );
				}
			}

			// Import organizer if provided.
			if ( ! empty( $row['organizer'] ) ) {
				$organizer_id = $this->get_or_create_organizer( sanitize_text_field( $row['organizer'] ), $row );
				if ( $organizer_id ) {
					update_post_meta( $event_id, '_twec_event_organizer', $organizer_id );
				}
			}

			// Import categories.
			if ( ! empty( $row['categories'] ) ) {
				$categories   = array_map( 'trim', explode( ',', $row['categories'] ) );
				$category_ids = array();
				foreach ( $categories as $category_name ) {
					$term = wp_insert_term( $category_name, 'twec_event_category' );
					if ( ! is_wp_error( $term ) ) {
						$category_ids[] = is_array( $term ) ? $term['term_id'] : $term;
					} elseif ( isset( $term->error_data['term_exists'] ) ) {
						$category_ids[] = $term->error_data['term_exists'];
					}
				}
				if ( ! empty( $category_ids ) ) {
					wp_set_post_terms( $event_id, $category_ids, 'twec_event_category' );
				}
			}

			// Import tags.
			if ( ! empty( $row['tags'] ) ) {
				$tags    = array_map( 'trim', explode( ',', $row['tags'] ) );
				$tag_ids = array();
				foreach ( $tags as $tag_name ) {
					$term = wp_insert_term( $tag_name, 'twec_event_tag' );
					if ( ! is_wp_error( $term ) ) {
						$tag_ids[] = is_array( $term ) ? $term['term_id'] : $term;
					} elseif ( isset( $term->error_data['term_exists'] ) ) {
						$tag_ids[] = $term->error_data['term_exists'];
					}
				}
				if ( ! empty( $tag_ids ) ) {
					wp_set_post_terms( $event_id, $tag_ids, 'twec_event_tag' );
				}
			}

			++$imported;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded CSV file, WP_Filesystem not applicable
		fclose( $handle );

		// Build success/error message.
		if ( $imported > 0 ) {
			$message = sprintf(
				/* translators: %d: Number of imported events */
				_n(
					'Successfully imported %d event from CSV.',
					'Successfully imported %d events from CSV.',
					absint( $imported ),
					'planit-event-manager'
				),
				absint( $imported )
			);

			if ( $errors > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: Number of rows with errors */
					_n(
						'%d row had errors and was skipped.',
						'%d rows had errors and were skipped.',
						absint( $errors ),
						'planit-event-manager'
					),
					absint( $errors )
				);
			}

			add_settings_error(
				'twec_import',
				'twec_csv_import_success',
				$message,
				$errors > 0 ? 'error' : 'updated'
			);
		} else {
			add_settings_error(
				'twec_import',
				'twec_csv_import_no_events',
				esc_html__( 'No events were imported. Please check your CSV file format and ensure required fields (title, start_date) are present.', 'planit-event-manager' ),
				'error'
			);
		}
	}

	/**
	 * Get or create venue from CSV data.
	 *
	 * @param string $venue_name Venue name.
	 * @param array  $row        CSV row data.
	 * @return int|false Venue ID or false on failure.
	 */
	private function get_or_create_venue( $venue_name, $row ) {
		// Check if venue exists.
		$existing = get_posts(
			array(
				'post_type'      => 'twec_venue',
				'title'          => $venue_name,
				'posts_per_page' => 1,
			)
		);

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

		// Create new venue.
		$venue_id = wp_insert_post(
			array(
				'post_title'  => $venue_name,
				'post_type'   => 'twec_venue',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $venue_id ) ) {
			return false;
		}

		// Set venue meta from CSV.
		if ( isset( $row['venue_address'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_address', sanitize_text_field( $row['venue_address'] ) );
		}
		if ( isset( $row['venue_city'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_city', sanitize_text_field( $row['venue_city'] ) );
		}
		if ( isset( $row['venue_state'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_state', sanitize_text_field( $row['venue_state'] ) );
		}
		if ( isset( $row['venue_zip'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_zip', sanitize_text_field( $row['venue_zip'] ) );
		}
		if ( isset( $row['venue_country'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_country', sanitize_text_field( $row['venue_country'] ) );
		}
		if ( isset( $row['venue_phone'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_phone', sanitize_text_field( $row['venue_phone'] ) );
		}
		if ( isset( $row['venue_website'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_website', esc_url_raw( $row['venue_website'] ) );
		}
		if ( isset( $row['venue_latitude'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_latitude', sanitize_text_field( $row['venue_latitude'] ) );
		}
		if ( isset( $row['venue_longitude'] ) ) {
			update_post_meta( $venue_id, '_twec_venue_longitude', sanitize_text_field( $row['venue_longitude'] ) );
		}

		return $venue_id;
	}

	/**
	 * Get or create organizer from CSV data.
	 *
	 * @param string $organizer_name Organizer name.
	 * @param array  $row            CSV row data.
	 * @return int|false Organizer ID or false on failure.
	 */
	private function get_or_create_organizer( $organizer_name, $row ) {
		// Check if organizer exists.
		$existing = get_posts(
			array(
				'post_type'      => 'twec_organizer',
				'title'          => $organizer_name,
				'posts_per_page' => 1,
			)
		);

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

		// Create new organizer.
		$organizer_id = wp_insert_post(
			array(
				'post_title'  => $organizer_name,
				'post_type'   => 'twec_organizer',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $organizer_id ) ) {
			return false;
		}

		// Set organizer meta from CSV.
		if ( isset( $row['organizer_phone'] ) ) {
			update_post_meta( $organizer_id, '_twec_organizer_phone', sanitize_text_field( $row['organizer_phone'] ) );
		}
		if ( isset( $row['organizer_email'] ) ) {
			update_post_meta( $organizer_id, '_twec_organizer_email', sanitize_email( $row['organizer_email'] ) );
		}
		if ( isset( $row['organizer_website'] ) ) {
			update_post_meta( $organizer_id, '_twec_organizer_website', esc_url_raw( $row['organizer_website'] ) );
		}

		return $organizer_id;
	}
}

// TWEC_Importer is initialized by TWEC class.
