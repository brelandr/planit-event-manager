<?php
/**
 * Shortcodes functionality.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Shortcodes {

	/**
	 * Initialize shortcodes.
	 */
	public function __construct() {
		add_shortcode( 'twec_calendar', array( $this, 'calendar_shortcode' ) );
		add_shortcode( 'twec_list', array( $this, 'list_shortcode' ) );
	}

	/**
	 * Calendar shortcode.
	 */
	public function calendar_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'view' => 'month',
			'category' => '',
			'tag' => '',
		), $atts );

		ob_start();
		include TWEC_PLUGIN_DIR . 'public/partials/twec-calendar.php';
		return ob_get_clean();
	}

	/**
	 * List shortcode.
	 */
	public function list_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'per_page' => 10,
			'category' => '',
			'tag' => '',
			'past_events' => 'hide',
		), $atts );

		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
		
		$args = array(
			'post_type' => 'twec_event',
			'posts_per_page' => intval( $atts['per_page'] ),
			'paged' => $paged,
			'orderby' => 'meta_value',
			'meta_key' => '_twec_event_start_date',
			'order' => 'ASC',
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'twec_event_category',
				'field' => 'slug',
				'terms' => $atts['category'],
			);
		}

		if ( ! empty( $atts['tag'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'twec_event_tag',
				'field' => 'slug',
				'terms' => $atts['tag'],
			);
		}

		if ( 'hide' === $atts['past_events'] ) {
			$args['meta_query'][] = array(
				'key' => '_twec_event_end_date',
				'value' => current_time( 'mysql' ),
				'compare' => '>=',
				'type' => 'DATETIME',
			);
		}

		$events_query = new WP_Query( $args );
		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		ob_start();
		include TWEC_PLUGIN_DIR . 'public/partials/twec-list.php';
		$output = ob_get_clean();
		wp_reset_postdata();
		
		return $output;
	}
}

new TWEC_Shortcodes();

