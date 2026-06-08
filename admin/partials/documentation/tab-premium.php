<?php
/**
 * Documentation tab: Premium features (reference).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$premium_features = class_exists( 'TWEC_Premium', false ) ? TWEC_Premium::get_premium_features() : array();
$upgrade_url      = class_exists( 'TWEC_Premium', false ) ? TWEC_Premium::UPGRADE_URL : '#';
?>

<h2><?php esc_html_e( 'What “Premium + license” means', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Install PlanIt Event Manager Premium alongside this free plugin and enter a valid license. The free package loads premium modules only when the premium build and license checks succeed (see TWEC_Premium::is_available in the codebase).', 'planit-event-manager' ); ?></p>

<h2><?php esc_html_e( 'Feature highlights (marketing list)', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'Your sales page lists many capabilities; the items below are surfaced from the plugin’s own premium feature list for reference. Each still requires Premium installed and an active license.', 'planit-event-manager' ); ?></p>
<?php if ( ! empty( $premium_features ) ) : ?>
<ul class="twec-docs-list">
	<?php foreach ( $premium_features as $label ) : ?>
		<li><?php echo esc_html( $label ); ?></li>
	<?php endforeach; ?>
</ul>
<?php else : ?>
	<p><?php esc_html_e( 'Premium helper class not loaded — reinstall PlanIt Premium to see the authoritative feature list programmatically.', 'planit-event-manager' ); ?></p>
<?php endif; ?>

<h2><?php esc_html_e( 'Engine-level Premium bundles used in code', 'planit-event-manager' ); ?></h2>
<p><?php esc_html_e( 'These capability names appear in loaders (examples): import, rss, recurring, custom_fields, pro_features. If a bundle is inactive, dependent UI stays hidden.', 'planit-event-manager' ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'import', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'CSV/The Events Calendar importer.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'recurring', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Recurring occurrence rules.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'custom_fields', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Extra per-event fields manager.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'pro_features', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Featured flag, series taxonomy integration, and related Pro UI.', 'planit-event-manager' ); ?></li>
	<li><strong><?php esc_html_e( 'rss', 'planit-event-manager' ); ?></strong> — <?php esc_html_e( 'Events RSS enhancements where enabled.', 'planit-event-manager' ); ?></li>
</ul>

<h2><?php esc_html_e( 'Shortcodes & blocks that require Premium on the front end', 'planit-event-manager' ); ?></h2>
<ul>
	<li><code>twec_rsvp</code>, <code>twec_submission_form</code> — <?php esc_html_e( 'RSVP attendee capture and authenticated submission workflows (PlanIt RSVP / PlanIt Event Submission blocks).', 'planit-event-manager' ); ?></li>
</ul>
<p>
	<?php
	$rsvp_docs = add_query_arg( 'tab', 'rsvp-door', admin_url( 'edit.php?post_type=twec_event&page=twec-documentation' ) );
	?>
	<a href="<?php echo esc_url( $rsvp_docs ); ?>"><?php esc_html_e( 'Documentation: RSVP CSV, print QR manifest, and Door check-in', 'planit-event-manager' ); ?></a>
</p>

<div class="notice notice-warning inline">
	<p><?php esc_html_e( 'Payment gateways and WooCommerce ticketing are documented separately — those are integrations that ship in the core plugin and rely on WooCommerce/active settings and gateway keys, not on the Premium marketing list.', 'planit-event-manager' ); ?></p>
	<?php if ( '#' !== $upgrade_url ) : ?>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=twec_event&page=twec-upgrade' ) ); ?>"><?php esc_html_e( 'Open Upgrade screen', 'planit-event-manager' ); ?></a> <a class="button" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'PlanIt Premium website', 'planit-event-manager' ); ?></a></p>
	<?php endif; ?>
</div>
