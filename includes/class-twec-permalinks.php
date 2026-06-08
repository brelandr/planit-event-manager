<?php
/**
 * Optional hierarchical event URLs: /events/{category}/{postname}/
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permalink structure for events.
 */
class TWEC_Permalinks {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_event_rewrite_token' ), 5 );
		add_filter( 'register_post_type_args', array( __CLASS__, 'filter_post_type_args' ), 20, 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'filter_post_type_link' ), 20, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_flush_after_settings' ) );
	}

	/**
	 * Register %twec_event_category% tag for use in the events rewrite slug.
	 *
	 * @return void
	 */
	public static function register_event_rewrite_token() {
		add_rewrite_tag( '%twec_event_category%', '([^/]+)' );
	}

	/**
	 * When enabled, use events/%twec_event_category% as the post type slug.
	 *
	 * @param array  $args      Post type args.
	 * @param string $post_type Post type name.
	 * @return array
	 */
	public static function filter_post_type_args( $args, $post_type ) {
		if ( 'twec_event' !== $post_type || ! is_array( $args ) ) {
			return $args;
		}
		$settings = get_option( 'twec_settings', array() );
		if ( ! is_array( $settings ) || ! isset( $settings['hierarchical_event_urls'] ) || 'yes' !== $settings['hierarchical_event_urls'] ) {
			return $args;
		}
		if ( empty( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
			$args['rewrite'] = array();
		}
		$args['rewrite']['slug']       = 'events/%twec_event_category%';
		$args['rewrite']['with_front'] = false;
		return $args;
	}

	/**
	 * Replace %twec_event_category% in event permalinks.
	 *
	 * @param string  $link Post link.
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public static function filter_post_type_link( $link, $post ) {
		if ( ! is_object( $post ) || 'twec_event' !== $post->post_type ) {
			return $link;
		}
		if ( false === strpos( $link, '%twec_event_category%' ) ) {
			return $link;
		}
		$settings = get_option( 'twec_settings', array() );
		if ( ! is_array( $settings ) || ! isset( $settings['hierarchical_event_urls'] ) || 'yes' !== $settings['hierarchical_event_urls'] ) {
			return $link;
		}
		$terms = get_the_terms( $post->ID, 'twec_event_category' );
		$slug  = 'uncategorized';
		if ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$slug = $terms[0]->slug;
		}
		return str_replace( '%twec_event_category%', $slug, $link );
	}

	/**
	 * Request rewrite rules flush after hierarchical URL option changes.
	 *
	 * @return void
	 */
	public static function maybe_flush_after_settings() {
		$settings = get_option( 'twec_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['_twec_flush_rewrite_flag'] ) ) {
			return;
		}
		unset( $settings['_twec_flush_rewrite_flag'] );
		update_option( 'twec_settings', $settings );
		flush_rewrite_rules( false );
	}
}

TWEC_Permalinks::init();
