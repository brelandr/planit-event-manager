<?php
/**
 * Meta boxes for events, venues, and organizers.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage admin
 */
class TWEC_Meta_Boxes {

	/**
	 * Initialize meta boxes.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10 );
		add_action( 'save_post', array( $this, 'save_event_meta' ) );
		add_action( 'save_post', array( $this, 'save_venue_meta' ) );
		add_action( 'save_post', array( $this, 'save_organizer_meta' ) );
	}
	
	/**
	 * Ensure event meta box is visible.
	 */
	public function show_event_meta_box( $hidden, $screen ) {
		if ( 'twec_event' === $screen->post_type ) {
			$hidden = array_diff( $hidden, array( 'twec_event_details' ) );
		}
		return $hidden;
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes( $post_type ) {
		// Add event details meta box
		if ( 'twec_event' === $post_type ) {
			add_meta_box(
				'twec_event_details',
				'<span style="font-size: 16px; font-weight: bold; color: #0073aa;">📅 ' . __( 'Event Data', 'the-wordpress-event-calendar' ) . '</span>',
				array( $this, 'event_details_callback' ),
				'twec_event',
				'normal',
				'high'
			);
		}
		
		// Ensure meta box is visible by default
		add_filter( 'hidden_meta_boxes', array( $this, 'show_event_meta_box' ), 10, 2 );

		if ( 'twec_venue' === $post_type ) {
			add_meta_box(
				'twec_venue_details',
				__( 'Venue Details', 'the-wordpress-event-calendar' ),
				array( $this, 'venue_details_callback' ),
				'twec_venue',
				'normal',
				'high'
			);
		}

		if ( 'twec_organizer' === $post_type ) {
			add_meta_box(
				'twec_organizer_details',
				__( 'Organizer Details', 'the-wordpress-event-calendar' ),
				array( $this, 'organizer_details_callback' ),
				'twec_organizer',
				'normal',
				'high'
			);
		}
	}

	/**
	 * Event details meta box callback.
	 */
	public function event_details_callback( $post ) {
		wp_nonce_field( 'twec_save_event_meta', 'twec_event_meta_nonce' );
		
		$start_date = get_post_meta( $post->ID, '_twec_event_start_date', true );
		$end_date = get_post_meta( $post->ID, '_twec_event_end_date', true );
		$start_time = get_post_meta( $post->ID, '_twec_event_start_time', true );
		$end_time = get_post_meta( $post->ID, '_twec_event_end_time', true );
		$venue_id = get_post_meta( $post->ID, '_twec_event_venue', true );
		$organizer_id = get_post_meta( $post->ID, '_twec_event_organizer', true );
		$all_day = get_post_meta( $post->ID, '_twec_event_all_day', true );
		$event_cost = get_post_meta( $post->ID, '_twec_event_cost', true );
		$event_website = get_post_meta( $post->ID, '_twec_event_website', true );
		$event_timezone = get_post_meta( $post->ID, '_twec_event_timezone', true );

		// Extract date and time from datetime
		$start_date_only = $start_date ? date( 'Y-m-d', strtotime( $start_date ) ) : '';
		$end_date_only = $end_date ? date( 'Y-m-d', strtotime( $end_date ) ) : '';
		$start_time_only = $start_time ? $start_time : ( $start_date ? date( 'H:i', strtotime( $start_date ) ) : '' );
		$end_time_only = $end_time ? $end_time : ( $end_date ? date( 'H:i', strtotime( $end_date ) ) : '' );

		$venues = get_posts( array( 'post_type' => 'twec_venue', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$organizers = get_posts( array( 'post_type' => 'twec_organizer', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<div class="twec-event-details-meta-box">
			<p class="description" style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #f0f6fc 0%, #e8f4f8 100%); border-left: 4px solid #0073aa; border-radius: 4px; font-size: 14px; line-height: 1.6;">
				<strong style="color: #0073aa; font-size: 15px;"><?php _e( 'Event Data', 'the-wordpress-event-calendar' ); ?>:</strong> <?php _e( 'Fill in the date, time, venue, and other information for your event below. All fields marked with an asterisk (*) are required.', 'the-wordpress-event-calendar' ); ?>
			</p>
			<table class="form-table">
			<tr>
				<th><label for="twec_all_day"><?php _e( 'All Day Event', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<input type="checkbox" id="twec_all_day" name="twec_all_day" value="1" <?php checked( $all_day, '1' ); ?> />
				</td>
			</tr>
			<tr>
				<th><label for="twec_start_date"><?php _e( 'Start Date', 'the-wordpress-event-calendar' ); ?> <span style="color: #d63638;">*</span></label></th>
				<td>
					<input type="date" id="twec_start_date" name="twec_start_date" value="<?php echo esc_attr( $start_date_only ); ?>" required style="font-size: 14px; padding: 8px 12px;" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_start_time"><?php _e( 'Start Time', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<input type="time" id="twec_start_time" name="twec_start_time" value="<?php echo esc_attr( $start_time_only ); ?>" style="font-size: 14px; padding: 8px 12px;" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_end_date"><?php _e( 'End Date', 'the-wordpress-event-calendar' ); ?> <span style="color: #d63638;">*</span></label></th>
				<td>
					<input type="date" id="twec_end_date" name="twec_end_date" value="<?php echo esc_attr( $end_date_only ); ?>" required style="font-size: 14px; padding: 8px 12px;" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_end_time"><?php _e( 'End Time', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<input type="time" id="twec_end_time" name="twec_end_time" value="<?php echo esc_attr( $end_time_only ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="twec_venue"><?php _e( 'Venue', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<select id="twec_venue" name="twec_venue">
						<option value=""><?php _e( 'Select Venue', 'the-wordpress-event-calendar' ); ?></option>
						<?php foreach ( $venues as $venue ) : ?>
							<option value="<?php echo absint( $venue->ID ); ?>" <?php selected( $venue_id, $venue->ID ); ?>><?php echo esc_html( $venue->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=twec_venue' ) ); ?>" target="_blank" rel="noopener"><?php _e( 'Add New Venue', 'the-wordpress-event-calendar' ); ?></a>
				</td>
			</tr>
			<tr>
				<th><label for="twec_organizer"><?php _e( 'Organizer', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<select id="twec_organizer" name="twec_organizer">
						<option value=""><?php _e( 'Select Organizer', 'the-wordpress-event-calendar' ); ?></option>
						<?php foreach ( $organizers as $organizer ) : ?>
							<option value="<?php echo absint( $organizer->ID ); ?>" <?php selected( $organizer_id, $organizer->ID ); ?>><?php echo esc_html( $organizer->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=twec_organizer' ) ); ?>" target="_blank" rel="noopener"><?php _e( 'Add New Organizer', 'the-wordpress-event-calendar' ); ?></a>
				</td>
			</tr>
			<?php if ( TWEC_Premium::is_available( 'event_cost' ) ) : ?>
			<tr>
				<th><label for="twec_event_cost"><?php _e( 'Event Cost', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<input type="text" id="twec_event_cost" name="twec_event_cost" value="<?php echo esc_attr( $event_cost ); ?>" class="regular-text" placeholder="<?php _e( 'e.g., $25, Free, $10-20', 'the-wordpress-event-calendar' ); ?>" />
					<p class="description"><?php _e( 'Enter the cost of the event (e.g., $25, Free, $10-20)', 'the-wordpress-event-calendar' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th><label><?php _e( 'Event Cost', 'the-wordpress-event-calendar' ); ?> <span class="twec-premium-badge">PRO</span></label></th>
				<td>
					<input type="text" class="regular-text" disabled placeholder="<?php _e( 'Premium Feature', 'the-wordpress-event-calendar' ); ?>" />
					<p class="description"><?php echo TWEC_Premium::get_upgrade_notice( __( 'Event Cost', 'the-wordpress-event-calendar' ), 'admin' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ( TWEC_Premium::is_available( 'event_website' ) ) : ?>
			<tr>
				<th><label for="twec_event_website"><?php _e( 'Event Website', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<input type="url" id="twec_event_website" name="twec_event_website" value="<?php echo esc_url( $event_website ); ?>" class="regular-text" placeholder="https://example.com" />
					<p class="description"><?php _e( 'Link to event website or registration page', 'the-wordpress-event-calendar' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th><label><?php _e( 'Event Website', 'the-wordpress-event-calendar' ); ?> <span class="twec-premium-badge">PRO</span></label></th>
				<td>
					<input type="url" class="regular-text" disabled placeholder="<?php _e( 'Premium Feature', 'the-wordpress-event-calendar' ); ?>" />
					<p class="description"><?php echo TWEC_Premium::get_upgrade_notice( __( 'Event Website', 'the-wordpress-event-calendar' ), 'admin' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ( TWEC_Premium::is_available( 'event_timezone' ) ) : ?>
			<tr>
				<th><label for="twec_event_timezone"><?php _e( 'Event Timezone', 'the-wordpress-event-calendar' ); ?></label></th>
				<td>
					<select id="twec_event_timezone" name="twec_event_timezone">
						<option value=""><?php _e( 'Use Site Default', 'the-wordpress-event-calendar' ); ?> (<?php echo wp_timezone_string(); ?>)</option>
						<?php
						$timezones = timezone_identifiers_list();
						foreach ( $timezones as $tz ) {
							?>
							<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $event_timezone, $tz ); ?>><?php echo esc_html( $tz ); ?></option>
							<?php
						}
						?>
					</select>
					<p class="description"><?php _e( 'Select timezone for this event. Leave blank to use site default.', 'the-wordpress-event-calendar' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th><label><?php _e( 'Event Timezone', 'the-wordpress-event-calendar' ); ?> <span class="twec-premium-badge">PRO</span></label></th>
				<td>
					<select disabled>
						<option><?php _e( 'Premium Feature', 'the-wordpress-event-calendar' ); ?></option>
					</select>
					<p class="description"><?php echo TWEC_Premium::get_upgrade_notice( __( 'Event Timezone', 'the-wordpress-event-calendar' ), 'admin' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		</div>
		<?php
	}

	/**
	 * Venue details meta box callback.
	 */
	public function venue_details_callback( $post ) {
		wp_nonce_field( 'twec_save_venue_meta', 'twec_venue_meta_nonce' );
		
		$address = get_post_meta( $post->ID, '_twec_venue_address', true );
		$city = get_post_meta( $post->ID, '_twec_venue_city', true );
		$state = get_post_meta( $post->ID, '_twec_venue_state', true );
		$zip = get_post_meta( $post->ID, '_twec_venue_zip', true );
		$country = get_post_meta( $post->ID, '_twec_venue_country', true );
		$phone = get_post_meta( $post->ID, '_twec_venue_phone', true );
		$website = get_post_meta( $post->ID, '_twec_venue_website', true );
		$latitude = get_post_meta( $post->ID, '_twec_venue_latitude', true );
		$longitude = get_post_meta( $post->ID, '_twec_venue_longitude', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="twec_venue_address"><?php _e( 'Address', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_address" name="twec_venue_address" value="<?php echo esc_attr( $address ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_city"><?php _e( 'City', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_city" name="twec_venue_city" value="<?php echo esc_attr( $city ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_state"><?php _e( 'State/Province', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_state" name="twec_venue_state" value="<?php echo esc_attr( $state ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_zip"><?php _e( 'ZIP/Postal Code', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_zip" name="twec_venue_zip" value="<?php echo esc_attr( $zip ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_country"><?php _e( 'Country', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_country" name="twec_venue_country" value="<?php echo esc_attr( $country ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_phone"><?php _e( 'Phone', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_phone" name="twec_venue_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_website"><?php _e( 'Website', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="url" id="twec_venue_website" name="twec_venue_website" value="<?php echo esc_attr( $website ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_latitude"><?php _e( 'Latitude', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_latitude" name="twec_venue_latitude" value="<?php echo esc_attr( $latitude ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_venue_longitude"><?php _e( 'Longitude', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_venue_longitude" name="twec_venue_longitude" value="<?php echo esc_attr( $longitude ); ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Organizer details meta box callback.
	 */
	public function organizer_details_callback( $post ) {
		wp_nonce_field( 'twec_save_organizer_meta', 'twec_organizer_meta_nonce' );
		
		$phone = get_post_meta( $post->ID, '_twec_organizer_phone', true );
		$email = get_post_meta( $post->ID, '_twec_organizer_email', true );
		$website = get_post_meta( $post->ID, '_twec_organizer_website', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="twec_organizer_phone"><?php _e( 'Phone', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="text" id="twec_organizer_phone" name="twec_organizer_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_organizer_email"><?php _e( 'Email', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="email" id="twec_organizer_email" name="twec_organizer_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="twec_organizer_website"><?php _e( 'Website', 'the-wordpress-event-calendar' ); ?></label></th>
				<td><input type="url" id="twec_organizer_website" name="twec_organizer_website" value="<?php echo esc_attr( $website ); ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save event meta data.
	 */
	public function save_event_meta( $post_id ) {
		if ( ! isset( $_POST['twec_event_meta_nonce'] ) || ! wp_verify_nonce( $_POST['twec_event_meta_nonce'], 'twec_save_event_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'twec_event' !== get_post_type( $post_id ) ) {
			return;
		}

		$all_day = isset( $_POST['twec_all_day'] ) ? '1' : '0';
		update_post_meta( $post_id, '_twec_event_all_day', $all_day );

		if ( isset( $_POST['twec_start_date'] ) ) {
			$start_date = sanitize_text_field( $_POST['twec_start_date'] );
			$start_time = isset( $_POST['twec_start_time'] ) ? sanitize_text_field( $_POST['twec_start_time'] ) : '00:00:00';
			$start_datetime = $start_date . ' ' . $start_time;
			update_post_meta( $post_id, '_twec_event_start_date', $start_datetime );
			update_post_meta( $post_id, '_twec_event_start_time', $start_time );
		}

		if ( isset( $_POST['twec_end_date'] ) ) {
			$end_date = sanitize_text_field( $_POST['twec_end_date'] );
			$end_time = isset( $_POST['twec_end_time'] ) ? sanitize_text_field( $_POST['twec_end_time'] ) : '23:59:59';
			$end_datetime = $end_date . ' ' . $end_time;
			update_post_meta( $post_id, '_twec_event_end_date', $end_datetime );
			update_post_meta( $post_id, '_twec_event_end_time', $end_time );
		}

		if ( isset( $_POST['twec_venue'] ) ) {
			update_post_meta( $post_id, '_twec_event_venue', intval( $_POST['twec_venue'] ) );
		}

		if ( isset( $_POST['twec_organizer'] ) ) {
			update_post_meta( $post_id, '_twec_event_organizer', intval( $_POST['twec_organizer'] ) );
		}
	}

	/**
	 * Save venue meta data.
	 */
	public function save_venue_meta( $post_id ) {
		if ( ! isset( $_POST['twec_venue_meta_nonce'] ) || ! wp_verify_nonce( $_POST['twec_venue_meta_nonce'], 'twec_save_venue_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'twec_venue' !== get_post_type( $post_id ) ) {
			return;
		}

		$fields = array( 'address', 'city', 'state', 'zip', 'country', 'phone', 'website', 'latitude', 'longitude' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ 'twec_venue_' . $field ] ) ) {
				update_post_meta( $post_id, '_twec_venue_' . $field, sanitize_text_field( $_POST[ 'twec_venue_' . $field ] ) );
			}
		}
	}

	/**
	 * Save organizer meta data.
	 */
	public function save_organizer_meta( $post_id ) {
		if ( ! isset( $_POST['twec_organizer_meta_nonce'] ) || ! wp_verify_nonce( $_POST['twec_organizer_meta_nonce'], 'twec_save_organizer_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'twec_organizer' !== get_post_type( $post_id ) ) {
			return;
		}

		$fields = array( 'phone', 'email', 'website' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ 'twec_organizer_' . $field ] ) ) {
				update_post_meta( $post_id, '_twec_organizer_' . $field, sanitize_text_field( $_POST[ 'twec_organizer_' . $field ] ) );
			}
		}
	}
}

// Initialize meta boxes - ensure it runs after WordPress is fully loaded
if ( is_admin() ) {
	new TWEC_Meta_Boxes();
}

