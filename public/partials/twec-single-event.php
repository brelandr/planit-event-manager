<?php
/**
 * Single event template.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage public/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

get_header();
?>
<div class="twec-single-event">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
			</header>

			<div class="entry-content">
				<?php
				$start_date = get_post_meta( get_the_ID(), '_twec_event_start_date', true );
				$end_date = get_post_meta( get_the_ID(), '_twec_event_end_date', true );
				$venue_id = get_post_meta( get_the_ID(), '_twec_event_venue', true );
				$organizer_id = get_post_meta( get_the_ID(), '_twec_event_organizer', true );
				$all_day = get_post_meta( get_the_ID(), '_twec_event_all_day', true );
				$event_cost = get_post_meta( get_the_ID(), '_twec_event_cost', true );
				$event_website = get_post_meta( get_the_ID(), '_twec_event_website', true );
				$event_timezone = get_post_meta( get_the_ID(), '_twec_event_timezone', true );
				?>

				<div class="twec-event-details">
					<?php if ( $start_date ) : ?>
						<div class="twec-event-date-time">
							<strong><?php esc_html_e( 'Date & Time:', 'the-wordpress-event-calendar' ); ?></strong>
							<?php
							// Handle timezone
							$display_timezone = $event_timezone ? $event_timezone : wp_timezone_string();
							$timezone_obj = new DateTimeZone( $display_timezone );
							$start_dt = new DateTime( $start_date, $timezone_obj );
							$end_dt = $end_date ? new DateTime( $end_date, $timezone_obj ) : null;
							
							if ( $all_day ) {
								echo esc_html( $start_dt->format( get_option( 'date_format' ) ) );
								if ( $end_dt && $start_dt->format( 'Y-m-d' ) !== $end_dt->format( 'Y-m-d' ) ) {
									echo ' - ' . esc_html( $end_dt->format( get_option( 'date_format' ) ) );
								}
								echo ' <span class="twec-all-day">(' . esc_html__( 'All Day', 'the-wordpress-event-calendar' ) . ')</span>';
							} else {
								echo esc_html( $start_dt->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
								if ( $end_dt ) {
									echo ' - ' . esc_html( $end_dt->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
								}
							}
							if ( $event_timezone ) {
								echo ' <span class="twec-timezone">(' . esc_html( $event_timezone ) . ')</span>';
							}
							?>
						</div>
					<?php endif; ?>
					
					<?php if ( $event_cost ) : ?>
						<div class="twec-event-cost">
							<strong><?php esc_html_e( 'Cost:', 'the-wordpress-event-calendar' ); ?></strong>
							<?php echo esc_html( $event_cost ); ?>
						</div>
					<?php endif; ?>
					
					<?php if ( $event_website ) : ?>
						<div class="twec-event-website">
							<strong><?php esc_html_e( 'Event Website:', 'the-wordpress-event-calendar' ); ?></strong>
							<a href="<?php echo esc_url( $event_website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $event_website ); ?></a>
						</div>
					<?php endif; ?>

					<?php if ( $venue_id ) : 
						$venue = get_post( $venue_id );
						if ( $venue ) :
							$venue_address = get_post_meta( $venue_id, '_twec_venue_address', true );
							$venue_city = get_post_meta( $venue_id, '_twec_venue_city', true );
							$venue_state = get_post_meta( $venue_id, '_twec_venue_state', true );
							$venue_zip = get_post_meta( $venue_id, '_twec_venue_zip', true );
							$venue_country = get_post_meta( $venue_id, '_twec_venue_country', true );
							$venue_phone = get_post_meta( $venue_id, '_twec_venue_phone', true );
							$venue_website = get_post_meta( $venue_id, '_twec_venue_website', true );
							$venue_lat = get_post_meta( $venue_id, '_twec_venue_latitude', true );
							$venue_lng = get_post_meta( $venue_id, '_twec_venue_longitude', true );
					?>
						<div class="twec-event-venue">
							<strong><?php esc_html_e( 'Venue:', 'the-wordpress-event-calendar' ); ?></strong>
							<h3><?php echo esc_html( $venue->post_title ); ?></h3>
							<?php if ( $venue_address || $venue_city || $venue_state || $venue_zip ) : ?>
								<div class="twec-venue-address">
									<?php
									$address_parts = array_filter( array( $venue_address, $venue_city, $venue_state, $venue_zip, $venue_country ) );
									echo esc_html( implode( ', ', $address_parts ) );
									?>
								</div>
							<?php endif; ?>
							<?php if ( $venue_phone ) : ?>
								<div class="twec-venue-phone"><?php echo esc_html( $venue_phone ); ?></div>
							<?php endif; ?>
							<?php if ( $venue_website ) : ?>
								<div class="twec-venue-website"><a href="<?php echo esc_url( $venue_website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $venue_website ); ?></a></div>
							<?php endif; ?>
							<?php if ( $venue_lat && $venue_lng ) : ?>
								<div class="twec-venue-map" data-lat="<?php echo esc_attr( $venue_lat ); ?>" data-lng="<?php echo esc_attr( $venue_lng ); ?>"></div>
							<?php endif; ?>
						</div>
					<?php endif; endif; ?>

					<?php if ( $organizer_id ) : 
						$organizer = get_post( $organizer_id );
						if ( $organizer ) :
							$organizer_phone = get_post_meta( $organizer_id, '_twec_organizer_phone', true );
							$organizer_email = get_post_meta( $organizer_id, '_twec_organizer_email', true );
							$organizer_website = get_post_meta( $organizer_id, '_twec_organizer_website', true );
					?>
						<div class="twec-event-organizer">
							<strong><?php esc_html_e( 'Organizer:', 'the-wordpress-event-calendar' ); ?></strong>
							<h3><?php echo esc_html( $organizer->post_title ); ?></h3>
							<?php if ( $organizer_phone ) : ?>
								<div class="twec-organizer-phone"><?php echo esc_html( $organizer_phone ); ?></div>
							<?php endif; ?>
							<?php if ( $organizer_email ) : ?>
								<div class="twec-organizer-email"><a href="mailto:<?php echo esc_attr( $organizer_email ); ?>"><?php echo esc_html( $organizer_email ); ?></a></div>
							<?php endif; ?>
							<?php if ( $organizer_website ) : ?>
								<div class="twec-organizer-website"><a href="<?php echo esc_url( $organizer_website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $organizer_website ); ?></a></div>
							<?php endif; ?>
						</div>
					<?php endif; endif; ?>

					<div class="twec-event-export">
						<a href="<?php echo esc_url( add_query_arg( array( 'twec_export' => 'ical', 'event_id' => get_the_ID() ), home_url() ) ); ?>" class="twec-export-btn"><?php esc_html_e( 'Add to iCal', 'the-wordpress-event-calendar' ); ?></a>
						<a href="<?php echo esc_url( add_query_arg( array( 'twec_export' => 'google', 'event_id' => get_the_ID() ), home_url() ) ); ?>" class="twec-export-btn" target="_blank" rel="noopener"><?php esc_html_e( 'Add to Google Calendar', 'the-wordpress-event-calendar' ); ?></a>
					</div>
				</div>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="twec-event-image">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>

				<div class="twec-event-description">
					<?php the_content(); ?>
				</div>

				<?php
				$categories = get_the_terms( get_the_ID(), 'twec_event_category' );
				$tags = get_the_terms( get_the_ID(), 'twec_event_tag' );
				?>
				<?php if ( $categories || $tags ) : ?>
					<footer class="entry-footer">
						<?php if ( $categories ) : ?>
							<div class="twec-event-categories">
								<strong><?php esc_html_e( 'Categories:', 'the-wordpress-event-calendar' ); ?></strong>
								<?php foreach ( $categories as $category ) : ?>
									<a href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( $tags ) : ?>
							<div class="twec-event-tags">
								<strong><?php esc_html_e( 'Tags:', 'the-wordpress-event-calendar' ); ?></strong>
								<?php foreach ( $tags as $tag ) : ?>
									<a href="<?php echo esc_url( get_term_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</footer>
				<?php endif; ?>
			</div>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();

