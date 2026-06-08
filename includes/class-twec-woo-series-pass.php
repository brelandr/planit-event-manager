<?php
/**
 * WooCommerce passes linked to PlanIt Event Series taxonomy and explicit event packs.
 *
 * Purchases grant attendee entitlements keyed by series term IDs and/or event post IDs; revocations decrement counts.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Series / season passes and ticket packs (WooCommerce product ↔ series terms and/or event list).
 */
class TWEC_Woo_Series_Pass {

	public const META_SERIES_TERM       = '_twec_pass_series_term_id';
	public const META_SERIES_TERMS      = '_twec_pass_series_term_ids';
	public const META_EVENT_IDS         = '_twec_pass_event_ids';
	public const ORDER_META_SNAPSHOT    = '_twec_series_pass_snapshot';
	public const ORDER_META_READY       = '_twec_series_pass_applied';
	public const USER_META_COUNTS       = 'twec_series_pass_counts';
	public const USER_META_EVENT_COUNTS = 'twec_pack_event_counts';

	/**
	 * Bootstrap hooks after WooCommerce is available.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_boot' ), 25 );
	}

	/**
	 * Register WooCommerce hooks when ticket integration is enabled.
	 *
	 * @return void
	 */
	public static function maybe_boot() {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'TWEC_WooCommerce' ) ) {
			return;
		}
		if ( ! TWEC_WooCommerce::is_wc_active() || ! TWEC_WooCommerce::is_feature_enabled() ) {
			return;
		}

		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_product_series_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_series_field' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_apply_series_pass' ), 30, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_apply_series_pass' ), 30, 1 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'maybe_revoke_series_pass' ), 30, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'maybe_revoke_series_pass' ), 30, 1 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_gate_single_event' ), 12 );
	}

	/**
	 * Optional frontend guard (opt-in filter) for events tagged with Series or explicit pack entitlements.
	 *
	 * @return void
	 */
	public static function maybe_gate_single_event() {
		$lock = apply_filters( 'twec_series_pass_lock_events', false );
		if ( ! $lock ) {
			$lock = apply_filters( 'twec_ticket_pack_lock_events', false );
		}
		if ( ! $lock ) {
			return;
		}
		if ( ! is_singular( 'twec_event' ) ) {
			return;
		}
		$event_id = get_queried_object_id();
		if ( ! $event_id ) {
			return;
		}
		if ( current_user_can( 'edit_post', $event_id ) ) {
			return;
		}
		if ( self::user_has_pack_access( $event_id ) ) {
			return;
		}
		wp_safe_redirect( home_url() );
		exit;
	}

	/**
	 * PlanIt pass fields on the product edit screen.
	 *
	 * @return void
	 */
	public static function render_product_series_field() {
		if ( ! taxonomy_exists( 'twec_event_series' ) ) {
			echo '<div class="options_group"><p><em>' .
				esc_html__( 'Event Series taxonomy is not registered — license PlanIt Premium and ensure Pro features initialize.', 'planit-event-manager' ) .
				'</em></p></div>';
			return;
		}

		global $post;
		$pid            = isset( $post->ID ) ? (int) $post->ID : 0;
		$selected_terms = $pid ? self::get_product_series_term_ids( $pid ) : array();
		$event_ids_raw  = $pid ? get_post_meta( $pid, self::META_EVENT_IDS, true ) : '';
		$event_lines    = '';
		if ( is_string( $event_ids_raw ) && '' !== $event_ids_raw ) {
			$decoded = json_decode( $event_ids_raw, true );
			if ( is_array( $decoded ) ) {
				$event_lines = implode(
					"\n",
					array_map( 'strval', array_map( 'absint', $decoded ) )
				);
			}
		}

		wp_nonce_field( 'twec_series_pass_product', 'twec_series_pass_product_nonce' );
		echo '<div class="options_group">';
		echo '<p><strong>' . esc_html__( 'PlanIt ticket pack / series pass', 'planit-event-manager' ) . '</strong></p>';

		$terms = get_terms(
			array(
				'taxonomy'   => 'twec_event_series',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		echo '<p class="form-field"><label>' . esc_html__( 'Series terms (multi-select)', 'planit-event-manager' ) . '</label></p>';
		echo '<div style="max-height:14em;overflow:auto;border:1px solid #c3c4c7;padding:8px;background:#fff;">';
		if ( empty( $terms ) ) {
			echo '<em>' . esc_html__( 'No series terms yet.', 'planit-event-manager' ) . '</em>';
		} else {
			$selected_lookup = array_fill_keys( array_map( 'intval', $selected_terms ), true );
			foreach ( $terms as $term ) {
				if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
					continue;
				}
				$tid = (int) $term->term_id;
				printf(
					'<label style="display:block;margin:4px 0;"><input type="checkbox" name="twec_pass_series_terms[]" value="%1$s" %2$s /> %3$s</label>',
					esc_attr( (string) $tid ),
					checked( ! empty( $selected_lookup[ $tid ] ), true, false ),
					esc_html( $term->name )
				);
			}
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Each quantity sold adds one entitlement per selected series (events tagged with any of these terms).', 'planit-event-manager' ) . '</p>';

		echo '<p class="form-field"><label for="twec_pass_event_ids">' . esc_html__( 'Event IDs (optional)', 'planit-event-manager' ) . '</label>';
		echo '<textarea name="twec_pass_event_ids" id="twec_pass_event_ids" rows="4" class="large-text" placeholder="123
456">' . esc_textarea( $event_lines ) . '</textarea></p>';
		echo '<p class="description">' . esc_html__( 'One PlanIt event post ID per line or comma-separated. Each quantity sold adds one entitlement per listed event (for packs not tied to a series).', 'planit-event-manager' ) . '</p>';

		echo '</div>';
	}

	/**
	 * Persist series terms and event IDs for a product.
	 *
	 * @param int $pid Product ID.
	 * @return void
	 */
	public static function save_product_series_field( $pid ) {
		$pid = (int) $pid;
		if ( $pid < 1 ) {
			return;
		}
		if ( ! isset( $_POST['twec_series_pass_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['twec_series_pass_product_nonce'] ) ), 'twec_series_pass_product' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $pid ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce product cap.
			return;
		}

		$term_ids = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each ID is cast with absint().
		$posted_terms = isset( $_POST['twec_pass_series_terms'] ) ? wp_unslash( $_POST['twec_pass_series_terms'] ) : array();
		$posted_terms = is_array( $posted_terms ) ? $posted_terms : array();
		if ( taxonomy_exists( 'twec_event_series' ) && ! empty( $posted_terms ) ) {
			foreach ( $posted_terms as $raw_tid ) {
				$tid = absint( $raw_tid );
				if ( $tid < 1 ) {
					continue;
				}
				$term = get_term( $tid, 'twec_event_series' );
				if ( $term && ! is_wp_error( $term ) ) {
					$term_ids[] = $tid;
				}
			}
			$term_ids = array_values( array_unique( array_map( 'intval', $term_ids ) ) );
		}

		if ( ! empty( $term_ids ) ) {
			update_post_meta( $pid, self::META_SERIES_TERMS, wp_json_encode( $term_ids ) );
			update_post_meta( $pid, self::META_SERIES_TERM, $term_ids[0] );
		} else {
			delete_post_meta( $pid, self::META_SERIES_TERMS );
			delete_post_meta( $pid, self::META_SERIES_TERM );
		}

		$event_body = isset( $_POST['twec_pass_event_ids'] ) ? sanitize_textarea_field( wp_unslash( $_POST['twec_pass_event_ids'] ) ) : '';
		$event_ids  = self::parse_event_id_list( $event_body );
		if ( ! empty( $event_ids ) ) {
			update_post_meta( $pid, self::META_EVENT_IDS, wp_json_encode( $event_ids ) );
		} else {
			delete_post_meta( $pid, self::META_EVENT_IDS );
		}
	}

	/**
	 * Parse a textarea or comma-separated list into unique `twec_event` IDs.
	 *
	 * @param string $raw Text from textarea.
	 * @return array<int>
	 */
	private static function parse_event_id_list( $raw ) {
		$raw = is_string( $raw ) ? $raw : '';
		$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		$out = array();
		foreach ( preg_split( '/[\n,]+/', $raw ) as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			$eid = absint( $part );
			if ( $eid < 1 ) {
				continue;
			}
			if ( 'twec_event' !== get_post_type( $eid ) ) {
				continue;
			}
			$out[] = $eid;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Series term IDs configured on a product (variation or parent).
	 *
	 * @param int $product_id     Product or variation ID.
	 * @param int $parent_id      Parent product when $product_id is a variation.
	 * @return array<int>
	 */
	private static function get_product_series_term_ids( $product_id, $parent_id = 0 ) {
		$product_id = (int) $product_id;
		$parent_id  = (int) $parent_id;
		$from_json  = static function ( $pid ) {
			$pid = (int) $pid;
			if ( $pid < 1 ) {
				return array();
			}
			$raw = get_post_meta( $pid, self::META_SERIES_TERMS, true );
			if ( ! is_string( $raw ) || '' === $raw ) {
				return array();
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return array();
			}
			$out = array();
			foreach ( $decoded as $tid ) {
				$t = (int) $tid;
				if ( $t > 0 ) {
					$out[] = $t;
				}
			}
			return array_values( array_unique( $out ) );
		};

		$terms = $from_json( $product_id );
		if ( empty( $terms ) && $parent_id > 0 ) {
			$terms = $from_json( $parent_id );
		}
		if ( empty( $terms ) ) {
			$single = (int) get_post_meta( $product_id, self::META_SERIES_TERM, true );
			if ( $single < 1 && $parent_id > 0 ) {
				$single = (int) get_post_meta( $parent_id, self::META_SERIES_TERM, true );
			}
			if ( $single > 0 ) {
				$terms = array( $single );
			}
		}
		return $terms;
	}

	/**
	 * Event IDs configured on a product (variation or parent).
	 *
	 * @param int $product_id Product or variation ID.
	 * @param int $parent_id  Parent product when $product_id is a variation.
	 * @return array<int>
	 */
	private static function get_product_event_ids( $product_id, $parent_id = 0 ) {
		$product_id = (int) $product_id;
		$parent_id  = (int) $parent_id;
		$parse      = static function ( $pid ) {
			$pid = (int) $pid;
			if ( $pid < 1 ) {
				return array();
			}
			$raw = get_post_meta( $pid, self::META_EVENT_IDS, true );
			if ( ! is_string( $raw ) || '' === $raw ) {
				return array();
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return array();
			}
			$out = array();
			foreach ( $decoded as $eid ) {
				$e = (int) $eid;
				if ( $e > 0 && 'twec_event' === get_post_type( $e ) ) {
					$out[] = $e;
				}
			}
			return array_values( array_unique( $out ) );
		};

		$ids = $parse( $product_id );
		if ( empty( $ids ) && $parent_id > 0 ) {
			$ids = $parse( $parent_id );
		}
		return $ids;
	}

	/**
	 * Whether a snapshot has any grants.
	 *
	 * @param array $snapshot Normalized snapshot.
	 * @return bool
	 */
	private static function snapshot_has_grants( array $snapshot ) {
		$snap = self::normalize_order_snapshot( $snapshot );
		return ( ! empty( $snap['terms'] ) || ! empty( $snap['events'] ) );
	}

	/**
	 * Normalize order snapshot for grants/revoke (supports legacy flat term map).
	 *
	 * @param array $raw Raw meta array.
	 * @return array Normalized snapshot with `terms` and `events` maps.
	 */
	private static function normalize_order_snapshot( $raw ) {
		$out = array(
			'terms'  => array(),
			'events' => array(),
		);
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		if ( isset( $raw['terms'] ) && is_array( $raw['terms'] ) ) {
			foreach ( $raw['terms'] as $k => $qty ) {
				$k   = (string) $k;
				$qty = max( 0, (int) $qty );
				if ( (int) $k > 0 && $qty > 0 ) {
					$out['terms'][ (string) ( (int) $k ) ] = $qty;
				}
			}
		}
		if ( isset( $raw['events'] ) && is_array( $raw['events'] ) ) {
			foreach ( $raw['events'] as $k => $qty ) {
				$k   = (string) $k;
				$qty = max( 0, (int) $qty );
				if ( (int) $k > 0 && $qty > 0 ) {
					$out['events'][ (string) ( (int) $k ) ] = $qty;
				}
			}
		}
		if ( empty( $out['terms'] ) && empty( $out['events'] ) && ! empty( $raw ) ) {
			$looks_legacy = ! isset( $raw['terms'] ) && ! isset( $raw['events'] );
			if ( $looks_legacy ) {
				foreach ( $raw as $k => $qty ) {
					if ( 'terms' === $k || 'events' === $k ) {
						continue;
					}
					$tid = (int) $k;
					$qty = max( 0, (int) $qty );
					if ( $tid > 0 && $qty > 0 ) {
						$out['terms'][ (string) $tid ] = $qty;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Apply pass grants exactly once after payment settles.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function maybe_apply_series_pass( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id < 1 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_meta' ) ) ) {
			return;
		}
		if ( 'yes' === (string) $order->get_meta( self::ORDER_META_READY ) ) {
			return;
		}

		$snapshot = self::summarize_series_pass_quantities_for_order( $order );
		if ( ! self::snapshot_has_grants( $snapshot ) ) {
			return;
		}

		$uid = (int) $order->get_user_id();
		if ( $uid < 1 ) {
			self::store_snapshot_meta( $order, $snapshot );
			if ( is_callable( array( $order, 'add_order_note' ) ) ) {
				$order->add_order_note( __( 'PlanIt series pass entitlement skipped — assign a WooCommerce customer account before checkout for automatic access.', 'planit-event-manager' ) );
			}
			return;
		}

		self::increment_user_entitlements( $uid, $snapshot );

		self::store_snapshot_meta( $order, $snapshot );
		$order->update_meta_data( self::ORDER_META_READY, 'yes' );
		$order->save();
	}

	/**
	 * Revoke entitlement snapshot when refunded/cancelled.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function maybe_revoke_series_pass( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id < 1 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) ) {
			return;
		}
		if ( 'yes' !== (string) $order->get_meta( self::ORDER_META_READY ) ) {
			return;
		}

		$snapshot_raw = $order->get_meta( self::ORDER_META_SNAPSHOT );
		if ( ! is_array( $snapshot_raw ) ) {
			return;
		}

		$uid = (int) $order->get_user_id();
		if ( $uid > 0 ) {
			self::decrement_user_entitlements( $uid, $snapshot_raw );
		}

		$order->update_meta_data( self::ORDER_META_READY, '' );
		$order->update_meta_data( self::ORDER_META_SNAPSHOT, array() );
		$order->save();
	}

	/**
	 * Persist a normalized grant snapshot on the WooCommerce order.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $snapshot Snapshot (normalized or raw).
	 * @return void
	 */
	private static function store_snapshot_meta( $order, array $snapshot ) {
		if ( ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}
		$norm  = self::normalize_order_snapshot( $snapshot );
		$clean = array(
			'terms'  => array(),
			'events' => array(),
		);
		foreach ( $norm['terms'] as $tid => $qty ) {
			$tid = (int) $tid;
			$qty = max( 0, (int) $qty );
			if ( $tid > 0 && $qty > 0 ) {
				$clean['terms'][ (string) $tid ] = $qty;
			}
		}
		foreach ( $norm['events'] as $eid => $qty ) {
			$eid = (int) $eid;
			$qty = max( 0, (int) $qty );
			if ( $eid > 0 && $qty > 0 ) {
				$clean['events'][ (string) $eid ] = $qty;
			}
		}
		$order->update_meta_data( self::ORDER_META_SNAPSHOT, $clean );
		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}

	/**
	 * Collect PlanIt pass quantities from order line items (including bundled children when available).
	 *
	 * @param WC_Order $order Order object.
	 * @return array Snapshot structure (terms/events).
	 */
	private static function summarize_series_pass_quantities_for_order( $order ) {
		$terms  = array();
		$events = array();
		if ( ! method_exists( $order, 'get_items' ) ) {
			return array(
				'terms'  => $terms,
				'events' => $events,
			);
		}
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}
			$product    = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
			$candidates = self::resolve_line_item_candidate_product_ids( $item, $product );
			$qty        = max( 0, (int) $item->get_quantity() );
			if ( $qty < 1 || empty( $candidates ) ) {
				continue;
			}
			$variation_id = (int) $item->get_variation_id();
			$product_id   = max( 0, (int) $item->get_product_id() );
			$merged_terms = array();
			$merged_ev    = array();
			foreach ( $candidates as $candidate ) {
				$candidate = (int) $candidate;
				if ( $candidate < 1 ) {
					continue;
				}
				$parent_for_meta = ( $variation_id > 0 && $candidate === $variation_id && $product_id > 0 ) ? $product_id : 0;
				$merged_terms    = array_merge( $merged_terms, self::get_product_series_term_ids( $candidate, $parent_for_meta ) );
				$merged_ev       = array_merge( $merged_ev, self::get_product_event_ids( $candidate, $parent_for_meta ) );
			}
			$merged_terms = array_values( array_unique( array_map( 'intval', $merged_terms ) ) );
			$merged_ev    = array_values( array_unique( array_map( 'intval', $merged_ev ) ) );
			foreach ( $merged_terms as $tid ) {
				$tid           = (int) $tid;
				$terms[ $tid ] = isset( $terms[ $tid ] ) ? $terms[ $tid ] + $qty : $qty;
			}
			foreach ( $merged_ev as $eid ) {
				$eid            = (int) $eid;
				$events[ $eid ] = isset( $events[ $eid ] ) ? $events[ $eid ] + $qty : $qty;
			}
		}
		return array(
			'terms'  => $terms,
			'events' => $events,
		);
	}

	/**
	 * Product IDs to inspect for pass meta (variation, parent, bundled children).
	 *
	 * @param WC_Order_Item_Product $item    Line item.
	 * @param WC_Product|null       $product Product instance.
	 * @return array<int>
	 */
	private static function resolve_line_item_candidate_product_ids( $item, $product ) {
		$ids = array();
		if ( is_object( $item ) ) {
			$variation_id = (int) $item->get_variation_id();
			$product_id   = max( 1, (int) $item->get_product_id() );
			$main         = $variation_id > 0 ? $variation_id : $product_id;
			if ( $main > 0 ) {
				$ids[] = $main;
			}
			if ( $variation_id > 0 && $product_id > 0 ) {
				$ids[] = $product_id;
			}
		}
		if ( $product && is_a( $product, 'WC_Product' ) && $product->is_type( 'bundle' ) ) {
			if ( is_callable( array( $product, 'get_bundled_items' ) ) ) {
				foreach ( $product->get_bundled_items() as $bundled_item ) {
					if ( is_object( $bundled_item ) && is_callable( array( $bundled_item, 'get_product_id' ) ) ) {
						$bid = (int) $bundled_item->get_product_id();
						if ( $bid > 0 ) {
							$ids[] = $bid;
						}
					}
				}
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		/**
		 * Add or replace candidate product IDs scanned for PlanIt pass meta on a Woo line item.
		 *
		 * @param array          $ids     Candidate product IDs.
		 * @param WC_Order_Item  $item    Order line item.
		 * @param WC_Product|null $product Product object.
		 */
		return apply_filters( 'twec_series_pass_line_item_product_ids', $ids, $item, $product );
	}

	/**
	 * Add term and event entitlements from an order snapshot.
	 *
	 * @param int   $uid User ID.
	 * @param array $snapshot Raw or normalized snapshot.
	 * @return void
	 */
	private static function increment_user_entitlements( $uid, array $snapshot ) {
		$snap = self::normalize_order_snapshot( $snapshot );
		if ( ! empty( $snap['terms'] ) ) {
			self::increment_term_counts( $uid, $snap['terms'] );
		}
		if ( ! empty( $snap['events'] ) ) {
			self::increment_event_counts( $uid, $snap['events'] );
		}
	}

	/**
	 * Remove term and event entitlements using a stored order snapshot.
	 *
	 * @param int   $uid User ID.
	 * @param array $snapshot Raw snapshot from order meta.
	 * @return void
	 */
	private static function decrement_user_entitlements( $uid, array $snapshot ) {
		$snap = self::normalize_order_snapshot( $snapshot );
		if ( ! empty( $snap['terms'] ) ) {
			self::decrement_term_counts( $uid, $snap['terms'] );
		}
		if ( ! empty( $snap['events'] ) ) {
			self::decrement_event_counts( $uid, $snap['events'] );
		}
	}

	/**
	 * Increment series term counts for a user.
	 *
	 * @param int   $uid User ID.
	 * @param array $quantities Term ID => quantity.
	 * @return void
	 */
	private static function increment_term_counts( $uid, array $quantities ) {
		$counts = self::get_counts_map( $uid );
		foreach ( $quantities as $term_id => $qty ) {
			$t = (int) $term_id;
			$c = max( 0, (int) $qty );
			if ( $t < 1 || $c < 1 ) {
				continue;
			}
			if ( isset( $counts[ $t ] ) ) {
				$counts[ $t ] += $c;
			} else {
				$counts[ $t ] = $c;
			}
		}
		update_user_meta( $uid, self::USER_META_COUNTS, wp_json_encode( $counts ) );
	}

	/**
	 * Decrement series term counts for a user.
	 *
	 * @param int   $uid User ID.
	 * @param array $quantities Term ID => quantity.
	 * @return void
	 */
	private static function decrement_term_counts( $uid, array $quantities ) {
		$counts = self::get_counts_map( $uid );
		foreach ( $quantities as $term_id => $qty ) {
			$t = (int) $term_id;
			$c = max( 0, (int) $qty );
			if ( $t < 1 || $c < 1 ) {
				continue;
			}
			if ( ! isset( $counts[ $t ] ) ) {
				continue;
			}
			$counts[ $t ] -= $c;
			if ( $counts[ $t ] < 1 ) {
				unset( $counts[ $t ] );
			}
		}
		update_user_meta( $uid, self::USER_META_COUNTS, wp_json_encode( $counts ) );
	}

	/**
	 * Increment explicit event entitlement counts for a user.
	 *
	 * @param int   $uid User ID.
	 * @param array $quantities Event post ID => quantity.
	 * @return void
	 */
	private static function increment_event_counts( $uid, array $quantities ) {
		$counts = self::get_event_counts_map( $uid );
		foreach ( $quantities as $eid => $qty ) {
			$e = (int) $eid;
			$c = max( 0, (int) $qty );
			if ( $e < 1 || $c < 1 ) {
				continue;
			}
			if ( isset( $counts[ $e ] ) ) {
				$counts[ $e ] += $c;
			} else {
				$counts[ $e ] = $c;
			}
		}
		update_user_meta( $uid, self::USER_META_EVENT_COUNTS, wp_json_encode( $counts ) );
	}

	/**
	 * Decrement explicit event entitlement counts for a user.
	 *
	 * @param int   $uid User ID.
	 * @param array $quantities Event post ID => quantity.
	 * @return void
	 */
	private static function decrement_event_counts( $uid, array $quantities ) {
		$counts = self::get_event_counts_map( $uid );
		foreach ( $quantities as $eid => $qty ) {
			$e = (int) $eid;
			$c = max( 0, (int) $qty );
			if ( $e < 1 || $c < 1 ) {
				continue;
			}
			if ( ! isset( $counts[ $e ] ) ) {
				continue;
			}
			$counts[ $e ] -= $c;
			if ( $counts[ $e ] < 1 ) {
				unset( $counts[ $e ] );
			}
		}
		update_user_meta( $uid, self::USER_META_EVENT_COUNTS, wp_json_encode( $counts ) );
	}

	/**
	 * Decode stored series term counts safely.
	 *
	 * @param int $uid User ID.
	 * @return array<int,int>
	 */
	private static function get_counts_map( $uid ) {
		$uid = (int) $uid;
		if ( $uid < 1 ) {
			return array();
		}
		$raw = get_user_meta( $uid, self::USER_META_COUNTS, true );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$counts  = is_array( $decoded ) ? array_map( 'intval', $decoded ) : array();
		} elseif ( is_array( $raw ) ) {
			$counts = array_map( 'intval', $raw );
		} else {
			$counts = array();
		}
		$counts = array_filter(
			array_map(
				function ( $qty ) {
					return max( 0, (int) $qty );
				},
				$counts
			),
			function ( $qty ) {
				return $qty > 0;
			}
		);

		return $counts;
	}

	/**
	 * Decode stored per-event entitlement counts.
	 *
	 * @param int $uid User ID.
	 * @return array<int,int>
	 */
	private static function get_event_counts_map( $uid ) {
		$uid = (int) $uid;
		if ( $uid < 1 ) {
			return array();
		}
		$raw = get_user_meta( $uid, self::USER_META_EVENT_COUNTS, true );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$counts  = is_array( $decoded ) ? array_map( 'intval', $decoded ) : array();
		} elseif ( is_array( $raw ) ) {
			$counts = array_map( 'intval', $raw );
		} else {
			$counts = array();
		}
		$counts = array_filter(
			array_map(
				function ( $qty ) {
					return max( 0, (int) $qty );
				},
				$counts
			),
			function ( $qty ) {
				return $qty > 0;
			}
		);
		return $counts;
	}

	/**
	 * Whether the user has series or explicit pack access to this event.
	 *
	 * @param int   $event_id Event post ID.
	 * @param mixed $user_id  User ID or null for current user.
	 * @return bool
	 */
	public static function user_has_pack_access( $event_id, $user_id = null ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			return true;
		}

		$uid = null === $user_id ? get_current_user_id() : (int) $user_id;

		if ( $uid > 0 ) {
			$event_counts = self::get_event_counts_map( $uid );
			if ( ! empty( $event_counts[ $event_id ] ) ) {
				return true;
			}
		}

		if ( ! taxonomy_exists( 'twec_event_series' ) ) {
			return true;
		}

		$terms = wp_get_post_terms( $event_id, 'twec_event_series', array( 'fields' => 'ids' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return true;
		}

		if ( $uid < 1 ) {
			return false;
		}

		$counts = self::get_counts_map( $uid );
		foreach ( $terms as $tid ) {
			$tid = (int) $tid;
			if ( $tid < 1 ) {
				continue;
			}
			if ( ! empty( $counts[ $tid ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether user still has pass coverage for series term IDs on an event (alias of user_has_pack_access).
	 *
	 * @param int   $event_id Event post ID.
	 * @param mixed $user_id  User ID or null for current user.
	 * @return bool
	 */
	public static function user_has_series_access( $event_id, $user_id = null ) {
		return self::user_has_pack_access( $event_id, $user_id );
	}
}
