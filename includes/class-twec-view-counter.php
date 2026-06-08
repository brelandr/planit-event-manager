<?php
/**
 * Cookieless single-event view counter (server-side meta increment).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks template_redirect to bump _twec_view_count when enabled in settings.
 */
class TWEC_View_Counter {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_increment' ), 1 );
		add_filter( 'manage_twec_event_posts_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_twec_event_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Increment view count once per request for singular events (no cookies).
	 *
	 * @return void
	 */
	public static function maybe_increment() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		$settings = get_option( 'twec_settings', array() );
		if ( empty( $settings['cookieless_view_counter'] ) || 'yes' !== $settings['cookieless_view_counter'] ) {
			return;
		}
		if ( ! is_singular( 'twec_event' ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( post_password_required( $post ) ) {
			return;
		}
		$n = (int) get_post_meta( $post->ID, '_twec_view_count', true );
		update_post_meta( $post->ID, '_twec_view_count', $n + 1 );
	}

	/**
	 * Add the Views column to the event list table header.
	 *
	 * @param string[] $cols Columns.
	 * @return string[]
	 */
	public static function column( $cols ) {
		$cols['twec_views'] = __( 'Views', 'planit-event-manager' );
		return $cols;
	}

	/**
	 * Output the view count for an event row.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'twec_views' !== $column ) {
			return;
		}
		echo (int) get_post_meta( $post_id, '_twec_view_count', true );
	}
}
