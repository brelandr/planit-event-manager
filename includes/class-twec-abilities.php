<?php
/**
 * WordPress Abilities API: PlanIt event discovery and safe writes for AI agents.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers planit-events abilities when enabled in settings (WP 6.9+).
 */
class TWEC_Abilities {

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Whether abilities should register on this request.
	 *
	 * @return bool
	 */
	private static function should_register() {
		if ( ! class_exists( 'TWEC_AI', false ) ) {
			return false;
		}
		return TWEC_AI::is_abilities_enabled();
	}

	/**
	 * Register ability category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! self::should_register() || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			'planit-events',
			array(
				'label'       => __( 'PlanIt Events', 'planit-event-manager' ),
				'description' => __( 'Discover and manage events from PlanIt Event Manager.', 'planit-event-manager' ),
			)
		);
	}

	/**
	 * Register abilities.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! self::should_register() ) {
			return;
		}

		$readonly_meta = array(
			'category'     => 'planit-events',
			'show_in_rest' => true,
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'mcp'          => array(
				'public' => true,
			),
		);

		wp_register_ability(
			'planit/list-upcoming-events',
			array(
				'label'               => __( 'List upcoming PlanIt events', 'planit-event-manager' ),
				'description'         => __( 'Returns upcoming published events with id, title, start date, and categories.', 'planit-event-manager' ),
				'category'            => 'planit-events',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'days'     => array(
							'type'    => 'integer',
							'default' => 30,
							'minimum' => 1,
							'maximum' => 365,
						),
						'category' => array(
							'type' => 'string',
						),
						'limit'    => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'         => array( 'type' => 'integer' ),
							'title'      => array( 'type' => 'string' ),
							'start_date' => array( 'type' => 'string' ),
							'categories' => array( 'type' => 'string' ),
							'url'        => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute_list_upcoming' ),
				'permission_callback' => array( __CLASS__, 'permission_read_events' ),
				'meta'                => $readonly_meta,
			)
		);

		wp_register_ability(
			'planit/get-event',
			array(
				'label'               => __( 'Get PlanIt event details', 'planit-event-manager' ),
				'description'         => __( 'Returns title, excerpt, planit_event meta, and permalink for one event.', 'planit-event-manager' ),
				'category'            => 'planit-events',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'event_id' => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
					),
					'required'   => array( 'event_id' ),
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => array( __CLASS__, 'execute_get_event' ),
				'permission_callback' => array( __CLASS__, 'permission_read_events' ),
				'meta'                => $readonly_meta,
			)
		);

		wp_register_ability(
			'planit/search-events',
			array(
				'label'               => __( 'Search PlanIt events', 'planit-event-manager' ),
				'description'         => __( 'Search events by keyword with optional date window.', 'planit-event-manager' ),
				'category'            => 'planit-events',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'       => array( 'type' => 'string' ),
						'twec_after'  => array( 'type' => 'string' ),
						'twec_before' => array( 'type' => 'string' ),
						'limit'       => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'execute_callback'    => array( __CLASS__, 'execute_search_events' ),
				'permission_callback' => array( __CLASS__, 'permission_read_events' ),
				'meta'                => $readonly_meta,
			)
		);

		wp_register_ability(
			'planit/create-event-draft',
			array(
				'label'               => __( 'Create PlanIt event draft', 'planit-event-manager' ),
				'description'         => __( 'Creates a draft twec_event with validated start/end dates.', 'planit-event-manager' ),
				'category'            => 'planit-events',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'            => array( 'type' => 'string' ),
						'start_date'       => array( 'type' => 'string' ),
						'end_date'         => array( 'type' => 'string' ),
						'all_day'          => array( 'type' => 'boolean' ),
						'venue_id'         => array( 'type' => 'integer' ),
						'organizer_id'     => array( 'type' => 'integer' ),
						'natural_language' => array( 'type' => 'string' ),
						'content'          => array( 'type' => 'string' ),
						'excerpt'          => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => array( 'type' => 'integer' ),
						'edit_link' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute_create_draft' ),
				'permission_callback' => array( __CLASS__, 'permission_create_events' ),
				'meta'                => array(
					'category'     => 'planit-events',
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'planit/update-event',
			array(
				'label'               => __( 'Update PlanIt event', 'planit-event-manager' ),
				'description'         => __( 'Updates an existing twec_event with validated fields.', 'planit-event-manager' ),
				'category'            => 'planit-events',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'event_id'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'        => array( 'type' => 'string' ),
						'content'      => array( 'type' => 'string' ),
						'excerpt'      => array( 'type' => 'string' ),
						'start_date'   => array( 'type' => 'string' ),
						'end_date'     => array( 'type' => 'string' ),
						'all_day'      => array( 'type' => 'boolean' ),
						'venue_id'     => array( 'type' => 'integer' ),
						'organizer_id' => array( 'type' => 'integer' ),
						'categories'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'tags'         => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'event_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => array( 'type' => 'integer' ),
						'edit_link' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute_update_event' ),
				'permission_callback' => array( __CLASS__, 'permission_create_events' ),
				'meta'                => array(
					'category'     => 'planit-events',
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		/**
		 * Fires after PlanIt registers core event abilities.
		 *
		 * @since 1.0.16
		 */
		do_action( 'twec_register_abilities' );
	}

	/**
	 * @return bool
	 */
	public static function permission_read_events() {
		return current_user_can( 'read' );
	}

	/**
	 * @return bool
	 */
	public static function permission_create_events() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Normalize ability input to array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	private static function normalize_input( $input ) {
		return is_array( $input ) ? $input : array();
	}

	/**
	 * Build summary row for an event post.
	 *
	 * @param WP_Post $post Event post.
	 * @return array<string, mixed>
	 */
	private static function event_summary_row( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return array();
		}
		$id       = (int) $post->ID;
		$start    = get_post_meta( $id, '_twec_event_start_date', true );
		$terms    = get_the_terms( $id, 'twec_event_category' );
		$cat_list = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( isset( $term->name ) ) {
					$cat_list[] = $term->name;
				}
			}
		}
		return array(
			'id'         => $id,
			'title'      => get_the_title( $post ),
			'start_date' => is_string( $start ) ? $start : '',
			'categories' => ! empty( $cat_list ) ? implode( ', ', $cat_list ) : '',
			'url'        => (string) get_permalink( $post ),
		);
	}

	/**
	 * List upcoming events ability.
	 *
	 * @param mixed $input Ability input.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function execute_list_upcoming( $input ) {
		$in     = self::normalize_input( $input );
		$days   = isset( $in['days'] ) ? max( 1, min( 365, (int) $in['days'] ) ) : 30;
		$limit  = isset( $in['limit'] ) ? max( 1, min( 100, (int) $in['limit'] ) ) : 20;
		$cat    = isset( $in['category'] ) ? sanitize_key( (string) $in['category'] ) : '';
		$after  = current_time( 'Y-m-d' );
		$before = gmdate( 'Y-m-d', strtotime( '+' . $days . ' days', strtotime( $after ) ) );

		$args = array(
			'post_type'      => 'twec_event',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'meta_value',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Event ordering.
			'meta_key'       => '_twec_event_start_date',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Date window.
			'meta_query'     => array(
				array(
					'key'     => '_twec_event_start_date',
					'value'   => $after,
					'compare' => '>=',
					'type'    => 'DATE',
				),
				array(
					'key'     => '_twec_event_start_date',
					'value'   => $before,
					'compare' => '<=',
					'type'    => 'DATE',
				),
			),
		);
		if ( '' !== $cat ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'twec_event_category',
					'field'    => 'slug',
					'terms'    => $cat,
				),
			);
		}

		$query = new WP_Query( $args );
		$out   = array();
		foreach ( $query->posts as $post ) {
			$row = self::event_summary_row( $post );
			if ( ! empty( $row ) ) {
				$out[] = $row;
			}
		}
		wp_reset_postdata();
		return $out;
	}

	/**
	 * Get single event ability.
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_get_event( $input ) {
		$in       = self::normalize_input( $input );
		$event_id = isset( $in['event_id'] ) ? (int) $in['event_id'] : 0;
		if ( $event_id <= 0 ) {
			return new WP_Error( 'twec_ability_invalid', __( 'event_id is required.', 'planit-event-manager' ) );
		}
		$post = get_post( $event_id );
		if ( ! $post || 'twec_event' !== $post->post_type ) {
			return new WP_Error( 'twec_ability_not_found', __( 'Event not found.', 'planit-event-manager' ) );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'twec_ability_forbidden', __( 'You cannot view this event.', 'planit-event-manager' ) );
		}
		$payload = class_exists( 'TWEC_REST', false ) ? TWEC_REST::get_event_payload( array( 'id' => $event_id ) ) : array();
		return array(
			'id'           => $event_id,
			'title'        => get_the_title( $post ),
			'excerpt'      => has_excerpt( $post ) ? get_the_excerpt( $post ) : '',
			'content'      => wp_strip_all_tags( (string) $post->post_content ),
			'planit_event' => $payload,
			'url'          => (string) get_permalink( $post ),
		);
	}

	/**
	 * Search events ability.
	 *
	 * @param mixed $input Ability input.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function execute_search_events( $input ) {
		$in     = self::normalize_input( $input );
		$query  = isset( $in['query'] ) ? sanitize_text_field( (string) $in['query'] ) : '';
		$limit  = isset( $in['limit'] ) ? max( 1, min( 100, (int) $in['limit'] ) ) : 20;
		$after  = isset( $in['twec_after'] ) ? sanitize_text_field( (string) $in['twec_after'] ) : '';
		$before = isset( $in['twec_before'] ) ? sanitize_text_field( (string) $in['twec_before'] ) : '';

		$args = array(
			'post_type'      => 'twec_event',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'meta_value',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Event ordering.
			'meta_key'       => '_twec_event_start_date',
			'order'          => 'ASC',
		);
		if ( '' !== $query ) {
			$args['s'] = $query;
		}
		$meta_query = array();
		if ( '' !== $after ) {
			$meta_query[] = array(
				'key'     => '_twec_event_start_date',
				'value'   => $after,
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}
		if ( '' !== $before ) {
			$meta_query[] = array(
				'key'     => '_twec_event_start_date',
				'value'   => $before,
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}
		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ability date filter.
			$args['meta_query'] = $meta_query;
		}

		$q   = new WP_Query( $args );
		$out = array();
		foreach ( $q->posts as $post ) {
			$row = self::event_summary_row( $post );
			if ( ! empty( $row ) ) {
				$out[] = $row;
			}
		}
		wp_reset_postdata();
		return $out;
	}

	/**
	 * Create draft event ability.
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_create_draft( $input ) {
		$in = self::normalize_input( $input );
		if ( ! empty( $in['natural_language'] ) && class_exists( 'TWEC_AI', false ) && TWEC_AI::is_text_generation_available() ) {
			$parsed = TWEC_AI::parse_event_from_natural_language( (string) $in['natural_language'] );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$in = array_merge( $parsed, $in );
			unset( $in['natural_language'] );
		}
		$in = (array) apply_filters( 'twec_ability_create_event_draft_args', $in );
		if ( ! class_exists( 'TWEC_REST', false ) || ! method_exists( 'TWEC_REST', 'create_event_draft_from_args' ) ) {
			return new WP_Error( 'twec_ability_unavailable', __( 'Event creation is not available.', 'planit-event-manager' ) );
		}
		return TWEC_REST::create_event_draft_from_args( $in );
	}

	/**
	 * Update event ability.
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_update_event( $input ) {
		$in = self::normalize_input( $input );
		$in = (array) apply_filters( 'twec_ability_update_event_args', $in );
		if ( ! class_exists( 'TWEC_REST', false ) || ! method_exists( 'TWEC_REST', 'update_event_from_args' ) ) {
			return new WP_Error( 'twec_ability_unavailable', __( 'Event updates are not available.', 'planit-event-manager' ) );
		}
		return TWEC_REST::update_event_from_args( $in );
	}
}
