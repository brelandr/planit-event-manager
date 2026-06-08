<?php
/**
 * Calendar view template.
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
$view = isset( $atts['view'] ) ? $atts['view'] : 'month';
// Use WordPress query var instead of direct $_GET access (WordPress.org security requirement).
// date is registered as a query var in register_query_vars().
$current_date = sanitize_text_field( get_query_var( 'date' ) );
if ( empty( $current_date ) ) {
	$current_date = current_time( 'Y-m-d' );
}

try {
	$date_obj = new DateTime( $current_date );
} catch ( Exception $e ) {
	$date_obj = new DateTime( current_time( 'Y-m-d' ) );
}

switch ( $view ) {
	case 'day':
		$calendar_title = $date_obj->format( 'F j, Y' );
		break;
	case 'week':
		$start = clone $date_obj;
		$start->modify( 'monday this week' );
		$end = clone $start;
		$end->modify( '+6 days' );
		$calendar_title = $start->format( 'M j' ) . ' - ' . $end->format( 'M j, Y' );
		break;
	case 'month':
		$calendar_title = $date_obj->format( 'F Y' );
		break;
	case 'year':
		$calendar_title = $date_obj->format( 'Y' );
		break;
	default:
		$calendar_title = $date_obj->format( 'F Y' );
}

$twec_cal_atts = isset( $atts ) && is_array( $atts ) ? $atts : array();
$twec_cal_cat  = isset( $twec_cal_atts['category'] ) && is_scalar( $twec_cal_atts['category'] ) ? (string) $twec_cal_atts['category'] : '';
$twec_cal_tag  = isset( $twec_cal_atts['tag'] ) && is_scalar( $twec_cal_atts['tag'] ) ? (string) $twec_cal_atts['tag'] : '';
// Match `TWEC_Shortcodes::sanitize_shortcode_text` (typographic quotes from pasted shortcodes).
$twec_cal_quote_chars  = array( "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}" );
$twec_cal_cat          = trim( str_replace( $twec_cal_quote_chars, array( '', '', '', '' ), $twec_cal_cat ) );
$twec_cal_tag          = trim( str_replace( $twec_cal_quote_chars, array( '', '', '', '' ), $twec_cal_tag ) );
$twec_calendar_filters = array();
if ( '' !== $twec_cal_cat ) {
	$twec_calendar_filters['category'] = sanitize_title( $twec_cal_cat );
}
if ( '' !== $twec_cal_tag ) {
	$twec_calendar_filters['tag'] = sanitize_title( $twec_cal_tag );
}

global $twec_public_instance;
if ( $twec_public_instance ) {
	$initial_html = $twec_public_instance->render_calendar_view( $view, $current_date, $twec_calendar_filters );
} else {
	$twec_public_instance = new TWEC_Public();
	$initial_html         = $twec_public_instance->render_calendar_view( $view, $current_date, $twec_calendar_filters );
}

$twec_use_interactivity = function_exists( 'twec_calendar_should_use_interactivity' ) && twec_calendar_should_use_interactivity( isset( $atts ) && is_array( $atts ) ? $atts : array() );

$ticket_cta_calendar = ! empty( $GLOBALS['twec_calendar_show_ticket_ctas'] );

$twec_show_quick_add = class_exists( 'TWEC_Public', false )
	&& TWEC_Public::user_can_quick_add_event()
	&& apply_filters( 'twec_quick_add_show', true );

if ( $twec_use_interactivity ) {
	wp_interactivity_state(
		'planit/calendar',
		array(
			'title'        => $calendar_title,
			'calendarHtml' => $initial_html,
			'isLoading'    => false,
			'view'         => $view,
			'date'         => $current_date,
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'twec_ajax_get_calendar' ),
			'calPub'       => TWEC_Public::calendar_ajax_public_hash_localized(),
			'ticketCta'    => ! empty( $ticket_cta_calendar ) ? '1' : '0',
			'category'     => isset( $twec_calendar_filters['category'] ) ? (string) $twec_calendar_filters['category'] : '',
			'tag'          => isset( $twec_calendar_filters['tag'] ) ? (string) $twec_calendar_filters['tag'] : '',
		)
	);
}

?>
<div class="twec-calendar-wrapper" data-view="<?php echo esc_attr( $view ); ?>" data-current-date="<?php echo esc_attr( $current_date ); ?>" data-twec-ticket-cta="<?php echo ! empty( $ticket_cta_calendar ) ? '1' : '0'; ?>" data-twec-category="<?php echo isset( $twec_calendar_filters['category'] ) ? esc_attr( $twec_calendar_filters['category'] ) : ''; ?>" data-twec-tag="<?php echo isset( $twec_calendar_filters['tag'] ) ? esc_attr( $twec_calendar_filters['tag'] ) : ''; ?>"<?php echo $twec_use_interactivity ? ' data-wp-interactive="planit/calendar"' : ''; ?>>
	<div class="twec-calendar-header">
		<div class="twec-calendar-nav">
			<?php if ( $twec_use_interactivity ) : ?>
				<button type="button" class="twec-nav-btn" data-action="prev" data-wp-on--click="actions.navigate"><?php esc_html_e( '← Previous', 'planit-event-manager' ); ?></button>
				<h2 class="twec-calendar-title" data-wp-text="state.title"></h2>
				<button type="button" class="twec-nav-btn" data-action="next" data-wp-on--click="actions.navigate"><?php esc_html_e( 'Next →', 'planit-event-manager' ); ?></button>
			<?php else : ?>
				<button type="button" class="twec-nav-btn" data-action="prev"><?php esc_html_e( '← Previous', 'planit-event-manager' ); ?></button>
				<h2 class="twec-calendar-title"><?php echo esc_html( $calendar_title ); ?></h2>
				<button type="button" class="twec-nav-btn" data-action="next"><?php esc_html_e( 'Next →', 'planit-event-manager' ); ?></button>
			<?php endif; ?>
		</div>
		<div class="twec-view-switcher">
			<?php
			$day_active   = ( 'day' === $view ) ? 'active' : '';
			$week_active  = ( 'week' === $view ) ? 'active' : '';
			$month_active = ( 'month' === $view ) ? 'active' : '';
			$year_active  = ( 'year' === $view ) ? 'active' : '';
			$photo_active = ( 'photo' === $view ) ? 'active' : '';
			$map_active   = ( 'map' === $view ) ? 'active' : '';
			?>
			<?php if ( $twec_use_interactivity ) : ?>
				<button type="button" class="twec-view-btn <?php echo esc_attr( $day_active ); ?>" data-view="day" data-wp-on--click="actions.setView"><?php esc_html_e( 'Day', 'planit-event-manager' ); ?></button>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'week' ) ) : ?>
					<button type="button" class="twec-view-btn <?php echo esc_attr( $week_active ); ?>" data-view="week" data-wp-on--click="actions.setView"><?php esc_html_e( 'Week', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
				<button type="button" class="twec-view-btn <?php echo esc_attr( $month_active ); ?>" data-view="month" data-wp-on--click="actions.setView"><?php esc_html_e( 'Month', 'planit-event-manager' ); ?></button>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'year' ) ) : ?>
					<button type="button" class="twec-view-btn <?php echo esc_attr( $year_active ); ?>" data-view="year" data-wp-on--click="actions.setView"><?php esc_html_e( 'Year', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'photo' ) ) : ?>
					<button type="button" class="twec-view-btn <?php echo esc_attr( $photo_active ); ?>" data-view="photo" data-wp-on--click="actions.setView"><?php esc_html_e( 'Photo', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'map' ) ) : ?>
					<button type="button" class="twec-view-btn <?php echo esc_attr( $map_active ); ?>" data-view="map" data-wp-on--click="actions.setView"><?php esc_html_e( 'Map', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
			<?php else : ?>
				<button class="twec-view-btn <?php echo esc_attr( $day_active ); ?>" data-view="day"><?php esc_html_e( 'Day', 'planit-event-manager' ); ?></button>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'week' ) ) : ?>
					<button class="twec-view-btn <?php echo esc_attr( $week_active ); ?>" data-view="week"><?php esc_html_e( 'Week', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
				<button class="twec-view-btn <?php echo esc_attr( $month_active ); ?>" data-view="month"><?php esc_html_e( 'Month', 'planit-event-manager' ); ?></button>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'year' ) ) : ?>
					<button class="twec-view-btn <?php echo esc_attr( $year_active ); ?>" data-view="year"><?php esc_html_e( 'Year', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'photo' ) ) : ?>
					<button class="twec-view-btn <?php echo esc_attr( $photo_active ); ?>" data-view="photo"><?php esc_html_e( 'Photo', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
				<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'map' ) ) : ?>
					<button class="twec-view-btn <?php echo esc_attr( $map_active ); ?>" data-view="map"><?php esc_html_e( 'Map', 'planit-event-manager' ); ?></button>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php if ( $twec_use_interactivity ) : ?>
			<button type="button" class="twec-today-btn" data-wp-on--click="actions.today"><?php esc_html_e( 'Today', 'planit-event-manager' ); ?></button>
		<?php else : ?>
			<button type="button" class="twec-today-btn"><?php esc_html_e( 'Today', 'planit-event-manager' ); ?></button>
		<?php endif; ?>
		<?php if ( $twec_show_quick_add ) : ?>
			<button type="button" class="button twec-quick-add-open" aria-haspopup="dialog" aria-controls="twec-quick-add-dialog"><?php esc_html_e( 'Quick add event', 'planit-event-manager' ); ?></button>
		<?php endif; ?>
	</div>
	<div class="twec-calendar-content">
		<div class="twec-calendar-loading"<?php echo $twec_use_interactivity ? ' data-wp-class--is-loading="state.isLoading"' : ''; ?>>
			<?php esc_html_e( 'Loading calendar...', 'planit-event-manager' ); ?>
		</div>
		<div class="twec-calendar-view" <?php echo $twec_use_interactivity ? ' data-wp-html="state.calendarHtml"' : ''; ?>>
			<?php
			// Always SSR the first paint: Interactivity `data-wp-html` depends on client hydration + merged
			// server state. If the module fails or state is empty, omitting this leaves a blank calendar
			// (jQuery init skips [data-wp-interactive] wrappers). Interactive navigation still updates via state.
			echo $initial_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Server-rendered calendar HTML is escaped in render methods.
			?>
		</div>
	</div>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
<?php if ( function_exists( 'twec_premium_view_available' ) && twec_premium_view_available( 'rss' ) ) : ?>
	<div class="twec-calendar-rss-link">
		<a href="<?php echo esc_url( home_url( '/feed/events' ) ); ?>" class="button"><?php esc_html_e( 'Subscribe to Events RSS Feed', 'planit-event-manager' ); ?></a>
	</div>
<?php endif; ?>
<?php if ( $twec_show_quick_add ) : ?>
	<?php $twec_quick_default_day = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $current_date ) ? (string) $current_date : (string) current_time( 'Y-m-d' ); ?>
	<?php $twec_quick_can_publish = current_user_can( 'publish_posts' ); ?>
	<dialog id="twec-quick-add-dialog" class="twec-quick-add-dialog" aria-labelledby="twec-quick-add-title">
		<form id="twec-quick-add-form" class="twec-quick-add-form" novalidate>
			<div class="twec-quick-add-header">
				<h2 id="twec-quick-add-title"><?php esc_html_e( 'Quick add event', 'planit-event-manager' ); ?></h2>
			</div>
			<div class="twec-quick-add-fields" role="group" aria-describedby="twec-quick-add-desc">
				<p id="twec-quick-add-desc" class="screen-reader-text"><?php esc_html_e( 'Add a minimal event linked to Event Data.', 'planit-event-manager' ); ?></p>
				<p class="twec-quick-add-field">
					<label for="twec-quick-add-title-field"><?php esc_html_e( 'Title', 'planit-event-manager' ); ?> <abbr title="<?php echo esc_attr__( 'required', 'planit-event-manager' ); ?>">*</abbr></label>
					<input type="text" id="twec-quick-add-title-field" name="twec_quick_title" class="regular-text twec-quick-add-title-field" autocomplete="off" required />
				</p>
				<p class="twec-quick-add-field twec-quick-add-checkbox">
					<label><input type="checkbox" id="twec-quick-add-all-day" name="twec_quick_all_day" value="1" /> <?php esc_html_e( 'All day', 'planit-event-manager' ); ?></label>
				</p>
				<p class="twec-quick-add-field twec-quick-add-times">
					<span class="twec-quick-add-time-start">
						<label for="twec-quick-add-start-time"><?php esc_html_e( 'Start time', 'planit-event-manager' ); ?></label>
						<input type="time" id="twec-quick-add-start-time" step="60" />
					</span>
					<span class="twec-quick-add-time-end">
						<label for="twec-quick-add-end-time"><?php esc_html_e( 'End time', 'planit-event-manager' ); ?></label>
						<input type="time" id="twec-quick-add-end-time" step="60" />
					</span>
				</p>
				<p class="twec-quick-add-field">
					<label for="twec-quick-add-start-date"><?php esc_html_e( 'Start date', 'planit-event-manager' ); ?> <abbr title="<?php echo esc_attr__( 'required', 'planit-event-manager' ); ?>">*</abbr></label>
					<input type="date" id="twec-quick-add-start-date" name="twec_quick_start_date" required value="<?php echo esc_attr( $twec_quick_default_day ); ?>" />
				</p>
				<p class="twec-quick-add-field">
					<label for="twec-quick-add-end-date"><?php esc_html_e( 'End date', 'planit-event-manager' ); ?> <abbr title="<?php echo esc_attr__( 'required', 'planit-event-manager' ); ?>">*</abbr></label>
					<input type="date" id="twec-quick-add-end-date" name="twec_quick_end_date" required value="<?php echo esc_attr( $twec_quick_default_day ); ?>" />
				</p>
				<?php if ( $twec_quick_can_publish ) : ?>
					<p class="twec-quick-add-field">
						<label for="twec-quick-add-status"><?php esc_html_e( 'Status', 'planit-event-manager' ); ?></label>
						<select id="twec-quick-add-status" name="twec_quick_status">
							<option value="draft"><?php esc_html_e( 'Draft', 'planit-event-manager' ); ?></option>
							<option value="publish"><?php esc_html_e( 'Published', 'planit-event-manager' ); ?></option>
						</select>
					</p>
				<?php else : ?>
					<input type="hidden" id="twec-quick-add-status" name="twec_quick_status" value="draft" />
				<?php endif; ?>
			</div>
			<p class="twec-quick-add-message twec-quick-add-feedback" role="alert" aria-live="polite" hidden></p>
			<div class="twec-quick-add-actions">
				<button type="button" class="button twec-quick-add-cancel"><?php esc_html_e( 'Cancel', 'planit-event-manager' ); ?></button>
				<button type="submit" class="button button-primary twec-quick-add-submit"><?php esc_html_e( 'Save event', 'planit-event-manager' ); ?></button>
			</div>
		</form>
	</dialog>
<?php endif; ?>
</div>
