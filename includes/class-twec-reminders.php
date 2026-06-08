<?php
/**
 * Event start reminders (RSVP opt-in, wp_mail, dedupe, hourly sweep).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reminders: WP-Cron or Action Scheduler sweep, per-email opt-in, unsubscribe.
 */
class TWEC_Reminders {

	/**
	 * Post meta: md5( strtolower( email ) ) => '1'|'0'.
	 */
	public const META_OPTIN = '_twec_rsvp_reminder';

	/**
	 * Post meta: list of send keys to dedupe.
	 */
	public const META_SENT = '_twec_reminder_sent';

	/**
	 * Recurring callback hook.
	 */
	public const SWEEP_HOOK = 'twec_reminder_sweep';

	/**
	 * Action Scheduler group.
	 */
	public const AS_GROUP = 'twec';

	/**
	 * Unsubscribe query arg.
	 */
	public const QUERY_ARG = 'twec_reminder_unsub';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_unsubscribe_request' ), 5 );
		add_action( 'init', array( __CLASS__, 'sync_schedules' ), 25 );
		add_action( self::SWEEP_HOOK, array( __CLASS__, 'run_sweep' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_licensed() {
		return class_exists( 'TWEC_License' ) && method_exists( 'TWEC_License', 'is_licensed' ) && TWEC_License::is_licensed();
	}

	/**
	 * @return bool
	 */
	public static function reminders_enabled_in_settings() {
		return self::is_reminders_enabled_in_array( (array) get_option( 'twec_settings', array() ) );
	}

	/**
	 * @param array $settings twec_settings array.
	 * @return bool
	 */
	public static function is_reminders_enabled_in_array( $settings ) {
		$settings = (array) $settings;
		return ! empty( $settings['event_reminders_enabled'] ) && 'yes' === (string) $settings['event_reminders_enabled'];
	}

	/**
	 * @return int Hours, min 1, default 24.
	 */
	public static function get_offset_hours() {
		$st = (array) get_option( 'twec_settings', array() );
		$h  = isset( $st['reminder_offset_hours'] ) ? (int) $st['reminder_offset_hours'] : 24;
		if ( $h < 1 ) {
			$h = 1;
		}
		if ( $h > 168 ) {
			$h = 168;
		}
		return $h;
	}

	/**
	 * @return bool
	 */
	public static function should_run_sweep() {
		return self::is_licensed() && self::reminders_enabled_in_settings();
	}

	/**
	 * @param array $settings Merged or saved `twec_settings` (e.g. after sanitize) — used so cron sync matches unsaved form state.
	 * @return void
	 */
	public static function sync_for_settings( $settings ) {
		$settings = (array) $settings;
		if ( ! self::is_licensed() || ! self::is_reminders_enabled_in_array( $settings ) ) {
			self::unschedule_all();
			return;
		}
		self::do_schedule();
	}

	/**
	 * @param int    $event_id Event ID.
	 * @param string $email    Email.
	 * @param bool   $opt_in   Reminder opt-in.
	 * @return void
	 */
	public static function update_rsvp_optin( $event_id, $email, $opt_in ) {
		$event_id = (int) $event_id;
		if ( $event_id <= 0 || ! is_email( $email ) ) {
			return;
		}
		$h         = self::email_hash( $email );
		$map       = get_post_meta( $event_id, self::META_OPTIN, true );
		$map       = is_array( $map ) ? $map : array();
		$map[ $h ] = $opt_in ? '1' : '0';
		update_post_meta( $event_id, self::META_OPTIN, $map );
	}

	/**
	 * @param string $email Email.
	 * @return string
	 */
	public static function email_hash( $email ) {
		return md5( strtolower( trim( (string) $email ) ) );
	}

	/**
	 * @return bool
	 */
	private static function use_action_scheduler() {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_next_scheduled_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}

	/**
	 * @return void
	 */
	public static function sync_schedules() {
		self::sync_for_settings( (array) get_option( 'twec_settings', array() ) );
	}

	/**
	 * @return void
	 */
	private static function do_schedule() {
		if ( self::use_action_scheduler() ) {
			self::unschedule_wp_cron();
			$next = as_next_scheduled_action( self::SWEEP_HOOK, null, self::AS_GROUP );
			if ( ! $next ) {
				as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::SWEEP_HOOK, array(), self::AS_GROUP );
			}
		} else {
			self::unschedule_action_scheduler();
			$ts = wp_next_scheduled( self::SWEEP_HOOK );
			if ( ! $ts ) {
				wp_schedule_event( time() + 60, 'hourly', self::SWEEP_HOOK );
			}
		}
	}

	/**
	 * @return void
	 */
	public static function unschedule_all() {
		self::unschedule_wp_cron();
		self::unschedule_action_scheduler();
	}

	/**
	 * @return void
	 */
	private static function unschedule_wp_cron() {
		$ts = wp_next_scheduled( self::SWEEP_HOOK );
		while ( false !== $ts ) {
			wp_unschedule_event( $ts, self::SWEEP_HOOK );
			$ts = wp_next_scheduled( self::SWEEP_HOOK );
		}
	}

	/**
	 * @return void
	 */
	private static function unschedule_action_scheduler() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::SWEEP_HOOK, null, self::AS_GROUP );
		}
	}

	/**
	 * @return void
	 */
	public static function run_sweep() {
		if ( ! self::is_licensed() || ! self::reminders_enabled_in_settings() ) {
			return;
		}
		$offset = self::get_offset_hours();
		$now    = time();
		$min_ts = $now + ( $offset - 1 ) * HOUR_IN_SECONDS;
		$max_ts = $now + ( $offset + 1 ) * HOUR_IN_SECONDS;
		$min_s  = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $min_ts ) : date_i18n( 'Y-m-d H:i:s', $min_ts );
		$max_s  = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $max_ts ) : date_i18n( 'Y-m-d H:i:s', $max_ts );

		$q = new WP_Query(
			array(
				'post_type'              => 'twec_event',
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => '_twec_event_start_date',
						'value'   => array( $min_s, $max_s ),
						'compare' => 'BETWEEN',
						'type'    => 'CHAR',
					),
				),
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);

		if ( ! $q->have_posts() ) {
			return;
		}

		while ( $q->have_posts() ) {
			$q->the_post();
			$eid = (int) get_the_ID();
			self::maybe_send_for_event( $eid, $offset, $now );
		}
		wp_reset_postdata();
	}

	/**
	 * @param int $event_id Event ID.
	 * @param int $offset   Offset hours.
	 * @param int $now      Current unix time.
	 * @return void
	 */
	private static function maybe_send_for_event( $event_id, $offset, $now ) {
		$start_ts = self::get_event_start_timestamp( $event_id );
		if ( $start_ts <= 0 || $start_ts <= $now ) {
			return;
		}
		$emails = get_post_meta( $event_id, 'twec_rsvp_emails', true );
		$emails = is_array( $emails ) ? $emails : array();
		if ( empty( $emails ) ) {
			return;
		}
		$opt_map = get_post_meta( $event_id, self::META_OPTIN, true );
		$opt_map = is_array( $opt_map ) ? $opt_map : array();
		$sent    = get_post_meta( $event_id, self::META_SENT, true );
		$sent    = is_array( $sent ) ? $sent : array();

		$from_name = (string) get_bloginfo( 'name' );
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );

		$tz        = self::get_event_timezone( $event_id );
		$date_fmt  = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
		$start_str = function_exists( 'wp_date' )
			? wp_date( $date_fmt, $start_ts, $tz )
			: date_i18n( $date_fmt, $start_ts, true );
		$link      = get_permalink( $event_id );
		if ( ! is_string( $link ) || '' === $link ) {
			$link = home_url( '/' );
		}

		$merged = array();
		if ( class_exists( 'TWEC_Email_Templates', false ) ) {
			$merged = TWEC_Email_Templates::merged_email_settings( (array) get_option( 'twec_settings', array() ) );
		}

		$subj_tpl = isset( $merged['reminder_email_subject'] ) ? (string) $merged['reminder_email_subject'] : '';
		if ( '' === trim( wp_strip_all_tags( $subj_tpl ) ) ) {
			/* translators: %s: event title */
			$subj_tpl = __( 'Reminder: {event_title}', 'planit-event-manager' );
		}

		$body_tpl        = isset( $merged['reminder_email_body_html'] ) ? (string) $merged['reminder_email_body_html'] : '';
		$use_custom_body = '' !== trim( wp_strip_all_tags( $body_tpl ) );

		foreach ( $emails as $email ) {
			$email = is_string( $email ) ? sanitize_email( $email ) : '';
			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}
			$h   = self::email_hash( $email );
			$opt = array_key_exists( $h, $opt_map ) ? (string) $opt_map[ $h ] : '1';
			if ( '1' !== $opt ) {
				continue;
			}
			$key = $offset . 'h|' . $h;
			if ( ! empty( $sent[ $key ] ) ) {
				continue;
			}

			if ( class_exists( 'TWEC_Email_Templates', false ) ) {
				$tokens = array(
					'event_title'     => wp_strip_all_tags( get_the_title( $event_id ) ),
					'event_url'       => esc_url_raw( $link ),
					'event_starts'    => $start_str,
					'unsubscribe_url' => esc_url_raw( self::get_unsubscribe_url( $event_id, $h ) ),
					'site_name'       => sanitize_text_field( $from_name ),
					'recipient_email' => $email,
				);
				$subj   = TWEC_Email_Templates::replace_tokens( $subj_tpl, $tokens );
			} else {
				$subj = sprintf(
					/* translators: %s: event title */
					__( 'Reminder: %s', 'planit-event-manager' ),
					get_the_title( $event_id )
				);
			}

			if ( $use_custom_body && class_exists( 'TWEC_Email_Templates', false ) ) {
				$tokens_body = array(
					'event_title'     => wp_strip_all_tags( get_the_title( $event_id ) ),
					'event_url'       => esc_url_raw( $link ),
					'event_starts'    => $start_str,
					'unsubscribe_url' => esc_url_raw( self::get_unsubscribe_url( $event_id, $h ) ),
					'site_name'       => sanitize_text_field( $from_name ),
					'recipient_email' => $email,
				);
				$body        = wp_kses_post( TWEC_Email_Templates::replace_tokens( $body_tpl, $tokens_body ) );
			} else {
				$body = self::get_email_html( $event_id, $h, $start_ts, $from_name );
			}

			if ( '' === $body ) {
				continue;
			}
			$ok = wp_mail( $email, $subj, $body, $headers );
			if ( $ok ) {
				$sent[ $key ] = '1';
				update_post_meta( $event_id, self::META_SENT, $sent );
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'PlanIt: reminder send failed for event ' . (int) $event_id . ' (hash: ' . $h . ')' );
			}
		}
	}

	/**
	 * @param int    $event_id Event ID.
	 * @param string $email_h  Email hash.
	 * @param int    $start_ts Event start (UTC unix).
	 * @param string $from     Site name.
	 * @return string HTML
	 */
	private static function get_email_html( $event_id, $email_h, $start_ts, $from ) {
		$event_id = (int) $event_id;
		$title    = get_the_title( $event_id );
		$tz       = self::get_event_timezone( $event_id );
		$date_fmt = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
		if ( function_exists( 'wp_date' ) ) {
			$start_str = wp_date( $date_fmt, $start_ts, $tz );
		} else {
			$start_str = date_i18n( $date_fmt, $start_ts, true );
		}
		$link = get_permalink( $event_id );
		if ( ! is_string( $link ) || '' === $link ) {
			$link = home_url( '/' );
		}
		$unsub_url = self::get_unsubscribe_url( $event_id, $email_h );
		$site_name = $from;
		$path      = dirname( __DIR__ ) . '/includes/emails/twec-reminder-event.php';
		if ( is_readable( $path ) ) {
			ob_start();
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomFunction -- packaged template; variables for template.
			include $path;
			return (string) ob_get_clean();
		}
		return self::get_email_html_fallback( $title, $start_str, $link, $from, $unsub_url );
	}

	/**
	 * @param string $title     Title.
	 * @param string $start     Formatted start.
	 * @param string $link      URL.
	 * @param string $site      Site name.
	 * @param string $unsub     Unsub URL.
	 * @return string
	 */
	private static function get_email_html_fallback( $title, $start, $link, $site, $unsub ) {
		$esc      = static function ( $s ) {
			return esc_html( (string) $s );
		};
		$t_unsub  = __( 'Unsubscribe from reminders for this event', 'planit-event-manager' );
		$t_starts = __( 'Starts', 'planit-event-manager' );
		return '<p>' . $esc( $title ) . '</p><p><strong>' . $esc( $t_starts ) . ':</strong> ' . $esc( $start ) . '</p><p><a href="' . esc_url( $link ) . '">' . $esc( $link ) . '</a></p><p>— ' . $esc( $site ) . '</p><p><a href="' . esc_url( $unsub ) . '">' . $esc( $t_unsub ) . '</a></p>';
	}

	/**
	 * @param int    $event_id  Event post ID.
	 * @param string $email_h   md5 of lower email.
	 * @return string
	 */
	public static function get_unsubscribe_url( $event_id, $email_h ) {
		$event_id = (int) $event_id;
		$email_h  = (string) $email_h;
		$payload  = wp_json_encode(
			array(
				'e' => $event_id,
				'h' => $email_h,
			)
		);
		$b64      = rtrim( strtr( base64_encode( (string) $payload ), '+/', '-_' ), '=' );
		$mac      = hash_hmac( 'sha256', $b64, wp_salt( 'twec_rem_unsub' ) );
		$token    = $b64 . '.' . $mac;
		return add_query_arg( self::QUERY_ARG, $token, home_url( '/' ) );
	}

	/**
	 * @param int $event_id Post ID.
	 * @return \DateTimeZone
	 */
	public static function get_event_timezone( $event_id ) {
		$tzs = (string) get_post_meta( $event_id, '_twec_event_timezone', true );
		if ( '' !== $tzs ) {
			try {
				return new \DateTimeZone( $tzs );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		return new \DateTimeZone( 'UTC' );
	}

	/**
	 * @param int $event_id Post ID.
	 * @return int
	 */
	public static function get_event_start_timestamp( $event_id ) {
		$str = (string) get_post_meta( $event_id, '_twec_event_start_date', true );
		if ( '' === $str ) {
			return 0;
		}
		$tz = self::get_event_timezone( $event_id );
		$dt = date_create_immutable( $str, $tz );
		if ( ! $dt ) {
			return 0;
		}
		return $dt->getTimestamp();
	}

	/**
	 * @return void
	 */
	public static function handle_unsubscribe_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public one-click unsubscribe; token is HMAC-verified.
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) || is_admin() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HMAC + JSON verify; not stored.
		$raw = isset( $_GET[ self::QUERY_ARG ] ) ? (string) wp_unslash( $_GET[ self::QUERY_ARG ] ) : '';
		if ( '' === $raw ) {
			return;
		}
		$parts = explode( '.', $raw, 2 );
		if ( count( $parts ) < 2 ) {
			self::unsub_error();
		}
		$b64 = (string) $parts[0];
		$mac = (string) $parts[1];
		$exp = hash_hmac( 'sha256', $b64, wp_salt( 'twec_rem_unsub' ) );
		if ( ! hash_equals( $exp, $mac ) ) {
			self::unsub_error();
		}
		$pad = $b64 . str_repeat( '=', ( 4 - ( strlen( $b64 ) % 4 ) ) % 4 );
		$js  = base64_decode( strtr( $pad, '-_', '+/' ), true );
		if ( false === $js || '' === $js ) {
			self::unsub_error();
		}
		$data = json_decode( $js, true );
		if ( ! is_array( $data ) || ! isset( $data['e'], $data['h'] ) ) {
			self::unsub_error();
		}
		$eid = (int) $data['e'];
		$h   = (string) $data['h'];
		if ( $eid <= 0 || 'twec_event' !== get_post_type( $eid ) || ! preg_match( '/^[a-f0-9]{32}$/i', $h ) ) {
			self::unsub_error();
		}
		$opt       = get_post_meta( $eid, self::META_OPTIN, true );
		$opt       = is_array( $opt ) ? $opt : array();
		$opt[ $h ] = '0';
		update_post_meta( $eid, self::META_OPTIN, $opt );
		$link = get_permalink( $eid );
		if ( ! is_string( $link ) || '' === $link ) {
			$link = home_url( '/' );
		}
		wp_safe_redirect( $link, 302 );
		exit;
	}

	/**
	 * @return void
	 */
	private static function unsub_error() {
		status_header( 400 );
		wp_die( esc_html__( 'Invalid or expired link.', 'planit-event-manager' ) );
	}
}
