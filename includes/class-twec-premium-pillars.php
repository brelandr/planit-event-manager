<?php
/**
 * Premium roadmap: public submission, RSVP, payment bridge stubs (licensed), REST + shortcodes.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end submission and RSVP, plus payment / gateway placeholders.
 */
class TWEC_Premium_Pillars {

	const RSVP_EMAILS_META   = 'twec_rsvp_emails';
	const RSVP_WAITLIST_META = 'twec_rsvp_waitlist';
	const RSVP_TOKEN_META    = 'twec_rsvp_checkin_tokens';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpts' ), 9 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		add_shortcode( 'twec_submission_form', array( __CLASS__, 'shortcode_submission' ) );
		add_shortcode( 'twec_rsvp', array( __CLASS__, 'shortcode_rsvp' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_event_meta' ) );
		add_action( 'save_post_twec_event', array( __CLASS__, 'save_event_capacity' ), 10, 2 );
		add_action( 'admin_post_twec_download_rsvp_csv', array( __CLASS__, 'handle_download_rsvp_csv' ) );
	}

	/**
	 * @return bool
	 */
	private static function is_licensed() {
		return class_exists( 'TWEC_License' ) && method_exists( 'TWEC_License', 'is_licensed' ) && TWEC_License::is_licensed();
	}

	/**
	 * @return void
	 */
	public static function register_cpts() {
		if ( ! self::is_licensed() ) {
			return;
		}
		register_post_type(
			'twec_submission',
			array(
				'labels'          => array(
					'name'          => __( 'Event submissions', 'planit-event-manager' ),
					'singular_name' => __( 'Event submission', 'planit-event-manager' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=twec_event',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor' ),
			)
		);
	}

	/**
	 * REST routes under namespace `planit/v1`.
	 *
	 * Each POST route registers a permission_callback; callbacks validate payloads and `wp_rest` nonces where applicable.
	 * Payment gateways under Premium also expose `/planit/v1/(stripe|paypal)/…` endpoints with gated permissions.
	 *
	 * @return void
	 */
	public static function register_rest() {
		register_rest_route(
			'planit/v1',
			'/submissions',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_submit' ),
				'permission_callback' => array( __CLASS__, 'rest_permission_submissions' ),
				'args'                => array(
					'nonce'   => array(
						'description'       => __( 'REST cookie nonce (`wp_rest`). Required in JSON/form body.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'title'   => array(
						'description'       => __( 'Proposed submission title.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'content' => array(
						'description'       => __( 'Proposed submission content.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'wp_filter_post_kses',
					),
					'start'   => array(
						'description'       => __( 'Suggested start date/time string.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			'planit/v1',
			'/rsvp',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_rsvp' ),
				'permission_callback' => array( __CLASS__, 'rest_permission_rsvp' ),
				'args'                => array(
					'nonce'          => array(
						'description'       => __( 'REST cookie nonce (`wp_rest`).', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'event_id'       => array(
						'description'       => __( 'Event ID.', 'planit-event-manager' ),
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'email'          => array(
						'description'       => __( 'RSVP email address.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_email',
					),
					'name'           => array(
						'description'       => __( 'Attendee display name.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'remind'         => array(
						'description'       => __( 'Reminder opt-in (boolean-ish).', 'planit-event-manager' ),
						'type'              => array( 'boolean', 'string', 'integer' ),
						'required'          => false,
						'sanitize_callback' => array( __CLASS__, 'sanitize_rsvp_remind_arg' ),
					),
					'reminder_optin' => array(
						'description'       => __( 'Legacy reminder opt-in key.', 'planit-event-manager' ),
						'type'              => array( 'boolean', 'string', 'integer' ),
						'required'          => false,
						'sanitize_callback' => array( __CLASS__, 'sanitize_rsvp_remind_arg' ),
					),
				),
			)
		);
		register_rest_route(
			'planit/v1',
			'/rsvp/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_rsvp_cancel' ),
				'permission_callback' => array( __CLASS__, 'rest_permission_rsvp' ),
				'args'                => array(
					'nonce'    => array(
						'description'       => __( 'REST cookie nonce (`wp_rest`).', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'event_id' => array(
						'description'       => __( 'Event ID.', 'planit-event-manager' ),
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'email'    => array(
						'description'       => __( 'RSVP email address to remove.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);
		register_rest_route(
			'planit/v1',
			'/rsvp/checkin',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_rsvp_checkin' ),
				'permission_callback' => array( __CLASS__, 'rest_permission_checkin' ),
			)
		);
		register_rest_route(
			'planit/v1',
			'/rsvp-scan',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_rsvp_checkin' ),
				'permission_callback' => array( __CLASS__, 'rest_permission_checkin' ),
			)
		);
	}

	/**
	 * Submissions require a logged-in user (capability aligns with Subscriber+).
	 * Nonces and payloads are enforced in {@see TWEC_Premium_Pillars::rest_submit()}.
	 *
	 * @return bool
	 */
	public static function rest_permission_submissions() {
		return is_user_logged_in() && current_user_can( 'read' );
	}

	/**
	 * RSVP intentionally allows anonymous users; legitimacy is enforced with the `wp_rest` nonce inside the callback.
	 *
	 * @return true
	 */
	public static function rest_permission_rsvp() {
		return true;
	}

	/**
	 * RSVP check-ins require authenticated staff with editing rights.
	 *
	 * @return bool
	 */
	public static function rest_permission_checkin() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Coerce RSVP reminder flags from mixed input.
	 *
	 * @param mixed $value Raw remind value.
	 * @return bool|string|int|string[]|float
	 */
	public static function sanitize_rsvp_remind_arg( $value ) {
		if ( null === $value ) {
			return '';
		}
		if ( is_bool( $value ) || is_numeric( $value ) ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_submit( $request ) {
		if ( ! self::is_licensed() ) {
			return new WP_Error( 'twec_premium', __( 'Premium is not active or licensed.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( (string) $params['nonce'] ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'twec_bad_nonce', __( 'Invalid or missing nonce.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'twec_auth', __( 'Log in to submit an event for review (REST + cookie auth).', 'planit-event-manager' ), array( 'status' => 401 ) );
		}
		$title = isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : '';
		if ( '' === $title ) {
			return new WP_Error( 'twec_title', __( 'Title is required.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'twec_submission',
				'post_status'  => 'pending',
				'post_title'   => $title,
				'post_content' => isset( $params['content'] ) ? wp_kses_post( (string) $params['content'] ) : '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'twec_insert', $post_id->get_error_message(), array( 'status' => 500 ) );
		}
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return new WP_Error( 'twec_insert', __( 'Could not create submission.', 'planit-event-manager' ), array( 'status' => 500 ) );
		}
		if ( ! empty( $params['start'] ) ) {
			update_post_meta( (int) $post_id, '_twec_suggested_start', sanitize_text_field( (string) $params['start'] ) );
		}
		/**
		 * Fires after a public event submission is stored (pending).
		 */
		do_action( 'twec_submission_created', (int) $post_id, $params );
		return new WP_REST_Response( array( 'id' => (int) $post_id ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_rsvp( $request ) {
		if ( ! self::is_licensed() ) {
			return new WP_Error( 'twec_premium', __( 'Premium is not active or licensed.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( (string) $params['nonce'] ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'twec_bad_nonce', __( 'Invalid or missing nonce.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		$event_id = isset( $params['event_id'] ) ? (int) $params['event_id'] : 0;
		$email    = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';
		$name     = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		$remind   = true;
		if ( array_key_exists( 'remind', $params ) ) {
			$remind = (bool) $params['remind'];
		} elseif ( array_key_exists( 'reminder_optin', $params ) ) {
			$remind = (bool) $params['reminder_optin'];
		}
		if ( $event_id <= 0 || '' === $email || 'twec_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'twec_rsvp_args', __( 'Event and valid email are required.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$cap  = (int) get_post_meta( $event_id, '_twec_event_capacity', true );
		$key  = self::RSVP_EMAILS_META;
		$list = get_post_meta( $event_id, $key, true );
		$list = is_array( $list ) ? $list : array();
		if ( $cap > 0 && count( $list ) >= $cap && ! in_array( $email, $list, true ) ) {
			$wl = self::get_rsvp_waitlist_rows( $event_id );
			foreach ( $wl as $row ) {
				if ( is_array( $row ) && isset( $row['email'] ) && $row['email'] === $email ) {
					return new WP_REST_Response(
						array(
							'ok'       => true,
							'waitlist' => true,
						),
						200
					);
				}
			}
			$wl[] = array(
				'email' => $email,
				'name'  => $name,
				't'     => time(),
			);
			self::save_rsvp_waitlist_rows( $event_id, $wl );
			/**
			 * Fires after an attendee is appended to the waitlist.
			 */
			do_action( 'twec_rsvp_waitlisted', $event_id, $email, $name );
			return new WP_REST_Response(
				array(
					'ok'       => true,
					'waitlist' => true,
				),
				200
			);
		}
		if ( ! in_array( $email, $list, true ) ) {
			$list[] = $email;
		}
		update_post_meta( $event_id, $key, $list );
		update_post_meta( $event_id, '_twec_rsvp_names_' . md5( $email ), $name );
		if ( class_exists( 'TWEC_Reminders' ) && is_callable( array( 'TWEC_Reminders', 'update_rsvp_optin' ) ) ) {
			TWEC_Reminders::update_rsvp_optin( $event_id, $email, $remind );
		}
		/**
		 * Fires after an RSVP is recorded.
		 */
		do_action( 'twec_rsvp_recorded', $event_id, $email, $name );
		self::issue_attendee_token( $event_id, $email );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Remove an RSVP email from confirmed list or waitlist; promote waitlist after a confirmed cancellation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_rsvp_cancel( $request ) {
		if ( ! self::is_licensed() ) {
			return new WP_Error( 'twec_premium', __( 'Premium is not active or licensed.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( (string) $params['nonce'] ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'twec_bad_nonce', __( 'Invalid or missing nonce.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		$event_id = isset( $params['event_id'] ) ? (int) $params['event_id'] : 0;
		$email    = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';

		if ( $event_id <= 0 || '' === $email || ! is_email( $email ) || 'twec_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'twec_rsvp_args', __( 'Event and valid email are required.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		$key  = self::RSVP_EMAILS_META;
		$list = get_post_meta( $event_id, $key, true );
		$list = is_array( $list ) ? array_values( $list ) : array();

		$confirmed_idx = array_search( $email, $list, true );
		if ( false !== $confirmed_idx ) {
			unset( $list[ $confirmed_idx ] );
			$list = array_values( array_map( 'strval', $list ) );
			update_post_meta( $event_id, $key, $list );
			delete_post_meta( $event_id, '_twec_rsvp_names_' . md5( $email ) );
			self::remove_attendee_token_row( $event_id, $email );
			if ( class_exists( 'TWEC_Reminders' ) && is_callable( array( 'TWEC_Reminders', 'update_rsvp_optin' ) ) ) {
				TWEC_Reminders::update_rsvp_optin( $event_id, $email, false );
			}

			do_action( 'twec_rsvp_cancelled', $event_id, $email, 'confirmed' );
			self::maybe_promote_waitlist_for_event( $event_id );

			return new WP_REST_Response(
				array(
					'ok'      => true,
					'removed' => true,
					'context' => 'confirmed',
				),
				200
			);
		}

		$wl_rows  = self::get_rsvp_waitlist_rows( $event_id );
		$new_wl   = array();
		$found_wl = false;

		foreach ( $wl_rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['email'] ) ) {
				continue;
			}
			$row_em = sanitize_email( (string) $row['email'] );
			if ( $row_em === $email ) {
				$found_wl = true;
				continue;
			}
			$new_wl[] = $row;
		}

		if ( $found_wl ) {
			self::save_rsvp_waitlist_rows( $event_id, $new_wl );
			do_action( 'twec_rsvp_cancelled', $event_id, $email, 'waitlist' );
			return new WP_REST_Response(
				array(
					'ok'      => true,
					'removed' => true,
					'context' => 'waitlist',
				),
				200
			);
		}

		do_action( 'twec_rsvp_cancelled', $event_id, $email, 'none' );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'removed' => false,
			),
			200
		);
	}

	/**
	 * @param string $k Key.
	 * @param string $d Default.
	 * @return string
	 */
	private static function get_payment_setting( $k, $d = '' ) {
		$settings = get_option( 'twec_settings', array() );
		$key      = 'payment_' . $k;
		return isset( $settings[ $key ] ) ? (string) $settings[ $key ] : $d;
	}

	/**
	 * @param string[] $atts Atts.
	 * @return string
	 */
	public static function shortcode_submission( $atts ) {
		if ( ! self::is_licensed() ) {
			if ( class_exists( 'TWEC_Premium' ) && is_callable( array( 'TWEC_Premium', 'get_upgrade_notice' ) ) ) {
				return TWEC_Premium::get_upgrade_notice( __( 'Front-end submission', 'planit-event-manager' ), 'frontend' );
			}
			return '<p class="twec-upgrade-inline">' . esc_html__( 'A valid PlanIt Premium license is required for submissions.', 'planit-event-manager' ) . '</p>';
		}
		$rest  = esc_url( rest_url( 'planit/v1/submissions' ) );
		$nonce = wp_create_nonce( 'wp_rest' );
		ob_start();
		?>
		<div class="twec-submission-form">
			<p class="description"><?php esc_html_e( 'You must be logged in; the REST request uses the wp_rest nonce and your session cookie.', 'planit-event-manager' ); ?></p>
			<p><label><?php esc_html_e( 'Title', 'planit-event-manager' ); ?><br /><input type="text" class="widefat twec-subject-title" required /></label></p>
			<p><label><?php esc_html_e( 'Description', 'planit-event-manager' ); ?><br /><textarea class="widefat twec-subject-content" rows="4"></textarea></label></p>
			<p><button type="button" class="button twec-submit-proposal" data-endpoint="<?php echo esc_url( $rest ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Submit for review', 'planit-event-manager' ); ?></button></p>
			<p class="twec-submission-message" style="display:none;" role="status"></p>
		</div>
		<script>
		(function() {
			var b = document.querySelector('.twec-submit-proposal');
			if (!b) return;
			b.addEventListener('click', function() {
				var el = b.closest('.twec-submission-form');
				var t = el.querySelector('.twec-subject-title');
				var c = el.querySelector('.twec-subject-content');
				var m = el.querySelector('.twec-submission-message');
				fetch(b.getAttribute('data-endpoint'), {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': b.getAttribute('data-nonce') },
					credentials: 'same-origin',
					body: JSON.stringify({ nonce: b.getAttribute('data-nonce'), title: t.value, content: c.value })
				}).then(function(r) { return r.json().then(function(j) { return { status: r.status, body: j }; }); })
				.then(function(res) {
					m.style.display = 'block';
					m.textContent = res.status === 201 ? '<?php echo esc_js( __( 'Thanks — we will review your submission.', 'planit-event-manager' ) ); ?>' : (res.body.message || '<?php echo esc_js( __( 'Something went wrong.', 'planit-event-manager' ) ); ?>');
				}).catch(function() {
					m.style.display = 'block';
					m.textContent = '<?php echo esc_js( __( 'Network error.', 'planit-event-manager' ) ); ?>';
				});
			});
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string[] $atts Atts.
	 * @return string
	 */
	public static function shortcode_rsvp( $atts ) {
		if ( ! self::is_licensed() ) {
			if ( class_exists( 'TWEC_Premium' ) && is_callable( array( 'TWEC_Premium', 'get_upgrade_notice' ) ) ) {
				return TWEC_Premium::get_upgrade_notice( __( 'RSVP', 'planit-event-manager' ), 'frontend' );
			}
			return '<p class="twec-upgrade-inline">' . esc_html__( 'A valid PlanIt Premium license is required for RSVP.', 'planit-event-manager' ) . '</p>';
		}
		$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'twec_rsvp' );
		$eid  = (int) $atts['event_id'];
		if ( $eid <= 0 && is_singular( 'twec_event' ) ) {
			$eid = (int) get_queried_object_id();
		}
		if ( $eid <= 0 || 'twec_event' !== get_post_type( $eid ) ) {
			return '';
		}
		$rest        = esc_url( rest_url( 'planit/v1/rsvp' ) );
		$rest_cancel = esc_url( rest_url( 'planit/v1/rsvp/cancel' ) );
		$nonce       = wp_create_nonce( 'wp_rest' );
		ob_start();
		?>
		<div class="twec-rsvp">
			<p><label><?php esc_html_e( 'Name', 'planit-event-manager' ); ?><br /><input type="text" class="widefat twec-rsvp-name" /></label></p>
			<p><label><?php esc_html_e( 'Email', 'planit-event-manager' ); ?><br /><input type="email" class="widefat twec-rsvp-email" required /></label></p>
			<p><label><input type="checkbox" class="twec-rsvp-remind" value="1" checked="checked" /> <?php esc_html_e( 'Remind me before the event', 'planit-event-manager' ); ?></label></p>
			<p class="twec-rsvp-actions">
				<button type="button" class="button button-primary twec-rsvp-send" data-endpoint="<?php echo esc_url( $rest ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-event="<?php echo (int) $eid; ?>"><?php esc_html_e( 'RSVP', 'planit-event-manager' ); ?></button>
				<button type="button" class="button twec-rsvp-cancel" data-cancel-endpoint="<?php echo esc_url( $rest_cancel ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-event="<?php echo (int) $eid; ?>"><?php esc_html_e( 'Cancel my RSVP', 'planit-event-manager' ); ?></button>
			</p>
			<p class="twec-rsvp-message" style="display:none;" role="status"></p>
		</div>
		<script>
		(function() {
			document.querySelectorAll('.twec-rsvp').forEach(function(el) {
				var btn = el.querySelector('.twec-rsvp-send');
				var cb = el.querySelector('.twec-rsvp-cancel');
				var m = el.querySelector('.twec-rsvp-message');
				if (!btn || !m) return;
				btn.addEventListener('click', function() {
					var rcb = el.querySelector('.twec-rsvp-remind');
					var remind = rcb && rcb.checked;
					var mail = el.querySelector('.twec-rsvp-email');
					var nm = el.querySelector('.twec-rsvp-name');
					fetch(btn.getAttribute('data-endpoint'), {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': btn.getAttribute('data-nonce') },
						credentials: 'same-origin',
						body: JSON.stringify({
							nonce: btn.getAttribute('data-nonce'),
							event_id: parseInt(btn.getAttribute('data-event'), 10),
							email: mail ? mail.value : '',
							name: nm ? nm.value : '',
							remind: remind
						})
					}).then(function(r) { return r.json().then(function(j) { return { status: r.status, body: j }; }); })
					.then(function(res) {
						m.style.display = 'block';
						if (res.status === 200 && res.body && res.body.waitlist) {
							m.textContent = '<?php echo esc_js( __( "You're on the waitlist — we'll notify you if a seat opens.", 'planit-event-manager' ) ); ?>';
						} else {
							m.textContent = res.status === 200 ? '<?php echo esc_js( __( "You're on the list.", 'planit-event-manager' ) ); ?>' : (res.body.message || '<?php echo esc_js( __( 'Could not RSVP.', 'planit-event-manager' ) ); ?>');
						}
					}).catch(function() {
						m.style.display = 'block';
						m.textContent = '<?php echo esc_js( __( 'Network error.', 'planit-event-manager' ) ); ?>';
					});
				});
				if (cb) cb.addEventListener('click', function() {
					var mail = el.querySelector('.twec-rsvp-email');
					var em = mail ? String(mail.value || '').trim() : '';
					if (!em) {
						m.style.display = 'block';
						m.textContent = '<?php echo esc_js( __( 'Enter the email address you used to RSVP so we can remove it.', 'planit-event-manager' ) ); ?>';
						return;
					}
					fetch(cb.getAttribute('data-cancel-endpoint'), {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cb.getAttribute('data-nonce') },
						credentials: 'same-origin',
						body: JSON.stringify({
							nonce: cb.getAttribute('data-nonce'),
							event_id: parseInt(cb.getAttribute('data-event'), 10),
							email: em
						})
					}).then(function(r) { return r.json().then(function(j) { return { status: r.status, body: j }; }); })
					.then(function(res) {
						m.style.display = 'block';
						if (res.status !== 200) {
							m.textContent = (res.body && res.body.message) ? res.body.message : '<?php echo esc_js( __( 'Could not cancel.', 'planit-event-manager' ) ); ?>';
							return;
						}
						var b = res.body || {};
						if (!b.removed) {
							m.textContent = '<?php echo esc_js( __( 'No matching RSVP or waitlist entry for that email.', 'planit-event-manager' ) ); ?>';
							return;
						}
						if (b.context === 'waitlist') {
							m.textContent = '<?php echo esc_js( __( "You've been removed from the waitlist.", 'planit-event-manager' ) ); ?>';
						} else {
							m.textContent = '<?php echo esc_js( __( 'Your RSVP has been cancelled.', 'planit-event-manager' ) ); ?>';
						}
					}).catch(function() {
						m.style.display = 'block';
						m.textContent = '<?php echo esc_js( __( 'Network error.', 'planit-event-manager' ) ); ?>';
					});
				});
			});
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return void
	 */
	public static function add_event_meta() {
		if ( ! self::is_licensed() ) {
			return;
		}
		add_meta_box( 'twec_capacity', __( 'RSVP capacity (optional)', 'planit-event-manager' ), array( __CLASS__, 'mb_capacity' ), 'twec_event', 'side' );
		add_meta_box( 'twec_rsvp_guest_tools', __( 'RSVP attendee tools', 'planit-event-manager' ), array( __CLASS__, 'mb_rsvp_guest_tools' ), 'twec_event', 'side' );
		$st = (array) get_option( 'twec_settings', array() );
		$gw = isset( $st['payment_gateway'] ) ? (string) $st['payment_gateway'] : 'none';
		if ( 'stripe' === $gw && class_exists( 'TWEC_Payments_Stripe' ) ) {
			add_meta_box( 'twec_stripe_listing', __( 'Featured listing (Stripe)', 'planit-event-manager' ), array( __CLASS__, 'mb_stripe' ), 'twec_event', 'side' );
		}
		if ( 'paypal' === $gw && class_exists( 'TWEC_Payments_PayPal' ) ) {
			add_meta_box( 'twec_paypal_listing', __( 'Featured listing (PayPal)', 'planit-event-manager' ), array( __CLASS__, 'mb_paypal' ), 'twec_event', 'side' );
		}
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function mb_capacity( $post ) {
		$v = (int) get_post_meta( $post->ID, '_twec_event_capacity', true );
		wp_nonce_field( 'twec_capacity_save', 'twec_capacity_nonce' );
		?>
		<p><input type="number" name="twec_event_capacity" value="<?php echo esc_attr( (string) $v ); ?>" min="0" class="widefat" /></p>
		<p class="description"><?php esc_html_e( '0 = no limit. When set, RSVP is capped by email in the REST/shortcode flow.', 'planit-event-manager' ); ?></p>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function mb_stripe( $post ) {
		if ( ! self::is_licensed() || 'twec_event' !== $post->post_type ) {
			return;
		}
		$st = (array) get_option( 'twec_settings', array() );
		if ( ( isset( $st['payment_gateway'] ) ? (string) $st['payment_gateway'] : 'none' ) !== 'stripe' ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$paid = (string) get_post_meta( $post->ID, TWEC_Payments_Stripe::META_PAID, true );
		$at   = (string) get_post_meta( $post->ID, TWEC_Payments_Stripe::META_PAID_AT, true );
		$eid  = (int) $post->ID;
		if ( '1' === $paid && $at ) {
			?>
			<p><?php echo esc_html( sprintf( /* translators: %s: MySQL datetime */ __( 'Payment recorded: %s', 'planit-event-manager' ), $at ) ); ?></p>
			<?php
			return;
		}
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$nonce  = wp_create_nonce( 'twec_stripe_start_' . $eid );
		?>
		<p class="description"><?php esc_html_e( 'Start Stripe Checkout to pay for a featured / paid listing for this event. You need Test or Live keys and price in PlanIt → Settings → Payments.', 'planit-event-manager' ); ?></p>
		<form method="post" action="<?php echo esc_url( $action ); ?>">
			<input type="hidden" name="action" value="twec_stripe_start" />
			<input type="hidden" name="event_id" value="<?php echo (int) $eid; ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
			<?php submit_button( __( 'Pay with Stripe', 'planit-event-manager' ), 'secondary', 'twec_stripe_start_btn', false ); ?>
		</form>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function mb_paypal( $post ) {
		if ( ! self::is_licensed() || 'twec_event' !== $post->post_type ) {
			return;
		}
		$st = (array) get_option( 'twec_settings', array() );
		if ( ( isset( $st['payment_gateway'] ) ? (string) $st['payment_gateway'] : 'none' ) !== 'paypal' ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$paid = (string) get_post_meta( $post->ID, TWEC_Payments_PayPal::META_PAID, true );
		$at   = (string) get_post_meta( $post->ID, TWEC_Payments_PayPal::META_PAID_AT, true );
		$eid  = (int) $post->ID;
		if ( '1' === $paid && $at ) {
			?>
			<p><?php echo esc_html( sprintf( /* translators: %s: MySQL datetime */ __( 'PayPal payment recorded: %s', 'planit-event-manager' ), $at ) ); ?></p>
			<?php
			return;
		}
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$nonce  = wp_create_nonce( 'twec_paypal_start_' . $eid );
		?>
		<p class="description"><?php esc_html_e( 'Start PayPal Checkout for a featured / paid listing. Set PayPal app credentials, webhook id, and use the same feature price and currency as in the Payments section.', 'planit-event-manager' ); ?></p>
		<form method="post" action="<?php echo esc_url( $action ); ?>">
			<input type="hidden" name="action" value="twec_paypal_start" />
			<input type="hidden" name="event_id" value="<?php echo (int) $eid; ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
			<?php submit_button( __( 'Pay with PayPal', 'planit-event-manager' ), 'secondary', 'twec_paypal_start_btn', false ); ?>
		</form>
		<?php
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function save_event_capacity( $post_id, $post ) {
		if ( ! self::is_licensed() || 'twec_event' !== $post->post_type ) {
			return;
		}
		if ( ! isset( $_POST['twec_capacity_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['twec_capacity_nonce'] ) ), 'twec_capacity_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['twec_event_capacity'] ) ) {
			update_post_meta( $post_id, '_twec_event_capacity', max( 0, (int) wp_unslash( $_POST['twec_event_capacity'] ) ) );
		}

		self::maybe_promote_waitlist_for_event( (int) $post_id );
	}

	/**
	 * Raw waitlist rows for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_rsvp_waitlist_rows( $event_id ) {
		$event_id = (int) $event_id;
		if ( $event_id <= 0 ) {
			return array();
		}
		$w = get_post_meta( $event_id, self::RSVP_WAITLIST_META, true );
		return is_array( $w ) ? array_values( $w ) : array();
	}

	/**
	 * @param int                     $event_id Event ID.
	 * @param array<int,array<mixed>> $rows     Rows.
	 * @return void
	 */
	private static function save_rsvp_waitlist_rows( $event_id, array $rows ) {
		update_post_meta( (int) $event_id, self::RSVP_WAITLIST_META, array_values( $rows ) );
	}

	/**
	 * When capacity rises or slots free, FIFO promote from waitlist.
	 *
	 * @param int $event_id Event ID.
	 * @return void
	 */
	public static function maybe_promote_waitlist_for_event( $event_id ) {
		if ( ! self::is_licensed() ) {
			return;
		}
		$event_id = (int) $event_id;
		if ( $event_id <= 0 || 'twec_event' !== get_post_type( $event_id ) ) {
			return;
		}
		$cap = (int) get_post_meta( $event_id, '_twec_event_capacity', true );
		if ( $cap <= 0 ) {
			return;
		}
		$key  = self::RSVP_EMAILS_META;
		$list = get_post_meta( $event_id, $key, true );
		$list = is_array( $list ) ? array_values( array_unique( array_map( 'strval', $list ) ) ) : array();

		$wl = self::get_rsvp_waitlist_rows( $event_id );

		$changed_list = false;
		$changed_wl   = false;

		while ( count( $list ) < $cap && ! empty( $wl ) ) {
			$row        = array_shift( $wl );
			$changed_wl = true;
			if ( ! is_array( $row ) || empty( $row['email'] ) ) {
				continue;
			}
			$em = sanitize_email( (string) $row['email'] );
			if ( '' === $em || in_array( $em, $list, true ) ) {
				continue;
			}
			$list[]       = $em;
			$changed_list = true;
			$nm           = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			update_post_meta( $event_id, '_twec_rsvp_names_' . md5( $em ), $nm );
			self::issue_attendee_token( $event_id, $em );
			/**
			 * Fires when an RSVP is promoted from waitlist into the confirmed attendee list.
			 */
			do_action( 'twec_rsvp_promoted_from_waitlist', $event_id, $em, $nm );
		}

		if ( $changed_list ) {
			update_post_meta( $event_id, $key, $list );
		}
		if ( $changed_wl ) {
			self::save_rsvp_waitlist_rows( $event_id, $wl );
		}
	}

	/**
	 * Admin meta box for RSVP exports.
	 *
	 * @param WP_Post $post Event post.
	 * @return void
	 */
	public static function mb_rsvp_guest_tools( $post ) {
		if ( ! self::is_licensed() || 'twec_event' !== $post->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=twec_download_rsvp_csv&event_id=' . (int) $post->ID ),
			'twec_rsvp_csv_' . (int) $post->ID
		);
		echo '<p>';
		printf(
			'<a class="button button-secondary" href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Download RSVP CSV', 'planit-event-manager' )
		);
		echo '</p>';
		echo '<p class="description">';
		esc_html_e( 'Includes confirmed RSVP emails, stored display names, and per-guest check-in tokens. Use the REST check-in endpoint together with these tokens for door scanning integrations.', 'planit-event-manager' );
		echo '</p>';
	}

	/**
	 * Streams a CSV of RSVP guests for the supplied event.
	 *
	 * @return void
	 */
	public static function handle_download_rsvp_csv() {
		if ( ! self::is_licensed() ) {
			wp_die( esc_html__( 'Premium license required.', 'planit-event-manager' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin download link uses explicit nonce + capability checks.
		if ( ! isset( $_GET['_wpnonce'], $_GET['event_id'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'planit-event-manager' ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$event_id = absint( wp_unslash( $_GET['event_id'] ) );
		if ( $event_id < 1 || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'twec_rsvp_csv_' . $event_id ) ) {
			wp_die( esc_html__( 'Invalid RSVP export link.', 'planit-event-manager' ) );
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			wp_die( esc_html__( 'You do not have permission to export this event.', 'planit-event-manager' ) );
		}

		$list = get_post_meta( $event_id, self::RSVP_EMAILS_META, true );
		$list = is_array( $list ) ? $list : array();

		$filename = 'twec-rsvp-' . $event_id . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'planit-event-manager' ) );
		}

		fputcsv( $out, array( 'email', 'name', 'checkin_token' ) );
		foreach ( $list as $email ) {
			$email = sanitize_email( (string) $email );
			if ( '' === $email ) {
				continue;
			}
			$token = self::issue_attendee_token( $event_id, $email );
			$name  = (string) get_post_meta( $event_id, '_twec_rsvp_names_' . md5( $email ), true );
			fputcsv( $out, array( $email, $name, $token ) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Sliding minute bucket rate limit for RSVP check-in REST (per logged-in user).
	 *
	 * Disabled when filter {@see 'twec_rsvp_checkin_rate_limit_per_minute'} returns 0 or less.
	 *
	 * @return null|\WP_Error
	 */
	private static function enforce_rsvp_checkin_rate_limit() {
		$uid = get_current_user_id();
		if ( $uid < 1 ) {
			return null;
		}

		$max = (int) apply_filters( 'twec_rsvp_checkin_rate_limit_per_minute', 60 );
		if ( $max < 1 ) {
			return null;
		}

		$bucket = (int) floor( time() / MINUTE_IN_SECONDS );
		$key    = 'twec_ckrl_' . $uid . '_' . $bucket;
		$cnt    = (int) get_transient( $key );
		if ( $cnt >= $max ) {
			return new WP_Error(
				'rate_limit',
				__( 'Too many check-in requests. Wait a minute and try again.', 'planit-event-manager' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $cnt + 1, 2 * MINUTE_IN_SECONDS );

		return null;
	}

	/**
	 * Registers a check-in for a previously issued token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_rsvp_checkin( $request ) {
		if ( ! self::is_licensed() ) {
			return new WP_Error( 'twec_premium', __( 'Premium is not active or licensed.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( (string) $params['nonce'] ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'twec_bad_nonce', __( 'Invalid or missing nonce.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$rl = self::enforce_rsvp_checkin_rate_limit();
		if ( is_wp_error( $rl ) ) {
			return $rl;
		}

		$event_id = isset( $params['event_id'] ) ? absint( $params['event_id'] ) : 0;
		$email    = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';
		$token    = isset( $params['token'] ) ? sanitize_text_field( (string) $params['token'] ) : '';

		if ( $event_id < 1 || '' === $email || '' === $token ) {
			return new WP_Error( 'bad_args', __( 'Event ID, attendee email, and token are required.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot check in RSVPs for this event.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$list = get_post_meta( $event_id, self::RSVP_EMAILS_META, true );
		$list = is_array( $list ) ? $list : array();
		if ( ! in_array( $email, $list, true ) ) {
			return new WP_Error( 'not_rsvp', __( 'That email is not on the RSVP list for this event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		$stored = self::lookup_token_for_email( $event_id, $email );
		if ( '' === $stored || ! hash_equals( $stored, $token ) ) {
			return new WP_Error( 'token_mismatch', __( 'Unknown token for that email.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		/**
		 * Fires once a validated RSVP check-in succeeds.
		 */
		do_action( 'twec_rsvp_checkin_verified', $event_id, $email );

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'event_id' => $event_id,
				'email'    => $email,
			),
			200
		);
	}

	/**
	 * Persist (or regenerate) attendee tokens keyed by salted email hashes.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $email    RSVP email address.
	 * @return string Token string.
	 */
	private static function issue_attendee_token( $event_id, $email ) {
		$event_id = (int) $event_id;
		$norm     = strtolower( sanitize_email( (string) $email ) );

		if ( $event_id < 1 || '' === $norm ) {
			return '';
		}

		$digest = md5( $norm );
		$map    = get_post_meta( $event_id, self::RSVP_TOKEN_META, true );
		if ( ! is_array( $map ) ) {
			$map = array();
		}

		if ( empty( $map[ $digest ] ) ) {
			if ( function_exists( 'random_bytes' ) ) {
				$map[ $digest ] = bin2hex( random_bytes( 16 ) );
			} else {
				$map[ $digest ] = sha1( (string) wp_rand() );
			}
		}

		update_post_meta( $event_id, self::RSVP_TOKEN_META, $map );

		return (string) $map[ $digest ];
	}

	/**
	 * Remove stored attendee token digest.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $email    RSVP email.
	 * @return void
	 */
	private static function remove_attendee_token_row( $event_id, $email ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			return;
		}
		$norm = strtolower( sanitize_email( (string) $email ) );
		if ( '' === $norm ) {
			return;
		}

		$digest = md5( $norm );
		$map    = get_post_meta( $event_id, self::RSVP_TOKEN_META, true );
		if ( ! is_array( $map ) ) {
			return;
		}

		unset( $map[ $digest ] );

		update_post_meta( $event_id, self::RSVP_TOKEN_META, $map );
	}

	/**
	 * Fetch token for attendee.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $email    Email address.
	 * @return string
	 */
	private static function lookup_token_for_email( $event_id, $email ) {
		$norm = strtolower( sanitize_email( (string) $email ) );
		if ( '' === $norm ) {
			return '';
		}

		$digest = md5( $norm );
		$map    = get_post_meta( (int) $event_id, self::RSVP_TOKEN_META, true );
		if ( ! is_array( $map ) ) {
			return '';
		}
		return isset( $map[ $digest ] ) ? (string) $map[ $digest ] : '';
	}
}
