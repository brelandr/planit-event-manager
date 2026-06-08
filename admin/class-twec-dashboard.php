<?php
/**
 * Dashboard widget: events in the current week.
 *
 * @package    The_Event_Calendar
 * @subpackage admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the PlanIt events dashboard widget.
 */
class TWEC_Dashboard {

	/**
	 * Hook dashboard setup.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Add widget for users who can edit events.
	 *
	 * @return void
	 */
	public static function register_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$pto              = 'twec_event';
		$post_type_object = get_post_type_object( $pto );
		if ( ! $post_type_object || ! $post_type_object->show_in_menu ) {
			return;
		}

		wp_add_dashboard_widget(
			'twec_dashboard_this_week',
			__( 'PlanIt: events this week', 'planit-event-manager' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Output widget: upcoming events in the next 7 days.
	 *
	 * @return void
	 */
	public static function render() {
		$start = current_time( 'mysql' );
		$end   = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days', current_time( 'timestamp' ) ) );

		$events = get_posts(
			array(
				'post_type'      => 'twec_event',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Date ordering for a small list.
				'meta_key'       => '_twec_event_start_date',
				'meta_type'      => 'DATETIME',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_twec_event_start_date',
						'value'   => $start,
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => '_twec_event_start_date',
						'value'   => $end,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		$edit = admin_url( 'edit.php?post_type=twec_event' );
		$add  = admin_url( 'post-new.php?post_type=twec_event' );

		echo '<p class="description">';
		esc_html_e( 'Events starting in the next 7 days from now.', 'planit-event-manager' );
		echo '</p>';

		if ( empty( $events ) ) {
			echo '<p><em>' . esc_html__( 'No events in this range.', 'planit-event-manager' ) . '</em></p>';
			echo '<p><a class="button button-primary" href="' . esc_url( $add ) . '">' . esc_html__( 'Add event', 'planit-event-manager' ) . '</a></p>';
			return;
		}

		echo '<ul style="list-style: disc; margin-left: 1.25em;">';
		foreach ( $events as $e ) {
			$es    = get_post_meta( $e->ID, '_twec_event_start_date', true );
			$label = $es ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $es ) : '';
			$link  = get_edit_post_link( $e->ID, 'raw' );
			echo '<li>';
			echo $label ? esc_html( $label . ' — ' ) : '';
			if ( $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $e->ID ) ) . '</a>';
			} else {
				echo esc_html( get_the_title( $e->ID ) );
			}
			echo '</li>';
		}
		echo '</ul>';
		/* translators: %s: URL to all events in admin */
		echo '<p><a href="' . esc_url( $edit ) . '">' . esc_html__( 'View all events', 'planit-event-manager' ) . '</a></p>';
	}
}
