<?php
/**
 * Calendar Interactivity API (WordPress 6.5+) — toggle helpers.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the front-end calendar should use the Interactivity API store.
 *
 * Respects: WP 6.5+ APIs, Settings > "Calendar: Interactivity API", shortcode `interactivity`,
 * and filter `twec_use_interactivity`.
 *
 * @param array $shortcode_atts Shortcode or block-mapped attributes (e.g. interactivity, enableInteractivity).
 * @return bool
 */
function twec_calendar_should_use_interactivity( $shortcode_atts = array() ) {
	if ( ! is_array( $shortcode_atts ) ) {
		$shortcode_atts = array();
	}

	if ( ! function_exists( 'wp_interactivity_state' ) || ! function_exists( 'wp_enqueue_script_module' ) ) {
		return false;
	}

	$settings = get_option( 'twec_settings', array() );
	$opt      = isset( $settings['calendar_interactivity'] ) ? (string) $settings['calendar_interactivity'] : 'yes';
	if ( 'no' === $opt ) {
		return false;
	}

	$inter = '';
	if ( isset( $shortcode_atts['interactivity'] ) && '' !== (string) $shortcode_atts['interactivity'] ) {
		$inter = strtolower( (string) $shortcode_atts['interactivity'] );
	} elseif ( array_key_exists( 'enableInteractivity', $shortcode_atts ) ) {
		$e = $shortcode_atts['enableInteractivity'];
		if ( is_bool( $e ) ) {
			$inter = $e ? 'yes' : 'no';
		} else {
			$inter = strtolower( (string) $e );
		}
	}
	if ( in_array( $inter, array( 'no', '0', 'false', 'off' ), true ) ) {
		return false;
	}

	$on = (bool) apply_filters( 'twec_use_interactivity', true, $shortcode_atts );
	return (bool) $on;
}

/**
 * Whether the compact event list should use enhanced client-side modal behavior.
 *
 * @param array $shortcode_atts Shortcode or block-mapped attributes.
 * @return bool
 */
function twec_compact_should_use_interactivity( $shortcode_atts = array() ) {
	if ( ! is_array( $shortcode_atts ) ) {
		$shortcode_atts = array();
	}

	$settings = get_option( 'twec_settings', array() );
	$opt      = isset( $settings['compact_list_interactivity'] ) ? (string) $settings['compact_list_interactivity'] : '';
	if ( '' === $opt ) {
		$opt = isset( $settings['calendar_interactivity'] ) ? (string) $settings['calendar_interactivity'] : 'yes';
	}
	if ( 'no' === $opt ) {
		return false;
	}

	$inter = '';
	if ( isset( $shortcode_atts['interactivity'] ) && '' !== (string) $shortcode_atts['interactivity'] ) {
		$inter = strtolower( (string) $shortcode_atts['interactivity'] );
	} elseif ( array_key_exists( 'enableInteractivity', $shortcode_atts ) ) {
		$e = $shortcode_atts['enableInteractivity'];
		$inter = is_bool( $e ) ? ( $e ? 'yes' : 'no' ) : strtolower( (string) $e );
	}
	if ( in_array( $inter, array( 'no', '0', 'false', 'off' ), true ) ) {
		return false;
	}

	return (bool) apply_filters( 'twec_use_compact_interactivity', true, $shortcode_atts );
}
