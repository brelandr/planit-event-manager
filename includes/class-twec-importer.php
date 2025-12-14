<?php
/**
 * Import functionality for events.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
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
		// Only show import menu if premium is available
		if ( ! TWEC_Premium::is_available( 'import' ) ) {
			add_submenu_page(
				'edit.php?post_type=twec_event',
				__( 'Import Events', 'the-wordpress-event-calendar' ),
				'<span style="color: #f56e28;">★ ' . __( 'Import', 'the-wordpress-event-calendar' ) . ' <span class="twec-premium-badge">PRO</span></span>',
				'manage_options',
				'twec-import',
				array( $this, 'display_import_page' )
			);
		} else {
			add_submenu_page(
				'edit.php?post_type=twec_event',
				__( 'Import Events', 'the-wordpress-event-calendar' ),
				__( 'Import', 'the-wordpress-event-calendar' ),
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
		require_once TWEC_PLUGIN_DIR . 'admin/partials/twec-admin-import.php';
	}

	/**
	 * Handle import requests.
	 */
	public function handle_imports() {
		if ( ! isset( $_POST['twec_import_action'] ) ) {
			return;
		}

		// Check if premium is available
		if ( ! TWEC_Premium::is_available( 'import' ) ) {
			add_settings_error( 'twec_import', 'twec_premium_required', __( 'Import functionality is a premium feature. Please upgrade to use this feature.', 'the-wordpress-event-calendar' ), 'error' );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'the-wordpress-event-calendar' ) );
		}

		check_admin_referer( 'twec_import' );

		$action = sanitize_text_field( $_POST['twec_import_action'] );

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
			wp_die( __( 'The Events Calendar plugin is not installed or activated.', 'the-wordpress-event-calendar' ) );
		}

		$events = get_posts( array(
			'post_type' => 'tribe_events',
			'posts_per_page' => -1,
			'post_status' => 'publish',
		) );

		$imported = 0;
		$skipped = 0;

		foreach ( $events as $event ) {
			// Check if already imported
			$existing = get_posts( array(
				'post_type' => 'twec_event',
				'meta_query' => array(
					array(
						'key' => '_twec_imported_from_tec',
						'value' => $event->ID,
					),
				),
				'posts_per_page' => 1,
			) );

			if ( ! empty( $existing ) ) {
				$skipped++;
				continue;
			}

			// Create new event
			$new_event_id = wp_insert_post( array(
				'post_title' => $event->post_title,
				'post_content' => $event->post_content,
				'post_excerpt' => $event->post_excerpt,
				'post_status' => $event->post_status,
				'post_type' => 'twec_event',
				'post_date' => $event->post_date,
			) );

			if ( is_wp_error( $new_event_id ) ) {
				continue;
			}

			// Import featured image
			$thumbnail_id = get_post_thumbnail_id( $event->ID );
			if ( $thumbnail_id ) {
				set_post_thumbnail( $new_event_id, $thumbnail_id );
			}

			// Import event meta
			$start_date = get_post_meta( $event->ID, '_EventStartDate', true );
			$end_date = get_post_meta( $event->ID, '_EventEndDate', true );
			$all_day = get_post_meta( $event->ID, '_EventAllDay', true );

			if ( $start_date ) {
				$start_time = get_post_meta( $event->ID, '_EventStartDate', true );
				$end_time = get_post_meta( $event->ID, '_EventEndDate', true );
				
				update_post_meta( $new_event_id, '_twec_event_start_date', $start_date );
				update_post_meta( $new_event_id, '_twec_event_end_date', $end_date );
				update_post_meta( $new_event_id, '_twec_event_start_time', date( 'H:i:s', strtotime( $start_date ) ) );
				update_post_meta( $new_event_id, '_twec_event_end_time', date( 'H:i:s', strtotime( $end_date ) ) );
				update_post_meta( $new_event_id, '_twec_event_all_day', $all_day ? '1' : '0' );
			}

			// Import venue
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

			// Import organizer
			$organizer_ids = get_post_meta( $event->ID, '_EventOrganizerID', false );
			if ( ! empty( $organizer_ids ) && is_array( $organizer_ids ) ) {
				$organizer_id = $organizer_ids[0];
				$organizer = get_post( $organizer_id );
				if ( $organizer && 'tribe_organizer' === $organizer->post_type ) {
					$new_organizer_id = $this->import_organizer_from_tec( $organizer_id );
					if ( $new_organizer_id ) {
						update_post_meta( $new_event_id, '_twec_event_organizer', $new_organizer_id );
					}
				}
			}

			// Import categories
			$categories = wp_get_post_terms( $event->ID, 'tribe_events_cat' );
			if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
				$category_ids = array();
				foreach ( $categories as $category ) {
					$new_term = wp_insert_term( $category->name, 'twec_event_category', array(
						'description' => $category->description,
						'slug' => $category->slug,
					) );
					if ( ! is_wp_error( $new_term ) ) {
						$category_ids[] = $new_term['term_id'];
					}
				}
				if ( ! empty( $category_ids ) ) {
					wp_set_post_terms( $new_event_id, $category_ids, 'twec_event_category' );
				}
			}

			// Import tags
			$tags = wp_get_post_terms( $event->ID, 'post_tag' );
			if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
				$tag_ids = array();
				foreach ( $tags as $tag ) {
					$new_term = wp_insert_term( $tag->name, 'twec_event_tag', array(
						'description' => $tag->description,
						'slug' => $tag->slug,
					) );
					if ( ! is_wp_error( $new_term ) ) {
						$tag_ids[] = $new_term['term_id'];
					}
				}
				if ( ! empty( $tag_ids ) ) {
					wp_set_post_terms( $new_event_id, $tag_ids, 'twec_event_tag' );
				}
			}

			// Mark as imported
			update_post_meta( $new_event_id, '_twec_imported_from_tec', $event->ID );

			$imported++;
		}

		/* translators: %1$d: Number of imported events, %2$d: Number of skipped events */
		add_settings_error(
			'twec_import',
			'twec_import_success',
			sprintf( __( 'Imported %1$d events. %2$d events were skipped (already imported).', 'the-wordpress-event-calendar' ), absint( $imported ), absint( $skipped ) ),
			'updated'
		);
	}

	/**
	 * Import venue from The Events Calendar.
	 */
	private function import_venue_from_tec( $venue_id ) {
		// Check if already imported
		$existing = get_posts( array(
			'post_type' => 'twec_venue',
			'meta_query' => array(
				array(
					'key' => '_twec_imported_from_tec_venue',
					'value' => $venue_id,
				),
			),
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

		$venue = get_post( $venue_id );
		if ( ! $venue ) {
			return false;
		}

		$new_venue_id = wp_insert_post( array(
			'post_title' => $venue->post_title,
			'post_content' => $venue->post_content,
			'post_status' => 'publish',
			'post_type' => 'twec_venue',
		) );

		if ( is_wp_error( $new_venue_id ) ) {
			return false;
		}

		// Import venue meta
		$address = get_post_meta( $venue_id, '_VenueAddress', true );
		$city = get_post_meta( $venue_id, '_VenueCity', true );
		$state = get_post_meta( $venue_id, '_VenueState', true );
		$zip = get_post_meta( $venue_id, '_VenueZip', true );
		$country = get_post_meta( $venue_id, '_VenueCountry', true );
		$phone = get_post_meta( $venue_id, '_VenuePhone', true );
		$website = get_post_meta( $venue_id, '_VenueURL', true );
		$lat = get_post_meta( $venue_id, '_VenueLat', true );
		$lng = get_post_meta( $venue_id, '_VenueLng', true );

		if ( $address ) update_post_meta( $new_venue_id, '_twec_venue_address', $address );
		if ( $city ) update_post_meta( $new_venue_id, '_twec_venue_city', $city );
		if ( $state ) update_post_meta( $new_venue_id, '_twec_venue_state', $state );
		if ( $zip ) update_post_meta( $new_venue_id, '_twec_venue_zip', $zip );
		if ( $country ) update_post_meta( $new_venue_id, '_twec_venue_country', $country );
		if ( $phone ) update_post_meta( $new_venue_id, '_twec_venue_phone', $phone );
		if ( $website ) update_post_meta( $new_venue_id, '_twec_venue_website', $website );
		if ( $lat ) update_post_meta( $new_venue_id, '_twec_venue_latitude', $lat );
		if ( $lng ) update_post_meta( $new_venue_id, '_twec_venue_longitude', $lng );

		update_post_meta( $new_venue_id, '_twec_imported_from_tec_venue', $venue_id );

		return $new_venue_id;
	}

	/**
	 * Import organizer from The Events Calendar.
	 */
	private function import_organizer_from_tec( $organizer_id ) {
		// Check if already imported
		$existing = get_posts( array(
			'post_type' => 'twec_organizer',
			'meta_query' => array(
				array(
					'key' => '_twec_imported_from_tec_organizer',
					'value' => $organizer_id,
				),
			),
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

		$organizer = get_post( $organizer_id );
		if ( ! $organizer ) {
			return false;
		}

		$new_organizer_id = wp_insert_post( array(
			'post_title' => $organizer->post_title,
			'post_content' => $organizer->post_content,
			'post_status' => 'publish',
			'post_type' => 'twec_organizer',
		) );

		if ( is_wp_error( $new_organizer_id ) ) {
			return false;
		}

		// Import organizer meta
		$phone = get_post_meta( $organizer_id, '_OrganizerPhone', true );
		$email = get_post_meta( $organizer_id, '_OrganizerEmail', true );
		$website = get_post_meta( $organizer_id, '_OrganizerWebsite', true );

		if ( $phone ) update_post_meta( $new_organizer_id, '_twec_organizer_phone', $phone );
		if ( $email ) update_post_meta( $new_organizer_id, '_twec_organizer_email', $email );
		if ( $website ) update_post_meta( $new_organizer_id, '_twec_organizer_website', $website );

		update_post_meta( $new_organizer_id, '_twec_imported_from_tec_organizer', $organizer_id );

		return $new_organizer_id;
	}

	/**
	 * Import events from CSV file.
	 */
	private function import_from_csv() {
		// Check file upload
		if ( ! isset( $_FILES['csv_file'] ) ) {
			add_settings_error(
				'twec_import',
				'twec_csv_upload_error',
				__( 'No file was uploaded.', 'the-wordpress-event-calendar' ),
				'error'
			);
			return;
		}

		if ( $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			$error_message = __( 'Error uploading CSV file.', 'the-wordpress-event-calendar' );
			switch ( $_FILES['csv_file']['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$error_message = __( 'The uploaded file exceeds the maximum file size.', 'the-wordpress-event-calendar' );
					break;
				case UPLOAD_ERR_PARTIAL:
					$error_message = __( 'The file was only partially uploaded.', 'the-wordpress-event-calendar' );
					break;
				case UPLOAD_ERR_NO_FILE:
					$error_message = __( 'No file was uploaded.', 'the-wordpress-event-calendar' );
					break;
				case UPLOAD_ERR_NO_TMP_DIR:
					$error_message = __( 'Missing a temporary folder.', 'the-wordpress-event-calendar' );
					break;
				case UPLOAD_ERR_CANT_WRITE:
					$error_message = __( 'Failed to write file to disk.', 'the-wordpress-event-calendar' );
					break;
				case UPLOAD_ERR_EXTENSION:
					$error_message = __( 'File upload stopped by extension.', 'the-wordpress-event-calendar' );
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

		// Validate file type
		$file_name = isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( $_FILES['csv_file']['name'] ) : '';
		$file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( $file_ext !== 'csv' ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_type_error',
				__( 'Please upload a CSV file.', 'the-wordpress-event-calendar' ),
				'error'
			);
			return;
		}

		// Get tmp_name - must use is_uploaded_file() for security, don't sanitize path
		$file = isset( $_FILES['csv_file']['tmp_name'] ) ? $_FILES['csv_file']['tmp_name'] : '';
		
		// Validate tmp_name is actually an uploaded file (critical security check)
		if ( empty( $file ) || ! is_uploaded_file( $file ) ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_security_error',
				__( 'Invalid file upload.', 'the-wordpress-event-calendar' ),
				'error'
			);
			return;
		}
		
		// Check if file exists and is readable
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_read_error',
				__( 'Error reading CSV file. File may not exist or is not readable.', 'the-wordpress-event-calendar' ),
				'error'
			);
			return;
		}

		$handle = fopen( $file, 'r' );

		if ( ! $handle ) {
			add_settings_error(
				'twec_import',
				'twec_csv_file_open_error',
				__( 'Error opening CSV file.', 'the-wordpress-event-calendar' ),
				'error'
			);
			return;
		}

		// Read header row
		$headers = fgetcsv( $handle );
		if ( ! $headers || empty( $headers ) ) {
			fclose( $handle );
			add_settings_error(
				'twec_import',
				'twec_csv_format_error',
				__( 'Invalid CSV file format. Could not read header row.', 'the-wordpress-event-calendar' ),
				'error'
			);
			return;
		}

		// Normalize headers (trim whitespace, lowercase)
		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		$imported = 0;
		$errors = 0;
		$row_number = 1; // Start at 1 since header is row 0

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;

			// Skip empty rows
			if ( empty( array_filter( $data ) ) ) {
				continue;
			}

			// Handle rows with different column counts
			if ( count( $data ) !== count( $headers ) ) {
				// Pad or trim data to match headers
				if ( count( $data ) < count( $headers ) ) {
					$data = array_pad( $data, count( $headers ), '' );
				} else {
					$data = array_slice( $data, 0, count( $headers ) );
				}
			}

			$row = @array_combine( $headers, $data );
			
			// If array_combine fails, skip this row
			if ( ! $row || ! is_array( $row ) ) {
				$errors++;
				continue;
			}

			// Required fields
			if ( empty( $row['title'] ) || empty( $row['start_date'] ) ) {
				$errors++;
				continue;
			}

			// Validate date format
			$start_date_test = strtotime( $row['start_date'] );
			if ( ! $start_date_test ) {
				$errors++;
				continue;
			}

			// Create event
			$event_data = array(
				'post_title' => sanitize_text_field( $row['title'] ),
				'post_content' => isset( $row['description'] ) ? wp_kses_post( $row['description'] ) : '',
				'post_excerpt' => isset( $row['excerpt'] ) ? sanitize_text_field( $row['excerpt'] ) : '',
				'post_status' => isset( $row['status'] ) ? sanitize_text_field( $row['status'] ) : 'publish',
				'post_type' => 'twec_event',
			);

			$event_id = wp_insert_post( $event_data );

			if ( is_wp_error( $event_id ) ) {
				$errors++;
				continue;
			}

			// Set dates
			$start_date = sanitize_text_field( $row['start_date'] );
			$start_time = isset( $row['start_time'] ) ? sanitize_text_field( $row['start_time'] ) : '00:00:00';
			$end_date = isset( $row['end_date'] ) ? sanitize_text_field( $row['end_date'] ) : $start_date;
			$end_time = isset( $row['end_time'] ) ? sanitize_text_field( $row['end_time'] ) : '23:59:59';
			$all_day = isset( $row['all_day'] ) && ( $row['all_day'] === '1' || strtolower( $row['all_day'] ) === 'yes' );

			$start_datetime = $start_date . ' ' . $start_time;
			$end_datetime = $end_date . ' ' . $end_time;

			update_post_meta( $event_id, '_twec_event_start_date', $start_datetime );
			update_post_meta( $event_id, '_twec_event_end_date', $end_datetime );
			update_post_meta( $event_id, '_twec_event_start_time', $start_time );
			update_post_meta( $event_id, '_twec_event_end_time', $end_time );
			update_post_meta( $event_id, '_twec_event_all_day', $all_day ? '1' : '0' );

			// Import venue if provided
			if ( ! empty( $row['venue'] ) ) {
				$venue_id = $this->get_or_create_venue( sanitize_text_field( $row['venue'] ), $row );
				if ( $venue_id ) {
					update_post_meta( $event_id, '_twec_event_venue', $venue_id );
				}
			}

			// Import organizer if provided
			if ( ! empty( $row['organizer'] ) ) {
				$organizer_id = $this->get_or_create_organizer( sanitize_text_field( $row['organizer'] ), $row );
				if ( $organizer_id ) {
					update_post_meta( $event_id, '_twec_event_organizer', $organizer_id );
				}
			}

			// Import categories
			if ( ! empty( $row['categories'] ) ) {
				$categories = array_map( 'trim', explode( ',', $row['categories'] ) );
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

			// Import tags
			if ( ! empty( $row['tags'] ) ) {
				$tags = array_map( 'trim', explode( ',', $row['tags'] ) );
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

			$imported++;
		}

		fclose( $handle );

		// Build success/error message
		if ( $imported > 0 ) {
			/* translators: %d: Number of imported events */
			$message = sprintf( 
				_n( 
					'Successfully imported %d event from CSV.', 
					'Successfully imported %d events from CSV.', 
					absint( $imported ), 
					'the-wordpress-event-calendar' 
				), 
				absint( $imported ) 
			);
			
			if ( $errors > 0 ) {
				/* translators: %d: Number of rows with errors */
				$message .= ' ' . sprintf( 
					_n( 
						'%d row had errors and was skipped.', 
						'%d rows had errors and were skipped.', 
						absint( $errors ), 
						'the-wordpress-event-calendar' 
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
				__( 'No events were imported. Please check your CSV file format and ensure required fields (title, start_date) are present.', 'the-wordpress-event-calendar' ),
				'error'
			);
		}
	}

	/**
	 * Get or create venue from CSV data.
	 */
	private function get_or_create_venue( $venue_name, $row ) {
		// Check if venue exists
		$existing = get_posts( array(
			'post_type' => 'twec_venue',
			'title' => $venue_name,
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

		// Create new venue
		$venue_id = wp_insert_post( array(
			'post_title' => $venue_name,
			'post_type' => 'twec_venue',
			'post_status' => 'publish',
		) );

		if ( is_wp_error( $venue_id ) ) {
			return false;
		}

		// Set venue meta from CSV
		if ( isset( $row['venue_address'] ) ) update_post_meta( $venue_id, '_twec_venue_address', sanitize_text_field( $row['venue_address'] ) );
		if ( isset( $row['venue_city'] ) ) update_post_meta( $venue_id, '_twec_venue_city', sanitize_text_field( $row['venue_city'] ) );
		if ( isset( $row['venue_state'] ) ) update_post_meta( $venue_id, '_twec_venue_state', sanitize_text_field( $row['venue_state'] ) );
		if ( isset( $row['venue_zip'] ) ) update_post_meta( $venue_id, '_twec_venue_zip', sanitize_text_field( $row['venue_zip'] ) );
		if ( isset( $row['venue_country'] ) ) update_post_meta( $venue_id, '_twec_venue_country', sanitize_text_field( $row['venue_country'] ) );
		if ( isset( $row['venue_phone'] ) ) update_post_meta( $venue_id, '_twec_venue_phone', sanitize_text_field( $row['venue_phone'] ) );
		if ( isset( $row['venue_website'] ) ) update_post_meta( $venue_id, '_twec_venue_website', esc_url_raw( $row['venue_website'] ) );
		if ( isset( $row['venue_latitude'] ) ) update_post_meta( $venue_id, '_twec_venue_latitude', sanitize_text_field( $row['venue_latitude'] ) );
		if ( isset( $row['venue_longitude'] ) ) update_post_meta( $venue_id, '_twec_venue_longitude', sanitize_text_field( $row['venue_longitude'] ) );

		return $venue_id;
	}

	/**
	 * Get or create organizer from CSV data.
	 */
	private function get_or_create_organizer( $organizer_name, $row ) {
		// Check if organizer exists
		$existing = get_posts( array(
			'post_type' => 'twec_organizer',
			'title' => $organizer_name,
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

		// Create new organizer
		$organizer_id = wp_insert_post( array(
			'post_title' => $organizer_name,
			'post_type' => 'twec_organizer',
			'post_status' => 'publish',
		) );

		if ( is_wp_error( $organizer_id ) ) {
			return false;
		}

		// Set organizer meta from CSV
		if ( isset( $row['organizer_phone'] ) ) update_post_meta( $organizer_id, '_twec_organizer_phone', sanitize_text_field( $row['organizer_phone'] ) );
		if ( isset( $row['organizer_email'] ) ) update_post_meta( $organizer_id, '_twec_organizer_email', sanitize_email( $row['organizer_email'] ) );
		if ( isset( $row['organizer_website'] ) ) update_post_meta( $organizer_id, '_twec_organizer_website', esc_url_raw( $row['organizer_website'] ) );

		return $organizer_id;
	}
}

new TWEC_Importer();

