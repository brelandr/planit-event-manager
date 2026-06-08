<?php
/**
 * Documentation tab: Diagnostics & tools.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$diag_url = admin_url( 'edit.php?post_type=twec_event&page=twec-diagnostics' );
?>

<h2><?php esc_html_e( 'Diagnostics', 'planit-event-manager' ); ?></h2>
<p>
	<a href="<?php echo esc_url( $diag_url ); ?>"><?php esc_html_e( 'Events → Diagnostics', 'planit-event-manager' ); ?></a>
	<?php esc_html_e( 'summarizes environment details useful when opening a support ticket (PHP, WordPress, active theme/plugins snapshot as implemented).', 'planit-event-manager' ); ?>
</p>

<h2><?php esc_html_e( 'Importer', 'planit-event-manager' ); ?></h2>
<p><strong><?php esc_html_e( 'Premium capability bundle:', 'planit-event-manager' ); ?></strong> <?php esc_html_e( 'CSV and The Events Calendar imports load only when TWEC_Premium::is_available( \'import\' ) is true — that requires PlanIt Premium installed and licensed.', 'planit-event-manager' ); ?></p>

<h2><?php esc_html_e( 'Collaboration / research features', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'If present in your build, advanced collaboration tools hook only for eligible sites — treat as optional and documented in developer notes.', 'planit-event-manager' ); ?></p>

<h2><?php esc_html_e( 'Payment & email logs', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Use Events → Payments for gateway rows and Events → Emails for copy. These are operational tools, not privacy exports.', 'planit-event-manager' ); ?></p>
