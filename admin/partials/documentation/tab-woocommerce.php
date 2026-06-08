<?php
/**
 * Documentation tab: WooCommerce ticketing.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$woo_orders_url = admin_url( 'edit.php?post_type=twec_event&page=twec-wc-ticket-orders' );
?>

<h2><?php esc_html_e( 'WooCommerce ticket sales', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'This is separate from the Stripe/PayPal “featured listing” payment gateway in Settings. Ticket sales use WooCommerce checkout and simple products linked to events.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'Enable & configure', 'planit-event-manager' ); ?></h3>
<ol>
	<li><?php esc_html_e( 'Install and activate WooCommerce.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Open Events → Settings, scroll to the WooCommerce fieldset, enable “WooCommerce ticket sales”, and save.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'On each event, set the linked product ID (created as a WooCommerce simple product).', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Optional: control default ticket buttons on list/calendar, require buyer details at checkout, and style buttons (theme, solid, outline, custom colors).', 'planit-event-manager' ); ?></li>
</ol>

<h3><?php esc_html_e( 'Shortcode & block', 'planit-event-manager' ); ?></h3>
<p><code>[twec_wc_add_to_cart]</code> <?php esc_html_e( 'outputs the add-to-cart control (optionally with event_id and label attributes). The matching block is “PlanIt WooCommerce Tickets”.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'Admin: Woo ticket orders', 'planit-event-manager' ); ?></h3>
<p>
	<?php esc_html_e( 'When tickets are enabled, an admin submenu may appear for ticket-related order review.', 'planit-event-manager' ); ?>
	<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
		<a href="<?php echo esc_url( $woo_orders_url ); ?>"><?php esc_html_e( 'Open Woo ticket orders', 'planit-event-manager' ); ?></a>
	<?php endif; ?>
</p>

<div class="notice notice-info inline">
	<p><strong><?php esc_html_e( 'Premium:', 'planit-event-manager' ); ?></strong> <?php esc_html_e( 'Capacity/RSVP-style limits may appear with Premium-only metaboxes; core WooCommerce integration itself does not replace a Premium license for those features.', 'planit-event-manager' ); ?></p>
</div>
