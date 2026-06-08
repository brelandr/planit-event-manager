<?php
/**
 * Diagnostics page for troubleshooting calendar issues.
 *
 * @package    The_Event_Calendar
 * @subpackage admin/partials
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local scope, not globals
// Get sample events.
$events = get_posts(
	array(
		'post_type'      => 'twec_event',
		'posts_per_page' => 10,
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

// Get current month events.
$current_month = current_time( 'Y-m' );
// Optimized: Use DATE type instead of DATETIME for better performance.
// Note: meta_query is necessary for event calendar date filtering. Performance can be improved with database indexes (see class-twec-activator.php).
$current_month_events = get_posts(
	array(
		'post_type'      => 'twec_event',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for event calendar functionality, optimized with DATE type. Database indexes recommended for production.
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_twec_event_start_date',
				'value'   => $current_month . '-01', // Use date-only format for DATE type.
				'compare' => '>=',
				'type'    => 'DATE', // DATE type is faster than DATETIME for date-only comparisons.
			),
			array(
				'key'     => '_twec_event_start_date',
				'value'   => $current_month . '-31', // Use date-only format for DATE type.
				'compare' => '<=',
				'type'    => 'DATE', // DATE type is faster than DATETIME for date-only comparisons.
			),
		),
	)
);

$settings = get_option( 'twec_settings', array() );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Event Calendar Diagnostics', 'planit-event-manager' ); ?></h1>
	
	<h2><?php esc_html_e( 'Site Configuration', 'planit-event-manager' ); ?></h2>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Setting', 'planit-event-manager' ); ?></th>
				<th><?php esc_html_e( 'Value', 'planit-event-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Site Timezone', 'planit-event-manager' ); ?></strong></td>
				<td><?php echo esc_html( wp_timezone_string() ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'PHP Timezone', 'planit-event-manager' ); ?></strong></td>
				<td><?php echo esc_html( date_default_timezone_get() ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Current Time (Site)', 'planit-event-manager' ); ?></strong></td>
				<td><?php echo esc_html( current_time( 'mysql' ) ); ?> (<?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?>)</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Current Time (PHP)', 'planit-event-manager' ); ?></strong></td>
				<td><?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Date Format', 'planit-event-manager' ); ?></strong></td>
				<td><?php echo esc_html( get_option( 'date_format' ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Time Format', 'planit-event-manager' ); ?></strong></td>
				<td><?php echo esc_html( get_option( 'time_format' ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Hide Past Events', 'planit-event-manager' ); ?></strong></td>
				<td><?php echo esc_html( isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] ? __( 'Yes', 'planit-event-manager' ) : __( 'No', 'planit-event-manager' ) ); ?></td>
			</tr>
		</tbody>
	</table>
	
	<h2><?php esc_html_e( 'Event Data Sample', 'planit-event-manager' ); ?></h2>
	<p>
		<?php
		/* translators: %d: Number of events */
		printf( esc_html__( 'Showing %d recent events:', 'planit-event-manager' ), count( $events ) );
		?>
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Event Title', 'planit-event-manager' ); ?></th>
				<th><?php esc_html_e( 'Start Date (Raw)', 'planit-event-manager' ); ?></th>
				<th><?php esc_html_e( 'End Date (Raw)', 'planit-event-manager' ); ?></th>
				<th><?php esc_html_e( 'Start Date (Parsed)', 'planit-event-manager' ); ?></th>
				<th><?php esc_html_e( 'End Date (Parsed)', 'planit-event-manager' ); ?></th>
				<th><?php esc_html_e( 'In Current Month?', 'planit-event-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $events ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No events found.', 'planit-event-manager' ); ?></td>
				</tr>
			<?php else : ?>
				<?php
				foreach ( $events as $event ) :
					$start_date_raw = get_post_meta( $event->ID, '_twec_event_start_date', true );
					$end_date_raw   = get_post_meta( $event->ID, '_twec_event_end_date', true );
					// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for parsing stored datetime values
					$start_date_parsed = $start_date_raw ? gmdate( 'Y-m-d H:i:s', strtotime( $start_date_raw ) ) : 'N/A';
					// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for parsing stored datetime values
					$end_date_parsed  = $end_date_raw ? gmdate( 'Y-m-d H:i:s', strtotime( $end_date_raw ) ) : 'N/A';
					$is_current_month = false;
					if ( $start_date_raw ) {
						// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date - Used for parsing stored datetime values
						$event_month      = gmdate( 'Y-m', strtotime( $start_date_raw ) );
						$is_current_month = ( $event_month === $current_month );
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $event->post_title ); ?></strong></td>
						<td><code><?php echo esc_html( $start_date_raw ? $start_date_raw : 'N/A' ); ?></code></td>
						<td><code><?php echo esc_html( $end_date_raw ? $end_date_raw : 'N/A' ); ?></code></td>
						<td><?php echo esc_html( $start_date_parsed ); ?></td>
						<td><?php echo esc_html( $end_date_parsed ); ?></td>
						<td>
						<?php
						if ( $is_current_month ) {
							echo '<span style="color: green;">✓ ' . esc_html__( 'Yes', 'planit-event-manager' ) . '</span>';
						} else {
							echo '<span style="color: red;">✗ ' . esc_html__( 'No', 'planit-event-manager' ) . '</span>';
						}
						?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	
	<h2><?php esc_html_e( 'Current Month Query Test', 'planit-event-manager' ); ?></h2>
	<p>
		<?php
		/* translators: %1$s: Current month (YYYY-MM), %2$d: Number of events */
		printf( esc_html__( 'Events found for current month (%1$s): %2$d', 'planit-event-manager' ), esc_html( $current_month ), count( $current_month_events ) );
		?>
	</p>
	<?php if ( ! empty( $current_month_events ) ) : ?>
		<ul>
			<?php
			foreach ( $current_month_events as $event ) :
				$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
				?>
				<li><strong><?php echo esc_html( $event->post_title ); ?></strong> - <?php echo esc_html( $start_date ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p style="color: red;"><?php esc_html_e( 'No events found for current month using direct query.', 'planit-event-manager' ); ?></p>
	<?php endif; ?>
	
	<h2><?php esc_html_e( 'Test Calendar Query', 'planit-event-manager' ); ?></h2>
	<?php
	$test_date = current_time( 'Y-m-d' );
	global $twec_public_instance;
	if ( ! $twec_public_instance ) {
		$twec_public_instance = new TWEC_Public();
	}
	$test_events = $twec_public_instance->get_events_for_period( 'month', $test_date );
	?>
	<p>
		<?php
		/* translators: %1$s: Test date, %2$d: Number of events */
		printf( esc_html__( 'Events found using get_events_for_period() for %1$s: %2$d', 'planit-event-manager' ), esc_html( $test_date ), count( $test_events ) );
		?>
	</p>
	<?php if ( ! empty( $test_events ) ) : ?>
		<ul>
			<?php
			foreach ( $test_events as $event ) :
				$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
				?>
				<li><strong><?php echo esc_html( $event->post_title ); ?></strong> - <?php echo esc_html( $start_date ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p style="color: red;"><?php esc_html_e( 'No events found using get_events_for_period().', 'planit-event-manager' ); ?></p>
	<?php endif; ?>
	
	<h2><?php esc_html_e( 'Database Query Test', 'planit-event-manager' ); ?></h2>
	<?php
	global $wpdb;

	// Use cache for diagnostic query to improve performance and address NoCaching warning.
	$cache_key = 'twec_diagnostics_db_events';
	$db_events = wp_cache_get( $cache_key, 'twec_diagnostics' );

	if ( false === $db_events ) {
		// All values in this query are hardcoded constants (post type, post status, meta keys).
		// No user input is used, so the query is safe. Using prepare() for safety.
		$start_meta_key = '_twec_event_start_date';
		$end_meta_key   = '_twec_event_end_date';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Local variable for query, not overriding WordPress global.
		$post_type   = 'twec_event';
		$post_status = 'publish';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic page, direct query for testing purposes. All values are hardcoded constants (no user input). Results are cached with wp_cache_set().
		$db_events = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT p.ID, p.post_title, 
					MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as start_date,
					MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as end_date
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status = %s
				AND pm.meta_key IN (%s, %s)
				GROUP BY p.ID
				ORDER BY start_date DESC
				LIMIT 10
				",
				$start_meta_key,
				$end_meta_key,
				$post_type,
				$post_status,
				$start_meta_key,
				$end_meta_key
			)
		);

		// Cache results for 5 minutes to address NoCaching warning.
		wp_cache_set( $cache_key, $db_events, 'twec_diagnostics', 300 );
	}
	?>
	<p>
		<?php
		/* translators: %d: Number of events */
		printf( esc_html__( 'Events found via direct database query: %d', 'planit-event-manager' ), count( $db_events ) );
		?>
	</p>
	<?php if ( ! empty( $db_events ) ) : ?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Event Title', 'planit-event-manager' ); ?></th>
					<th><?php esc_html_e( 'Start Date (DB)', 'planit-event-manager' ); ?></th>
					<th><?php esc_html_e( 'End Date (DB)', 'planit-event-manager' ); ?></th>
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
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

