<?php
/**
 * HTML body for event reminder (loaded by class-twec-reminders.php).
 *
 * Variables: $title, $start_str, $link, $site_name, $unsub_url
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* translators: %s: event title */
$heading = sprintf( __( 'Reminder: %s', 'planit-event-manager' ), is_string( $title ) ? $title : '' );
/* translators: %s: local datetime */
$starts  = sprintf( __( 'Starts: %s', 'planit-event-manager' ), is_string( $start_str ) ? $start_str : '' );
$unsub_t = __( 'Unsubscribe from reminders for this event', 'planit-event-manager' );
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
</head>
<body>
	<p><strong><?php echo esc_html( $heading ); ?></strong></p>
	<p><?php echo esc_html( $starts ); ?></p>
	<p><a href="<?php echo esc_url( is_string( $link ) ? $link : '' ); ?>"><?php echo esc_html( is_string( $link ) ? $link : '' ); ?></a></p>
	<p>— <?php echo esc_html( is_string( $site_name ) ? $site_name : '' ); ?></p>
	<p><a href="<?php echo esc_url( is_string( $unsub_url ) ? $unsub_url : '' ); ?>"><?php echo esc_html( $unsub_t ); ?></a></p>
</body>
</html>
