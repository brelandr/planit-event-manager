<?php
/**
 * Shared helpers (performance, prefixes). Loaded from the main plugin file before Premium short-circuit.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return PHP timezone identifiers with a 24-hour transient cache (avoids rebuilding a large list on every admin render).
 *
 * Transient key is prefixed with the plugin slug to avoid collisions.
 *
 * @return string[]
 */
function planit_event_manager_get_timezone_identifiers() {
	$key    = 'planit_event_manager_tz_identifiers_v1';
	$cached = get_transient( $key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$list = function_exists( 'timezone_identifiers_list' ) ? timezone_identifiers_list() : array();
	if ( ! is_array( $list ) ) {
		return array();
	}

	set_transient( $key, $list, DAY_IN_SECONDS );

	return $list;
}

/**
 * Build a transient key for admin CPT dropdown caches.
 *
 * @param string $post_type Post type slug.
 * @return string
 */
function planit_event_manager_admin_choices_transient_key( $post_type ) {
	return 'planit_event_manager_admin_choices_' . sanitize_key( (string) $post_type ) . '_v1';
}

/**
 * Published posts for admin dropdowns (ID + title), cached 24 hours.
 *
 * @param string $post_type Post type slug.
 * @return array<int, array{ID:int, post_title:string}>
 */
function planit_event_manager_get_admin_post_choices( $post_type ) {
	$post_type = sanitize_key( (string) $post_type );
	if ( '' === $post_type ) {
		return array();
	}

	$key    = planit_event_manager_admin_choices_transient_key( $post_type );
	$cached = get_transient( $key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$posts = get_posts(
		array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		)
	);

	$choices = array();
	foreach ( $posts as $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			continue;
		}
		$choices[] = array(
			'ID'         => $post_id,
			'post_title' => (string) get_the_title( $post_id ),
		);
	}

	set_transient( $key, $choices, DAY_IN_SECONDS );

	return $choices;
}

/**
 * Events linked to a WooCommerce ticket product (admin filter dropdown), cached 24 hours.
 *
 * @return array<int, array{ID:int, post_title:string}>
 */
function planit_event_manager_get_wc_ticket_event_choices() {
	$key    = 'planit_event_manager_wc_ticket_events_v1';
	$cached = get_transient( $key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$choices = array();
	if ( ! class_exists( 'TWEC_WooCommerce', false ) ) {
		set_transient( $key, $choices, DAY_IN_SECONDS );
		return $choices;
	}

	$events = get_posts(
		array(
			'post_type'              => 'twec_event',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		)
	);

	foreach ( $events as $event_id ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			continue;
		}
		$product_id = (int) get_post_meta( $event_id, TWEC_WooCommerce::META_PRODUCT_ID, true );
		if ( $product_id < 1 ) {
			continue;
		}
		$choices[] = array(
			'ID'         => $event_id,
			'post_title' => (string) get_the_title( $event_id ),
		);
	}

	set_transient( $key, $choices, DAY_IN_SECONDS );

	return $choices;
}

/**
 * Dashboard widget: events in the next 7 days (15-minute cache bucket).
 *
 * @return array<int, array{id:int, title:string, start_label:string, edit_url:string}>
 */
function planit_event_manager_get_dashboard_week_events() {
	$bucket = (int) floor( (int) current_time( 'timestamp' ) / ( 15 * MINUTE_IN_SECONDS ) );
	$key    = 'planit_event_manager_dashboard_week_' . $bucket;
	$cached = get_transient( $key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$start = current_time( 'mysql' );
	$end   = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days', (int) current_time( 'timestamp' ) ) );

	$events = get_posts(
		array(
			'post_type'              => 'twec_event',
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
			'orderby'                => 'meta_value',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded widget query; cached 15 minutes.
			'meta_key'               => '_twec_event_start_date',
			'meta_type'              => 'DATETIME',
			'meta_query'             => array(
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

	$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	$rows        = array();

	foreach ( $events as $event ) {
		if ( ! $event instanceof WP_Post ) {
			continue;
		}
		$start_raw = (string) get_post_meta( $event->ID, '_twec_event_start_date', true );
		$edit_url  = get_edit_post_link( $event->ID, 'raw' );
		$rows[]    = array(
			'id'          => (int) $event->ID,
			'title'       => (string) get_the_title( $event->ID ),
			'start_label' => $start_raw ? (string) mysql2date( $date_format, $start_raw ) : '',
			'edit_url'    => is_string( $edit_url ) ? $edit_url : '',
		);
	}

	set_transient( $key, $rows, 15 * MINUTE_IN_SECONDS );

	return $rows;
}

/**
 * Clear admin list transients when event-related content changes.
 *
 * @param string $post_type Optional post type to limit busting.
 * @return void
 */
function planit_event_manager_bust_admin_list_caches( $post_type = '' ) {
	$types = array( 'twec_venue', 'twec_organizer', 'twec_event' );
	if ( '' !== (string) $post_type ) {
		$types = array( sanitize_key( (string) $post_type ) );
	}
	foreach ( $types as $type ) {
		if ( '' === $type ) {
			continue;
		}
		delete_transient( planit_event_manager_admin_choices_transient_key( $type ) );
	}
	delete_transient( 'planit_event_manager_wc_ticket_events_v1' );
}

/**
 * Register cache invalidation hooks for admin dropdown transients.
 *
 * @return void
 */
function planit_event_manager_register_cache_invalidation_hooks() {
	$handler = static function ( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( ! is_string( $type ) || '' === $type ) {
			return;
		}
		if ( in_array( $type, array( 'twec_venue', 'twec_organizer', 'twec_event' ), true ) ) {
			planit_event_manager_bust_admin_list_caches( $type );
		}
	};

	add_action( 'save_post_twec_venue', $handler, 20, 1 );
	add_action( 'save_post_twec_organizer', $handler, 20, 1 );
	add_action( 'save_post_twec_event', $handler, 20, 1 );
	add_action( 'deleted_post', $handler, 20, 1 );
}

/**
 * Verify a $_POST nonce for classic metabox / save_post handlers.
 *
 * Returns false when the field is absent (autosave, REST, or revisions without the metabox).
 * Calls wp_die() when the field is present but invalid.
 *
 * @param string $field  $_POST key holding the nonce value.
 * @param string $action Nonce action name.
 * @return bool True when verified.
 */
function twec_verify_post_nonce_field( $field, $action ) {
	if ( ! isset( $_POST[ $field ] ) ) {
		return false;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
		wp_die(
			esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ),
			esc_html__( 'Security error', 'planit-event-manager' ),
			array( 'response' => 403 )
		);
	}

	return true;
}

/**
 * Verify a $_POST nonce for dedicated form handlers (import, settings actions).
 *
 * @param string $field  $_POST key holding the nonce value (usually `_wpnonce`).
 * @param string $action Nonce action name.
 * @return void
 */
function twec_verify_post_nonce_or_die( $field, $action ) {
	if ( ! isset( $_POST[ $field ] ) ) {
		wp_die(
			esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ),
			esc_html__( 'Security error', 'planit-event-manager' ),
			array( 'response' => 403 )
		);
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
		wp_die(
			esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ),
			esc_html__( 'Security error', 'planit-event-manager' ),
			array( 'response' => 403 )
		);
	}
}

/**
 * Verify a $_GET nonce for admin/export links.
 *
 * @param string $action Nonce action name.
 * @param string $field  $_GET key holding the nonce value (default `_wpnonce`).
 * @return void
 */
function twec_verify_get_nonce_or_die( $action, $field = '_wpnonce' ) {
	if ( ! isset( $_GET[ $field ] ) ) {
		wp_die(
			esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ),
			esc_html__( 'Security error', 'planit-event-manager' ),
			array( 'response' => 403 )
		);
	}

	$nonce = sanitize_text_field( wp_unslash( $_GET[ $field ] ) );
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
		wp_die(
			esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ),
			esc_html__( 'Security error', 'planit-event-manager' ),
			array( 'response' => 403 )
		);
	}
}
