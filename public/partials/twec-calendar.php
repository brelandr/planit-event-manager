<?php
/**
 * Calendar view template.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage public/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$view = isset( $atts['view'] ) ? $atts['view'] : 'month';
$current_date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : current_time( 'Y-m-d' );
?>
<div class="twec-calendar-wrapper" data-view="<?php echo esc_attr( $view ); ?>" data-current-date="<?php echo esc_attr( $current_date ); ?>">
	<div class="twec-calendar-header">
		<div class="twec-calendar-nav">
			<button class="twec-nav-btn" data-action="prev"><?php esc_html_e( '← Previous', 'the-wordpress-event-calendar' ); ?></button>
			<h2 class="twec-calendar-title">
				<?php
				// Set initial title
				$date_obj = new DateTime( $current_date );
				switch ( $view ) {
					case 'day':
						echo esc_html( $date_obj->format( 'F j, Y' ) );
						break;
					case 'week':
						$start = clone $date_obj;
						$start->modify( 'monday this week' );
						$end = clone $start;
						$end->modify( '+6 days' );
						echo esc_html( $start->format( 'M j' ) . ' - ' . $end->format( 'M j, Y' ) );
						break;
					case 'month':
						echo esc_html( $date_obj->format( 'F Y' ) );
						break;
					case 'year':
						echo esc_html( $date_obj->format( 'Y' ) );
						break;
					default:
						echo esc_html( $date_obj->format( 'F Y' ) );
				}
				?>
			</h2>
			<button class="twec-nav-btn" data-action="next"><?php esc_html_e( 'Next →', 'the-wordpress-event-calendar' ); ?></button>
		</div>
		<div class="twec-view-switcher">
			<button class="twec-view-btn <?php echo 'day' === $view ? 'active' : ''; ?>" data-view="day"><?php esc_html_e( 'Day', 'the-wordpress-event-calendar' ); ?></button>
			<?php if ( TWEC_Premium::is_available( 'week' ) ) : ?>
				<button class="twec-view-btn <?php echo 'week' === $view ? 'active' : ''; ?>" data-view="week"><?php esc_html_e( 'Week', 'the-wordpress-event-calendar' ); ?></button>
			<?php else : ?>
				<button class="twec-view-btn twec-premium-locked" data-view="week" title="<?php esc_attresc_html_e( 'Week View - Premium Feature', 'the-wordpress-event-calendar' ); ?>">
					<?php esc_html_e( 'Week', 'the-wordpress-event-calendar' ); ?> <span class="twec-premium-badge">PRO</span>
				</button>
			<?php endif; ?>
			<button class="twec-view-btn <?php echo 'month' === $view ? 'active' : ''; ?>" data-view="month"><?php esc_html_e( 'Month', 'the-wordpress-event-calendar' ); ?></button>
			<?php if ( TWEC_Premium::is_available( 'year' ) ) : ?>
				<button class="twec-view-btn <?php echo 'year' === $view ? 'active' : ''; ?>" data-view="year"><?php esc_html_e( 'Year', 'the-wordpress-event-calendar' ); ?></button>
			<?php else : ?>
				<button class="twec-view-btn twec-premium-locked" data-view="year" title="<?php esc_attresc_html_e( 'Year View - Premium Feature', 'the-wordpress-event-calendar' ); ?>">
					<?php esc_html_e( 'Year', 'the-wordpress-event-calendar' ); ?> <span class="twec-premium-badge">PRO</span>
				</button>
			<?php endif; ?>
			<?php if ( TWEC_Premium::is_available( 'photo' ) ) : ?>
				<button class="twec-view-btn <?php echo 'photo' === $view ? 'active' : ''; ?>" data-view="photo"><?php esc_html_e( 'Photo', 'the-wordpress-event-calendar' ); ?></button>
			<?php else : ?>
				<button class="twec-view-btn twec-premium-locked" data-view="photo" title="<?php esc_attresc_html_e( 'Photo View - Premium Feature', 'the-wordpress-event-calendar' ); ?>">
					<?php esc_html_e( 'Photo', 'the-wordpress-event-calendar' ); ?> <span class="twec-premium-badge">PRO</span>
				</button>
			<?php endif; ?>
			<?php if ( TWEC_Premium::is_available( 'map' ) ) : ?>
				<button class="twec-view-btn <?php echo 'map' === $view ? 'active' : ''; ?>" data-view="map"><?php esc_html_e( 'Map', 'the-wordpress-event-calendar' ); ?></button>
			<?php else : ?>
				<button class="twec-view-btn twec-premium-locked" data-view="map" title="<?php esc_attresc_html_e( 'Map View - Premium Feature', 'the-wordpress-event-calendar' ); ?>">
					<?php esc_html_e( 'Map', 'the-wordpress-event-calendar' ); ?> <span class="twec-premium-badge">PRO</span>
				</button>
			<?php endif; ?>
		</div>
		<button class="twec-today-btn"><?php esc_html_e( 'Today', 'the-wordpress-event-calendar' ); ?></button>
	</div>
	<div class="twec-calendar-content">
		<div class="twec-calendar-loading">
			<?php esc_html_e( 'Loading calendar...', 'the-wordpress-event-calendar' ); ?>
		</div>
		<div class="twec-calendar-view">
			<?php
			// Load initial calendar view server-side to avoid AJAX dependency
			global $twec_public_instance;
			if ( $twec_public_instance ) {
				$initial_html = $twec_public_instance->render_calendar_view( $view, $current_date );
				// Output is already escaped in render methods
				echo $initial_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				// Fallback: create instance if not available
				$twec_public_instance = new TWEC_Public();
				$initial_html = $twec_public_instance->render_calendar_view( $view, $current_date );
				// Output is already escaped in render methods
				echo $initial_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
	</div>
	<?php if ( TWEC_Premium::is_available( 'rss' ) ) : ?>
		<div class="twec-calendar-rss-link">
			<a href="<?php echo esc_url( home_url( '/feed/events' ) ); ?>" class="button"><?php esc_html_e( 'Subscribe to Events RSS Feed', 'the-wordpress-event-calendar' ); ?></a>
		</div>
	<?php endif; ?>
</div>

