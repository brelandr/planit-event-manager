<?php
/**
 * Premium features handler.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Premium {

	/**
	 * Premium upgrade URL.
	 */
	const UPGRADE_URL = 'https://landtechwebdesigns.com/the-wordpress-event-calendar-premium';

	/**
	 * Check if a premium feature is available.
	 *
	 * @param string $feature Feature name to check.
	 * @return bool True if feature is available, false otherwise.
	 */
	public static function is_available( $feature = '' ) {
		// In free version, all premium features return false
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
		$feature_text = $feature_name ? sprintf( __( '%s is a premium feature.', 'the-wordpress-event-calendar' ), esc_html( $feature_name ) ) : __( 'This is a premium feature.', 'the-wordpress-event-calendar' );
		
		if ( 'admin' === $context ) {
			return sprintf(
				'<div class="twec-upgrade-notice notice notice-info" style="margin: 15px 0; padding: 12px; background: #fff; border-left: 4px solid #0073aa;">
					<p style="margin: 0 0 8px 0;"><strong>%s</strong></p>
					<p style="margin: 0 0 8px 0;">%s</p>
					<p style="margin: 0;">
						<a href="%s" target="_blank" rel="noopener" class="button button-primary" style="margin-right: 10px;">%s</a>
						<a href="%s" target="_blank" rel="noopener" class="button">%s</a>
					</p>
				</div>',
				esc_html( $feature_text ),
				esc_html__( 'Upgrade to Premium to unlock this feature and many more!', 'the-wordpress-event-calendar' ),
				esc_url( $upgrade_url ),
				esc_html__( 'Upgrade to Premium', 'the-wordpress-event-calendar' ),
				esc_url( $upgrade_url ),
				esc_html__( 'Learn More', 'the-wordpress-event-calendar' )
			);
		} else {
			return sprintf(
				'<div class="twec-upgrade-notice-frontend" style="padding: 20px; margin: 20px 0; background: #f0f6fc; border: 1px solid #0073aa; border-radius: 5px; text-align: center;">
					<p style="margin: 0 0 10px 0; font-size: 16px; font-weight: 600; color: #0073aa;">%s</p>
					<p style="margin: 0 0 15px 0; color: #555;">%s</p>
					<a href="%s" target="_blank" rel="noopener" class="button button-primary" style="text-decoration: none; display: inline-block; padding: 10px 20px; background: #0073aa; color: #fff; border-radius: 3px;">%s</a>
				</div>',
				esc_html( $feature_text ),
				esc_html__( 'Upgrade to Premium to unlock this feature and many more!', 'the-wordpress-event-calendar' ),
				esc_url( $upgrade_url ),
				esc_html__( 'Upgrade to Premium', 'the-wordpress-event-calendar' )
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
			__( 'Week View', 'the-wordpress-event-calendar' ),
			__( 'Year View', 'the-wordpress-event-calendar' ),
			__( 'Photo View', 'the-wordpress-event-calendar' ),
			__( 'Map View', 'the-wordpress-event-calendar' ),
			__( 'Recurring Events', 'the-wordpress-event-calendar' ),
			__( 'Custom Fields', 'the-wordpress-event-calendar' ),
			__( 'Event Series', 'the-wordpress-event-calendar' ),
			__( 'Featured Events', 'the-wordpress-event-calendar' ),
			__( 'CSV Import', 'the-wordpress-event-calendar' ),
			__( 'The Events Calendar Import', 'the-wordpress-event-calendar' ),
			__( 'Event Cost/Price', 'the-wordpress-event-calendar' ),
			__( 'Event Website', 'the-wordpress-event-calendar' ),
			__( 'Event Timezone', 'the-wordpress-event-calendar' ),
			__( 'RSS Feed', 'the-wordpress-event-calendar' ),
			__( 'Featured Events Widget', 'the-wordpress-event-calendar' ),
			__( 'Event Series Widget', 'the-wordpress-event-calendar' ),
			__( 'Countdown Widget', 'the-wordpress-event-calendar' ),
		);
	}
}

