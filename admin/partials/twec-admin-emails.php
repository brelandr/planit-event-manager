<?php
/**
 * Admin: email templates (RSVP reminders + payment receipts).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = (array) get_option( 'twec_settings', array() );
if ( class_exists( 'TWEC_Email_Templates', false ) ) {
	$settings = TWEC_Email_Templates::merged_email_settings( $settings );
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'PlanIt emails', 'planit-event-manager' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Placeholders for both sections: {event_title}, {event_url}, {event_starts}, {site_name}, {buyer_email}, {buyer_name}, {amount_display}, {currency}, {unsubscribe_url} (reminders), {recipient_email} (reminders), {stripe_session_id} or {paypal_capture_id}, {receipt_notes}.', 'planit-event-manager' ); ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'twec_settings_group' ); ?>
		<input type="hidden" name="twec_settings[_twec_emails_form]" value="1" />

		<h2><?php esc_html_e( 'RSVP reminder emails', 'planit-event-manager' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sent to RSVP addresses that opted in, using the schedule under Settings → RSVP reminders.', 'planit-event-manager' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="twec_reminder_email_subject"><?php esc_html_e( 'Subject', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="text" class="large-text" name="twec_settings[reminder_email_subject]" id="twec_reminder_email_subject" value="<?php echo esc_attr( (string) ( $settings['reminder_email_subject'] ?? '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="twec_reminder_email_body_html"><?php esc_html_e( 'HTML body', 'planit-event-manager' ); ?></label></th>
				<td>
					<textarea name="twec_settings[reminder_email_body_html]" id="twec_reminder_email_body_html" class="large-text code" rows="12"><?php echo esc_textarea( (string) ( $settings['reminder_email_body_html'] ?? '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Leave empty to use the built-in HTML template file.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Payment receipt emails', 'planit-event-manager' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sent after Stripe or PayPal confirms a successful capture when a payer email is available.', 'planit-event-manager' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable receipts', 'planit-event-manager' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="twec_settings[payment_receipt_enabled]" value="yes" <?php checked( 'yes', (string) ( $settings['payment_receipt_enabled'] ?? 'no' ) ); ?> />
						<?php esc_html_e( 'Send HTML receipt via WordPress mail', 'planit-event-manager' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="twec_payment_receipt_subject"><?php esc_html_e( 'Subject', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="text" class="large-text" name="twec_settings[payment_receipt_subject]" id="twec_payment_receipt_subject" value="<?php echo esc_attr( (string) ( $settings['payment_receipt_subject'] ?? '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="twec_payment_receipt_body_html"><?php esc_html_e( 'HTML body', 'planit-event-manager' ); ?></label></th>
				<td>
					<textarea name="twec_settings[payment_receipt_body_html]" id="twec_payment_receipt_body_html" class="large-text code" rows="12"><?php echo esc_textarea( (string) ( $settings['payment_receipt_body_html'] ?? '' ) ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="twec_payment_receipt_bcc_admin"><?php esc_html_e( 'BCC (optional)', 'planit-event-manager' ); ?></label></th>
				<td>
					<input type="email" class="large-text" name="twec_settings[payment_receipt_bcc_admin]" id="twec_payment_receipt_bcc_admin" value="<?php echo esc_attr( (string) ( $settings['payment_receipt_bcc_admin'] ?? '' ) ); ?>" />
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
