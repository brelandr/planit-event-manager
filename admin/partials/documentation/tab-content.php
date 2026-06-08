<?php
/**
 * Documentation tab: Events, venues, organizers.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Events', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Events are standard WordPress posts with the “Event” type (twec_event). Create one from Events → Add New (or the “+” in the toolbar).', 'planit-event-manager' ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Event Data metabox', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Sets start/end dates, all-day flag, linked venue and organizer, attendance type (in person, online, hybrid) and optional virtual URL, and (where available) premium-only fields such as cost, website, and timezone with upgrade messaging in the free bundle.', 'planit-event-manager' ); ?> <em><?php esc_html_e( 'Full use of cost, website, and timezone on the front end typically requires Premium.', 'planit-event-manager' ); ?></em></li>
	<li><strong><?php esc_html_e( 'Categories & tags', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Use the sidebar while editing — slugs drive shortcode filters and optional hierarchical event URLs.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Featured image', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Shows in skins that support thumbnails (list, some calendar cells, widgets).', 'planit-event-manager' ); ?></li>
</ul>

<div class="notice notice-info inline">
	<p><strong><?php esc_html_e( 'Premium:', 'planit-event-manager' ); ?></strong> <?php esc_html_e( 'Additional metaboxes and tools load when Premium is licensed — for example recurring rules, featured event flag, WooCommerce ticket product linkage (when WooCommerce ticketing is enabled), custom fields suite, RSVP attendee tools (CSV, print QR manifest, door check-in screen), CSV/TEC importer, and extra taxonomy such as Event Series.', 'planit-event-manager' ); ?></p>
</div>

<h2><?php esc_html_e( 'Venues & organizers', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Create venues and organizers under their admin menus. Each has its own “Details” metabox for address, map coordinates, contact info, and similar fields when provided.', 'planit-event-manager' ); ?></p>
<p><?php esc_html_e( 'On the event screen, pick a venue and organizer from the dropdowns in Event Data. You can create new records from the event editor if your user role permits.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'Google Maps', 'planit-event-manager' ); ?></h3>
<p>
	<?php esc_html_e( 'Venue maps generally need a Maps JavaScript API key under Events → Settings. Without a key, map embeds may be blank.', 'planit-event-manager' ); ?>
</p>
