<?php
/**
 * Archive events template.
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
<div class="twec-archive-wrapper">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Events', 'the-wordpress-event-calendar' ); ?></h1>
	</header>

	<div class="twec-archive-search" style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px;">
		<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'twec_event' ) ); ?>">
			<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
				<input type="text" name="s" value="<?php echo esc_attr( get_query_var( 's' ) ); ?>" placeholder="<?php esc_html_e( 'Search events...', 'the-wordpress-event-calendar' ); ?>" style="flex: 1; min-width: 200px; padding: 8px;" />
				<?php
				$selected_category = isset( $_GET['event_category'] ) ? sanitize_text_field( $_GET['event_category'] ) : '';
				$categories = get_terms( array( 'taxonomy' => 'twec_event_category', 'hide_empty' => false ) );
				if ( ! empty( $categories ) ) :
				?>
					<select name="event_category" style="padding: 8px;">
						<option value=""><?php esc_html_e( 'All Categories', 'the-wordpress-event-calendar' ); ?></option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $selected_category, $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button type="submit" class="button" style="padding: 8px 20px;"><?php esc_html_e( 'Search', 'the-wordpress-event-calendar' ); ?></button>
			</div>
		</form>
	</div>

	<div class="twec-archive-controls">
		<a href="<?php echo esc_url( add_query_arg( 'view', 'calendar', get_post_type_archive_link( 'twec_event' ) ) ); ?>" class="twec-view-link <?php echo ( isset( $_GET['view'] ) && 'calendar' === $_GET['view'] ) ? 'active' : ''; ?>"><?php esc_html_e( 'Calendar View', 'the-wordpress-event-calendar' ); ?></a>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'twec_event' ) ); ?>" class="twec-view-link <?php echo ( ! isset( $_GET['view'] ) || 'calendar' !== $_GET['view'] ) ? 'active' : ''; ?>"><?php esc_html_e( 'List View', 'the-wordpress-event-calendar' ); ?></a>
	</div>

	<?php if ( isset( $_GET['view'] ) && 'calendar' === $_GET['view'] ) : ?>
		<?php echo do_shortcode( '[twec_calendar]' ); ?>
	<?php else : ?>
		<?php 
		// Get settings for events per page
		$settings = get_option( 'twec_settings', array() );
		$per_page = isset( $settings['events_per_page'] ) ? intval( $settings['events_per_page'] ) : 10;
		$hide_past = isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] ? 'hide' : 'show';
		echo do_shortcode( '[twec_list per_page="' . $per_page . '" past_events="' . $hide_past . '"]' ); 
		?>
	<?php endif; ?>
</div>
<?php
get_footer();

