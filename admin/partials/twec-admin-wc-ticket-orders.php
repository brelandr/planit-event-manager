<?php
/**
 * Admin: WooCommerce orders referencing an event’s ticket product.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'planit-event-manager' ), '', array( 'response' => 403 ) );
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Partial scope.

if ( isset( $_GET['twec_export'] ) && 'csv' === sanitize_text_field( wp_unslash( $_GET['twec_export'] ) ) ) {
	$eid_export = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
	// Nonce name bound to event id for tighter scope.
	twec_verify_get_nonce_or_die( 'twec_wc_export_' . $eid_export );
	if ( class_exists( 'TWEC_WooCommerce', false ) ) {
		TWEC_WooCommerce::send_ticket_orders_csv( $eid_export );
	}
	exit;
}

$events = planit_event_manager_get_wc_ticket_event_choices();

$selected = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
$orders   = array();
if ( $selected > 0 && class_exists( 'TWEC_WooCommerce', false ) ) {
	$orders = TWEC_WooCommerce::get_orders_for_event_ticket_product( $selected );
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'WooCommerce ticket orders', 'planit-event-manager' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Lists recent WooCommerce orders that include the ticket product linked on the chosen event (scan limit applies on very large shops). Inventory and refunds remain managed in WooCommerce.', 'planit-event-manager' ); ?></p>

	<form method="get" action="">
		<input type="hidden" name="post_type" value="twec_event" />
		<input type="hidden" name="page" value="twec-wc-ticket-orders" />
		<label for="event_id"><?php esc_html_e( 'Event', 'planit-event-manager' ); ?></label><br />
		<select name="event_id" id="event_id">
			<option value="0"><?php esc_html_e( '— Select —', 'planit-event-manager' ); ?></option>
			<?php foreach ( $events as $ev ) : ?>
				<?php
				printf(
					'<option value="%d" %s>%s</option>',
					(int) $ev['ID'],
					selected( $selected, (int) $ev['ID'], false ),
					esc_html( $ev['post_title'] )
				);
				?>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Filter', 'planit-event-manager' ), 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( $selected > 0 ) : ?>
		<?php if ( empty( $orders ) ) : ?>
			<p><?php esc_html_e( 'No matching orders found in the scanned range.', 'planit-event-manager' ); ?></p>
		<?php else : ?>
			<p>
				<a class="button" href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'post_type'   => 'twec_event',
								'page'        => 'twec-wc-ticket-orders',
								'event_id'    => (int) $selected,
								'twec_export' => 'csv',
							),
							admin_url( 'edit.php' )
						),
						'twec_wc_export_' . (int) $selected
					)
				);
				?>
				"><?php esc_html_e( 'Download CSV', 'planit-event-manager' ); ?></a>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'planit-event-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'planit-event-manager' ); ?></th>
						<th><?php esc_html_e( 'Date', 'planit-event-manager' ); ?></th>
						<th><?php esc_html_e( 'Email', 'planit-event-manager' ); ?></th>
						<th><?php esc_html_e( 'Matching qty', 'planit-event-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $orders as $order ) : ?>
						<?php
						if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_id' ) ) ) {
							continue;
						}
						$oid = (int) $order->get_id();
						$qty = 0;
						$lnk = (int) get_post_meta( $selected, TWEC_WooCommerce::META_PRODUCT_ID, true );
						foreach ( $order->get_items( 'line_item' ) as $item ) {
							if ( ! is_object( $item ) || ! is_callable( array( $item, 'get_product_id' ) ) ) {
								continue;
							}
							$p = (int) $item->get_product_id();
							$v = (int) $item->get_variation_id();
							if ( ( $lnk > 0 && ( $p === $lnk || $v === $lnk ) ) ) {
								$qty += (int) $item->get_quantity();
							}
						}
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $oid . '&action=edit' ) ); ?>"><?php echo esc_html( '#' . $oid ); ?></a>
							</td>
							<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
							<td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '' ); ?></td>
							<td><?php echo esc_html( $order->get_billing_email() ); ?></td>
							<td><?php echo esc_html( (string) $qty ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>
