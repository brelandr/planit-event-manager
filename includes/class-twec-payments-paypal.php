<?php
/**
 * PayPal Checkout Orders v2: featured / paid event listing (no Composer dependency).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create PayPal orders, verify webhooks, and record payment on events.
 */
class TWEC_Payments_PayPal {

	const META_PAID       = '_twec_paypal_paid';
	const META_PAID_AT    = '_twec_paypal_paid_at';
	const META_CAPTURE_ID = '_twec_paypal_capture_id';

	// #region agent log
	/**
	 * Optional NDJSON debug file. No secrets/PII.
	 *
	 * Default: no file logging. Set path via the `twec_paypal_debug_log_path` filter, for example
	 * `add_filter( 'twec_paypal_debug_log_path', function () { return WP_CONTENT_DIR . '/uploads/twec-paypal-debug.ndjson'; } );`
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $data    Data.
	 * @param string               $hypothesis_id Hypothesis id.
	 * @return void
	 */
	public static function agent_log( $message, $data = array(), $hypothesis_id = 'H' ) {
		/**
		 * Path to a writable NDJSON log file, or empty string to disable.
		 *
		 * @param string $path Absolute filesystem path, or ''.
		 */
		$path = (string) apply_filters( 'twec_paypal_debug_log_path', '' );
		if ( '' === $path ) {
			return;
		}
		$dir = @dirname( $path );
		if ( ! is_string( $dir ) || ! is_dir( $dir ) ) {
			return;
		}
		$line = wp_json_encode(
			array(
				'timestamp'    => (int) round( microtime( true ) * 1000 ),
				'message'      => (string) $message,
				'data'         => is_array( $data ) ? $data : array(),
				'hypothesisId' => (string) $hypothesis_id,
			)
		) . "\n";
		@file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
	}
	// #endregion

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_post_twec_paypal_start', array( __CLASS__, 'admin_post_start' ) );
		add_shortcode( 'twec_paypal_checkout', array( __CLASS__, 'shortcode_checkout' ) );
		add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'filter_allowed_redirect_hosts' ), 10, 2 );
	}

	/**
	 * Allow wp_safe_redirect after PayPal order creation (approval URL is off-site).
	 *
	 * @param string[]     $hosts Trusted hosts for wp_safe_redirect.
	 * @param string|false $location Passed by WP; unused.
	 * @return string[]
	 */
	public static function filter_allowed_redirect_hosts( $hosts, $location = '' ) {
		unset( $location );

		if ( ! is_array( $hosts ) ) {
			$hosts = array();
		}

		$extras = array(
			'www.paypal.com',
			'www.sandbox.paypal.com',
			'paypal.com',
			'sandbox.paypal.com',
		);

		return array_values( array_unique( array_merge( $hosts, $extras ) ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$s = (array) get_option( 'twec_settings', array() );
		return array_merge(
			array(
				'payment_gateway'             => 'none',
				'payment_mode'                => 'test',
				'paypal_test_client_id'       => '',
				'paypal_test_client_secret'   => '',
				'paypal_live_client_id'       => '',
				'paypal_live_client_secret'   => '',
				'paypal_webhook_id'           => '',
				'stripe_feature_price_minor'  => 0,
				'stripe_currency'             => 'usd',
				'stripe_product_name'         => '',
				'paypal_checkout_success_url' => '',
				'paypal_checkout_cancel_url'  => '',
			),
			$s
		);
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return string 'test'|'live'
	 */
	public static function live_mode( array $s ) {
		return ( isset( $s['payment_mode'] ) && 'live' === $s['payment_mode'] ) ? 'live' : 'test';
	}

	/**
	 * @param string $mode test|live.
	 * @return string
	 */
	public static function api_base( $mode ) {
		return 'live' === $mode ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return string Client id.
	 */
	public static function get_client_id( array $s ) {
		$m = self::live_mode( $s );
		return 'live' === $m
			? (string) ( $s['paypal_live_client_id'] ?? '' )
			: (string) ( $s['paypal_test_client_id'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return string Client secret.
	 */
	public static function get_client_secret( array $s ) {
		$m = self::live_mode( $s );
		return 'live' === $m
			? (string) ( $s['paypal_live_client_secret'] ?? '' )
			: (string) ( $s['paypal_test_client_secret'] ?? '' );
	}

	/**
	 * Currencies: whole units (no decimal fraction in PayPal value string).
	 *
	 * @var string[]
	 */
	private static function zero_decimal_currencies() {
		return array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
	}

	/**
	 * @param int    $minor    Amount in minor units (same as Stripe field).
	 * @param string $currency 3-letter code.
	 * @return string Value for PayPal API.
	 */
	public static function format_amount_value( $minor, $currency ) {
		$c     = strtolower( (string) $currency );
		$minor = (int) $minor;
		if ( in_array( $c, self::zero_decimal_currencies(), true ) ) {
			return (string) $minor;
		}
		return number_format( $minor / 100, 2, '.', '' );
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return string|WP_Error
	 */
	public static function get_access_token( array $s ) {
		$cid = self::get_client_id( $s );
		$sec = self::get_client_secret( $s );
		if ( '' === $cid || '' === $sec ) {
			return new WP_Error( 'twec_paypal_creds', __( 'PayPal client id/secret is not configured.', 'planit-event-manager' ) );
		}
		$mode = self::live_mode( $s );
		$base = self::api_base( $mode );
		$key  = 'twec_paypal_at_' . md5( $mode . $cid );
		$tok  = get_transient( $key );
		if ( is_string( $tok ) && '' !== $tok ) {
			self::agent_log( 'paypal_token_cache_hit', array( 'mode' => $mode ), 'H1' );
			return $tok;
		}
		$auth = base64_encode( $cid . ':' . $sec );
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . $auth,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => 'grant_type=client_credentials',
		);
		$resp = wp_safe_remote_post( $base . '/v1/oauth2/token', $args );
		self::agent_log(
			'paypal_token_response',
			array(
				'http' => is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp ),
				'ok'   => ! is_wp_error( $resp ),
			),
			'H1'
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = (string) wp_remote_retrieve_body( $resp );
		if ( 200 !== $code ) {
			return new WP_Error(
				'twec_paypal_token_http',
				__( 'PayPal OAuth token request failed.', 'planit-event-manager' ),
				array( 'status' => 502 )
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return new WP_Error( 'twec_paypal_token', __( 'Could not get PayPal access token.', 'planit-event-manager' ) );
		}
		$at  = (string) $data['access_token'];
		$ex  = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 30000;
		$ttl = max( 60, $ex - 120 );
		set_transient( $key, $at, $ttl );
		return $at;
	}

	/**
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'planit/v1',
			'/paypal/create-checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_create_checkout' ),
				'permission_callback' => array( __CLASS__, 'rest_can_create_checkout' ),
				'args'                => array(
					'event_id' => array(
						'description'       => __( 'Event post ID.', 'planit-event-manager' ),
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			'planit/v1',
			'/paypal/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_webhook' ),
				'permission_callback' => array( __CLASS__, 'rest_permission_paypal_webhook' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * Webhook endpoint must be public; verification uses PayPal signing API (see rest_webhook).
	 *
	 * @return true
	 */
	public static function rest_permission_paypal_webhook() {
		return true;
	}

	/**
	 * @return bool
	 */
	public static function rest_can_create_checkout() {
		return is_user_logged_in() && ( current_user_can( 'edit_posts' ) || is_super_admin() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_create_checkout( $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_cookie_invalid', __( 'Invalid or missing REST nonce. Refresh the page and try again.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		if ( 'paypal' !== self::get_settings()['payment_gateway'] ) {
			return new WP_Error( 'twec_paypal_gateway', __( 'PayPal is not the selected payment gateway in PlanIt settings.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$event_id = (int) $request->get_param( 'event_id' );
		if ( $event_id <= 0 ) {
			$json = $request->get_json_params();
			if ( is_array( $json ) && ! empty( $json['event_id'] ) ) {
				$event_id = (int) $json['event_id'];
			}
		}
		if ( $event_id <= 0 || 'twec_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'twec_paypal_event', __( 'Invalid or missing event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'twec_paypal_cap', __( 'You cannot start checkout for this event.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		$result = self::create_order( $event_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param int $event_id Event ID.
	 * @param int $user_id  User.
	 * @return array|WP_Error
	 */
	public static function create_order( $event_id, $user_id = 0 ) {
		$s   = self::get_settings();
		$tok = self::get_access_token( $s );
		if ( is_wp_error( $tok ) ) {
			return $tok;
		}

		$currency_lower = ! empty( $s['stripe_currency'] ) ? strtolower( preg_replace( '/[^a-z]/', '', (string) $s['stripe_currency'] ) ) : 'usd';
		if ( 3 !== strlen( $currency_lower ) ) {
			$currency_lower = 'usd';
		}

		$resolved = class_exists( 'TWEC_Payments_Stripe' )
			? TWEC_Payments_Stripe::resolve_checkout_amount_minor( $event_id, $s, $currency_lower )
			: new WP_Error( 'twec_paypal_dep', __( 'PlanIt payments module is not loaded.', 'planit-event-manager' ), array( 'status' => 500 ) );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$minor = (int) $resolved;

		$currency  = strtoupper( $currency_lower );
		$default   = __( 'Featured event listing', 'planit-event-manager' );
		$name      = ! empty( $s['stripe_product_name'] ) ? (string) $s['stripe_product_name'] : $default;
		$event_id  = (int) $event_id;
		$event_url = get_permalink( $event_id );
		if ( ! is_string( $event_url ) || '' === $event_url ) {
			$event_url = home_url( '/' );
		}
		$return = ! empty( $s['paypal_checkout_success_url'] ) ? (string) $s['paypal_checkout_success_url'] : add_query_arg(
			array(
				'twec_paypal' => 'success',
				'event_id'    => (string) $event_id,
			),
			$event_url
		);
		$cancel = ! empty( $s['paypal_checkout_cancel_url'] ) ? (string) $s['paypal_checkout_cancel_url'] : $event_url;
		$mode   = self::live_mode( $s );
		$base   = self::api_base( $mode );
		$body   = wp_json_encode(
			array(
				'intent'              => 'CAPTURE',
				'purchase_units'      => array(
					array(
						'description' => $name,
						'custom_id'   => (string) $event_id,
						'invoice_id'  => 'twec-' . (string) $event_id,
						'amount'      => array(
							'currency_code' => $currency,
							'value'         => self::format_amount_value( $minor, $currency ),
						),
					),
				),
				'application_context' => array(
					'return_url' => $return,
					'cancel_url' => $cancel,
				),
			)
		);
		if ( ! is_string( $body ) ) {
			$body = '{}';
		}
		$args    = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization'     => 'Bearer ' . $tok,
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' => 'twec-' . (string) $event_id . '-' . (string) wp_generate_password( 8, false, false ),
				'Prefer'            => 'return=representation',
			),
			'body'    => $body,
		);
		$resp = wp_safe_remote_post( $base . '/v2/checkout/orders', $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$hcode = (int) wp_remote_retrieve_response_code( $resp );
		$raw   = (string) wp_remote_retrieve_body( $resp );
		$data  = json_decode( $raw, true );
		$data  = is_array( $data ) ? $data : array();
		$approve = '';
		$oid     = '';
		if ( is_array( $data ) ) {
			$oid = isset( $data['id'] ) ? (string) $data['id'] : '';
			if ( ! empty( $data['links'] ) && is_array( $data['links'] ) ) {
				foreach ( $data['links'] as $l ) {
					if ( is_array( $l ) && isset( $l['rel'] ) && 'approve' === (string) $l['rel'] && ! empty( $l['href'] ) ) {
						$approve = (string) $l['href'];
						break;
					}
				}
			}
		}
		self::agent_log(
			'paypal_create_order',
			array(
				'http'        => $hcode,
				'has_approve' => ( '' !== $approve ),
				'has_id'      => ( '' !== $oid ),
			),
			'H2'
		);
		if ( $hcode < 200 || $hcode >= 300 ) {
			$msg = isset( $data['message'] ) ? sanitize_text_field( (string) $data['message'] ) : __( 'PayPal order could not be created.', 'planit-event-manager' );
			return new WP_Error( 'twec_paypal_order', $msg, array( 'status' => 400 ) );
		}
		if ( '' === $approve ) {
			return new WP_Error( 'twec_paypal_no_approve', __( 'No PayPal approval URL in response.', 'planit-event-manager' ), array( 'status' => 502 ) );
		}
		return array(
			'url'      => $approve,
			'order_id' => $oid,
		);
	}

	/**
	 * @return void
	 */
	public static function admin_post_start() {
		if ( ! isset( $_POST['event_id'] ) ) {
			wp_die( esc_html__( 'Missing event.', 'planit-event-manager' ), 400 );
		}
		$event_id = absint( wp_unslash( $_POST['event_id'] ) );
		if ( ! $event_id || ! current_user_can( 'edit_post', $event_id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'planit-event-manager' ), 403 );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'twec_paypal_start_' . $event_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'planit-event-manager' ), 403 );
		}
		if ( 'paypal' !== self::get_settings()['payment_gateway'] ) {
			wp_die( esc_html__( 'PayPal is not the selected payment gateway in PlanIt settings.', 'planit-event-manager' ), 400 );
		}
		$result = self::create_order( $event_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 500 );
		}
		if ( ! empty( $result['url'] ) ) {
			wp_safe_redirect( $result['url'] );
			exit;
		}
		wp_die( esc_html__( 'No redirect URL from PayPal.', 'planit-event-manager' ), 500 );
	}

	/**
	 * @param string[] $atts Atts.
	 * @return string
	 */
	public static function shortcode_checkout( $atts ) {
		if ( 'paypal' !== self::get_settings()['payment_gateway'] ) {
			return '';
		}
		$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'twec_paypal_checkout' );
		$eid  = (int) $atts['event_id'];
		if ( $eid <= 0 && is_singular( 'twec_event' ) ) {
			$eid = (int) get_queried_object_id();
		}
		if ( $eid <= 0 || 'twec_event' !== get_post_type( $eid ) ) {
			return '';
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $eid ) ) {
			return '';
		}
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$label  = esc_html__( 'Pay with PayPal (featured listing)', 'planit-event-manager' );
		$nonce  = wp_create_nonce( 'twec_paypal_start_' . $eid );
		ob_start();
		?>
		<form class="twec-paypal-checkout-shortcode" method="post" action="<?php echo esc_url( $action ); ?>">
			<input type="hidden" name="action" value="twec_paypal_start" />
			<input type="hidden" name="event_id" value="<?php echo (int) $eid; ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
			<p><button type="submit" class="wp-block-button__link button"><?php echo esc_html( $label ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_webhook( $request ) {
		$s = self::get_settings();
		if ( 'paypal' !== $s['payment_gateway'] ) {
			return new WP_REST_Response(
				array(
					'ok'      => true,
					'ignored' => 'gateway',
				),
				200
			);
		}
		$wid = ! empty( $s['paypal_webhook_id'] ) ? (string) $s['paypal_webhook_id'] : '';
		if ( '' === $wid ) {
			return new WP_Error( 'twec_paypal_webhook', __( 'PayPal webhook id not configured.', 'planit-event-manager' ), array( 'status' => 500 ) );
		}
		$raw = $request->get_body();
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new WP_Error( 'twec_paypal_empty', __( 'Empty webhook body.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$event = json_decode( $raw, true );
		if ( ! is_array( $event ) || empty( $event['event_type'] ) ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}
		$etype = (string) $event['event_type'];
		self::agent_log( 'paypal_webhook_in', array( 'type' => $etype ), 'H3' );
		$ok = self::verify_webhook_signature( $s, $request, $raw, $event, $wid );
		self::agent_log( 'paypal_webhook_verify', array( 'ok' => (bool) $ok ), 'H3' );
		if ( ! $ok ) {
			return new WP_Error( 'twec_paypal_sig', __( 'Invalid PayPal webhook signature.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( 'PAYMENT.CAPTURE.COMPLETED' === $etype ) {
			$res = isset( $event['resource'] ) && is_array( $event['resource'] ) ? $event['resource'] : array();
			self::handle_capture_completed( $res );
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * @param array<string, mixed> $s     Settings.
	 * @param WP_REST_Request      $req  Request.
	 * @param string               $raw  Raw body.
	 * @param array<string, mixed> $evt  Decoded event.
	 * @param string               $wid  Webhook id.
	 * @return bool
	 */
	public static function verify_webhook_signature( $s, $req, $raw, $evt, $wid ) {
		$tok = self::get_access_token( $s );
		if ( is_wp_error( $tok ) || ! is_string( $tok ) || '' === $tok ) {
			return false;
		}
		$mode = self::live_mode( $s );
		$base = self::api_base( $mode );
		$body = wp_json_encode(
			array(
				'transmission_id'   => (string) self::paypal_header( $req, 'paypal_transmission_id' ),
				'transmission_time' => (string) self::paypal_header( $req, 'paypal_transmission_time' ),
				'cert_url'          => (string) self::paypal_header( $req, 'paypal_cert_url' ),
				'auth_algo'         => (string) self::paypal_header( $req, 'paypal_auth_algo' ),
				'transmission_sig'  => (string) self::paypal_header( $req, 'paypal_transmission_sig' ),
				'webhook_id'        => (string) $wid,
				'webhook_event'     => $evt,
			)
		);
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $tok,
				'Content-Type'  => 'application/json',
			),
			'body'    => is_string( $body ) ? $body : '{}',
		);
		$resp = wp_safe_remote_post( $base . '/v1/notifications/verify-webhook-signature', $args );
		if ( is_wp_error( $resp ) ) {
			self::agent_log( 'paypal_verify_wp_error', array( 'msg' => $resp->get_error_message() ), 'H3' );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$out  = (string) wp_remote_retrieve_body( $resp );
		if ( $code < 200 || $code >= 300 ) {
			self::agent_log( 'paypal_verify_bad_http', array( 'http' => $code ), 'H3' );
			return false;
		}

		$j   = json_decode( $out, true );
		$ver = is_array( $j ) && isset( $j['verification_status'] ) && 'SUCCESS' === (string) $j['verification_status'];
		if ( ! $ver ) {
			self::agent_log(
				'paypal_verify_fail',
				array(
					'http'   => $code,
					'status' => is_array( $j ) && isset( $j['verification_status'] ) ? (string) $j['verification_status'] : 'none',
				),
				'H3'
			);
		}
		return $ver;
	}

	/**
	 * Read PayPal webhook headers (case-insensitive).
	 *
	 * @param WP_REST_Request $req  Request.
	 * @param string          $name Lowercase header without HTTP_ (e.g. paypal_transmission_id).
	 * @return string
	 */
	public static function paypal_header( $req, $name ) {
		$hyp = str_replace( '_', '-', (string) $name );
		foreach ( array( $hyp, $name ) as $try ) {
			$v = (string) $req->get_header( $try );
			if ( '' !== $v ) {
				return $v;
			}
		}
		$all = $req->get_headers();
		if ( is_array( $all ) ) {
			foreach ( $all as $k => $vals ) {
				if ( 0 === strcasecmp( (string) $k, $hyp ) && ! empty( $vals[0] ) ) {
					return (string) $vals[0];
				}
			}
		}
		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $hyp ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- PayPal signature headers, read only.
		return isset( $_SERVER[ $key ] ) ? (string) wp_unslash( $_SERVER[ $key ] ) : '';
	}

	/**
	 * @param array<string, mixed> $resource Resource from webhook.
	 * @return void
	 */
	public static function handle_capture_completed( array $resource ) {
		$capture_id = isset( $resource['id'] ) ? (string) $resource['id'] : '';
		$event_id   = 0;
		if ( ! empty( $resource['custom_id'] ) ) {
			$event_id = (int) $resource['custom_id'];
		}
		if ( $event_id <= 0 && ! empty( $resource['invoice_id'] ) && preg_match( '/^twec-(\d+)$/', (string) $resource['invoice_id'], $m ) ) {
			$event_id = (int) $m[1];
		}
		self::agent_log(
			'paypal_handle_capture',
			array(
				'event_id'    => $event_id,
				'has_capture' => ( '' !== $capture_id ),
			),
			'H4'
		);
		if ( $event_id <= 0 || 'twec_event' !== get_post_type( $event_id ) ) {
			return;
		}

		if ( class_exists( 'TWEC_Payment_Log', false ) ) {
			TWEC_Payment_Log::insert_from_paypal_capture( $event_id, $resource );
		}

		$prev = (string) get_post_meta( $event_id, self::META_CAPTURE_ID, true );
		if ( $capture_id && $prev === $capture_id ) {
			self::agent_log( 'paypal_idempotent_skip', array( 'event_id' => $event_id ), 'H5' );
			return;
		}

		update_post_meta( $event_id, self::META_PAID, '1' );
		update_post_meta( $event_id, self::META_PAID_AT, current_time( 'mysql' ) );
		if ( $capture_id ) {
			update_post_meta( $event_id, self::META_CAPTURE_ID, $capture_id );
		}
		self::agent_log( 'paypal_meta_updated', array( 'event_id' => $event_id ), 'H5' );

		self::maybe_send_payment_receipt( $event_id, $resource );

		do_action( 'twec_paypal_capture_paid', $event_id, $resource );
	}

	/**
	 * Optional receipt mail for PayPal when payer email is present.
	 *
	 * @param int                 $event_id Event ID.
	 * @param array<string,mixed> $resource Capture resource array.
	 * @return void
	 */
	public static function maybe_send_payment_receipt( $event_id, array $resource ) {
		if ( ! function_exists( 'wp_mail' ) || ! class_exists( 'TWEC_Email_Templates', false ) ) {
			return;
		}

		$m = TWEC_Email_Templates::merged_email_settings( (array) get_option( 'twec_settings', array() ) );
		if ( 'yes' !== (string) ( $m['payment_receipt_enabled'] ?? 'no' ) ) {
			return;
		}
		if ( true !== apply_filters( 'twec_send_payment_receipt_paypal', true, (int) $event_id, $resource ) ) {
			return;
		}

		$email = '';
		if ( ! empty( $resource['payer']['email_address'] ) ) {
			$email = sanitize_email( (string) $resource['payer']['email_address'] );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}

		$name = '';
		if ( ! empty( $resource['payer']['name']['given_name'] ) || ! empty( $resource['payer']['name']['surname'] ) ) {
			$gn   = isset( $resource['payer']['name']['given_name'] ) ? (string) $resource['payer']['name']['given_name'] : '';
			$sn   = isset( $resource['payer']['name']['surname'] ) ? (string) $resource['payer']['name']['surname'] : '';
			$name = sanitize_text_field( trim( $gn . ' ' . $sn ) );
		}

		$currency = isset( $resource['amount']['currency_code'] ) ? strtolower( (string) $resource['amount']['currency_code'] ) : 'usd';
		$minor    = 0;
		if ( isset( $resource['amount']['value'] ) && is_numeric( $resource['amount']['value'] ) ) {
			$major = (float) $resource['amount']['value'];
			if ( in_array( $currency, self::zero_decimal_currencies(), true ) ) {
				$minor = (int) round( $major );
			} else {
				$minor = (int) round( $major * 100 );
			}
		}

		$capture_id = isset( $resource['id'] ) ? (string) $resource['id'] : '';

		$title_wp = html_entity_decode( wp_strip_all_tags( get_the_title( (int) $event_id ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		if ( '' === $title_wp ) {
			$title_wp = '#' . (string) (int) $event_id;
		}

		$ev_url = get_permalink( (int) $event_id );
		if ( ! is_string( $ev_url ) || '' === $ev_url ) {
			$ev_url = home_url( '/' );
		}

		$starts = '';
		if ( class_exists( 'TWEC_Reminders', false ) ) {
			$start_ts = (int) TWEC_Reminders::get_event_start_timestamp( (int) $event_id );
			$tz       = TWEC_Reminders::get_event_timezone( (int) $event_id );
			$date_fmt = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
			if ( function_exists( 'wp_date' ) ) {
				$starts = wp_date( $date_fmt, $start_ts > 0 ? $start_ts : time(), $tz );
			} else {
				$starts = date_i18n( $date_fmt, $start_ts > 0 ? $start_ts : time(), true );
			}
		}

		if ( class_exists( 'TWEC_Payments_Stripe', false ) ) {
			$amt_display = TWEC_Payments_Stripe::format_minor_for_display( $minor, $currency );
		} else {
			$amt_display = '' . number_format( $minor / 100, 2 ) . ' ' . strtoupper( $currency );
		}

		$tokens = array(
			'event_title'       => $title_wp,
			'event_url'         => esc_url_raw( $ev_url ),
			'event_starts'      => $starts,
			'amount_display'    => $amt_display,
			'currency'          => strtoupper( $currency ),
			'buyer_email'       => $email,
			'buyer_name'        => $name,
			'site_name'         => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'paypal_capture_id' => $capture_id,
			'stripe_session_id' => '',
			'receipt_notes'     => '',
		);

		$subj_tpl = (string) $m['payment_receipt_subject'];
		if ( '' === trim( wp_strip_all_tags( $subj_tpl ) ) ) {
			$subj_tpl = __( 'Receipt: {event_title}', 'planit-event-manager' );
		}
		$subject = TWEC_Email_Templates::replace_tokens( $subj_tpl, $tokens );

		$body_tpl = (string) $m['payment_receipt_body_html'];
		if ( '' === trim( wp_strip_all_tags( $body_tpl ) ) ) {
			$body_tpl =
				'<p>' . __( 'Hi {buyer_name},', 'planit-event-manager' ) . '</p>' .
				'<p>' . __( 'Thank you for your payment.', 'planit-event-manager' ) . '</p>' .
				'<p><strong>' . __( 'Event:', 'planit-event-manager' ) . '</strong> {event_title}</p>' .
				'<p><strong>' . __( 'When:', 'planit-event-manager' ) . '</strong> {event_starts}</p>' .
				'<p><strong>' . __( 'Amount:', 'planit-event-manager' ) . '</strong> {amount_display}</p>' .
				'<p><a href="{event_url}">' . __( 'Event page', 'planit-event-manager' ) . '</a></p>' .
				'<p>— {site_name}</p>';
		}

		$html = TWEC_Email_Templates::replace_tokens( $body_tpl, $tokens );
		$html = wp_kses_post( $html );

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$mail_args = array(
			'to'      => $email,
			'subject' => $subject,
			'message' => $html,
			'headers' => $headers,
		);
		if ( '' !== trim( (string) ( $m['payment_receipt_bcc_admin'] ?? '' ) ) ) {
			$bcc_target = sanitize_email( (string) $m['payment_receipt_bcc_admin'] );
			if ( is_email( $bcc_target ) ) {
				$headers[]            = 'Bcc: ' . $bcc_target;
				$mail_args['headers'] = $headers;
			}
		}

		$mail_args = apply_filters( 'twec_payment_receipt_mail_args_paypal', $mail_args, $event_id, $resource );
		wp_mail( $mail_args['to'], $mail_args['subject'], $mail_args['message'], $mail_args['headers'] );
	}
}
