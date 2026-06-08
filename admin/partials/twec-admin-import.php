<?php
/**
 * Import page template.
 *
 * @package    The_Event_Calendar
 * @subpackage admin/partials
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local scope, not globals
settings_errors( 'twec_import' );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Import Events', 'planit-event-manager' ); ?></h1>

	<?php if ( ! TWEC_Premium::is_available( 'import' ) ) : ?>
		<?php echo wp_kses_post( TWEC_Premium::get_upgrade_notice( esc_html__( 'Event Import', 'planit-event-manager' ), 'admin' ) ); ?>
		<div class="twec-premium-feature-info" style="margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
			<h2><?php esc_html_e( 'Import Features Available in Premium:', 'planit-event-manager' ); ?></h2>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><strong><?php esc_html_e( 'CSV Import', 'planit-event-manager' ); ?></strong> - <?php esc_html_e( 'Bulk import events from CSV files with support for venues, organizers, categories, and tags.', 'planit-event-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'The Events Calendar Import', 'planit-event-manager' ); ?></strong> - <?php esc_html_e( 'Migrate all events, venues, and organizers from The Events Calendar plugin.', 'planit-event-manager' ); ?></li>
			</ul>
			<p style="margin-top: 20px;">
				<a href="<?php echo esc_url( TWEC_Premium::UPGRADE_URL ); ?>" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'Upgrade to Premium', 'planit-event-manager' ); ?></a>
			</p>
		</div>
	<?php else : ?>
	<div class="twec-import-sections" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
		
		<!-- Import from The Events Calendar -->
		<div class="twec-import-section" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php esc_html_e( 'Import from The Events Calendar', 'planit-event-manager' ); ?></h2>
			<?php if ( post_type_exists( 'tribe_events' ) ) : ?>
				<p><?php esc_html_e( 'Import all events, venues, and organizers from The Events Calendar plugin.', 'planit-event-manager' ); ?></p>
				<?php
				$tec_events_count = wp_count_posts( 'tribe_events' );
				$tec_events_total = $tec_events_count->publish + $tec_events_count->draft + $tec_events_count->future;
				?>
				<p><strong>
					<?php
					/* translators: %d: Number of events to import */
					printf( esc_html__( 'Found %d events to import.', 'planit-event-manager' ), absint( $tec_events_total ) );
					?>
				</strong></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'twec_import' ); ?>
					<input type="hidden" name="twec_import_action" value="import_tec" />
					<?php submit_button( __( 'Import Events', 'planit-event-manager' ), 'primary', 'import_tec', false ); ?>
				</form>
				<p class="description"><?php esc_html_e( 'Note: Events that have already been imported will be skipped.', 'planit-event-manager' ); ?></p>
			<?php else : ?>
				<p style="color: #d63638;"><?php esc_html_e( 'The Events Calendar plugin is not installed or activated.', 'planit-event-manager' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Import from CSV -->
		<div class="twec-import-section" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php esc_html_e( 'Import from CSV File', 'planit-event-manager' ); ?></h2>
			<p><?php esc_html_e( 'Upload a CSV file to import events. Download the sample CSV template below to see the required format.', 'planit-event-manager' ); ?></p>
			
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'twec_import' ); ?>
				<input type="hidden" name="twec_import_action" value="import_csv" />
				<input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
				
				<p>
					<label for="csv_file"><?php esc_html_e( 'Select CSV File:', 'planit-event-manager' ); ?></label><br>
					<input type="file" name="csv_file" id="csv_file" accept=".csv" required />
				</p>
				
				<?php submit_button( __( 'Import from CSV', 'planit-event-manager' ), 'primary', 'import_csv', false ); ?>
			</form>
			
			<p style="margin-top: 15px;">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=twec_download_csv_template' ), 'twec_download_csv_template' ) ); ?>" class="button"><?php esc_html_e( 'Download CSV Template', 'planit-event-manager' ); ?></a>
			</p>
			
			<div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 3px;">
				<strong><?php esc_html_e( 'CSV Format:', 'planit-event-manager' ); ?></strong>
				<p style="margin: 5px 0; font-size: 12px;">
					<strong><?php esc_html_e( 'Required columns:', 'planit-event-manager' ); ?></strong> title, start_date<br>
					<strong><?php esc_html_e( 'Optional columns:', 'planit-event-manager' ); ?></strong> description, excerpt, start_time, end_date, end_time, all_day, venue, organizer, categories, tags, status<br>
					<strong><?php esc_html_e( 'Venue columns:', 'planit-event-manager' ); ?></strong> venue_address, venue_city, venue_state, venue_zip, venue_country, venue_phone, venue_website, venue_latitude, venue_longitude<br>
					<strong><?php esc_html_e( 'Organizer columns:', 'planit-event-manager' ); ?></strong> organizer_phone, organizer_email, organizer_website
				</p>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

