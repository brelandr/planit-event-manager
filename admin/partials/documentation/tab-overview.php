<?php
/**
 * Documentation tab: Overview.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$upgrade = class_exists( 'TWEC_Premium', false ) ? TWEC_Premium::UPGRADE_URL : '#';
?>
<div class="notice notice-warning twec-docs-premium-banner">
	<p><strong><?php esc_html_e( 'Premium vs. free', 'planit-event-manager' ); ?></strong></p>
	<p>
		<?php esc_html_e( 'This plugin works without Premium. Premium-only tools (calendar views, recurring events, imports, RSVP, reminders that require a license, extra widgets, and related UI) require PlanIt Event Manager Premium to be installed together with this free plugin and a valid Premium license key.', 'planit-event-manager' ); ?>
	</p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=twec_event&page=twec-upgrade' ) ); ?>"><?php esc_html_e( 'Upgrade screen (inside WordPress)', 'planit-event-manager' ); ?></a>
		<?php if ( '#' !== $upgrade ) : ?>
			<a class="button" href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PlanIt Premium website', 'planit-event-manager' ); ?></a>
		<?php endif; ?>
		<?php
		$demo_url_ov = class_exists( 'TWEC_Premium', false ) ? TWEC_Premium::get_premium_live_demo_url() : '';
		if ( '' !== $demo_url_ov ) :
			?>
			<a class="button" href="<?php echo esc_url( $demo_url_ov ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Try Premium Version', 'planit-event-manager' ); ?></a>
		<?php endif; ?>
	</p>
</div>

<h2><?php esc_html_e( 'Getting started', 'planit-event-manager' ); ?></h2>
<ol>
	<li><?php esc_html_e( 'Activate PlanIt Event Manager.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Optional: activate PlanIt Event Manager Premium under Plugins and enter your license key when prompted (Premium).', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Go to Events in the WordPress admin to create venues and organizers, then add events.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Place a calendar or list on a page using blocks (Block editor → Widgets category) or shortcodes, or use Appearance → Widgets for the “Upcoming Events” widget.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Review Events → Settings for maps, SEO options, optional payments, and optional WooCommerce ticketing.', 'planit-event-manager' ); ?></li>
</ol>

<h2><?php esc_html_e( 'WordPress admin tour', 'planit-event-manager' ); ?></h2>
<ul>
	<li><strong><?php esc_html_e( 'Events', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Manage event posts (twec_event). Supports categories and tags.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Venues / Organizers', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Separate post types you link from each event.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Settings', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Maps key, SEO toggles, URLs, payments, WooCommerce tickets, reminders (Premium sends), test events, subscribe URL, inline shortcode help.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Documentation', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'This screen.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Diagnostics', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Health / environment details for support.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Payments', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Featured-listing Stripe/PayPal payment log rows.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Emails', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Customize reminder and receipt email copy where available.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Woo ticket orders', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Appears only when WooCommerce is active and WooCommerce ticket sales are enabled in Settings.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Door check-in', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Premium + license: mobile-friendly RSVP QR scanning and manual check-in (Events → Door check-in).', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Dashboard', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( '“PlanIt: events this week” widget lists events for editors.', 'planit-event-manager' ); ?></li>
</ul>

<h2><?php esc_html_e( 'Optional: demo content', 'planit-event-manager' ); ?></h2>
<p>
	<?php esc_html_e( 'If you installed the separate small add-on “PlanIt Event Demo Data”, use Tools → PlanIt Demo to install or remove tagged sample content. That add-on is optional and is not required for production use.', 'planit-event-manager' ); ?>
</p>
