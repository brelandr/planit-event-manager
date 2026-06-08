<?php
/**
 * Premium feature flags when the free package’s {@see TWEC_Premium} is not loaded (e.g. premium-only runtime).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'twec_premium_view_available' ) ) {
	/**
	 * Whether an optional calendar view (week, year, photo, map) or RSS is available in this environment.
	 *
	 * @param string $slug week|year|photo|map|rss|recurring|...
	 * @return bool
	 */
	function twec_premium_view_available( $slug ) {
		if ( class_exists( 'TWEC_Premium' ) && is_callable( array( 'TWEC_Premium', 'is_available' ) ) && TWEC_Premium::is_available( (string) $slug ) ) {
			return true;
		}
		if ( ! in_array( (string) $slug, array( 'week', 'year', 'photo', 'map', 'rss' ), true ) ) {
			return false;
		}
		return class_exists( 'TWEC_License' ) && method_exists( 'TWEC_License', 'is_licensed' ) && TWEC_License::is_licensed();
	}
}
