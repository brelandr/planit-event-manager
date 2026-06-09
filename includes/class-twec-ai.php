<?php
/**
 * WordPress 7.0 AI Client integration for PlanIt events.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings, REST routes, and editor assist for AI-powered event workflows.
 */
class TWEC_AI {

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * @return void
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_event_editor_ai' ), 25 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_classic_metabox' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_entity_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_classic_ai' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_entity_ai' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_ai_connector_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_bulk_publish_prep_notice' ) );
		add_filter( 'bulk_actions-edit-twec_event', array( __CLASS__, 'register_bulk_publish_prep_action' ) );
		add_filter( 'handle_bulk_actions-edit-twec_event', array( __CLASS__, 'handle_bulk_publish_prep_action' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_bulk_publish_prep_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_public_assistant_assets' ) );
		add_shortcode( 'twec_event_search', array( __CLASS__, 'shortcode_event_search' ) );
	}

	/**
	 * @param string $key     Setting key suffix (ai_*).
	 * @param string $default Default yes/no.
	 * @return bool
	 */
	public static function is_setting_yes( $key, $default = 'no' ) {
		$val = TWEC_Settings::get( $key, $default );
		return ( 'yes' === (string) $val );
	}

	/**
	 * Master AI toggle.
	 *
	 * @return bool
	 */
	public static function is_master_enabled() {
		return self::is_setting_yes( 'ai_enabled', 'no' );
	}

	/**
	 * @return bool
	 */
	public static function is_admin_assist_enabled() {
		return self::is_master_enabled() && self::is_setting_yes( 'ai_admin_assist', 'no' );
	}

	/**
	 * @return bool
	 */
	public static function is_abilities_enabled() {
		return self::is_master_enabled() && self::is_setting_yes( 'ai_abilities', 'no' );
	}

	/**
	 * @return bool
	 */
	public static function is_public_assistant_enabled() {
		return self::is_master_enabled() && self::is_setting_yes( 'ai_public_assistant', 'no' );
	}

	/**
	 * @return bool
	 */
	public static function is_command_palette_enabled() {
		return self::is_master_enabled() && self::is_setting_yes( 'ai_command_palette', 'no' );
	}

	/**
	 * Whether bulk AI publish prep is enabled on the events list screen.
	 *
	 * @return bool
	 */
	public static function is_bulk_publish_prep_enabled() {
		return self::is_admin_assist_enabled()
			&& self::is_text_generation_available()
			&& self::is_setting_yes( 'ai_bulk_publish_prep', 'no' );
	}

	/**
	 * Admin URL for the WordPress Connectors screen (WP 7.0+ uses options-connectors.php).
	 *
	 * @return string
	 */
	public static function get_connectors_admin_url() {
		if ( defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/options-connectors.php' ) ) {
			return admin_url( 'options-connectors.php' );
		}
		return admin_url( 'options-general.php?page=connectors' );
	}

	/**
	 * Whether the current user can open the Connectors settings screen.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_connectors() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Whether WordPress AI is enabled for this request (not blocked by core).
	 *
	 * @return bool
	 */
	public static function is_ai_environment_enabled() {
		if ( defined( 'WP_AI_SUPPORT' ) && ! WP_AI_SUPPORT ) {
			return false;
		}
		if ( function_exists( 'wp_supports_ai' ) ) {
			return (bool) wp_supports_ai();
		}
		return true;
	}

	/**
	 * Whether any AI provider matches the Connectors screen "Connected" state.
	 *
	 * Mirrors core logic in `_wp_connectors_get_connector_script_module_data()`.
	 *
	 * @return bool
	 */
	public static function has_configured_ai_provider() {
		if ( ! function_exists( 'wp_get_connectors' ) || ! class_exists( 'WordPress\AiClient\AiClient', false ) ) {
			return false;
		}
		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			foreach ( wp_get_connectors() as $connector_id => $connector_data ) {
				if ( ! is_array( $connector_data ) || 'ai_provider' !== ( $connector_data['type'] ?? '' ) ) {
					continue;
				}
				$provider_id = sanitize_key( (string) $connector_id );
				if ( '' === $provider_id ) {
					continue;
				}
				if ( $registry->hasProvider( $provider_id ) && $registry->isProviderConfigured( $provider_id ) ) {
					return true;
				}
			}
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Registry probe.
			return false;
		}
		return false;
	}

	/**
	 * Names of configured AI providers (for admin status text).
	 *
	 * @return array<int, string>
	 */
	public static function get_configured_ai_provider_labels() {
		if ( ! function_exists( 'wp_get_connectors' ) || ! class_exists( 'WordPress\AiClient\AiClient', false ) ) {
			return array();
		}
		$labels = array();
		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			foreach ( wp_get_connectors() as $connector_id => $connector_data ) {
				if ( ! is_array( $connector_data ) || 'ai_provider' !== ( $connector_data['type'] ?? '' ) ) {
					continue;
				}
				$provider_id = sanitize_key( (string) $connector_id );
				if ( '' === $provider_id ) {
					continue;
				}
				if ( $registry->hasProvider( $provider_id ) && $registry->isProviderConfigured( $provider_id ) ) {
					$labels[] = isset( $connector_data['name'] ) ? (string) $connector_data['name'] : $provider_id;
				}
			}
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Registry probe.
			return array();
		}
		return $labels;
	}

	/**
	 * Whether WP AI Client text generation is available on this site.
	 *
	 * @return bool
	 */
	public static function is_text_generation_available() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}
		if ( ! self::is_ai_environment_enabled() ) {
			return false;
		}
		try {
			$builder = wp_ai_client_prompt();
			if ( is_object( $builder ) ) {
				// WP_AI_Client_Prompt_Builder routes support checks through __call; never use method_exists().
				if ( $builder->is_supported_for_text_generation() ) {
					return true;
				}
			}
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Feature probe.
			// Fall through to connector registry check.
		}
		return self::has_configured_ai_provider();
	}

	/**
	 * Temperature for public factual queries (low).
	 *
	 * @return float
	 */
	private static function public_temperature() {
		$preset = TWEC_Settings::get( 'ai_temperature_preset', 'factual' );
		if ( 'creative' === (string) $preset ) {
			return 0.7;
		}
		return 0.2;
	}

	/**
	 * Register PlanIt AI REST routes under `planit/v1`.
	 *
	 * Admin routes require per-post nonces and `edit_post` capability checks.
	 * The public query route is gated by settings, rate limits, and response caching.
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		$admin_routes     = array(
			'/ai/draft-description' => array( __CLASS__, 'rest_draft_description' ),
			'/ai/suggest-taxonomy'  => array( __CLASS__, 'rest_suggest_taxonomy' ),
			'/ai/social-snippet'    => array( __CLASS__, 'rest_social_snippet' ),
			'/ai/alt-text'          => array( __CLASS__, 'rest_alt_text' ),
			'/ai/publish-prep'      => array( __CLASS__, 'rest_publish_prep' ),
			'/ai/parse-event-draft' => array( __CLASS__, 'rest_parse_event_draft' ),
			'/ai/create-from-text'  => array( __CLASS__, 'rest_create_from_text' ),
		);
		$text_only_routes = array(
			'/ai/parse-event-draft',
			'/ai/create-from-text',
		);
		foreach ( $admin_routes as $route => $callback ) {
			$args = in_array( $route, $text_only_routes, true ) ? self::text_route_args() : self::admin_route_args();
			register_rest_route(
				'planit/v1',
				$route,
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => $callback,
					'permission_callback' => in_array( $route, $text_only_routes, true ) ? array( __CLASS__, 'rest_ai_text_permissions' ) : array( __CLASS__, 'rest_admin_ai_permissions' ),
					'args'                => $args,
				)
			);
		}

		$entity_routes = array(
			'/ai/venue-description' => array( __CLASS__, 'rest_venue_description' ),
			'/ai/organizer-bio'     => array( __CLASS__, 'rest_organizer_bio' ),
		);
		foreach ( $entity_routes as $route => $callback ) {
			register_rest_route(
				'planit/v1',
				$route,
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => $callback,
					'permission_callback' => array( __CLASS__, 'rest_entity_ai_permissions' ),
					'args'                => self::admin_route_args(),
				)
			);
		}

		register_rest_route(
			'planit/v1',
			'/ai/public-query',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_public_query' ),
				'permission_callback' => array( __CLASS__, 'rest_public_query_permissions' ),
				'args'                => array(
					'query'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'days'     => array(
						'type'              => 'integer',
						'default'           => 14,
						'sanitize_callback' => 'absint',
					),
					'category' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			'planit/v1',
			'/ai/event-search',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_event_search' ),
				'permission_callback' => array( __CLASS__, 'rest_public_query_permissions' ),
				'args'                => array(
					'query'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'days'     => array(
						'type'              => 'integer',
						'default'           => 60,
						'sanitize_callback' => 'absint',
					),
					'category' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_title',
					),
					'limit'    => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Resolve event post ID on post edit screens (includes auto-draft on post-new.php).
	 *
	 * @return int
	 */
	private static function get_editor_post_id() {
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Editor screen context only.
			$from_get = absint( wp_unslash( $_GET['post'] ) );
			if ( $from_get > 0 ) {
				return $from_get;
			}
		}
		global $post;
		if ( $post instanceof WP_Post && 'twec_event' === $post->post_type ) {
			return (int) $post->ID;
		}
		return 0;
	}

	/**
	 * Whether the classic editor is used for this event post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function uses_classic_editor( $post_id ) {
		if ( $post_id > 0 && function_exists( 'use_block_editor_for_post' ) ) {
			return ! use_block_editor_for_post( $post_id );
		}
		if ( function_exists( 'use_block_editor_for_post_type' ) ) {
			return ! use_block_editor_for_post_type( 'twec_event' );
		}
		return true;
	}

	/**
	 * Admin notice when AI is enabled but no connector is ready.
	 *
	 * @return void
	 */
	public static function maybe_ai_connector_notice() {
		if ( ! self::is_master_enabled() || self::is_text_generation_available() ) {
			return;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'twec_event' !== $screen->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$url = admin_url( 'edit.php?post_type=twec_event&page=twec-settings' );
		echo '<div class="notice notice-warning"><p>';
		$connectors_label = esc_html__( 'Settings → Connectors', 'planit-event-manager' );
		$connectors_link  = self::current_user_can_manage_connectors()
			? '<a href="' . esc_url( self::get_connectors_admin_url() ) . '">' . $connectors_label . '</a>'
			: $connectors_label;
		printf(
			/* translators: 1: PlanIt AI settings URL, 2: Connectors settings link or label. */
			esc_html__( 'PlanIt AI is enabled but no text-generation connector is configured. Configure AI under %1$s or add a provider under %2$s.', 'planit-event-manager' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Events → Settings → AI', 'planit-event-manager' ) . '</a>',
			$connectors_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_url/esc_html above.
		);
		echo '</p></div>';
	}

	/**
	 * Classic editor metabox for AI assist.
	 *
	 * @return void
	 */
	public static function register_classic_metabox() {
		if ( ! self::is_admin_assist_enabled() || ! self::is_text_generation_available() ) {
			return;
		}
		$post_id = self::get_editor_post_id();
		if ( $post_id > 0 && ! self::uses_classic_editor( $post_id ) ) {
			return;
		}
		add_meta_box(
			'twec-ai-assist',
			__( 'PlanIt AI Assist', 'planit-event-manager' ),
			array( __CLASS__, 'render_classic_metabox' ),
			'twec_event',
			'side',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_classic_metabox( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$post_id = (int) $post->ID;
		$file    = PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-event-ai-metabox.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		include $file;
	}

	/**
	 * Venue and organizer AI metaboxes.
	 *
	 * @return void
	 */
	public static function register_entity_metabox() {
		if ( ! self::is_admin_assist_enabled() || ! self::is_text_generation_available() ) {
			return;
		}
		foreach ( array( 'twec_venue', 'twec_organizer' ) as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			add_meta_box(
				'twec-entity-ai-assist',
				__( 'PlanIt AI Assist', 'planit-event-manager' ),
				array( __CLASS__, 'render_entity_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_entity_metabox( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$file = PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-entity-ai-metabox.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		include $file;
	}

	/**
	 * Enqueue classic editor AI script.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function enqueue_classic_ai( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( ! self::is_admin_assist_enabled() || ! self::is_text_generation_available() ) {
			return;
		}
		$post_id = self::get_editor_post_id();
		if ( $post_id > 0 && ! self::uses_classic_editor( $post_id ) ) {
			return;
		}
		$file = PLANIT_EVENT_MANAGER_DIR . 'admin/js/twec-event-ai-classic.js';
		if ( ! is_readable( $file ) ) {
			return;
		}
		wp_enqueue_script(
			'twec-event-ai-classic',
			PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-event-ai-classic.js',
			array( 'jquery' ),
			(string) filemtime( $file ),
			true
		);
	}

	/**
	 * Enqueue venue/organizer AI assist script.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function enqueue_entity_ai( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( ! self::is_admin_assist_enabled() || ! self::is_text_generation_available() ) {
			return;
		}

		$post_type = '';
		$post_id   = 0;
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Editor screen context only.
			$post_id = absint( wp_unslash( $_GET['post'] ) );
		}
		if ( $post_id > 0 ) {
			$post_type = (string) get_post_type( $post_id );
		} else {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && isset( $screen->post_type ) ) {
				$post_type = (string) $screen->post_type;
			}
		}
		if ( ! in_array( $post_type, array( 'twec_venue', 'twec_organizer' ), true ) ) {
			return;
		}

		$file = PLANIT_EVENT_MANAGER_DIR . 'admin/js/twec-entity-ai.js';
		if ( ! is_readable( $file ) ) {
			return;
		}
		wp_enqueue_script(
			'twec-entity-ai',
			PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-entity-ai.js',
			array( 'jquery' ),
			(string) filemtime( $file ),
			true
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function admin_route_args() {
		return array(
			'post_id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'nonce'   => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function text_route_args() {
		return array(
			'text' => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * Permissions for NL parse/create routes (no post_id).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function rest_ai_text_permissions( $request ) {
		unset( $request );
		if ( ! self::is_master_enabled() || ! self::is_setting_yes( 'ai_admin_assist', 'no' ) ) {
			return new WP_Error( 'twec_ai_disabled', __( 'Enable Event editor assist under Events → Settings → AI.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		if ( ! self::is_text_generation_available() ) {
			return new WP_Error( 'twec_ai_unavailable', __( 'AI text generation is not configured.', 'planit-event-manager' ), array( 'status' => 503 ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'twec_ai_forbidden', __( 'You cannot create events.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Permissions for venue/organizer AI routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function rest_entity_ai_permissions( $request ) {
		if ( ! self::is_master_enabled() || ! self::is_setting_yes( 'ai_admin_assist', 'no' ) ) {
			return new WP_Error( 'twec_ai_disabled', __( 'Enable Event editor assist under Events → Settings → AI.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		if ( ! self::is_text_generation_available() ) {
			return new WP_Error( 'twec_ai_unavailable', __( 'AI text generation is not configured.', 'planit-event-manager' ), array( 'status' => 503 ) );
		}
		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return false;
		}
		$post_id = (int) $request->get_param( 'post_id' );
		$nonce   = sanitize_text_field( (string) $request->get_param( 'nonce' ) );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'twec_ai_post', __( 'Invalid post.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, array( 'twec_venue', 'twec_organizer' ), true ) ) {
			return new WP_Error( 'twec_ai_context', __( 'Invalid venue or organizer.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'twec_ai_assist_' . $post_id ) ) {
			return new WP_Error( 'twec_ai_nonce', __( 'Security check failed. Reload the editor and try again.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'twec_ai_forbidden', __( 'You cannot edit this item.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function rest_admin_ai_permissions( $request ) {
		if ( ! self::is_master_enabled() ) {
			return new WP_Error(
				'twec_ai_disabled',
				__( 'Enable PlanIt AI under Events → Settings → AI.', 'planit-event-manager' ),
				array( 'status' => 403 )
			);
		}
		if ( ! self::is_setting_yes( 'ai_admin_assist', 'no' ) ) {
			return new WP_Error(
				'twec_ai_disabled',
				__( 'Enable Event editor assist under Events → Settings → AI.', 'planit-event-manager' ),
				array( 'status' => 403 )
			);
		}
		if ( ! self::is_text_generation_available() ) {
			return new WP_Error(
				'twec_ai_unavailable',
				__( 'AI text generation is not configured. Add a provider under Settings → Connectors.', 'planit-event-manager' ),
				array( 'status' => 503 )
			);
		}
		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return false;
		}
		$post_id = (int) $request->get_param( 'post_id' );
		$nonce   = sanitize_text_field( (string) $request->get_param( 'nonce' ) );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'twec_ai_post', __( 'Save the event draft first, then try again.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'twec_ai_assist_' . $post_id ) ) {
			return new WP_Error( 'twec_ai_nonce', __( 'Security check failed. Reload the editor and try again.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'twec_ai_forbidden', __( 'You cannot edit this event.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function rest_public_query_permissions( $request ) {
		if ( ! self::is_public_assistant_enabled() || ! self::is_text_generation_available() ) {
			return false;
		}
		return $request instanceof WP_REST_Request;
	}

	/**
	 * @param int $post_id Event ID.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function get_event_context( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post || 'twec_event' !== $post->post_type ) {
			return new WP_Error( 'twec_ai_context', __( 'Invalid event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$venue_name     = '';
		$venue_id       = (int) get_post_meta( $post_id, '_twec_event_venue', true );
		$organizer_name = '';
		$organizer_id   = (int) get_post_meta( $post_id, '_twec_event_organizer', true );
		if ( $venue_id > 0 ) {
			$venue_name = get_the_title( $venue_id );
		}
		if ( $organizer_id > 0 ) {
			$organizer_name = get_the_title( $organizer_id );
		}
		$cats   = wp_get_post_terms( $post_id, 'twec_event_category', array( 'fields' => 'names' ) );
		$tags   = wp_get_post_terms( $post_id, 'twec_event_tag', array( 'fields' => 'names' ) );
		$planit = class_exists( 'TWEC_REST', false ) ? TWEC_REST::get_event_payload( array( 'id' => $post_id ) ) : array();
		return array(
			'title'         => get_the_title( $post ),
			'excerpt'       => has_excerpt( $post ) ? get_the_excerpt( $post ) : '',
			'content'       => $post->post_content,
			'venue'         => is_string( $venue_name ) ? $venue_name : '',
			'organizer'     => is_string( $organizer_name ) ? $organizer_name : '',
			'categories'    => is_array( $cats ) ? $cats : array(),
			'tags'          => is_array( $tags ) ? $tags : array(),
			'has_thumbnail' => has_post_thumbnail( $post_id ),
			'planit_event'  => $planit,
		);
	}

	/**
	 * @param int $post_id Venue or organizer ID.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function get_entity_context( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'twec_venue', 'twec_organizer' ), true ) ) {
			return new WP_Error( 'twec_ai_context', __( 'Invalid venue or organizer.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$meta = array();
		if ( 'twec_venue' === $post->post_type ) {
			foreach ( array( '_twec_venue_address', '_twec_venue_city', '_twec_venue_state', '_twec_venue_zip', '_twec_venue_country', '_twec_venue_phone', '_twec_venue_website' ) as $key ) {
				$meta[ $key ] = (string) get_post_meta( $post_id, $key, true );
			}
		} else {
			foreach ( array( '_twec_organizer_phone', '_twec_organizer_email', '_twec_organizer_website' ) as $key ) {
				$meta[ $key ] = (string) get_post_meta( $post_id, $key, true );
			}
		}
		return array(
			'title'     => get_the_title( $post ),
			'content'   => $post->post_content,
			'post_type' => $post->post_type,
			'meta'      => $meta,
		);
	}

	/**
	 * Parse natural-language event text into structured fields.
	 *
	 * @param string $text Natural language description.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function parse_event_from_natural_language( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return new WP_Error( 'twec_ai_text', __( 'Describe the event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'title'          => array( 'type' => 'string' ),
				'start_date'     => array( 'type' => 'string' ),
				'end_date'       => array( 'type' => 'string' ),
				'start_time'     => array( 'type' => 'string' ),
				'end_time'       => array( 'type' => 'string' ),
				'all_day'        => array( 'type' => 'boolean' ),
				'venue_name'     => array( 'type' => 'string' ),
				'organizer_name' => array( 'type' => 'string' ),
				'description'    => array( 'type' => 'string' ),
				'excerpt'        => array( 'type' => 'string' ),
			),
			'required'   => array( 'title', 'start_date', 'end_date' ),
		);
		$prompt = sprintf(
			'Parse this event description into structured fields. Use Y-m-d for dates. Today is %s in the site timezone. Text: %s',
			wp_date( 'Y-m-d' ),
			$text
		);
		$json   = self::request_text( $prompt, $schema, 0.2 );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$data = self::decode_ai_json( $json );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twec_ai_parse', __( 'Could not parse event text.', 'planit-event-manager' ) );
		}
		return self::normalize_parsed_event_args( $data );
	}

	/**
	 * @param array<string, mixed> $data Parsed AI output.
	 * @return array<string, mixed>
	 */
	private static function normalize_parsed_event_args( $data ) {
		$out = array(
			'title'      => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
			'start_date' => isset( $data['start_date'] ) ? sanitize_text_field( (string) $data['start_date'] ) : '',
			'end_date'   => isset( $data['end_date'] ) ? sanitize_text_field( (string) $data['end_date'] ) : '',
			'all_day'    => ! empty( $data['all_day'] ),
		);
		if ( ! empty( $data['start_time'] ) ) {
			$out['start_time'] = sanitize_text_field( (string) $data['start_time'] );
		}
		if ( ! empty( $data['end_time'] ) ) {
			$out['end_time'] = sanitize_text_field( (string) $data['end_time'] );
		}
		if ( ! empty( $data['description'] ) ) {
			$out['content'] = wp_kses_post( (string) $data['description'] );
		}
		if ( ! empty( $data['excerpt'] ) ) {
			$out['excerpt'] = sanitize_text_field( (string) $data['excerpt'] );
		}
		if ( ! empty( $data['venue_name'] ) && class_exists( 'TWEC_REST', false ) ) {
			$vid = TWEC_REST::resolve_venue_id_by_name( (string) $data['venue_name'] );
			if ( $vid > 0 ) {
				$out['venue_id'] = $vid;
			}
		}
		if ( ! empty( $data['organizer_name'] ) && class_exists( 'TWEC_REST', false ) ) {
			$oid = TWEC_REST::resolve_organizer_id_by_name( (string) $data['organizer_name'] );
			if ( $oid > 0 ) {
				$out['organizer_id'] = $oid;
			}
		}
		return $out;
	}

	/**
	 * Deterministic publish readiness checks for an event.
	 *
	 * @param array<string, mixed> $ctx Event context from get_event_context().
	 * @return array<int, array<string, string>>
	 */
	private static function build_publish_checks( $ctx ) {
		$checks = array();
		$add    = static function ( $field, $status, $message ) use ( &$checks ) {
			$checks[] = array(
				'field'   => (string) $field,
				'status'  => (string) $status,
				'message' => (string) $message,
			);
		};
		if ( '' === trim( (string) ( $ctx['title'] ?? '' ) ) ) {
			$add( 'title', 'missing', __( 'Add an event title.', 'planit-event-manager' ) );
		} else {
			$add( 'title', 'ok', __( 'Title is set.', 'planit-event-manager' ) );
		}
		$start = isset( $ctx['planit_event']['start_date'] ) ? (string) $ctx['planit_event']['start_date'] : '';
		if ( '' === $start ) {
			$add( 'dates', 'missing', __( 'Set start and end dates.', 'planit-event-manager' ) );
		} else {
			$add( 'dates', 'ok', __( 'Event dates are set.', 'planit-event-manager' ) );
		}
		if ( '' === trim( (string) ( $ctx['venue'] ?? '' ) ) ) {
			$add( 'venue', 'warn', __( 'Consider linking a venue.', 'planit-event-manager' ) );
		} else {
			$add( 'venue', 'ok', __( 'Venue is linked.', 'planit-event-manager' ) );
		}
		if ( '' === trim( (string) ( $ctx['excerpt'] ?? '' ) ) ) {
			$add( 'excerpt', 'warn', __( 'Add a short excerpt for listings.', 'planit-event-manager' ) );
		} else {
			$add( 'excerpt', 'ok', __( 'Excerpt is set.', 'planit-event-manager' ) );
		}
		if ( empty( $ctx['has_thumbnail'] ) ) {
			$add( 'image', 'warn', __( 'Add a featured image.', 'planit-event-manager' ) );
		} else {
			$add( 'image', 'ok', __( 'Featured image is set.', 'planit-event-manager' ) );
		}
		if ( empty( $ctx['categories'] ) ) {
			$add( 'categories', 'warn', __( 'Assign at least one category.', 'planit-event-manager' ) );
		} else {
			$add( 'categories', 'ok', __( 'Categories are assigned.', 'planit-event-manager' ) );
		}
		return $checks;
	}

	/**
	 * Normalize a JSON schema for OpenAI structured output (strict mode).
	 *
	 * @param mixed $schema Schema fragment.
	 * @return mixed
	 */
	public static function normalize_ai_response_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		$normalized = $schema;

		if ( isset( $normalized['type'] ) && 'object' === $normalized['type'] ) {
			if ( ! array_key_exists( 'additionalProperties', $normalized ) ) {
				$normalized['additionalProperties'] = false;
			}
			// OpenAI strict JSON schema: required must list every key in properties.
			if ( false === $normalized['additionalProperties'] && ! empty( $normalized['properties'] ) && is_array( $normalized['properties'] ) ) {
				$normalized['required'] = array_values( array_keys( $normalized['properties'] ) );
			}
		}

		if ( ! empty( $normalized['properties'] ) && is_array( $normalized['properties'] ) ) {
			foreach ( $normalized['properties'] as $key => $prop_schema ) {
				$normalized['properties'][ $key ] = self::normalize_ai_response_schema( $prop_schema );
			}
		}

		if ( isset( $normalized['items'] ) ) {
			$normalized['items'] = self::normalize_ai_response_schema( $normalized['items'] );
		}

		foreach ( array( 'allOf', 'anyOf', 'oneOf', 'prefixItems' ) as $composite_key ) {
			if ( empty( $normalized[ $composite_key ] ) || ! is_array( $normalized[ $composite_key ] ) ) {
				continue;
			}
			foreach ( $normalized[ $composite_key ] as $index => $sub_schema ) {
				$normalized[ $composite_key ][ $index ] = self::normalize_ai_response_schema( $sub_schema );
			}
		}

		return $normalized;
	}

	/**
	 * Decode JSON from an AI response (tolerates optional markdown fences).
	 *
	 * @param string $raw Raw model output.
	 * @return array<string, mixed>|null
	 */
	private static function decode_ai_json( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		if ( preg_match( '/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $matches ) ) {
			$data = json_decode( $matches[1], true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return null;
	}

	/**
	 * Whether a provider error indicates temperature is unsupported for the active model.
	 *
	 * @param WP_Error $error Error object.
	 * @return bool
	 */
	private static function is_temperature_unsupported_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}
		$msg = strtolower( $error->get_error_message() );
		return false !== strpos( $msg, 'temperature' )
			&& ( false !== strpos( $msg, 'not supported' ) || false !== strpos( $msg, 'unsupported' ) );
	}

	/**
	 * Run one WP AI Client text generation attempt.
	 *
	 * @param string     $prompt Prompt text.
	 * @param array|null $schema Optional JSON schema.
	 * @param float|null $temp   Temperature, or null to omit.
	 * @return string|WP_Error
	 */
	private static function run_wp_ai_text_generation_once( $prompt, $schema = null, $temp = null ) {
		try {
			$builder = wp_ai_client_prompt( (string) $prompt );
			if ( null !== $temp ) {
				$builder = $builder->using_temperature( (float) $temp );
			}
			if ( is_array( $schema ) ) {
				// Routed through __call; do not use method_exists().
				$builder = $builder->as_json_response( self::normalize_ai_response_schema( $schema ) );
			}
			return $builder->generate_text();
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Provider errors.
			return new WP_Error(
				'twec_ai_provider',
				sprintf(
					/* translators: %s: error message from AI provider. */
					__( 'AI provider error: %s', 'planit-event-manager' ),
					sanitize_text_field( $e->getMessage() )
				),
				array( 'status' => 502 )
			);
		}
	}

	/**
	 * Request text from the configured WordPress AI connector (retries without temperature when unsupported).
	 *
	 * Delegates outbound provider HTTP to core `wp_ai_client_*` APIs; returns `WP_Error` on provider failure.
	 *
	 * @param string     $prompt Prompt text.
	 * @param array|null $schema Optional JSON schema for structured responses.
	 * @param float|null $temp   Temperature; null skips the parameter entirely.
	 * @return string|WP_Error Generated text or error.
	 */
	public static function request_text( $prompt, $schema = null, $temp = 0.5 ) {
		if ( ! self::is_text_generation_available() ) {
			return new WP_Error( 'twec_ai_unavailable', __( 'AI text generation is not configured. Add a provider under Settings → Connectors.', 'planit-event-manager' ), array( 'status' => 503 ) );
		}

		$use_temperature = apply_filters( 'twec_ai_use_temperature', null !== $temp, $temp, $schema, $prompt );
		$result          = null;

		if ( $use_temperature && null !== $temp ) {
			$result = self::run_wp_ai_text_generation_once( $prompt, $schema, $temp );
			if ( is_wp_error( $result ) && self::is_temperature_unsupported_error( $result ) ) {
				$result = self::run_wp_ai_text_generation_once( $prompt, $schema, null );
			}
		} else {
			$result = self::run_wp_ai_text_generation_once( $prompt, $schema, null );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return (string) $result;
	}

	/**
	 * @param string     $prompt Prompt text.
	 * @param array|null $schema Optional JSON schema.
	 * @param float|null $temp   Temperature.
	 * @return string|WP_Error
	 */
	private static function generate_text( $prompt, $schema = null, $temp = 0.5 ) {
		return self::request_text( $prompt, $schema, $temp );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_draft_description( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$ctx     = self::get_event_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		$prompt = sprintf(
			'You are helping write a WordPress event page. Event title: %s. Start: %s. End: %s. Venue: %s. Categories: %s. Write 2-3 engaging paragraphs for the event description and a one-sentence excerpt. Respond as JSON with keys description and excerpt only.',
			$ctx['title'],
			isset( $ctx['planit_event']['start_date'] ) ? (string) $ctx['planit_event']['start_date'] : '',
			isset( $ctx['planit_event']['end_date'] ) ? (string) $ctx['planit_event']['end_date'] : '',
			$ctx['venue'],
			implode( ', ', $ctx['categories'] )
		);
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'description' => array( 'type' => 'string' ),
				'excerpt'     => array( 'type' => 'string' ),
			),
			'required'   => array( 'description', 'excerpt' ),
		);
		$json   = self::generate_text( $prompt, $schema, 0.6 );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$data = self::decode_ai_json( $json );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twec_ai_parse', __( 'Could not parse AI response.', 'planit-event-manager' ) );
		}
		return rest_ensure_response(
			array(
				'description' => isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : '',
				'excerpt'     => isset( $data['excerpt'] ) ? sanitize_text_field( (string) $data['excerpt'] ) : '',
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_suggest_taxonomy( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$ctx     = self::get_event_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		$result = self::suggest_taxonomy_slugs_for_context( $ctx );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Comma-separated list of event category and tag slugs on this site.
	 *
	 * @return string
	 */
	private static function get_site_taxonomy_slug_list() {
		$existing = get_terms(
			array(
				'taxonomy'   => array( 'twec_event_category', 'twec_event_tag' ),
				'hide_empty' => false,
				'fields'     => 'id=>slug',
			)
		);
		return is_array( $existing ) ? implode( ', ', array_values( $existing ) ) : '';
	}

	/**
	 * Sanitize AI taxonomy slug arrays.
	 *
	 * @param mixed $raw Raw categories or tags from AI JSON.
	 * @return string[]
	 */
	private static function sanitize_taxonomy_slug_list( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $item ) {
			$slug = sanitize_key( (string) $item );
			if ( '' !== $slug ) {
				$out[] = $slug;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Suggest category and tag slugs for an event context.
	 *
	 * @param array<string, mixed> $ctx Event context from get_event_context().
	 * @return array{categories: string[], tags: string[]}|WP_Error
	 */
	private static function suggest_taxonomy_slugs_for_context( $ctx ) {
		$slug_list = self::get_site_taxonomy_slug_list();
		$prompt    = sprintf(
			'Event: %s. Existing taxonomy slugs on this site: %s. Suggest up to 3 category slugs and 3 tag slugs from the list or new hyphenated slugs. Include at least one category slug. JSON keys: categories (array of strings), tags (array of strings).',
			isset( $ctx['title'] ) ? (string) $ctx['title'] : '',
			$slug_list
		);
		$schema    = array(
			'type'       => 'object',
			'properties' => array(
				'categories' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'tags'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'categories', 'tags' ),
		);
		$json      = self::generate_text( $prompt, $schema, 0.3 );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$data = self::decode_ai_json( $json );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twec_ai_parse', __( 'Could not parse AI response.', 'planit-event-manager' ) );
		}

		return array(
			'categories' => self::sanitize_taxonomy_slug_list( isset( $data['categories'] ) ? $data['categories'] : array() ),
			'tags'       => self::sanitize_taxonomy_slug_list( isset( $data['tags'] ) ? $data['tags'] : array() ),
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_social_snippet( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$ctx     = self::get_event_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		$prompt = sprintf(
			'Write a short social sharing blurb (max 200 characters) for this event: %s on %s. Plain text only.',
			$ctx['title'],
			isset( $ctx['planit_event']['start_date'] ) ? (string) $ctx['planit_event']['start_date'] : ''
		);
		$text   = self::generate_text( $prompt, null, 0.5 );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return rest_ensure_response( array( 'snippet' => sanitize_text_field( $text ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_alt_text( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$ctx     = self::get_event_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id <= 0 ) {
			return new WP_Error( 'twec_ai_no_image', __( 'This event has no featured image.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$prompt = sprintf(
			'Write concise image alt text (max 125 characters) for a featured image on an event page titled: %s.',
			$ctx['title']
		);
		$text   = self::generate_text( $prompt, null, 0.3 );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return rest_ensure_response( array( 'alt_text' => sanitize_text_field( $text ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_publish_prep( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$result  = self::run_publish_prep_for_event( $post_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Run publish prep for a single event (shared by REST and bulk actions).
	 *
	 * @param int $post_id Event ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function run_publish_prep_for_event( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || 'twec_event' !== get_post_type( $post_id ) ) {
			return new WP_Error( 'twec_ai_post', __( 'Invalid event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'twec_ai_forbidden', __( 'You cannot edit this event.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$ctx = self::get_event_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}

		$checks               = self::build_publish_checks( $ctx );
		$needs_categories     = empty( $ctx['categories'] );
		$site_taxonomy_list   = self::get_site_taxonomy_slug_list();
		$schema               = array(
			'type'       => 'object',
			'properties' => array(
				'description'    => array( 'type' => 'string' ),
				'excerpt'        => array( 'type' => 'string' ),
				'social_snippet' => array( 'type' => 'string' ),
				'alt_text'       => array( 'type' => 'string' ),
				'categories'     => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'tags'           => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'summary'        => array( 'type' => 'string' ),
			),
			'required'   => array( 'description', 'excerpt', 'social_snippet', 'summary' ),
		);
		$category_instruction = $needs_categories
			? 'This event has no categories assigned: you MUST include at least one category slug in the categories array, chosen from the site taxonomy slugs or as a new hyphenated slug.'
			: 'Include category slugs in the categories array when refining taxonomy (use site slugs when possible).';
		$prompt               = sprintf(
			'Prepare publish-ready copy for this event. Title: %1$s. Dates: %2$s – %3$s. Venue: %4$s. Organizer: %5$s. Current category names: %6$s. Site taxonomy slugs: %7$s. Return JSON with description, excerpt, social_snippet (max 200 chars), alt_text (empty string if no featured image is expected), categories (array of hyphenated slugs), tags (array of hyphenated slugs), and a one-sentence summary. %8$s',
			$ctx['title'],
			isset( $ctx['planit_event']['start_date'] ) ? (string) $ctx['planit_event']['start_date'] : '',
			isset( $ctx['planit_event']['end_date'] ) ? (string) $ctx['planit_event']['end_date'] : '',
			$ctx['venue'],
			$ctx['organizer'],
			implode( ', ', $ctx['categories'] ),
			$site_taxonomy_list,
			$category_instruction
		);
		$json                 = self::generate_text( $prompt, $schema, 0.5 );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$data = self::decode_ai_json( $json );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twec_ai_parse', __( 'Could not parse AI response.', 'planit-event-manager' ) );
		}

		$categories = self::sanitize_taxonomy_slug_list( isset( $data['categories'] ) ? $data['categories'] : array() );
		$tags       = self::sanitize_taxonomy_slug_list( isset( $data['tags'] ) ? $data['tags'] : array() );

		if ( empty( $categories ) && $needs_categories ) {
			$suggested = self::suggest_taxonomy_slugs_for_context( $ctx );
			if ( ! is_wp_error( $suggested ) ) {
				if ( ! empty( $suggested['categories'] ) ) {
					$categories = $suggested['categories'];
				}
				if ( empty( $tags ) && ! empty( $suggested['tags'] ) ) {
					$tags = $suggested['tags'];
				}
			}
		}

		$ready = true;
		foreach ( $checks as $row ) {
			if ( isset( $row['status'] ) && 'missing' === $row['status'] ) {
				$ready = false;
				break;
			}
		}

		return array(
			'ready'          => $ready,
			'checks'         => $checks,
			'description'    => isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : '',
			'excerpt'        => isset( $data['excerpt'] ) ? sanitize_text_field( (string) $data['excerpt'] ) : '',
			'social_snippet' => isset( $data['social_snippet'] ) ? sanitize_text_field( (string) $data['social_snippet'] ) : '',
			'alt_text'       => isset( $data['alt_text'] ) ? sanitize_text_field( (string) $data['alt_text'] ) : '',
			'categories'     => $categories,
			'tags'           => $tags,
			'summary'        => isset( $data['summary'] ) ? sanitize_text_field( (string) $data['summary'] ) : '',
		);
	}

	/**
	 * Apply publish prep output to an event post.
	 *
	 * @param int                  $post_id Event ID.
	 * @param array<string, mixed> $prep    Output from run_publish_prep_for_event().
	 * @return true|WP_Error
	 */
	public static function apply_publish_prep_for_event( $post_id, $prep ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! is_array( $prep ) ) {
			return new WP_Error( 'twec_ai_apply', __( 'Invalid publish prep data.', 'planit-event-manager' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'twec_ai_forbidden', __( 'You cannot edit this event.', 'planit-event-manager' ) );
		}

		$args = array( 'event_id' => $post_id );
		if ( ! empty( $prep['description'] ) ) {
			$args['content'] = (string) $prep['description'];
		}
		if ( ! empty( $prep['excerpt'] ) ) {
			$args['excerpt'] = (string) $prep['excerpt'];
		}
		if ( ! empty( $prep['categories'] ) && is_array( $prep['categories'] ) ) {
			$args['categories'] = $prep['categories'];
		}
		if ( ! empty( $prep['tags'] ) && is_array( $prep['tags'] ) ) {
			$args['tags'] = $prep['tags'];
		}

		if ( class_exists( 'TWEC_REST', false ) ) {
			if ( count( $args ) > 1 ) {
				$updated = TWEC_REST::update_event_from_args( $args );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			} else {
				if ( ! empty( $prep['categories'] ) && is_array( $prep['categories'] ) ) {
					TWEC_REST::assign_taxonomy_slugs( $post_id, 'twec_event_category', $prep['categories'], false );
				}
				if ( ! empty( $prep['tags'] ) && is_array( $prep['tags'] ) ) {
					TWEC_REST::assign_taxonomy_slugs( $post_id, 'twec_event_tag', $prep['tags'], false );
				}
			}
		}

		if ( ! empty( $prep['social_snippet'] ) ) {
			update_post_meta( $post_id, '_twec_ai_social_snippet', sanitize_text_field( (string) $prep['social_snippet'] ) );
		}

		if ( ! empty( $prep['alt_text'] ) ) {
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb_id > 0 && current_user_can( 'edit_post', $thumb_id ) ) {
				update_post_meta( $thumb_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $prep['alt_text'] ) );
			}
		}

		/**
		 * Fires after bulk or automated publish prep is applied to an event.
		 *
		 * @param int                  $post_id Event ID.
		 * @param array<string, mixed> $prep    Applied prep payload.
		 */
		do_action( 'twec_after_publish_prep_applied', $post_id, $prep );

		return true;
	}

	/**
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public static function register_bulk_publish_prep_action( $actions ) {
		if ( ! self::is_bulk_publish_prep_enabled() ) {
			return $actions;
		}
		$actions['twec_ai_bulk_publish_prep'] = __( 'AI Publish prep (apply)', 'planit-event-manager' );
		return $actions;
	}

	/**
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Bulk action.
	 * @param array  $post_ids     Post IDs.
	 * @return string
	 */
	public static function handle_bulk_publish_prep_action( $redirect_url, $action, $post_ids ) {
		if ( 'twec_ai_bulk_publish_prep' !== $action || ! self::is_bulk_publish_prep_enabled() ) {
			return $redirect_url;
		}

		$post_ids = array_map( 'absint', (array) $post_ids );
		$post_ids = array_values( array_filter( $post_ids ) );
		if ( empty( $post_ids ) ) {
			return $redirect_url;
		}

		$limit    = (int) apply_filters( 'twec_ai_bulk_publish_prep_limit', 15 );
		$limit    = max( 1, min( 50, $limit ) );
		$skipped  = 0;
		$success  = 0;
		$failed   = 0;
		$messages = array();

		if ( count( $post_ids ) > $limit ) {
			$skipped  = count( $post_ids ) - $limit;
			$post_ids = array_slice( $post_ids, 0, $limit );
		}

		foreach ( $post_ids as $post_id ) {
			$prep = self::run_publish_prep_for_event( $post_id );
			if ( is_wp_error( $prep ) ) {
				++$failed;
				if ( count( $messages ) < 5 ) {
					$messages[] = sprintf(
						/* translators: 1: event ID, 2: error message */
						__( 'Event #%1$d: %2$s', 'planit-event-manager' ),
						$post_id,
						$prep->get_error_message()
					);
				}
				continue;
			}

			$applied = self::apply_publish_prep_for_event( $post_id, $prep );
			if ( is_wp_error( $applied ) ) {
				++$failed;
				if ( count( $messages ) < 5 ) {
					$messages[] = sprintf(
						/* translators: 1: event ID, 2: error message */
						__( 'Event #%1$d: %2$s', 'planit-event-manager' ),
						$post_id,
						$applied->get_error_message()
					);
				}
				continue;
			}

			++$success;
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient(
				'twec_bulk_publish_prep_' . $user_id,
				array(
					'success'  => $success,
					'failed'   => $failed,
					'skipped'  => $skipped,
					'messages' => $messages,
				),
				60
			);
		}

		return add_query_arg( 'twec_bulk_publish_prep', '1', $redirect_url );
	}

	/**
	 * @return void
	 */
	public static function maybe_bulk_publish_prep_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query flag after bulk redirect.
		if ( ! isset( $_GET['twec_bulk_publish_prep'] ) ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$report = get_transient( 'twec_bulk_publish_prep_' . $user_id );
		delete_transient( 'twec_bulk_publish_prep_' . $user_id );
		if ( ! is_array( $report ) ) {
			return;
		}

		$success = isset( $report['success'] ) ? (int) $report['success'] : 0;
		$failed  = isset( $report['failed'] ) ? (int) $report['failed'] : 0;
		$skipped = isset( $report['skipped'] ) ? (int) $report['skipped'] : 0;
		$class   = ( $failed > 0 ) ? 'notice-warning' : 'notice-success';

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>';
		printf(
			/* translators: 1: success count, 2: failed count */
			esc_html__( 'AI Publish prep finished: %1$d updated, %2$d failed.', 'planit-event-manager' ),
			$success,
			$failed
		);
		if ( $skipped > 0 ) {
			printf(
				' %s',
				esc_html(
					sprintf(
						/* translators: %d: number of events not processed due to limit */
						__( '%d additional events were skipped (bulk limit).', 'planit-event-manager' ),
						$skipped
					)
				)
			);
		}
		echo '</p>';
		if ( ! empty( $report['messages'] ) && is_array( $report['messages'] ) ) {
			echo '<ul>';
			foreach ( $report['messages'] as $line ) {
				echo '<li>' . esc_html( (string) $line ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public static function enqueue_bulk_publish_prep_scripts( $hook ) {
		if ( 'edit.php' !== $hook || ! self::is_bulk_publish_prep_enabled() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'twec_event' !== $screen->post_type ) {
			return;
		}
		$file = PLANIT_EVENT_MANAGER_DIR . 'admin/js/twec-ai-bulk-publish-prep.js';
		if ( ! is_readable( $file ) ) {
			return;
		}
		wp_enqueue_script(
			'twec-ai-bulk-publish-prep',
			PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-ai-bulk-publish-prep.js',
			array( 'jquery' ),
			(string) filemtime( $file ),
			true
		);
		wp_localize_script(
			'twec-ai-bulk-publish-prep',
			'twecAiBulkPublishPrep',
			array(
				'confirm' => __( 'Run AI Publish prep on the selected events? This will overwrite descriptions and excerpts, and may update categories, tags, and featured image alt text.', 'planit-event-manager' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_venue_description( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$ctx     = self::get_entity_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		$prompt = sprintf(
			'Write 2 short paragraphs describing this venue for a WordPress venue page. Name: %1$s. Address meta: %2$s. Existing content: %3$s. Plain HTML paragraphs only.',
			$ctx['title'],
			wp_json_encode( $ctx['meta'] ),
			wp_strip_all_tags( (string) $ctx['content'] )
		);
		$text   = self::generate_text( $prompt, null, 0.5 );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return rest_ensure_response( array( 'description' => wp_kses_post( $text ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_organizer_bio( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$ctx     = self::get_entity_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		$prompt = sprintf(
			'Write a short organizer bio for a WordPress organizer page. Name: %1$s. Contact meta: %2$s. Existing content: %3$s. Plain HTML paragraphs only.',
			$ctx['title'],
			wp_json_encode( $ctx['meta'] ),
			wp_strip_all_tags( (string) $ctx['content'] )
		);
		$text   = self::generate_text( $prompt, null, 0.5 );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return rest_ensure_response( array( 'bio' => wp_kses_post( $text ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_parse_event_draft( $request ) {
		$text = sanitize_textarea_field( (string) $request->get_param( 'text' ) );
		$data = self::parse_event_from_natural_language( $text );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return rest_ensure_response( $data );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_create_from_text( $request ) {
		$text = sanitize_textarea_field( (string) $request->get_param( 'text' ) );
		$args = self::parse_event_from_natural_language( $text );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		if ( ! class_exists( 'TWEC_REST', false ) ) {
			return new WP_Error( 'twec_ai_unavailable', __( 'Event creation is not available.', 'planit-event-manager' ) );
		}
		$args = (array) apply_filters( 'twec_ability_create_event_draft_args', $args );
		return TWEC_REST::create_event_draft_from_args( $args );
	}

	/**
	 * Fetch upcoming events for grounded public answers.
	 *
	 * @param int $days Days ahead.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_upcoming_events_for_context( $days = 14, $category_slug = '' ) {
		$days          = max( 1, min( 60, (int) $days ) );
		$category_slug = sanitize_title( (string) $category_slug );
		$cache_key     = 'planit_event_manager_ai_events_ctx_' . md5( $days . '|' . $category_slug . '|' . gmdate( 'Y-m-d' ) );
		$cached        = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$after = current_time( 'Y-m-d' );
		$args          = array(
			'post_type'      => 'twec_event',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'orderby'        => 'meta_value',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Context query.
			'meta_key'       => '_twec_event_start_date',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Upcoming filter.
			'meta_query'     => array(
				array(
					'key'     => '_twec_event_end_date',
					'value'   => $after,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);
		if ( '' !== $category_slug ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Optional category filter.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'twec_event_category',
					'field'    => 'slug',
					'terms'    => $category_slug,
				),
			);
		}
		$q    = new WP_Query( $args );
		$rows = array();
		foreach ( $q->posts as $post ) {
			if ( ! ( $post instanceof WP_Post ) ) {
				continue;
			}
			$id    = (int) $post->ID;
			$start = get_post_meta( $id, '_twec_event_start_date', true );
			$terms = get_the_terms( $id, 'twec_event_category' );
			$cats  = array();
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( isset( $term->name ) ) {
						$cats[] = $term->name;
					}
				}
			}
			$rows[] = array(
				'id'         => $id,
				'title'      => get_the_title( $post ),
				'start_date' => is_string( $start ) ? $start : '',
				'categories' => $cats,
				'url'        => (string) get_permalink( $post ),
			);
		}
		wp_reset_postdata();

		set_transient( $cache_key, $rows, 15 * MINUTE_IN_SECONDS );

		return $rows;
	}

	/**
	 * Public AI assistant: answers calendar questions using upcoming event context.
	 *
	 * Responses are cached briefly to avoid repeated provider calls for identical questions.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error JSON answer and supporting event rows.
	 */
	public static function rest_public_query( $request ) {
		if ( ! self::check_public_rate_limit() ) {
			return new WP_Error( 'twec_ai_rate_limit', __( 'Too many requests. Please wait a moment.', 'planit-event-manager' ), array( 'status' => 429 ) );
		}
		$query = sanitize_text_field( (string) $request->get_param( 'query' ) );
		if ( '' === trim( $query ) || strlen( $query ) > 500 ) {
			return new WP_Error( 'twec_ai_query', __( 'Invalid question.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$days     = max( 1, min( 60, (int) $request->get_param( 'days' ) ) );
		$category = sanitize_title( (string) $request->get_param( 'category' ) );
		$locale   = sanitize_text_field( (string) apply_filters( 'twec_ai_public_query_locale', get_locale(), $query, $days ) );

		$events = self::get_upcoming_events_for_context( $days, $category );
		$events = (array) apply_filters( 'twec_ai_public_query_events', $events, $query, $days );

		$response_cache_key = 'planit_event_manager_ai_pub_' . md5( $query . '|' . $days . '|' . $category . '|' . $locale . '|' . md5( (string) wp_json_encode( $events ) ) );
		$cached_response    = get_transient( $response_cache_key );
		if ( false !== $cached_response && is_array( $cached_response ) ) {
			return rest_ensure_response( $cached_response );
		}

		$json   = wp_json_encode( $events );
		$prompt = "You are an event calendar assistant. Answer ONLY using the event data JSON below. If no events match, say so. Be concise. Do not invent events. Respond in the user's language (locale: {$locale}).\n\nQuestion: {$query}\n\nEvents JSON:\n{$json}";
		$answer = self::generate_text( $prompt, null, self::public_temperature() );
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		$payload = array(
			'answer' => wp_kses_post( $answer ),
			'events' => $events,
		);

		set_transient( $response_cache_key, $payload, 15 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $payload );
	}

	/**
	 * Natural-language event search: returns a ranked list of matching upcoming events.
	 *
	 * Uses AI to pick event IDs from a grounded pool, with a keyword WP_Query fallback.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error Matched events and optional summary text.
	 */
	public static function rest_event_search( $request ) {
		if ( ! self::check_public_rate_limit() ) {
			return new WP_Error( 'twec_ai_rate_limit', __( 'Too many requests. Please wait a moment.', 'planit-event-manager' ), array( 'status' => 429 ) );
		}

		$query = sanitize_text_field( (string) $request->get_param( 'query' ) );
		if ( '' === trim( $query ) || strlen( $query ) > 500 ) {
			return new WP_Error( 'twec_ai_query', __( 'Enter a search phrase.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		$days     = max( 1, min( 90, (int) $request->get_param( 'days' ) ) );
		$category = sanitize_title( (string) $request->get_param( 'category' ) );
		$limit    = max( 1, min( 50, (int) $request->get_param( 'limit' ) ) );
		$locale   = sanitize_text_field( (string) apply_filters( 'twec_ai_event_search_locale', get_locale(), $query, $days ) );

		$pool = self::get_upcoming_events_for_context( $days, $category );
		$pool = (array) apply_filters( 'twec_ai_event_search_pool', $pool, $query, $days, $category );

		$cache_key = 'planit_event_manager_ai_es_' . md5( $query . '|' . $days . '|' . $category . '|' . $limit . '|' . $locale . '|' . md5( (string) wp_json_encode( $pool ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$matched = self::match_events_by_natural_language( $query, $pool, $limit, $locale );
		if ( is_wp_error( $matched ) ) {
			return $matched;
		}

		if ( empty( $matched['events'] ) ) {
			$fallback = self::keyword_search_events( $query, $days, $category, $limit );
			if ( ! empty( $fallback ) ) {
				$matched['events']  = $fallback;
				$matched['summary'] = __( 'Showing keyword matches.', 'planit-event-manager' );
			}
		}

		$matched['total'] = count( $matched['events'] );
		set_transient( $cache_key, $matched, 15 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $matched );
	}

	/**
	 * Ask the AI connector which event IDs best match a natural-language query.
	 *
	 * @param string                      $query  Visitor search phrase.
	 * @param array<int, array<string,mixed>> $pool   Upcoming events context rows.
	 * @param int                         $limit  Max results.
	 * @param string                      $locale Site locale for summaries.
	 * @return array{summary:string,events:array<int,array<string,mixed>>}|WP_Error
	 */
	private static function match_events_by_natural_language( $query, array $pool, $limit, $locale ) {
		$limit = max( 1, min( 50, (int) $limit ) );
		if ( empty( $pool ) ) {
			return array(
				'summary' => __( 'No upcoming events to search.', 'planit-event-manager' ),
				'events'  => array(),
			);
		}

		$compact = array();
		foreach ( $pool as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$compact[] = array(
				'id'         => (int) $row['id'],
				'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
				'start_date' => isset( $row['start_date'] ) ? (string) $row['start_date'] : '',
				'categories' => isset( $row['categories'] ) && is_array( $row['categories'] ) ? $row['categories'] : array(),
				'url'        => isset( $row['url'] ) ? (string) $row['url'] : '',
			);
		}

		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'event_ids' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'summary'   => array( 'type' => 'string' ),
			),
			'required'   => array( 'event_ids' ),
		);

		$prompt = sprintf(
			"You match calendar events to a visitor's natural-language search. Locale: %s.\nSearch: %s\n\nEvents JSON (only use ids from this list):\n%s\n\nReturn up to %d event ids ordered by relevance. Use an empty array when nothing matches. Optional one-sentence summary for the visitor.",
			$locale,
			$query,
			(string) wp_json_encode( $compact ),
			$limit
		);

		$json = self::generate_text( $prompt, $schema, 0.2 );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = self::decode_ai_json( $json );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twec_ai_parse', __( 'Could not parse search results.', 'planit-event-manager' ), array( 'status' => 502 ) );
		}

		$allowed_ids = array();
		foreach ( $compact as $item ) {
			$allowed_ids[ (int) $item['id'] ] = true;
		}

		$ordered = array();
		$raw_ids = isset( $data['event_ids'] ) && is_array( $data['event_ids'] ) ? $data['event_ids'] : array();
		foreach ( $raw_ids as $raw_id ) {
			$eid = absint( $raw_id );
			if ( $eid < 1 || empty( $allowed_ids[ $eid ] ) ) {
				continue;
			}
			$row = self::format_event_search_result_row( $eid );
			if ( ! empty( $row ) ) {
				$ordered[] = $row;
			}
			if ( count( $ordered ) >= $limit ) {
				break;
			}
		}

		$summary = isset( $data['summary'] ) ? sanitize_text_field( (string) $data['summary'] ) : '';

		return array(
			'summary' => $summary,
			'events'  => $ordered,
		);
	}

	/**
	 * Keyword fallback when AI returns no matches.
	 *
	 * @param string $query    Search phrase.
	 * @param int    $days     Days ahead window.
	 * @param string $category Optional category slug.
	 * @param int    $limit    Max posts.
	 * @return array<int, array<string, mixed>>
	 */
	private static function keyword_search_events( $query, $days, $category, $limit ) {
		$days  = max( 1, min( 90, (int) $days ) );
		$limit = max( 1, min( 50, (int) $limit ) );
		$after = current_time( 'Y-m-d' );

		$args = array(
			'post_type'              => 'twec_event',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			's'                      => sanitize_text_field( (string) $query ),
			'orderby'                => 'meta_value',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Search ordering.
			'meta_key'               => '_twec_event_start_date',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Upcoming filter.
			'meta_query'             => array(
				array(
					'key'     => '_twec_event_end_date',
					'value'   => $after,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);

		$category = sanitize_title( (string) $category );
		if ( '' !== $category ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Optional filter.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'twec_event_category',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$q    = new WP_Query( $args );
		$rows = array();
		foreach ( $q->posts as $post ) {
			if ( ! ( $post instanceof WP_Post ) ) {
				continue;
			}
			$row = self::format_event_search_result_row( (int) $post->ID );
			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}
		}
		wp_reset_postdata();

		return $rows;
	}

	/**
	 * Build a public event row for search result lists.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, mixed>
	 */
	public static function format_event_search_result_row( $event_id ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 || 'twec_event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
			return array();
		}

		$start    = (string) get_post_meta( $event_id, '_twec_event_start_date', true );
		$end      = (string) get_post_meta( $event_id, '_twec_event_end_date', true );
		$venue_id = (int) get_post_meta( $event_id, '_twec_event_venue', true );
		$venue    = $venue_id > 0 ? get_post( $venue_id ) : null;
		$terms    = get_the_terms( $event_id, 'twec_event_category' );
		$cats     = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( isset( $term->name ) ) {
					$cats[] = (string) $term->name;
				}
			}
		}

		$date_label = '';
		if ( '' !== $start ) {
			$date_label = (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $start );
		}

		return array(
			'id'          => $event_id,
			'title'       => (string) get_the_title( $event_id ),
			'url'         => (string) get_permalink( $event_id ),
			'start_date'  => $start,
			'end_date'    => $end,
			'date_label'  => $date_label,
			'venue'       => ( $venue instanceof WP_Post ) ? (string) $venue->post_title : '',
			'excerpt'     => has_excerpt( $event_id ) ? (string) get_the_excerpt( $event_id ) : '',
			'categories'  => $cats,
		);
	}

	/**
	 * Shortcode: natural-language event search UI.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_event_search( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'heading'     => '',
				'placeholder' => '',
				'days'        => 60,
				'limit'       => 20,
				'category'    => '',
			),
			is_array( $atts ) ? $atts : array(),
			'twec_event_search'
		);

		return self::render_event_search_markup(
			array(
				'heading'     => sanitize_text_field( (string) $atts['heading'] ),
				'placeholder' => sanitize_text_field( (string) $atts['placeholder'] ),
				'days'        => max( 1, min( 90, (int) $atts['days'] ) ),
				'limit'       => max( 1, min( 50, (int) $atts['limit'] ) ),
				'category'    => sanitize_title( (string) $atts['category'] ),
			)
		);
	}

	/**
	 * Render the event search block/shortcode HTML shell.
	 *
	 * @param array<string, mixed> $attributes Block or shortcode attributes.
	 * @return string
	 */
	public static function render_event_search_markup( array $attributes ) {
		if ( ! self::is_public_assistant_enabled() || ! self::is_text_generation_available() ) {
			return '';
		}

		self::enqueue_event_search_assets();

		$heading = isset( $attributes['heading'] ) ? sanitize_text_field( (string) $attributes['heading'] ) : '';
		if ( '' === $heading ) {
			$heading = __( 'Search events', 'planit-event-manager' );
		}

		$placeholder = isset( $attributes['placeholder'] ) ? sanitize_text_field( (string) $attributes['placeholder'] ) : '';
		if ( '' === $placeholder ) {
			$placeholder = __( 'e.g. free outdoor concerts this month', 'planit-event-manager' );
		}

		$days     = isset( $attributes['days'] ) ? max( 1, min( 90, (int) $attributes['days'] ) ) : 60;
		$limit    = isset( $attributes['limit'] ) ? max( 1, min( 50, (int) $attributes['limit'] ) ) : 20;
		$category = isset( $attributes['category'] ) ? sanitize_title( (string) $attributes['category'] ) : '';

		$input_id = 'twec-event-search-input-' . wp_unique_id( 'twec-event-search-' );

		$html  = '<div class="twec-event-search" data-days="' . esc_attr( (string) $days ) . '" data-limit="' . esc_attr( (string) $limit ) . '" data-category="' . esc_attr( $category ) . '">';
		$html .= '<h3 class="twec-event-search__heading">' . esc_html( $heading ) . '</h3>';
		$html .= '<form class="twec-event-search__form" action="#" method="get" role="search">';
		$html .= '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html( $heading ) . '</label>';
		$html .= '<input type="search" id="' . esc_attr( $input_id ) . '" class="twec-event-search__input" name="twec_event_search" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" />';
		$html .= '<button type="submit" class="twec-event-search__submit button">' . esc_html__( 'Search', 'planit-event-manager' ) . '</button>';
		$html .= '</form>';
		$html .= '<p class="twec-event-search__summary" aria-live="polite"></p>';
		$html .= '<div class="twec-event-search__results" aria-live="polite"></div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Enqueue event search front-end assets.
	 *
	 * @return void
	 */
	public static function enqueue_event_search_assets() {
		static $done = false;
		if ( $done || ! self::is_public_assistant_enabled() || ! self::is_text_generation_available() ) {
			return;
		}
		$done = true;

		$js = PLANIT_EVENT_MANAGER_DIR . 'public/js/twec-event-search.js';
		if ( ! is_readable( $js ) ) {
			return;
		}

		wp_enqueue_style(
			'twec-event-search',
			PLANIT_EVENT_MANAGER_URL . 'public/css/twec-event-search.css',
			array(),
			PLANIT_EVENT_MANAGER_VERSION
		);
		wp_enqueue_script(
			'twec-event-search',
			PLANIT_EVENT_MANAGER_URL . 'public/js/twec-event-search.js',
			array(),
			(string) filemtime( $js ),
			true
		);
		wp_localize_script(
			'twec-event-search',
			'twecEventSearch',
			array(
				'restUrl' => esc_url_raw( rest_url( 'planit/v1/ai/event-search' ) ),
				'i18n'    => array(
					'loading'  => __( 'Searching events…', 'planit-event-manager' ),
					'empty'    => __( 'No matching events found. Try different words.', 'planit-event-manager' ),
					'error'    => __( 'Search failed. Please try again.', 'planit-event-manager' ),
					'results'  => __( '%d events found', 'planit-event-manager' ),
					'oneResult' => __( '1 event found', 'planit-event-manager' ),
				),
			)
		);
	}

	/**
	 * Simple per-IP rate limit for public AI queries.
	 *
	 * @return bool True if allowed.
	 */
	private static function check_public_rate_limit() {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
		}
		if ( '' === $ip ) {
			return true;
		}
		$key   = 'twec_ai_pub_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 10 ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Enqueue event editor AI assist script.
	 *
	 * @return void
	 */
	public static function enqueue_event_editor_ai() {
		if ( ! self::is_admin_assist_enabled() || ! self::is_text_generation_available() ) {
			return;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'twec_event' !== $screen->post_type ) {
			return;
		}
		$post_id = self::get_editor_post_id();
		if ( $post_id <= 0 ) {
			return;
		}
		$file = PLANIT_EVENT_MANAGER_DIR . 'admin/js/twec-event-ai-assist.js';
		if ( ! is_readable( $file ) ) {
			return;
		}
		wp_enqueue_script(
			'twec-event-ai-assist',
			PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-event-ai-assist.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			(string) filemtime( $file ),
			true
		);
		wp_localize_script(
			'twec-event-ai-assist',
			'twecEventAiAssist',
			array(
				'postId'   => $post_id,
				'nonce'    => wp_create_nonce( 'twec_ai_assist_' . $post_id ),
				'restRoot' => esc_url_raw( rest_url( 'planit/v1/ai/' ) ),
				'i18n'     => array(
					'panelTitle'            => __( 'PlanIt AI Assist', 'planit-event-manager' ),
					'publishPrep'           => __( 'Publish prep', 'planit-event-manager' ),
					'draftDesc'             => __( 'Generate description', 'planit-event-manager' ),
					'suggestTax'            => __( 'Suggest categories & tags', 'planit-event-manager' ),
					'social'                => __( 'Social snippet', 'planit-event-manager' ),
					'altText'               => __( 'Featured image alt text', 'planit-event-manager' ),
					'accept'                => __( 'Accept', 'planit-event-manager' ),
					'acceptAlt'             => __( 'Apply alt text', 'planit-event-manager' ),
					'acceptTaxonomy'        => __( 'Apply categories & tags', 'planit-event-manager' ),
					'accepted'              => __( 'Applied.', 'planit-event-manager' ),
					'noFeaturedImage'       => __( 'Set a featured image first.', 'planit-event-manager' ),
					'noTaxonomySuggestions' => __( 'No categories or tags to apply.', 'planit-event-manager' ),
					'regenerate'            => __( 'Regenerate', 'planit-event-manager' ),
					'discard'               => __( 'Discard', 'planit-event-manager' ),
					'loading'               => __( 'Generating…', 'planit-event-manager' ),
					'error'                 => __( 'AI request failed.', 'planit-event-manager' ),
					'previewLabel'          => __( 'Preview', 'planit-event-manager' ),
				),
			)
		);
	}

	/**
	 * Enqueue front-end event assistant assets when block is present.
	 *
	 * @return void
	 */
	public static function enqueue_public_assistant_assets() {
		// Assets are enqueued on demand from the block render callback.
	}

	/**
	 * Enqueue event assistant front-end assets (called from block render).
	 *
	 * @return void
	 */
	public static function enqueue_assistant_assets() {
		static $done = false;
		if ( $done || ! self::is_public_assistant_enabled() || ! self::is_text_generation_available() ) {
			return;
		}
		$done = true;
		$js   = PLANIT_EVENT_MANAGER_DIR . 'public/js/twec-event-assistant.js';
		if ( ! is_readable( $js ) ) {
			return;
		}
		wp_enqueue_style(
			'twec-event-assistant',
			PLANIT_EVENT_MANAGER_URL . 'public/css/twec-event-assistant.css',
			array(),
			PLANIT_EVENT_MANAGER_VERSION
		);
		wp_enqueue_script(
			'twec-event-assistant',
			PLANIT_EVENT_MANAGER_URL . 'public/js/twec-event-assistant.js',
			array(),
			(string) filemtime( $js ),
			true
		);
		wp_localize_script(
			'twec-event-assistant',
			'twecEventAssistant',
			array(
				'restUrl' => esc_url_raw( rest_url( 'planit/v1/ai/public-query' ) ),
				'i18n'    => array(
					'placeholder' => __( 'Ask about upcoming events…', 'planit-event-manager' ),
					'submit'      => __( 'Ask', 'planit-event-manager' ),
					'loading'     => __( 'Thinking…', 'planit-event-manager' ),
					'error'       => __( 'Could not get an answer. Try again.', 'planit-event-manager' ),
				),
			)
		);
	}
}
