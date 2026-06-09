<?php
/**
 * Block editor: free calendar + event list blocks (always loaded from the org plugin).
 *
 * Registered even when PlanIt Event Manager Premium is active so core blocks stay
 * on free assets and are not overridden by the premium package.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers calendar, event-list, and compact-event-list dynamic blocks.
 */
class TWEC_Blocks_Core {

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Register on init.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'init', array( __CLASS__, 'register' ), 9 );
	}

	/**
	 * Block editor script config (WooCommerce ticket hints on calendar/list).
	 *
	 * @return array<string, mixed>
	 */
	private static function get_block_editor_data() {
		$wc_tickets = false;
		if ( class_exists( 'TWEC_WooCommerce' ) ) {
			$wc_tickets = TWEC_WooCommerce::is_wc_active() && TWEC_WooCommerce::is_feature_enabled();
		}

		return array(
			'hasWooCommerce'       => class_exists( 'WooCommerce' ),
			'woocommerceTicketsOn' => $wc_tickets,
		);
	}

	/**
	 * Plugin version for block assets.
	 *
	 * @return string
	 */
	private static function asset_version() {
		if ( defined( 'PLANIT_EVENT_MANAGER_VERSION' ) ) {
			return (string) PLANIT_EVENT_MANAGER_VERSION;
		}
		return '1.0.0';
	}

	/**
	 * Register scripts, block types, and pattern.
	 *
	 * @return void
	 */
	public static function register() {
		$url = defined( 'PLANIT_EVENT_MANAGER_URL' ) ? PLANIT_EVENT_MANAGER_URL : '';
		$dir = defined( 'PLANIT_EVENT_MANAGER_DIR' ) ? PLANIT_EVENT_MANAGER_DIR : '';
		$js  = $dir ? $dir . 'admin/js/twec-blocks-core.js' : '';

		if ( ! $url || ! $js || ! is_readable( $js ) ) {
			return;
		}

		$ver = self::asset_version();

		$script_deps = array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-i18n',
		);
		if ( wp_script_is( 'wp-server-side-render', 'registered' ) ) {
			$script_deps[] = 'wp-server-side-render';
		}

		wp_register_script(
			'planit-twec-blocks-core',
			$url . 'admin/js/twec-blocks-core.js',
			$script_deps,
			$ver,
			true
		);

		wp_localize_script(
			'planit-twec-blocks-core',
			'planitTwecBlocksCore',
			self::get_block_editor_data()
		);

		$editor_style_args = array();
		$editor_styles     = array();
		$css               = $dir . 'admin/css/twec-blocks-editor.css';
		if ( is_readable( $css ) ) {
			wp_register_style(
				'planit-twec-blocks-editor',
				$url . 'admin/css/twec-blocks-editor.css',
				array(),
				$ver
			);
			$editor_styles[] = 'planit-twec-blocks-editor';
		}
		$public_css = $dir . 'public/css/twec-public.css';
		if ( is_readable( $public_css ) ) {
			wp_register_style(
				'planit-twec-blocks-calendar-preview',
				$url . 'public/css/twec-public.css',
				array(),
				$ver
			);
			$editor_styles[] = 'planit-twec-blocks-calendar-preview';
		}
		if ( ! empty( $editor_styles ) ) {
			$editor_style_args['editor_style'] = 1 === count( $editor_styles ) ? $editor_styles[0] : $editor_styles;
		}

		$calendar_attributes = array(
			'view'                => array(
				'type'    => 'string',
				'default' => 'month',
			),
			'enableInteractivity' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'categorySlug'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'tagSlug'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'ticketsMode'         => array(
				'type'    => 'string',
				'default' => '',
			),
		);

		register_block_type(
			'planit-event-manager/calendar',
			array_merge(
				array(
					'api_version'     => 2,
					'editor_script'   => 'planit-twec-blocks-core',
					'attributes'      => $calendar_attributes,
					'render_callback' => array( __CLASS__, 'render_calendar' ),
				),
				$editor_style_args
			)
		);

		$list_attributes = array(
			'perPage'      => array(
				'type'    => 'number',
				'default' => 10,
			),
			'pastEvents'   => array(
				'type'    => 'string',
				'default' => 'hide',
			),
			'categorySlug' => array(
				'type'    => 'string',
				'default' => '',
			),
			'tagSlug'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'ticketsMode'  => array(
				'type'    => 'string',
				'default' => '',
			),
		);

		register_block_type(
			'planit-event-manager/event-list',
			array_merge(
				array(
					'api_version'     => 2,
					'editor_script'   => 'planit-twec-blocks-core',
					'attributes'      => $list_attributes,
					'render_callback' => array( __CLASS__, 'render_list' ),
				),
				$editor_style_args
			)
		);

		$compact_attributes = array(
			'perPage'             => array(
				'type'    => 'number',
				'default' => 25,
			),
			'pastEvents'          => array(
				'type'    => 'string',
				'default' => 'hide',
			),
			'categorySlug'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'tagSlug'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'linkBehavior'        => array(
				'type'    => 'string',
				'default' => 'modal',
			),
			'enableInteractivity' => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);

		register_block_type(
			'planit-event-manager/compact-event-list',
			array_merge(
				array(
					'api_version'     => 2,
					'editor_script'   => 'planit-twec-blocks-core',
					'attributes'      => $compact_attributes,
					'render_callback' => array( __CLASS__, 'render_compact_list' ),
				),
				$editor_style_args
			)
		);

		$assistant_attributes = array(
			'heading' => array(
				'type'    => 'string',
				'default' => '',
			),
			'days'    => array(
				'type'    => 'number',
				'default' => 14,
			),
		);

		register_block_type(
			'planit-event-manager/event-assistant',
			array_merge(
				array(
					'api_version'     => 2,
					'editor_script'   => 'planit-twec-blocks-core',
					'attributes'      => $assistant_attributes,
					'render_callback' => array( __CLASS__, 'render_event_assistant' ),
				),
				$editor_style_args
			)
		);

		if ( function_exists( 'register_block_pattern' ) ) {
			$title   = esc_html( __( 'Upcoming events', 'planit-event-manager' ) );
			$cal     = "<!-- wp:planit-event-manager/calendar {\"view\":\"month\"} /-->\n";
			$heading = "<!-- wp:heading {\"level\":2} --><h2>{$title}</h2><!-- /wp:heading -->\n";
			register_block_pattern(
				'planit-event-manager/events-landing',
				array(
					'title'       => __( 'PlanIt: heading + calendar', 'planit-event-manager' ),
					'description' => __( 'Section title and month calendar for events.', 'planit-event-manager' ),
					'categories'  => array( 'text' ),
					'content'     => $heading . $cal,
				)
			);
		}
	}

	/**
	 * Sanitize a taxonomy slug fragment for shortcode attributes.
	 *
	 * @param mixed $value Raw attribute.
	 * @return string
	 */
	private static function slug_attr( $value ) {
		$s = is_scalar( $value ) ? (string) $value : '';
		$s = trim( $s );
		if ( '' === $s ) {
			return '';
		}

		return sanitize_key( str_replace( ' ', '-', $s ) );
	}

	/**
	 * Map block ticketsMode to shortcode `tickets` value.
	 *
	 * @param mixed $mode Block attribute.
	 * @return string '' | 'yes' | 'no'
	 */
	private static function tickets_shortcode_value( $mode ) {
		$m = is_string( $mode ) ? strtolower( trim( $mode ) ) : '';
		if ( 'yes' === $m || 'no' === $m ) {
			return $m;
		}

		return '';
	}

	/**
	 * Whether the current request is the block editor ServerSideRender preview.
	 *
	 * @return bool
	 */
	private static function is_block_editor_renderer_request() {
		$is_preview = false;
		$uri        = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';

		if ( function_exists( 'wp_is_rest_request' ) && wp_is_rest_request() ) {
			// phpcs:disable WordPress.Security.NonceVerification -- Block renderer REST route shape only.
			if ( isset( $_GET['rest_route'] ) && is_scalar( $_GET['rest_route'] ) ) {
				$route      = sanitize_text_field( (string) wp_unslash( $_GET['rest_route'] ) );
				$is_preview = false !== strpos( $route, '/block-renderer/' );
			}
			// phpcs:enable WordPress.Security.NonceVerification
		}

		if ( ! $is_preview && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$is_preview = '' !== $uri && false !== strpos( $uri, 'block-renderer' );
		}

		if ( ! $is_preview && '' !== $uri && false !== strpos( $uri, 'block-renderer' ) ) {
			$is_preview = true;
		}

		return (bool) apply_filters( 'twec_is_block_editor_preview_request', $is_preview );
	}

	/**
	 * Minimal placeholder markup for editor-only block renderer previews.
	 *
	 * @param string $message User-facing preview label.
	 * @return string
	 */
	private static function block_editor_preview_placeholder_html( $message ) {
		$msg = esc_html( $message );
		return '<div class="twec-block-editor-preview-placeholder" aria-hidden="true"><p>' . $msg . '</p></div>';
	}

	/**
	 * Whether the block editor should render a live SSR preview for core blocks.
	 *
	 * @return bool
	 */
	private static function use_editor_live_preview() {
		return (bool) apply_filters( 'twec_block_editor_live_preview', true );
	}

	/**
	 * Wrap front-end markup for non-interactive editor preview.
	 *
	 * @param string $html Rendered shortcode HTML.
	 * @return string
	 */
	private static function wrap_editor_live_preview_html( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return self::block_editor_preview_placeholder_html(
				__( 'No events to preview yet. The calendar or list will appear here once events are published.', 'planit-event-manager' )
			);
		}

		return '<div class="twec-block-editor-live-preview" aria-hidden="true">' . $html . '</div>';
	}

	/**
	 * Render calendar block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block object.
	 * @return string
	 */
	public static function render_calendar( $attributes, $content, $block ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$is_editor = self::is_block_editor_renderer_request();

		$view = isset( $attributes['view'] ) ? sanitize_key( (string) $attributes['view'] ) : 'month';
		if ( ! in_array( $view, array( 'day', 'month' ), true ) ) {
			$view = 'month';
		}

		if ( $is_editor && ! self::use_editor_live_preview() ) {
			/* translators: %s: calendar view slug (month or day). */
			$msg = sprintf( __( 'PlanIt calendar preview (%s view). The interactive calendar renders on the front end.', 'planit-event-manager' ), $view );
			return self::block_editor_preview_placeholder_html( $msg );
		}

		$use_ia = ! $is_editor && ! empty( $attributes['enableInteractivity'] );
		$ia     = $use_ia ? 'yes' : 'no';

		$shortcode = '[twec_calendar view="' . esc_attr( $view ) . '" interactivity="' . esc_attr( $ia ) . '"';
		$category  = self::slug_attr( $attributes['categorySlug'] ?? '' );
		$tag       = self::slug_attr( $attributes['tagSlug'] ?? '' );
		$tickets   = self::tickets_shortcode_value( $attributes['ticketsMode'] ?? '' );

		if ( '' !== $category ) {
			$shortcode .= ' category="' . esc_attr( $category ) . '"';
		}
		if ( '' !== $tag ) {
			$shortcode .= ' tag="' . esc_attr( $tag ) . '"';
		}
		if ( '' !== $tickets ) {
			$shortcode .= ' tickets="' . esc_attr( $tickets ) . '"';
		}
		$shortcode .= ']';

		$html = do_shortcode( $shortcode );
		if ( $is_editor ) {
			return self::wrap_editor_live_preview_html( $html );
		}

		return $html;
	}

	/**
	 * Render list block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block object.
	 * @return string
	 */
	public static function render_list( $attributes, $content, $block ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$is_editor = self::is_block_editor_renderer_request();

		if ( $is_editor && ! self::use_editor_live_preview() ) {
			$per = isset( $attributes['perPage'] ) ? max( 1, (int) $attributes['perPage'] ) : 10;
			/* translators: %d: number of events per page. */
			$msg = sprintf( __( 'PlanIt event list preview (%d per page). The list renders on the front end.', 'planit-event-manager' ), $per );
			return self::block_editor_preview_placeholder_html( $msg );
		}

		$per  = isset( $attributes['perPage'] ) ? max( 1, (int) $attributes['perPage'] ) : 10;
		$past = isset( $attributes['pastEvents'] ) && 'show' === $attributes['pastEvents'] ? 'show' : 'hide';

		$shortcode = '[twec_list per_page="' . absint( $per ) . '" past_events="' . esc_attr( $past ) . '"';
		$category  = self::slug_attr( $attributes['categorySlug'] ?? '' );
		$tag       = self::slug_attr( $attributes['tagSlug'] ?? '' );
		$tickets   = self::tickets_shortcode_value( $attributes['ticketsMode'] ?? '' );

		if ( '' !== $category ) {
			$shortcode .= ' category="' . esc_attr( $category ) . '"';
		}
		if ( '' !== $tag ) {
			$shortcode .= ' tag="' . esc_attr( $tag ) . '"';
		}
		if ( '' !== $tickets ) {
			$shortcode .= ' tickets="' . esc_attr( $tickets ) . '"';
		}
		$shortcode .= ']';

		$html = do_shortcode( $shortcode );
		if ( $is_editor ) {
			return self::wrap_editor_live_preview_html( $html );
		}

		return $html;
	}

	/**
	 * Render compact list block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block object.
	 * @return string
	 */
	public static function render_compact_list( $attributes, $content, $block ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$is_editor = self::is_block_editor_renderer_request();

		if ( $is_editor && ! self::use_editor_live_preview() ) {
			$per = isset( $attributes['perPage'] ) ? max( 1, (int) $attributes['perPage'] ) : 25;
			/* translators: %d: number of events per page. */
			$msg = sprintf( __( 'PlanIt compact event list preview (%d per page). The table renders on the front end.', 'planit-event-manager' ), $per );
			return self::block_editor_preview_placeholder_html( $msg );
		}

		$per  = isset( $attributes['perPage'] ) ? max( 1, (int) $attributes['perPage'] ) : 25;
		$past = isset( $attributes['pastEvents'] ) && 'show' === $attributes['pastEvents'] ? 'show' : 'hide';
		$link = isset( $attributes['linkBehavior'] ) ? strtolower( (string) $attributes['linkBehavior'] ) : 'modal';
		if ( ! in_array( $link, array( 'modal', 'page' ), true ) ) {
			$link = 'modal';
		}

		$use_ia    = ! $is_editor && ! empty( $attributes['enableInteractivity'] );
		$ia        = $use_ia ? 'yes' : 'no';
		$shortcode = '[twec_compact_list per_page="' . absint( $per ) . '" past_events="' . esc_attr( $past ) . '" link_behavior="' . esc_attr( $link ) . '" interactivity="' . esc_attr( $ia ) . '"';
		$category  = self::slug_attr( $attributes['categorySlug'] ?? '' );
		$tag       = self::slug_attr( $attributes['tagSlug'] ?? '' );

		if ( '' !== $category ) {
			$shortcode .= ' category="' . esc_attr( $category ) . '"';
		}
		if ( '' !== $tag ) {
			$shortcode .= ' tag="' . esc_attr( $tag ) . '"';
		}
		$shortcode .= ']';

		$html = do_shortcode( $shortcode );
		if ( $is_editor ) {
			return self::wrap_editor_live_preview_html( $html );
		}

		return $html;
	}

	/**
	 * Render event assistant block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block object.
	 * @return string
	 */
	public static function render_event_assistant( $attributes, $content, $block ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$is_editor = self::is_block_editor_renderer_request();

		if ( $is_editor && ! self::use_editor_live_preview() ) {
			return self::block_editor_preview_placeholder_html(
				__( 'PlanIt Event Assistant preview. Enable AI under Events → Settings on the front end.', 'planit-event-manager' )
			);
		}

		if ( ! class_exists( 'TWEC_AI', false ) || ! TWEC_AI::is_public_assistant_enabled() ) {
			if ( $is_editor ) {
				return self::block_editor_preview_placeholder_html(
					__( 'Event Assistant is disabled. Enable PlanIt AI and the public assistant under Events → Settings.', 'planit-event-manager' )
				);
			}
			return '';
		}

		if ( class_exists( 'TWEC_AI', false ) && method_exists( 'TWEC_AI', 'enqueue_assistant_assets' ) ) {
			TWEC_AI::enqueue_assistant_assets();
		}

		$heading = isset( $attributes['heading'] ) ? sanitize_text_field( (string) $attributes['heading'] ) : '';
		if ( '' === $heading ) {
			$heading = __( 'Ask about upcoming events', 'planit-event-manager' );
		}
		$placeholder = esc_attr__( 'What is happening this weekend?', 'planit-event-manager' );
		$submit      = esc_attr__( 'Ask', 'planit-event-manager' );

		$html  = '<div class="twec-event-assistant" data-days="' . esc_attr( (string) ( isset( $attributes['days'] ) ? max( 1, (int) $attributes['days'] ) : 14 ) ) . '">';
		$html .= '<h3 class="twec-event-assistant__heading">' . esc_html( $heading ) . '</h3>';
		$html .= '<form class="twec-event-assistant__form" action="#" method="post">';
		$html .= '<input type="text" class="twec-event-assistant__input" name="twec_assistant_query" placeholder="' . $placeholder . '" aria-label="' . $placeholder . '" />';
		$html .= '<button type="submit" class="twec-event-assistant__submit button">' . esc_html( $submit ) . '</button>';
		$html .= '</form>';
		$html .= '<div class="twec-event-assistant__answer" aria-live="polite"></div>';
		$html .= '<div class="twec-event-assistant__events"></div>';
		$html .= '</div>';

		if ( $is_editor ) {
			return self::wrap_editor_live_preview_html( $html );
		}
		return $html;
	}
}
