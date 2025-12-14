<?php
/**
 * Pro features functionality.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Pro_Features {

	/**
	 * Initialize pro features.
	 */
	public function __construct() {
		// Featured events
		add_action( 'add_meta_boxes', array( $this, 'add_featured_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_featured_meta' ) );
		
		// Event series
		add_action( 'init', array( $this, 'register_event_series_taxonomy' ) );
		
		// Additional views
		add_filter( 'twec_calendar_views', array( $this, 'add_pro_views' ) );
		
		// Advanced widgets
		add_action( 'widgets_init', array( $this, 'register_pro_widgets' ) );
	}

	/**
	 * Add featured events meta box.
	 */
	public function add_featured_meta_box() {
		add_meta_box(
			'twec_featured',
			__( 'Featured Event', 'the-wordpress-event-calendar' ),
			array( $this, 'featured_meta_box_callback' ),
			'twec_event',
			'side',
			'default'
		);
	}

	/**
	 * Featured meta box callback.
	 */
	public function featured_meta_box_callback( $post ) {
		wp_nonce_field( 'twec_save_featured', 'twec_featured_nonce' );
		
		$is_featured = get_post_meta( $post->ID, '_twec_is_featured', true );
		?>
		<p>
			<label>
				<input type="checkbox" id="twec_is_featured" name="twec_is_featured" value="1" <?php checked( $is_featured, '1' ); ?> />
				<?php _e( 'Feature this event', 'the-wordpress-event-calendar' ); ?>
			</label>
		</p>
		<p class="description"><?php _e( 'Featured events will be highlighted in calendar and list views.', 'the-wordpress-event-calendar' ); ?></p>
		<?php
	}

	/**
	 * Save featured meta.
	 */
	public function save_featured_meta( $post_id ) {
		if ( ! isset( $_POST['twec_featured_nonce'] ) || ! wp_verify_nonce( $_POST['twec_featured_nonce'], 'twec_save_featured' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'twec_event' !== get_post_type( $post_id ) ) {
			return;
		}

		$is_featured = isset( $_POST['twec_is_featured'] ) ? '1' : '0';
		update_post_meta( $post_id, '_twec_is_featured', $is_featured );
	}

	/**
	 * Register event series taxonomy.
	 */
	public function register_event_series_taxonomy() {
		$labels = array(
			'name'              => _x( 'Event Series', 'taxonomy general name', 'the-wordpress-event-calendar' ),
			'singular_name'     => _x( 'Event Series', 'taxonomy singular name', 'the-wordpress-event-calendar' ),
			'search_items'      => __( 'Search Series', 'the-wordpress-event-calendar' ),
			'all_items'         => __( 'All Series', 'the-wordpress-event-calendar' ),
			'parent_item'       => __( 'Parent Series', 'the-wordpress-event-calendar' ),
			'parent_item_colon' => __( 'Parent Series:', 'the-wordpress-event-calendar' ),
			'edit_item'         => __( 'Edit Series', 'the-wordpress-event-calendar' ),
			'update_item'       => __( 'Update Series', 'the-wordpress-event-calendar' ),
			'add_new_item'      => __( 'Add New Series', 'the-wordpress-event-calendar' ),
			'new_item_name'     => __( 'New Series Name', 'the-wordpress-event-calendar' ),
			'menu_name'         => __( 'Series', 'the-wordpress-event-calendar' ),
		);

		register_taxonomy( 'twec_event_series', array( 'twec_event' ), array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'event-series' ),
			'show_in_rest'      => true,
		) );
	}

	/**
	 * Add pro views.
	 */
	public function add_pro_views( $views ) {
		$views['photo'] = __( 'Photo View', 'the-wordpress-event-calendar' );
		$views['map'] = __( 'Map View', 'the-wordpress-event-calendar' );
		return $views;
	}

	/**
	 * Register pro widgets.
	 */
	public function register_pro_widgets() {
		register_widget( 'TWEC_Featured_Events_Widget' );
		register_widget( 'TWEC_Event_Series_Widget' );
		register_widget( 'TWEC_Event_Countdown_Widget' );
	}
}

/**
 * Featured Events Widget.
 */
class TWEC_Featured_Events_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'twec_featured_events',
			__( 'Featured Events', 'the-wordpress-event-calendar' ),
			array( 'description' => __( 'Display featured events', 'the-wordpress-event-calendar' ) )
		);
	}

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
			'meta_query' => array(
				array(
					'key' => '_twec_is_featured',
					'value' => '1',
				),
				array(
					'key' => '_twec_event_end_date',
					'value' => current_time( 'mysql' ),
					'compare' => '>=',
					'type' => 'DATETIME',
				),
			),
			'meta_key' => '_twec_event_start_date',
			'orderby' => 'meta_value',
			'order' => 'ASC',
		) );

		if ( $events->have_posts() ) {
			echo '<ul class="twec-featured-events-widget">';
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
			echo '<p>' . __( 'No featured events.', 'the-wordpress-event-calendar' ) . '</p>';
		}

		wp_reset_postdata();
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Featured Events', 'the-wordpress-event-calendar' );
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'the-wordpress-event-calendar' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of events:', 'the-wordpress-event-calendar' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['number'] = ! empty( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
		return $instance;
	}
}

/**
 * Event Series Widget.
 */
class TWEC_Event_Series_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'twec_event_series',
			__( 'Event Series', 'the-wordpress-event-calendar' ),
			array( 'description' => __( 'Display events from a specific series', 'the-wordpress-event-calendar' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		$series_id = ! empty( $instance['series_id'] ) ? absint( $instance['series_id'] ) : 0;
		$number = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 5;

		if ( ! $series_id ) {
			return;
		}

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$events = new WP_Query( array(
			'post_type' => 'twec_event',
			'posts_per_page' => $number,
			'tax_query' => array(
				array(
					'taxonomy' => 'twec_event_series',
					'field' => 'term_id',
					'terms' => $series_id,
				),
			),
			'meta_key' => '_twec_event_start_date',
			'orderby' => 'meta_value',
			'order' => 'ASC',
		) );

		if ( $events->have_posts() ) {
			echo '<ul class="twec-series-events-widget">';
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
			echo '<p>' . __( 'No events in this series.', 'the-wordpress-event-calendar' ) . '</p>';
		}

		wp_reset_postdata();
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Event Series', 'the-wordpress-event-calendar' );
		$series_id = isset( $instance['series_id'] ) ? absint( $instance['series_id'] ) : 0;
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;

		$series = get_terms( array(
			'taxonomy' => 'twec_event_series',
			'hide_empty' => false,
		) );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'the-wordpress-event-calendar' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'series_id' ); ?>"><?php _e( 'Series:', 'the-wordpress-event-calendar' ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'series_id' ); ?>" name="<?php echo $this->get_field_name( 'series_id' ); ?>">
				<option value="0"><?php _e( 'Select Series', 'the-wordpress-event-calendar' ); ?></option>
				<?php foreach ( $series as $s ) : ?>
					<option value="<?php echo $s->term_id; ?>" <?php selected( $series_id, $s->term_id ); ?>><?php echo esc_html( $s->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of events:', 'the-wordpress-event-calendar' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['series_id'] = ! empty( $new_instance['series_id'] ) ? absint( $new_instance['series_id'] ) : 0;
		$instance['number'] = ! empty( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
		return $instance;
	}
}

/**
 * Event Countdown Widget.
 */
class TWEC_Event_Countdown_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'twec_event_countdown',
			__( 'Event Countdown', 'the-wordpress-event-calendar' ),
			array( 'description' => __( 'Display countdown to a specific event', 'the-wordpress-event-calendar' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		$event_id = ! empty( $instance['event_id'] ) ? absint( $instance['event_id'] ) : 0;

		if ( ! $event_id ) {
			return;
		}

		$event = get_post( $event_id );
		if ( ! $event || 'twec_event' !== $event->post_type ) {
			return;
		}

		$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
		if ( ! $start_date ) {
			return;
		}

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$start_timestamp = strtotime( $start_date );
		$now = current_time( 'timestamp' );
		
		if ( $start_timestamp <= $now ) {
			echo '<p>' . __( 'This event has already started.', 'the-wordpress-event-calendar' ) . '</p>';
		} else {
			?>
			<div class="twec-countdown" data-event-date="<?php echo esc_attr( date( 'Y-m-d H:i:s', $start_timestamp ) ); ?>">
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-days>0</span>
					<span class="twec-countdown-label"><?php _e( 'Days', 'the-wordpress-event-calendar' ); ?></span>
				</div>
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-hours>0</span>
					<span class="twec-countdown-label"><?php _e( 'Hours', 'the-wordpress-event-calendar' ); ?></span>
				</div>
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-minutes>0</span>
					<span class="twec-countdown-label"><?php _e( 'Minutes', 'the-wordpress-event-calendar' ); ?></span>
				</div>
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-seconds>0</span>
					<span class="twec-countdown-label"><?php _e( 'Seconds', 'the-wordpress-event-calendar' ); ?></span>
				</div>
			</div>
			<p><a href="<?php echo esc_url( get_permalink( $event_id ) ); ?>"><?php echo esc_html( $event->post_title ); ?></a></p>
			<?php
		}

		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Event Countdown', 'the-wordpress-event-calendar' );
		$event_id = isset( $instance['event_id'] ) ? absint( $instance['event_id'] ) : 0;

		$events = get_posts( array(
			'post_type' => 'twec_event',
			'posts_per_page' => -1,
			'meta_key' => '_twec_event_start_date',
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key' => '_twec_event_start_date',
					'value' => current_time( 'mysql' ),
					'compare' => '>=',
					'type' => 'DATETIME',
				),
			),
		) );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'the-wordpress-event-calendar' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'event_id' ); ?>"><?php _e( 'Event:', 'the-wordpress-event-calendar' ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'event_id' ); ?>" name="<?php echo $this->get_field_name( 'event_id' ); ?>">
				<option value="0"><?php _e( 'Select Event', 'the-wordpress-event-calendar' ); ?></option>
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo $event->ID; ?>" <?php selected( $event_id, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['event_id'] = ! empty( $new_instance['event_id'] ) ? absint( $new_instance['event_id'] ) : 0;
		return $instance;
	}
}

new TWEC_Pro_Features();

