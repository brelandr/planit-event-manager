<?php
/**
 * Documentation tab: Display, shortcodes, blocks.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Calendar shortcode', 'planit-event-manager' ); ?></h2>
<p><code><?php echo esc_html( '[twec_calendar]' ); ?></code></p>
<p><?php esc_html_e( 'Common attributes:', 'planit-event-manager' ); ?></p>
<ul>
	<li><code>view</code> — <?php esc_html_e( 'day', 'planit-event-manager' ); ?>, <?php esc_html_e( 'month (default)', 'planit-event-manager' ); ?> — <?php esc_html_e( 'Week, Year, Photo, Map require Premium to display in the switcher and render.', 'planit-event-manager' ); ?></li>
	<li><code>interactivity</code> — <?php esc_html_e( 'yes or no — client-side navigation (respects Events → Settings and WordPress Interactivity availability).', 'planit-event-manager' ); ?></li>
	<li><code>category</code> — <?php esc_html_e( 'event category slug', 'planit-event-manager' ); ?></li>
	<li><code>tag</code> — <?php esc_html_e( 'event tag slug', 'planit-event-manager' ); ?></li>
	<li><code>tickets</code> — <?php esc_html_e( 'yes or no — override ticket button visibility when WooCommerce tickets are configured.', 'planit-event-manager' ); ?></li>
</ul>
<p>
	<?php esc_html_e( 'On the front end, logged-in users who can edit events see a Quick add event control above the embedded calendar so they can draft or publish minimal events without leaving the page (full details are editable later in the admin).', 'planit-event-manager' ); ?>
</p>

<h2><?php esc_html_e( 'List shortcode', 'planit-event-manager' ); ?></h2>
<p><code><?php echo esc_html( '[twec_list per_page="10" past_events="hide"]' ); ?></code></p>
<ul>
	<li><code>per_page</code> — <?php esc_html_e( 'number of events per page', 'planit-event-manager' ); ?></li>
	<li><code>past_events</code> — <?php esc_html_e( 'hide (upcoming), or show / all / yes / include (and similar) to include past events', 'planit-event-manager' ); ?></li>
	<li><code>category</code>, <code>tag</code> — <?php esc_html_e( 'slug filters', 'planit-event-manager' ); ?></li>
	<li><code>tickets</code> — <?php esc_html_e( 'yes or no for WooCommerce ticket CTAs when enabled.', 'planit-event-manager' ); ?></li>
</ul>

<h2><?php esc_html_e( 'Block editor blocks', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Insert blocks from the block inserter (search “PlanIt”). Each block maps to the shortcodes above or to payment/ticket shortcodes.', 'planit-event-manager' ); ?></p>
<ul>
	<li><?php esc_html_e( 'PlanIt Calendar, PlanIt Event List — free display; ticket options follow Woo settings.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Premium + license', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'PlanIt RSVP and PlanIt Event Submission blocks require Premium and a valid license for the shortcodes to work on the front end.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'PlanIt PayPal Checkout / PlanIt Stripe Checkout — use when the matching gateway is selected under Events → Settings → Payments (not a Premium license requirement; configuration is required).', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'PlanIt WooCommerce Tickets — requires WooCommerce and ticket sales enabled in Settings.', 'planit-event-manager' ); ?></li>
</ul>

<h2><?php esc_html_e( 'Widget', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Appearance → Widgets (or the Site Editor where supported): add “Upcoming Events” to show a simple list of future events with optional title and count.', 'planit-event-manager' ); ?></p>

<h2><?php esc_html_e( 'Archives & permalinks', 'planit-event-manager' ); ?></h2>
<p>
	<?php esc_html_e( 'The main events archive URL is shown at the bottom of Events → Settings. If you enable “category in event path”, save settings and flush permalinks (button on the same page or Settings → Permalinks).', 'planit-event-manager' ); ?>
</p>
