<?php
/**
 * Persisted payment rows (Stripe / PayPal) for per-event reporting.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom table + accessors for successful payments.
 */
class TWEC_Payment_Log {

	public const DB_VERSION     = 1;
	public const OPTION_VERSION = 'twec_db_version';

	/**
	 * @return string Full table name including prefix.
	 */
	public static function table_name() {
		global $wpdb;

		/**
		 * Filters the payment log table name (testing only).
		 *
		 * @param string $table Full prefixed name.
		 */
		$name = (string) apply_filters( 'twec_payment_log_table', $wpdb->prefix . 'twec_payment_log' );

		return $name;
	}

	/**
	 * @return void
	 */
	public static function maybe_install() {
		$installed = (int) get_option( self::OPTION_VERSION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}
		self::install();
		update_option( self::OPTION_VERSION, self::DB_VERSION );
	}

	/**
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			gateway varchar(20) NOT NULL DEFAULT '',
			gateway_ref varchar(255) NOT NULL DEFAULT '',
			amount_minor bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			currency varchar(12) NOT NULL DEFAULT '',
			paid_at_gmt datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			buyer_email varchar(255) NOT NULL DEFAULT '',
			buyer_name varchar(255) NOT NULL DEFAULT '',
			wp_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			raw_payload_json longtext NULL,
			created_gmt datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY twec_gateway_ref (gateway(20),gateway_ref(191)),
			KEY event_id (event_id),
			KEY buyer_email (buyer_email(100)),
			KEY paid_at_gmt (paid_at_gmt)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * @param int   $event_id Event post ID.
	 * @param array $session  Stripe Checkout Session object.
	 * @return int|false Insert ID or false.
	 */
	public static function insert_from_stripe_session( $event_id, array $session ) {
		$event_id = (int) $event_id;
		if ( $event_id <= 0 ) {
			return false;
		}

		$gateway_ref = isset( $session['id'] ) ? (string) $session['id'] : '';
		if ( '' === $gateway_ref ) {
			return false;
		}

		/**
		 * Short-circuit insert (e.g. testing).
		 *
		 * @param bool $insert   Default true.
		 * @param int  $event_id Event ID.
		 * @param array $session Stripe session.
		 */
		if ( ! apply_filters( 'twec_payment_log_should_insert', true, $event_id, $session ) ) {
			return false;
		}

		$amount_minor = isset( $session['amount_total'] ) ? (int) $session['amount_total'] : 0;
		$currency     = isset( $session['currency'] ) ? strtolower( preg_replace( '/[^a-z]/', '', (string) $session['currency'] ) ) : '';
		if ( strlen( $currency ) > 12 ) {
			$currency = substr( $currency, 0, 12 );
		}

		$email = '';
		$name  = '';
		if ( ! empty( $session['customer_details'] ) && is_array( $session['customer_details'] ) ) {
			$cd = $session['customer_details'];
			if ( ! empty( $cd['email'] ) ) {
				$email = sanitize_email( (string) $cd['email'] );
			}
			if ( ! empty( $cd['name'] ) ) {
				$name = sanitize_text_field( (string) $cd['name'] );
			}
		}

		$wp_user = 0;
		if ( ! empty( $session['metadata'] ) && is_array( $session['metadata'] ) && ! empty( $session['metadata']['twec_user_id'] ) ) {
			$wp_user = absint( $session['metadata']['twec_user_id'] );
		}

		$created = isset( $session['created'] ) ? (int) $session['created'] : 0;
		$paid_g  = gmdate( 'Y-m-d H:i:s', $created > 0 ? $created : time() );

		$payload_json = '';
		if ( apply_filters( 'twec_payment_log_store_raw_payload', false, 'stripe', $event_id ) ) {
			$payload_json = wp_json_encode( $session );
			if ( ! is_string( $payload_json ) ) {
				$payload_json = '';
			}
		}

		$row = array(
			'event_id'         => $event_id,
			'gateway'          => 'stripe',
			'gateway_ref'      => $gateway_ref,
			'amount_minor'     => max( 0, $amount_minor ),
			'currency'         => $currency,
			'paid_at_gmt'      => $paid_g,
			'buyer_email'      => $email,
			'buyer_name'       => $name,
			'wp_user_id'       => $wp_user,
			'raw_payload_json' => $payload_json,
			'created_gmt'      => current_time( 'mysql', true ),
		);

		/**
		 * Filters the row prior to insert.
		 *
		 * @param array $row Row.
		 * @param array $session Stripe session.
		 */
		$row = apply_filters( 'twec_payment_log_insert_data', $row, $session );
		if ( ! is_array( $row ) || empty( $row['gateway_ref'] ) ) {
			return false;
		}

		return self::insert_row( $row );
	}

	/**
	 * @param int   $event_id Event ID.
	 * @param array $resource PayPal capture resource.
	 * @return int|false
	 */
	public static function insert_from_paypal_capture( $event_id, array $resource ) {
		$event_id = (int) $event_id;
		if ( $event_id <= 0 ) {
			return false;
		}

		$gateway_ref = isset( $resource['id'] ) ? (string) $resource['id'] : '';
		if ( '' === $gateway_ref ) {
			return false;
		}

		if ( ! apply_filters( 'twec_payment_log_should_insert_paypal', true, $event_id, $resource ) ) {
			return false;
		}

		$currency = '';
		$minor    = 0;
		if ( ! empty( $resource['amount'] ) && is_array( $resource['amount'] ) ) {
			$am = $resource['amount'];
			if ( ! empty( $am['currency_code'] ) ) {
				$currency = strtolower( preg_replace( '/[^a-z]/', '', (string) $am['currency_code'] ) );
			}
			if ( isset( $am['value'] ) && is_numeric( $am['value'] ) ) {
				$major = (float) $am['value'];
				if ( self::currency_is_zero_decimal_paypal( $currency ) ) {
					$minor = (int) round( $major );
				} else {
					$minor = (int) round( $major * 100 );
				}
			}
		}

		$email = '';
		$name  = '';
		if ( ! empty( $resource['payer'] ) && is_array( $resource['payer'] ) ) {
			$p = $resource['payer'];
			if ( ! empty( $p['email_address'] ) ) {
				$email = sanitize_email( (string) $p['email_address'] );
			}
			if ( ! empty( $p['name'] ) && is_array( $p['name'] ) ) {
				$gn   = isset( $p['name']['given_name'] ) ? (string) $p['name']['given_name'] : '';
				$sn   = isset( $p['name']['surname'] ) ? (string) $p['name']['surname'] : '';
				$name = trim( $gn . ' ' . $sn );
				$name = sanitize_text_field( $name );
			}
		}

		$create = isset( $resource['create_time'] ) ? strtotime( (string) $resource['create_time'] ) : time();
		if ( $create <= 0 ) {
			$create = time();
		}
		$paid_g = gmdate( 'Y-m-d H:i:s', $create );

		$payload_json = '';
		if ( apply_filters( 'twec_payment_log_store_raw_payload', false, 'paypal', $event_id ) ) {
			$payload_json = wp_json_encode( $resource );
			if ( ! is_string( $payload_json ) ) {
				$payload_json = '';
			}
		}

		$row = array(
			'event_id'         => $event_id,
			'gateway'          => 'paypal',
			'gateway_ref'      => $gateway_ref,
			'amount_minor'     => max( 0, $minor ),
			'currency'         => $currency,
			'paid_at_gmt'      => $paid_g,
			'buyer_email'      => $email,
			'buyer_name'       => $name,
			'wp_user_id'       => 0,
			'raw_payload_json' => $payload_json,
			'created_gmt'      => current_time( 'mysql', true ),
		);

		$row = apply_filters( 'twec_payment_log_insert_data_paypal', $row, $resource );
		if ( ! is_array( $row ) || empty( $row['gateway_ref'] ) ) {
			return false;
		}

		return self::insert_row( $row );
	}

	/**
	 * @param string $currency PayPal currency code.
	 * @return bool
	 */
	private static function currency_is_zero_decimal_paypal( $currency ) {
		return self::currency_has_no_subunits( $currency );
	}

	/**
	 * Whether stored minor units are already in the smallest display unit (no /100), e.g. JPY.
	 *
	 * @param string $currency ISO code.
	 * @return bool
	 */
	private static function currency_has_no_subunits( $currency ) {
		$c = strtolower( preg_replace( '/[^a-z]/', '', (string) $currency ) );
		if ( strlen( $c ) > 12 ) {
			$c = substr( $c, 0, 12 );
		}

		$defaults = array( 'bif', 'clp', 'djf', 'gnf', 'huf', 'isk', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'twd', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );

		/**
		 * Filters whether a currency code uses zero decimal places (minor units = major display amount).
		 *
		 * @param bool   $is_zero Default from built-in ISO list.
		 * @param string $code    Normalized lowercase ISO code.
		 */
		return (bool) apply_filters( 'twec_currency_zero_decimal', in_array( $c, $defaults, true ), $c );
	}

	/**
	 * Format gateway minor units for admin display (zero-decimal vs cent-based).
	 *
	 * @param int    $minor    Amount in minor units as stored.
	 * @param string $currency ISO currency code.
	 * @return string Localized number string (no currency symbol).
	 */
	public static function format_amount_minor_for_display( $minor, $currency ) {
		$minor = (int) $minor;
		if ( self::currency_has_no_subunits( $currency ) ) {
			return (string) number_format_i18n( $minor, 0 );
		}

		return (string) number_format_i18n( $minor / 100, 2 );
	}

	/**
	 * @param array<string,mixed> $row Row.
	 * @return int|false
	 */
	private static function insert_row( array $row ) {
		global $wpdb;

		$table = self::table_name();
		$ok    = $wpdb->insert(
			$table,
			array(
				'event_id'         => (int) $row['event_id'],
				'gateway'          => sanitize_key( (string) $row['gateway'] ),
				'gateway_ref'      => substr( (string) $row['gateway_ref'], 0, 255 ),
				'amount_minor'     => (int) $row['amount_minor'],
				'currency'         => substr( (string) $row['currency'], 0, 12 ),
				'paid_at_gmt'      => (string) $row['paid_at_gmt'],
				'buyer_email'      => substr( (string) $row['buyer_email'], 0, 255 ),
				'buyer_name'       => substr( (string) $row['buyer_name'], 0, 255 ),
				'wp_user_id'       => (int) $row['wp_user_id'],
				'raw_payload_json' => is_string( $row['raw_payload_json'] ?? '' ) ? $row['raw_payload_json'] : '',
				'created_gmt'      => (string) $row['created_gmt'],
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $ok ) {
			$dup = stripos( (string) $wpdb->last_error, 'duplicate' );
			if ( false !== $dup ) {
				$existing = self::get_id_by_gateway_ref( (string) $row['gateway'], (string) $row['gateway_ref'] );
				return $existing > 0 ? $existing : false;
			}
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param string $gateway Gateway slug.
	 * @param string $ref     Gateway reference.
	 * @return int
	 */
	public static function get_id_by_gateway_ref( $gateway, $ref ) {
		global $wpdb;

		$table = self::table_name();
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE gateway = %s AND gateway_ref = %s LIMIT 1",
				sanitize_key( $gateway ),
				substr( (string) $ref, 0, 255 )
			)
		);

		return $id ? (int) $id : 0;
	}

	/**
	 * @param int $id Row ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}

		$table = self::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Query rows for list table.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array{items: array<int,array<string,mixed>>, total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;

		$table    = self::table_name();
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$page     = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['event_id'] ) ) {
			$where[]  = 'event_id = %d';
			$params[] = (int) $args['event_id'];
		}
		if ( ! empty( $args['gateway'] ) && in_array( $args['gateway'], array( 'stripe', 'paypal' ), true ) ) {
			$where[]  = 'gateway = %s';
			$params[] = (string) $args['gateway'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(buyer_email LIKE %s OR buyer_name LIKE %s OR gateway_ref LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['date_from_gmt'] ) ) {
			$where[]  = 'paid_at_gmt >= %s';
			$params[] = (string) $args['date_from_gmt'];
		}
		if ( ! empty( $args['date_to_gmt'] ) ) {
			$where[]  = 'paid_at_gmt <= %s';
			$params[] = (string) $args['date_to_gmt'];
		}

		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			$count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$params );
			$list_sql  = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY paid_at_gmt DESC LIMIT %d OFFSET %d",
				...array_merge( $params, array( $per_page, $offset ) )
			);
		} else {
			$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$list_sql  = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY paid_at_gmt DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built with prepare when placeholders exist.

		$items = $wpdb->get_results( $list_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Aggregate successful payment amounts by calendar month (GMT) for dashboards.
	 *
	 * Rows are summed in minor currency units per month/gateway/currency (split when mixed currencies).
	 *
	 * @param int $months Number of trailing months including the current calendar month (min 1, max 24).
	 * @return list<array{ym:string,gateway:string,currency:string,total_minor:int,count:int}>
	 */
	public static function get_monthly_totals_trailing_gmt( $months = 6 ) {
		global $wpdb;

		$months = (int) $months;
		if ( $months < 1 ) {
			$months = 1;
		}
		if ( $months > 24 ) {
			$months = 24;
		}

		$table       = self::table_name();
		$month_start = gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $months - 1 ) . ' months', strtotime( gmdate( 'Y-m-01 00:00:00' ) ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Static table identifier.
		$sql = $wpdb->prepare(
			"SELECT DATE_FORMAT(paid_at_gmt, '%%Y-%%m') AS ym,
				gateway,
				currency,
				SUM(amount_minor) AS total_minor,
				COUNT(*) AS cnt
				FROM {$table}
				WHERE paid_at_gmt >= %s
				GROUP BY ym, gateway, currency
				ORDER BY ym DESC",
			$month_start
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$out[] = array(
				'ym'          => isset( $r['ym'] ) ? (string) $r['ym'] : '',
				'gateway'     => isset( $r['gateway'] ) ? (string) $r['gateway'] : '',
				'currency'    => isset( $r['currency'] ) ? (string) $r['currency'] : '',
				'total_minor' => isset( $r['total_minor'] ) ? (int) $r['total_minor'] : 0,
				'count'       => isset( $r['cnt'] ) ? (int) $r['cnt'] : 0,
			);
		}
		return $out;
	}

	/**
	 * Rows in the promotion-payment log for a buyer email (case-insensitive), paginated.
	 *
	 * @param string $email    Buyer email.
	 * @param int    $page     1-based page.
	 * @param int    $per_page Page size.
	 * @return array{items: array<int,array<string,mixed>>, total: int}
	 */
	public static function get_rows_for_buyer_email_paged( $email, $page, $per_page ) {
		global $wpdb;

		$email    = sanitize_email( (string) $email );
		$page     = max( 1, (int) $page );
		$per_page = max( 1, (int) $per_page );
		$offset   = ( $page - 1 ) * $per_page;

		if ( '' === $email || ! is_email( $email ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$table = self::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom payment table; name from helper; dynamic SQL.
		$count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE LOWER(buyer_email) = LOWER(%s)", $email );
		$total     = (int) $wpdb->get_var( $count_sql );

		$list_sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE LOWER(buyer_email) = LOWER(%s) ORDER BY paid_at_gmt DESC LIMIT %d OFFSET %d",
			$email,
			$per_page,
			$offset
		);
		$items    = $wpdb->get_results( $list_sql, ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete promotion-payment log rows for a buyer email (case-insensitive).
	 *
	 * @param string $email Buyer email.
	 * @return int Rows deleted.
	 */
	public static function delete_rows_by_buyer_email( $email ) {
		global $wpdb;

		$email = sanitize_email( (string) $email );
		if ( '' === $email || ! is_email( $email ) ) {
			return 0;
		}

		$table = self::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom payment table; name from helper.
		$sql = $wpdb->prepare( "DELETE FROM {$table} WHERE LOWER(buyer_email) = LOWER(%s)", $email );
		$del = (int) $wpdb->query( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		return $del;
	}
}
