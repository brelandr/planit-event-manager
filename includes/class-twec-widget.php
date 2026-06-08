<?php
/**
 * Widget for upcoming events.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-widget.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget for upcoming events.
 *
 * Displays a list of upcoming events in a WordPress widget.
 */
class TWEC_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'twec_widget',
			__( 'Upcoming Events', 'planit-event-manager' ),
			array( 'description' => __( 'Display upcoming events', 'planit-event-manager' ) )
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		$title  = apply_filters( 'widget_title', $instance['title'] );
		$number = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 5;

		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $title ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		// Optimized: Use DATE type instead of DATETIME for better performance.
		// Note: meta_query and meta_key are necessary for event calendar functionality. Performance can be improved with database indexes (see class-twec-activator.php).
		$events = new WP_Query(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => $number,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering, optimized with DATE type. Database indexes recommended for production.
				'meta_key'       => '_twec_event_start_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for event calendar date filtering, optimized with DATE type. Database indexes recommended for production.
				'meta_query'     => array(
					array(
						'key'     => '_twec_event_end_date',
						'value'   => current_time( 'Y-m-d' ), // Use date-only format for DATE type.
						'compare' => '>=',
						'type'    => 'DATE', // DATE type is faster than DATETIME for date-only comparisons.
					),
				),
			)
		);

		if ( $events->have_posts() ) {
			echo '<ul class="twec-widget-events">';
			while ( $events->have_posts() ) {
				$events->the_post();
				$start_date = get_post_meta( get_the_ID(), '_twec_event_start_date', true );
				echo '<li>';
				echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
				if ( $start_date ) {
					echo '<span class="twec-widget-date">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'No upcoming events.', 'planit-event-manager' ) . '</p>';
		}

		wp_reset_postdata();
		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title  = isset( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Events', 'planit-event-manager' );
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'planit-event-manager' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of events to show:', 'planit-event-manager' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $number ); ?>" size="3">
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array Updated instance.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance           = array();
		$instance['title']  = ! empty( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['number'] = ! empty( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
		return $instance;
	}
}

/**
 * Register widget with WordPress.
 *
 * This function registers the TWEC_Widget class with WordPress.
 * It is hooked into the widgets_init action.
 *
 * @since 1.0.0
 * @return void
 */
// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Widget registration function is standard WordPress pattern
// phpcs:ignore Squiz.Commenting.FunctionComment.Missing -- Function has doc comment above.
function twec_register_widget() {
	register_widget( 'TWEC_Widget' );
}
add_action( 'widgets_init', 'twec_register_widget' );

