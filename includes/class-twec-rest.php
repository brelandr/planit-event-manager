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
	 * Create a draft event from a plain argument array (Abilities API / integrations).
	 *
	 * @param array<string, mixed> $args title, start_date, end_date, all_day?, venue_id?, start_time?, end_time?.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_event_draft_from_args( $args ) {
		$args  = is_array( $args ) ? $args : array();
		$title = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
		if ( '' === trim( $title ) ) {
			return new WP_Error( 'twec_draft_title', __( 'Title is required.', 'planit-event-manager' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'twec_draft_forbidden', __( 'You do not have permission to create events.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$all_day  = ! empty( $args['all_day'] );
		$start_d  = isset( $args['start_date'] ) ? sanitize_text_field( (string) $args['start_date'] ) : '';
		$end_d    = isset( $args['end_date'] ) ? sanitize_text_field( (string) $args['end_date'] ) : '';
		$start_ti = isset( $args['start_time'] ) ? sanitize_text_field( (string) $args['start_time'] ) : '';
		$end_ti   = isset( $args['end_time'] ) ? sanitize_text_field( (string) $args['end_time'] ) : '';

		if ( '' === $start_d || '' === $end_d ) {
			return new WP_Error( 'twec_draft_dates', __( 'start_date and end_date are required.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		if ( ! class_exists( 'TWEC_Event_Datetime', false ) ) {
			return new WP_Error( 'twec_draft_unavailable', __( 'Event datetime helper is not available.', 'planit-event-manager' ) );
		}

		$built = TWEC_Event_Datetime::validate_and_build_storage( $all_day, $start_d, $end_d, $start_ti, $end_ti );
		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'twec_event',
				'post_status' => 'draft',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'twec_draft_insert', __( 'Could not create the event.', 'planit-event-manager' ) );
		}

		update_post_meta( $post_id, '_twec_event_all_day', ( '1' === $built['all_day_meta'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_twec_event_start_date', $built['start_dt'] );
		update_post_meta( $post_id, '_twec_event_end_date', $built['end_dt'] );
		update_post_meta( $post_id, '_twec_event_start_time', $built['start_t'] );
		update_post_meta( $post_id, '_twec_event_end_time', $built['end_t'] );

		$venue_id = isset( $args['venue_id'] ) ? absint( $args['venue_id'] ) : 0;
		if ( $venue_id > 0 && 'twec_venue' === get_post_type( $venue_id ) ) {
			update_post_meta( $post_id, '_twec_event_venue', $venue_id );
		}
		$organizer_id = isset( $args['organizer_id'] ) ? absint( $args['organizer_id'] ) : 0;
		if ( $organizer_id > 0 && 'twec_organizer' === get_post_type( $organizer_id ) ) {
			update_post_meta( $post_id, '_twec_event_organizer', $organizer_id );
		}
		if ( ! empty( $args['content'] ) ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_kses_post( (string) $args['content'] ),
				)
			);
		}
		if ( ! empty( $args['excerpt'] ) ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_excerpt' => sanitize_text_field( (string) $args['excerpt'] ),
				)
			);
		}

		/**
		 * Fires after PlanIt creates an event draft via REST or abilities.
		 *
		 * @param int                  $post_id Event ID.
		 * @param array<string, mixed> $args    Args used to create the draft.
		 */
		do_action( 'twec_after_event_save', $post_id, $args );

		$edit = get_edit_post_link( $post_id, 'raw' );
		return array(
			'id'        => $post_id,
			'edit_link' => $edit ? (string) $edit : '',
		);
	}

	/**
	 * Resolve a venue post ID by title (existing venues only).
	 *
	 * @param string $name Venue title.
	 * @return int
	 */
	public static function resolve_venue_id_by_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}
		$posts = get_posts(
			array(
				'post_type'              => 'twec_venue',
				'title'                  => $name,
				'posts_per_page'         => 1,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return ! empty( $posts[0] ) ? (int) $posts[0]->ID : 0;
	}

	/**
	 * Resolve an organizer post ID by title (existing organizers only).
	 *
	 * @param string $name Organizer title.
	 * @return int
	 */
	public static function resolve_organizer_id_by_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}
		$posts = get_posts(
			array(
				'post_type'              => 'twec_organizer',
				'title'                  => $name,
				'posts_per_page'         => 1,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return ! empty( $posts[0] ) ? (int) $posts[0]->ID : 0;
	}

	/**
	 * Update an existing event from a plain argument array.
	 *
	 * @param array<string, mixed> $args Partial event fields keyed by event_id.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update_event_from_args( $args ) {
		$args     = is_array( $args ) ? $args : array();
		$event_id = isset( $args['event_id'] ) ? absint( $args['event_id'] ) : 0;
		if ( $event_id <= 0 || 'twec_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'twec_update_invalid', __( 'Invalid event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'twec_update_forbidden', __( 'You cannot edit this event.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$patch = array( 'ID' => $event_id );
		if ( isset( $args['title'] ) && '' !== trim( (string) $args['title'] ) ) {
			$patch['post_title'] = sanitize_text_field( (string) $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$patch['post_content'] = wp_kses_post( (string) $args['content'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$patch['post_excerpt'] = sanitize_text_field( (string) $args['excerpt'] );
		}
		if ( count( $patch ) > 1 ) {
			$updated = wp_update_post( $patch, true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		if ( isset( $args['all_day'] ) || isset( $args['start_date'] ) || isset( $args['end_date'] ) || isset( $args['start_time'] ) || isset( $args['end_time'] ) ) {
			$all_day  = isset( $args['all_day'] ) ? (bool) $args['all_day'] : ( '1' === get_post_meta( $event_id, '_twec_event_all_day', true ) );
			$start_d  = isset( $args['start_date'] ) ? sanitize_text_field( (string) $args['start_date'] ) : '';
			$end_d    = isset( $args['end_date'] ) ? sanitize_text_field( (string) $args['end_date'] ) : '';
			$start_ti = isset( $args['start_time'] ) ? sanitize_text_field( (string) $args['start_time'] ) : '';
			$end_ti   = isset( $args['end_time'] ) ? sanitize_text_field( (string) $args['end_time'] ) : '';
			if ( '' === $start_d ) {
				$start_d = substr( (string) get_post_meta( $event_id, '_twec_event_start_date', true ), 0, 10 );
			}
			if ( '' === $end_d ) {
				$end_d = substr( (string) get_post_meta( $event_id, '_twec_event_end_date', true ), 0, 10 );
			}
			if ( class_exists( 'TWEC_Event_Datetime', false ) && '' !== $start_d && '' !== $end_d ) {
				$built = TWEC_Event_Datetime::validate_and_build_storage( $all_day, $start_d, $end_d, $start_ti, $end_ti );
				if ( is_wp_error( $built ) ) {
					return $built;
				}
				update_post_meta( $event_id, '_twec_event_all_day', ( '1' === $built['all_day_meta'] ) ? '1' : '0' );
				update_post_meta( $event_id, '_twec_event_start_date', $built['start_dt'] );
				update_post_meta( $event_id, '_twec_event_end_date', $built['end_dt'] );
				update_post_meta( $event_id, '_twec_event_start_time', $built['start_t'] );
				update_post_meta( $event_id, '_twec_event_end_time', $built['end_t'] );
			}
		}

		if ( isset( $args['venue_id'] ) ) {
			$venue_id = absint( $args['venue_id'] );
			if ( $venue_id > 0 && 'twec_venue' === get_post_type( $venue_id ) ) {
				update_post_meta( $event_id, '_twec_event_venue', $venue_id );
			}
		}
		if ( isset( $args['organizer_id'] ) ) {
			$organizer_id = absint( $args['organizer_id'] );
			if ( $organizer_id > 0 && 'twec_organizer' === get_post_type( $organizer_id ) ) {
				update_post_meta( $event_id, '_twec_event_organizer', $organizer_id );
			}
		}
		if ( ! empty( $args['categories'] ) && is_array( $args['categories'] ) ) {
			self::assign_taxonomy_slugs( $event_id, 'twec_event_category', $args['categories'], false );
		}
		if ( ! empty( $args['tags'] ) && is_array( $args['tags'] ) ) {
			self::assign_taxonomy_slugs( $event_id, 'twec_event_tag', $args['tags'], false );
		}

		/**
		 * Fires after PlanIt updates an event via REST or abilities.
		 *
		 * @param int                  $event_id Event ID.
		 * @param array<string, mixed> $args     Update args.
		 */
		do_action( 'twec_after_event_save', $event_id, $args );

		$edit = get_edit_post_link( $event_id, 'raw' );
		return array(
			'id'        => $event_id,
			'edit_link' => $edit ? (string) $edit : '',
		);
	}

	/**
	 * Resolve taxonomy slugs to term IDs, creating missing terms when allowed.
	 *
	 * @param string   $taxonomy Taxonomy name.
	 * @param string[] $slugs    Term slugs.
	 * @return int[]
	 */
	public static function resolve_taxonomy_slugs_to_term_ids( $taxonomy, $slugs ) {
		$taxonomy = sanitize_key( (string) $taxonomy );
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$slugs    = is_array( $slugs ) ? $slugs : array();
		$term_ids = array();

		foreach ( array_unique( array_filter( array_map( 'sanitize_key', $slugs ) ) ) as $slug ) {
			if ( '' === $slug ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = (int) $term->term_id;
				continue;
			}

			$name    = ucwords( str_replace( '-', ' ', $slug ) );
			$created = wp_insert_term(
				$name,
				$taxonomy,
				array( 'slug' => $slug )
			);
			if ( is_wp_error( $created ) ) {
				if ( isset( $created->error_data['term_exists'] ) ) {
					$term_ids[] = (int) $created->error_data['term_exists'];
				}
				continue;
			}
			if ( isset( $created['term_id'] ) ) {
				$term_ids[] = (int) $created['term_id'];
			}
		}

		return array_values( array_unique( $term_ids ) );
	}

	/**
	 * Assign taxonomy terms to a post using slugs (creates terms when missing).
	 *
	 * @param int      $post_id  Post ID.
	 * @param string   $taxonomy Taxonomy name.
	 * @param string[] $slugs    Term slugs.
	 * @param bool     $append   Whether to append instead of replace.
	 * @return void
	 */
	public static function assign_taxonomy_slugs( $post_id, $taxonomy, $slugs, $append = false ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}

		$term_ids = self::resolve_taxonomy_slugs_to_term_ids( $taxonomy, $slugs );
		if ( empty( $term_ids ) ) {
			return;
		}

		wp_set_post_terms( $post_id, $term_ids, $taxonomy, (bool) $append );
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
