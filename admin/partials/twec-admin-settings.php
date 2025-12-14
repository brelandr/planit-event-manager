<?php
/**
 * Settings page template.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings = get_option( 'twec_settings', array() );
$hide_past_events = isset( $settings['hide_past_events'] ) ? $settings['hide_past_events'] : 'no';
$events_per_page = isset( $settings['events_per_page'] ) ? $settings['events_per_page'] : 10;
$google_maps_api_key = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php settings_errors( 'twec_settings' ); ?>
	
	<?php echo TWEC_Premium::get_upgrade_notice( '', 'admin' ); ?>
	
	<form method="post" action="options.php">
		<?php settings_fields( 'twec_settings_group' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="hide_past_events"><?php _e( 'Hide Past Events', 'the-wordpress-event-calendar' ); ?></label>
				</th>
				<td>
					<select name="twec_settings[hide_past_events]" id="hide_past_events">
						<option value="no" <?php selected( $hide_past_events, 'no' ); ?>><?php _e( 'No', 'the-wordpress-event-calendar' ); ?></option>
						<option value="yes" <?php selected( $hide_past_events, 'yes' ); ?>><?php _e( 'Yes', 'the-wordpress-event-calendar' ); ?></option>
					</select>
					<p class="description"><?php _e( 'Hide events that have already passed from calendar and list views.', 'the-wordpress-event-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="events_per_page"><?php _e( 'Events Per Page', 'the-wordpress-event-calendar' ); ?></label>
				</th>
				<td>
					<input type="number" name="twec_settings[events_per_page]" id="events_per_page" value="<?php echo esc_attr( $events_per_page ); ?>" min="1" />
					<p class="description"><?php _e( 'Number of events to display per page in list view.', 'the-wordpress-event-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="google_maps_api_key"><?php _e( 'Google Maps API Key', 'the-wordpress-event-calendar' ); ?></label>
				</th>
				<td>
					<input type="text" name="twec_settings[google_maps_api_key]" id="google_maps_api_key" value="<?php echo esc_attr( $google_maps_api_key ); ?>" class="regular-text" />
					<p class="description"><?php _e( 'Enter your Google Maps API key to enable map display for venues.', 'the-wordpress-event-calendar' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	
	<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php _e( 'Fix Permalink Issues', 'the-wordpress-event-calendar' ); ?></h2>
		<p><?php _e( 'If event pages are showing 404 errors, click the button below to flush the permalink structure.', 'the-wordpress-event-calendar' ); ?></p>
		<form method="post" action="">
			<?php wp_nonce_field( 'twec_flush_rewrite_rules' ); ?>
			<input type="hidden" name="twec_flush_rewrite_rules" value="1" />
			<?php submit_button( __( 'Flush Permalinks', 'the-wordpress-event-calendar' ), 'secondary', 'flush_permalinks', false ); ?>
		</form>
		<p class="description"><?php _e( 'You can also go to Settings > Permalinks and click "Save Changes" to flush permalinks.', 'the-wordpress-event-calendar' ); ?></p>
	</div>
	
	<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php _e( 'Test Events', 'the-wordpress-event-calendar' ); ?></h2>
		<p><?php _e( 'Create sample test events to help you test and demonstrate the calendar functionality. These events will be marked as test events and can be easily deleted later.', 'the-wordpress-event-calendar' ); ?></p>
		
		<?php
		global $twec_admin_instance;
		if ( ! $twec_admin_instance ) {
			$twec_admin_instance = new TWEC_Admin();
		}
		$test_events_count = $twec_admin_instance->get_test_events_count();
		?>
		
		<?php if ( $test_events_count > 0 ) : ?>
			<div class="notice notice-info inline" style="margin: 15px 0;">
				<?php
				/* translators: %d: Number of test events */
				?>
				<p><strong><?php printf( esc_html__( 'Found %d test event(s).', 'the-wordpress-event-calendar' ), absint( $test_events_count ) ); ?></strong></p>
			</div>
		<?php endif; ?>
		
		<form method="post" action="" style="margin-top: 15px;">
			<?php wp_nonce_field( 'twec_test_events' ); ?>
			<p>
				<input type="submit" name="twec_create_test_events" class="button button-primary" value="<?php esc_attr_e( 'Create 5 Test Events', 'the-wordpress-event-calendar' ); ?>" <?php echo $test_events_count > 0 ? 'disabled' : ''; ?> /> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $test_events_count > 0 ) : ?>
					<span class="description" style="margin-left: 10px; color: #d63638;"><?php _e( 'Please delete existing test events first.', 'the-wordpress-event-calendar' ); ?></span>
				<?php endif; ?>
			</p>
		</form>
		
		<?php if ( $test_events_count > 0 ) : ?>
			<form method="post" action="" style="margin-top: 15px;">
				<?php wp_nonce_field( 'twec_test_events' ); ?>
				<p>
					<input type="submit" name="twec_delete_test_events" class="button button-secondary" value="<?php esc_attr_e( 'Delete All Test Events', 'the-wordpress-event-calendar' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete all test events? This action cannot be undone.', 'the-wordpress-event-calendar' ); ?>');" />
					<span class="description" style="margin-left: 10px;"><?php _e( 'This will permanently delete all test events.', 'the-wordpress-event-calendar' ); ?></span>
				</p>
			</form>
		<?php endif; ?>
		
		<p class="description" style="margin-top: 15px;"><?php _e( 'Test events include a mix of past and future events to help you test different calendar views and features.', 'the-wordpress-event-calendar' ); ?></p>
	</div>
	
	<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php _e( 'Display Options', 'the-wordpress-event-calendar' ); ?></h2>
		<h3><?php _e( 'Calendar View', 'the-wordpress-event-calendar' ); ?></h3>
		<p><?php _e( 'To display the calendar on any page or post, use this shortcode:', 'the-wordpress-event-calendar' ); ?></p>
		<code>[twec_calendar]</code> or <code>[twec_calendar view="month"]</code>
		<p><?php _e( 'Available views: day, month (Week, Year, Photo, and Map views available in Premium)', 'the-wordpress-event-calendar' ); ?></p>
		
		<h3><?php _e( 'List View (Chronological)', 'the-wordpress-event-calendar' ); ?></h3>
		<p><?php _e( 'To display a chronological list of events, use this shortcode:', 'the-wordpress-event-calendar' ); ?></p>
		<code>[twec_list]</code> or <code>[twec_list per_page="10" past_events="hide"]</code>
		<p><?php _e( 'Options:', 'the-wordpress-event-calendar' ); ?></p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><code>per_page</code> - Number of events per page (default: 10)</li>
			<li><code>past_events</code> - "hide" or "show" past events (default: hide)</li>
			<li><code>category</code> - Filter by category slug</li>
			<li><code>tag</code> - Filter by tag slug</li>
		</ul>
		<p><?php _e( 'You can also visit the events archive page at:', 'the-wordpress-event-calendar' ); ?> <a href="<?php echo esc_url( get_post_type_archive_link( 'twec_event' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_url( get_post_type_archive_link( 'twec_event' ) ); ?></a></p>
	</div>
</div>

