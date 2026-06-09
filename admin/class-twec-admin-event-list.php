<?php
/**
 * Admin list table filters and helpers for twec_event.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date range filters on Events → All Events.
 */
class TWEC_Admin_Event_List {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_date_filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_date_filters' ) );
	}

	/**
	 * @param string $post_type Current list post type.
	 * @return void
	 */
	public static function render_date_filters( $post_type ) {
		if ( 'twec_event' !== $post_type ) {
			return;
		}

		$start_from = self::get_request_date( 'twec_start_from' );
		$start_to   = self::get_request_date( 'twec_start_to' );
		$end_from   = self::get_request_date( 'twec_end_from' );
		$end_to     = self::get_request_date( 'twec_end_to' );

		echo '<span class="twec-event-list-date-filters">';
		echo '<span class="twec-event-list-date-filters__group">';
		echo '<label for="twec_start_from">' . esc_html__( 'Start from', 'planit-event-manager' ) . '</label>';
		echo '<input type="date" name="twec_start_from" id="twec_start_from" value="' . esc_attr( $start_from ) . '" />';
		echo '<label for="twec_start_to">' . esc_html__( 'Start to', 'planit-event-manager' ) . '</label>';
		echo '<input type="date" name="twec_start_to" id="twec_start_to" value="' . esc_attr( $start_to ) . '" />';
		echo '</span>';
		echo '<span class="twec-event-list-date-filters__group">';
		echo '<label for="twec_end_from">' . esc_html__( 'End from', 'planit-event-manager' ) . '</label>';
		echo '<input type="date" name="twec_end_from" id="twec_end_from" value="' . esc_attr( $end_from ) . '" />';
		echo '<label for="twec_end_to">' . esc_html__( 'End to', 'planit-event-manager' ) . '</label>';
		echo '<input type="date" name="twec_end_to" id="twec_end_to" value="' . esc_attr( $end_to ) . '" />';
		echo '</span>';
		echo '</span>';
	}

	/**
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public static function apply_date_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter (no privileged action).
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		if ( 'twec_event' !== $post_type ) {
			return;
		}

		$start_from = self::get_request_date( 'twec_start_from' );
		$start_to   = self::get_request_date( 'twec_start_to' );
		$end_from   = self::get_request_date( 'twec_end_from' );
		$end_to     = self::get_request_date( 'twec_end_to' );

		if ( '' === $start_from && '' === $start_to && '' === $end_from && '' === $end_to ) {
			return;
		}

		$meta_query = array();

		if ( '' !== $start_from ) {
			$meta_query[] = array(
				'key'     => '_twec_event_start_date',
				'value'   => self::datetime_bound( $start_from, false ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}
		if ( '' !== $start_to ) {
			$meta_query[] = array(
				'key'     => '_twec_event_start_date',
				'value'   => self::datetime_bound( $start_to, true ),
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}
		if ( '' !== $end_from ) {
			$meta_query[] = array(
				'key'     => '_twec_event_end_date',
				'value'   => self::datetime_bound( $end_from, false ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}
		if ( '' !== $end_to ) {
			$meta_query[] = array(
				'key'     => '_twec_event_end_date',
				'value'   => self::datetime_bound( $end_to, true ),
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		/**
		 * Filter meta_query used for event start/end date filters on the admin events list.
		 *
		 * @param array<string, mixed> $meta_query Meta query clauses.
		 * @param array<string, string> $dates     Sanitized Y-m-d filter values.
		 */
		$meta_query = apply_filters(
			'twec_admin_event_list_meta_query',
			$meta_query,
			array(
				'start_from' => $start_from,
				'start_to'   => $start_to,
				'end_from'   => $end_from,
				'end_to'     => $end_to,
			)
		);

		if ( empty( $meta_query ) ) {
			return;
		}

		$existing = $query->get( 'meta_query' );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$combined = array( 'relation' => 'AND' );
			foreach ( $existing as $key => $clause ) {
				if ( 'relation' === $key ) {
					continue;
				}
				$combined[] = $clause;
			}
			foreach ( $meta_query as $key => $clause ) {
				if ( 'relation' === $key ) {
					continue;
				}
				$combined[] = $clause;
			}
			$meta_query = $combined;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin list date filter.
		$query->set( 'meta_query', $meta_query );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort preference from list table.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		if ( '' === $orderby ) {
			$query->set( 'meta_key', '_twec_event_start_date' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * @param string $param GET parameter name.
	 * @return string Y-m-d or empty.
	 */
	private static function get_request_date( $param ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		if ( ! isset( $_GET[ $param ] ) ) {
			return '';
		}
		return self::sanitize_ymd( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) );
	}

	/**
	 * @param string $raw Raw date string.
	 * @return string Y-m-d or empty when invalid.
	 */
	private static function sanitize_ymd( $raw ) {
		$raw = trim( (string) $raw );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return '';
		}
		$parts = explode( '-', $raw );
		if ( 3 !== count( $parts ) ) {
			return '';
		}
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return '';
		}
		return $raw;
	}

	/**
	 * @param string $ymd        Date Y-m-d.
	 * @param bool   $end_of_day Whether to use end of day.
	 * @return string Datetime bound for meta compare.
	 */
	private static function datetime_bound( $ymd, $end_of_day ) {
		return $end_of_day ? $ymd . ' 23:59:59' : $ymd . ' 00:00:00';
	}
}
