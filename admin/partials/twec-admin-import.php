<?php
/**
 * Import page template.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

settings_errors( 'twec_import' );
?>

<div class="wrap">
	<h1><?php _e( 'Import Events', 'the-wordpress-event-calendar' ); ?></h1>

	<?php if ( ! TWEC_Premium::is_available( 'import' ) ) : ?>
		<?php echo TWEC_Premium::get_upgrade_notice( __( 'Event Import', 'the-wordpress-event-calendar' ), 'admin' ); ?>
		<div class="twec-premium-feature-info" style="margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
			<h2><?php _e( 'Import Features Available in Premium:', 'the-wordpress-event-calendar' ); ?></h2>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><strong><?php _e( 'CSV Import', 'the-wordpress-event-calendar' ); ?></strong> - <?php _e( 'Bulk import events from CSV files with support for venues, organizers, categories, and tags.', 'the-wordpress-event-calendar' ); ?></li>
				<li><strong><?php _e( 'The Events Calendar Import', 'the-wordpress-event-calendar' ); ?></strong> - <?php _e( 'Migrate all events, venues, and organizers from The Events Calendar plugin.', 'the-wordpress-event-calendar' ); ?></li>
			</ul>
			<p style="margin-top: 20px;">
				<a href="<?php echo esc_url( TWEC_Premium::UPGRADE_URL ); ?>" target="_blank" rel="noopener" class="button button-primary"><?php _e( 'Upgrade to Premium', 'the-wordpress-event-calendar' ); ?></a>
			</p>
		</div>
	<?php else : ?>
	<div class="twec-import-sections" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
		
		<!-- Import from The Events Calendar -->
		<div class="twec-import-section" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php _e( 'Import from The Events Calendar', 'the-wordpress-event-calendar' ); ?></h2>
			<?php if ( post_type_exists( 'tribe_events' ) ) : ?>
				<p><?php _e( 'Import all events, venues, and organizers from The Events Calendar plugin.', 'the-wordpress-event-calendar' ); ?></p>
				<?php
				$tec_events_count = wp_count_posts( 'tribe_events' );
				$tec_events_total = $tec_events_count->publish + $tec_events_count->draft + $tec_events_count->future;
				?>
				<?php
				/* translators: %d: Number of events to import */
				?>
				<p><strong><?php printf( esc_html__( 'Found %d events to import.', 'the-wordpress-event-calendar' ), absint( $tec_events_total ) ); ?></strong></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'twec_import' ); ?>
					<input type="hidden" name="twec_import_action" value="import_tec" />
					<?php submit_button( __( 'Import Events', 'the-wordpress-event-calendar' ), 'primary', 'import_tec', false ); ?>
				</form>
				<p class="description"><?php _e( 'Note: Events that have already been imported will be skipped.', 'the-wordpress-event-calendar' ); ?></p>
			<?php else : ?>
				<p style="color: #d63638;"><?php _e( 'The Events Calendar plugin is not installed or activated.', 'the-wordpress-event-calendar' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Import from CSV -->
		<div class="twec-import-section" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php _e( 'Import from CSV File', 'the-wordpress-event-calendar' ); ?></h2>
			<p><?php _e( 'Upload a CSV file to import events. Download the sample CSV template below to see the required format.', 'the-wordpress-event-calendar' ); ?></p>
			
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'twec_import' ); ?>
				<input type="hidden" name="twec_import_action" value="import_csv" />
				<input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
				
				<p>
					<label for="csv_file"><?php _e( 'Select CSV File:', 'the-wordpress-event-calendar' ); ?></label><br>
					<input type="file" name="csv_file" id="csv_file" accept=".csv" required />
				</p>
				
				<?php submit_button( __( 'Import from CSV', 'the-wordpress-event-calendar' ), 'primary', 'import_csv', false ); ?>
			</form>
			
			<p style="margin-top: 15px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?action=twec_download_csv_template' ) ); ?>" class="button"><?php _e( 'Download CSV Template', 'the-wordpress-event-calendar' ); ?></a>
			</p>
			
			<div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 3px;">
				<strong><?php _e( 'CSV Format:', 'the-wordpress-event-calendar' ); ?></strong>
				<p style="margin: 5px 0; font-size: 12px;">
					<strong><?php _e( 'Required columns:', 'the-wordpress-event-calendar' ); ?></strong> title, start_date<br>
					<strong><?php _e( 'Optional columns:', 'the-wordpress-event-calendar' ); ?></strong> description, excerpt, start_time, end_date, end_time, all_day, venue, organizer, categories, tags, status<br>
					<strong><?php _e( 'Venue columns:', 'the-wordpress-event-calendar' ); ?></strong> venue_address, venue_city, venue_state, venue_zip, venue_country, venue_phone, venue_website, venue_latitude, venue_longitude<br>
					<strong><?php _e( 'Organizer columns:', 'the-wordpress-event-calendar' ); ?></strong> organizer_phone, organizer_email, organizer_website
				</p>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

