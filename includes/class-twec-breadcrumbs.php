<?php
/**
 * Breadcrumb JSON-LD and SEO plugin integration hooks.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BreadcrumbList schema and filters for themes / Yoast / Rank Math.
 */
class TWEC_Breadcrumbs {

	/**
	 * Init.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output_breadcrumb_json_ld' ), 6 );
		if ( class_exists( 'WPSEO_Frontend' ) || defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_breadcrumb_links', array( __CLASS__, 'filter_yoast_breadcrumbs' ), 20 );
		}
		add_filter( 'rank_math/frontend/breadcrumb/items', array( __CLASS__, 'filter_rank_math_breadcrumbs' ), 20, 2 );
	}

	/**
	 * Whether BreadcrumbList JSON-LD is enabled.
	 *
	 * @return bool
	 */
	private static function is_breadcrumb_json_enabled() {
		$settings = get_option( 'twec_settings', array() );
		if ( ! is_array( $settings ) ) {
			return true;
		}
		if ( isset( $settings['seo_breadcrumb_json_ld'] ) && 'no' === $settings['seo_breadcrumb_json_ld'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Build ordered breadcrumb items for PlanIt (filterable).
	 *
	 * @return array<int, array{name:string,url:string}>
	 */
	public static function get_breadcrumb_items() {
		$items   = array();
		$items[] = array(
			'name' => get_bloginfo( 'name' ),
			'url'  => home_url( '/' ),
		);

		if ( is_singular( 'twec_event' ) ) {
			$items[] = array(
				'name' => _x( 'Events', 'breadcrumb label', 'planit-event-manager' ),
				'url'  => get_post_type_archive_link( 'twec_event' ),
			);
			$terms   = get_the_terms( get_queried_object_id(), 'twec_event_category' );
			if ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$t       = $terms[0];
				$items[] = array(
					'name' => $t->name,
					'url'  => get_term_link( $t ),
				);
			}
			$items[] = array(
				'name' => get_the_title( get_queried_object_id() ),
				'url'  => get_permalink( get_queried_object_id() ),
			);
		} elseif ( is_post_type_archive( 'twec_event' ) ) {
			$items[] = array(
				'name' => _x( 'Events', 'breadcrumb label', 'planit-event-manager' ),
				'url'  => get_post_type_archive_link( 'twec_event' ),
			);
		} elseif ( is_tax( 'twec_event_category' ) || is_tax( 'twec_event_tag' ) ) {
			$items[] = array(
				'name' => _x( 'Events', 'breadcrumb label', 'planit-event-manager' ),
				'url'  => get_post_type_archive_link( 'twec_event' ),
			);
			$term    = get_queried_object();
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				$items[] = array(
					'name' => $term->name,
					'url'  => get_term_link( $term ),
				);
			}
		} else {
			return array();
		}
		/**
		 * Breadcrumb items for PlanIt (name + url) before JSON-LD.
		 *
		 * @param array $items Breadcrumb items.
		 */
		$items = apply_filters( 'twec_breadcrumb_items', $items );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Print BreadcrumbList JSON-LD.
	 *
	 * @return void
	 */
	public static function output_breadcrumb_json_ld() {
		if ( ! self::is_breadcrumb_json_enabled() ) {
			return;
		}
		if ( ! is_post_type_archive( 'twec_event' ) && ! is_singular( 'twec_event' ) && ! is_tax( 'twec_event_category' ) && ! is_tax( 'twec_event_tag' ) ) {
			return;
		}
		$raw = self::get_breadcrumb_items();
		if ( empty( $raw ) ) {
			return;
		}
		$list = array();
		$pos  = 1;
		foreach ( $raw as $one ) {
			if ( empty( $one['name'] ) || empty( $one['url'] ) ) {
				++$pos;
				continue;
			}
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => $one['name'],
				'item'     => $one['url'],
			);
			++$pos;
		}
		if ( empty( $list ) ) {
			return;
		}
		$out = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list,
		);
		$out = apply_filters( 'twec_breadcrumb_list_json_ld', $out, $raw );
		if ( empty( $out ) || ! is_array( $out ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n";
	}

	/**
	 * Yoast SEO: prepend Events archive when on single event.
	 *
	 * @param array $links Breadcrumb links.
	 * @return array
	 */
	public static function filter_yoast_breadcrumbs( $links ) {
		if ( ! is_singular( 'twec_event' ) || ! is_array( $links ) ) {
			return $links;
		}
		$archive = get_post_type_archive_link( 'twec_event' );
		if ( ! $archive ) {
			return $links;
		}
		$label = _x( 'Events', 'breadcrumb label', 'planit-event-manager' );
		$add   = array(
			'url'  => $archive,
			'text' => $label,
		);
		if ( ! empty( $links[0] ) && is_array( $links[0] ) && isset( $links[0]['url'] ) && (string) $archive === (string) $links[0]['url'] ) {
			return $links;
		}
		return array_merge( array( $add ), $links );
	}

	/**
	 * Rank Math: prepend Events archive.
	 *
	 * @param array  $items Breadcrumb item rows.
	 * @param object $breadcrumbs Breadcrumbs class instance.
	 * @return array
	 */
	public static function filter_rank_math_breadcrumbs( $items, $breadcrumbs = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! is_singular( 'twec_event' ) || ! is_array( $items ) ) {
			return $items;
		}
		$archive = get_post_type_archive_link( 'twec_event' );
		if ( ! $archive ) {
			return $items;
		}
		foreach ( $items as $row ) {
			if ( is_string( $row[0] ?? null ) && (string) $row[0] === (string) $archive ) {
				return $items;
			}
		}
		$label = _x( 'Events', 'breadcrumb label', 'planit-event-manager' );
		$add   = array( $archive, $label );
		return array_merge( array( $add ), $items );
	}
}

TWEC_Breadcrumbs::init();
