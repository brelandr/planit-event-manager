<?php
/**
 * Review request functionality.
 *
 * Displays an admin notice asking users for a review after 7 days of plugin usage.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-review-request.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review request class.
 *
 * Handles the "Enjoying [Plugin Name]?" notice with Yes, No/Support, and Dismiss actions.
 */
class TWEC_Review_Request {

	/**
	 * Option key for install date.
	 */
	const OPTION_INSTALL_DATE = 'twec_install_date';

	/**
	 * Option key for dismissed state.
	 */
	const OPTION_DISMISSED = 'twec_review_dismissed';

	/**
	 * Plugin slug for WordPress.org URLs.
	 */
	const PLUGIN_SLUG = 'planit-event-manager';

	/**
	 * Plugin display name.
	 */
	const PLUGIN_NAME = 'PlanIt Event Manager';

	/**
	 * Days until notice can be shown.
	 */
	const DAYS_UNTIL_PROMPT = 7;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_show_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_script' ) );
		add_action( 'wp_ajax_twec_dismiss_review_request', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Ensure install date is set (for upgrades from versions before this feature).
	 */
	private function ensure_install_date() {
		if ( false === get_option( self::OPTION_INSTALL_DATE, false ) ) {
			update_option( self::OPTION_INSTALL_DATE, time() );
		}
	}

	/**
	 * Check if we're on a plugin admin screen.
	 *
	 * @return bool True if on a plugin-specific admin page.
	 */
	private function is_plugin_admin_screen() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		$plugin_screens = array(
			'edit-twec_event',
			'edit-twec_venue',
			'edit-twec_organizer',
			'twec_event',
			'twec_venue',
			'twec_organizer',
			'twec_event_page_twec-settings',
			'twec_event_page_twec-diagnostics',
			'twec_event_page_twec-upgrade',
		);
		return in_array( $screen->id, $plugin_screens, true );
	}

	/**
	 * Check if the review notice should be shown.
	 *
	 * @return bool True if notice should be displayed.
	 */
	private function should_show_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( get_option( self::OPTION_DISMISSED, false ) ) {
			return false;
		}
		if ( ! $this->is_plugin_admin_screen() ) {
			return false;
		}

		$this->ensure_install_date();

		$install_date = (int) get_option( self::OPTION_INSTALL_DATE, 0 );
		$days_elapsed = ( time() - $install_date ) / DAY_IN_SECONDS;

		return $days_elapsed >= self::DAYS_UNTIL_PROMPT;
	}

	/**
	 * Display the review request notice if conditions are met.
	 */
	public function maybe_show_notice() {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$reviews_url = 'https://wordpress.org/support/plugin/' . self::PLUGIN_SLUG . '/reviews/#new-post';
		$support_url = 'https://wordpress.org/support/plugin/' . self::PLUGIN_SLUG . '/';
		$nonce       = wp_create_nonce( 'twec_dismiss_review_request' );

		?>
		<div class="notice notice-info twec-review-request-notice" id="twec-review-request-notice">
			<p>
				<?php
				printf(
					/* translators: %s: Plugin name */
					esc_html__( 'Enjoying %s?', 'planit-event-manager' ),
					'<strong>' . esc_html( self::PLUGIN_NAME ) . '</strong>'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $reviews_url ); ?>" target="_blank" rel="noopener" class="button button-primary twec-review-btn" data-action="yes">
					<?php esc_html_e( 'Yes, leave a review!', 'planit-event-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener" class="button twec-review-btn" data-action="support">
					<?php esc_html_e( 'No / Support', 'planit-event-manager' ); ?>
				</a>
				<button type="button" class="button button-link twec-review-btn" data-action="dismiss">
					<?php esc_html_e( 'Dismiss', 'planit-event-manager' ); ?>
				</button>
			</p>
			<input type="hidden" id="twec-review-nonce" value="<?php echo esc_attr( $nonce ); ?>" />
		</div>
		<?php
	}

	/**
	 * Enqueue the dismissal script only when the notice is shown.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function maybe_enqueue_script( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by WordPress hook.
		if ( ! $this->should_show_notice() ) {
			return;
		}

		wp_enqueue_script(
			'twec-review-request',
			PLANIT_EVENT_MANAGER_URL . 'admin/js/twec-review-request.js',
			array( 'jquery' ),
			PLANIT_EVENT_MANAGER_VERSION,
			true
		);

		wp_localize_script(
			'twec-review-request',
			'twecReviewRequest',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'twec_dismiss_review_request' ),
			)
		);
	}

	/**
	 * Handle AJAX dismissal request.
	 */
	public function ajax_dismiss() {
		if ( ! check_ajax_referer( 'twec_dismiss_review_request', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'planit-event-manager' ) ),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'planit-event-manager' ) ),
				403
			);
		}

		update_option( self::OPTION_DISMISSED, true );

		wp_send_json_success();
	}
}
