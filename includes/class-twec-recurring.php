<?php
/**
 * Recurring events functionality.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Recurring {

	/**
	 * Initialize recurring events.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_recurring_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_recurring_meta' ) );
		add_action( 'wp', array( $this, 'generate_recurring_instances' ) );
	}

	/**
	 * Add recurring events meta box.
	 */
	public function add_recurring_meta_box() {
		add_meta_box(
			'twec_recurring',
			__( 'Recurring Event', 'the-wordpress-event-calendar' ),
			array( $this, 'recurring_meta_box_callback' ),
			'twec_event',
			'side',
			'default'
		);
	}

	/**
	 * Recurring meta box callback.
	 */
	public function recurring_meta_box_callback( $post ) {
		wp_nonce_field( 'twec_save_recurring', 'twec_recurring_nonce' );
		
		$is_recurring = get_post_meta( $post->ID, '_twec_is_recurring', true );
		$recurrence_type = get_post_meta( $post->ID, '_twec_recurrence_type', true );
		$recurrence_interval = get_post_meta( $post->ID, '_twec_recurrence_interval', true );
		$recurrence_end_date = get_post_meta( $post->ID, '_twec_recurrence_end_date', true );
		$recurrence_count = get_post_meta( $post->ID, '_twec_recurrence_count', true );
		?>
		<p>
			<label>
				<input type="checkbox" id="twec_is_recurring" name="twec_is_recurring" value="1" <?php checked( $is_recurring, '1' ); ?> />
				<?php _e( 'This is a recurring event', 'the-wordpress-event-calendar' ); ?>
			</label>
		</p>
		
		<div id="twec-recurring-options" style="<?php echo $is_recurring ? '' : 'display: none;'; ?>">
			<p>
				<label for="twec_recurrence_type"><?php _e( 'Repeat:', 'the-wordpress-event-calendar' ); ?></label>
				<select id="twec_recurrence_type" name="twec_recurrence_type">
					<option value="daily" <?php selected( $recurrence_type, 'daily' ); ?>><?php _e( 'Daily', 'the-wordpress-event-calendar' ); ?></option>
					<option value="weekly" <?php selected( $recurrence_type, 'weekly' ); ?>><?php _e( 'Weekly', 'the-wordpress-event-calendar' ); ?></option>
					<option value="monthly" <?php selected( $recurrence_type, 'monthly' ); ?>><?php _e( 'Monthly', 'the-wordpress-event-calendar' ); ?></option>
					<option value="yearly" <?php selected( $recurrence_type, 'yearly' ); ?>><?php _e( 'Yearly', 'the-wordpress-event-calendar' ); ?></option>
				</select>
			</p>
			
			<p>
				<label for="twec_recurrence_interval"><?php _e( 'Every:', 'the-wordpress-event-calendar' ); ?></label>
				<input type="number" id="twec_recurrence_interval" name="twec_recurrence_interval" value="<?php echo esc_attr( $recurrence_interval ? $recurrence_interval : 1 ); ?>" min="1" style="width: 60px;" />
				<span id="twec-recurrence-interval-text"></span>
			</p>
			
			<p>
				<label>
					<input type="radio" name="twec_recurrence_end" value="date" <?php checked( empty( $recurrence_count ), true ); ?> />
					<?php _e( 'End date:', 'the-wordpress-event-calendar' ); ?>
				</label>
				<input type="date" id="twec_recurrence_end_date" name="twec_recurrence_end_date" value="<?php echo esc_attr( $recurrence_end_date ); ?>" />
			</p>
			
			<p>
				<label>
					<input type="radio" name="twec_recurrence_end" value="count" <?php checked( ! empty( $recurrence_count ), true ); ?> />
					<?php _e( 'After', 'the-wordpress-event-calendar' ); ?>
				</label>
				<input type="number" id="twec_recurrence_count" name="twec_recurrence_count" value="<?php echo esc_attr( $recurrence_count ); ?>" min="1" style="width: 60px;" />
				<?php _e( 'occurrences', 'the-wordpress-event-calendar' ); ?>
			</p>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#twec_is_recurring').on('change', function() {
				$('#twec-recurring-options').toggle($(this).is(':checked'));
			});
			
			$('#twec_recurrence_type').on('change', function() {
				var type = $(this).val();
				var text = type === 'daily' ? 'day(s)' : (type === 'weekly' ? 'week(s)' : (type === 'monthly' ? 'month(s)' : 'year(s)'));
				$('#twec-recurrence-interval-text').text(text);
			}).trigger('change');
		});
		</script>
		<?php
	}

	/**
	 * Save recurring meta data.
	 */
	public function save_recurring_meta( $post_id ) {
		if ( ! isset( $_POST['twec_recurring_nonce'] ) || ! wp_verify_nonce( $_POST['twec_recurring_nonce'], 'twec_save_recurring' ) ) {
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

		$is_recurring = isset( $_POST['twec_is_recurring'] ) ? '1' : '0';
		update_post_meta( $post_id, '_twec_is_recurring', $is_recurring );

		if ( $is_recurring ) {
			if ( isset( $_POST['twec_recurrence_type'] ) ) {
				update_post_meta( $post_id, '_twec_recurrence_type', sanitize_text_field( $_POST['twec_recurrence_type'] ) );
			}
			if ( isset( $_POST['twec_recurrence_interval'] ) ) {
				update_post_meta( $post_id, '_twec_recurrence_interval', intval( $_POST['twec_recurrence_interval'] ) );
			}
			if ( isset( $_POST['twec_recurrence_end'] ) && $_POST['twec_recurrence_end'] === 'date' ) {
				if ( isset( $_POST['twec_recurrence_end_date'] ) ) {
					update_post_meta( $post_id, '_twec_recurrence_end_date', sanitize_text_field( $_POST['twec_recurrence_end_date'] ) );
				}
				delete_post_meta( $post_id, '_twec_recurrence_count' );
			} elseif ( isset( $_POST['twec_recurrence_end'] ) && $_POST['twec_recurrence_end'] === 'count' ) {
				if ( isset( $_POST['twec_recurrence_count'] ) ) {
					update_post_meta( $post_id, '_twec_recurrence_count', intval( $_POST['twec_recurrence_count'] ) );
				}
				delete_post_meta( $post_id, '_twec_recurrence_end_date' );
			}
		} else {
			delete_post_meta( $post_id, '_twec_recurrence_type' );
			delete_post_meta( $post_id, '_twec_recurrence_interval' );
			delete_post_meta( $post_id, '_twec_recurrence_end_date' );
			delete_post_meta( $post_id, '_twec_recurrence_count' );
		}
	}

	/**
	 * Generate recurring event instances.
	 */
	public function generate_recurring_instances() {
		// This would generate instances on-the-fly or store them
		// For now, we'll handle this in the query
	}

	/**
	 * Get recurring instances for an event.
	 */
	public static function get_recurring_instances( $event_id, $start_date = null, $end_date = null ) {
		$is_recurring = get_post_meta( $event_id, '_twec_is_recurring', true );
		if ( ! $is_recurring ) {
			return array();
		}

		$event = get_post( $event_id );
		$base_start = get_post_meta( $event_id, '_twec_event_start_date', true );
		$base_end = get_post_meta( $event_id, '_twec_event_end_date', true );
		$recurrence_type = get_post_meta( $event_id, '_twec_recurrence_type', true );
		$recurrence_interval = get_post_meta( $event_id, '_twec_recurrence_interval', true ) ?: 1;
		$recurrence_end_date = get_post_meta( $event_id, '_twec_recurrence_end_date', true );
		$recurrence_count = get_post_meta( $event_id, '_twec_recurrence_count', true );

		if ( ! $base_start ) {
			return array();
		}

		$instances = array();
		$current = new DateTime( $base_start );
		$end = $recurrence_end_date ? new DateTime( $recurrence_end_date ) : null;
		$count = 0;
		$max_count = $recurrence_count ? intval( $recurrence_count ) : null;

		$start_range = $start_date ? new DateTime( $start_date ) : null;
		$end_range = $end_date ? new DateTime( $end_date ) : null;

		while ( true ) {
			if ( $end && $current > $end ) {
				break;
			}
			if ( $max_count && $count >= $max_count ) {
				break;
			}

			// Check if this instance is in the requested range
			if ( $start_range && $current < $start_range ) {
				// Skip to start range
				$current = self::get_next_occurrence( $current, $recurrence_type, $recurrence_interval, $start_range );
				continue;
			}
			if ( $end_range && $current > $end_range ) {
				break;
			}

			$instance_start = clone $current;
			$duration = strtotime( $base_end ) - strtotime( $base_start );
			$instance_end = clone $current;
			$instance_end->modify( '+' . $duration . ' seconds' );

			$instances[] = array(
				'start' => $instance_start->format( 'Y-m-d H:i:s' ),
				'end' => $instance_end->format( 'Y-m-d H:i:s' ),
			);

			$current = self::get_next_occurrence( $current, $recurrence_type, $recurrence_interval );
			$count++;
		}

		return $instances;
	}

	/**
	 * Get next occurrence date.
	 */
	private static function get_next_occurrence( $current, $type, $interval, $min_date = null ) {
		$next = clone $current;
		
		switch ( $type ) {
			case 'daily':
				$next->modify( "+$interval days" );
				break;
			case 'weekly':
				$next->modify( "+$interval weeks" );
				break;
			case 'monthly':
				$next->modify( "+$interval months" );
				break;
			case 'yearly':
				$next->modify( "+$interval years" );
				break;
		}

		if ( $min_date && $next < $min_date ) {
			// Calculate how many intervals needed to reach min_date
			$diff = $current->diff( $min_date );
			$days = $diff->days;
			$intervals_needed = ceil( $days / $interval );
			$next = clone $current;
			
			switch ( $type ) {
				case 'daily':
					$next->modify( "+" . ( $intervals_needed * $interval ) . " days" );
					break;
				case 'weekly':
					$next->modify( "+" . ( $intervals_needed * $interval ) . " weeks" );
					break;
				case 'monthly':
					$next->modify( "+" . ( $intervals_needed * $interval ) . " months" );
					break;
				case 'yearly':
					$next->modify( "+" . ( $intervals_needed * $interval ) . " years" );
					break;
			}
		}

		return $next;
	}
}

new TWEC_Recurring();

