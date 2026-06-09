<?php
/**
 * Shortcodes functionality.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-shortcodes.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes functionality.
 *
 * Handles registration and rendering of plugin shortcodes.
 */
class TWEC_Shortcodes {

	/**
	 * Strip typographic quotes and trim, for shortcode values pasted from documents.
	 *
	 * @param mixed $v Attribute value.
	 * @return string
	 */
	private static function sanitize_shortcode_text( $v ) {
		$s = is_scalar( $v ) ? (string) $v : '';
		$s = str_replace( array( '“', '”', '‘', '’' ), array( '', '', '', '' ), $s );
		return trim( $s );
	}

	/**
	 * Initialize shortcodes.
	 */
	public function __construct() {
		// Run before do_blocks (9) and do_shortcode (11) so typographic quotes in the stored shortcode are fixed.
		add_filter( 'the_content', array( $this, 'normalize_shortcode_ascii_quotes' ), 7 );
		add_filter( 'widget_text', array( $this, 'normalize_shortcode_ascii_quotes' ), 7 );
		add_shortcode( 'twec_calendar', array( $this, 'calendar_shortcode' ) );
		add_shortcode( 'twec_list', array( $this, 'list_shortcode' ) );
		add_shortcode( 'twec_compact_list', array( $this, 'compact_list_shortcode' ) );
	}

	/**
	 * Replace Unicode “smart” / typographic quote marks inside PlanIt shortcodes with ASCII so WordPress can parse attributes.
	 *
	 * Pasted content like per_page=”10″ (U+201C, U+201D, primes) is otherwise ignored by the shortcode attribute parser.
	 *
	 * @param string $html Post or widget text.
	 * @return string
	 */
	public function normalize_shortcode_ascii_quotes( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === strpos( $html, 'twec_' ) ) {
			return $html;
		}
		$from = array( '“', '”', '„', '‟', '«', '»', '‘', '’', '‚', '‛', '′', '″', '‴' );
		$to_d = array( '"', '"', '"', '"', '"', '"', "'", "'", "'", "'", "'", '"', '"', '"' );
		foreach ( array( 'twec_list', 'twec_compact_list', 'twec_calendar' ) as $tag ) {
			$pattern = '/\[' . preg_quote( $tag, '/' ) . '\b[^\]]*\]/u';
			$html    = (string) preg_replace_callback(
				$pattern,
				static function ( $m ) use ( $from, $to_d ) {
					return str_replace( $from, $to_d, $m[0] );
				},
				$html
			);
		}
		return $html;
	}

	/**
	 * Calendar shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function calendar_shortcode( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts(
			array(
				'view'          => 'month',
				'category'      => '',
				'tag'           => '',
				'interactivity' => '',
				'tickets'       => '',
			),
			$atts,
			'twec_calendar'
		);

		$atts['view'] = self::sanitize_shortcode_text( isset( $atts['view'] ) ? $atts['view'] : 'month' );
		if ( '' === $atts['view'] ) {
			$atts['view'] = 'month';
		}

		$ticket_cta = false;
		if ( class_exists( 'TWEC_WooCommerce', false ) ) {
			$ticket_cta = TWEC_WooCommerce::resolve_show_ticket_cta( isset( $atts['tickets'] ) ? (string) $atts['tickets'] : '', 'calendar' );
		}
		$GLOBALS['twec_calendar_show_ticket_ctas'] = (bool) $ticket_cta;

		if ( class_exists( 'TWEC_Public', false ) ) {
			TWEC_Public::enqueue_quick_add_for_calendar();
		}

		ob_start();
		include PLANIT_EVENT_MANAGER_DIR . 'public/partials/twec-calendar.php';
		$html = (string) ob_get_clean();
		unset( $GLOBALS['twec_calendar_show_ticket_ctas'] );

		return $html;
	}

	/**
	 * Whether past events should be hidden for list-style shortcodes.
	 *
	 * @param string $past_raw past_events attribute.
	 * @return bool
	 */
	private static function should_hide_past_events( $past_raw ) {
		$past_l = strtolower( self::sanitize_shortcode_text( (string) $past_raw ) );
		if ( '' === $past_l ) {
			$past_l = 'hide';
		}
		$show_past = in_array( $past_l, array( 'show', 'all', 'yes', '1', 'true', 'include' ), true );
		return ! $show_past && ( 0 === strcasecmp( 'hide', $past_l ) || 0 === strcasecmp( 'upcoming', $past_l ) || in_array( $past_l, array( '0', 'no', 'false' ), true ) );
	}

	/**
	 * Build WP_Query args for event list shortcodes.
	 *
	 * @param int    $per_page Events per page.
	 * @param string $category Category slug.
	 * @param string $tag      Tag slug.
	 * @param bool   $hide_past Hide ended events.
	 * @param int    $paged     Current page.
	 * @return array<string, mixed>
	 */
	private static function build_events_query_args( $per_page, $category, $tag, $hide_past, $paged ) {
		$args = array(
			'post_type'      => 'twec_event',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'meta_value',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Event date ordering.
			'meta_key'       => '_twec_event_start_date',
			'order'          => 'ASC',
		);

		if ( '' !== $category ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'twec_event_category',
				'field'    => 'slug',
				'terms'    => $category,
			);
		}

		if ( '' !== $tag ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'twec_event_tag',
				'field'    => 'slug',
				'terms'    => $tag,
			);
		}

		if ( $hide_past ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Upcoming-only filter.
			$args['meta_query'][] = array(
				'key'     => '_twec_event_end_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			);
		}

		return $args;
	}

	/**
	 * Enqueue compact list assets once per request.
	 *
	 * @param string $link_behavior modal|page.
	 * @return void
	 */
	private static function enqueue_compact_list_assets( $link_behavior ) {
		if ( 'page' === $link_behavior ) {
			return;
		}

		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$url = defined( 'PLANIT_EVENT_MANAGER_URL' ) ? PLANIT_EVENT_MANAGER_URL : '';
		$ver = defined( 'PLANIT_EVENT_MANAGER_VERSION' ) ? PLANIT_EVENT_MANAGER_VERSION : '1.0.0';
		if ( '' === $url ) {
			return;
		}

		wp_enqueue_script(
			'twec-compact-list',
			$url . 'public/js/twec-compact-list.js',
			array(),
			$ver,
			true
		);

		wp_localize_script(
			'twec-compact-list',
			'twecCompactList',
			array(
				'restRoot' => esc_url_raw( rest_url( 'wp/v2/' ) ),
				'i18n'     => array(
					'close'     => __( 'Close', 'planit-event-manager' ),
					'viewEvent' => __( 'View full event', 'planit-event-manager' ),
					'loading'   => __( 'Loading…', 'planit-event-manager' ),
					'error'     => __( 'Could not load this event.', 'planit-event-manager' ),
				),
			)
		);
	}

	/**
	 * List shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function list_shortcode( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts(
			array(
				'per_page'    => 10,
				'category'    => '',
				'tag'         => '',
				'past_events' => 'hide',
				'tickets'     => '',
			),
			$atts,
			'twec_list'
		);

		$per_page  = max( 1, absint( self::sanitize_shortcode_text( $atts['per_page'] ) ?: 10 ) );
		$category  = self::sanitize_shortcode_text( $atts['category'] );
		$tag       = self::sanitize_shortcode_text( $atts['tag'] );
		$hide_past = self::should_hide_past_events( $atts['past_events'] );
		$paged     = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;

		$events_query = new WP_Query( self::build_events_query_args( $per_page, $category, $tag, $hide_past, $paged ) );

		$twec_list_show_ticket_ctas = false;
		if ( class_exists( 'TWEC_WooCommerce', false ) ) {
			$twec_list_show_ticket_ctas = TWEC_WooCommerce::resolve_show_ticket_cta( isset( $atts['tickets'] ) ? (string) $atts['tickets'] : '', 'list' );
		}

		ob_start();
		include PLANIT_EVENT_MANAGER_DIR . 'public/partials/twec-list.php';
		$output = ob_get_clean();
		wp_reset_postdata();

		return $output;
	}

	/**
	 * Compact list shortcode (date, title, category).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function compact_list_shortcode( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts(
			array(
				'per_page'      => 25,
				'category'      => '',
				'tag'           => '',
				'past_events'   => 'hide',
				'link_behavior' => 'modal',
				'interactivity' => '',
			),
			$atts,
			'twec_compact_list'
		);

		$per_page      = max( 1, absint( self::sanitize_shortcode_text( $atts['per_page'] ) ?: 25 ) );
		$category      = self::sanitize_shortcode_text( $atts['category'] );
		$tag           = self::sanitize_shortcode_text( $atts['tag'] );
		$hide_past     = self::should_hide_past_events( $atts['past_events'] );
		$link_behavior = strtolower( self::sanitize_shortcode_text( $atts['link_behavior'] ) );
		if ( ! in_array( $link_behavior, array( 'modal', 'page' ), true ) ) {
			$link_behavior = 'modal';
		}
		$link_behavior = (string) apply_filters( 'twec_compact_list_link_behavior', $link_behavior, $atts );
		$use_interactive = function_exists( 'twec_compact_should_use_interactivity' )
			&& twec_compact_should_use_interactivity( $atts );

		$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;

		self::enqueue_compact_list_assets( $link_behavior );

		$events_query               = new WP_Query( self::build_events_query_args( $per_page, $category, $tag, $hide_past, $paged ) );
		$twec_compact_link_behavior = $link_behavior;
		$twec_compact_interactive   = $use_interactive;

		ob_start();
		include PLANIT_EVENT_MANAGER_DIR . 'public/partials/twec-compact-list.php';
		$output = ob_get_clean();
		wp_reset_postdata();

		return $output;
	}
}

// TWEC_Shortcodes is initialized by TWEC class.
