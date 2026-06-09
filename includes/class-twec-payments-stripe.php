<?php
/**
 * Stripe Checkout: featured / paid event listing (v1) — no Composer dependency.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create Checkout Sessions, verify webhooks, and record payment on events.
 */
class TWEC_Payments_Stripe {

	const META_PAID       = '_twec_stripe_paid';
	const META_PAID_AT    = '_twec_stripe_paid_at';
	const META_SESSION_ID = '_twec_stripe_session_id';
	const API_BASE        = 'https://api.stripe.com/v1/';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_post_twec_stripe_start', array( __CLASS__, 'admin_post_start' ) );
		add_shortcode( 'twec_stripe_checkout', array( __CLASS__, 'shortcode_checkout' ) );
		// wp_safe_redirect() only trusts same-site hosts unless we allow Stripe Checkout.
		add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'filter_allowed_redirect_hosts' ), 10, 2 );
	}

	/**
	 * Allow redirecting users to Stripe Checkout after admin-post handlers.
	 *
	 * @param string[]     $hosts    Hosts WordPress trusts for wp_safe_redirect.
	 * @param string|false $location Unused; included for parity with WP’s filter signature.
	 * @return string[]
	 */
	public static function filter_allowed_redirect_hosts( $hosts, $location = '' ) {
		unset( $location ); // Passed by WordPress starting in 5.1+.

		if ( ! is_array( $hosts ) ) {
			$hosts = array();
		}

		$extras = array( 'checkout.stripe.com' );

		return array_values( array_unique( array_merge( $hosts, $extras ) ) );
	}

	/**
	 * Merged settings with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$s = (array) get_option( 'twec_settings', array() );
		return array_merge(
			array(
				'payment_gateway'             => 'none',
				'payment_mode'                => 'test',
				'stripe_test_publishable_key' => '',
				'stripe_test_secret_key'      => '',
				'stripe_live_publishable_key' => '',
				'stripe_live_secret_key'      => '',
				'stripe_webhook_secret'       => '',
				'stripe_feature_price_minor'  => 0,
				'stripe_currency'             => 'usd',
				'stripe_product_name'         => '',
				'stripe_checkout_success_url' => '',
				'stripe_checkout_cancel_url'  => '',
			),
			$s
		);
	}

	/**
	 * Secret key for current payment_mode (test | live).
	 *
	 * @param array<string, mixed> $s Settings (from get_settings()).
	 * @return string
	 */
	public static function get_secret_key( array $s ) {
		$mode = ( isset( $s['payment_mode'] ) && 'live' === $s['payment_mode'] ) ? 'live' : 'test';
		if ( 'live' === $mode ) {
			return isset( $s['stripe_live_secret_key'] ) ? (string) $s['stripe_live_secret_key'] : '';
		}
		return isset( $s['stripe_test_secret_key'] ) ? (string) $s['stripe_test_secret_key'] : '';
	}

	/**
	 * Whether Stripe expects integer amounts with no fractional sub-units (e.g. JPY).
	 *
	 * @param string $currency Lowercase ISO 4217.
	 * @return bool
	 */
	public static function stripe_currency_is_zero_decimal( $currency ) {
		$c  = strtolower( (string) $currency );
		$zd = array( 'bif', 'clp', 'djf', 'gnf', 'isk', 'jpy', 'kmf', 'krw', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
		return in_array( $c, $zd, true );
	}

	/**
	 * Approximate Stripe minimum charge in smallest currency unit (Stripe payment minimum docs).
	 * Unknown currencies fall back to 50 to match USD-style safety.
	 *
	 * @param string $currency Lowercase ISO 4217.
	 * @return int
	 */
	public static function stripe_minimum_charge_minor( $currency ) {
		$c = strtolower( (string) $currency );
		// Approximate Stripe minimum charge (smallest units). Unknown codes default to USD-like 50.
		$map = array(
			'aed' => 200,
			'aud' => 50,
			'brl' => 50,
			'cad' => 50,
			'chf' => 50,
			'czk' => 1500,
			'dkk' => 250,
			'eur' => 50,
			'gbp' => 30,
			'hkd' => 400,
			'huf' => 17500,
			'idr' => 975000,
			'inr' => 50,
			'jpy' => 50,
			'mxn' => 1000,
			'myr' => 200,
			'nok' => 300,
			'nzd' => 50,
			'php' => 2500,
			'pln' => 200,
			'rub' => 75,
			'sek' => 300,
			'sgd' => 50,
			'thb' => 1750,
			'twd' => 1000,
			'usd' => 50,
		);

		return isset( $map[ $c ] ) ? (int) $map[ $c ] : 50;
	}

	/**
	 * Parse `_twec_event_cost` human text into Stripe minor units (cents when not zero-decimal).
	 *
	 * @param string $raw      Raw meta text (e.g. "$25", "25.00", "Free").
	 * @param string $currency Lowercase ISO 4217 currency for the checkout.
	 * @return int Minor units or 0 when not parseable.
	 */
	public static function event_cost_string_to_minor( $raw, $currency ) {
		$currency = strtolower( (string) $currency );
		if ( 3 !== strlen( $currency ) ) {
			$currency = 'usd';
		}
		if ( self::stripe_currency_is_zero_decimal( $currency ) ) {
			$decimals = 0;
		} else {
			$decimals = 2;
		}

		$raw = is_string( $raw ) ? trim( wp_strip_all_tags( $raw ) ) : '';
		if ( '' === $raw ) {
			return 0;
		}

		if ( preg_match( '/^(free|-|n\/a|unknown)$/iu', $raw ) ) {
			return 0;
		}

		$stripped = preg_replace(
			'/[^\d.,\-]/',
			'',
			str_replace(
				array( "\xE2\x80\xAF", chr( 0xa0 ) ),
				'',
				$raw
			)
		);

		if ( '' === $stripped || '-' === substr( trim( $stripped ), 0, 1 ) ) {
			return 0;
		}

		if ( false !== strpos( $stripped, ',' ) && false !== strpos( $stripped, '.' ) ) {

			$comma_pos = (int) strrpos( $stripped, ',' );
			$dot_pos   = (int) strrpos( $stripped, '.' );
			if ( $comma_pos > $dot_pos ) {

				$stripped = str_replace( '.', '', $stripped );
				$stripped = str_replace( ',', '.', $stripped );
			} else {

				$stripped = str_replace( ',', '', $stripped );
			}
		} elseif ( false !== strpos( $stripped, ',' ) ) {

			$stripped = str_replace( ',', '.', $stripped );
		}

		$major = filter_var(
			preg_replace( '/[^0-9.\-]/', '', $stripped ),
			FILTER_VALIDATE_FLOAT
		);
		if ( false === $major || ! is_finite( $major ) || $major <= 0 ) {
			return 0;
		}

		if ( self::stripe_currency_is_zero_decimal( $currency ) ) {
			return (int) round( $major );
		}

		return (int) max( 0, round( (float) $major * pow( 10, $decimals ) ) );
	}

	/**
	 * Pick unit_amount for Stripe (and counterpart for PayPal) from settings + optional Event Cost.
	 *
	 * Rules: if the configured "feature price" (minor units) meets Stripe’s minimum charge, use it.
	 * Otherwise, if `_twec_event_cost` parses to at least that minimum (e.g. $25 → 2500¢), use that.
	 * This avoids Stripe errors when "25" was entered meaning $25 rather than 25¢.
	 *
	 * @param int                 $event_id Event post ID.
	 * @param array<string,mixed> $s        merged twec_settings + defaults.
	 * @param string|null         $currency Normalized lowercase 3-letter code; null = read from $s.
	 * @return int|WP_Error
	 */
	public static function resolve_checkout_amount_minor( $event_id, array $s, $currency = null ) {
		$currency = is_string( $currency ) ? strtolower( preg_replace( '/[^a-z]/', '', $currency ) ) : '';
		if ( 3 !== strlen( $currency ) ) {
			$currency = ! empty( $s['stripe_currency'] ) ? strtolower( preg_replace( '/[^a-z]/', '', (string) $s['stripe_currency'] ) ) : 'usd';
		}
		if ( 3 !== strlen( $currency ) ) {
			$currency = 'usd';
		}

		$feature_minor = isset( $s['stripe_feature_price_minor'] ) ? (int) $s['stripe_feature_price_minor'] : 0;
		$cost_raw      = get_post_meta( (int) $event_id, '_twec_event_cost', true );
		$event_minor   = self::event_cost_string_to_minor( is_scalar( $cost_raw ) ? (string) $cost_raw : '', $currency );
		$min           = (int) self::stripe_minimum_charge_minor( $currency );

		if ( $feature_minor >= $min ) {
			return $feature_minor;
		}

		if ( $event_minor >= $min ) {
			return $event_minor;
		}

		if ( $feature_minor < 1 && $event_minor < 1 ) {
			return new WP_Error(
				'twec_checkout_amt_missing',
				__( 'Set a Payment feature price in PlanIt → Settings → Payments (amounts are in smallest currency units, e.g. 2500 for $25.00 USD). You can also set Event Cost on the event.', 'planit-event-manager' ),
				array( 'status' => 400 )
			);
		}

		return new WP_Error(
			'twec_checkout_amt_below_stripe_minimum',
			sprintf(
				/* translators: 1=feature price (minor units), 2=currency code, 3=Stripe minimum (minor units), 4=parsed event cost (minor units). */
				__( 'The checkout amount is below Stripe’s minimum for %2$s (%3$d smallest units). Feature price (settings) is %1$d; Event Cost parses to %4$d smallest units. For USD, values are in cents (e.g. $25.00 → 2500, not 25).', 'planit-event-manager' ),
				(int) max( $feature_minor, 0 ),
				strtoupper( $currency ),
				$min,
				(int) max( $event_minor, 0 )
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'planit/v1',
			'/stripe/create-checkout',
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
			'/stripe/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_webhook' ),
				'permission_callback' => array( __CLASS__, 'rest_permission_stripe_webhook' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * Webhook must accept unsigned POST bodies; authenticity is enforced via Stripe-Signature + secret.
	 *
	 * @return true
	 */
	public static function rest_permission_stripe_webhook() {
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
		$nonce = sanitize_text_field( (string) $request->get_header( 'X-WP-Nonce' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_cookie_invalid', __( 'Invalid or missing REST nonce. Refresh the page and try again.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		if ( 'stripe' !== self::get_settings()['payment_gateway'] ) {
			return new WP_Error( 'twec_stripe_gateway', __( 'Stripe is not the selected payment gateway in PlanIt settings.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$event_id = (int) $request->get_param( 'event_id' );
		if ( $event_id <= 0 ) {
			$json = $request->get_json_params();
			if ( is_array( $json ) && ! empty( $json['event_id'] ) ) {
				$event_id = (int) $json['event_id'];
			}
		}
		if ( $event_id <= 0 || 'twec_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'twec_stripe_event', __( 'Invalid or missing event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'twec_stripe_cap', __( 'You cannot start checkout for this event.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}
		$result = self::create_checkout_session( $event_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param int $event_id Event post ID.
	 * @param int $user_id  User running checkout.
	 * @return array|WP_Error
	 */
	public static function create_checkout_session( $event_id, $user_id = 0 ) {
		$s  = self::get_settings();
		$sk = self::get_secret_key( $s );
		if ( '' === $sk ) {
			return new WP_Error( 'twec_stripe_key', __( 'Stripe secret key is not configured in PlanIt settings.', 'planit-event-manager' ), array( 'status' => 500 ) );
		}

		$currency = ! empty( $s['stripe_currency'] ) ? strtolower( preg_replace( '/[^a-z]/', '', (string) $s['stripe_currency'] ) ) : 'usd';
		if ( 3 !== strlen( $currency ) ) {
			$currency = 'usd';
		}

		$amount_res = self::resolve_checkout_amount_minor( $event_id, $s, $currency );
		if ( is_wp_error( $amount_res ) ) {
			return $amount_res;
		}
		$amount       = (int) $amount_res;
		$default_name = __( 'Featured event listing', 'planit-event-manager' );
		$product_name = ! empty( $s['stripe_product_name'] ) ? (string) $s['stripe_product_name'] : $default_name;
		$event_id     = (int) $event_id;
		$event_url    = get_permalink( $event_id );
		if ( ! is_string( $event_url ) || '' === $event_url ) {
			$event_url = home_url( '/' );
		}
		$success = ! empty( $s['stripe_checkout_success_url'] ) ? (string) $s['stripe_checkout_success_url'] : add_query_arg(
			array(
				'twec_stripe' => 'success',
				'event_id'    => (string) $event_id,
				'session_id'  => '{CHECKOUT_SESSION_ID}',
			),
			$event_url
		);
		$cancel  = ! empty( $s['stripe_checkout_cancel_url'] ) ? (string) $s['stripe_checkout_cancel_url'] : $event_url;
		$params  = array(
			'mode'                => 'payment',
			'success_url'         => $success,
			'cancel_url'          => $cancel,
			'client_reference_id' => (string) $event_id,
			'metadata'            => array(
				'twec_event_id' => (string) $event_id,
				'twec_user_id'  => (string) (int) $user_id,
			),
			'line_items'          => array(
				array(
					'price_data' => array(
						'currency'     => $currency,
						'unit_amount'  => $amount,
						'product_data' => array( 'name' => $product_name ),
					),
					'quantity'   => 1,
				),
			),
		);
		$body    = self::form_encode( $params );
		$resp = self::api_request( 'POST', 'checkout/sessions', $sk, $body );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $http_code < 200 || $http_code >= 300 ) {
			return new WP_Error(
				'twec_stripe_http',
				__( 'Stripe checkout session could not be created.', 'planit-event-manager' ),
				array( 'status' => 502 )
			);
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twec_stripe_parse', __( 'Invalid response from Stripe.', 'planit-event-manager' ), array( 'status' => 502 ) );
		}
		if ( ! empty( $data['error'] ) && is_array( $data['error'] ) ) {
			$msg = isset( $data['error']['message'] ) ? sanitize_text_field( (string) $data['error']['message'] ) : __( 'Stripe error', 'planit-event-manager' );
			return new WP_Error( 'twec_stripe_api', $msg, array( 'status' => 400 ) );
		}
		$url = isset( $data['url'] ) ? (string) $data['url'] : '';
		$cid = isset( $data['id'] ) ? (string) $data['id'] : '';
		if ( '' === $url ) {
			return new WP_Error( 'twec_stripe_no_url', __( 'No Checkout URL from Stripe.', 'planit-event-manager' ), array( 'status' => 502 ) );
		}
		return array(
			'url'        => $url,
			'session_id' => $cid,
		);
	}

	/**
	 * Form-encode nested arrays for application/x-www-form-urlencoded (Stripe API).
	 *
	 * @param array<string, mixed> $params Params.
	 * @return string
	 */
	public static function form_encode( array $params ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Stripe expects x-www-form-urlencoded; http_build_query matches Stripe examples.
		return http_build_query( $params, '', '&' );
	}

	/**
	 * Executes a Stripe API request via the WordPress HTTP API (`wp_safe_remote_post`).
	 *
	 * Network failures return WP_Error. HTTP 5xx responses return WP_Error (service unavailable).
	 * Stripe often returns HTTP 400 with a JSON `{ error: { … } }` body for validation failures;
	 * those responses are passed through so create_checkout_session can surface the message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method POST-only for current helpers.
	 * @param string $path   Path relative to `API_BASE` (example: checkout/sessions).
	 * @param string $secret Secret API key.
	 * @param string $body   Body string (typically form-encoded).
	 *
	 * @return array|\WP_Error Raw HTTP response array for wp_remote_* functions; WP_Error on transport or 5xx failure.
	 */
	public static function api_request( $method, $path, $secret, $body ) {
		$url      = self::API_BASE . ltrim( (string) $path, '/' );
		$args     = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . (string) $secret,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => $body,
		);
		$response = wp_safe_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		// Network/edge: treat 5xx and missing/invalid codes as server-side failure; 4xx may contain JSON error details.
		if ( $code < 200 || $code >= 500 ) {
			return new WP_Error(
				'twec_stripe_http',
				__( 'Could not reach Stripe. Try again later.', 'planit-event-manager' ),
				array( 'status' => 502 )
			);
		}

		return $response;
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
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'twec_stripe_start_' . $event_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'planit-event-manager' ), 403 );
		}
		if ( 'stripe' !== self::get_settings()['payment_gateway'] ) {
			wp_die( esc_html__( 'Stripe is not the selected payment gateway in PlanIt settings.', 'planit-event-manager' ), 400 );
		}
		$result = self::create_checkout_session( $event_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 500 );
		}
		if ( ! empty( $result['url'] ) ) {
			wp_safe_redirect( $result['url'] );
			exit;
		}
		wp_die( esc_html__( 'No redirect URL from Stripe.', 'planit-event-manager' ), 500 );
	}

	/**
	 * @param string[] $atts Shortcode atts.
	 * @return string
	 */
	public static function shortcode_checkout( $atts ) {
		if ( 'stripe' !== self::get_settings()['payment_gateway'] ) {
			return '';
		}
		$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'twec_stripe_checkout' );
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
		$nonce  = wp_create_nonce( 'twec_stripe_start_' . $eid );
		$label  = esc_html__( 'Pay to feature this event (Stripe)', 'planit-event-manager' );
		ob_start();
		?>
		<form class="twec-stripe-checkout-shortcode" method="post" action="<?php echo esc_url( $action ); ?>">
			<input type="hidden" name="action" value="twec_stripe_start" />
			<input type="hidden" name="event_id" value="<?php echo (int) $eid; ?>" />
			<?php wp_nonce_field( 'twec_stripe_start_' . $eid, '_wpnonce', false, true ); ?>
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
		if ( 'stripe' !== $s['payment_gateway'] ) {
			return new WP_REST_Response(
				array(
					'ok'      => true,
					'ignored' => 'gateway',
				),
				200
			);
		}
		$secret = ! empty( $s['stripe_webhook_secret'] ) ? (string) $s['stripe_webhook_secret'] : '';
		if ( '' === $secret || 0 !== strpos( $secret, 'whsec_' ) ) {
			return new WP_Error( 'twec_stripe_webhook', __( 'Webhook secret not configured.', 'planit-event-manager' ), array( 'status' => 500 ) );
		}
		$raw = $request->get_body();
		$sig = (string) $request->get_header( 'Stripe-Signature' );
		if ( '' === (string) $raw || '' === $sig ) {
			$sig = (string) $request->get_header( 'stripe_signature' );
		}
		if ( '' === (string) $raw || '' === $sig ) {
			return new WP_Error( 'twec_stripe_webhook', __( 'Invalid webhook request.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		if ( ! self::verify_webhook_signature( (string) $raw, $sig, $secret ) ) {
			return new WP_Error( 'twec_stripe_sig', __( 'Invalid signature.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}
		$payload = json_decode( (string) $raw, true );
		if ( ! is_array( $payload ) || empty( $payload['type'] ) ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}
		$type = (string) $payload['type'];
		if ( 'checkout.session.completed' === $type && ! empty( $payload['data']['object'] ) && is_array( $payload['data']['object'] ) ) {
			self::handle_checkout_session_completed( $payload['data']['object'] );
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * @param array<string, mixed> $session Stripe Checkout Session.
	 * @return void
	 */
	public static function handle_checkout_session_completed( array $session ) {
		$session_id = isset( $session['id'] ) ? (string) $session['id'] : '';
		$event_id   = 0;
		if ( ! empty( $session['client_reference_id'] ) ) {
			$event_id = (int) $session['client_reference_id'];
		}
		if ( $event_id <= 0 && ! empty( $session['metadata']['twec_event_id'] ) ) {
			$event_id = (int) $session['metadata']['twec_event_id'];
		}
		if ( $event_id <= 0 || 'twec_event' !== get_post_type( $event_id ) ) {
			return;
		}

		if ( class_exists( 'TWEC_Payment_Log', false ) ) {
			TWEC_Payment_Log::insert_from_stripe_session( $event_id, $session );
		}

		$prev = (string) get_post_meta( $event_id, self::META_SESSION_ID, true );
		if ( $session_id && $prev === $session_id ) {
			return;
		}

		update_post_meta( $event_id, self::META_PAID, '1' );
		update_post_meta( $event_id, self::META_PAID_AT, current_time( 'mysql' ) );
		if ( $session_id ) {
			update_post_meta( $event_id, self::META_SESSION_ID, $session_id );
		}

		self::maybe_send_payment_receipt( $event_id, $session );

		/**
		 * Fires after Stripe confirms a featured listing payment for an event.
		 */
		do_action( 'twec_stripe_checkout_paid', $event_id, $session );
	}

	/**
	 * Human-readable currency amount from Stripe minor units.
	 *
	 * @param int    $minor    Minor units.
	 * @param string $currency Lowercase ISO currency.
	 * @return string
	 */
	public static function format_minor_for_display( $minor, $currency ) {
		$minor = (int) $minor;
		$cur   = strtolower( (string) $currency );
		if ( self::stripe_currency_is_zero_decimal( $cur ) ) {
			return number_format( $minor > 0 ? $minor : 0, 0, '.', ',' ) . ' ' . strtoupper( $cur );
		}
		$val = $minor / 100;
		return number_format( $val > 0 ? $val : 0, 2, '.', ',' ) . ' ' . strtoupper( $cur );
	}

	/**
	 * Send configurable HTML receipt via wp_mail.
	 *
	 * @param int                 $event_id Event ID.
	 * @param array<string,mixed> $session  Stripe session.
	 * @return void
	 */
	public static function maybe_send_payment_receipt( $event_id, array $session ) {
		if ( ! function_exists( 'wp_mail' ) || ! class_exists( 'TWEC_Email_Templates', false ) ) {
			return;
		}

		$m = TWEC_Email_Templates::merged_email_settings( (array) get_option( 'twec_settings', array() ) );
		if ( 'yes' !== (string) ( $m['payment_receipt_enabled'] ?? 'no' ) ) {
			return;
		}
		if ( true !== apply_filters( 'twec_send_payment_receipt', true, (int) $event_id, $session ) ) {
			return;
		}

		$email = '';
		if ( ! empty( $session['customer_details']['email'] ) ) {
			$email = sanitize_email( (string) $session['customer_details']['email'] );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}

		$name = '';
		if ( ! empty( $session['customer_details']['name'] ) ) {
			$name = sanitize_text_field( (string) $session['customer_details']['name'] );
		}

		$currency = isset( $session['currency'] ) ? strtolower( (string) $session['currency'] ) : 'usd';
		$minor    = isset( $session['amount_total'] ) ? (int) $session['amount_total'] : 0;

		$sid = isset( $session['id'] ) ? (string) $session['id'] : '';

		$title_wp = html_entity_decode( wp_strip_all_tags( get_the_title( (int) $event_id ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		if ( '' === $title_wp ) {
			$title_wp = '#' . (string) (int) $event_id;
		}

		$ev_url = get_permalink( (int) $event_id );
		if ( ! is_string( $ev_url ) || '' === $ev_url ) {
			$ev_url = home_url( '/' );
		}

		$starts = '';
		if ( class_exists( 'TWEC_Reminders', false ) && is_callable( array( 'TWEC_Reminders', 'get_event_start_timestamp' ) ) ) {
			$start_ts = (int) TWEC_Reminders::get_event_start_timestamp( (int) $event_id );
			$tz       = TWEC_Reminders::get_event_timezone( (int) $event_id );
			$date_fmt = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
			if ( function_exists( 'wp_date' ) ) {
				$starts = wp_date( $date_fmt, $start_ts > 0 ? $start_ts : time(), $tz );
			} else {
				$starts = date_i18n( $date_fmt, $start_ts > 0 ? $start_ts : time(), true );
			}
		}

		$tokens = array(
			'event_title'       => $title_wp,
			'event_url'         => esc_url_raw( $ev_url ),
			'event_starts'      => $starts,
			'amount_display'    => self::format_minor_for_display( $minor, $currency ),
			'currency'          => strtoupper( $currency ),
			'buyer_email'       => $email,
			'buyer_name'        => $name,
			'site_name'         => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'stripe_session_id' => $sid,
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
		/** @phpstan-ignore-next-line */
		$mail_args = apply_filters( 'twec_payment_receipt_mail_args', $mail_args, $event_id, $session );
		wp_mail(
			$mail_args['to'],
			$mail_args['subject'],
			$mail_args['message'],
			$mail_args['headers']
		);
	}

	/**
	 * Match Stripe’s Webhook-Signature v1 (HMAC-SHA256). Same as stripe-php `WebhookSignature::verifyHeader`.
	 *
	 * @param string $raw        Raw request body.
	 * @param string $sig_header Stripe-Signature header.
	 * @param string $secret     Endpoint secret (whsec_…).
	 * @param int    $tolerance  Seconds; default 300.
	 * @return bool
	 */
	public static function verify_webhook_signature( $raw, $sig_header, $secret, $tolerance = 300 ) {
		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', (string) $sig_header ) as $item ) {
			$item = trim( $item );
			if ( '' === $item || false === strpos( $item, '=' ) ) {
				continue;
			}
			$pair = explode( '=', $item, 2 );
			$k    = trim( (string) ( $pair[0] ?? '' ) );
			$v    = trim( (string) ( $pair[1] ?? '' ) );
			if ( 't' === $k && is_numeric( $v ) ) {
				$timestamp = (int) $v;
			} elseif ( 'v1' === $k && '' !== $v ) {
				$signatures[] = $v;
			}
		}
		if ( $timestamp < 1 || empty( $signatures ) ) {
			return false;
		}
		if ( $tolerance > 0 && abs( time() - $timestamp ) > $tolerance ) {
			return false;
		}
		$signed_payload = (string) $timestamp . '.' . (string) $raw;
		$expected       = hash_hmac( 'sha256', $signed_payload, $secret );
		foreach ( $signatures as $sig ) {
			if ( hash_equals( $expected, (string) $sig ) ) {
				return true;
			}
		}
		return false;
	}
}
