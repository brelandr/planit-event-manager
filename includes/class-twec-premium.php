<?php
/**
 * Premium features handler.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-premium.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Premium features handler.
 *
 * Manages premium feature availability and upgrade notices.
 */
class TWEC_Premium {

	/**
	 * Premium upgrade URL.
	 */
	const UPGRADE_URL = 'https://landtechwebdesigns.com/planit-event-manager-premium';

	/**
	 * InstaWP (or filtered) URL for trying Premium in a browser sandbox.
	 *
	 * @return string Non-empty URL or empty if unavailable.
	 */
	public static function get_premium_live_demo_url() {
		if ( defined( 'PLANIT_PREMIUM_LIVE_DEMO_URL' ) && is_string( PLANIT_PREMIUM_LIVE_DEMO_URL ) ) {
			$url = trim( PLANIT_PREMIUM_LIVE_DEMO_URL );
			return $url;
		}
		return '';
	}

	/**
	 * Check if a premium feature is available.
	 *
	 * @param string $feature Feature name to check (unused in free version).
	 * @return bool True if feature is available, false otherwise.
	 */
	public static function is_available( $feature = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Parameter kept for API consistency.
		if ( class_exists( 'TWEC_License', false ) && method_exists( 'TWEC_License', 'is_licensed' ) && TWEC_License::is_licensed() ) {
			return true;
		}
		return false;
	}

	/**
	 * Get upgrade notice HTML.
	 *
	 * @param string $feature_name Name of the premium feature.
	 * @param string $context Context where notice is shown (admin, frontend).
	 * @return string HTML for upgrade notice.
	 */
	public static function get_upgrade_notice( $feature_name = '', $context = 'admin' ) {
		$upgrade_url = self::UPGRADE_URL;
		/* translators: %s: Feature name */
		$feature_text = $feature_name ? sprintf( __( '%s is a premium feature.', 'planit-event-manager' ), esc_html( $feature_name ) ) : __( 'This is a premium feature.', 'planit-event-manager' );

		if ( 'admin' === $context ) {
			$demo_url = self::get_premium_live_demo_url();
			$demo_btn = '';
			if ( '' !== $demo_url ) {
				$demo_btn = sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer" class="button" style="margin-left: 10px;">%2$s</a>',
					esc_url( $demo_url ),
					esc_html__( 'Try Premium Version', 'planit-event-manager' )
				);
			}
			return sprintf(
				'<div class="twec-upgrade-notice notice notice-info" style="margin: 15px 0; padding: 12px; background: #fff; border-left: 4px solid #0073aa;">
					<p style="margin: 0 0 8px 0;"><strong>%s</strong></p>
					<p style="margin: 0 0 8px 0;">%s</p>
					<p style="margin: 0;">
						<a href="%s" target="_blank" rel="noopener noreferrer" class="button button-primary" style="margin-right: 10px;">%s</a>
						<a href="%s" target="_blank" rel="noopener noreferrer" class="button" style="margin-right: 10px;">%s</a>
						%s
					</p>
				</div>',
				esc_html( $feature_text ),
				esc_html__( 'Upgrade to Premium to unlock this feature and many more!', 'planit-event-manager' ),
				esc_url( $upgrade_url ),
				esc_html__( 'Upgrade to Premium', 'planit-event-manager' ),
				esc_url( $upgrade_url ),
				esc_html__( 'Learn More', 'planit-event-manager' ),
				$demo_btn // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped fragments above.
			);
		} else {
			return sprintf(
				'<div class="twec-upgrade-notice-frontend" style="padding: 20px; margin: 20px 0; background: #f0f6fc; border: 1px solid #0073aa; border-radius: 5px; text-align: center;">
					<p style="margin: 0 0 10px 0; font-size: 16px; font-weight: 600; color: #0073aa;">%s</p>
					<p style="margin: 0 0 15px 0; color: #555;">%s</p>
					<a href="%s" target="_blank" rel="noopener" class="button button-primary" style="text-decoration: none; display: inline-block; padding: 10px 20px; background: #0073aa; color: #fff; border-radius: 3px;">%s</a>
				</div>',
				esc_html( $feature_text ),
				esc_html__( 'Upgrade to Premium to unlock this feature and many more!', 'planit-event-manager' ),
				esc_url( $upgrade_url ),
				esc_html__( 'Upgrade to Premium', 'planit-event-manager' )
			);
		}
	}

	/**
	 * Get list of premium features.
	 *
	 * @return array List of premium feature names.
	 */
	public static function get_premium_features() {
		return array(
			__( 'Week View', 'planit-event-manager' ),
			__( 'Year View', 'planit-event-manager' ),
			__( 'Photo View', 'planit-event-manager' ),
			__( 'Map View', 'planit-event-manager' ),
			__( 'Recurring Events', 'planit-event-manager' ),
			__( 'Custom Fields', 'planit-event-manager' ),
			__( 'Event Series', 'planit-event-manager' ),
			__( 'Featured Events', 'planit-event-manager' ),
			__( 'CSV Import', 'planit-event-manager' ),
			__( 'The Events Calendar Import', 'planit-event-manager' ),
			__( 'Event Cost/Price', 'planit-event-manager' ),
			__( 'Event Website', 'planit-event-manager' ),
			__( 'Event Timezone', 'planit-event-manager' ),
			__( 'RSS Feed', 'planit-event-manager' ),
			__( 'Featured Events Widget', 'planit-event-manager' ),
			__( 'Event Series Widget', 'planit-event-manager' ),
			__( 'Countdown Widget', 'planit-event-manager' ),
		);
	}
}
