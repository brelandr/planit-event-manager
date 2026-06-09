<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    The_Event_Calendar
 * @subpackage public
 * @since      1.0.0
 * @file       class-twec-public.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * Handles front-end display, shortcodes, AJAX, and export functionality.
 */
class TWEC_Public {

	/**
	 * Public asset base URL. Premium may define TWEC_PLUGIN_URL; free.org build uses PLANIT_EVENT_MANAGER_URL.
	 *
	 * @return string
	 */
	private static function twec_asset_base_url() {
		// Canonical public assets ship in the org (free) package. Prefer PLANIT_* so enqueue URLs stay
		// correct when TWEC_PLUGIN_* is defined (e.g. after Premium was deactivated or in mixed installs).
		if ( defined( 'PLANIT_EVENT_MANAGER_URL' ) ) {
			return (string) constant( 'PLANIT_EVENT_MANAGER_URL' );
		}
		if ( defined( 'TWEC_PLUGIN_URL' ) ) {
			return (string) constant( 'TWEC_PLUGIN_URL' );
		}
		return '';
	}

	/**
	 * Plugin file path to bundled templates (free vs premium).
	 *
	 * @return string
	 */
	private static function twec_plugin_path() {
		if ( defined( 'TWEC_PLUGIN_DIR' ) ) {
			return (string) constant( 'TWEC_PLUGIN_DIR' );
		}
		if ( defined( 'PLANIT_EVENT_MANAGER_DIR' ) ) {
			return (string) constant( 'PLANIT_EVENT_MANAGER_DIR' );
		}
		return '';
	}

	/**
	 * Script/style version.
	 *
	 * @return string
	 */
	private static function twec_asset_version() {
		if ( defined( 'TWEC_VERSION' ) ) {
			return (string) constant( 'TWEC_VERSION' );
		}
		if ( defined( 'PLANIT_EVENT_MANAGER_VERSION' ) ) {
			return (string) constant( 'PLANIT_EVENT_MANAGER_VERSION' );
		}
		return '1.0.0';
	}

	/**
	 * Whether calendar query debug lines should be written to the PHP error log.
	 *
	 * Uses WP_DEBUG_LOG so enabling WP_DEBUG alone (display-only) does not flood debug.log.
	 *
	 * @return bool
	 */
	private function twec_should_log_calendar_debug() {
		return (bool) apply_filters(
			'twec_log_calendar_queries',
			defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG
		);
	}

	/**
	 * Register query vars for public-facing functionality.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'event_category';
		$vars[] = 'view';
		$vars[] = 'date';
		return $vars;
	}

	/**
	 * Optional compact ticket link for calendar views when enabled via shortcode/settings.
	 *
	 * @param int $event_id Event post ID.
	 * @return string HTML fragment (escaped).
	 */
	private function maybe_calendar_ticket_markup( $event_id ) {
		if ( empty( $GLOBALS['twec_calendar_show_ticket_ctas'] ) || ! class_exists( 'TWEC_WooCommerce', false ) ) {
			return '';
		}
		return (string) TWEC_WooCommerce::get_calendar_inline_ticket_markup( (int) $event_id );
	}

	/**
	 * Enqueue styles for the public-facing side of the site.
	 */
	public function enqueue_styles() {
		$base = self::twec_asset_base_url();
		if ( '' === $base ) {
			return;
		}
		wp_enqueue_style(
			'twec-public',
			$base . 'public/css/twec-public.css',
			self::twec_public_style_dependencies(),
			self::twec_asset_version(),
			'all'
		);
	}

	/**
	 * Style handles that should load before PlanIt public CSS (theme overrides).
	 *
	 * @return string[]
	 */
	private static function twec_public_style_dependencies() {
		$deps  = array();
		$theme = array( 'mvp-media-queries', 'mvp-custom-style', 'mvp-reset' );
		foreach ( $theme as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) {
				$deps[] = $handle;
			}
		}
		return $deps;
	}

	/**
	 * Enqueue scripts for the public-facing side of the site.
	 */
	public function enqueue_scripts() {
		$base = self::twec_asset_base_url();
		if ( '' === $base ) {
			return;
		}
		wp_enqueue_script(
			'twec-map-init',
			$base . 'public/js/twec-map-init.js',
			array( 'jquery' ),
			self::twec_asset_version(),
			true
		);

		wp_enqueue_script(
			'twec-calendar-grid-client',
			$base . 'public/js/twec-calendar-grid-client.js',
			array(),
			self::twec_asset_version(),
			true
		);

		wp_enqueue_script(
			'twec-public',
			$base . 'public/js/twec-public.js',
			array( 'jquery', 'twec-map-init', 'twec-calendar-grid-client' ),
			self::twec_asset_version(),
			true
		);

		$settings            = get_option( 'twec_settings', array() );
		$google_maps_api_key = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';

		// WordPress 6.5+ script modules wire `@wordpress/interactivity`; jQuery still loads for non-interactive calendars and older releases.
		$use_interactivity = function_exists( 'twec_calendar_should_use_interactivity' )
			&& twec_calendar_should_use_interactivity( array() )
			&& function_exists( 'wp_enqueue_script_module' )
			&& function_exists( 'wp_interactivity_state' );
		if ( $use_interactivity ) {
			// WordPress 6.9+: `WP_Script_Modules` requires every string in this deps array to be registered via the
			// script-modules API (`wp_register_script_module`). Our grid helper ships as classic JS (IIFE) and stays
			// loaded with `wp_enqueue_script( 'twec-calendar-grid-client', ... )` above; it only assigns
			// `window.twecCalendarHtmlFromStructuredGrid` for runtime hydration. Do not list it here—non-core handles
			// are easy to miss at register time and WP 6.9.1 emits `_doing_it_wrong` if the dep is not in the module registry.
			wp_enqueue_script_module(
				'twec-calendar-view',
				$base . 'public/js/twec-calendar-view.js',
				array(
					'@wordpress/interactivity',
				),
				self::twec_asset_version()
			);
		}

		wp_localize_script(
			'twec-public',
			'twecData',
			array(
				'ajaxUrl'          => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'            => wp_create_nonce( 'twec_ajax_get_calendar' ),
				'calPub'           => self::calendar_ajax_public_hash_localized(),
				'googleMapsApiKey' => (string) ( $google_maps_api_key ? sanitize_text_field( $google_maps_api_key ) : '' ),
				'useInteractivity' => (bool) $use_interactivity,
			)
		);

		if ( ! empty( $google_maps_api_key ) ) {
			$key_for_query = sanitize_text_field( (string) $google_maps_api_key );
			$maps_src      = esc_url(
				add_query_arg(
					'key',
					rawurlencode( $key_for_query ),
					'https://maps.googleapis.com/maps/api/js'
				)
			);
			wp_enqueue_script(
				'google-maps',
				$maps_src,
				array(),
				self::twec_asset_version(),
				true
			);
		}
	}

	/**
	 * Whether the logged-in viewer may use embedded Quick Add on the calendar.
	 *
	 * @return bool
	 */
	public static function user_can_quick_add_event() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$cap = apply_filters( 'twec_quick_add_capability', 'edit_posts' );
		return (bool) apply_filters( 'twec_quick_add_allowed', current_user_can( $cap ) );
	}

	/**
	 * Enqueue Quick Add assets when the calendar shortcode is rendered (runs after wp_enqueue_scripts).
	 *
	 * @return void
	 */
	public static function enqueue_quick_add_for_calendar() {
		if ( ! self::user_can_quick_add_event() ) {
			return;
		}
		if ( ! apply_filters( 'twec_quick_add_show', true ) ) {
			return;
		}
		$base = self::twec_asset_base_url();
		if ( '' === $base ) {
			return;
		}
		wp_enqueue_style(
			'twec-quick-add',
			$base . 'public/css/twec-quick-add.css',
			array( 'twec-public' ),
			self::twec_asset_version(),
			'all'
		);
		wp_enqueue_script(
			'twec-quick-add',
			$base . 'public/js/twec-quick-add.js',
			array( 'jquery', 'twec-public' ),
			self::twec_asset_version(),
			true
		);

		wp_localize_script(
			'twec-quick-add',
			'twecQuickAdd',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'planit/v1/events/quick-add' ) ),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
				'canPublish' => (bool) current_user_can( 'publish_posts' ),
				'i18n'       => array(
					'title'          => __( 'Quick add event', 'planit-event-manager' ),
					'btnOpen'        => __( 'Quick add event', 'planit-event-manager' ),
					'btnSave'        => __( 'Save', 'planit-event-manager' ),
					'btnCancel'      => __( 'Cancel', 'planit-event-manager' ),
					'labelTitle'     => __( 'Title', 'planit-event-manager' ),
					'labelAllDay'    => __( 'All day', 'planit-event-manager' ),
					'labelStartDate' => __( 'Start date', 'planit-event-manager' ),
					'labelEndDate'   => __( 'End date', 'planit-event-manager' ),
					'labelStartTime' => __( 'Start time', 'planit-event-manager' ),
					'labelEndTime'   => __( 'End time', 'planit-event-manager' ),
					'labelStatus'    => __( 'Status', 'planit-event-manager' ),
					'statusDraft'    => __( 'Draft', 'planit-event-manager' ),
					'statusPublish'  => __( 'Published', 'planit-event-manager' ),
					'errorGeneric'   => __( 'Could not save the event.', 'planit-event-manager' ),
					'saving'         => __( 'Saving…', 'planit-event-manager' ),
					'editingNote'    => __( 'Edit details in the admin after saving.', 'planit-event-manager' ),
					'badDates'       => __( 'Start and end dates must use Y-m-d.', 'planit-event-manager' ),
					'invalidRange'   => __( 'End must be on or after the start.', 'planit-event-manager' ),
				),
			)
		);
	}

	/**
	 * Load custom template for events.
	 *
	 * @param string $template Template path.
	 * @return string Template path.
	 */
	public function event_template( $template ) {
		$base = self::twec_plugin_path();
		if ( is_singular( 'twec_event' ) && '' !== $base ) {
			$custom_template = $base . 'public/partials/twec-single-event.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		} elseif ( is_post_type_archive( 'twec_event' ) && '' !== $base ) {
			$custom_template = $base . 'public/partials/twec-archive-events.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}

	/**
	 * Modify event query to hide past events if setting is enabled.
	 *
	 * @param WP_Query $query Query object.
	 */
	public function modify_event_query( $query ) {
		if ( ! is_admin() && $query->is_main_query() && ( is_post_type_archive( 'twec_event' ) || is_tax( 'twec_event_category' ) || is_tax( 'twec_event_tag' ) ) ) {
			$settings = get_option( 'twec_settings', array() );

			// Handle category filter.
			// Use get_query_var() instead of $_GET to avoid nonce verification requirement for public read-only filters.
			// This is the WordPress-recommended way to access URL parameters for filtering/display (read-only operations).
			$category_slug = get_query_var( 'event_category' );
			if ( ! empty( $category_slug ) ) {
				// Sanitize the category slug.
				$category_slug = sanitize_text_field( $category_slug );

				// Validate that the term exists in the taxonomy to prevent invalid queries and potential security issues.
				$term = get_term_by( 'slug', $category_slug, 'twec_event_category' );
				if ( $term && ! is_wp_error( $term ) ) {
					$query->set(
						'tax_query',
						array(
							array(
								'taxonomy' => 'twec_event_category',
								'field'    => 'slug',
								'terms'    => $category_slug,
							),
						)
					);
				}
			}

			if ( isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] ) {
				$meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}
				$meta_query[] = array(
					'key'     => '_twec_event_end_date',
					'value'   => current_time( 'mysql' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				);
				$query->set( 'meta_query', $meta_query );
				$query->set( 'meta_key', '_twec_event_end_date' );
				$query->set( 'orderby', 'meta_value' );
				$query->set( 'order', 'ASC' );
			}
		}
	}

	/**
	 * Hash for calendar public AJAX token for a UTC calendar day (pairs with {@see self::calendar_ajax_public_hashes_current_and_prev()}).
	 *
	 * @param string $ymd Date 'Y-m-d' in UTC.
	 * @return string
	 */
	private static function calendar_ajax_public_hash_for_utc_day( $ymd ) {
		return wp_hash( 'twec_calendar_ajax_pub|' . $ymd, 'nonce' );
	}

	/**
	 * Acceptable public tokens for calendar AJAX when cached HTML left a stale WP nonce (read-only endpoint).
	 *
	 * @return string[]
	 */
	private static function calendar_ajax_public_hashes_current_and_prev() {
		$utc    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$hashes = array(
			self::calendar_ajax_public_hash_for_utc_day( $utc->format( 'Y-m-d' ) ),
			self::calendar_ajax_public_hash_for_utc_day( $utc->modify( '-1 day' )->format( 'Y-m-d' ) ),
		);

		/**
		 * Filter valid rotating public hashes for calendar AJAX when the WP nonce was cached out.
		 *
		 * @param string[] $hashes Server-side hashes for today and yesterday (UTC).
		 */
		return apply_filters( 'twec_calendar_ajax_public_hashes', array_unique( array_filter( $hashes ) ) );
	}

	/**
	 * Today's UTC public hash for wp_localize_script and interactivity state (`calPub`).
	 *
	 * @return string
	 */
	public static function calendar_ajax_public_hash_localized() {
		$utc = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		return self::calendar_ajax_public_hash_for_utc_day( $utc->format( 'Y-m-d' ) );
	}

	/**
	 * Handle calendar AJAX request.
	 */
	public function ajax_get_calendar() {
		// Verify AJAX nonce, or rotating public hash when full-page cache invalidated the WP nonce (read-only).
		$nonce_ok = check_ajax_referer( 'twec_ajax_get_calendar', 'nonce', false );
		if ( ! $nonce_ok ) {
			$allowed     = false;
			$posted_hash = isset( $_POST['cal_pub'] ) ? sanitize_text_field( wp_unslash( $_POST['cal_pub'] ) ) : '';
			if ( '' !== $posted_hash && apply_filters( 'twec_calendar_ajax_allow_public_hash', true ) ) {
				foreach ( self::calendar_ajax_public_hashes_current_and_prev() as $hash ) {
					if ( hash_equals( $hash, $posted_hash ) ) {
						$allowed = true;
						break;
					}
				}
			}
			if ( ! $allowed ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid nonce', 'planit-event-manager' ) ) );
				return;
			}
		}

		// Public read-only operation - no permission check required for public data.
		// This AJAX endpoint only retrieves and displays published event data (read-only).
		// For logged-in users, verify they have read capability (WordPress.org requirement).
		if ( is_user_logged_in() && ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to view events.', 'planit-event-manager' ) ) );
			return;
		}

		// Access $_POST after nonce and permission checks (WordPress.org security requirement).
		$view = isset( $_POST['view'] ) ? sanitize_text_field( wp_unslash( $_POST['view'] ) ) : 'month';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : current_time( 'Y-m-d' );

		$response_format = isset( $_POST['response_format'] ) ? sanitize_key( wp_unslash( $_POST['response_format'] ) ) : '';

		// Interactivity calendars may request structured month grid JSON (payload v2) to avoid repeating large HTML payloads.
		$calendar_payload_version_req = isset( $_POST['calendar_payload_version'] ) ? absint( wp_unslash( $_POST['calendar_payload_version'] ) ) : 1;

		$ticket_raw = isset( $_POST['ticket_cta'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_cta'] ) ) : '';

		if ( '' === $ticket_raw ) {
			$GLOBALS['twec_calendar_show_ticket_ctas'] = class_exists( 'TWEC_WooCommerce', false ) && TWEC_WooCommerce::default_show_tickets_calendar();
		} else {
			$GLOBALS['twec_calendar_show_ticket_ctas'] = ( '1' === $ticket_raw || 'yes' === strtolower( $ticket_raw ) );
		}

		try {
			$posted_cat       = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';
			$posted_tag       = isset( $_POST['tag'] ) ? sanitize_title( wp_unslash( $_POST['tag'] ) ) : '';
			$calendar_filters = array();
			if ( '' !== $posted_cat ) {
				$calendar_filters['category'] = $posted_cat;
			}
			if ( '' !== $posted_tag ) {
				$calendar_filters['tag'] = $posted_tag;
			}

			$events = $this->get_events_for_period( $view, $date, $calendar_filters );

			/**
			 * Structured grid payloads eliminate large HTML blobs for supported views.
			 * Premium week/year gated here to match actual rendered content (no misleading shapes on fallbacks).
			 */
			$calendar_payload_grid           = null;
			$honored_pv                      = 1;
			$use_structured_calendar_payload = false;
			$calendar_html                   = '';

			if ( $calendar_payload_version_req >= 2 ) {
				$anchor = new DateTime( $date );

				if ( 'month' === $view ) {
					$use_structured_calendar_payload = true;
					$honored_pv                      = 2;
					$calendar_payload_grid           = $this->build_month_calendar_grid( $anchor, $events );
				} elseif ( 'day' === $view ) {
					$use_structured_calendar_payload = true;
					$honored_pv                      = 2;
					$calendar_payload_grid           = $this->build_day_view_grid( $anchor, $events );
				} elseif ( 'week' === $view && TWEC_Premium::is_available( 'week' ) ) {
					$use_structured_calendar_payload = true;
					$honored_pv                      = 2;
					$calendar_payload_grid           = $this->build_week_view_grid( $anchor, $events );
				} elseif ( 'year' === $view && TWEC_Premium::is_available( 'year' ) ) {
					$use_structured_calendar_payload = true;
					$honored_pv                      = 2;
					$calendar_payload_grid           = $this->build_year_view_grid( $anchor, $events );
				}
			}

			if ( ! $use_structured_calendar_payload ) {
				$calendar_html = $this->render_calendar_view( $view, $date, $calendar_filters );
			}

			$title = $this->get_calendar_title( $view, $date );

			// Map view: pass markers separately so jQuery AJAX .html() clients can hydrate maps (script tags in fragments are stripped).
			$map_markers = array();
			if ( 'map' === $view && TWEC_Premium::is_available( 'map' ) ) {
				$map_markers = $this->build_map_marker_list_for_events( $events );
			}

			// Ensure title is properly escaped for JSON output (defense in depth).
			// The title is already safe from get_calendar_title(), but we escape it for extra safety.
			$payload = array(
				'html'           => $calendar_html,
				'title'          => esc_html( $title ),
				'payloadVersion' => $honored_pv,
			);

			if ( null !== $calendar_payload_grid ) {
				$payload['grid'] = $calendar_payload_grid;
			}

			$compact = ( 'compact' === $response_format );

			// Do not serialize full `events`/debug payloads when compact, or when v2 sends structured grid.
			$omit_verbose_payload = (
				$compact
				|| $use_structured_calendar_payload
			);

			if ( ! $omit_verbose_payload ) {
				$payload['events']     = $events;
				$payload['mapMarkers'] = $map_markers;
				$payload['debug']      = array(
					'view'         => esc_html( $view ),
					'date'         => esc_html( $date ),
					'events_count' => absint( count( $events ) ),
				);
			} elseif ( 'map' === $view && TWEC_Premium::is_available( 'map' ) && ! empty( $map_markers ) ) {
				$payload['mapMarkers'] = $map_markers;
			}

			wp_send_json_success( $payload );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		} finally {
			unset( $GLOBALS['twec_calendar_show_ticket_ctas'] );
		}
	}

	/**
	 * Render calendar view HTML.
	 *
	 * @param string $view View type.
	 * @param string $date Date string.
	 * @param array  $filters Optional. Keys: `category` (twec_event_category slug), `tag` (twec_event_tag slug). Same as `[twec_calendar]` attributes.
	 * @return string Calendar HTML.
	 */
	public function render_calendar_view( $view, $date, $filters = array() ) {
		$events   = $this->get_events_for_period( $view, $date, $filters );
		$date_obj = new DateTime( $date );

		// Debug: Log events being passed to render (only when debug.log is enabled).
		if ( $this->twec_should_log_calendar_debug() ) {
			error_log( 'TWEC render_calendar_view: View=' . $view . ', Date=' . $date . ', Events=' . count( $events ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Check for premium views - fallback to month view without showing upgrade notice.
		$premium_views = array( 'week', 'year', 'photo', 'map' );
		if ( in_array( $view, $premium_views, true ) && ! TWEC_Premium::is_available( $view ) ) {
			// Silently fallback to month view for better UX.
			return $this->render_month_view( $date_obj, $events );
		}

		switch ( $view ) {
			case 'day':
				return $this->render_day_view( $date_obj, $events );
			case 'week':
				if ( TWEC_Premium::is_available( 'week' ) ) {
					return $this->render_week_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice.
				return $this->render_month_view( $date_obj, $events );
			case 'month':
				return $this->render_month_view( $date_obj, $events );
			case 'year':
				if ( TWEC_Premium::is_available( 'year' ) ) {
					return $this->render_year_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice.
				return $this->render_month_view( $date_obj, $events );
			case 'photo':
				if ( TWEC_Premium::is_available( 'photo' ) ) {
					return $this->render_photo_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice.
				return $this->render_month_view( $date_obj, $events );
			case 'map':
				if ( TWEC_Premium::is_available( 'map' ) ) {
					return $this->render_map_view( $date_obj, $events );
				}
				// Fallback to month view without upgrade notice.
				return $this->render_month_view( $date_obj, $events );
			default:
				return $this->render_month_view( $date_obj, $events );
		}
	}

	/**
	 * Build structured month view data shared by AJAX grid payloads and SSR HTML rendering.
	 *
	 * @param DateTime $date    Month anchor date.
	 * @param array    $events  Events covering the period (from {@see get_events_for_period()}).
	 * @return array{weekdayLabels: string[], weeks: array<int, array<int, array<string,mixed>>>}
	 */
	private function build_month_calendar_grid( DateTime $date, array $events ) {
		$year      = $date->format( 'Y' );
		$month     = $date->format( 'm' );
		$first_day = new DateTime( "$year-$month-01" );
		$last_day  = clone $first_day;
		$last_day->modify( 'last day of this month' );

		$start_date = clone $first_day;
		$start_date->modify( 'monday this week' );

		$end_date = clone $last_day;
		$end_date->modify( 'sunday this week' );

		$weekday_labels = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );

		$cells   = array();
		$current = clone $start_date;

		while ( $current <= $end_date ) {
			$is_other_month = (int) $current->format( 'm' ) !== (int) $month;
			$is_today       = $current->format( 'Y-m-d' ) === current_time( 'Y-m-d' );
			$day_class      = 'twec-calendar-day';
			if ( $is_other_month ) {
				$day_class .= ' other-month';
			}
			if ( $is_today ) {
				$day_class .= ' today';
			}

			$ymd = $current->format( 'Y-m-d' );

			$ev_payloads = array();

			$day_events = $this->get_events_for_day( $ymd, $events );
			if ( ! empty( $day_events ) ) {
				foreach ( $day_events as $event ) {
					$event_title_raw = get_the_title( $event->ID );
					$event_url_raw   = get_permalink( $event->ID );
					$title_short     = mb_substr( $event_title_raw, 0, 30 ) . ( mb_strlen( $event_title_raw ) > 30 ? '...' : '' );

					$ev_payloads[] = array(
						'id'         => (int) $event->ID,
						'titleShort' => $title_short,
						'titleFull'  => $event_title_raw,
						'url'        => $event_url_raw ? (string) $event_url_raw : '',
						// Stored for JSON consumers; escapes not applied HTML-side because values are plaintext + JSON-encoded.
						'ticketHtml' => $this->maybe_calendar_ticket_markup( (int) $event->ID ),
					);
				}
			}

			$cells[] = array(
				'ymd'     => $ymd,
				'dayNum'  => $current->format( 'j' ),
				'tdClass' => $day_class,
				'events'  => $ev_payloads,
			);

			$current->modify( '+1 day' );
		}

		return array(
			'layout'        => 'month',
			'weekdayLabels' => $weekday_labels,
			'weeks'         => array_chunk( $cells, 7 ),
		);
	}

	/**
	 * Render SSR month table markup from structured grid built in {@see build_month_calendar_grid()}.
	 *
	 * @param array $grid Grid structure.
	 * @return string
	 */
	private function month_calendar_grid_to_html( array $grid ) {
		if ( empty( $grid['weekdayLabels'] ) || empty( $grid['weeks'] ) ) {
			return '';
		}

		$html  = '<table class="twec-calendar-month">';
		$html .= '<thead><tr>';

		foreach ( (array) $grid['weekdayLabels'] as $day_label ) {
			$html .= '<th>' . esc_html( (string) $day_label ) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( (array) $grid['weeks'] as $week ) {
			$html .= '<tr>';

			foreach ( (array) $week as $cell ) {
				$classes = isset( $cell['tdClass'] ) ? (string) $cell['tdClass'] : '';
				$day_num = isset( $cell['dayNum'] ) ? (string) $cell['dayNum'] : '';

				$html .= '<td class="' . esc_attr( $classes ) . '">';
				$html .= '<div class="twec-calendar-day">';
				$html .= '<div class="twec-calendar-day-number">' . esc_html( $day_num ) . '</div>';

				if ( ! empty( $cell['events'] ) && is_array( $cell['events'] ) ) {
					foreach ( $cell['events'] as $event_data ) {
						if ( empty( $event_data ) || ! is_array( $event_data ) ) {
							continue;
						}

						$url           = isset( $event_data['url'] ) ? (string) $event_data['url'] : '';
						$title_short   = isset( $event_data['titleShort'] ) ? (string) $event_data['titleShort'] : '';
						$title_full    = isset( $event_data['titleFull'] ) ? (string) $event_data['titleFull'] : '';
						$ticket_markup = isset( $event_data['ticketHtml'] ) ? (string) $event_data['ticketHtml'] : '';

						$html .= '<a href="' . esc_url( $url ) . '" class="twec-calendar-event" data-url="' . esc_url( $url ) . '" title="' . esc_attr( $title_full ) . '">';
						$html .= esc_html( $title_short );
						$html .= '</a>';

						if ( '' !== $ticket_markup ) {
							$html .= $ticket_markup;
						}
					}
				}

				$html .= '</div></td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		return $html;
	}

	/**
	 * Day view grid for payload v2 and SSR parity.
	 *
	 * @param DateTime $date   Anchored calendar day.
	 * @param array    $events Events list.
	 * @return array<string, mixed>
	 */
	private function build_day_view_grid( DateTime $date, array $events ) {
		$hours_payload = array();

		for ( $hour = 0; $hour < 24; $hour++ ) {
			$ev_payloads = array();
			$day_events  = $this->get_events_for_day( $date->format( 'Y-m-d' ), $events );

			foreach ( $day_events as $event ) {
				$start_time = get_post_meta( $event->ID, '_twec_event_start_time', true );
				if ( $start_time ) {
					$event_hour = (int) substr( $start_time, 0, 2 );
					if ( $event_hour === $hour ) {
						$title      = get_the_title( $event->ID );
						$permalink  = get_permalink( $event->ID );
						$link_inner = $title . ' - ' . $start_time;

						$ev_payloads[] = array(
							'id'         => (int) $event->ID,
							'titleShort' => $title,
							'titleFull'  => $title,
							'linkText'   => $link_inner,
							'url'        => $permalink ? (string) $permalink : '',
							'ticketHtml' => $this->maybe_calendar_ticket_markup( (int) $event->ID ),
						);
					}
				}
			}

			$hours_payload[] = array(
				'hour'   => $hour,
				'label'  => sprintf( '%02d:00', $hour ),
				'events' => $ev_payloads,
			);
		}

		return array(
			'layout' => 'day',
			'hours'  => $hours_payload,
		);
	}

	/**
	 * Render day view HTML from structured grid.
	 *
	 * @param array $grid Grid from {@see build_day_view_grid()}.
	 * @return string
	 */
	private function day_view_grid_to_html( array $grid ) {
		if ( empty( $grid['hours'] ) || ! is_array( $grid['hours'] ) ) {
			return '';
		}

		$html = '<div class="twec-calendar-day-view">';
		foreach ( (array) $grid['hours'] as $hour_row ) {
			if ( empty( $hour_row ) || ! is_array( $hour_row ) ) {
				continue;
			}

			$label = isset( $hour_row['label'] ) ? (string) $hour_row['label'] : '';

			$html .= '<div class="twec-day-hour">' . esc_html( $label ) . '</div>';
			$html .= '<div class="twec-day-events">';

			if ( ! empty( $hour_row['events'] ) && is_array( $hour_row['events'] ) ) {
				foreach ( $hour_row['events'] as $event_row ) {
					if ( empty( $event_row ) || ! is_array( $event_row ) ) {
						continue;
					}

					$url           = isset( $event_row['url'] ) ? (string) $event_row['url'] : '';
					$link_text     = isset( $event_row['linkText'] ) ? (string) $event_row['linkText'] : '';
					$ticket_markup = isset( $event_row['ticketHtml'] ) ? (string) $event_row['ticketHtml'] : '';

					$html .= '<div class="twec-week-event">';
					$html .= '<a href="' . esc_url( $url ) . '">' . esc_html( $link_text ) . '</a>';
					if ( '' !== $ticket_markup ) {
						$html .= $ticket_markup;
					}
					$html .= '</div>';
				}
			}

			$html .= '</div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Week view grid payload + SSR parity (Premium week view).
	 *
	 * @param DateTime $date   Anchored calendar day.
	 * @param array    $events Events list.
	 * @return array<string, mixed>
	 */
	private function build_week_view_grid( DateTime $date, array $events ) {
		$start_of_week = clone $date;
		$start_of_week->modify( 'monday this week' );

		$day_labels = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$d = clone $start_of_week;
			$d->modify( "+$i days" );
			$day_labels[] = $d->format( 'D, M j' );
		}

		$rows = array();
		for ( $hour = 0; $hour < 24; $hour++ ) {
			$cells = array();

			for ( $i = 0; $i < 7; $i++ ) {
				$events_payload = array();

				$d = clone $start_of_week;
				$d->modify( "+$i days" );
				$day_events = $this->get_events_for_day( $d->format( 'Y-m-d' ), $events );

				foreach ( $day_events as $event ) {
					$start_time = get_post_meta( $event->ID, '_twec_event_start_time', true );

					if ( $start_time ) {
						$event_hour = (int) substr( $start_time, 0, 2 );
						if ( $event_hour === $hour ) {
							$title     = get_the_title( $event->ID );
							$permalink = get_permalink( $event->ID );

							$events_payload[] = array(
								'id'         => (int) $event->ID,
								'titleShort' => $title,
								'titleFull'  => $title,
								'url'        => $permalink ? (string) $permalink : '',
								'ticketHtml' => $this->maybe_calendar_ticket_markup( (int) $event->ID ),
							);
						}
					}
				}

				$cells[] = array(
					'events' => $events_payload,
				);
			}

			$rows[] = array(
				'hour'  => $hour,
				'label' => sprintf( '%02d:00', $hour ),
				'cells' => $cells,
			);
		}

		return array(
			'layout'    => 'week',
			'dayLabels' => $day_labels,
			'rows'      => $rows,
		);
	}

	/**
	 * Week view SSR HTML from structured grid.
	 *
	 * @param array $grid Grid from {@see build_week_view_grid()}.
	 * @return string
	 */
	private function week_view_grid_to_html( array $grid ) {
		if ( empty( $grid['rows'] ) || ! is_array( $grid['rows'] ) ) {
			return '';
		}

		$html  = '<div class="twec-calendar-week">';
		$html .= '<div class="twec-week-hour"></div>';

		if ( ! empty( $grid['dayLabels'] ) && is_array( $grid['dayLabels'] ) ) {
			foreach ( (array) $grid['dayLabels'] as $lbl ) {
				$html .= '<div class="twec-week-day-header">' . esc_html( (string) $lbl ) . '</div>';
			}
		}

		foreach ( (array) $grid['rows'] as $row ) {
			if ( empty( $row ) || ! is_array( $row ) ) {
				continue;
			}

			$row_label = isset( $row['label'] ) ? (string) $row['label'] : '';

			$html .= '<div class="twec-week-hour">' . esc_html( $row_label ) . '</div>';

			if ( ! empty( $row['cells'] ) && is_array( $row['cells'] ) ) {
				foreach ( (array) $row['cells'] as $cell ) {
					$html .= '<div class="twec-week-day">';

					if ( ! empty( $cell['events'] ) && is_array( $cell['events'] ) ) {
						foreach ( $cell['events'] as $ev ) {
							if ( empty( $ev ) || ! is_array( $ev ) ) {
								continue;
							}

							$url           = isset( $ev['url'] ) ? (string) $ev['url'] : '';
							$title_short   = isset( $ev['titleShort'] ) ? (string) $ev['titleShort'] : '';
							$ticket_markup = isset( $ev['ticketHtml'] ) ? (string) $ev['ticketHtml'] : '';

							$html .= '<div class="twec-week-event">';
							$html .= '<a href="' . esc_url( $url ) . '">' . esc_html( $title_short ) . '</a>';

							if ( '' !== $ticket_markup ) {
								$html .= $ticket_markup;
							}

							$html .= '</div>';
						}
					}

					$html .= '</div>';
				}
			}
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Year view grid payload + SSR parity (Premium).
	 *
	 * @param DateTime $date   Anchored calendar day.
	 * @param array    $events Events list (year-spanning query results).
	 * @return array<string, mixed>
	 */
	private function build_year_view_grid( DateTime $date, array $events ) {
		$year     = $date->format( 'Y' );
		$dow_mini = array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' );
		$months   = array();

		for ( $month = 1; $month <= 12; $month++ ) {
			$m          = sprintf( '%02d', $month );
			$month_date = new DateTime( "$year-$m-01" );
			$cells      = array();

			$first_day = clone $month_date;
			$first_day->modify( 'monday this week' );

			$last_day = clone $month_date;
			$last_day->modify( 'last day of this month' );
			$last_day->modify( 'sunday this week' );

			$current = clone $first_day;
			while ( $current <= $last_day ) {
				$is_other_month = (int) $current->format( 'm' ) !== (int) $month;

				$has_events = ! empty( $this->get_events_for_day( $current->format( 'Y-m-d' ), $events ) );

				$day_class = 'twec-year-day';

				if ( $has_events && ! $is_other_month ) {
					$day_class .= ' has-events';
				}

				$cells[] = array(
					'ymd'       => $current->format( 'Y-m-d' ),
					'dayNum'    => $is_other_month ? '' : $current->format( 'j' ),
					'inMonth'   => ! $is_other_month,
					'hasEvents' => $has_events && ! $is_other_month,
					'divClass'  => $day_class,
				);

				$current->modify( '+1 day' );
			}

			$months[] = array(
				'title'         => $month_date->format( 'F' ),
				'weekdayLabels' => $dow_mini,
				'weeks'         => array_chunk( $cells, 7 ),
			);
		}

		return array(
			'layout' => 'year',
			'year'   => (string) $year,
			'months' => $months,
		);
	}

	/**
	 * Render year SSR HTML from structured grid.
	 *
	 * @param array $grid Grid from {@see build_year_view_grid()}.
	 * @return string
	 */
	private function year_view_grid_to_html( array $grid ) {
		if ( empty( $grid['months'] ) || ! is_array( $grid['months'] ) ) {
			return '';
		}

		$html = '<div class="twec-calendar-year">';
		foreach ( (array) $grid['months'] as $month_block ) {
			if ( empty( $month_block ) || ! is_array( $month_block ) ) {
				continue;
			}

			$title_month = isset( $month_block['title'] ) ? (string) $month_block['title'] : '';

			$html .= '<div class="twec-year-month">';
			$html .= '<div class="twec-year-month-title">' . esc_html( $title_month ) . '</div>';
			$html .= '<div class="twec-year-month-grid">';

			if ( ! empty( $month_block['weekdayLabels'] ) && is_array( $month_block['weekdayLabels'] ) ) {
				foreach ( (array) $month_block['weekdayLabels'] as $w ) {
					$html .= '<div class="twec-year-day twec-year-day-header">' . esc_html( (string) $w ) . '</div>';
				}
			}

			if ( ! empty( $month_block['weeks'] ) && is_array( $month_block['weeks'] ) ) {
				foreach ( (array) $month_block['weeks'] as $week ) {
					if ( ! is_array( $week ) ) {
						continue;
					}

					foreach ( $week as $cell ) {
						if ( empty( $cell ) || ! is_array( $cell ) ) {
							continue;
						}

						$classes = isset( $cell['divClass'] ) ? (string) $cell['divClass'] : 'twec-year-day';

						$html .= '<div class="' . esc_attr( $classes ) . '">';
						if ( ! empty( $cell['dayNum'] ) ) {
							$html .= esc_html( (string) $cell['dayNum'] );
						}
						$html .= '</div>';
					}
				}
			}

			$html .= '</div></div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render month view.
	 *
	 * @param DateTime $date   Date object.
	 * @param array    $events Events array.
	 * @return string HTML output.
	 */
	private function render_month_view( $date, $events ) {
		$grid = $this->build_month_calendar_grid( $date, $events );

		return $this->month_calendar_grid_to_html( $grid );
	}

	/**
	 * Render week view.
	 *
	 * @param DateTime $date   Date object.
	 * @param array    $events Events array.
	 * @return string HTML output.
	 */
	private function render_week_view( $date, $events ) {
		$grid = $this->build_week_view_grid( $date, $events );

		return $this->week_view_grid_to_html( $grid );
	}

	/**
	 * Render day view.
	 *
	 * @param DateTime $date   Date object.
	 * @param array    $events Events array.
	 * @return string HTML output.
	 */
	private function render_day_view( $date, $events ) {
		$grid = $this->build_day_view_grid( $date, $events );

		return $this->day_view_grid_to_html( $grid );
	}

	/**
	 * Render year view.
	 *
	 * @param DateTime $date   Date object.
	 * @param array    $events Events array.
	 * @return string HTML output.
	 */
	private function render_year_view( $date, $events ) {
		$grid = $this->build_year_view_grid( $date, $events );

		return $this->year_view_grid_to_html( $grid );
	}

	/**
	 * Get events for a specific period.
	 *
	 * @param string $view    View type.
	 * @param string $date    Date string.
	 * @param array  $filters Optional. Keys: `category` (twec_event_category slug), `tag` (twec_event_tag slug).
	 * @return array Events array.
	 */
	public function get_events_for_period( $view, $date, $filters = array() ) {
		$date_obj   = new DateTime( $date );
		$start_date = clone $date_obj;
		$end_date   = clone $date_obj;

		switch ( $view ) {
			case 'day':
				$end_date->modify( '+1 day' );
				break;
			case 'week':
				$start_date->modify( 'monday this week' );
				$end_date = clone $start_date;
				$end_date->modify( '+7 days' );
				break;
			case 'month':
				$start_date->modify( 'first day of this month' );
				$start_date->modify( 'monday this week' );
				$end_date->modify( 'last day of this month' );
				$end_date->modify( 'sunday this week' );
				break;
			case 'year':
				$start_date->modify( 'first day of January' );
				$end_date->modify( 'last day of December' );
				break;
		}

		// Build meta query - events that overlap with the period.
		// An event overlaps if: start_date <= period_end AND end_date >= period_start.
		// Use DATE type instead of DATETIME for more reliable comparisons.
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_twec_event_start_date',
				'value'   => $end_date->format( 'Y-m-d' ),
				'compare' => '<=',
				'type'    => 'DATE',
			),
			array(
				'key'     => '_twec_event_end_date',
				'value'   => $start_date->format( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);

		$settings = get_option( 'twec_settings', array() );
		// Only apply hide_past_events filter if viewing current month or future.
		// Don't apply it when viewing past months (user might want to see past events).
		$viewing_date = clone $date_obj;
		$viewing_date->setTime( 0, 0, 0 );
		// Get first day of viewing month for comparison.
		if ( 'month' === $view ) {
			$viewing_date->modify( 'first day of this month' );
		}
		$today = new DateTime();
		$today->setTime( 0, 0, 0 );
		$today->modify( 'first day of this month' );

		// Only hide past events if we're viewing current month or future, AND the setting is enabled.
		// This prevents hiding events when viewing past months.
		if ( isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] && $viewing_date >= $today ) {
			$meta_query[] = array(
				'key'     => '_twec_event_end_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			);
		}

		$tax_query = array();
		if ( is_array( $filters ) ) {
			$category_slug = isset( $filters['category'] ) ? sanitize_title( (string) $filters['category'] ) : '';
			$tag_slug      = isset( $filters['tag'] ) ? sanitize_title( (string) $filters['tag'] ) : '';
			if ( '' !== $category_slug ) {
				$tax_query[] = array(
					'taxonomy' => 'twec_event_category',
					'field'    => 'slug',
					'terms'    => $category_slug,
				);
			}
			if ( '' !== $tag_slug ) {
				$tax_query[] = array(
					'taxonomy' => 'twec_event_tag',
					'field'    => 'slug',
					'terms'    => $tag_slug,
				);
			}
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		// Optimized: Use efficient query structure.
		// Note: meta_query and meta_key are necessary for event calendar functionality. Performance can be improved with database indexes (see class-twec-activator.php).
		$args = array(
			'post_type'      => 'twec_event',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for event calendar date filtering, optimized with DATE type. Database indexes recommended for production.
			'meta_query'     => $meta_query,
			'orderby'        => 'meta_value',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering, optimized with DATE type. Database indexes recommended for production.
			'meta_key'       => '_twec_event_start_date',
			'order'          => 'ASC',
		);
		if ( ! empty( $tax_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Category/tag filter for `[twec_calendar]`; matches list shortcode.
			$args['tax_query'] = $tax_query;
		}

		$events = get_posts( $args );

		// If no events found with DATE type, try a broader query as fallback.
		if ( empty( $events ) && 'month' === $view ) {
			// Fallback: Get all events and filter in PHP.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering
			$fallback_args = array(
				'post_type'      => 'twec_event',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'meta_value',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering fallback query. Database indexes recommended for production.
				'meta_key'       => '_twec_event_start_date',
				'order'          => 'ASC',
			);
			if ( ! empty( $tax_query ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Mirror primary query taxonomy filter when fallback runs.
				$fallback_args['tax_query'] = $tax_query;
			}

			$all_events = get_posts( $fallback_args );
			$events     = array();

			foreach ( $all_events as $event ) {
				$event_start = get_post_meta( $event->ID, '_twec_event_start_date', true );
				$event_end   = get_post_meta( $event->ID, '_twec_event_end_date', true );

				if ( ! $event_start || ! $event_end ) {
					continue;
				}

				// Extract just the date part.
				$event_start_date  = substr( $event_start, 0, 10 );
				$event_end_date    = substr( $event_end, 0, 10 );
				$period_start_date = $start_date->format( 'Y-m-d' );
				$period_end_date   = $end_date->format( 'Y-m-d' );

				// Check if event overlaps with period.
				if ( $event_start_date <= $period_end_date && $event_end_date >= $period_start_date ) {
					$events[] = $event;
				}
			}
		}

		// Debug logging (only when WP_DEBUG_LOG is enabled — see twec_should_log_calendar_debug()).
		if ( $this->twec_should_log_calendar_debug() ) {
			error_log( 'TWEC Calendar Query: View=' . $view . ', Date=' . $date ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'TWEC Period: Start=' . $start_date->format( 'Y-m-d H:i:s' ) . ', End=' . $end_date->format( 'Y-m-d H:i:s' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'TWEC Events Found from Query: ' . count( $events ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Don't filter events again - they're already filtered by the query.
		// The PHP filtering was causing issues, so we'll trust the MySQL query.
		// Just ensure we have valid event objects.
		$valid_events = array();
		foreach ( $events as $event ) {
			if ( isset( $event->ID ) && get_post( $event->ID ) ) {
				$valid_events[] = $event;
			}
		}

		// Recurring series: parent post meta is the first occurrence only; include the post when any
		// computed instance overlaps the visible period (otherwise month grids show zero events).
		if ( class_exists( 'TWEC_Recurring', false ) ) {
			$included_ids     = wp_list_pluck( $valid_events, 'ID' );
			$period_start_str = $start_date->format( 'Y-m-d' );
			$period_end_str   = $end_date->format( 'Y-m-d' );

			$hide_past_cutoff = null;
			if ( isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] && $viewing_date >= $today ) {
				$hide_past_cutoff = current_time( 'Y-m-d' );
			}

			$recurring_args = array(
				'post_type'              => 'twec_event',
				'posts_per_page'         => -1,
				'post_status'            => 'publish',
				'post__not_in'           => $included_ids,
				'update_post_meta_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small secondary fetch for recurring series not matched by primary DATE overlap query.
				'meta_query'             => array(
					array(
						'key'   => '_twec_is_recurring',
						'value' => '1',
					),
				),
			);
			if ( ! empty( $tax_query ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Same taxonomy filters as primary calendar query.
				$recurring_args['tax_query'] = $tax_query;
			}

			$recurring_candidates = get_posts( $recurring_args );

			foreach ( $recurring_candidates as $rec_post ) {
				$instances = TWEC_Recurring::get_recurring_instances( $rec_post->ID, $period_start_str, $period_end_str );
				if ( empty( $instances ) ) {
					continue;
				}
				if ( null !== $hide_past_cutoff ) {
					$has_visible = false;
					foreach ( $instances as $inst ) {
						if ( ! isset( $inst['end'] ) ) {
							continue;
						}
						$inst_end_day = substr( (string) $inst['end'], 0, 10 );
						if ( $inst_end_day >= $hide_past_cutoff ) {
							$has_visible = true;
							break;
						}
					}
					if ( ! $has_visible ) {
						continue;
					}
				}
				if ( isset( $rec_post->ID ) && get_post( $rec_post->ID ) ) {
					$valid_events[] = $rec_post;
				}
			}
		}

		if ( $this->twec_should_log_calendar_debug() ) {
			error_log( 'TWEC Final Events: ' . count( $valid_events ) . ' total (primary query matched ' . count( $events ) . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $valid_events;
	}

	/**
	 * Get events for a specific day.
	 *
	 * @param string $date   Date string.
	 * @param array  $events Events array.
	 * @return array Day events array.
	 */
	private function get_events_for_day( $date, $events ) {
		$day_events = array();
		$check_date = $this->parse_event_date( $date );
		if ( ! $check_date ) {
			return $day_events;
		}

		// Normalize check date to just the date part (ignore time).
		$check_date_only = $check_date->format( 'Y-m-d' );

		foreach ( $events as $event ) {
			$is_recurring = get_post_meta( $event->ID, '_twec_is_recurring', true );
			if ( $is_recurring && class_exists( 'TWEC_Recurring', false ) ) {
				$lookback_days = (int) apply_filters( 'twec_calendar_recurring_day_lookback_days', 370, $event->ID );
				if ( $lookback_days < 1 ) {
					$lookback_days = 1;
				}
				$lookup_start = clone $check_date;
				$lookup_start->modify( '-' . $lookback_days . ' days' );
				$instances = TWEC_Recurring::get_recurring_instances(
					$event->ID,
					$lookup_start->format( 'Y-m-d' ),
					$check_date_only
				);
				foreach ( $instances as $inst ) {
					if ( ! isset( $inst['start'], $inst['end'] ) ) {
						continue;
					}
					$inst_start_day = substr( (string) $inst['start'], 0, 10 );
					$inst_end_day   = substr( (string) $inst['end'], 0, 10 );
					if ( $check_date_only >= $inst_start_day && $check_date_only <= $inst_end_day ) {
						$day_events[] = $event;
						break;
					}
				}
				continue;
			}

			$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
			$end_date   = get_post_meta( $event->ID, '_twec_event_end_date', true );

			if ( ! $start_date || ! $end_date ) {
				continue;
			}

			// Parse dates.
			$event_start_dt = $this->parse_event_date( $start_date );
			$event_end_dt   = $this->parse_event_date( $end_date );

			if ( ! $event_start_dt || ! $event_end_dt ) {
				continue;
			}

			// Get just the date portion (ignore time).
			$event_start_only = $event_start_dt->format( 'Y-m-d' );
			$event_end_only   = $event_end_dt->format( 'Y-m-d' );

			// Check if the day falls within the event range.
			if ( $check_date_only >= $event_start_only && $check_date_only <= $event_end_only ) {
				$day_events[] = $event;
			}
		}

		return $day_events;
	}

	/**
	 * Get calendar title.
	 *
	 * @param string $view View type.
	 * @param string $date Date string.
	 * @return string Calendar title.
	 */
	private function get_calendar_title( $view, $date ) {
		$date_obj = new DateTime( $date );

		switch ( $view ) {
			case 'day':
				return $date_obj->format( 'F j, Y' );
			case 'week':
				$start = clone $date_obj;
				$start->modify( 'monday this week' );
				$end = clone $start;
				$end->modify( '+6 days' );
				return $start->format( 'M j' ) . ' - ' . $end->format( 'M j, Y' );
			case 'month':
				return $date_obj->format( 'F Y' );
			case 'year':
				return $date_obj->format( 'Y' );
			case 'photo':
				return esc_html__( 'Photo View', 'planit-event-manager' );
			case 'map':
				return esc_html__( 'Map View', 'planit-event-manager' );
			default:
				return $date_obj->format( 'F Y' );
		}
	}

	/**
	 * Public subscribe feed: multiple VEVENTs in one calendar (upcoming, ordered by start date).
	 * Use: home_url + ?twec_feed=ics  optional &twec_event_category=category-slug
	 *
	 * @return void
	 */
	public function handle_ics_subscribe_feed() {
		// phpcs:ignore WordPress.Security.NonceVerification -- Public, read-only calendar feed; only published events.
		if ( ! isset( $_GET['twec_feed'] ) || 'ics' !== sanitize_key( wp_unslash( $_GET['twec_feed'] ) ) ) {
			return;
		}

		$args = array(
			'post_type'      => 'twec_event',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'no_found_rows'  => true,
			'meta_key'       => '_twec_event_start_date',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_type'      => 'DATETIME',
			'meta_query'     => array(
				array(
					'key'     => '_twec_event_end_date',
					'value'   => current_time( 'mysql' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
		);

		// phpcs:ignore WordPress.Security.NonceVerification -- Read-only filter by public taxonomy slug.
		$cat = isset( $_GET['twec_event_category'] ) ? sanitize_title( wp_unslash( $_GET['twec_event_category'] ) ) : '';
		if ( $cat ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'twec_event_category',
					'field'    => 'slug',
					'terms'    => $cat,
				),
			);
		}

		// Transient key: option version bumps on event save; slug hash keeps category variants separate.
		$feed_ver  = absint( get_option( 'planit_pem_ics_feed_ver', 1 ) );
		$cache_key = 'planit_pem_v' . $feed_ver . '_ics_' . md5( $cat ? $cat : 'all' );

		$cached_feed = get_transient( $cache_key );

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename=planit-events.ics' );

		if ( false !== $cached_feed && is_string( $cached_feed ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full .ics payload; cache cleared when planit_pem_ics_feed_ver changes.
			echo $cached_feed;
			exit;
		}

		$query = new WP_Query( $args );

		ob_start();

		echo "BEGIN:VCALENDAR\r\n";
		echo "VERSION:2.0\r\n";
		echo "PRODID:-//PlanIt Event Manager//EN\r\n";

		$parsed_url = wp_parse_url( home_url() );
		$host       = isset( $parsed_url['host'] ) ? $parsed_url['host'] : 'example.com';

		while ( $query->have_posts() ) {
			$query->the_post();
			$event_id   = (int) get_the_ID();
			$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
			$end_date   = get_post_meta( $event_id, '_twec_event_end_date', true );
			$venue_id   = get_post_meta( $event_id, '_twec_event_venue', true );
			$ev         = get_post( $event_id );
			if ( ! $start_date || ! $ev ) {
				continue;
			}
			if ( ! $end_date ) {
				$end_date = $start_date;
			}
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal VEVENT lines are not HTML; escapes would corrupt ICS fields.
			echo "BEGIN:VEVENT\r\n";
			echo 'UID:planit-feed-' . absint( $event_id ) . '@' . $this->ical_host_for_uid( $host ) . "\r\n";
			echo 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\r\n";
			echo 'DTSTART:' . gmdate( 'Ymd\THis\Z', strtotime( $start_date ) ) . "\r\n";
			echo 'DTEND:' . gmdate( 'Ymd\THis\Z', strtotime( $end_date ) ) . "\r\n";
			echo 'SUMMARY:' . $this->ical_escape( $ev->post_title ) . "\r\n";
			echo 'DESCRIPTION:' . $this->ical_escape( wp_strip_all_tags( $ev->post_content ) ) . "\r\n";
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			$event_url = get_permalink( $event_id );
			if ( $event_url ) {
				echo 'URL:' . esc_url( $event_url ) . "\r\n";
			}
			if ( $venue_id ) {
				$venue         = get_post( $venue_id );
				$venue_address = get_post_meta( $venue_id, '_twec_venue_address', true );
				$venue_city    = get_post_meta( $venue_id, '_twec_venue_city', true );
				$venue_state   = get_post_meta( $venue_id, '_twec_venue_state', true );
				$venue_zip     = get_post_meta( $venue_id, '_twec_venue_zip', true );
				if ( $venue ) {
					$location = $venue->post_title;
					if ( $venue_address || $venue_city || $venue_state || $venue_zip ) {
						$address_parts = array_filter( array( $venue_address, $venue_city, $venue_state, $venue_zip ) );
						$location     .= ', ' . implode( ', ', $address_parts );
					}
					echo 'LOCATION:' . $this->ical_escape( $location ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo "END:VEVENT\r\n";
		}

		wp_reset_postdata();

		echo "END:VCALENDAR\r\n";

		$output = ob_get_clean();

		set_transient( $cache_key, $output, DAY_IN_SECONDS );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full .ics payload.
		echo $output;
		exit;
	}

	/**
	 * Bump ICS feed cache revision so subscribe URLs stop serving stale .ics payloads.
	 *
	 * @param int $post_id Event ID.
	 * @return void
	 */
	public function maybe_bump_public_ics_feed_cache( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		update_option( 'planit_pem_ics_feed_ver', absint( get_option( 'planit_pem_ics_feed_ver', 1 ) ) + 1 );
	}

	/**
	 * Hostname safe for iCal UID (fallback).
	 *
	 * @param string $host Site host.
	 * @return string
	 */
	private function ical_host_for_uid( $host ) {
		$host = preg_replace( '/[^a-zA-Z0-9.-]/', '', (string) $host );
		return $host ? $host : 'example.com';
	}

	/**
	 * Handle iCal export.
	 */
	public function handle_ical_export() {
		// Check if this is an export request first (handler runs on every page load via 'init' hook).
		$export = isset( $_GET['twec_export'] ) ? sanitize_text_field( wp_unslash( $_GET['twec_export'] ) ) : '';
		if ( 'ical' !== $export ) {
			return;
		}

		twec_verify_get_nonce_or_die( 'twec_export_ical' );

		// Public read-only export - no permission check needed, but nonce verification is required.
		// Access $_GET after nonce verification (WordPress.org security requirement).
		$event_id = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
		if ( ! $event_id ) {
			return;
		}

		$event = get_post( $event_id );
		if ( ! $event || 'twec_event' !== $event->post_type || 'publish' !== $event->post_status ) {
			return;
		}

		$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
		$end_date   = get_post_meta( $event_id, '_twec_event_end_date', true );
		$venue_id   = get_post_meta( $event_id, '_twec_event_venue', true );

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="event-' . absint( $event_id ) . '.ics"' );

		echo "BEGIN:VCALENDAR\r\n";
		echo "VERSION:2.0\r\n";
		echo "PRODID:-//PlanIt Event Manager//EN\r\n";
		echo "BEGIN:VEVENT\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - iCal format, not HTML
		$parsed_url = wp_parse_url( home_url() );
		$host       = isset( $parsed_url['host'] ) ? $parsed_url['host'] : 'example.com';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - iCal format, not HTML
		echo 'UID:event-' . absint( $event_id ) . '@' . esc_attr( $host ) . "\r\n";
		echo 'DTSTART:' . gmdate( 'Ymd\THis', strtotime( $start_date ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal format, not HTML
		echo 'DTEND:' . gmdate( 'Ymd\THis', strtotime( $end_date ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal format, not HTML
		echo 'SUMMARY:' . $this->ical_escape( $event->post_title ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal format, not HTML
		echo 'DESCRIPTION:' . $this->ical_escape( wp_strip_all_tags( $event->post_content ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal format, not HTML
		$event_url = get_permalink( $event_id );
		if ( $event_url ) {
			echo 'URL:' . esc_url( $event_url ) . "\r\n";
		}

		if ( $venue_id ) {
			$venue         = get_post( $venue_id );
			$venue_address = get_post_meta( $venue_id, '_twec_venue_address', true );
			$venue_city    = get_post_meta( $venue_id, '_twec_venue_city', true );
			$venue_state   = get_post_meta( $venue_id, '_twec_venue_state', true );
			$venue_zip     = get_post_meta( $venue_id, '_twec_venue_zip', true );

			$location = $venue->post_title;
			if ( $venue_address || $venue_city || $venue_state || $venue_zip ) {
				$address_parts = array_filter( array( $venue_address, $venue_city, $venue_state, $venue_zip ) );
				$location     .= ', ' . implode( ', ', $address_parts );
			}
			echo 'LOCATION:' . $this->ical_escape( $location ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal format, not HTML
		}

		echo "END:VEVENT\r\n";
		echo "END:VCALENDAR\r\n";
		exit;
	}

	/**
	 * Handle Google Calendar export.
	 */
	public function handle_google_calendar_export() {
		// Check if this is an export request first (handler runs on every page load via 'init' hook).
		$export = isset( $_GET['twec_export'] ) ? sanitize_text_field( wp_unslash( $_GET['twec_export'] ) ) : '';
		if ( 'google' !== $export ) {
			return;
		}

		twec_verify_get_nonce_or_die( 'twec_export_google' );

		// Public read-only export - no permission check needed, but nonce verification is required.
		// Access $_GET after nonce verification (WordPress.org security requirement).
		$event_id = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
		if ( ! $event_id ) {
			return;
		}

		$event = get_post( $event_id );
		if ( ! $event || 'twec_event' !== $event->post_type || 'publish' !== $event->post_status ) {
			return;
		}

		$start_date = get_post_meta( $event_id, '_twec_event_start_date', true );
		$end_date   = get_post_meta( $event_id, '_twec_event_end_date', true );

		$url             = 'https://www.google.com/calendar/render?action=TEMPLATE';
		$url            .= '&text=' . rawurlencode( $event->post_title );
		$url            .= '&dates=' . gmdate( 'Ymd\THis', strtotime( $start_date ) ) . '/' . gmdate( 'Ymd\THis', strtotime( $end_date ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - URL parameter, not HTML
		$url            .= '&details=' . rawurlencode( wp_strip_all_tags( $event->post_content ) );
		$event_permalink = get_permalink( $event_id );
		if ( $event_permalink ) {
			$url .= '&location=' . rawurlencode( $event_permalink );
		}

		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Encode data for a non-executable JSON script block (prevents closing-script injection).
	 *
	 * @param mixed $data Arbitrary data.
	 * @return string JSON string (empty on failure).
	 */
	private function wp_json_for_script_inline( $data ) {
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
			$flags |= JSON_UNESCAPED_SLASHES;
		}
		$out = wp_json_encode( $data, $flags );

		return is_string( $out ) ? $out : '';
	}

	/**
	 * Build GeoJSON-like marker payloads for the map view (shared by SSR and AJAX).
	 *
	 * @param array $events Event post objects from get_events_for_period().
	 * @return array<int, array<string, mixed>>
	 */
	private function build_map_marker_list_for_events( $events ) {
		$markers = array();

		foreach ( $events as $event ) {
			$venue_id = get_post_meta( $event->ID, '_twec_event_venue', true );
			if ( ! $venue_id ) {
				continue;
			}

			$venue = get_post( $venue_id );
			$lat   = get_post_meta( $venue_id, '_twec_venue_latitude', true );
			$lng   = get_post_meta( $venue_id, '_twec_venue_longitude', true );

			if ( ! $lat || ! $lng ) {
				continue;
			}

			$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );

			$markers[] = array(
				'lat'   => floatval( $lat ),
				'lng'   => floatval( $lng ),
				'title' => wp_strip_all_tags( get_the_title( $event->ID ) ),
				'url'   => esc_url_raw( get_permalink( $event->ID ) ),
				'venue' => $venue ? wp_strip_all_tags( $venue->post_title ) : '',
				'date'  => $start_date ? wp_strip_all_tags( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) : '',
			);
		}

		return $markers;
	}

	/**
	 * Render photo view.
	 *
	 * @param DateTime $date   Date object.
	 * @param array    $events Events array.
	 * @return string HTML output.
	 */
	private function render_photo_view( $date, $events ) {
		$html = '<div class="twec-calendar-photo-view">';

		foreach ( $events as $event ) {
			$thumbnail   = get_the_post_thumbnail( $event->ID, 'medium' );
			$start_date  = get_post_meta( $event->ID, '_twec_event_start_date', true );
			$is_featured = get_post_meta( $event->ID, '_twec_is_featured', true );

			$html .= '<div class="twec-photo-event' . ( $is_featured ? ' twec-featured' : '' ) . '">';
			if ( $thumbnail ) {
				$html .= '<a href="' . esc_url( get_permalink( $event->ID ) ) . '">' . $thumbnail . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- thumbnail is already escaped by get_the_post_thumbnail
			} else {
				$html .= '<div class="twec-photo-placeholder"></div>';
			}
			$html .= '<div class="twec-photo-event-info">';
			$html .= '<h3><a href="' . esc_url( get_permalink( $event->ID ) ) . '">' . esc_html( get_the_title( $event->ID ) ) . '</a></h3>';
			if ( $start_date ) {
				$html .= '<div class="twec-photo-event-date">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) . '</div>';
			}
			if ( has_excerpt( $event->ID ) ) {
				$html .= '<div class="twec-photo-event-excerpt">' . wp_kses_post( get_the_excerpt( $event->ID ) ) . '</div>';
			}
			$ticket_markup = $this->maybe_calendar_ticket_markup( (int) $event->ID );
			if ( '' !== $ticket_markup ) {
				$html .= '<div class="twec-photo-event-tickets">' . $ticket_markup . '</div>';
			}
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render map view.
	 *
	 * @param DateTime $date   Date object.
	 * @param array    $events Events array.
	 * @return string HTML output.
	 */
	private function render_map_view( $date, $events ) {
		$html        = '<div class="twec-calendar-map-view">';
		$html       .= '<div id="twec-map-container" class="twec-map-container"></div>';
		$map_markers = $this->build_map_marker_list_for_events( $events );
		if ( ! empty( $map_markers ) ) {
			$json_blob = $this->wp_json_for_script_inline( $map_markers );
			if ( '' !== $json_blob ) {
				$html .= '<textarea id="twec-calendar-map-markers-json" class="twec-map-markers-json" hidden readonly aria-hidden="true">';
				$html .= esc_textarea( $json_blob );
				$html .= '</textarea>';
			}
		}
		$html .= '<div class="twec-map-events-list">';

		foreach ( $events as $event ) {
			$venue_id = get_post_meta( $event->ID, '_twec_event_venue', true );
			if ( ! $venue_id ) {
				continue;
			}

			$venue = get_post( $venue_id );
			$lat   = get_post_meta( $venue_id, '_twec_venue_latitude', true );
			$lng   = get_post_meta( $venue_id, '_twec_venue_longitude', true );

			if ( ! $lat || ! $lng ) {
				continue;
			}

			$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );

			$html .= '<div class="twec-map-event-item">';
			$html .= '<h3><a href="' . esc_url( get_permalink( $event->ID ) ) . '">' . esc_html( get_the_title( $event->ID ) ) . '</a></h3>';
			if ( $venue ) {
				$html .= '<div class="twec-map-event-venue">' . esc_html( $venue->post_title ) . '</div>';
			}
			if ( $start_date ) {
				$html .= '<div class="twec-map-event-date">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ) . '</div>';
			}
			$ticket_markup = $this->maybe_calendar_ticket_markup( (int) $event->ID );
			if ( '' !== $ticket_markup ) {
				$html .= '<div class="twec-map-event-tickets">' . $ticket_markup . '</div>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Parse event date from various formats.
	 *
	 * @param string $date_string Date string.
	 * @return DateTime|false DateTime object or false on failure.
	 */
	private function parse_event_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return false;
		}

		// Try to parse the date.
		$date = DateTime::createFromFormat( 'Y-m-d H:i:s', $date_string );
		if ( ! $date ) {
			$date = DateTime::createFromFormat( 'Y-m-d H:i', $date_string );
		}
		if ( ! $date ) {
			$date = DateTime::createFromFormat( 'Y-m-d', $date_string );
		}
		if ( ! $date ) {
			// Try strtotime as fallback.
			$timestamp = strtotime( $date_string );
			if ( $timestamp ) {
				$date = new DateTime();
				$date->setTimestamp( $timestamp );
			}
		}

		return $date ? $date : false;
	}

	/**
	 * Escape text for iCal format.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	private function ical_escape( $text ) {
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( ',', '\\,', $text );
		$text = str_replace( ';', '\\;', $text );
		$text = str_replace( "\n", '\\n', $text );
		return $text;
	}
}
