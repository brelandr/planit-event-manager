<?php
/**
 * Widget for upcoming events.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'twec_widget',
			__( 'Upcoming Events', 'the-wordpress-event-calendar' ),
			array( 'description' => __( 'Display upcoming events', 'the-wordpress-event-calendar' ) )
		);
	}

	/**
	 * Front-end display of widget.
	 */
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		$number = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 5;

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$events = new WP_Query( array(
			'post_type' => 'twec_event',
			'posts_per_page' => $number,
			'meta_key' => '_twec_event_start_date',
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key' => '_twec_event_end_date',
					'value' => current_time( 'mysql' ),
					'compare' => '>=',
					'type' => 'DATETIME',
				),
			),
		) );

		if ( $events->have_posts() ) {
			echo '<ul class="twec-widget-events">';
			while ( $events->have_posts() ) {
				$events->the_post();
				$start_date = get_post_meta( get_the_ID(), '_twec_event_start_date', true );
				echo '<li>';
				echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a>';
				if ( $start_date ) {
					echo '<span class="twec-widget-date">' . date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . __( 'No upcoming events.', 'the-wordpress-event-calendar' ) . '</p>';
		}

		wp_reset_postdata();
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Events', 'the-wordpress-event-calendar' );
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'the-wordpress-event-calendar' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of events to show:', 'the-wordpress-event-calendar' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3">
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['number'] = ! empty( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
		return $instance;
	}
}

// Register widget
function twec_register_widget() {
	register_widget( 'TWEC_Widget' );
}
add_action( 'widgets_init', 'twec_register_widget' );

