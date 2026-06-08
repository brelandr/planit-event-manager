<?php
/**
 * In-admin documentation (tabbed).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Tab navigation only.
$tab_raw = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( '' === $tab_raw ) {
	$tab_raw = 'overview';
}

$twec_docs_tabs = array(
	'overview'        => __( 'Overview', 'planit-event-manager' ),
	'content'         => __( 'Events, venues & organizers', 'planit-event-manager' ),
	'display'         => __( 'Display, shortcodes & blocks', 'planit-event-manager' ),
	'settings'        => __( 'Settings', 'planit-event-manager' ),
	'woocommerce'     => __( 'WooCommerce tickets', 'planit-event-manager' ),
	'payments'        => __( 'Featured listings (Stripe / PayPal)', 'planit-event-manager' ),
	'email-reminders' => __( 'Emails & reminders', 'planit-event-manager' ),
	'rsvp-door'       => __( 'RSVP & door check-in (Premium)', 'planit-event-manager' ),
	'tools'           => __( 'Diagnostics & tools', 'planit-event-manager' ),
	'premium'         => __( 'Premium features', 'planit-event-manager' ),
);

if ( ! isset( $twec_docs_tabs[ $tab_raw ] ) ) {
	$tab_raw = 'overview';
}

$twec_docs_base_url = admin_url( 'edit.php?post_type=twec_event&page=twec-documentation' );
$twec_docs_file     = PLANIT_EVENT_MANAGER_DIR . 'admin/partials/documentation/tab-' . $tab_raw . '.php';

?>
<div class="wrap twec-docs">
	<h1><?php esc_html_e( 'PlanIt Event Manager — documentation', 'planit-event-manager' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Use the tabs below to jump between major topics. Anything labeled Premium requires PlanIt Event Manager Premium to be installed and a valid Premium license.', 'planit-event-manager' ); ?>
	</p>

	<h2 class="screen-reader-text"><?php esc_html_e( 'Documentation sections', 'planit-event-manager' ); ?></h2>
	<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Documentation tabs', 'planit-event-manager' ); ?>">
		<?php foreach ( $twec_docs_tabs as $slug => $label ) : ?>
			<?php
			$tab_url = add_query_arg( 'tab', $slug, $twec_docs_base_url );
			$active  = ( $slug === $tab_raw );
			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab<?php echo $active ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="twec-docs-panel">
		<?php
		if ( is_readable( $twec_docs_file ) ) {
			require $twec_docs_file;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Documentation section not found.', 'planit-event-manager' ) . '</p></div>';
		}
		?>
	</div>
</div>
