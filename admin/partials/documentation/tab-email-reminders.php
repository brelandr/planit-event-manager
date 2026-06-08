<?php
/**
 * Documentation tab: Emails & RSVP reminders.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emails_url = admin_url( 'edit.php?post_type=twec_event&page=twec-emails' );
?>

<h2><?php esc_html_e( 'Events → Emails', 'planit-event-manager' ); ?></h2>
<p>
	<a href="<?php echo esc_url( $emails_url ); ?>"><?php esc_html_e( 'Open Emails settings', 'planit-event-manager' ); ?></a>
	<?php esc_html_e( 'to adjust reminder/receipt wording where templates are exposed. Capability can be filtered for teams that should not edit payment keys.', 'planit-event-manager' ); ?>
</p>

<h2><?php esc_html_e( 'RSVP email reminders', 'planit-event-manager' ); ?></h2>
<p><strong><?php esc_html_e( 'Premium license required:', 'planit-event-manager' ); ?></strong></p>
<p><?php esc_html_e( 'Reminder sending is gated by a valid Premium license — see TWEC_Reminders in the codebase. Turning the switches on in Events → Settings without a license will not send production reminders.', 'planit-event-manager' ); ?></p>
<p><?php esc_html_e( 'When licensed, reminders use WordPress cron (or Action Scheduler when WooCommerce provides it). Configure lead time in hours before the event start.', 'planit-event-manager' ); ?></p>

<h2><?php esc_html_e( 'Related blocks (front end)', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'RSVP and submission blocks/shortcodes require Premium and a valid license — see the Display and Premium tabs.', 'planit-event-manager' ); ?></p>
