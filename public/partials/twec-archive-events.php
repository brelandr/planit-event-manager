<?php
/**
 * Archive events template.
 *
 * @package    The_Event_Calendar
 * @subpackage public/partials
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local scope, not globals

get_header();
?>
<div class="twec-archive-wrapper">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Events', 'planit-event-manager' ); ?></h1>
	</header>

	<div class="twec-archive-search" style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px;">
		<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'twec_event' ) ); ?>">
			<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
				<input type="text" name="s" value="<?php echo esc_attr( get_query_var( 's' ) ); ?>" placeholder="<?php esc_html_e( 'Search events...', 'planit-event-manager' ); ?>" style="flex: 1; min-width: 200px; padding: 8px;" />
				<?php
				// Use WordPress query var instead of direct $_GET access (WordPress.org security requirement).
				// event_category is registered as a query var in register_query_vars().
				$selected_category = sanitize_key( get_query_var( 'event_category' ) );
				$categories        = get_terms(
					array(
						'taxonomy'   => 'twec_event_category',
						'hide_empty' => false,
					)
				);
				if ( ! empty( $categories ) ) :
					?>
					<select name="event_category" style="padding: 8px;">
						<option value=""><?php esc_html_e( 'All Categories', 'planit-event-manager' ); ?></option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $selected_category, $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button type="submit" class="button" style="padding: 8px 20px;"><?php esc_html_e( 'Search', 'planit-event-manager' ); ?></button>
			</div>
		</form>
	</div>

	<div class="twec-archive-controls">
		<?php
		// Use WordPress query var instead of direct $_GET access (WordPress.org security requirement).
		// view is registered as a query var in register_query_vars().
		$view            = sanitize_key( get_query_var( 'view' ) );
		$calendar_active = ( 'calendar' === $view ) ? 'active' : '';
		$list_active     = ( 'calendar' !== $view ) ? 'active' : '';
		?>
		<a href="<?php echo esc_url( add_query_arg( 'view', 'calendar', get_post_type_archive_link( 'twec_event' ) ) ); ?>" class="twec-view-link <?php echo esc_attr( $calendar_active ); ?>"><?php esc_html_e( 'Calendar View', 'planit-event-manager' ); ?></a>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'twec_event' ) ); ?>" class="twec-view-link <?php echo esc_attr( $list_active ); ?>"><?php esc_html_e( 'List View', 'planit-event-manager' ); ?></a>
	</div>

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public-facing filter query, not a form submission.
		// Use already sanitized $view variable instead of re-sanitizing $_GET.
		if ( 'calendar' === $view ) :
			?>
			<?php echo do_shortcode( '[twec_calendar]' ); ?>
	<?php else : ?>
		<?php
		// Get settings for events per page.
		$settings     = get_option( 'twec_settings', array() );
		$events_limit = isset( $settings['events_per_page'] ) ? intval( $settings['events_per_page'] ) : 10;
		$hide_past    = isset( $settings['hide_past_events'] ) && 'yes' === $settings['hide_past_events'] ? 'hide' : 'show';
		echo do_shortcode( '[twec_list per_page="' . esc_attr( $events_limit ) . '" past_events="' . esc_attr( $hide_past ) . '"]' );
		?>
	<?php endif; ?>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
get_footer();

