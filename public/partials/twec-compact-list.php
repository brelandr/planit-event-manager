<?php
/**
 * Compact list view: date, title, category — optional modal preview.
 *
 * @package The_Event_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals.

if ( ! isset( $twec_compact_link_behavior ) ) {
	$twec_compact_link_behavior = 'modal';
}
$twec_compact_use_modal = ( 'page' !== $twec_compact_link_behavior );
$twec_compact_dialog_id = 'twec-compact-dialog-' . wp_unique_id();
?>
<div class="twec-compact-list-wrapper" data-link-behavior="<?php echo esc_attr( $twec_compact_link_behavior ); ?>" data-dialog-id="<?php echo esc_attr( $twec_compact_dialog_id ); ?>">
	<?php if ( $events_query->have_posts() ) : ?>
		<table class="twec-compact-list" role="table">
			<thead>
				<tr>
					<th scope="col" class="twec-compact-list-col-date"><?php esc_html_e( 'Date', 'planit-event-manager' ); ?></th>
					<th scope="col" class="twec-compact-list-col-title"><?php esc_html_e( 'Event', 'planit-event-manager' ); ?></th>
					<th scope="col" class="twec-compact-list-col-category"><?php esc_html_e( 'Category', 'planit-event-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				while ( $events_query->have_posts() ) :
					$events_query->the_post();
					$event_id    = get_the_ID();
					$start_date  = get_post_meta( $event_id, '_twec_event_start_date', true );
					$permalink   = get_permalink( $event_id );
					$date_label  = '';
					if ( is_string( $start_date ) && '' !== $start_date ) {
						$ts = strtotime( $start_date );
						if ( $ts ) {
							$date_label = wp_date( get_option( 'date_format' ), $ts );
						}
					}
					$categories = get_the_terms( $event_id, 'twec_event_category' );
					$cat_names  = array();
					if ( is_array( $categories ) ) {
						foreach ( $categories as $term ) {
							if ( isset( $term->name ) ) {
								$cat_names[] = $term->name;
							}
						}
					}
					$cat_label = ! empty( $cat_names ) ? implode( ', ', $cat_names ) : '—';
					$excerpt   = has_excerpt( $event_id ) ? get_the_excerpt() : '';
					?>
					<tr class="twec-compact-list-row">
						<td class="twec-compact-list-date"><?php echo esc_html( $date_label ); ?></td>
						<td class="twec-compact-list-title">
							<?php if ( $twec_compact_use_modal ) : ?>
								<button
									type="button"
									class="twec-compact-list-trigger"
									data-event-id="<?php echo esc_attr( (string) $event_id ); ?>"
									data-event-url="<?php echo esc_url( $permalink ); ?>"
									data-event-title="<?php echo esc_attr( get_the_title() ); ?>"
									data-event-date="<?php echo esc_attr( $date_label ); ?>"
									data-event-category="<?php echo esc_attr( $cat_label ); ?>"
									data-event-excerpt="<?php echo esc_attr( wp_strip_all_tags( $excerpt ) ); ?>"
								><?php echo esc_html( get_the_title() ); ?></button>
							<?php else : ?>
								<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
							<?php endif; ?>
						</td>
						<td class="twec-compact-list-category"><?php echo esc_html( $cat_label ); ?></td>
					</tr>
					<?php
				endwhile;
				?>
			</tbody>
		</table>
		<?php
		$pagination = paginate_links(
			array(
				'total'     => $events_query->max_num_pages,
				'current'   => $paged,
				'prev_text' => __( '← Previous', 'planit-event-manager' ),
				'next_text' => __( 'Next →', 'planit-event-manager' ),
			)
		);
		if ( $pagination ) :
			?>
			<div class="twec-pagination twec-compact-list-pagination"><?php echo wp_kses_post( $pagination ); ?></div>
		<?php endif; ?>
		<?php if ( $twec_compact_use_modal ) : ?>
			<div
				id="<?php echo esc_attr( $twec_compact_dialog_id ); ?>"
				class="twec-compact-list-dialog"
				role="dialog"
				aria-modal="true"
				aria-hidden="true"
				aria-labelledby="<?php echo esc_attr( $twec_compact_dialog_id ); ?>-title"
				hidden
			>
				<div class="twec-compact-list-dialog__backdrop" data-twec-compact-close tabindex="-1"></div>
				<div class="twec-compact-list-dialog__panel" role="document">
					<button type="button" class="twec-compact-list-dialog__close" data-twec-compact-close aria-label="<?php esc_attr_e( 'Close', 'planit-event-manager' ); ?>">&times;</button>
					<h3 id="<?php echo esc_attr( $twec_compact_dialog_id ); ?>-title" class="twec-compact-list-dialog__title"></h3>
					<p class="twec-compact-list-dialog__meta"></p>
					<div class="twec-compact-list-dialog__body"></div>
					<p class="twec-compact-list-dialog__actions">
						<a class="button twec-compact-list-dialog__link" href="#"><?php esc_html_e( 'View full event', 'planit-event-manager' ); ?></a>
					</p>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<p class="twec-no-events"><?php esc_html_e( 'No events found.', 'planit-event-manager' ); ?></p>
	<?php endif; ?>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
