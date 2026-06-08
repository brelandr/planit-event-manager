<?php
/**
 * Dismissible onboarding checklist for new installs.
 *
 * @package    The_Event_Calendar
 * @subpackage admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a one-time (dismissible) checklist to site admins.
 */
class TWEC_Onboarding {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'admin_post_twec_dismiss_onboarding', array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Whether the checklist should be shown.
	 *
	 * @return bool
	 */
	private static function should_show() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$user = get_current_user_id();
		if ( ! $user || get_user_meta( $user, 'twec_onboarding_dismissed', true ) ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return false;
		}
		return true;
	}

	/**
	 * Dismiss action (admin_post).
	 *
	 * @return void
	 */
	public static function dismiss() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified with wp_verify_nonce( wp_unslash( ... ), ... ).
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'twec_dismiss_onboarding' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'planit-event-manager' ), esc_html__( 'Onboarding', 'planit-event-manager' ), array( 'response' => 403 ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'planit-event-manager' ), esc_html__( 'Onboarding', 'planit-event-manager' ), array( 'response' => 403 ) );
		}
		update_user_meta( get_current_user_id(), 'twec_onboarding_dismissed', '1' );
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * Render the checklist on the main Dashboard.
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! self::should_show() ) {
			return;
		}

		$event_count = (int) wp_count_posts( 'twec_event' )->publish;
		$venue_count = (int) wp_count_posts( 'twec_venue' )->publish;
		$ok_event    = $event_count > 0;
		$ok_venue    = $venue_count > 0;

		$settings = admin_url( 'edit.php?post_type=twec_event&page=twec-settings' );
		$add      = admin_url( 'post-new.php?post_type=twec_event' );
		$venue    = admin_url( 'post-new.php?post_type=twec_venue' );
		$dismiss  = wp_nonce_url(
			add_query_arg(
				'action',
				'twec_dismiss_onboarding',
				admin_url( 'admin-post.php' )
			),
			'twec_dismiss_onboarding'
		);

		?>
		<div class="notice notice-info is-dismissible" id="twec-onboarding-checklist" style="position:relative">
			<p><strong><?php esc_html_e( 'PlanIt Event Manager — get started', 'planit-event-manager' ); ?></strong></p>
			<ol style="list-style: decimal; margin-left:1.5em">
				<li style="<?php echo $ok_venue ? 'text-decoration: line-through; opacity: 0.7;' : ''; ?>">
					<?php esc_html_e( 'Add a venue (optional but useful for addresses and maps).', 'planit-event-manager' ); ?>
					<?php if ( ! $ok_venue ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $venue ); ?>"><?php esc_html_e( 'Add venue', 'planit-event-manager' ); ?></a>
					<?php endif; ?>
				</li>
				<li style="<?php echo $ok_event ? 'text-decoration: line-through; opacity: 0.7;' : ''; ?>">
					<?php esc_html_e( 'Create your first event.', 'planit-event-manager' ); ?>
					<?php if ( ! $ok_event ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $add ); ?>"><?php esc_html_e( 'Add event', 'planit-event-manager' ); ?></a>
					<?php endif; ?>
				</li>
				<li>
					<?php esc_html_e( 'Review settings (permalinks, Google Maps key, and sample events).', 'planit-event-manager' ); ?>
					<a class="button button-small" href="<?php echo esc_url( $settings ); ?>"><?php esc_html_e( 'Open settings', 'planit-event-manager' ); ?></a>
				</li>
			</ol>
			<p>
				<?php esc_html_e( 'Add the calendar to a page with a block, or the shortcode', 'planit-event-manager' ); ?>
				<code>[twec_calendar]</code> <?php esc_html_e( 'or', 'planit-event-manager' ); ?> <code>[twec_list]</code>.
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss this message', 'planit-event-manager' ); ?></a>
			</p>
		</div>
		<?php
	}
}
