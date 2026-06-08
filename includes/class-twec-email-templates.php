<?php
/**
 * Placeholder substitution for RSVP reminder + payment receipt emails.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token replacement helpers.
 */
class TWEC_Email_Templates {

	/**
	 * Merge tokens array and replace `{key}` in subject/body.
	 *
	 * @param string               $template Template text.
	 * @param array<string,string> $tokens   Token map (plain strings; escaping is caller responsibility for HTML bodies).
	 * @return string
	 */
	public static function replace_tokens( $template, array $tokens ) {
		if ( '' === (string) $template ) {
			return '';
		}
		foreach ( $tokens as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( '' === $key ) {
				continue;
			}
			$template = str_replace( '{' . $key . '}', is_scalar( $v ) ? (string) $v : '', $template );
		}
		return (string) $template;
	}

	/**
	 * Map optional keys from settings with defaults merged.
	 *
	 * @param array<string,mixed> $settings twec_settings option (partial).
	 * @return array<string,string>
	 */
	public static function merged_email_settings( array $settings ) {
		$defaults = array(
			'reminder_email_subject'    => __( 'Reminder: {event_title}', 'planit-event-manager' ),
			'reminder_email_body_html'  => '',
			'payment_receipt_enabled'   => 'no',
			'payment_receipt_subject'   => __( 'Receipt: {event_title}', 'planit-event-manager' ),
			'payment_receipt_body_html' => '',
			'payment_receipt_bcc_admin' => '',
		);

		$merged = wp_parse_args( $settings, $defaults );

		$merged['payment_receipt_enabled'] = ( ! empty( $merged['payment_receipt_enabled'] ) && 'yes' === (string) $merged['payment_receipt_enabled'] ) ? 'yes' : 'no';

		return $merged;
	}
}
