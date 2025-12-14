<?php
/**
 * Upgrade page template.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$upgrade_url = TWEC_Premium::UPGRADE_URL;
$premium_features = TWEC_Premium::get_premium_features();
?>
<div class="wrap">
	<h1><?php _e( 'Upgrade to Premium', 'the-wordpress-event-calendar' ); ?></h1>
	
	<div class="twec-upgrade-hero" style="background: linear-gradient(135deg, #0073aa 0%, #005a87 100%); color: #fff; padding: 40px; margin: 20px 0; border-radius: 8px; text-align: center;">
		<h2 style="color: #fff; margin: 0 0 15px 0; font-size: 32px;"><?php _e( 'Unlock Premium Features', 'the-wordpress-event-calendar' ); ?></h2>
		<p style="font-size: 18px; margin: 0 0 25px 0; opacity: 0.95;"><?php _e( 'Get access to advanced calendar views, recurring events, custom fields, and much more!', 'the-wordpress-event-calendar' ); ?></p>
		<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="button button-hero" style="background: #f56e28; border-color: #f56e28; color: #fff; font-size: 18px; padding: 15px 40px; height: auto; text-decoration: none; display: inline-block;">
			<?php _e( 'Upgrade to Premium Now', 'the-wordpress-event-calendar' ); ?>
		</a>
	</div>
	
	<div class="twec-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
		<?php foreach ( $premium_features as $feature ) : ?>
			<div class="twec-feature-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; border-left: 4px solid #0073aa;">
				<h3 style="margin: 0 0 10px 0; color: #0073aa;"><?php echo esc_html( $feature ); ?></h3>
				<?php
				/* translators: %s: Feature name */
				?>
				<p style="margin: 0; color: #666;"><?php printf( esc_html__( '%s is available in the Premium version.', 'the-wordpress-event-calendar' ), esc_html( $feature ) ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	
	<div class="twec-premium-details" style="background: #f9f9f9; padding: 30px; margin: 30px 0; border-radius: 5px;">
		<h2><?php _e( 'Premium Features Include:', 'the-wordpress-event-calendar' ); ?></h2>
		<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px;">
			<div>
				<h3><?php _e( 'Advanced Calendar Views', 'the-wordpress-event-calendar' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php _e( 'Week View - See your week at a glance', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Year View - Annual overview', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Photo View - Visual grid with event images', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Map View - Interactive map with event locations', 'the-wordpress-event-calendar' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php _e( 'Event Management', 'the-wordpress-event-calendar' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php _e( 'Recurring Events - Create repeating events', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Custom Fields - Add unlimited custom data', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Event Series - Group related events', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Featured Events - Highlight important events', 'the-wordpress-event-calendar' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php _e( 'Import & Export', 'the-wordpress-event-calendar' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php _e( 'CSV Import - Bulk import events', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'The Events Calendar Import', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'RSS Feed - Events RSS feed', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Event Cost/Price - Display pricing', 'the-wordpress-event-calendar' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php _e( 'Advanced Widgets', 'the-wordpress-event-calendar' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php _e( 'Featured Events Widget', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Event Series Widget', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Countdown Widget - Real-time countdown timer', 'the-wordpress-event-calendar' ); ?></li>
					<li><?php _e( 'Event Website & Timezone support', 'the-wordpress-event-calendar' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
	
	<div class="twec-cta" style="text-align: center; margin: 40px 0; padding: 30px; background: #fff; border: 2px solid #0073aa; border-radius: 8px;">
		<h2 style="color: #0073aa; margin: 0 0 15px 0;"><?php _e( 'Ready to Upgrade?', 'the-wordpress-event-calendar' ); ?></h2>
		<p style="font-size: 18px; margin: 0 0 25px 0; color: #555;"><?php _e( 'Get all premium features and priority support!', 'the-wordpress-event-calendar' ); ?></p>
		<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="button button-primary button-hero" style="background: #0073aa; border-color: #0073aa; font-size: 18px; padding: 15px 40px; height: auto; text-decoration: none; display: inline-block;">
			<?php _e( 'Visit Premium Page', 'the-wordpress-event-calendar' ); ?>
		</a>
		<p style="margin: 20px 0 0 0; color: #666;">
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" style="color: #0073aa;"><?php echo esc_url( $upgrade_url ); ?></a>
		</p>
	</div>
</div>

