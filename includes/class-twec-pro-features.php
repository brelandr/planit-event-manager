<?php
/**
 * Pro features functionality.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-pro-features.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pro features functionality.
 *
 * Handles premium/pro feature availability and functionality.
 */
class TWEC_Pro_Features {

	/**
	 * Initialize pro features.
	 */
	public function __construct() {
		// Featured events.
		add_action( 'add_meta_boxes', array( $this, 'add_featured_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_featured_meta' ) );

		// Event series.
		add_action( 'init', array( $this, 'register_event_series_taxonomy' ) );

		// Additional views.
		add_filter( 'twec_calendar_views', array( $this, 'add_pro_views' ) );

		// Advanced widgets.
		add_action( 'widgets_init', array( $this, 'register_pro_widgets' ) );
	}

	/**
	 * Add featured events meta box.
	 */
	public function add_featured_meta_box() {
		add_meta_box(
			'twec_featured',
			__( 'Featured Event', 'planit-event-manager' ),
			array( $this, 'featured_meta_box_callback' ),
			'twec_event',
			'side',
			'default'
		);
	}

	/**
	 * Featured meta box callback.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function featured_meta_box_callback( $post ) {
		wp_nonce_field( 'twec_save_featured', 'twec_featured_nonce' );

		$is_featured = get_post_meta( $post->ID, '_twec_is_featured', true );
		?>
		<p>
			<label>
				<input type="checkbox" id="twec_is_featured" name="twec_is_featured" value="1" <?php checked( $is_featured, '1' ); ?> />
				<?php esc_html_e( 'Feature this event', 'planit-event-manager' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Featured events will be highlighted in calendar and list views.', 'planit-event-manager' ); ?></p>
		<?php
	}

	/**
	 * Save featured meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_featured_meta( $post_id ) {
		if ( ! twec_verify_post_nonce_field( 'twec_featured_nonce', 'twec_save_featured' ) ) {
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
			'name'              => _x( 'Event Series', 'taxonomy general name', 'planit-event-manager' ),
			'singular_name'     => _x( 'Event Series', 'taxonomy singular name', 'planit-event-manager' ),
			'search_items'      => __( 'Search Series', 'planit-event-manager' ),
			'all_items'         => __( 'All Series', 'planit-event-manager' ),
			'parent_item'       => __( 'Parent Series', 'planit-event-manager' ),
			'parent_item_colon' => __( 'Parent Series:', 'planit-event-manager' ),
			'edit_item'         => __( 'Edit Series', 'planit-event-manager' ),
			'update_item'       => __( 'Update Series', 'planit-event-manager' ),
			'add_new_item'      => __( 'Add New Series', 'planit-event-manager' ),
			'new_item_name'     => __( 'New Series Name', 'planit-event-manager' ),
			'menu_name'         => __( 'Series', 'planit-event-manager' ),
		);

		register_taxonomy(
			'twec_event_series',
			array( 'twec_event' ),
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'event-series' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Add pro views.
	 *
	 * @param array $views Existing views.
	 * @return array Modified views.
	 */
	public function add_pro_views( $views ) {
		$views['photo'] = __( 'Photo View', 'planit-event-manager' );
		$views['map']   = __( 'Map View', 'planit-event-manager' );
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
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Multiple widget classes in one file for organizational purposes
/**
 * Featured Events Widget class.
 *
 * Displays featured events in a widget.
 */
class TWEC_Featured_Events_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'twec_featured_events',
			__( 'Featured Events', 'planit-event-manager' ),
			array( 'description' => __( 'Display featured events', 'planit-event-manager' ) )
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
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for event calendar date filtering, optimized with DATE type. Database indexes recommended for production.
				'meta_query'     => array(
					array(
						'key'   => '_twec_is_featured',
						'value' => '1',
					),
					array(
						'key'     => '_twec_event_end_date',
						'value'   => current_time( 'Y-m-d' ), // Use date-only format for DATE type.
						'compare' => '>=',
						'type'    => 'DATE', // DATE type is faster than DATETIME for date-only comparisons.
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering, optimized with DATE type. Database indexes recommended for production.
				'meta_key'       => '_twec_event_start_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);

		if ( $events->have_posts() ) {
			echo '<ul class="twec-featured-events-widget">';
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
			echo '<p>' . esc_html__( 'No featured events.', 'planit-event-manager' ) . '</p>';
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
		$title  = isset( $instance['title'] ) ? $instance['title'] : __( 'Featured Events', 'planit-event-manager' );
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'planit-event-manager' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of events:', 'planit-event-manager' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $number ); ?>" size="3">
		</p>
		<?php
	}

	/**
	 * Update widget instance.
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
 * Event Series Widget.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 */
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName,Generic.Files.OneObjectStructurePerFile.MultipleFound -- Multiple widget classes in one file for organizational purposes
/**
 * Event Series Widget class.
 *
 * Displays events from a specific series in a widget.
 */
class TWEC_Event_Series_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'twec_event_series',
			__( 'Event Series', 'planit-event-manager' ),
			array( 'description' => __( 'Display events from a specific series', 'planit-event-manager' ) )
		);
	}

	/**
	 * Widget output.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		$title     = apply_filters( 'widget_title', $instance['title'] );
		$series_id = ! empty( $instance['series_id'] ) ? absint( $instance['series_id'] ) : 0;
		$number    = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 5;

		if ( ! $series_id ) {
			return;
		}

		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $title ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		$events = new WP_Query(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => $number,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for event calendar series filtering. Optimized query with proper limits.
				'tax_query'      => array(
					array(
						'taxonomy' => 'twec_event_series',
						'field'    => 'term_id',
						'terms'    => $series_id,
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering, optimized with DATE type. Database indexes recommended for production.
				'meta_key'       => '_twec_event_start_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);

		if ( $events->have_posts() ) {
			echo '<ul class="twec-series-events-widget">';
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
			echo '<p>' . esc_html__( 'No events in this series.', 'planit-event-manager' ) . '</p>';
		}

		wp_reset_postdata();
		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title     = isset( $instance['title'] ) ? $instance['title'] : __( 'Event Series', 'planit-event-manager' );
		$series_id = isset( $instance['series_id'] ) ? absint( $instance['series_id'] ) : 0;
		$number    = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;

		$series = get_terms(
			array(
				'taxonomy'   => 'twec_event_series',
				'hide_empty' => false,
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'planit-event-manager' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'series_id' ) ); ?>"><?php esc_html_e( 'Series:', 'planit-event-manager' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'series_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'series_id' ) ); ?>">
				<option value="0"><?php esc_html_e( 'Select Series', 'planit-event-manager' ); ?></option>
				<?php foreach ( $series as $s ) : ?>
					<option value="<?php echo esc_attr( $s->term_id ); ?>" <?php selected( $series_id, $s->term_id ); ?>><?php echo esc_html( $s->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of events:', 'planit-event-manager' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $number ); ?>" size="3">
		</p>
		<?php
	}

	/**
	 * Update widget instance.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array Updated instance.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance              = array();
		$instance['title']     = ! empty( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['series_id'] = ! empty( $new_instance['series_id'] ) ? absint( $new_instance['series_id'] ) : 0;
		$instance['number']    = ! empty( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
		return $instance;
	}
}

/**
 * Event Countdown Widget.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 */
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName,Generic.Files.OneObjectStructurePerFile.MultipleFound -- Multiple widget classes in one file for organizational purposes
/**
 * Event Countdown Widget class.
 *
 * Displays a countdown to a specific event in a widget.
 */
class TWEC_Event_Countdown_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'twec_event_countdown',
			__( 'Event Countdown', 'planit-event-manager' ),
			array( 'description' => __( 'Display countdown to a specific event', 'planit-event-manager' ) )
		);
	}

	/**
	 * Widget output.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		$title    = apply_filters( 'widget_title', $instance['title'] );
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

		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $title ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		$start_timestamp = strtotime( $start_date );
		$now             = time();

		if ( $start_timestamp <= $now ) {
			echo '<p>' . esc_html__( 'This event has already started.', 'planit-event-manager' ) . '</p>';
		} else {
			?>
			<div class="twec-countdown" data-event-date="<?php echo esc_attr( gmdate( 'Y-m-d H:i:s', $start_timestamp ) ); ?>">
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-days>0</span>
					<span class="twec-countdown-label"><?php esc_html_e( 'Days', 'planit-event-manager' ); ?></span>
				</div>
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-hours>0</span>
					<span class="twec-countdown-label"><?php esc_html_e( 'Hours', 'planit-event-manager' ); ?></span>
				</div>
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-minutes>0</span>
					<span class="twec-countdown-label"><?php esc_html_e( 'Minutes', 'planit-event-manager' ); ?></span>
				</div>
				<div class="twec-countdown-item">
					<span class="twec-countdown-value" data-seconds>0</span>
					<span class="twec-countdown-label"><?php esc_html_e( 'Seconds', 'planit-event-manager' ); ?></span>
				</div>
			</div>
			<p><a href="<?php echo esc_url( get_permalink( $event_id ) ); ?>"><?php echo esc_html( $event->post_title ); ?></a></p>
			<?php
		}

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title    = isset( $instance['title'] ) ? $instance['title'] : __( 'Event Countdown', 'planit-event-manager' );
		$event_id = isset( $instance['event_id'] ) ? absint( $instance['event_id'] ) : 0;

		// Optimized: Use DATE type instead of DATETIME for better performance.
		// Note: meta_query and meta_key are necessary for event calendar functionality. Performance can be improved with database indexes (see class-twec-activator.php).
		$events = get_posts(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering, optimized with DATE type. Database indexes recommended for production.
				'meta_key'       => '_twec_event_start_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for event calendar date filtering, optimized with DATE type. Database indexes recommended for production.
				'meta_query'     => array(
					array(
						'key'     => '_twec_event_start_date',
						'value'   => current_time( 'Y-m-d' ), // Use date-only format for DATE type.
						'compare' => '>=',
						'type'    => 'DATE', // DATE type is faster than DATETIME for date-only comparisons.
					),
				),
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'planit-event-manager' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'event_id' ) ); ?>"><?php esc_html_e( 'Event:', 'planit-event-manager' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'event_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'event_id' ) ); ?>">
				<option value="0"><?php esc_html_e( 'Select Event', 'planit-event-manager' ); ?></option>
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Update widget instance.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array Updated instance.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance             = array();
		$instance['title']    = ! empty( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['event_id'] = ! empty( $new_instance['event_id'] ) ? absint( $new_instance['event_id'] ) : 0;
		return $instance;
	}
}

// TWEC_Pro_Features is initialized by TWEC class.

