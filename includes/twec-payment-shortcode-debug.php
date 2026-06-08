<?php
/**
 * Admin-visible hints when payment checkout shortcodes intentionally output nothing.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optionally show administrators why `[twec_stripe_checkout]` / `[twec_paypal_checkout]` output nothing.
 *
 * Only users with manage_options see this (typically site admins); other visitors continue to see no output.
 *
 * @param string $shortcode_slug 'stripe' or 'paypal' (which gateway this shortcode is for).
 * @param string $reason         no_license | wrong_gateway | no_event | no_capability.
 * @return string Empty string unless the current user is an administrator.
 */
if ( ! function_exists( 'twec_payment_shortcode_admin_hint' ) ) {
	function twec_payment_shortcode_admin_hint( $shortcode_slug, $reason ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		if ( function_exists( 'apply_filters' ) ) {
			$show = (bool) apply_filters( 'twec_payment_shortcode_admin_hint_enabled', true, $shortcode_slug, $reason );
			if ( ! $show ) {
				return '';
			}
		}

		$name = 'stripe' === $shortcode_slug ? '[twec_stripe_checkout]' : '[twec_paypal_checkout]';

		$messages = array(
			'no_license'    => __( 'PlanIt Premium must be activated with a valid license before payment checkout shortcodes render.', 'planit-event-manager' ),
			'wrong_gateway' => 'stripe' === $shortcode_slug
				? __( 'Choose Stripe as the payment gateway in PlanIt settings, or use [twec_paypal_checkout] if you use PayPal.', 'planit-event-manager' )
				: __( 'Choose PayPal as the payment gateway in PlanIt settings, or use [twec_stripe_checkout] if you use Stripe.', 'planit-event-manager' ),
			'no_event'      => __( 'Add event_id="123" (a published event post ID) or place this shortcode on a single event page so the event is known.', 'planit-event-manager' ),
			'no_capability' => __( 'The checkout button only appears for users who are logged in and can edit that event (so they can run checkout).', 'planit-event-manager' ),
		);

		if ( empty( $messages[ $reason ] ) ) {
			return '';
		}

		$text = $messages[ $reason ];
		if ( function_exists( 'apply_filters' ) ) {
			$text = (string) apply_filters( 'twec_payment_shortcode_admin_hint_message', $text, $shortcode_slug, $reason );
		}

		return sprintf(
			'<div class="twec-payment-shortcode-hint" role="note"><p class="twec-payment-shortcode-hint__title">%s</p><p class="twec-payment-shortcode-hint__body">%s</p></div>',
			/* translators: %s: shortcode name in brackets. */
			esc_html( sprintf( __( 'PlanIt: %s not shown', 'planit-event-manager' ), $name ) ),
			esc_html( $text )
		);
	}
}
