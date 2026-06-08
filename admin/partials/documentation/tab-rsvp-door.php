<?php
/**
 * Documentation tab: RSVP QR manifests & door check-in (Premium).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$door_url = admin_url( 'edit.php?post_type=twec_event&page=twec-door-scan' );
?>

<h2><?php esc_html_e( 'RSVP attendee tools, QR tickets, and door check-in', 'planit-event-manager' ); ?></h2>
<p>
	<?php esc_html_e( 'These workflows require PlanIt Event Manager Premium to be installed with an active license, WooCommerce ticketing is not required. Guests RSVP via the front end; staff manage exports and check-in in wp-admin.', 'planit-event-manager' ); ?>
</p>

<h3><?php esc_html_e( 'On each event (sidebar metabox)', 'planit-event-manager' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Download RSVP CSV', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Confirmed guests with display names and per-guest check-in tokens (for manual verification or integrations).', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Print QR manifest', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Opens a print-friendly page with one QR code per guest. Each QR encodes a signed payload (see below) for quick scanning at the door.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'Open door check-in', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Jumps to the Door check-in screen with this event pre-selected.', 'planit-event-manager' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Events → Door check-in', 'planit-event-manager' ); ?></h3>
<p>
	<?php esc_html_e( 'Use a phone or tablet on HTTPS. Start the camera to scan guest QR codes from the manifest, paste a full scan string, or enter email + token from the CSV. Results and errors appear on screen. Staff need permission to edit the event being checked in.', 'planit-event-manager' ); ?>
</p>
<p>
	<a class="button button-secondary" href="<?php echo esc_url( $door_url ); ?>"><?php esc_html_e( 'Open Door check-in', 'planit-event-manager' ); ?></a>
</p>

<h3><?php esc_html_e( 'Signed scan payload (planit1)', 'planit-event-manager' ); ?></h3>
<p>
	<?php esc_html_e( 'Compact format:', 'planit-event-manager' ); ?> <code>planit1:</code> <?php esc_html_e( 'plus base64url-encoded JSON containing event id, normalized email, token, and an HMAC signature (server uses the auth salt). Invalid or tampered codes are rejected.', 'planit-event-manager' ); ?>
</p>

<h3><?php esc_html_e( 'Check-in log & duplicates', 'planit-event-manager' ); ?></h3>
<p>
	<?php esc_html_e( 'Successful check-ins are stored on the event as post meta', 'planit-event-manager' ); ?> <code>_twec_rsvp_checkins</code>
	<?php esc_html_e( '(email, time, staff user, source: scan or manual). By default the same email cannot check in twice; a second attempt returns HTTP 409 with code', 'planit-event-manager' ); ?> <code>already_checked</code>.
	<?php esc_html_e( 'Developers can allow repeat check-ins with the', 'planit-event-manager' ); ?> <code>twec_rsvp_checkin_allow_reentry</code> <?php esc_html_e( 'filter.', 'planit-event-manager' ); ?>
</p>

<h3><?php esc_html_e( 'REST API (integrations)', 'planit-event-manager' ); ?></h3>
<ul>
	<li><code>POST <?php echo esc_html( rest_url( 'planit/v1/rsvp/checkin' ) ); ?></code> <?php esc_html_e( 'and alias', 'planit-event-manager' ); ?> <code><?php echo esc_html( rest_url( 'planit/v1/rsvp-scan' ) ); ?></code></li>
	<li><?php esc_html_e( 'Authenticated staff; body includes', 'planit-event-manager' ); ?> <code>nonce</code> (<?php esc_html_e( 'same as other PlanIt REST routes:', 'planit-event-manager' ); ?> <code>wp_rest</code>) <?php esc_html_e( 'and either', 'planit-event-manager' ); ?> <code>scan</code> <?php esc_html_e( '(full signed string) or', 'planit-event-manager' ); ?> <code>event_id</code> + <code>email</code> + <code>token</code>.</li>
	<li><?php esc_html_e( 'Rate limiting uses the existing', 'planit-event-manager' ); ?> <code>twec_rsvp_checkin_rate_limit_per_minute</code> <?php esc_html_e( 'filter.', 'planit-event-manager' ); ?></li>
</ul>

<div class="notice notice-warning inline">
	<p>
		<strong><?php esc_html_e( 'Security', 'planit-event-manager' ); ?></strong> —
		<?php esc_html_e( 'Use HTTPS on devices that use the camera. Do not log or share scan strings publicly.', 'planit-event-manager' ); ?>
	</p>
</div>
