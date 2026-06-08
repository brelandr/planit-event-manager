<?php
/**
 * Optional WooCommerce: link a product per event for ticket sales (add-to-cart + order hooks).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce integration (soft dependency — class loads always; hooks only when WC is active and enabled).
 */
class TWEC_WooCommerce {

	const META_PRODUCT_ID = '_twec_wc_product_id';
	const META_SALE_COUNT = '_twec_wc_ticket_sale_count';
	const META_LAST_ORDER = '_twec_wc_last_order_id';

	/** Order meta: ticket sale counts applied to linked events (idempotency). */
	const ORDER_META_RECORDED = '_twec_wc_ticket_sales_recorded';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 20 );
	}

	/**
	 * @return bool
	 */
	public static function is_wc_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * @return bool
	 */
	public static function is_feature_enabled() {
		$s = (array) get_option( 'twec_settings', array() );
		return ! empty( $s['woocommerce_tickets_enabled'] ) && 'yes' === (string) $s['woocommerce_tickets_enabled'];
	}

	/**
	 * Default: show ticket CTAs on event list ([twec_list]) when attr omitted.
	 *
	 * @return bool
	 */
	public static function default_show_tickets_list() {
		$s = (array) get_option( 'twec_settings', array() );
		return ! empty( $s['woocommerce_ticket_cta_list'] ) && 'yes' === (string) $s['woocommerce_ticket_cta_list'];
	}

	/**
	 * Default: show ticket CTAs on calendar ([twec_calendar]) when attr omitted.
	 *
	 * @return bool
	 */
	public static function default_show_tickets_calendar() {
		$s = (array) get_option( 'twec_settings', array() );
		return ! empty( $s['woocommerce_ticket_cta_calendar'] ) && 'yes' === (string) $s['woocommerce_ticket_cta_calendar'];
	}

	/**
	 * When tickets are enabled, require payer name/email/phone/address fields at WooCommerce checkout (classic + Checkout block).
	 *
	 * Default: yes. Disable under Events → Settings → WooCommerce ticket sales.
	 *
	 * @return bool
	 */
	public static function should_require_ticket_buyer_contact() {
		if ( ! self::is_feature_enabled() ) {
			return false;
		}
		$s = (array) get_option( 'twec_settings', array() );
		return ! isset( $s['woocommerce_ticket_require_buyer_details'] ) || 'yes' === (string) $s['woocommerce_ticket_require_buyer_details'];
	}

	/**
	 * Resolve whether to show ticket CTAs from shortcode attribute and settings.
	 *
	 * @param string $raw_attr Attribute value or empty.
	 * @param string $context  'list' or 'calendar'.
	 * @return bool
	 */
	public static function resolve_show_ticket_cta( $raw_attr, $context ) {
		$raw = is_string( $raw_attr ) ? strtolower( trim( str_replace( array( '“', '”', '‘', '’' ), '', $raw_attr ) ) ) : '';
		if ( in_array( $raw, array( 'yes', '1', 'true', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $raw, array( 'no', '0', 'false', 'off' ), true ) ) {
			return false;
		}
		if ( 'list' === $context ) {
			return self::default_show_tickets_list();
		}
		if ( 'calendar' === $context ) {
			return self::default_show_tickets_calendar();
		}
		return false;
	}

	/**
	 * @return void
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		if ( ! self::is_wc_active() || ! self::is_feature_enabled() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_ticket_button_styles' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_twec_event', array( __CLASS__, 'save_event_product_meta' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'sync_order_ticket_sales' ), 15, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'sync_order_ticket_sales' ), 15, 1 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'require_ticket_buyer_checkout_fields' ), 25, 1 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_ticket_buyer_classic_checkout' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'validate_ticket_buyer_store_api_order' ), 5, 2 );
	}

	/**
	 * When the cart has a TWEC-linked ticket product, mark core billing fields as required (classic checkout form).
	 *
	 * @param array $fields Checkout field groups by type.
	 * @return array
	 */
	public static function require_ticket_buyer_checkout_fields( $fields ) {
		if ( ! self::should_require_ticket_buyer_contact() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $fields;
		}
		if ( WC()->cart->is_empty() || ! self::cart_contains_twec_ticket_product() ) {
			return $fields;
		}
		$keys = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_address_1',
			'billing_city',
			'billing_postcode',
			'billing_country',
			'billing_state',
		);
		foreach ( $keys as $k ) {
			if ( isset( $fields['billing'][ $k ] ) ) {
				$fields['billing'][ $k ]['required'] = true;
			}
		}
		return $fields;
	}

	/**
	 * Validates billing contact fields on classic POST checkout after WC core validation.
	 *
	 * @param array     $posted Posted checkout payload.
	 * @param \WP_Error $errors Errors object.
	 * @return void
	 */
	public static function validate_ticket_buyer_classic_checkout( $posted, $errors ) {
		if ( ! self::should_require_ticket_buyer_contact() || ! is_object( $errors ) || ! is_callable( array( $errors, 'add' ) ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		if ( ! self::cart_contains_twec_ticket_product() ) {
			return;
		}
		$messages = self::get_ticket_buyer_contact_validation_messages( is_array( $posted ) ? $posted : array() );
		foreach ( $messages as $msg ) {
			if ( is_string( $msg ) && '' !== $msg ) {
				$errors->add( 'twec_ticket_buyer', wp_strip_all_tags( $msg ) );
			}
		}
	}

	/**
	 * Validates billing contact fields for Checkout block / Store API (WooCommerce 7.2+).
	 *
	 * @param WC_Order        $order   Order being updated from the request.
	 * @param WP_REST_Request $request REST request (unused; billing copied from order).
	 * @return void
	 */
	public static function validate_ticket_buyer_store_api_order( $order, $request ) {
		unset( $request );
		if ( ! self::should_require_ticket_buyer_contact() || ! is_object( $order ) ) {
			return;
		}
		if ( ! $order instanceof \WC_Order || ! method_exists( $order, 'get_items' ) || ! self::order_contains_twec_ticket_product( $order ) ) {
			return;
		}
		$data = self::checkout_billing_data_from_order( $order );
		$msgs = self::get_ticket_buyer_contact_validation_messages( $data );
		if ( empty( $msgs ) ) {
			return;
		}
		throw new \Exception( implode( ' ', array_map( 'wp_strip_all_tags', $msgs ) ) );
	}

	/**
	 * Normalize WC_Order billing_* into POST-style keys used by validators.
	 *
	 * @param \WC_Order $order Order object.
	 * @return array
	 */
	private static function checkout_billing_data_from_order( $order ) {
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_billing_country' ) ) ) {
			return array();
		}
		return array(
			'billing_first_name' => trim( (string) $order->get_billing_first_name() ),
			'billing_last_name'  => trim( (string) $order->get_billing_last_name() ),
			'billing_email'      => trim( (string) $order->get_billing_email() ),
			'billing_phone'      => trim( (string) $order->get_billing_phone() ),
			'billing_address_1'  => trim( (string) $order->get_billing_address_1() ),
			'billing_city'       => trim( (string) $order->get_billing_city() ),
			'billing_postcode'   => trim( (string) $order->get_billing_postcode() ),
			'billing_country'    => trim( (string) $order->get_billing_country() ),
			'billing_state'      => trim( (string) $order->get_billing_state() ),
		);
	}

	/**
	 * Whether the storefront cart contains a product linked as a TWEC event ticket.
	 *
	 * @return bool
	 */
	public static function cart_contains_twec_ticket_product() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $row ) {
			if ( is_array( $row ) && self::cart_row_links_twec_event_product( $row ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $cart_item Cart item structure.
	 * @return bool
	 */
	private static function cart_row_links_twec_event_product( array $cart_item ) {
		$product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
		$variation  = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;

		return self::candidate_product_ids_linked_to_twec_event(
			array(
				$variation > 0 ? $variation : $product_id,
				$product_id,
				$variation,
			)
		);
	}

	/**
	 * Whether the order contains a line item tied to at least one TWEC event ticket product.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return bool
	 */
	public static function order_contains_twec_ticket_product( $order ) {
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_items' ) ) ) {
			return false;
		}
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! is_callable( array( $item, 'get_product_id' ) ) ) {
				continue;
			}
			$a = (int) $item->get_product_id();
			$b = is_callable( array( $item, 'get_variation_id' ) ) ? (int) $item->get_variation_id() : 0;
			if ( self::candidate_product_ids_linked_to_twec_event(
				array(
					$a > 0 ? $a : 0,
					$b > 0 ? $b : 0,
				)
			) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve whether any TWEC events link products of the supplied IDs as ticket products/variations/parent simple.
	 *
	 * @param int[] $candidate_ids WooCommerce product/variation IDs.
	 * @return bool
	 */
	private static function candidate_product_ids_linked_to_twec_event( array $candidate_ids ) {
		foreach ( array_unique( array_filter( array_map( 'intval', $candidate_ids ) ) ) as $cid ) {
			if ( $cid > 0 && ! empty( self::get_events_for_product( $cid ) ) ) {
				return true;
			}
			if ( $cid > 0 ) {
				$parent = (int) wp_get_post_parent_id( $cid );
				if ( $parent > 0 && ! empty( self::get_events_for_product( $parent ) ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Human-readable state/province label for CSV exports.
	 *
	 * @param string $country Country ISO code (e.g. US).
	 * @param string $state   State slug / code saved on the order.
	 * @return string
	 */
	private static function billing_state_readable( $country, $state ) {
		$country = strtoupper( (string) $country );
		$state   = (string) $state;
		if ( '' === $state ) {
			return '';
		}
		if ( function_exists( 'WC' ) && WC()->countries && method_exists( WC()->countries, 'get_states' ) ) {
			$states = WC()->countries->get_states( $country );
			if ( is_array( $states ) && isset( $states[ $state ] ) ) {
				return (string) $states[ $state ];
			}
		}
		return $state;
	}

	/**
	 * Validation messages when ticket buyer contact detail requirements are enforced.
	 *
	 * @param array $data Posted checkout billing keys (`billing_*`) or normalized array from order.
	 * @return string[]
	 */
	private static function get_ticket_buyer_contact_validation_messages( array $data ) {
		$errs = array();
		$t    = static function ( $k ) use ( $data ) {
			return isset( $data[ $k ] ) ? trim( (string) $data[ $k ] ) : '';
		};

		if ( '' === $t( 'billing_first_name' ) ) {
			$errs[] = __( 'Please enter your first name.', 'planit-event-manager' );
		}
		if ( '' === $t( 'billing_last_name' ) ) {
			$errs[] = __( 'Please enter your last name.', 'planit-event-manager' );
		}
		$email = $t( 'billing_email' );
		if ( '' === $email || ! is_email( $email ) ) {
			$errs[] = __( 'Please enter a valid email address.', 'planit-event-manager' );
		}
		if ( '' === $t( 'billing_phone' ) ) {
			$errs[] = __( 'Please enter a phone number.', 'planit-event-manager' );
		}
		if ( '' === $t( 'billing_address_1' ) ) {
			$errs[] = __( 'Please enter your street address.', 'planit-event-manager' );
		}
		if ( '' === $t( 'billing_city' ) ) {
			$errs[] = __( 'Please enter your city or town.', 'planit-event-manager' );
		}

		$cc = strtoupper( $t( 'billing_country' ) );
		if ( '' === $cc ) {
			$errs[] = __( 'Please select your country.', 'planit-event-manager' );
		}

		if ( '' !== $cc && function_exists( 'WC' ) && WC()->countries && method_exists( WC()->countries, 'get_states' ) ) {
			$states = WC()->countries->get_states( $cc );
			if ( is_array( $states ) && count( $states ) > 0 && '' === $t( 'billing_state' ) ) {
				$errs[] = __( 'Please enter your state or province.', 'planit-event-manager' );
			}
		}

		if ( '' !== $cc && '' === $t( 'billing_postcode' ) ) {
			$need_postcode = true;
			if ( function_exists( 'WC' ) && WC()->countries && method_exists( WC()->countries, 'postcode_optional_for_country' ) ) {
				$need_postcode = ! WC()->countries->postcode_optional_for_country( $cc );
			} elseif ( function_exists( 'wc_get_country_locale' ) ) {
				$loc = wc_get_country_locale();
				if ( isset( $loc[ $cc ]['postcode']['required'] ) ) {
					$need_postcode = (bool) $loc[ $cc ]['postcode']['required'];
				}
			}
			if ( $need_postcode ) {
				$errs[] = __( 'Please enter your postcode or ZIP code.', 'planit-event-manager' );
			}
		}

		return array_values( array_unique( array_filter( apply_filters( 'twec_wc_ticket_buyer_contact_validation_messages', $errs, $data ) ) ) );
	}

	/**
	 * Scoped styles / custom colors for ticket + View cart buttons.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_ticket_button_styles() {
		if ( is_admin() ) {
			return;
		}
		$css = self::get_ticket_button_inline_css();
		if ( '' === $css ) {
			return;
		}
		wp_add_inline_style( 'twec-public', $css );
	}

	/**
	 * Whether to show "View cart" next to Get tickets on list/single surfaces.
	 *
	 * @return bool
	 */
	public static function should_show_view_cart_link() {
		$s = (array) get_option( 'twec_settings', array() );
		return ! isset( $s['woocommerce_ticket_show_view_cart'] ) || 'yes' === (string) $s['woocommerce_ticket_show_view_cart'];
	}

	/**
	 * Preset slug: theme, solid, outline, custom.
	 *
	 * @return string
	 */
	public static function get_ticket_button_style_preset() {
		$s   = (array) get_option( 'twec_settings', array() );
		$key = isset( $s['woocommerce_ticket_btn_style'] ) ? (string) $s['woocommerce_ticket_btn_style'] : 'solid';
		$key = strtolower( sanitize_key( $key ) );
		return in_array( $key, array( 'theme', 'solid', 'outline', 'custom' ), true ) ? $key : 'solid';
	}

	/**
	 * Wrapper class for preset (for CSS selectors).
	 *
	 * @return string Escaped-friendly class substring.
	 */
	public static function get_ticket_actions_preset_classes() {
		return 'twec-wc-preset--' . self::get_ticket_button_style_preset();
	}

	/**
	 * CSS rules for Custom preset colors (validated hex).
	 *
	 * @return string Safe CSS snippet.
	 */
	public static function get_ticket_button_inline_css() {
		if ( self::get_ticket_button_style_preset() !== 'custom' ) {
			return '';
		}
		$s = (array) get_option( 'twec_settings', array() );

		$prim_bg = isset( $s['woocommerce_ticket_btn_primary_bg'] ) ? strtolower( sanitize_text_field( (string) $s['woocommerce_ticket_btn_primary_bg'] ) ) : '#2271b1';
		$prim_tx = isset( $s['woocommerce_ticket_btn_primary_text'] ) ? strtolower( sanitize_text_field( (string) $s['woocommerce_ticket_btn_primary_text'] ) ) : '#ffffff';
		if ( ! is_string( $prim_bg ) || '' === sanitize_hex_color( $prim_bg ) ) {
			$prim_bg = '#2271b1';
		}
		if ( ! is_string( $prim_tx ) || '' === sanitize_hex_color( $prim_tx ) ) {
			$prim_tx = '#ffffff';
		}

		$rad = isset( $s['woocommerce_ticket_btn_radius'] ) ? (int) $s['woocommerce_ticket_btn_radius'] : 8;
		$rad = max( 0, min( 32, $rad ) );

		$sec_mode = isset( $s['woocommerce_ticket_btn_secondary_mode'] ) ? sanitize_key( (string) $s['woocommerce_ticket_btn_secondary_mode'] ) : 'outline';
		if ( ! in_array( $sec_mode, array( 'outline', 'ghost', 'muted' ), true ) ) {
			$sec_mode = 'outline';
		}

		$prim_bg_esc = sanitize_hex_color( $prim_bg );
		$prim_tx_esc = sanitize_hex_color( $prim_tx );
		if ( ! is_string( $prim_bg_esc ) || '' === $prim_bg_esc ) {
			$prim_bg_esc = '#2271b1';
		}
		if ( ! is_string( $prim_tx_esc ) || '' === $prim_tx_esc ) {
			$prim_tx_esc = '#ffffff';
		}

		$sel = '.twec-wc-preset--custom.twec-wc-ticket-actions';
		$c   = "{$sel} .twec-wc-btn-primary{";
		$c  .= 'background-color:' . $prim_bg_esc . ';';
		$c  .= 'color:' . $prim_tx_esc . '!important;';
		$c  .= 'border:none;border-radius:' . (int) $rad . 'px;';
		$c  .= 'padding:0.55em 1.15em;text-decoration:none;';
		$c  .= 'box-shadow:0 1px 2px rgba(0,0,0,.08);';
		$c  .= '}' . "{$sel} .twec-wc-btn-primary:hover," . "{$sel} .twec-wc-btn-primary:focus{filter:brightness(0.94);}";
		if ( 'ghost' === $sec_mode ) {
			$c .= "{$sel} .twec-wc-btn-secondary{background:transparent;color:#646970!important;border:none;border-radius:{$rad}px;}";
		} elseif ( 'muted' === $sec_mode ) {
			$c .= "{$sel} .twec-wc-btn-secondary{background:#f0f0f1;color:#50575e!important;border:1px solid #c3c4c7;border-radius:{$rad}px;}";
		} else {
			$c .= "{$sel} .twec-wc-btn-secondary{background:#fff;color:#50575e!important;border:2px solid " . $prim_bg_esc . ';border-radius:' . $rad . 'px;}';
			$c .= "{$sel} .twec-wc-btn-secondary:hover{background:rgba(255,255,255,.94);}";
		}
		return "\n" . $c . "\n";
	}


	/**
	 * Apply ticket line items to event meta once per order (payment complete and/or completed status).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function sync_order_ticket_sales( $order_id ) {
		if ( ! self::is_wc_active() || ! self::is_feature_enabled() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$oid = (int) $order_id;
		if ( $oid < 1 ) {
			return;
		}
		$order = wc_get_order( $oid );
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_items' ) ) ) {
			return;
		}
		if ( $order->get_meta( self::ORDER_META_RECORDED, true ) ) {
			return;
		}
		self::apply_order_items_to_events( $order, $oid );
		$order->update_meta_data( self::ORDER_META_RECORDED, 'yes' );
		$order->save();
	}

	/**
	 * @param WC_Order $order Order object.
	 * @param int      $oid   Order ID.
	 * @return void
	 */
	private static function apply_order_items_to_events( $order, $oid ) {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! is_callable( array( $item, 'get_product_id' ) ) ) {
				continue;
			}
			$product_id   = (int) $item->get_product_id();
			$variation_id = (int) $item->get_variation_id();
			$qty          = (int) $item->get_quantity();
			if ( $qty < 1 ) {
				continue;
			}
			$ids = array( $product_id, $variation_id );
			if ( $variation_id > 0 ) {
				$parent = (int) wp_get_post_parent_id( $variation_id );
				if ( $parent > 0 ) {
					$ids[] = $parent;
				}
			}
			$ids     = array_unique( array_filter( array_map( 'intval', $ids ) ) );
			$matched = array();
			foreach ( $ids as $cand ) {
				if ( $cand < 1 ) {
					continue;
				}
				$event_ids = self::get_events_for_product( $cand );
				foreach ( $event_ids as $eid ) {
					$eid = (int) $eid;
					if ( $eid < 1 || isset( $matched[ $eid ] ) ) {
						continue;
					}
					$matched[ $eid ] = true;
					$cur             = (int) get_post_meta( $eid, self::META_SALE_COUNT, true );
					update_post_meta( $eid, self::META_SALE_COUNT, $cur + $qty );
					update_post_meta( $eid, self::META_LAST_ORDER, (string) $oid );
					/**
					 * Fires when a WooCommerce order matched an event’s ticket product (after payment completion or completion status).
					 *
					 * @param int    $eid   Event post ID.
					 * @param int    $oid   Woo order ID.
					 * @param int    $cand  Matched product or variation ID.
					 * @param int    $qty   Line quantity.
					 * @param object $order WC Order.
					 * @param object $item  Order line item.
					 */
					do_action( 'twec_woo_event_ticket_sold', $eid, $oid, (int) $cand, (int) $qty, $order, $item );
				}
			}
		}
	}

	/**
	 * @return void
	 */
	public static function register_shortcode() {
		add_shortcode( 'twec_wc_add_to_cart', array( __CLASS__, 'shortcode_add_to_cart' ) );
	}

	/**
	 * @return void
	 */
	public static function add_meta_boxes() {
		if ( ! self::is_wc_active() || ! self::is_feature_enabled() ) {
			return;
		}
		add_meta_box(
			'twec_wc_ticket_product',
			__( 'WooCommerce ticket product', 'planit-event-manager' ),
			array( __CLASS__, 'render_meta_box' ),
			'twec_event',
			'side',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		if ( 'twec_event' !== $post->post_type ) {
			return;
		}
		wp_nonce_field( 'twec_wc_ticket_save', 'twec_wc_ticket_nonce' );
		$pid   = (int) get_post_meta( $post->ID, self::META_PRODUCT_ID, true );
		$sales = (int) get_post_meta( $post->ID, self::META_SALE_COUNT, true );
		$last  = (string) get_post_meta( $post->ID, self::META_LAST_ORDER, true );
		?>
		<p class="description">
			<?php esc_html_e( 'Create a product in WooCommerce, then enter its product ID (or a variation ID) to sell tickets for this event through your store checkout.', 'planit-event-manager' ); ?>
		</p>
		<p>
			<label for="twec_wc_product_id"><strong><?php esc_html_e( 'Product ID', 'planit-event-manager' ); ?></strong></label><br />
			<input type="number" class="small-text" name="twec_wc_product_id" id="twec_wc_product_id" value="<?php echo $pid > 0 ? (int) $pid : ''; ?>" min="0" step="1" />
		</p>
		<?php
		if ( $pid > 0 ) {
			$edit = get_edit_post_link( $pid, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				printf(
					'<p><a href="%s">%s</a></p>',
					esc_url( $edit ),
					esc_html__( 'Edit this product in WooCommerce', 'planit-event-manager' )
				);
			}
		}
		if ( $sales > 0 || '' !== $last ) {
			echo '<p class="description">';
			if ( $sales > 0 ) {
				echo esc_html( sprintf( /* translators: %d: total quantity */ __( 'Reported sales (ticket quantity recorded): %d', 'planit-event-manager' ), (int) $sales ) );
			}
			if ( '' !== $last ) {
				if ( $sales > 0 ) {
					echo ' ';
				}
				echo esc_html( sprintf( /* translators: %s: order id */ __( 'Last order: #%s', 'planit-event-manager' ), $last ) );
			}
			echo '</p>';
		}
		?>
		<?php
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_event_product_meta( $post_id, $post ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified with wp_verify_nonce( wp_unslash( ... ), ... ).
		if ( ! isset( $_POST['twec_wc_ticket_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['twec_wc_ticket_nonce'] ), 'twec_wc_ticket_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'twec_event' !== $post->post_type ) {
			return;
		}
		if ( ! self::is_wc_active() || ! self::is_feature_enabled() ) {
			return;
		}
		$raw = isset( $_POST['twec_wc_product_id'] ) ? (string) wp_unslash( $_POST['twec_wc_product_id'] ) : '';
		$raw = trim( $raw );
		if ( '' === $raw || '0' === $raw ) {
			delete_post_meta( $post_id, self::META_PRODUCT_ID );
			return;
		}
		$pid = absint( $raw );
		if ( $pid < 1 ) {
			return;
		}
		$ptype = get_post_type( $pid );
		if ( 'product' !== $ptype && 'product_variation' !== $ptype ) {
			return;
		}
		update_post_meta( $post_id, self::META_PRODUCT_ID, (string) $pid );
	}

	/**
	 * @param int $product_id Product or variation ID.
	 * @return int[]
	 */
	public static function get_events_for_product( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return array();
		}
		$q = new WP_Query(
			array(
				'post_type'      => 'twec_event',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::META_PRODUCT_ID,
				'meta_value'     => (string) $product_id,
			)
		);
		return is_array( $q->posts ) ? array_map( 'intval', $q->posts ) : array();
	}

	/**
	 * Product ID linked to an event (simple or variation).
	 *
	 * @param int $event_id Event post ID.
	 * @return int
	 */
	public static function get_linked_product_id_for_event( $event_id ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			return 0;
		}
		$pid = (int) get_post_meta( $event_id, self::META_PRODUCT_ID, true );
		return $pid > 0 ? $pid : 0;
	}

	/**
	 * Rich CTA row for event list/archive (button or sold-out text).
	 *
	 * @param int         $event_id Event post ID.
	 * @param string|null $label_override Optional button label; default "Get tickets".
	 * @return string HTML (safe for echo).
	 */
	public static function get_list_ticket_cta_html( $event_id, $label_override = null ) {
		if ( ! self::is_wc_active() || ! self::is_feature_enabled() ) {
			return '';
		}
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			return '';
		}
		$pid = self::get_linked_product_id_for_event( $event_id );
		if ( $pid < 1 ) {
			return '';
		}
		if ( 'product' !== get_post_type( $pid ) && 'product_variation' !== get_post_type( $pid ) ) {
			return '';
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $pid );
			if ( $wc_product && is_object( $wc_product ) && method_exists( $wc_product, 'is_in_stock' ) && ! $wc_product->is_in_stock() ) {
				return '<p class="twec-wc-add-to-cart twec-wc-tickets-sold-out"><span class="twec-wc-sold-out-label">' . esc_html__( 'Sold out', 'planit-event-manager' ) . '</span></p>';
			}
		}

		$url = self::get_add_to_cart_url( $pid );
		if ( '' === $url ) {
			return '';
		}
		$label = null !== $label_override && '' !== (string) $label_override ? (string) $label_override : __( 'Get tickets', 'planit-event-manager' );

		$preset        = self::get_ticket_actions_preset_classes();
		$primary_class = 'twec-wc-btn-primary button twec-woo-tickets-button';

		$html  = '<div class="twec-wc-add-to-cart twec-wc-ticket-actions ' . esc_attr( $preset ) . '">';
		$html .= '<a class="' . esc_attr( $primary_class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';

		if ( self::should_show_view_cart_link() && function_exists( 'wc_get_cart_url' ) ) {
			$cart_url = (string) wc_get_cart_url();
			if ( '' !== $cart_url ) {
				$html .= '<a class="twec-wc-btn-secondary button" href="' . esc_url( $cart_url ) . '">' . esc_html__( 'View cart', 'planit-event-manager' ) . '</a>';
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Compact markup for calendar cells (after event title link).
	 *
	 * @param int $event_id Event post ID.
	 * @return string HTML (safe for concatenation; contains escaped text/urls).
	 */
	public static function get_calendar_inline_ticket_markup( $event_id ) {
		if ( ! self::is_wc_active() || ! self::is_feature_enabled() ) {
			return '';
		}
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			return '';
		}
		$pid = self::get_linked_product_id_for_event( $event_id );
		if ( $pid < 1 ) {
			return '';
		}

		$wc_product = null;
		if ( function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $pid );
			if ( $wc_product && is_object( $wc_product ) && method_exists( $wc_product, 'is_in_stock' ) && ! $wc_product->is_in_stock() ) {
				return ' <span class="twec-wc-calendar-tickets twec-wc-tickets-sold-out">' . esc_html__( 'Sold out', 'planit-event-manager' ) . '</span>';
			}
		}

		$url = self::get_add_to_cart_url( $pid );
		if ( '' === $url ) {
			return '';
		}

		// Use `aria-label` (not `title`) so we do not trigger the native browser tooltip on hover; CSS handles rollover affordance.
		$ticket_aria_label = __( 'Buy tickets via WooCommerce', 'planit-event-manager' );
		if ( $wc_product && is_object( $wc_product ) && method_exists( $wc_product, 'get_name' ) ) {
			$product_name = trim( (string) $wc_product->get_name() );
			if ( '' !== $product_name ) {
				$ticket_aria_label = sprintf(
					/* translators: %s: WooCommerce product title */
					__( 'Buy tickets: %s', 'planit-event-manager' ),
					$product_name
				);
			}
		}

		return ' <a class="twec-wc-calendar-tickets twec-woo-tickets-button" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $ticket_aria_label ) . '">' . esc_html__( 'Tickets', 'planit-event-manager' ) . '</a>';
	}

	/**
	 * Stream a CSV of orders that include this event’s ticket product (matching qty column).
	 *
	 * @param int $event_id Event post ID.
	 * @return void
	 */
	public static function send_ticket_orders_csv( $event_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'planit-event-manager' ), '', array( 'response' => 403 ) );
		}
		$event_id = (int) $event_id;
		if ( $event_id < 1 || ! self::is_feature_enabled() || ! self::is_wc_active() ) {
			wp_die( esc_html__( 'Invalid event.', 'planit-event-manager' ), '', array( 'response' => 400 ) );
		}
		$orders = self::get_orders_for_event_ticket_product( $event_id );
		$lnk    = self::get_linked_product_id_for_event( $event_id );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=twec-ticket-orders-' . $event_id . '.csv' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}
		fputcsv( $out, array( 'order_id', 'status', 'date', 'email', 'phone', 'billing_address', 'matching_qty', 'billing_name' ) );

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_id' ) ) ) {
				continue;
			}
			$qty = 0;
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! is_object( $item ) || ! is_callable( array( $item, 'get_product_id' ) ) ) {
					continue;
				}
				$p = (int) $item->get_product_id();
				$v = (int) $item->get_variation_id();
				if ( $lnk > 0 && ( $p === $lnk || $v === $lnk ) ) {
					$qty += (int) $item->get_quantity();
				}
			}
			$name        = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$c_code      = (string) $order->get_billing_country();
			$c_state     = (string) $order->get_billing_state();
			$s_label     = '' !== trim( $c_state ) ? self::billing_state_readable( $c_code, $c_state ) : '';
			$addr_vec    = array(
				preg_replace( '/[\r\n]+/', ' ', (string) wp_strip_all_tags( (string) $order->get_billing_address_1() ) ),
				preg_replace( '/[\r\n]+/', ' ', (string) wp_strip_all_tags( (string) $order->get_billing_address_2() ) ),
				preg_replace( '/[\r\n]+/', ' ', (string) wp_strip_all_tags( (string) $order->get_billing_city() ) ),
				preg_replace( '/[\r\n]+/', ' ', (string) wp_strip_all_tags( $s_label ) ),
				preg_replace( '/[\r\n]+/', ' ', (string) wp_strip_all_tags( (string) $order->get_billing_postcode() ) ),
				preg_replace( '/[\r\n]+/', ' ', (string) wp_strip_all_tags( (string) $order->get_billing_country() ) ),
			);
			$addr_plain  = implode(
				', ',
				array_filter(
					array_map( 'trim', $addr_vec ),
					static function ( $p ) {
						return is_string( $p ) && '' !== $p;
					}
				)
			);
			$phone_plain = preg_replace( '/[\r\n]+/', ' ', (string) wp_strip_all_tags( (string) $order->get_billing_phone() ) );
			fputcsv(
				$out,
				array(
					(int) $order->get_id(),
					$order->get_status(),
					$order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '',
					(string) $order->get_billing_email(),
					trim( $phone_plain ),
					$addr_plain,
					$qty,
					$name,
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * Woo orders containing the linked product/variation for an event (recent first, capped).
	 *
	 * @param int $event_id Event ID.
	 * @return WC_Order[]
	 */
	public static function get_orders_for_event_ticket_product( $event_id ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 || ! self::is_wc_active() || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$pid = self::get_linked_product_id_for_event( $event_id );
		if ( $pid < 1 ) {
			return array();
		}
		$ids_flat = array( $pid );
		if ( function_exists( 'wc_get_product' ) ) {
			$p = wc_get_product( $pid );
			if ( $p && is_object( $p ) && method_exists( $p, 'get_parent_id' ) && (int) $p->get_parent_id() > 0 ) {
				$ids_flat[] = (int) $p->get_parent_id();
			}
		}
		$ids_flat = array_unique( array_filter( array_map( 'intval', $ids_flat ) ) );

		$limit = (int) apply_filters( 'twec_wc_ticket_orders_scan_limit', 400 );
		if ( $limit < 1 ) {
			$limit = 400;
		}

		$orders = wc_get_orders(
			array(
				'limit'   => $limit,
				'orderby' => 'date',
				'order'   => 'DESC',
				'status'  => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed' ),
			)
		);

		$out = array();
		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_items' ) ) ) {
				continue;
			}
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! is_object( $item ) || ! is_callable( array( $item, 'get_product_id' ) ) ) {
					continue;
				}
				$op = (int) $item->get_product_id();
				$ov = (int) $item->get_variation_id();
				if ( in_array( $op, $ids_flat, true ) || ( $ov > 0 && in_array( $ov, $ids_flat, true ) ) ) {
					$out[] = $order;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * @param string[] $atts Atts.
	 * @return string
	 */
	public static function shortcode_add_to_cart( $atts ) {
		if ( ! self::is_wc_active() || ! self::is_feature_enabled() ) {
			return '';
		}
		$atts = shortcode_atts(
			array(
				'event_id' => 0,
				'label'    => '',
			),
			$atts,
			'twec_wc_add_to_cart'
		);
		$eid  = (int) $atts['event_id'];
		if ( $eid <= 0 && is_singular( 'twec_event' ) ) {
			$eid = (int) get_queried_object_id();
		}
		if ( $eid < 1 || 'twec_event' !== get_post_type( $eid ) ) {
			return '';
		}
		$label = (string) $atts['label'];
		return self::get_list_ticket_cta_html( $eid, '' !== trim( $label ) ? $label : null );
	}

	/**
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public static function get_add_to_cart_url( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id < 1 || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! is_object( $product ) ) {
			return '';
		}
		if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
			return '';
		}

		if ( apply_filters( 'twec_wc_ticket_add_to_cart_uses_cart_url', true ) && function_exists( 'wc_get_cart_url' ) ) {
			$cart_url = wc_get_cart_url();
			if ( is_string( $cart_url ) && '' !== $cart_url ) {
				return (string) esc_url_raw(
					add_query_arg(
						'add-to-cart',
						(string) $product_id,
						$cart_url
					)
				);
			}
		}

		$u = $product->add_to_cart_url();
		return is_string( $u ) ? $u : '';
	}
}
