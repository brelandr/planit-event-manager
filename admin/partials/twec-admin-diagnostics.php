<?php
/**
 * Diagnostics page for troubleshooting calendar issues.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get sample events
$events = get_posts( array(
	'post_type' => 'twec_event',
	'posts_per_page' => 10,
	'post_status' => 'publish',
	'orderby' => 'date',
	'order' => 'DESC',
) );

// Get current month events
$current_month = date( 'Y-m' );
$current_month_events = get_posts( array(
	'post_type' => 'twec_event',
	'posts_per_page' => -1,
	'post_status' => 'publish',
	'meta_query' => array(
		'relation' => 'AND',
		array(
			'key' => '_twec_event_start_date',
			'value' => $current_month . '-01 00:00:00',
			'compare' => '>=',
			'type' => 'DATETIME',
		),
		array(
			'key' => '_twec_event_start_date',
			'value' => $current_month . '-31 23:59:59',
			'compare' => '<=',
			'type' => 'DATETIME',
		),
	),
) );

$settings = get_option( 'twec_settings', array() );
?>
<div class="wrap">
	<h1><?php _e( 'Event Calendar Diagnostics', 'the-wordpress-event-calendar' ); ?></h1>
	
	<h2><?php _e( 'Site Configuration', 'the-wordpress-event-calendar' ); ?></h2>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e( 'Setting', 'the-wordpress-event-calendar' ); ?></th>
				<th><?php _e( 'Value', 'the-wordpress-event-calendar' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong><?php _e( 'WordPress Timezone', 'the-wordpress-event-calendar' ); ?></strong></td>
				<td><?php echo wp_timezone_string(); ?></td>
			</tr>
			<tr>
				<td><strong><?php _e( 'PHP Timezone', 'the-wordpress-event-calendar' ); ?></strong></td>
				<td><?php echo date_default_timezone_get(); ?></td>
			</tr>
			<tr>
				<td><strong><?php _e( 'Current Time (WordPress)', 'the-wordpress-event-calendar' ); ?></strong></td>
				<td><?php echo current_time( 'mysql' ); ?> (<?php echo current_time( 'Y-m-d H:i:s' ); ?>)</td>
			</tr>
			<tr>
				<td><strong><?php _e( 'Current Time (PHP)', 'the-wordpress-event-calendar' ); ?></strong></td>
				<td><?php echo date( 'Y-m-d H:i:s' ); ?></td>
			</tr>
			<tr>
				<td><strong><?php _e( 'Date Format', 'the-wordpress-event-calendar' ); ?></strong></td>
				<td><?php echo get_option( 'date_format' ); ?></td>
			</tr>
			<tr>
				<td><strong><?php _e( 'Time Format', 'the-wordpress-event-calendar' ); ?></strong></td>
				<td><?php echo get_option( 'time_format' ); ?></td>
			</tr>
			<tr>
				<td><strong><?php _e( 'Hide Past Events', 'the-wordpress-event-calendar' ); ?></strong></td>
				<td><?php echo isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] ? 'Yes' : 'No'; ?></td>
			</tr>
		</tbody>
	</table>
	
	<h2><?php esc_html_e( 'Event Data Sample', 'the-wordpress-event-calendar' ); ?></h2>
	<?php
	/* translators: %d: Number of events */
	?>
	<p><?php printf( esc_html__( 'Showing %d recent events:', 'the-wordpress-event-calendar' ), count( $events ) ); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e( 'Event Title', 'the-wordpress-event-calendar' ); ?></th>
				<th><?php _e( 'Start Date (Raw)', 'the-wordpress-event-calendar' ); ?></th>
				<th><?php _e( 'End Date (Raw)', 'the-wordpress-event-calendar' ); ?></th>
				<th><?php _e( 'Start Date (Parsed)', 'the-wordpress-event-calendar' ); ?></th>
				<th><?php _e( 'End Date (Parsed)', 'the-wordpress-event-calendar' ); ?></th>
				<th><?php _e( 'In Current Month?', 'the-wordpress-event-calendar' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $events ) ) : ?>
				<tr>
					<td colspan="6"><?php _e( 'No events found.', 'the-wordpress-event-calendar' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $events as $event ) : 
					$start_date_raw = get_post_meta( $event->ID, '_twec_event_start_date', true );
					$end_date_raw = get_post_meta( $event->ID, '_twec_event_end_date', true );
					$start_date_parsed = $start_date_raw ? date( 'Y-m-d H:i:s', strtotime( $start_date_raw ) ) : 'N/A';
					$end_date_parsed = $end_date_raw ? date( 'Y-m-d H:i:s', strtotime( $end_date_raw ) ) : 'N/A';
					$is_current_month = false;
					if ( $start_date_raw ) {
						$event_month = date( 'Y-m', strtotime( $start_date_raw ) );
						$is_current_month = ( $event_month === $current_month );
					}
				?>
					<tr>
						<td><strong><?php echo esc_html( $event->post_title ); ?></strong></td>
						<td><code><?php echo esc_html( $start_date_raw ? $start_date_raw : 'N/A' ); ?></code></td>
						<td><code><?php echo esc_html( $end_date_raw ? $end_date_raw : 'N/A' ); ?></code></td>
						<td><?php echo esc_html( $start_date_parsed ); ?></td>
						<td><?php echo esc_html( $end_date_parsed ); ?></td>
						<td><?php echo $is_current_month ? '<span style="color: green;">✓ ' . esc_html__( 'Yes', 'the-wordpress-event-calendar' ) . '</span>' : '<span style="color: red;">✗ ' . esc_html__( 'No', 'the-wordpress-event-calendar' ) . '</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	
	<h2><?php esc_html_e( 'Current Month Query Test', 'the-wordpress-event-calendar' ); ?></h2>
	<?php
	/* translators: %1$s: Current month (YYYY-MM), %2$d: Number of events */
	?>
	<p><?php printf( esc_html__( 'Events found for current month (%1$s): %2$d', 'the-wordpress-event-calendar' ), esc_html( $current_month ), count( $current_month_events ) ); ?></p>
	<?php if ( ! empty( $current_month_events ) ) : ?>
		<ul>
			<?php foreach ( $current_month_events as $event ) : 
				$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
			?>
				<li><strong><?php echo esc_html( $event->post_title ); ?></strong> - <?php echo esc_html( $start_date ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p style="color: red;"><?php _e( 'No events found for current month using direct query.', 'the-wordpress-event-calendar' ); ?></p>
	<?php endif; ?>
	
	<h2><?php _e( 'Test Calendar Query', 'the-wordpress-event-calendar' ); ?></h2>
	<?php
	$test_date = date( 'Y-m-d' );
	global $twec_public_instance;
	if ( ! $twec_public_instance ) {
		$twec_public_instance = new TWEC_Public();
	}
	$test_events = $twec_public_instance->get_events_for_period( 'month', $test_date );
	?>
	<?php
	/* translators: %1$s: Test date, %2$d: Number of events */
	?>
	<p><?php printf( esc_html__( 'Events found using get_events_for_period() for %1$s: %2$d', 'the-wordpress-event-calendar' ), esc_html( $test_date ), count( $test_events ) ); ?></p>
	<?php if ( ! empty( $test_events ) ) : ?>
		<ul>
			<?php foreach ( $test_events as $event ) : 
				$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
			?>
				<li><strong><?php echo esc_html( $event->post_title ); ?></strong> - <?php echo esc_html( $start_date ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p style="color: red;"><?php _e( 'No events found using get_events_for_period().', 'the-wordpress-event-calendar' ); ?></p>
	<?php endif; ?>
	
	<h2><?php _e( 'Database Query Test', 'the-wordpress-event-calendar' ); ?></h2>
	<?php
	global $wpdb;
	// Use direct query since all values are hardcoded (no user input)
	$db_events = $wpdb->get_results( "
		SELECT p.ID, p.post_title, 
			MAX(CASE WHEN pm.meta_key = '_twec_event_start_date' THEN pm.meta_value END) as start_date,
			MAX(CASE WHEN pm.meta_key = '_twec_event_end_date' THEN pm.meta_value END) as end_date
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = 'twec_event'
		AND p.post_status = 'publish'
		AND pm.meta_key IN ('_twec_event_start_date', '_twec_event_end_date')
		GROUP BY p.ID
		ORDER BY start_date DESC
		LIMIT 10
	" );
	?>
	<?php
	/* translators: %d: Number of events */
	?>
	<p><?php printf( esc_html__( 'Events found via direct database query: %d', 'the-wordpress-event-calendar' ), count( $db_events ) ); ?></p>
	<?php if ( ! empty( $db_events ) ) : ?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Event Title', 'the-wordpress-event-calendar' ); ?></th>
					<th><?php _e( 'Start Date (DB)', 'the-wordpress-event-calendar' ); ?></th>
					<th><?php _e( 'End Date (DB)', 'the-wordpress-event-calendar' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $db_events as $db_event ) : ?>
					<tr>
						<td><?php echo esc_html( $db_event->post_title ); ?></td>
						<td><code><?php echo esc_html( $db_event->start_date ); ?></code></td>
						<td><code><?php echo esc_html( $db_event->end_date ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

