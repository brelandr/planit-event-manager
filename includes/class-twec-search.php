<?php
/**
 * Search and filtering functionality.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
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
		// This can be extended to add a search widget
	}

	/**
	 * Add RSS feed for events (Premium feature).
	 */
	public function add_rss_feed() {
		// RSS feed is a premium feature
		if ( TWEC_Premium::is_available( 'rss' ) ) {
			add_feed( 'events', array( $this, 'events_rss_feed' ) );
		}
	}

	/**
	 * Generate events RSS feed.
	 */
	public function events_rss_feed() {
		header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );

		$events = get_posts( array(
			'post_type' => 'twec_event',
			'posts_per_page' => 20,
			'meta_key' => '_twec_event_start_date',
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key' => '_twec_event_end_date',
					'value' => current_time( 'mysql' ),
					'compare' => '>=',
					'type' => 'DATETIME',
				),
			),
		) );

		$charset = get_option( 'blog_charset' );
		echo '<?xml version="1.0" encoding="' . esc_attr( $charset ) . '"?' . '>';
		?>
		<rss version="2.0"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:atom="http://www.w3.org/2005/Atom"
			xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
			<?php do_action( 'rss2_ns' ); ?>>
			<channel>
				<title><?php bloginfo_rss( 'name' ); ?> - <?php _e( 'Events', 'the-wordpress-event-calendar' ); ?></title>
				<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
				<link><?php bloginfo_rss( 'url' ); ?></link>
				<description><?php bloginfo_rss( 'description' ); ?></description>
				<lastBuildDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_lastpostmodified( 'GMT' ), false ); ?></lastBuildDate>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
				<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
				<?php
				foreach ( $events as $event ) {
					$start_date = get_post_meta( $event->ID, '_twec_event_start_date', true );
					$venue_id = get_post_meta( $event->ID, '_twec_event_venue', true );
					$venue = $venue_id ? get_post( $venue_id ) : null;
					?>
					<item>
						<title><?php echo get_the_title_rss( $event->ID ); ?></title>
						<link><?php echo get_permalink( $event->ID ); ?></link>
						<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', $event->post_date_gmt, false ); ?></pubDate>
						<dc:creator><![CDATA[<?php echo get_the_author_meta( 'display_name', $event->post_author ); ?>]]></dc:creator>
						<guid isPermaLink="false"><?php echo get_the_guid( $event->ID ); ?></guid>
						<description><![CDATA[<?php echo get_the_excerpt( $event->ID ); ?>]]></description>
						<content:encoded><![CDATA[<?php echo apply_filters( 'the_content', $event->post_content ); ?>]]></content:encoded>
						<?php if ( $start_date ) : ?>
							<event:startDate><?php echo date( 'Y-m-d\TH:i:s', strtotime( $start_date ) ); ?></event:startDate>
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

new TWEC_Search();

