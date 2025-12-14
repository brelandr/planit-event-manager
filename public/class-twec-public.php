<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage public
 */
class TWEC_Public {

	/**
	 * Enqueue styles for the public-facing side of the site.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'twec-public',
			TWEC_PLUGIN_URL . 'public/css/twec-public.css',
			array(),
			TWEC_VERSION,
			'all'
		);
	}

	/**
	 * Enqueue scripts for the public-facing side of the site.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'twec-public',
			TWEC_PLUGIN_URL . 'public/js/twec-public.js',
			array( 'jquery' ),
			TWEC_VERSION,
			true
		);

		$settings = get_option( 'twec_settings', array() );
		$google_maps_api_key = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';
		
		wp_localize_script( 'twec-public', 'twecData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'twec_nonce' ),
			'googleMapsApiKey' => $google_maps_api_key,
			'upgradeUrl' => TWEC_Premium::UPGRADE_URL,
		) );

		if ( ! empty( $google_maps_api_key ) ) {
			wp_enqueue_script(
				'google-maps',
				'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $google_maps_api_key ),
				array(),
				null,
				true
			);
		}
	}

	/**
	 * Load custom template for events.
	 */
	public function event_template( $template ) {
		if ( is_singular( 'twec_event' ) ) {
			$custom_template = TWEC_PLUGIN_DIR . 'public/partials/twec-single-event.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		} elseif ( is_post_type_archive( 'twec_event' ) ) {
			$custom_template = TWEC_PLUGIN_DIR . 'public/partials/twec-archive-events.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}

	/**
	 * Modify event query to hide past events if setting is enabled.
	 */
	public function modify_event_query( $query ) {
		if ( ! is_admin() && $query->is_main_query() && ( is_post_type_archive( 'twec_event' ) || is_tax( 'twec_event_category' ) || is_tax( 'twec_event_tag' ) ) ) {
			$settings = get_option( 'twec_settings', array() );
			
			// Handle category filter
			if ( isset( $_GET['event_category'] ) && ! empty( $_GET['event_category'] ) ) {
				$query->set( 'tax_query', array(
					array(
						'taxonomy' => 'twec_event_category',
						'field' => 'slug',
						'terms' => sanitize_text_field( $_GET['event_category'] ),
					),
				) );
			}
			
			if ( isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] ) {
				$meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}
				$meta_query[] = array(
					'key' => '_twec_event_end_date',
					'value' => current_time( 'mysql' ),
					'compare' => '>=',
					'type' => 'DATETIME',
				);
				$query->set( 'meta_query', $meta_query );
				$query->set( 'meta_key', '_twec_event_end_date' );
				$query->set( 'orderby', 'meta_value' );
				$query->set( 'order', 'ASC' );
			}
		}
	}

	/**
	 * Handle calendar AJAX request.
	 */
	public function ajax_get_calendar() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'twec_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'the-wordpress-event-calendar' ) ) );
			return;
		}

		$view = isset( $_POST['view'] ) ? sanitize_text_field( $_POST['view'] ) : 'month';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : current_time( 'Y-m-d' );

		try {
			$events = $this->get_events_for_period( $view, $date );
			$calendar_html = $this->render_calendar_view( $view, $date );
			$title = $this->get_calendar_title( $view, $date );

			wp_send_json_success( array(
				'html' => $calendar_html,
				'title' => $title,
				'events' => $events,
				'debug' => array(
					'view' => $view,
					'date' => $date,
					'events_count' => count( $events ),
				),
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * Render calendar view HTML.
	 */
	public function render_calendar_view( $view, $date ) {
		$events = $this->get_events_for_period( $view, $date );
		$date_obj = new DateTime( $date );
		
		// Debug: Log events being passed to render
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'TWEC render_calendar_view: View=' . $view . ', Date=' . $date . ', Events=' . count( $events ) );
		}

		// Check for premium views - fallback to month view without showing upgrade notice
		$premium_views = array( 'week', 'year', 'photo', 'map' );
		if ( in_array( $view, $premium_views, true ) && ! TWEC_Premium::is_available( $view ) ) {
			// Silently fallback to month view for better UX
			return $this->render_month_view( $date_obj, $events );
		}

		switch ( $view ) {
			case 'day':
				return $this->render_day_view( $date_obj, $events );
			case 'week':
				if ( TWEC_Premium::is_available( 'week' ) ) {
					return $this->render_week_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice
				return $this->render_month_view( $date_obj, $events );
			case 'month':
				return $this->render_month_view( $date_obj, $events );
			case 'year':
				if ( TWEC_Premium::is_available( 'year' ) ) {
					return $this->render_year_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice
				return $this->render_month_view( $date_obj, $events );
			case 'photo':
				if ( TWEC_Premium::is_available( 'photo' ) ) {
					return $this->render_photo_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice
				return $this->render_month_view( $date_obj, $events );
			case 'map':
				if ( TWEC_Premium::is_available( 'map' ) ) {
					return $this->render_map_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice
				return $this->render_month_view( $date_obj, $events );
			default:
				return $this->render_month_view( $date_obj, $events );
		}
	}

	/**
	 * Render month view.
	 */
	private function render_month_view( $date, $events ) {
		$year = $date->format( 'Y' );
		$month = $date->format( 'm' );
		$first_day = new DateTime( "$year-$month-01" );
		$last_day = clone $first_day;
		$last_day->modify( 'last day of this month' );
		
		$start_date = clone $first_day;
		$start_date->modify( 'monday this week' );
		
		$end_date = clone $last_day;
		$end_date->modify( 'sunday this week' );
		
		$html = '<table class="twec-calendar-month">';
		$html .= '<thead><tr>';
		$days = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
		foreach ( $days as $day ) {
			$html .= '<th>' . esc_html( $day ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		
		$current = clone $start_date;
		while ( $current <= $end_date ) {
			if ( $current->format( 'w' ) == 0 ) {
				$html .= '<tr>';
			}
			
			$is_other_month = $current->format( 'm' ) != $month;
			$is_today = $current->format( 'Y-m-d' ) === current_time( 'Y-m-d' );
			$day_class = 'twec-calendar-day';
			if ( $is_other_month ) {
				$day_class .= ' other-month';
			}
			if ( $is_today ) {
				$day_class .= ' today';
			}
			
			$html .= '<td class="' . $day_class . '">';
			$html .= '<div class="twec-calendar-day">';
			$html .= '<div class="twec-calendar-day-number">' . $current->format( 'j' ) . '</div>';
			
			$day_events = $this->get_events_for_day( $current->format( 'Y-m-d' ), $events );
			if ( ! empty( $day_events ) ) {
				foreach ( $day_events as $event ) {
					$event_title = get_the_title( $event->ID );
					$event_url = get_permalink( $event->ID );
					$html .= '<a href="' . esc_url( $event_url ) . '" class="twec-calendar-event" data-url="' . esc_url( $event_url ) . '" title="' . esc_attr( $event_title ) . '">';
					$html .= esc_html( mb_substr( $event_title, 0, 30 ) . ( mb_strlen( $event_title ) > 30 ? '...' : '' ) );
					$html .= '</a>';
				}
			}
			
			$html .= '</div></td>';
			
			if ( $current->format( 'w' ) == 6 ) {
				$html .= '</tr>';
			}
			
			$current->modify( '+1 day' );
		}
		
		$html .= '</tbody></table>';
		return $html;
	}

	/**
	 * Render week view.
	 */
	private function render_week_view( $date, $events ) {
		$start_of_week = clone $date;
		$start_of_week->modify( 'monday this week' );
		$end_of_week = clone $start_of_week;
		$end_of_week->modify( '+6 days' );
		
		$html = '<div class="twec-calendar-week">';
		$html .= '<div class="twec-week-hour"></div>';
		
		for ( $i = 0; $i < 7; $i++ ) {
			$day = clone $start_of_week;
			$day->modify( "+$i days" );
			$html .= '<div class="twec-week-day-header">';
			$html .= esc_html( $day->format( 'D, M j' ) );
			$html .= '</div>';
		}
		
		for ( $hour = 0; $hour < 24; $hour++ ) {
			$html .= '<div class="twec-week-hour">' . sprintf( '%02d:00', $hour ) . '</div>';
			for ( $i = 0; $i < 7; $i++ ) {
				$day = clone $start_of_week;
				$day->modify( "+$i days" );
				$html .= '<div class="twec-week-day">';
				$day_events = $this->get_events_for_day( $day->format( 'Y-m-d' ), $events );
				foreach ( $day_events as $event ) {
					$start_time = get_post_meta( $event->ID, '_twec_event_start_time', true );
					if ( $start_time ) {
						$event_hour = (int) substr( $start_time, 0, 2 );
						if ( $event_hour == $hour ) {
							$html .= '<div class="twec-week-event">';
							$html .= '<a href="' . esc_url( get_permalink( $event->ID ) ) . '">';
							$html .= esc_html( get_the_title( $event->ID ) );
							$html .= '</a></div>';
						}
					}
				}
				$html .= '</div>';
			}
		}
		
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render day view.
	 */
	private function render_day_view( $date, $events ) {
		$html = '<div class="twec-calendar-day-view">';
		
		for ( $hour = 0; $hour < 24; $hour++ ) {
			$html .= '<div class="twec-day-hour">' . sprintf( '%02d:00', $hour ) . '</div>';
			$html .= '<div class="twec-day-events">';
			
			$day_events = $this->get_events_for_day( $date->format( 'Y-m-d' ), $events );
			foreach ( $day_events as $event ) {
				$start_time = get_post_meta( $event->ID, '_twec_event_start_time', true );
				if ( $start_time ) {
					$event_hour = (int) substr( $start_time, 0, 2 );
					if ( $event_hour == $hour ) {
						$html .= '<div class="twec-week-event">';
						$html .= '<a href="' . esc_url( get_permalink( $event->ID ) ) . '">';
						$html .= esc_html( get_the_title( $event->ID ) );
						if ( $start_time ) {
							$html .= ' - ' . esc_html( $start_time );
						}
						$html .= '</a></div>';
					}
				}
			}
			
			$html .= '</div>';
		}
		
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render year view.
	 */
	private function render_year_view( $date, $events ) {
		$year = $date->format( 'Y' );
		$html = '<div class="twec-calendar-year">';
		
		for ( $month = 1; $month <= 12; $month++ ) {
			$month_date = new DateTime( "$year-$month-01" );
			$html .= '<div class="twec-year-month">';
			$html .= '<div class="twec-year-month-title">' . $month_date->format( 'F' ) . '</div>';
			$html .= '<div class="twec-year-month-grid">';
			
			$days = array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' );
			foreach ( $days as $day ) {
				$html .= '<div class="twec-year-day twec-year-day-header">' . esc_html( $day ) . '</div>';
			}
			
			$first_day = clone $month_date;
			$first_day->modify( 'monday this week' );
			$last_day = clone $month_date;
			$last_day->modify( 'last day of this month' );
			$last_day->modify( 'sunday this week' );
			
			$current = clone $first_day;
			while ( $current <= $last_day ) {
				$is_other_month = $current->format( 'm' ) != $month;
				$has_events = ! empty( $this->get_events_for_day( $current->format( 'Y-m-d' ), $events ) );
				$day_class = 'twec-year-day';
				if ( $has_events && ! $is_other_month ) {
					$day_class .= ' has-events';
				}
				
				$html .= '<div class="' . $day_class . '">';
				if ( ! $is_other_month ) {
					$html .= $current->format( 'j' );
				}
				$html .= '</div>';
				
				$current->modify( '+1 day' );
			}
			
			$html .= '</div></div>';
		}
		
		$html .= '</div>';
		return $html;
	}

	/**
	 * Get events for a specific period.
	 */
	public function get_events_for_period( $view, $date ) {
		$date_obj = new DateTime( $date );
		$start_date = clone $date_obj;
		$end_date = clone $date_obj;
		
		switch ( $view ) {
			case 'day':
				$end_date->modify( '+1 day' );
				break;
			case 'week':
				$start_date->modify( 'monday this week' );
				$end_date = clone $start_date;
				$end_date->modify( '+7 days' );
				break;
			case 'month':
				$start_date->modify( 'first day of this month' );
				$start_date->modify( 'monday this week' );
				$end_date->modify( 'last day of this month' );
				$end_date->modify( 'sunday this week' );
				break;
			case 'year':
				$start_date->modify( 'first day of January' );
				$end_date->modify( 'last day of December' );
				break;
		}
		
		// Build meta query - events that overlap with the period
		// An event overlaps if: start_date <= period_end AND end_date >= period_start
		// Use DATE type instead of DATETIME for more reliable comparisons
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key' => '_twec_event_start_date',
				'value' => $end_date->format( 'Y-m-d' ),
				'compare' => '<=',
				'type' => 'DATE',
			),
			array(
				'key' => '_twec_event_end_date',
				'value' => $start_date->format( 'Y-m-d' ),
				'compare' => '>=',
				'type' => 'DATE',
			),
		);
		
		$settings = get_option( 'twec_settings', array() );
		// Only apply hide_past_events filter if viewing current month or future
		// Don't apply it when viewing past months (user might want to see past events)
		$viewing_date = clone $date_obj;
		$viewing_date->setTime( 0, 0, 0 );
		// Get first day of viewing month for comparison
		if ( $view === 'month' ) {
			$viewing_date->modify( 'first day of this month' );
		}
		$today = new DateTime();
		$today->setTime( 0, 0, 0 );
		$today->modify( 'first day of this month' );
		
		// Only hide past events if we're viewing current month or future, AND the setting is enabled
		// This prevents hiding events when viewing past months
		if ( isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] && $viewing_date >= $today ) {
			$meta_query[] = array(
				'key' => '_twec_event_end_date',
				'value' => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type' => 'DATE',
			);
		}
		
		$args = array(
			'post_type' => 'twec_event',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'meta_query' => $meta_query,
			'orderby' => 'meta_value',
			'meta_key' => '_twec_event_start_date',
			'order' => 'ASC',
		);
		
		// Use get_posts with suppress_filters to avoid plugin conflicts
		$events = get_posts( $args );
		
		// If no events found with DATE type, try a broader query as fallback
		if ( empty( $events ) && $view === 'month' ) {
			// Fallback: Get all events and filter in PHP
			$fallback_args = array(
				'post_type' => 'twec_event',
				'posts_per_page' => -1,
				'post_status' => 'publish',
				'orderby' => 'meta_value',
				'meta_key' => '_twec_event_start_date',
				'order' => 'ASC',
			);
			
			$all_events = get_posts( $fallback_args );
			$events = array();
			
			foreach ( $all_events as $event ) {
				$event_start = get_post_meta( $event->ID, '_twec_event_start_date', true );
				$event_end = get_post_meta( $event->ID, '_twec_event_end_date', true );
				
				if ( ! $event_start || ! $event_end ) {
					continue;
				}
				
				// Extract just the date part
				$event_start_date = substr( $event_start, 0, 10 );
				$event_end_date = substr( $event_end, 0, 10 );
				$period_start_date = $start_date->format( 'Y-m-d' );
				$period_end_date = $end_date->format( 'Y-m-d' );
				
				// Check if event overlaps with period
				if ( $event_start_date <= $period_end_date && $event_end_date >= $period_start_date ) {
					$events[] = $event;
				}
			}
		}
		
		// Debug logging (only if WP_DEBUG is enabled)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'TWEC Calendar Query: View=' . $view . ', Date=' . $date );
			error_log( 'TWEC Period: Start=' . $start_date->format( 'Y-m-d H:i:s' ) . ', End=' . $end_date->format( 'Y-m-d H:i:s' ) );
			error_log( 'TWEC Events Found from Query: ' . count( $events ) );
		}
		
		// Don't filter events again - they're already filtered by the query
		// The PHP filtering was causing issues, so we'll trust the MySQL query
		// Just ensure we have valid event objects
		$valid_events = array();
		foreach ( $events as $event ) {
			if ( isset( $event->ID ) && get_post( $event->ID ) ) {
				$valid_events[] = $event;
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'TWEC Final Events: ' . count( $valid_events ) . ' out of ' . count( $events ) . ' found' );
		}
		
		return $valid_events;
	}

	/**
	 * Get events for a specific day.
	 */
	private function get_events_for_day( $date, $events ) {
		$day_events = array();
		$check_date = $this->parse_event_date( $date );
		if ( ! $check_date ) {
			return $day_events;
		}
		
		// Normalize check date to just the date part (ignore time)
		$check_date_only = $check_date->format( 'Y-m-d' );
		
		foreach ( $events as $event ) {
			$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
			$end_date = get_post_meta( $event->ID, '_twec_event_end_date', true );
			
			if ( ! $start_date || ! $end_date ) {
				continue;
			}
			
			// Parse dates
			$event_start_dt = $this->parse_event_date( $start_date );
			$event_end_dt = $this->parse_event_date( $end_date );
			
			if ( ! $event_start_dt || ! $event_end_dt ) {
				continue;
			}
			
			// Get just the date portion (ignore time)
			$event_start_only = $event_start_dt->format( 'Y-m-d' );
			$event_end_only = $event_end_dt->format( 'Y-m-d' );
			
			// Check if the day falls within the event range
			if ( $check_date_only >= $event_start_only && $check_date_only <= $event_end_only ) {
				$day_events[] = $event;
			}
		}
		
		return $day_events;
	}

	/**
	 * Get calendar title.
	 */
	private function get_calendar_title( $view, $date ) {
		$date_obj = new DateTime( $date );
		
		switch ( $view ) {
			case 'day':
				return $date_obj->format( 'F j, Y' );
			case 'week':
				$start = clone $date_obj;
				$start->modify( 'monday this week' );
				$end = clone $start;
				$end->modify( '+6 days' );
				return $start->format( 'M j' ) . ' - ' . $end->format( 'M j, Y' );
			case 'month':
				return $date_obj->format( 'F Y' );
			case 'year':
				return $date_obj->format( 'Y' );
			case 'photo':
				return __( 'Photo View', 'the-wordpress-event-calendar' );
			case 'map':
				return __( 'Map View', 'the-wordpress-event-calendar' );
			default:
				return $date_obj->format( 'F Y' );
		}
	}

	/**
	 * Handle iCal export.
	 */
	public function handle_ical_export() {
		if ( ! isset( $_GET['twec_export'] ) || 'ical' !== $_GET['twec_export'] ) {
			return;
		}
		
		$event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : 0;
		if ( ! $event_id ) {
			return;
		}
		
		$event = get_post( $event_id );
		if ( ! $event || 'twec_event' !== $event->post_type ) {
			return;
		}
		
		$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
		$end_date = get_post_meta( $event_id, '_twec_event_end_date', true );
		$venue_id = get_post_meta( $event_id, '_twec_event_venue', true );
		
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="event-' . absint( $event_id ) . '.ics"' );
		
		echo "BEGIN:VCALENDAR\r\n";
		echo "VERSION:2.0\r\n";
		echo "PRODID:-//WordPress Event Calendar//EN\r\n";
		echo "BEGIN:VEVENT\r\n";
		$parsed_url = wp_parse_url( home_url() );
		$host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : 'example.com';
		$host = esc_attr( $host ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - iCal format, not HTML
		echo "UID:event-" . absint( $event_id ) . "@" . $host . "\r\n";
		echo "DTSTART:" . gmdate( 'Ymd\THis', strtotime( $start_date ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - iCal format, not HTML
		echo "DTEND:" . gmdate( 'Ymd\THis', strtotime( $end_date ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - iCal format, not HTML
		echo "SUMMARY:" . $this->ical_escape( $event->post_title ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - iCal format, not HTML
		echo "DESCRIPTION:" . $this->ical_escape( wp_strip_all_tags( $event->post_content ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - iCal format, not HTML
		$event_url = get_permalink( $event_id );
		if ( $event_url ) {
			echo "URL:" . esc_url_raw( $event_url ) . "\r\n";
		}
		
		if ( $venue_id ) {
			$venue = get_post( $venue_id );
			$venue_address = get_post_meta( $venue_id, '_twec_venue_address', true );
			$venue_city = get_post_meta( $venue_id, '_twec_venue_city', true );
			$venue_state = get_post_meta( $venue_id, '_twec_venue_state', true );
			$venue_zip = get_post_meta( $venue_id, '_twec_venue_zip', true );
			
			$location = $venue->post_title;
			if ( $venue_address || $venue_city || $venue_state || $venue_zip ) {
				$address_parts = array_filter( array( $venue_address, $venue_city, $venue_state, $venue_zip ) );
				$location .= ', ' . implode( ', ', $address_parts );
			}
			echo "LOCATION:" . $this->ical_escape( $location ) . "\r\n";
		}
		
		echo "END:VEVENT\r\n";
		echo "END:VCALENDAR\r\n";
		exit;
	}

	/**
	 * Handle Google Calendar export.
	 */
	public function handle_google_calendar_export() {
		if ( ! isset( $_GET['twec_export'] ) || 'google' !== $_GET['twec_export'] ) {
			return;
		}
		
		$event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : 0;
		if ( ! $event_id ) {
			return;
		}
		
		$event = get_post( $event_id );
		if ( ! $event || 'twec_event' !== $event->post_type ) {
			return;
		}
		
		$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
		$end_date = get_post_meta( $event_id, '_twec_event_end_date', true );
		
		$url = 'https://www.google.com/calendar/render?action=TEMPLATE';
		$url .= '&text=' . rawurlencode( $event->post_title );
		$url .= '&dates=' . gmdate( 'Ymd\THis', strtotime( $start_date ) ) . '/' . gmdate( 'Ymd\THis', strtotime( $end_date ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - URL parameter, not HTML
		$url .= '&details=' . rawurlencode( wp_strip_all_tags( $event->post_content ) );
		$event_permalink = get_permalink( $event_id );
		if ( $event_permalink ) {
			$url .= '&location=' . rawurlencode( $event_permalink );
		}
		
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Render photo view.
	 */
	private function render_photo_view( $date, $events ) {
		$html = '<div class="twec-calendar-photo-view">';
		
		foreach ( $events as $event ) {
			$thumbnail = get_the_post_thumbnail( $event->ID, 'medium' );
			$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
			$is_featured = get_post_meta( $event->ID, '_twec_is_featured', true );
			
			$html .= '<div class="twec-photo-event' . ( $is_featured ? ' twec-featured' : '' ) . '">';
			if ( $thumbnail ) {
				$html .= '<a href="' . esc_url( get_permalink( $event->ID ) ) . '">' . $thumbnail . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - thumbnail is already escaped by get_the_post_thumbnail
			} else {
				$html .= '<div class="twec-photo-placeholder"></div>';
			}
			$html .= '<div class="twec-photo-event-info">';
			$html .= '<h3><a href="' . esc_url( get_permalink( $event->ID ) ) . '">' . esc_html( get_the_title( $event->ID ) ) . '</a></h3>';
			if ( $start_date ) {
				$html .= '<div class="twec-photo-event-date">' . date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) . '</div>';
			}
			if ( has_excerpt( $event->ID ) ) {
				$html .= '<div class="twec-photo-event-excerpt">' . get_the_excerpt( $event->ID ) . '</div>';
			}
			$html .= '</div>';
			$html .= '</div>';
		}
		
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render map view.
	 */
	private function render_map_view( $date, $events ) {
		$html = '<div class="twec-calendar-map-view">';
		$html .= '<div id="twec-map-container" class="twec-map-container"></div>';
		$html .= '<div class="twec-map-events-list">';
		
		$map_markers = array();
		
		foreach ( $events as $event ) {
			$venue_id = get_post_meta( $event->ID, '_twec_event_venue', true );
			if ( ! $venue_id ) {
				continue;
			}
			
			$venue = get_post( $venue_id );
			$lat = get_post_meta( $venue_id, '_twec_venue_latitude', true );
			$lng = get_post_meta( $venue_id, '_twec_venue_longitude', true );
			
			if ( ! $lat || ! $lng ) {
				continue;
			}
			
			$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
			
			$map_markers[] = array(
				'lat' => floatval( $lat ),
				'lng' => floatval( $lng ),
				'title' => get_the_title( $event->ID ),
				'url' => get_permalink( $event->ID ),
				'venue' => $venue ? $venue->post_title : '',
				'date' => $start_date ? date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) : '',
			);
			
			$html .= '<div class="twec-map-event-item">';
			$html .= '<h3><a href="' . esc_url( get_permalink( $event->ID ) ) . '">' . esc_html( get_the_title( $event->ID ) ) . '</a></h3>';
			if ( $venue ) {
				$html .= '<div class="twec-map-event-venue">' . esc_html( $venue->post_title ) . '</div>';
			}
			if ( $start_date ) {
				$html .= '<div class="twec-map-event-date">' . date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) . '</div>';
			}
			$html .= '</div>';
		}
		
		$html .= '</div>';
		$html .= '</div>';
		
		// Add map markers data
		$html .= '<script type="text/javascript">';
		$html .= 'var twecMapMarkers = ' . json_encode( $map_markers ) . ';';
		$html .= '</script>';
		
		return $html;
	}

	/**
	 * Parse event date from various formats.
	 */
	private function parse_event_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return false;
		}
		
		// Try to parse the date
		$date = DateTime::createFromFormat( 'Y-m-d H:i:s', $date_string );
		if ( ! $date ) {
			$date = DateTime::createFromFormat( 'Y-m-d H:i', $date_string );
		}
		if ( ! $date ) {
			$date = DateTime::createFromFormat( 'Y-m-d', $date_string );
		}
		if ( ! $date ) {
			// Try strtotime as fallback
			$timestamp = strtotime( $date_string );
			if ( $timestamp ) {
				$date = new DateTime();
				$date->setTimestamp( $timestamp );
			}
		}
		
		return $date ? $date : false;
	}

	/**
	 * Escape text for iCal format.
	 */
	private function ical_escape( $text ) {
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( ',', '\\,', $text );
		$text = str_replace( ';', '\\;', $text );
		$text = str_replace( "\n", '\\n', $text );
		return $text;
	}

}

