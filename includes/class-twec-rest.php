<?php
/**
 * REST API: event meta and collection query parameters for public events.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST fields and query args for the `twec_event` post type.
 *
 * Uses `register_rest_field`; authorization follows core post REST rules (`read_post`/`edit_post`),
 * plus any server-side filters/themes. Collection query args (`twec_after`, `twec_before`) are
 * sanitized before being applied as meta clauses in {@see TWEC_REST::rest_query()}.
 */
class TWEC_REST {

	/**
	 * Hook into rest_api_init.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_pre_dispatch_validate_twec_meta' ), 5, 3 );
	}

	/**
	 * Register fields and collection parameter mapping.
	 *
	 * @return void
	 */
	public static function register() {
		$post_type = 'twec_event';
		if ( ! post_type_exists( $post_type ) ) {
			return;
		}

		$common = array(
			'schema' => array(
				'description' => __( 'Event calendar meta and linked IDs.', 'planit-event-manager' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
			),
		);

		register_rest_field(
			$post_type,
			'planit_event',
			array_merge(
				$common,
				array(
					'get_callback' => array( __CLASS__, 'get_event_payload' ),
				)
			)
		);

		add_filter( 'rest_' . $post_type . '_query', array( __CLASS__, 'rest_query' ), 10, 2 );
		add_filter( 'rest_' . $post_type . '_collection_params', array( __CLASS__, 'collection_params' ) );

		self::register_quick_add_route();
	}

	/**
	 * REST: privileged quick-create for embedded calendar (matches classic Event Data datetime storage).
	 *
	 * @return void
	 */
	private static function register_quick_add_route() {
		register_rest_route(
			'planit/v1',
			'/events/quick-add',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_quick_add_event' ),
				'permission_callback' => array( __CLASS__, 'rest_quick_add_permissions' ),
				'args'                => array(
					'title'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'     => array(
						'type'              => 'string',
						'default'           => 'draft',
						'enum'              => array( 'draft', 'publish' ),
						'sanitize_callback' => static function ( $value ) {
							$v = sanitize_text_field( (string) $value );
							return in_array( $v, array( 'draft', 'publish' ), true ) ? $v : 'draft';
						},
					),
					'all_day'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'start_date' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_date'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'start_time' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_time'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Whether the current user may call quick-add.
	 *
	 * @return bool
	 */
	public static function rest_quick_add_permissions() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$cap = apply_filters( 'twec_quick_add_capability', 'edit_posts' );
		return (bool) apply_filters( 'twec_quick_add_allowed', current_user_can( $cap ) );
	}

	/**
	 * Block invalid Event Data ranges before Core persists REST meta for twec_event updates/creates with meta payloads.
	 *
	 * @param mixed           $response Result override.
	 * @param WP_REST_Server  $server   REST server instance.
	 * @param WP_REST_Request $request  Request object.
	 * @return mixed
	 */
	public static function rest_pre_dispatch_validate_twec_meta( $response, $server, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP filter signature includes $server.
		if ( null !== $response ) {
			return $response;
		}
		if ( ! ( $request instanceof WP_REST_Request ) || ! class_exists( 'TWEC_Event_Datetime', false ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/wp/v2/twec_event(?:/(?P<id>\\d+))?#', $route, $rm ) ) {
			return $response;
		}

		$m       = array();
		$decoded = $request->get_json_params();
		if ( is_array( $decoded ) && isset( $decoded['meta'] ) && is_array( $decoded['meta'] ) ) {
			$m = $decoded['meta'];
		} else {
			$body_meta = $request->get_param( 'meta' );
			if ( is_array( $body_meta ) ) {
				$m = $body_meta;
			}
		}

		if ( empty( $m ) || ! is_array( $m ) ) {
			return $response;
		}

		$post_id = 0;
		if ( ! empty( $rm['id'] ) ) {
			$post_id = (int) $rm['id'];
		}

		$existing = array();
		if ( $post_id > 0 ) {
			$keys = array(
				'_twec_event_all_day',
				'_twec_event_start_date',
				'_twec_event_end_date',
				'_twec_event_start_time',
				'_twec_event_end_time',
			);
			foreach ( $keys as $k ) {
				$existing[ $k ] = get_post_meta( $post_id, $k, true );
			}
		}

		$check = TWEC_Event_Datetime::validate_merged_rest_meta( $existing, $m );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		return $response;
	}

	/**
	 * Create a twec_event with Event Data meta (same shape as TWEC_Meta_Boxes::save_event_meta()).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_quick_add_event( WP_REST_Request $request ) {
		$title    = $request->get_param( 'title' );
		$status   = $request->get_param( 'status' );
		$all_day  = (bool) $request->get_param( 'all_day' );
		$start_d  = (string) $request->get_param( 'start_date' );
		$end_d    = (string) $request->get_param( 'end_date' );
		$start_ti = (string) $request->get_param( 'start_time' );
		$end_ti   = (string) $request->get_param( 'end_time' );

		if ( '' === trim( (string) $title ) ) {
			return new WP_Error( 'twec_quick_add_title', __( 'Title is required.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'twec_quick_add_publish', __( 'You do not have permission to publish events.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$built = TWEC_Event_Datetime::validate_and_build_storage( $all_day, $start_d, $end_d, $start_ti, $end_ti );
		if ( is_wp_error( $built ) ) {
			$data = $built->get_error_data();
			$code = $built->get_error_code();
			if ( 'twec_event_dates' === $code ) {
				return new WP_Error( 'twec_quick_add_dates', $built->get_error_message(), is_array( $data ) ? $data : array( 'status' => 400 ) );
			}
			return new WP_Error( 'twec_quick_add_range', $built->get_error_message(), is_array( $data ) ? $data : array( 'status' => 400 ) );
		}

		$start_dt = $built['start_dt'];
		$end_dt   = $built['end_dt'];
		$start_t  = $built['start_t'];
		$end_t    = $built['end_t'];
		$all_day  = ( '1' === $built['all_day_meta'] );

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'twec_event',
				'post_status' => ( 'publish' === $status ) ? 'publish' : 'draft',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'twec_quick_add_insert', __( 'Could not create the event.', 'planit-event-manager' ), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, '_twec_event_all_day', $all_day ? '1' : '0' );
		update_post_meta( $post_id, '_twec_event_start_date', $start_dt );
		update_post_meta( $post_id, '_twec_event_end_date', $end_dt );
		update_post_meta( $post_id, '_twec_event_start_time', $start_t );
		update_post_meta( $post_id, '_twec_event_end_time', $end_t );

		$edit = get_edit_post_link( $post_id, 'raw' );

		return rest_ensure_response(
			array(
				'id'        => $post_id,
				'edit_link' => $edit ? (string) $edit : '',
			)
		);
	}

	/**
	 * Expose custom query args to the collection endpoint (documented in REST-API.md).
	 *
	 * @param array $params Collection params.
	 * @return array
	 */
	public static function collection_params( $params ) {
		$params['twec_after']  = array(
			'description' => __( 'Return events with start date on or after this value (Y-m-d or Y-m-d H:i:s, compared to _twec_event_start_date).', 'planit-event-manager' ),
			'type'        => 'string',
		);
		$params['twec_before'] = array(
			'description' => __( 'Return events with start date on or before this value (Y-m-d or Y-m-d H:i:s).', 'planit-event-manager' ),
			'type'        => 'string',
		);
		return $params;
	}

	/**
	 * REST field payload for a single event in responses.
	 *
	 * @param array $item Prepared post data.
	 * @return array
	 */
	public static function get_event_payload( $item ) {
		$id = 0;
		if ( is_array( $item ) && ! empty( $item['id'] ) ) {
			$id = (int) $item['id'];
		} elseif ( is_object( $item ) && isset( $item->id ) ) {
			$id = (int) $item->id;
		} elseif ( is_object( $item ) && isset( $item->ID ) ) {
			$id = (int) $item->ID;
		}
		if ( ! $id ) {
			return array();
		}
		$start    = get_post_meta( $id, '_twec_event_start_date', true );
		$end      = get_post_meta( $id, '_twec_event_end_date', true );
		$all_day  = get_post_meta( $id, '_twec_event_all_day', true );
		$venue_id = (int) get_post_meta( $id, '_twec_event_venue', true );
		$org_id   = (int) get_post_meta( $id, '_twec_event_organizer', true );
		$cost     = get_post_meta( $id, '_twec_event_cost', true );
		$website  = get_post_meta( $id, '_twec_event_website', true );
		$tz       = get_post_meta( $id, '_twec_event_timezone', true );

		return array(
			'start_date' => $start ? (string) $start : null,
			'end_date'   => $end ? (string) $end : null,
			'all_day'    => in_array( (string) $all_day, array( '1', 'yes', 'true' ), true ),
			'venue'      => $venue_id ? (int) $venue_id : null,
			'organizer'  => $org_id ? (int) $org_id : null,
			'cost'       => $cost ? (string) $cost : null,
			'website'    => $website ? (string) $website : null,
			'timezone'   => $tz ? (string) $tz : null,
		);
	}

	/**
	 * Filter REST collection query: filter by event start date range (meta).
	 *
	 * @param array           $args    Query args for WP_Query.
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	public static function rest_query( $args, $request ) {
		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return $args;
		}

		$after  = $request->get_param( 'twec_after' );
		$before = $request->get_param( 'twec_before' );

		if ( empty( $after ) && empty( $before ) ) {
			return $args;
		}

		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();

		if ( ! empty( $after ) ) {
			$after        = sanitize_text_field( (string) $after );
			$meta_query[] = array(
				'key'     => '_twec_event_start_date',
				'value'   => $after,
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}
		if ( ! empty( $before ) ) {
			$before       = sanitize_text_field( (string) $before );
			$meta_query[] = array(
				'key'     => '_twec_event_start_date',
				'value'   => $before,
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		$args['meta_query'] = $meta_query;
		return $args;
	}
}
