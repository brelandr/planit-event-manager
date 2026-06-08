<?php
/**
 * WordPress Privacy Tools: personal data export and erasure (free package).
 *
 * When PlanIt Event Manager Premium is active, the free plugin does not load this runtime;
 * Premium registers its own {@see TWEC_Privacy} callbacks.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers exporters/erasers for RSVP-related event meta, optional extended check-in meta,
 * optional WooCommerce ticket slots (when that API exists), and payment log rows.
 */
class TWEC_Privacy {

	public const EXPORTER_EVENTS = 'planit-event-manager-events';

	public const EXPORTER_PAYMENTS = 'planit-event-manager-payments';

	public const ERASER_ID = 'planit-event-manager';

	/**
	 * Register privacy exporters, erasers, and policy suggestion hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'suggest_privacy_policy_text' ) );
	}

	/**
	 * Suggested Privacy Policy section (site owners can edit).
	 *
	 * @return void
	 */
	public static function suggest_privacy_policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$title   = __( 'PlanIt Event Manager', 'planit-event-manager' );
		$content = '<p>' . esc_html__( 'If you RSVP or pay for event promotion through this site, we may store your email (and name, if provided) on event attendee lists, waitlists, and related meta. Reminder preferences and optional WooCommerce ticket links can also create records tied to your email. You can request an export or erasure via this site’s privacy tools, subject to legal or accounting retention rules for payments.', 'planit-event-manager' ) . '</p>';

		wp_add_privacy_policy_content( $title, $content );
	}

	/**
	 * Append PlanIt personal data exporters.
	 *
	 * @param array<string,array<string,mixed>> $exporters Exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_exporters( array $exporters ) {
		$exporters[ self::EXPORTER_EVENTS ]   = array(
			'exporter_friendly_name' => __( 'PlanIt — Events, RSVPs & check-in', 'planit-event-manager' ),
			'callback'               => array( __CLASS__, 'export_event_data' ),
		);
		$exporters[ self::EXPORTER_PAYMENTS ] = array(
			'exporter_friendly_name' => __( 'PlanIt — Promotion payments (Stripe/PayPal log)', 'planit-event-manager' ),
			'callback'               => array( __CLASS__, 'export_payment_log' ),
		);
		return $exporters;
	}

	/**
	 * Append PlanIt personal data erasers.
	 *
	 * @param array<string,array<string,mixed>> $erasers Erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_erasers( array $erasers ) {
		$erasers[ self::ERASER_ID ] = array(
			'eraser_friendly_name' => __( 'PlanIt Event Manager', 'planit-event-manager' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Optional TWEC_Premium_Pillars class constant (Premium extends this file with more constants).
	 *
	 * @param string $name Constant short name (e.g. RSVP_CHECKINS_META).
	 * @return string|null Meta key string or null if missing.
	 */
	private static function pillars_meta_key( $name ) {
		if ( ! class_exists( 'TWEC_Premium_Pillars', false ) ) {
			return null;
		}
		try {
			$r = new ReflectionClass( 'TWEC_Premium_Pillars' );
		} catch ( \ReflectionException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Missing class is fine.
			return null;
		}
		if ( ! $r->hasConstant( $name ) ) {
			return null;
		}
		$v = $r->getConstant( $name );
		return is_string( $v ) && '' !== $v ? $v : null;
	}

	/**
	 * Normalize email for comparisons.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function normalize_email( $email ) {
		return strtolower( trim( sanitize_email( (string) $email ) ) );
	}

	/**
	 * Export RSVP, reminders, optional extended meta, and Woo slot data for an email.
	 *
	 * @param string   $email_address Requested email.
	 * @param int|null $page          Page (1-based).
	 * @return array{data: array<int,array<string,mixed>>, done: bool}
	 */
	public static function export_event_data( $email_address, $page ) {
		$email_address = sanitize_email( (string) $email_address );
		if ( ! is_email( $email_address ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$norm     = self::normalize_email( $email_address );
		$page     = max( 1, (int) $page );
		$per_page = (int) apply_filters( 'twec_privacy_export_events_per_page', 25 );
		if ( $per_page < 1 ) {
			$per_page = 25;
		}

		$q = new WP_Query(
			array(
				'post_type'              => 'twec_event',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$ids       = is_array( $q->posts ) ? array_map( 'absint', $q->posts ) : array();
		$max_pages = (int) $q->max_num_pages;
		$done      = $page >= max( 1, $max_pages );
		$out       = array();

		foreach ( $ids as $event_id ) {
			$group = self::build_event_export_item( $event_id, $norm, $email_address );
			if ( null !== $group ) {
				$out[] = $group;
			}
		}

		return array(
			'data' => $out,
			'done' => $done,
		);
	}

	/**
	 * Build a single export group for one event, if data exists for the email.
	 *
	 * @param int    $event_id      Event ID.
	 * @param string $norm          Normalized email.
	 * @param string $email_address Original email for display.
	 * @return array<string,mixed>|null
	 */
	private static function build_event_export_item( $event_id, $norm, $email_address ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			return null;
		}

		$key_emails = TWEC_Premium_Pillars::RSVP_EMAILS_META;
		$list       = get_post_meta( $event_id, $key_emails, true );
		$list       = is_array( $list ) ? $list : array();
		$on_list    = false;
		foreach ( $list as $em ) {
			if ( self::normalize_email( (string) $em ) === $norm ) {
				$on_list = true;
				break;
			}
		}

		$wl_rows = get_post_meta( $event_id, TWEC_Premium_Pillars::RSVP_WAITLIST_META, true );
		$wl_rows = is_array( $wl_rows ) ? $wl_rows : array();
		$wl_hit  = null;
		foreach ( $wl_rows as $row ) {
			if ( is_array( $row ) && isset( $row['email'] ) && self::normalize_email( (string) $row['email'] ) === $norm ) {
				$wl_hit = $row;
				break;
			}
		}

		$phit = null;
		$pkey = self::pillars_meta_key( 'RSVP_PENDING_CLAIMS_META' );
		if ( null !== $pkey ) {
			$pend = get_post_meta( $event_id, $pkey, true );
			$pend = is_array( $pend ) ? $pend : array();
			foreach ( $pend as $row ) {
				if ( is_array( $row ) && ! empty( $row['email'] ) && self::normalize_email( (string) $row['email'] ) === $norm ) {
					$phit = $row;
					break;
				}
			}
		}

		$my_ck = array();
		$ckey  = self::pillars_meta_key( 'RSVP_CHECKINS_META' );
		if ( null !== $ckey ) {
			$ck_rows = get_post_meta( $event_id, $ckey, true );
			$ck_rows = is_array( $ck_rows ) ? $ck_rows : array();
			foreach ( $ck_rows as $row ) {
				if ( is_array( $row ) && isset( $row['email'] ) && self::normalize_email( (string) $row['email'] ) === $norm ) {
					$my_ck[] = $row;
				}
			}
		}

		$woo_slots = array();
		if ( class_exists( 'TWEC_WooCommerce', false ) && is_callable( array( 'TWEC_WooCommerce', 'get_ticket_checkin_slots' ) ) ) {
			foreach ( TWEC_WooCommerce::get_ticket_checkin_slots( $event_id ) as $slot_row ) {
				if ( is_array( $slot_row ) && isset( $slot_row['email'] ) && self::normalize_email( (string) $slot_row['email'] ) === $norm ) {
					$woo_slots[] = $slot_row;
				}
			}
		}

		$h_rem = '';
		if ( class_exists( 'TWEC_Reminders', false ) && is_callable( array( 'TWEC_Reminders', 'email_hash' ) ) ) {
			$h_rem = (string) TWEC_Reminders::email_hash( $email_address );
		} else {
			$h_rem = md5( $norm );
		}

		$opt_map   = get_post_meta( $event_id, TWEC_Reminders::META_OPTIN, true );
		$opt_map   = is_array( $opt_map ) ? $opt_map : array();
		$opt_label = __( 'Unknown', 'planit-event-manager' );
		if ( array_key_exists( $h_rem, $opt_map ) ) {
			$opt_val   = (string) $opt_map[ $h_rem ];
			$opt_label = ( '1' === $opt_val ) ? __( 'Opted in', 'planit-event-manager' ) : __( 'Opted out', 'planit-event-manager' );
		}

		$sent_keys = array();
		$sent      = get_post_meta( $event_id, TWEC_Reminders::META_SENT, true );
		$sent      = is_array( $sent ) ? $sent : array();
		$suffix    = '|' . $h_rem;
		$suf_len   = strlen( $suffix );
		foreach ( array_keys( $sent ) as $sk ) {
			if ( ! is_string( $sk ) ) {
				continue;
			}
			$slen = strlen( $sk );
			if ( $slen >= $suf_len && substr( $sk, -$suf_len ) === $suffix ) {
				$sent_keys[] = $sk;
			}
		}

		$name = '';
		foreach ( $list as $em ) {
			if ( self::normalize_email( (string) $em ) === $norm ) {
				$name = (string) get_post_meta( $event_id, '_twec_rsvp_names_' . md5( (string) $em ), true );
				break;
			}
		}

		if ( ! $on_list && null === $wl_hit && null === $phit && empty( $my_ck ) && empty( $woo_slots ) && '' === $name && ! array_key_exists( $h_rem, $opt_map ) && empty( $sent_keys ) ) {
			return null;
		}

		$data_rows = array();

		$title = get_the_title( $event_id );
		$title = is_string( $title ) ? wp_strip_all_tags( $title ) : '';

		$data_rows[] = array(
			'name'  => __( 'Event', 'planit-event-manager' ),
			'value' => $title . ' (ID ' . (string) $event_id . ')',
		);

		if ( $on_list || '' !== $name ) {
			$data_rows[] = array(
				'name'  => __( 'RSVP status', 'planit-event-manager' ),
				'value' => $on_list ? __( 'Confirmed list', 'planit-event-manager' ) : __( 'Not on confirmed list', 'planit-event-manager' ),
			);
			$data_rows[] = array(
				'name'  => __( 'Email', 'planit-event-manager' ),
				'value' => $email_address,
			);
			if ( '' !== trim( $name ) ) {
				$data_rows[] = array(
					'name'  => __( 'Name (if provided)', 'planit-event-manager' ),
					'value' => $name,
				);
			}
		}

		if ( null !== $wl_hit ) {
			$data_rows[] = array(
				'name'  => __( 'Waitlist', 'planit-event-manager' ),
				'value' => wp_json_encode(
					array(
						'email' => isset( $wl_hit['email'] ) ? (string) $wl_hit['email'] : '',
						'name'  => isset( $wl_hit['name'] ) ? (string) $wl_hit['name'] : '',
					)
				),
			);
		}

		if ( null !== $phit ) {
			$export_p = $phit;
			unset( $export_p['plain'], $export_p['token'] );
			$data_rows[] = array(
				'name'  => __( 'Pending RSVP claim', 'planit-event-manager' ),
				'value' => wp_json_encode( $export_p ),
			);
		}

		if ( array_key_exists( $h_rem, $opt_map ) ) {
			$data_rows[] = array(
				'name'  => __( 'Event reminder preference', 'planit-event-manager' ),
				'value' => $opt_label,
			);
		}

		if ( ! empty( $sent_keys ) ) {
			$data_rows[] = array(
				'name'  => __( 'Reminder sends recorded (dedupe keys)', 'planit-event-manager' ),
				'value' => implode( ', ', $sent_keys ),
			);
		}

		if ( ! empty( $my_ck ) ) {
			$brief = array();
			foreach ( $my_ck as $r ) {
				if ( ! is_array( $r ) ) {
					continue;
				}
				$brief[] = array(
					'checked_in_at_gmt' => isset( $r['checked_in_at_gmt'] ) ? gmdate( 'c', (int) $r['checked_in_at_gmt'] ) : '',
					'source'            => isset( $r['source'] ) ? (string) $r['source'] : '',
					'order_id'          => isset( $r['order_id'] ) ? (int) $r['order_id'] : 0,
					'item_id'           => isset( $r['item_id'] ) ? (int) $r['item_id'] : 0,
					'slot'              => isset( $r['slot'] ) ? (int) $r['slot'] : 0,
				);
			}
			$data_rows[] = array(
				'name'  => __( 'Check-in log entries', 'planit-event-manager' ),
				'value' => wp_json_encode( $brief ),
			);
		}

		if ( ! empty( $woo_slots ) ) {
			$data_rows[] = array(
				'name'  => __( 'WooCommerce ticket slots', 'planit-event-manager' ),
				'value' => wp_json_encode( $woo_slots ),
			);
		}

		return array(
			'group_id'    => self::EXPORTER_EVENTS,
			'group_label' => __( 'PlanIt — Events, RSVPs & check-in', 'planit-event-manager' ),
			'item_id'     => 'twec-event-' . (string) $event_id,
			'data'        => $data_rows,
		);
	}

	/**
	 * Export Stripe/PayPal promotion payment log rows.
	 *
	 * @param string   $email_address Email.
	 * @param int|null $page          Page.
	 * @return array{data: array<int,array<string,mixed>>, done: bool}
	 */
	public static function export_payment_log( $email_address, $page ) {
		$email_address = sanitize_email( (string) $email_address );
		if ( ! is_email( $email_address ) || ! class_exists( 'TWEC_Payment_Log', false ) || ! is_callable( array( 'TWEC_Payment_Log', 'get_rows_for_buyer_email_paged' ) ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$page     = max( 1, (int) $page );
		$per_page = (int) apply_filters( 'twec_privacy_export_payments_per_page', 50 );
		if ( $per_page < 1 ) {
			$per_page = 50;
		}

		$batch = TWEC_Payment_Log::get_rows_for_buyer_email_paged( $email_address, $page, $per_page );
		$rows  = isset( $batch['items'] ) && is_array( $batch['items'] ) ? $batch['items'] : array();
		$total = isset( $batch['total'] ) ? (int) $batch['total'] : count( $rows );
		$out   = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$eid    = isset( $row['event_id'] ) ? (int) $row['event_id'] : 0;
			$etitle = $eid > 0 ? get_the_title( $eid ) : '';
			$etitle = is_string( $etitle ) ? wp_strip_all_tags( $etitle ) : '';

			$data_rows = array(
				array(
					'name'  => __( 'Payment row ID', 'planit-event-manager' ),
					'value' => isset( $row['id'] ) ? (string) $row['id'] : '',
				),
				array(
					'name'  => __( 'Event', 'planit-event-manager' ),
					'value' => $etitle . ( $eid > 0 ? ' (ID ' . (string) $eid . ')' : '' ),
				),
				array(
					'name'  => __( 'Gateway', 'planit-event-manager' ),
					'value' => isset( $row['gateway'] ) ? (string) $row['gateway'] : '',
				),
				array(
					'name'  => __( 'Gateway reference', 'planit-event-manager' ),
					'value' => isset( $row['gateway_ref'] ) ? (string) $row['gateway_ref'] : '',
				),
				array(
					'name'  => __( 'Buyer email', 'planit-event-manager' ),
					'value' => isset( $row['buyer_email'] ) ? (string) $row['buyer_email'] : '',
				),
				array(
					'name'  => __( 'Buyer name', 'planit-event-manager' ),
					'value' => isset( $row['buyer_name'] ) ? (string) $row['buyer_name'] : '',
				),
				array(
					'name'  => __( 'Paid at (GMT)', 'planit-event-manager' ),
					'value' => isset( $row['paid_at_gmt'] ) ? (string) $row['paid_at_gmt'] : '',
				),
				array(
					'name'  => __( 'Amount (minor units) / currency', 'planit-event-manager' ),
					'value' => ( isset( $row['amount_minor'] ) ? (string) (int) $row['amount_minor'] : '0' ) . ' ' . ( isset( $row['currency'] ) ? (string) $row['currency'] : '' ),
				),
			);

			$out[] = array(
				'group_id'    => self::EXPORTER_PAYMENTS,
				'group_label' => __( 'PlanIt — Promotion payments (Stripe/PayPal log)', 'planit-event-manager' ),
				'item_id'     => 'twec-payment-' . ( isset( $row['id'] ) ? (string) (int) $row['id'] : '0' ),
				'data'        => $data_rows,
			);
		}

		return array(
			'data' => $out,
			'done' => ( $page * $per_page ) >= $total,
		);
	}

	/**
	 * Erase PlanIt personal data for an email (paged over events).
	 *
	 * @param string   $email_address Email.
	 * @param int|null $page          Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase_personal_data( $email_address, $page ) {
		$email_address = sanitize_email( (string) $email_address );
		$messages      = array();
		$removed_any   = false;
		$retained_any  = false;

		if ( ! is_email( $email_address ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		if ( ! (bool) apply_filters( 'twec_privacy_erase_user_data', true, $email_address ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => true,
				'messages'       => array( __( 'PlanIt data erasure was skipped by a filter.', 'planit-event-manager' ) ),
				'done'           => true,
			);
		}

		$norm     = self::normalize_email( $email_address );
		$page     = max( 1, (int) $page );
		$per_page = (int) apply_filters( 'twec_privacy_erase_events_per_page', 25 );
		if ( $per_page < 1 ) {
			$per_page = 25;
		}

		$q = new WP_Query(
			array(
				'post_type'              => 'twec_event',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$ids         = is_array( $q->posts ) ? array_map( 'absint', $q->posts ) : array();
		$max_pages   = (int) $q->max_num_pages;
		$events_done = $page >= max( 1, $max_pages );
		foreach ( $ids as $event_id ) {
			$er = self::erase_event_data_for_email( $event_id, $norm, $email_address );
			if ( $er ) {
				$removed_any = true;
			}
		}

		if ( $events_done && class_exists( 'TWEC_Payment_Log', false ) && is_callable( array( 'TWEC_Payment_Log', 'delete_rows_by_buyer_email' ) ) ) {
			if ( (bool) apply_filters( 'twec_privacy_erase_payment_log_rows', true, $email_address ) ) {
				$del = (int) TWEC_Payment_Log::delete_rows_by_buyer_email( $email_address );
				if ( $del > 0 ) {
					$removed_any = true;
					$messages[]  = sprintf(
						/* translators: %d: rows deleted */
						__( 'Removed %d promotion payment log row(s) tied to this email.', 'planit-event-manager' ),
						$del
					);
				}
			} else {
				$retained_any = true;
				$messages[]   = __( 'Promotion payment log rows were retained (filter). Your gateway may still hold transaction records.', 'planit-event-manager' );
			}
		}

		return array(
			'items_removed'  => $removed_any,
			'items_retained' => $retained_any,
			'messages'       => $messages,
			'done'           => $events_done,
		);
	}

	/**
	 * Erase one event’s PlanIt data matching an email.
	 *
	 * @param int    $event_id      Event ID.
	 * @param string $norm          Normalized email.
	 * @param string $email_address Raw email for hash helpers.
	 * @return bool Whether something was removed/updated.
	 */
	private static function erase_event_data_for_email( $event_id, $norm, $email_address ) {
		$event_id = (int) $event_id;
		if ( $event_id < 1 ) {
			return false;
		}

		$changed = false;

		$key_emails = TWEC_Premium_Pillars::RSVP_EMAILS_META;
		$list       = get_post_meta( $event_id, $key_emails, true );
		$list       = is_array( $list ) ? array_values( $list ) : array();
		$new_list   = array();
		$was_on     = false;
		foreach ( $list as $em ) {
			if ( self::normalize_email( (string) $em ) === $norm ) {
				$was_on = true;
				delete_post_meta( $event_id, '_twec_rsvp_names_' . md5( (string) $em ) );
				$changed = true;
				continue;
			}
			$new_list[] = (string) $em;
		}
		if ( $was_on ) {
			update_post_meta( $event_id, $key_emails, $new_list );
			if ( is_callable( array( 'TWEC_Premium_Pillars', 'maybe_promote_waitlist_for_event' ) ) ) {
				TWEC_Premium_Pillars::maybe_promote_waitlist_for_event( $event_id );
			}
		}

		$wl  = get_post_meta( $event_id, TWEC_Premium_Pillars::RSVP_WAITLIST_META, true );
		$wl  = is_array( $wl ) ? $wl : array();
		$nwl = array();
		foreach ( $wl as $row ) {
			if ( is_array( $row ) && isset( $row['email'] ) && self::normalize_email( (string) $row['email'] ) === $norm ) {
				$changed = true;
				continue;
			}
			$nwl[] = $row;
		}
		if ( count( $nwl ) !== count( $wl ) ) {
			if ( empty( $nwl ) ) {
				delete_post_meta( $event_id, TWEC_Premium_Pillars::RSVP_WAITLIST_META );
			} else {
				update_post_meta( $event_id, TWEC_Premium_Pillars::RSVP_WAITLIST_META, $nwl );
			}
		}

		$pkey = self::pillars_meta_key( 'RSVP_PENDING_CLAIMS_META' );
		if ( null !== $pkey ) {
			$pend  = get_post_meta( $event_id, $pkey, true );
			$pend  = is_array( $pend ) ? $pend : array();
			$npend = array();
			foreach ( $pend as $row ) {
				if ( is_array( $row ) && ! empty( $row['email'] ) && self::normalize_email( (string) $row['email'] ) === $norm ) {
					$changed = true;
					continue;
				}
				$npend[] = $row;
			}
			if ( count( $npend ) !== count( $pend ) ) {
				if ( empty( $npend ) ) {
					delete_post_meta( $event_id, $pkey );
				} else {
					update_post_meta( $event_id, $pkey, $npend );
				}
			}
		}

		$h = '';
		if ( class_exists( 'TWEC_Reminders', false ) && is_callable( array( 'TWEC_Reminders', 'email_hash' ) ) ) {
			$h = (string) TWEC_Reminders::email_hash( $email_address );
		} else {
			$h = md5( $norm );
		}
		$omap = get_post_meta( $event_id, TWEC_Reminders::META_OPTIN, true );
		$omap = is_array( $omap ) ? $omap : array();
		if ( array_key_exists( $h, $omap ) ) {
			unset( $omap[ $h ] );
			$changed = true;
			if ( empty( $omap ) ) {
				delete_post_meta( $event_id, TWEC_Reminders::META_OPTIN );
			} else {
				update_post_meta( $event_id, TWEC_Reminders::META_OPTIN, $omap );
			}
		}

		$sent  = get_post_meta( $event_id, TWEC_Reminders::META_SENT, true );
		$sent  = is_array( $sent ) ? $sent : array();
		$suf   = '|' . $h;
		$l     = strlen( $suf );
		$nsent = $sent;
		foreach ( array_keys( $sent ) as $sk ) {
			if ( ! is_string( $sk ) ) {
				continue;
			}
			if ( strlen( $sk ) >= $l && substr( $sk, -$l ) === $suf ) {
				unset( $nsent[ $sk ] );
			}
		}
		if ( count( $nsent ) !== count( $sent ) ) {
			$changed = true;
			if ( empty( $nsent ) ) {
				delete_post_meta( $event_id, TWEC_Reminders::META_SENT );
			} else {
				update_post_meta( $event_id, TWEC_Reminders::META_SENT, $nsent );
			}
		}

		$tok = get_post_meta( $event_id, TWEC_Premium_Pillars::RSVP_TOKEN_META, true );
		$tok = is_array( $tok ) ? $tok : array();
		$dig = md5( $norm );
		if ( isset( $tok[ $dig ] ) ) {
			unset( $tok[ $dig ] );
			$changed = true;
			if ( empty( $tok ) ) {
				delete_post_meta( $event_id, TWEC_Premium_Pillars::RSVP_TOKEN_META );
			} else {
				update_post_meta( $event_id, TWEC_Premium_Pillars::RSVP_TOKEN_META, $tok );
			}
		}

		$ckey = self::pillars_meta_key( 'RSVP_CHECKINS_META' );
		if ( null !== $ckey ) {
			$ck  = get_post_meta( $event_id, $ckey, true );
			$ck  = is_array( $ck ) ? $ck : array();
			$nck = array();
			foreach ( $ck as $row ) {
				if ( is_array( $row ) && isset( $row['email'] ) && self::normalize_email( (string) $row['email'] ) === $norm ) {
					$changed = true;
					continue;
				}
				$nck[] = $row;
			}
			if ( count( $nck ) !== count( $ck ) ) {
				if ( empty( $nck ) ) {
					delete_post_meta( $event_id, $ckey );
				} else {
					update_post_meta( $event_id, $ckey, $nck );
				}
			}
		}

		if ( class_exists( 'TWEC_WooCommerce', false ) && is_callable( array( 'TWEC_WooCommerce', 'remove_checkin_slots_for_billing_email' ) ) ) {
			$rw = (int) TWEC_WooCommerce::remove_checkin_slots_for_billing_email( $event_id, $email_address );
			if ( $rw > 0 ) {
				$changed = true;
			}
		}

		return $changed;
	}
}
