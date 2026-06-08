<?php
/**
 * Search and filtering functionality.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-search.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search functionality for events.
 *
 * Handles event search, filtering, and RSS feed generation.
 */
class TWEC_Search {

	/**
	 * Initialize search functionality.
	 */
	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'modify_search_query' ) );
		add_action( 'wp', array( $this, 'add_search_widget' ) );
		add_action( 'init', array( $this, 'add_rss_feed' ) );
	}

	/**
	 * Modify search query to include events.
	 *
	 * @param WP_Query $query Query object.
	 */
	public function modify_search_query( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			$post_types = $query->get( 'post_type' );
			if ( empty( $post_types ) ) {
				$post_types = array( 'post', 'page', 'twec_event' );
			} elseif ( is_array( $post_types ) ) {
				$post_types[] = 'twec_event';
			} elseif ( is_string( $post_types ) ) {
				$post_types = array( $post_types, 'twec_event' );
			}
			$query->set( 'post_type', $post_types );
		}
	}

	/**
	 * Add search widget area.
	 */
	public function add_search_widget() {
		// This can be extended to add a search widget.
	}

	/**
	 * Add RSS feed for events (Premium feature).
	 */
	public function add_rss_feed() {
		// RSS feed is a premium feature.
		if ( TWEC_Premium::is_available( 'rss' ) ) {
			add_feed( 'events', array( $this, 'events_rss_feed' ) );
		}
	}

	/**
	 * Generate events RSS feed.
	 */
	public function events_rss_feed() {
		header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );

		// Optimized: Use DATE type instead of DATETIME for better performance.
		// Note: meta_query and meta_key are necessary for event calendar functionality. Performance can be improved with database indexes (see class-twec-activator.php).
		$events = get_posts(
			array(
				'post_type'      => 'twec_event',
				'posts_per_page' => 20,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for event calendar date ordering, optimized with DATE type. Database indexes recommended for production.
				'meta_key'       => '_twec_event_start_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for event calendar date filtering, optimized with DATE type. Database indexes recommended for production.
				'meta_query'     => array(
					array(
						'key'     => '_twec_event_end_date',
						'value'   => current_time( 'Y-m-d' ), // Use date-only format for DATE type.
						'compare' => '>=',
						'type'    => 'DATE', // DATE type is faster than DATETIME for date-only comparisons.
					),
				),
			)
		);

		$charset = get_option( 'blog_charset' );
		echo '<?xml version="1.0" encoding="' . esc_attr( $charset ) . '"?' . '>';
		?>
		<rss version="2.0"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:atom="http://www.w3.org/2005/Atom"
			xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
			<?php do_action( 'rss2_ns' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress RSS hook ?>
			<channel>
				<title><?php bloginfo_rss( 'name' ); ?> - <?php echo esc_html( __( 'Events', 'planit-event-manager' ) ); ?></title>
				<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
				<link><?php bloginfo_rss( 'url' ); ?></link>
				<description><?php bloginfo_rss( 'description' ); ?></description>
				<lastBuildDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', get_lastpostmodified( 'GMT' ), false ) ); ?></lastBuildDate>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<sy:updatePeriod><?php echo esc_html( apply_filters( 'rss_update_period', 'hourly' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress RSS hook ?></sy:updatePeriod>
				<sy:updateFrequency><?php echo esc_html( apply_filters( 'rss_update_frequency', '1' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress RSS hook ?></sy:updateFrequency>
				<?php
				foreach ( $events as $event ) {
					$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
					$venue_id   = get_post_meta( $event->ID, '_twec_event_venue', true );
					$venue      = $venue_id ? get_post( $venue_id ) : null;
					?>
					<item>
						<title><?php echo get_the_title_rss( $event->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RSS function is safe ?></title>
						<link><?php echo esc_url( get_permalink( $event->ID ) ); ?></link>
						<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', $event->post_date_gmt, false ) ); ?></pubDate>
						<dc:creator><![CDATA[<?php echo esc_html( get_the_author_meta( 'display_name', $event->post_author ) ); ?>]]></dc:creator>
						<guid isPermaLink="false"><?php echo esc_url( get_the_guid( $event->ID ) ); ?></guid>
						<description><![CDATA[<?php echo get_the_excerpt( $event->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RSS function is safe ?>]]></description>
						<content:encoded><![CDATA[<?php echo apply_filters( 'the_content', $event->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- RSS filter output is safe, core WordPress hook ?>]]></content:encoded>
						<?php if ( $start_date ) : ?>
							<event:startDate><?php echo esc_html( gmdate( 'Y-m-d\TH:i:s', strtotime( $start_date ) ) ); ?></event:startDate>
						<?php endif; ?>
						<?php if ( $venue ) : ?>
							<event:venue><![CDATA[<?php echo esc_html( $venue->post_title ); ?>]]></event:venue>
						<?php endif; ?>
					</item>
					<?php
				}
				?>
			</channel>
		</rss>
		<?php
		exit;
	}
}

// TWEC_Search is initialized by TWEC class.

