<?php
/**
 * Event SEO: JSON-LD and social (Open Graph / Twitter) tags for single events.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output Schema.org Event JSON-LD and optional social meta for shared links.
 */
class TWEC_SEO {

	/**
	 * Register front-end hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output_head' ), 5 );
	}

	/**
	 * Whether JSON-LD is enabled in settings.
	 *
	 * @return bool
	 */
	private static function is_json_ld_enabled() {
		$settings = get_option( 'twec_settings', array() );
		if ( isset( $settings['seo_json_ld'] ) && 'no' === $settings['seo_json_ld'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether to wrap JSON-LD in @graph (Organization + Event) for richer linking.
	 *
	 * @return bool
	 */
	private static function is_json_ld_graph_enabled() {
		$settings = get_option( 'twec_settings', array() );
		if ( isset( $settings['seo_json_ld_graph'] ) && 'yes' === $settings['seo_json_ld_graph'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether Open Graph / Twitter tags are enabled.
	 *
	 * @return bool
	 */
	private static function is_og_enabled() {
		$settings = get_option( 'twec_settings', array() );
		if ( isset( $settings['seo_og'] ) && 'no' === $settings['seo_og'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Print JSON-LD and social meta in wp_head.
	 *
	 * @return void
	 */
	public static function output_head() {
		if ( ! is_singular( 'twec_event' ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || 'twec_event' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! apply_filters( 'twec_seo_output_event_meta', true, $post ) ) {
			return;
		}

		$data = self::build_event_data( $post->ID );
		if ( empty( $data['name'] ) || empty( $data['url'] ) ) {
			return;
		}

		if ( self::is_json_ld_enabled() ) {
			$json_ld = self::build_json_ld( $data, $post->ID );
			if ( ! empty( $json_ld ) ) {
				$json_ld = apply_filters( 'twec_event_json_ld', $json_ld, $post->ID, $data );
				if ( ! empty( $json_ld ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD script; values escaped in build_json_ld.
					echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n";
				}
			}
		}

		if ( self::is_og_enabled() ) {
			self::print_og_twitter( $data, $post->ID );
		}
	}

	/**
	 * Build flat event data for JSON-LD and tags.
	 *
	 * @param int $event_id Post ID.
	 * @return array
	 */
	private static function build_event_data( $event_id ) {
		$title   = get_the_title( $event_id );
		$excerpt = has_excerpt( $event_id ) ? get_the_excerpt( $event_id ) : '';
		$desc    = $excerpt ? wp_strip_all_tags( $excerpt ) : '';
		$content = get_post_field( 'post_content', $event_id );
		if ( '' === $desc && $content ) {
			$desc = wp_strip_all_tags( wp_trim_words( $content, 40, '…' ) );
		}

		$url = get_permalink( $event_id );
		$img = get_the_post_thumbnail_url( $event_id, 'large' );
		$img = $img ? $img : '';

		$start    = get_post_meta( $event_id, '_twec_event_start_date', true );
		$end      = get_post_meta( $event_id, '_twec_event_end_date', true );
		$all_day  = get_post_meta( $event_id, '_twec_event_all_day', true );
		$is_alld  = in_array( (string) $all_day, array( '1', 'yes', 'true' ), true );
		$venue_id = (int) get_post_meta( $event_id, '_twec_event_venue', true );
		$org_id   = (int) get_post_meta( $event_id, '_twec_event_organizer', true );

		$attendance  = get_post_meta( $event_id, '_twec_event_attendance', true );
		$attendance  = in_array( (string) $attendance, array( 'online', 'mixed', 'in_person' ), true ) ? (string) $attendance : 'in_person';
		$virtual_url = get_post_meta( $event_id, '_twec_event_virtual_url', true );
		$virtual_url = $virtual_url ? esc_url_raw( (string) $virtual_url ) : '';

		$tz_string = get_post_meta( $event_id, '_twec_event_timezone', true );
		$tz_string = $tz_string ? $tz_string : wp_timezone_string();
		try {
			$tz = new DateTimeZone( $tz_string );
		} catch ( Exception $e ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.OverrideCustomPHPCS
			$tz = wp_timezone();
		}

		$start_iso = '';
		$end_iso   = '';
		if ( $start ) {
			if ( $is_alld ) {
				$start_iso = self::date_only( $start );
				$end_iso   = $end ? self::date_only( $end ) : $start_iso;
			} else {
				try {
					$start_dt  = new DateTime( $start, $tz );
					$start_iso = $start_dt->format( DATE_ATOM );
					if ( $end ) {
						$end_dt  = new DateTime( $end, $tz );
						$end_iso = $end_dt->format( DATE_ATOM );
					}
				} catch ( Exception $e ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.OverrideCustomPHPCS
					$start_iso = '';
					$end_iso   = '';
				}
			}
		}

		$place_location = self::get_location_for_schema( $venue_id, $event_id );
		$virtual_loc    = self::get_virtual_location_for_schema( $virtual_url, $event_id, $url );

		$cost   = get_post_meta( $event_id, '_twec_event_cost', true );
		$ext    = get_post_meta( $event_id, '_twec_event_website', true );
		$offers = array();
		if ( $cost ) {
			$offers = array(
				'@type'         => 'Offer',
				'price'         => wp_strip_all_tags( (string) $cost ),
				'priceCurrency' => apply_filters( 'twec_seo_event_currency', 'USD', $event_id ),
				'url'           => $url,
				'availability'  => 'https://schema.org/InStock',
			);
		}

		$organizer_schema = self::get_organizer_organization( $org_id, $event_id );
		$location_out     = self::merge_event_locations( $attendance, $place_location, $virtual_loc, $ext ? esc_url_raw( $ext ) : '' );

		return array(
			'name'              => $title,
			'description'       => $desc,
			'url'               => $url,
			'image'             => $img,
			'start_iso'         => $start_iso,
			'end_iso'           => $end_iso,
			'all_day'           => $is_alld,
			'attendance'        => $attendance,
			'location'          => $location_out,
			'offers'            => $offers,
			'event_website'     => $ext ? esc_url_raw( $ext ) : '',
			'organizer_schema'  => $organizer_schema,
			'organizer_post_id' => $org_id,
		);
	}

	/**
	 * Merge Place / VirtualLocation for schema based on attendance.
	 *
	 * @param string     $attendance   in_person|online|mixed.
	 * @param array|null $place         Place or null.
	 * @param array|null $virtual       VirtualLocation or null.
	 * @param string     $event_website Event external URL.
	 * @return array|\stdClass|array[]|null
	 */
	private static function merge_event_locations( $attendance, $place, $virtual, $event_website ) {
		if ( 'online' === $attendance ) {
			if ( is_array( $virtual ) && ! empty( $virtual['url'] ) ) {
				return $virtual;
			}
			if ( $event_website ) {
				return array(
					'@type' => 'VirtualLocation',
					'url'   => $event_website,
				);
			}
			return is_array( $virtual ) ? $virtual : null;
		}
		if ( 'mixed' === $attendance ) {
			$list = array();
			if ( is_array( $place ) ) {
				$list[] = $place;
			}
			if ( is_array( $virtual ) && ! empty( $virtual['url'] ) ) {
				$list[] = $virtual;
			} elseif ( $event_website ) {
				$list[] = array(
					'@type' => 'VirtualLocation',
					'url'   => $event_website,
				);
			}
			if ( empty( $list ) ) {
				return null;
			}
			if ( 1 === count( $list ) ) {
				return $list[0];
			}
			return $list;
		}
		// In-person.
		return is_array( $place ) ? $place : null;
	}

	/**
	 * Virtual location from meta or event permalink.
	 *
	 * @param string $virtual_url Virtual event URL.
	 * @param int    $event_id    Event post ID.
	 * @param string $permalink   Event URL.
	 * @return array|null
	 */
	private static function get_virtual_location_for_schema( $virtual_url, $event_id, $permalink ) {
		$url = $virtual_url;
		if ( ! $url ) {
			$url = $permalink;
		}
		$url = apply_filters( 'twec_seo_virtual_event_url', $url, $event_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}
		return array(
			'@type' => 'VirtualLocation',
			'url'   => $url,
		);
	}

	/**
	 * Build Organization for linked organizer or site fallback.
	 *
	 * @param int $organizer_id Organizer post ID.
	 * @param int $event_id     Event post ID.
	 * @return array
	 */
	private static function get_organizer_organization( $organizer_id, $event_id ) {
		if ( $organizer_id > 0 ) {
			$org = get_post( $organizer_id );
			if ( $org && 'publish' === $org->post_status && 'twec_organizer' === $org->post_type ) {
				$out = array(
					'@type' => 'Organization',
					'name'  => $org->post_title,
					'url'   => get_permalink( $organizer_id ),
				);
				$em  = get_post_meta( $organizer_id, '_twec_organizer_email', true );
				$ph  = get_post_meta( $organizer_id, '_twec_organizer_phone', true );
				$ws  = get_post_meta( $organizer_id, '_twec_organizer_website', true );
				if ( $em ) {
					$out['email'] = sanitize_email( (string) $em );
				}
				if ( $ph ) {
					$out['telephone'] = wp_strip_all_tags( (string) $ph );
				}
				if ( $ws ) {
					$out['sameAs'] = esc_url_raw( (string) $ws );
				}
				return apply_filters( 'twec_seo_organizer_organization', $out, $organizer_id, $event_id );
			}
		}
		return self::get_site_organization( $event_id );
	}

	/**
	 * Site-wide Organization as fallback organizer.
	 *
	 * @param int $event_id Event post ID.
	 * @return array
	 */
	private static function get_site_organization( $event_id ) {
		$name = get_bloginfo( 'name' );
		$out  = array(
			'@type' => 'Organization',
			'name'  => $name ? $name : 'Organization',
			'url'   => home_url( '/' ),
		);
		$logo = self::get_site_logo_url();
		if ( $logo ) {
			$out['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}
		return apply_filters( 'twec_seo_site_organization', $out, $event_id );
	}

	/**
	 * Custom logo or site icon URL.
	 *
	 * @return string
	 */
	private static function get_site_logo_url() {
		$custom_logo = get_theme_mod( 'custom_logo' );
		if ( $custom_logo ) {
			$img = wp_get_attachment_image_url( (int) $custom_logo, 'full' );
			if ( $img ) {
				return esc_url_raw( $img );
			}
		}
		$site_icon = get_site_icon_url( 512 );
		return $site_icon ? esc_url_raw( $site_icon ) : '';
	}

	/**
	 * Get date-only (Y-m-d) from stored start/end for all-day events.
	 *
	 * @param string $mysql Stored value.
	 * @return string
	 */
	private static function date_only( $mysql ) {
		if ( ! $mysql ) {
			return '';
		}
		$parts = explode( ' ', trim( $mysql ) );
		return isset( $parts[0] ) ? $parts[0] : '';
	}

	/**
	 * Build Place schema from venue.
	 *
	 * @param int $venue_id Venue post ID.
	 * @param int $event_id Event post ID.
	 * @return array|null
	 */
	private static function get_location_for_schema( $venue_id, $event_id ) {
		if ( ! $venue_id ) {
			return null;
		}
		$venue = get_post( $venue_id );
		if ( ! $venue || 'publish' !== $venue->post_status ) {
			return null;
		}
		$street = (string) get_post_meta( $venue_id, '_twec_venue_address', true );
		$city   = (string) get_post_meta( $venue_id, '_twec_venue_city', true );
		$state  = (string) get_post_meta( $venue_id, '_twec_venue_state', true );
		$zip    = (string) get_post_meta( $venue_id, '_twec_venue_zip', true );
		$region = (string) get_post_meta( $venue_id, '_twec_venue_country', true );
		$lat    = get_post_meta( $venue_id, '_twec_venue_latitude', true );
		$lng    = get_post_meta( $venue_id, '_twec_venue_longitude', true );

		$place = array(
			'@type'   => 'Place',
			'name'    => $venue->post_title,
			'address' => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'addressLocality' => $city,
				'addressRegion'   => $state,
				'postalCode'      => $zip,
				'addressCountry'  => $region,
			),
		);
		if ( $lat && $lng && is_numeric( $lat ) && is_numeric( $lng ) ) {
			$place['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}
		return apply_filters( 'twec_seo_venue_place', $place, $venue_id, $event_id );
	}

	/**
	 * Returns Schema.org EventAttendanceMode URL.
	 *
	 * @param string $attendance in_person|online|mixed.
	 * @return string
	 */
	private static function get_attendance_mode_url( $attendance ) {
		if ( 'online' === $attendance ) {
			return 'https://schema.org/OnlineEventAttendanceMode';
		}
		if ( 'mixed' === $attendance ) {
			return 'https://schema.org/MixedEventAttendanceMode';
		}
		return 'https://schema.org/OfflineEventAttendanceMode';
	}

	/**
	 * Build JSON-LD array (Event or @graph).
	 *
	 * @param array $data     From build_event_data.
	 * @param int   $event_id Post ID.
	 * @return array
	 */
	private static function build_json_ld( $data, $event_id ) {
		$attendance = isset( $data['attendance'] ) ? (string) $data['attendance'] : 'in_person';

		$event = array(
			'@type'               => 'Event',
			'name'                => $data['name'],
			'description'         => $data['description'] ? $data['description'] : $data['name'],
			'url'                 => $data['url'],
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => self::get_attendance_mode_url( $attendance ),
		);
		if ( ! empty( $data['image'] ) ) {
			$event['image'] = array( $data['image'] );
		}
		if ( ! empty( $data['start_iso'] ) ) {
			if ( ! empty( $data['all_day'] ) ) {
				$event['startDate'] = $data['start_iso'];
				$event['endDate']   = $data['end_iso'] ? $data['end_iso'] : $data['start_iso'];
			} else {
				$event['startDate'] = $data['start_iso'];
				if ( ! empty( $data['end_iso'] ) ) {
					$event['endDate'] = $data['end_iso'];
				}
			}
		}
		if ( ! empty( $data['location'] ) ) {
			$event['location'] = $data['location'];
		}
		if ( ! empty( $data['offers'] ) && is_array( $data['offers'] ) ) {
			$event['offers'] = $data['offers'];
		}
		if ( ! empty( $data['event_website'] ) ) {
			$event['sameAs'] = $data['event_website'];
		}
		$org_for_event = null;
		if ( ! empty( $data['organizer_schema'] ) && is_array( $data['organizer_schema'] ) ) {
			$org_for_event = $data['organizer_schema'];
		}
		if ( $org_for_event ) {
			$event['organizer'] = $org_for_event;
		}

		$context = 'https://schema.org';
		if ( ! self::is_json_ld_graph_enabled() ) {
			return array_merge( array( '@context' => $context ), $event );
		}

		$org_post_id = isset( $data['organizer_post_id'] ) ? (int) $data['organizer_post_id'] : 0;
		if ( $org_post_id > 0 && get_permalink( $org_post_id ) ) {
			$org_id_url = esc_url_raw( get_permalink( $org_post_id ) . '#planit-organization' );
		} else {
			$org_id_url = home_url( '/#planit-organization' );
		}
		$org_node        = isset( $data['organizer_schema'] ) && is_array( $data['organizer_schema'] ) ? $data['organizer_schema'] : self::get_site_organization( $event_id );
		$org_node['@id'] = $org_id_url;

		$ev_node                     = $event;
		$ev_node['@id']              = $data['url'] . '#event';
		$ev_node['organizer']        = array( '@id' => $org_id_url );
		$ev_node['mainEntityOfPage'] = array(
			'@type' => 'WebPage',
			'@id'   => $data['url'],
		);

		$graph = array( $org_node, $ev_node );
		$graph = apply_filters( 'twec_seo_json_ld_graph', $graph, $event_id, $data );

		return array(
			'@context' => $context,
			'@graph'   => $graph,
		);
	}

	/**
	 * Print Open Graph and Twitter tags.
	 *
	 * @param array $data     Event data.
	 * @param int   $event_id Post ID.
	 * @return void
	 */
	private static function print_og_twitter( $data, $event_id ) {
		$desc  = $data['description'] ? $data['description'] : $data['name'];
		$title = $data['name'];

		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( wp_html_excerpt( $desc, 300, '…' ) ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $data['url'] ) . '" />' . "\n";
		if ( ! empty( $data['image'] ) ) {
			echo '<meta property="og:image" content="' . esc_url( $data['image'] ) . '" />' . "\n";
		}
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( wp_html_excerpt( $desc, 200, '…' ) ) . '" />' . "\n";
		if ( ! empty( $data['image'] ) ) {
			echo '<meta name="twitter:image" content="' . esc_url( $data['image'] ) . '" />' . "\n";
		}
		/**
		 * Fires after default OG/Twitter tags for a PlanIt event.
		 *
		 * @param int   $event_id Post ID.
		 * @param array $data     Event data.
		 */
		do_action( 'twec_seo_event_social_meta', $event_id, $data );
	}
}
