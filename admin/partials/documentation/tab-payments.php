<?php
/**
 * Documentation tab: Featured listings (Stripe / PayPal).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$payments_url = admin_url( 'edit.php?post_type=twec_event&page=twec-payments' );
?>

<h2><?php esc_html_e( 'Featured listings (not WooCommerce cart)', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Use Events → Settings → Payments to choose Stripe or PayPal as the direct gateway for PlanIt’s “paid / featured listing” checkout. This flow is different from selling tickets through WooCommerce.', 'planit-event-manager' ); ?></p>

<h3><?php esc_html_e( 'What to configure', 'planit-event-manager' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'Gateway (None, Stripe, or PayPal) and Test/Live mode.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Stripe: publishable/secret keys, webhook signing secret, feature price in minor units, currency, line item name, optional success/cancel URLs, webhook URL shown on screen.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'PayPal: sandbox and live client id/secret, webhook id, optional return/cancel URLs, webhook URL shown on screen.', 'planit-event-manager' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Shortcodes & blocks', 'planit-event-manager' ); ?></h3>
<ul>
	<li><code>[twec_stripe_checkout]</code> / <code>[twec_paypal_checkout]</code> <?php esc_html_e( '— optional event_id; usually resolves on single event pages when the matching gateway is active.', 'planit-event-manager' ); ?></li>
	<li><?php esc_html_e( 'Block editor: “PlanIt Stripe Checkout” and “PlanIt PayPal Checkout”.', 'planit-event-manager' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Payment log', 'planit-event-manager' ); ?></h3>
<p>
	<a href="<?php echo esc_url( $payments_url ); ?>"><?php esc_html_e( 'Events → Payments', 'planit-event-manager' ); ?></a>
	<?php esc_html_e( 'lists recorded payment rows for troubleshooting.', 'planit-event-manager' ); ?>
</p>

<div class="notice notice-info inline">
	<p>
		<strong><?php esc_html_e( 'Premium & REST', 'planit-event-manager' ); ?></strong> —
		<?php esc_html_e( 'The Settings screen notes REST checkout endpoints that expect a logged-in session and REST nonce with a Premium license for certain flows. Always test in Test/Sandbox mode first.', 'planit-event-manager' ); ?>
	</p>
</div>
