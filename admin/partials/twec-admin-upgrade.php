<?php
/**
 * Upgrade page template.
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
$upgrade_url      = TWEC_Premium::UPGRADE_URL;
$premium_features = TWEC_Premium::get_premium_features();
$demo_url_sandbox = TWEC_Premium::get_premium_live_demo_url();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Upgrade to Premium', 'planit-event-manager' ); ?></h1>
	
	<div class="twec-upgrade-hero" style="background: linear-gradient(135deg, #0073aa 0%, #005a87 100%); color: #fff; padding: 40px; margin: 20px 0; border-radius: 8px; text-align: center;">
		<h2 style="color: #fff; margin: 0 0 15px 0; font-size: 32px;"><?php esc_html_e( 'Unlock Premium Features', 'planit-event-manager' ); ?></h2>
		<p style="font-size: 18px; margin: 0 0 25px 0; opacity: 0.95;"><?php esc_html_e( 'Get access to advanced calendar views, recurring events, custom fields, and much more!', 'planit-event-manager' ); ?></p>
		<p style="margin: 0;">
		<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-hero" style="background: #f56e28; border-color: #f56e28; color: #fff; font-size: 18px; padding: 15px 40px; height: auto; text-decoration: none; display: inline-block;">
			<?php esc_html_e( 'Upgrade to Premium Now', 'planit-event-manager' ); ?>
		</a>
		<?php if ( '' !== $demo_url_sandbox ) : ?>
		<a href="<?php echo esc_url( $demo_url_sandbox ); ?>" target="_blank" rel="noopener noreferrer" class="button button-hero" style="background: #fff; border-color: #fff; color: #0073aa; font-size: 18px; padding: 15px 40px; height: auto; text-decoration: none; display: inline-block; margin-left: 12px;">
			<?php esc_html_e( 'Try Premium Version', 'planit-event-manager' ); ?>
		</a>
		<?php endif; ?>
		</p>
	</div>
	
	<div class="twec-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
		<?php foreach ( $premium_features as $feature ) : ?>
			<div class="twec-feature-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; border-left: 4px solid #0073aa;">
				<h3 style="margin: 0 0 10px 0; color: #0073aa;"><?php echo esc_html( $feature ); ?></h3>
				<p style="margin: 0; color: #666;">
					<?php
					/* translators: %s: Feature name */
					printf( esc_html__( '%s is available in the Premium version.', 'planit-event-manager' ), esc_html( $feature ) );
					?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
	
	<div class="twec-premium-details" style="background: #f9f9f9; padding: 30px; margin: 30px 0; border-radius: 5px;">
		<h2><?php esc_html_e( 'Premium Features Include:', 'planit-event-manager' ); ?></h2>
		<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px;">
			<div>
				<h3><?php esc_html_e( 'Advanced Calendar Views', 'planit-event-manager' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'Week View - See your week at a glance', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Year View - Annual overview', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Photo View - Visual grid with event images', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Map View - Interactive map with event locations', 'planit-event-manager' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php esc_html_e( 'Event Management', 'planit-event-manager' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'Recurring Events - Create repeating events', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Custom Fields - Add unlimited custom data', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Event Series - Group related events', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Featured Events - Highlight important events', 'planit-event-manager' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php esc_html_e( 'Import & Export', 'planit-event-manager' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'CSV Import - Bulk import events', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'The Events Calendar Import', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'RSS Feed - Events RSS feed', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Event Cost/Price - Display pricing', 'planit-event-manager' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php esc_html_e( 'Advanced Widgets', 'planit-event-manager' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'Featured Events Widget', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Event Series Widget', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Countdown Widget - Real-time countdown timer', 'planit-event-manager' ); ?></li>
					<li><?php esc_html_e( 'Event Website & Timezone support', 'planit-event-manager' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
	
	<div class="twec-cta" style="text-align: center; margin: 40px 0; padding: 30px; background: #fff; border: 2px solid #0073aa; border-radius: 8px;">
		<h2 style="color: #0073aa; margin: 0 0 15px 0;"><?php esc_html_e( 'Ready to Upgrade?', 'planit-event-manager' ); ?></h2>
		<p style="font-size: 18px; margin: 0 0 25px 0; color: #555;"><?php esc_html_e( 'Get all premium features and priority support!', 'planit-event-manager' ); ?></p>
		<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero" style="background: #0073aa; border-color: #0073aa; font-size: 18px; padding: 15px 40px; height: auto; text-decoration: none; display: inline-block;">
			<?php esc_html_e( 'Visit Premium Page', 'planit-event-manager' ); ?>
		</a>
		<?php if ( '' !== $demo_url_sandbox ) : ?>
		<a href="<?php echo esc_url( $demo_url_sandbox ); ?>" target="_blank" rel="noopener noreferrer" class="button button-hero" style="font-size: 16px; padding: 12px 28px; height: auto; text-decoration: none; display: inline-block; margin-left: 10px;">
			<?php esc_html_e( 'Try Premium Version', 'planit-event-manager' ); ?>
		</a>
		<?php endif; ?>
		<p style="margin: 20px 0 0 0; color: #666;">
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" style="color: #0073aa;"><?php echo esc_html( $upgrade_url ); ?></a>
		</p>
	</div>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

