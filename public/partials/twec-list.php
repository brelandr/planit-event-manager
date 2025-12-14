<?php
/**
 * List view template.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage public/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="twec-list-wrapper">
	<?php if ( $events_query->have_posts() ) : ?>
		<div class="twec-events-list">
			<?php while ( $events_query->have_posts() ) : $events_query->the_post(); ?>
				<?php
				$start_date = get_post_meta( get_the_ID(), '_twec_event_start_date', true );
				$end_date = get_post_meta( get_the_ID(), '_twec_event_end_date', true );
				$venue_id = get_post_meta( get_the_ID(), '_twec_event_venue', true );
				$all_day = get_post_meta( get_the_ID(), '_twec_event_all_day', true );
				?>
				<article class="twec-event-item">
					<div class="twec-event-date">
						<?php if ( $start_date ) : ?>
							<span class="twec-date-day"><?php echo esc_html( date_i18n( 'd', strtotime( $start_date ) ) ); ?></span>
							<span class="twec-date-month"><?php echo esc_html( date_i18n( 'M', strtotime( $start_date ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="twec-event-content">
						<h3 class="twec-event-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<div class="twec-event-meta">
							<?php if ( $start_date ) : ?>
								<span class="twec-event-time">
									<?php
									if ( $all_day ) {
										echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) );
										if ( $end_date && $start_date !== $end_date ) {
											echo ' - ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) );
										}
										echo ' ';
										esc_html_e( '(All Day)', 'the-wordpress-event-calendar' );
									} else {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start_date ) ) );
										if ( $end_date ) {
											echo ' - ' . esc_html( date_i18n( get_option( 'time_format' ), strtotime( $end_date ) ) );
										}
									}
									?>
								</span>
							<?php endif; ?>
							<?php if ( $venue_id ) : 
								$venue = get_post( $venue_id );
								if ( $venue ) :
							?>
								<span class="twec-event-venue">
									<?php echo esc_html( $venue->post_title ); ?>
								</span>
							<?php endif; endif; ?>
							<?php 
							$event_cost = get_post_meta( get_the_ID(), '_twec_event_cost', true );
							if ( $event_cost ) :
							?>
								<span class="twec-event-cost">
									<?php echo esc_html( $event_cost ); ?>
								</span>
							<?php endif; ?>
						</div>
						<?php if ( has_excerpt() ) : ?>
							<div class="twec-event-excerpt"><?php the_excerpt(); ?></div>
						<?php endif; ?>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		$pagination = paginate_links( array(
			'total' => $events_query->max_num_pages,
			'current' => $paged,
			'prev_text' => __( '← Previous', 'the-wordpress-event-calendar' ),
			'next_text' => __( 'Next →', 'the-wordpress-event-calendar' ),
		) );
		if ( $pagination ) :
		?>
			<div class="twec-pagination"><?php echo wp_kses_post( $pagination ); ?></div>
		<?php endif; ?>
	<?php else : ?>
		<p class="twec-no-events"><?php esc_html_e( 'No events found.', 'the-wordpress-event-calendar' ); ?></p>
	<?php endif; ?>
</div>

