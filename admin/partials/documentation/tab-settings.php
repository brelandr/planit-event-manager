<?php
/**
 * Documentation tab: Settings (Events → Settings).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Events → Settings overview', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'The main options form saves a single combined option group. Sections below mirror the Settings page.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'Display', 'planit-event-manager' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Hide Past Events', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Affects embedded calendar/list behavior where the template respects this global default.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Events Per Page', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Default page size for list-style views.', 'planit-event-manager' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Maps', 'planit-event-manager' ); ?></h3>
<p><?php esc_html_e( 'Google Maps API key for venue maps. Create a browser-restricted key in Google Cloud Console and enable the Maps JavaScript API.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'SEO', 'planit-event-manager' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'JSON-LD for events (single object or optional @graph), BreadcrumbList JSON-LD, Open Graph / Twitter tags — toggle to avoid duplicate output if your SEO plugin already covers events.', 'planit-event-manager' ); ?></li>
</ul>

<h3><?php esc_html_e( 'URLs', 'planit-event-manager' ); ?></h3>
<p><?php esc_html_e( 'Optional hierarchical event URLs with the first event category in the path. After changing, flush permalinks using the button on this page or Settings → Permalinks.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'Calendar interactivity & analytics', 'planit-event-manager' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Interactivity API', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Client-side navigation for the calendar; can be overridden per shortcode/block.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Cookieless view counter', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Increments meta on single event loads (consider caching implications on high-traffic hosts).', 'planit-event-manager' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Payments block (Stripe / PayPal) & WooCommerce / reminders fieldsets', 'planit-event-manager' ); ?></h3>
<p><?php esc_html_e( 'The same Settings page includes payment gateway keys and WooCommerce ticketing — see the dedicated Documentation tabs.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'After-save utilities on the Settings page', 'planit-event-manager' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Flush Permalinks', 'planit-event-manager' ); ?></strong> <?php esc_html_e( '— fixes 404s after URL rule changes.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Test Events', 'planit-event-manager' ); ?></strong> <?php esc_html_e( '— generates or removes sample events flagged as tests.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'iCal subscribe URL', 'planit-event-manager' ); ?></strong> <?php esc_html_e( '— give subscribers this URL for Apple/Google Calendar feeds; append category query args as described on that screen.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Display options / shortcodes', 'planit-event-manager' ); ?></strong> <?php esc_html_e( '— condensed shortcode cheatsheet with links.', 'planit-event-manager' ); ?></li>
</ul>

<div class="notice notice-info inline">
	<p><strong><?php esc_html_e( 'Premium:', 'planit-event-manager' ); ?></strong> <?php esc_html_e( 'RSVP reminder fields on this screen only send mail when reminders are licensed and cron can run.', 'planit-event-manager' ); ?></p>
</div>
