<?php
/**
 * Meta boxes for events, venues, and organizers.
 *
 * @package    The_Event_Calendar
 * @subpackage admin
 * @since      1.0.0
 * @file       class-twec-meta-boxes.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta boxes for events, venues, and organizers.
 *
 * Handles registration and display of meta boxes for custom post types.
 */
class TWEC_Meta_Boxes {

	/**
	 * Boot meta boxes once per request (safe for free-only and Premium companion runtimes).
	 *
	 * @return void
	 */
	public static function init() {
		static $instance = null;
		if ( null !== $instance ) {
			return;
		}
		$instance = new self();
	}

	/**
	 * Initialize meta boxes.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10 );
		add_action( 'save_post', array( $this, 'save_event_meta' ) );
		add_action( 'save_post', array( $this, 'save_venue_meta' ) );
		add_action( 'save_post', array( $this, 'save_organizer_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_event_metabox_rest_sync' ) );
		add_filter( 'default_hidden_meta_boxes', array( $this, 'show_event_meta_box_default' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_event_datetime_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_event_datetime_notice' ), 11 );
	}

	/**
	 * Push Event Data / Custom Fields metabox values into REST post meta during block editor saves.
	 *
	 * @return void
	 */
	public function enqueue_event_metabox_rest_sync() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'twec_event' !== $screen->post_type ) {
			return;
		}

		$file = PLANIT_EVENT_MANAGER_DIR . 'admin/js/twec-metabox-rest-sync.js';
		if ( ! is_readable( $file ) ) {
			return;
		}

		wp_enqueue_script(
			'planit-twec-metabox-rest-sync',
			PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-metabox-rest-sync.js',
			array( 'wp-editor', 'wp-data', 'wp-dom-ready' ),
			PLANIT_EVENT_MANAGER_VERSION . '.' . (string) filemtime( $file ),
			true
		);

		$debug = ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		if ( defined( 'PLANIT_TWEC_META_SYNC_DEBUG' ) && PLANIT_TWEC_META_SYNC_DEBUG ) {
			$debug = true;
		}
		$meta_box_max_height_vh = (int) apply_filters( 'twec_event_metabox_max_height_vh', 50 );
		if ( $meta_box_max_height_vh < 25 ) {
			$meta_box_max_height_vh = 25;
		} elseif ( $meta_box_max_height_vh > 75 ) {
			$meta_box_max_height_vh = 75;
		}

		wp_localize_script(
			'planit-twec-metabox-rest-sync',
			'planitTwecMetaSync',
			array(
				'debug'              => $debug,
				'metaBoxMaxHeightVh' => $meta_box_max_height_vh,
				'validationI18n'     => array(
					'invalidRange' => __( 'End date and time must be on or after the start.', 'planit-event-manager' ),
					'invalidDates' => __( 'Start and end dates must use Y-m-d.', 'planit-event-manager' ),
				),
			)
		);
	}

	/**
	 * Ensure event meta box is visible.
	 *
	 * @param array     $hidden Hidden meta boxes.
	 * @param WP_Screen $screen Screen object.
	 * @return array Modified hidden meta boxes.
	 */
	public function show_event_meta_box( $hidden, $screen ) {
		if ( isset( $screen->post_type ) && 'twec_event' === $screen->post_type ) {
			$hidden = array_diff( $hidden, array( 'twec_event_details' ) );
		}
		return $hidden;
	}

	/**
	 * Keep Event Data visible for new users (not collapsed by default).
	 *
	 * @param array     $hidden Hidden meta boxes.
	 * @param WP_Screen $screen Screen object.
	 * @return array
	 */
	public function show_event_meta_box_default( $hidden, $screen ) {
		if ( isset( $screen->post_type ) && 'twec_event' === $screen->post_type ) {
			$hidden = array_diff( $hidden, array( 'twec_event_details' ) );
		}
		return $hidden;
	}

	/**
	 * Add meta boxes.
	 *
	 * @param string $post_type Post type.
	 */
	public function add_meta_boxes( $post_type ) {
		// Add event details meta box.
		if ( 'twec_event' === $post_type ) {
			add_meta_box(
				'twec_event_details',
				'<span style="font-size: 16px; font-weight: bold; color: #0073aa;">📅 ' . __( 'Event Data', 'planit-event-manager' ) . '</span>',
				array( $this, 'event_details_callback' ),
				'twec_event',
				'normal',
				'high'
			);
		}

		// Ensure meta box is visible by default.
		add_filter( 'hidden_meta_boxes', array( $this, 'show_event_meta_box' ), 10, 2 );

		if ( 'twec_venue' === $post_type ) {
			add_meta_box(
				'twec_venue_details',
				__( 'Venue Details', 'planit-event-manager' ),
				array( $this, 'venue_details_callback' ),
				'twec_venue',
				'normal',
				'high'
			);
		}

		if ( 'twec_organizer' === $post_type ) {
			add_meta_box(
				'twec_organizer_details',
				__( 'Organizer Details', 'planit-event-manager' ),
				array( $this, 'organizer_details_callback' ),
				'twec_organizer',
				'normal',
				'high'
			);
		}
	}

	/**
	 * Event details meta box callback.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function event_details_callback( $post ) {
		wp_nonce_field( 'twec_save_event_meta', 'twec_event_meta_nonce' );

		$start_date     = get_post_meta( $post->ID, '_twec_event_start_date', true );
		$end_date       = get_post_meta( $post->ID, '_twec_event_end_date', true );
		$start_time     = get_post_meta( $post->ID, '_twec_event_start_time', true );
		$end_time       = get_post_meta( $post->ID, '_twec_event_end_time', true );
		$venue_id       = get_post_meta( $post->ID, '_twec_event_venue', true );
		$organizer_id   = get_post_meta( $post->ID, '_twec_event_organizer', true );
		$all_day        = get_post_meta( $post->ID, '_twec_event_all_day', true );
		$event_cost     = get_post_meta( $post->ID, '_twec_event_cost', true );
		$event_website  = get_post_meta( $post->ID, '_twec_event_website', true );
		$event_timezone = get_post_meta( $post->ID, '_twec_event_timezone', true );
		$attendance     = get_post_meta( $post->ID, '_twec_event_attendance', true );
		$attendance     = in_array( (string) $attendance, array( 'online', 'mixed', 'in_person' ), true ) ? (string) $attendance : 'in_person';
		$virtual_url    = get_post_meta( $post->ID, '_twec_event_virtual_url', true );

		// Extract date and time from datetime.
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for extracting date parts from stored datetime, not for current time
		$start_date_only = $start_date ? gmdate( 'Y-m-d', strtotime( $start_date ) ) : '';
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for extracting date parts from stored datetime, not for current time
		$end_date_only = $end_date ? gmdate( 'Y-m-d', strtotime( $end_date ) ) : '';
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for extracting time parts from stored datetime, not for current time
		$start_time_only = $start_time ? $start_time : ( $start_date ? gmdate( 'H:i', strtotime( $start_date ) ) : '' );
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for extracting time parts from stored datetime, not for current time
		$end_time_only = $end_time ? $end_time : ( $end_date ? gmdate( 'H:i', strtotime( $end_date ) ) : '' );

		$venues     = get_posts(
			array(
				'post_type'      => 'twec_venue',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$organizers = get_posts(
			array(
				'post_type'      => 'twec_organizer',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="twec-event-details-meta-box">
			<div id="twec-event-datetime-inline-notice" class="notice notice-error" style="display:none;padding:10px;margin:0 0 12px;"><p></p></div>
			<p class="description" style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #f0f6fc 0%, #e8f4f8 100%); border-left: 4px solid #0073aa; border-radius: 4px; font-size: 14px; line-height: 1.6;">
				<strong style="color: #0073aa; font-size: 15px;"><?php esc_html_e( 'Event Data', 'planit-event-manager' ); ?>:</strong> <?php esc_html_e( 'Fill in the date, time, venue, and other information for your event below. All fields marked with an asterisk (*) are required.', 'planit-event-manager' ); ?>
			</p>
			<table class="form-table">
			<tr>
				<th><label for="twec_all_day"><?php esc_html_e( 'All Day Event', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="checkbox" id="twec_all_day" name="twec_all_day" value="1" <?php checked( $all_day, '1' ); ?> />
				</td>
			</tr>
			<tr>
				<th><label for="twec_start_date"><?php esc_html_e( 'Start Date', 'planit-event-manager' ); ?> <span style="color: #d63638;">*</span></label></th>
				<td>
					<input type="date" id="twec_start_date" name="twec_start_date" value="<?php echo esc_attr( $start_date_only ); ?>" required style="font-size: 14px; padding: 8px 12px;" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_start_time"><?php esc_html_e( 'Start Time', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="time" id="twec_start_time" name="twec_start_time" value="<?php echo esc_attr( $start_time_only ); ?>" style="font-size: 14px; padding: 8px 12px;" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_end_date"><?php esc_html_e( 'End Date', 'planit-event-manager' ); ?> <span style="color: #d63638;">*</span></label></th>
				<td>
					<input type="date" id="twec_end_date" name="twec_end_date" value="<?php echo esc_attr( $end_date_only ); ?>" required style="font-size: 14px; padding: 8px 12px;" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_end_time"><?php esc_html_e( 'End Time', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="time" id="twec_end_time" name="twec_end_time" value="<?php echo esc_attr( $end_time_only ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_venue"><?php esc_html_e( 'Venue', 'planit-event-manager' ); ?></label></th>
				<td>
					<select id="twec_venue" name="twec_venue">
						<option value=""><?php esc_html_e( 'Select Venue', 'planit-event-manager' ); ?></option>
						<?php foreach ( $venues as $venue ) : ?>
							<option value="<?php echo absint( $venue->ID ); ?>" <?php selected( $venue_id, $venue->ID ); ?>><?php echo esc_html( $venue->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=twec_venue' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Add New Venue', 'planit-event-manager' ); ?></a>
				</td>
			</tr>
			<tr>
				<th><label for="twec_organizer"><?php esc_html_e( 'Organizer', 'planit-event-manager' ); ?></label></th>
				<td>
					<select id="twec_organizer" name="twec_organizer">
						<option value=""><?php esc_html_e( 'Select Organizer', 'planit-event-manager' ); ?></option>
						<?php foreach ( $organizers as $organizer ) : ?>
							<option value="<?php echo absint( $organizer->ID ); ?>" <?php selected( $organizer_id, $organizer->ID ); ?>><?php echo esc_html( $organizer->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=twec_organizer' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Add New Organizer', 'planit-event-manager' ); ?></a>
				</td>
			</tr>
			<tr>
				<th><label for="twec_event_attendance"><?php esc_html_e( 'Event attendance (SEO / schema)', 'planit-event-manager' ); ?></label></th>
				<td>
					<select id="twec_event_attendance" name="twec_event_attendance">
						<option value="in_person" <?php selected( $attendance, 'in_person' ); ?>><?php esc_html_e( 'In person', 'planit-event-manager' ); ?></option>
						<option value="online" <?php selected( $attendance, 'online' ); ?>><?php esc_html_e( 'Online', 'planit-event-manager' ); ?></option>
						<option value="mixed" <?php selected( $attendance, 'mixed' ); ?>><?php esc_html_e( 'Hybrid (in person and online)', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Used for Google Event structured data (eventAttendanceMode) and location (Place and/or virtual URL).', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="twec_event_virtual_url"><?php esc_html_e( 'Online event URL (optional)', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="twec_event_virtual_url" name="twec_event_virtual_url" value="<?php echo $virtual_url ? esc_attr( $virtual_url ) : ''; ?>" placeholder="https://example.com/join" />
					<p class="description"><?php esc_html_e( 'Zoom, Meet, live stream, or registration link. Used for online/hybrid events in JSON-LD (VirtualLocation).', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<?php if ( TWEC_Premium::is_available( 'event_cost' ) ) : ?>
			<tr>
				<th><label for="twec_event_cost"><?php esc_html_e( 'Event Cost', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="text" id="twec_event_cost" name="twec_event_cost" value="<?php echo esc_attr( $event_cost ); ?>" class="regular-text" placeholder="<?php esc_html_e( 'e.g., $25, Free, $10-20', 'planit-event-manager' ); ?>" />
					<p class="description"><?php esc_html_e( 'Enter the cost of the event (e.g., $25, Free, $10-20)', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th><label><?php esc_html_e( 'Event Cost', 'planit-event-manager' ); ?> <span class="twec-premium-badge">PRO</span></label></th>
				<td>
					<input type="text" class="regular-text" disabled placeholder="<?php esc_html_e( 'Premium Feature', 'planit-event-manager' ); ?>" />
					<p class="description"><?php echo wp_kses_post( TWEC_Premium::get_upgrade_notice( esc_html__( 'Event Cost', 'planit-event-manager' ), 'admin' ) ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ( TWEC_Premium::is_available( 'event_website' ) ) : ?>
			<tr>
				<th><label for="twec_event_website"><?php esc_html_e( 'Event Website', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="url" id="twec_event_website" name="twec_event_website" value="<?php echo esc_url( $event_website ); ?>" class="regular-text" placeholder="https://example.com" />
					<p class="description"><?php esc_html_e( 'Link to event website or registration page', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th><label><?php esc_html_e( 'Event Website', 'planit-event-manager' ); ?> <span class="twec-premium-badge">PRO</span></label></th>
				<td>
					<input type="url" class="regular-text" disabled placeholder="<?php esc_html_e( 'Premium Feature', 'planit-event-manager' ); ?>" />
					<p class="description"><?php echo wp_kses_post( TWEC_Premium::get_upgrade_notice( esc_html__( 'Event Website', 'planit-event-manager' ), 'admin' ) ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ( TWEC_Premium::is_available( 'event_timezone' ) ) : ?>
			<tr>
				<th><label for="twec_event_timezone"><?php esc_html_e( 'Event Timezone', 'planit-event-manager' ); ?></label></th>
				<td>
					<select id="twec_event_timezone" name="twec_event_timezone">
						<option value=""><?php esc_html_e( 'Use Site Default', 'planit-event-manager' ); ?> (<?php echo esc_html( wp_timezone_string() ); ?>)</option>
						<?php
						$timezones = planit_event_manager_get_timezone_identifiers();
						foreach ( $timezones as $tz ) {
							?>
							<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $event_timezone, $tz ); ?>><?php echo esc_html( $tz ); ?></option>
							<?php
						}
						?>
					</select>
					<p class="description"><?php esc_html_e( 'Select timezone for this event. Leave blank to use site default.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th><label><?php esc_html_e( 'Event Timezone', 'planit-event-manager' ); ?> <span class="twec-premium-badge">PRO</span></label></th>
				<td>
					<select disabled>
						<option><?php esc_html_e( 'Premium Feature', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php echo wp_kses_post( TWEC_Premium::get_upgrade_notice( esc_html__( 'Event Timezone', 'planit-event-manager' ), 'admin' ) ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		</div>
		<?php
	}

	/**
	 * Venue details meta box callback.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function venue_details_callback( $post ) {
		wp_nonce_field( 'twec_save_venue_meta', 'twec_venue_meta_nonce' );

		$address   = get_post_meta( $post->ID, '_twec_venue_address', true );
		$city      = get_post_meta( $post->ID, '_twec_venue_city', true );
		$state     = get_post_meta( $post->ID, '_twec_venue_state', true );
		$zip       = get_post_meta( $post->ID, '_twec_venue_zip', true );
		$country   = get_post_meta( $post->ID, '_twec_venue_country', true );
		$phone     = get_post_meta( $post->ID, '_twec_venue_phone', true );
		$website   = get_post_meta( $post->ID, '_twec_venue_website', true );
		$latitude  = get_post_meta( $post->ID, '_twec_venue_latitude', true );
		$longitude = get_post_meta( $post->ID, '_twec_venue_longitude', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="twec_venue_address"><?php esc_html_e( 'Address', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_address" name="twec_venue_address" value="<?php echo esc_attr( $address ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_city"><?php esc_html_e( 'City', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_city" name="twec_venue_city" value="<?php echo esc_attr( $city ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_state"><?php esc_html_e( 'State/Province', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_state" name="twec_venue_state" value="<?php echo esc_attr( $state ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_zip"><?php esc_html_e( 'ZIP/Postal Code', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_zip" name="twec_venue_zip" value="<?php echo esc_attr( $zip ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_country"><?php esc_html_e( 'Country', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_country" name="twec_venue_country" value="<?php echo esc_attr( $country ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_phone"><?php esc_html_e( 'Phone', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_phone" name="twec_venue_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_website"><?php esc_html_e( 'Website', 'planit-event-manager' ); ?></label></th>
				<td><input type="url" id="twec_venue_website" name="twec_venue_website" value="<?php echo esc_url( $website ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_latitude"><?php esc_html_e( 'Latitude', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_latitude" name="twec_venue_latitude" value="<?php echo esc_attr( $latitude ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_longitude"><?php esc_html_e( 'Longitude', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_venue_longitude" name="twec_venue_longitude" value="<?php echo esc_attr( $longitude ); ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Organizer details meta box callback.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function organizer_details_callback( $post ) {
		wp_nonce_field( 'twec_save_organizer_meta', 'twec_organizer_meta_nonce' );

		$phone   = get_post_meta( $post->ID, '_twec_organizer_phone', true );
		$email   = get_post_meta( $post->ID, '_twec_organizer_email', true );
		$website = get_post_meta( $post->ID, '_twec_organizer_website', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="twec_organizer_phone"><?php esc_html_e( 'Phone', 'planit-event-manager' ); ?></label></th>
				<td><input type="text" id="twec_organizer_phone" name="twec_organizer_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_organizer_email"><?php esc_html_e( 'Email', 'planit-event-manager' ); ?></label></th>
				<td><input type="email" id="twec_organizer_email" name="twec_organizer_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_organizer_website"><?php esc_html_e( 'Website', 'planit-event-manager' ); ?></label></th>
				<td><input type="url" id="twec_organizer_website" name="twec_organizer_website" value="<?php echo esc_url( $website ); ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save event meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_event_meta( $post_id ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified with wp_verify_nonce( wp_unslash( ... ), ... ).
		if ( ! isset( $_POST['twec_event_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['twec_event_meta_nonce'] ), 'twec_save_event_meta' ) ) {
			// Invalid or absent metabox nonce: return (do not wp_die) so core can finish saves during autosave/revisions without this field.
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'twec_event' !== get_post_type( $post_id ) ) {
			return;
		}

		$all_day_flag = isset( $_POST['twec_all_day'] ) ? '1' : '0';

		if ( isset( $_POST['twec_start_date'] ) && isset( $_POST['twec_end_date'] ) && class_exists( 'TWEC_Event_Datetime', false ) ) {
			$start_date = sanitize_text_field( wp_unslash( $_POST['twec_start_date'] ) );
			$end_date   = sanitize_text_field( wp_unslash( $_POST['twec_end_date'] ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below via sanitize_text_field.
			$start_time_raw = isset( $_POST['twec_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['twec_start_time'] ) ) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$end_time_raw = isset( $_POST['twec_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['twec_end_time'] ) ) : '';

			$built = TWEC_Event_Datetime::validate_and_build_storage(
				'1' === $all_day_flag,
				$start_date,
				$end_date,
				$start_time_raw,
				$end_time_raw
			);

			if ( is_wp_error( $built ) ) {
				set_transient(
					'twec_event_datetime_notice_' . (int) get_current_user_id(),
					wp_strip_all_tags( (string) $built->get_error_message() ),
					120
				);

				return;
			}

			update_post_meta( $post_id, '_twec_event_all_day', $built['all_day_meta'] );
			update_post_meta( $post_id, '_twec_event_start_date', $built['start_dt'] );
			update_post_meta( $post_id, '_twec_event_end_date', $built['end_dt'] );
			update_post_meta( $post_id, '_twec_event_start_time', $built['start_t'] );
			update_post_meta( $post_id, '_twec_event_end_time', $built['end_t'] );
		}

		if ( isset( $_POST['twec_venue'] ) ) {
			update_post_meta( $post_id, '_twec_event_venue', absint( wp_unslash( $_POST['twec_venue'] ) ) );
		}

		if ( isset( $_POST['twec_organizer'] ) ) {
			update_post_meta( $post_id, '_twec_event_organizer', absint( wp_unslash( $_POST['twec_organizer'] ) ) );
		}

		if ( isset( $_POST['twec_event_attendance'] ) ) {
			$at = sanitize_text_field( wp_unslash( $_POST['twec_event_attendance'] ) );
			if ( in_array( $at, array( 'in_person', 'online', 'mixed' ), true ) ) {
				update_post_meta( $post_id, '_twec_event_attendance', $at );
			}
		}

		$vurl = isset( $_POST['twec_event_virtual_url'] ) ? esc_url_raw( wp_unslash( $_POST['twec_event_virtual_url'] ) ) : '';
		update_post_meta( $post_id, '_twec_event_virtual_url', $vurl );

		// Event cost, website, timezone (shown when licensed; premium bundle meta save may not run if TWEC was loaded from this plugin first).
		if ( TWEC_Premium::is_available( 'event_cost' ) && isset( $_POST['twec_event_cost'] ) ) {
			update_post_meta( $post_id, '_twec_event_cost', sanitize_text_field( wp_unslash( $_POST['twec_event_cost'] ) ) );
		}
		if ( TWEC_Premium::is_available( 'event_website' ) && isset( $_POST['twec_event_website'] ) ) {
			update_post_meta( $post_id, '_twec_event_website', esc_url_raw( wp_unslash( $_POST['twec_event_website'] ) ) );
		}
		if ( TWEC_Premium::is_available( 'event_timezone' ) && isset( $_POST['twec_event_timezone'] ) ) {
			$tz_raw = sanitize_text_field( wp_unslash( $_POST['twec_event_timezone'] ) );
			if ( '' === $tz_raw ) {
				delete_post_meta( $post_id, '_twec_event_timezone' );
			} elseif ( in_array( $tz_raw, planit_event_manager_get_timezone_identifiers(), true ) ) {
				update_post_meta( $post_id, '_twec_event_timezone', $tz_raw );
			}
		}
	}

	/**
	 * Admin CSS/JS for inline Event Data date validation on classic edit screens.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_event_datetime_admin_assets( $hook_suffix ) {
		if ( ! in_array( (string) $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'twec_event' !== $screen->post_type ) {
			return;
		}

		$base_url = defined( 'PLANIT_EVENT_MANAGER_URL' ) ? PLANIT_EVENT_MANAGER_URL : '';
		$file     = defined( 'PLANIT_EVENT_MANAGER_DIR' ) ? PLANIT_EVENT_MANAGER_DIR . 'admin/js/twec-event-datetime-admin.js' : '';
		if ( '' === $base_url || ! is_readable( $file ) ) {
			return;
		}

		wp_enqueue_script(
			'twec-event-datetime-admin',
			$base_url . 'admin/js/twec-event-datetime-admin.js',
			array( 'jquery' ),
			defined( 'PLANIT_EVENT_MANAGER_VERSION' ) ? PLANIT_EVENT_MANAGER_VERSION : '1.0.0',
			true
		);

		wp_localize_script(
			'twec-event-datetime-admin',
			'planitTwecEventValidation',
			array(
				'invalidRange' => __( 'End date and time must be on or after the start.', 'planit-event-manager' ),
				'badDates'     => __( 'Start and end dates must use Y-m-d.', 'planit-event-manager' ),
			)
		);
	}

	/**
	 * Surface save-blocked transient after invalid Event Data range on classic submit.
	 *
	 * @return void
	 */
	public function maybe_show_event_datetime_notice() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'twec_event' !== $screen->post_type ) {
			return;
		}
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$key = 'twec_event_datetime_notice_' . $user_id;
		$msg = get_transient( $key );
		if ( ! is_string( $msg ) || '' === trim( $msg ) ) {
			return;
		}
		delete_transient( $key );
		if ( function_exists( 'wp_admin_notice' ) ) {
			wp_admin_notice(
				esc_html( $msg ),
				array(
					'type'               => 'error',
					'additional_classes' => array( 'twec-event-datetime-save-notice' ),
				)
			);
		} else {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $msg )
			);
		}
	}

	/**
	 * Save venue meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_venue_meta( $post_id ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified with wp_verify_nonce( wp_unslash( ... ), ... ).
		if ( ! isset( $_POST['twec_venue_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['twec_venue_meta_nonce'] ), 'twec_save_venue_meta' ) ) {
			// Invalid or absent metabox nonce: return (do not wp_die) so core can finish saves during autosave/revisions without this field.
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'twec_venue' !== get_post_type( $post_id ) ) {
			return;
		}

		$fields = array( 'address', 'city', 'state', 'zip', 'country', 'phone', 'website', 'latitude', 'longitude' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ 'twec_venue_' . $field ] ) ) {
				update_post_meta( $post_id, '_twec_venue_' . $field, sanitize_text_field( wp_unslash( $_POST[ 'twec_venue_' . $field ] ) ) );
			}
		}
	}

	/**
	 * Save organizer meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_organizer_meta( $post_id ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified with wp_verify_nonce( wp_unslash( ... ), ... ).
		if ( ! isset( $_POST['twec_organizer_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['twec_organizer_meta_nonce'] ), 'twec_save_organizer_meta' ) ) {
			// Invalid or absent metabox nonce: return (do not wp_die) so core can finish saves during autosave/revisions without this field.
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'twec_organizer' !== get_post_type( $post_id ) ) {
			return;
		}

		$fields = array( 'phone', 'email', 'website' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ 'twec_organizer_' . $field ] ) ) {
				update_post_meta( $post_id, '_twec_organizer_' . $field, sanitize_text_field( wp_unslash( $_POST[ 'twec_organizer_' . $field ] ) ) );
			}
		}
	}
}

// Meta boxes are initialized by TWEC class.

