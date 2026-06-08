<?php
/**
 * Admin: payment log list + single row detail.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once PLANIT_EVENT_MANAGER_DIR . 'admin/class-twec-payment-log-list-table.php';

$payment_id = isset( $_GET['payment_id'] ) ? absint( wp_unslash( $_GET['payment_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( $payment_id > 0 && class_exists( 'TWEC_Payment_Log', false ) ) {
	$row = TWEC_Payment_Log::get( $payment_id );
	if ( is_array( $row ) ) {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Payment details', 'planit-event-manager' ); ?></h1>
			<p><a href="<?php echo esc_url( remove_query_arg( 'payment_id' ) ); ?>">&larr; <?php esc_html_e( 'Back to list', 'planit-event-manager' ); ?></a></p>
			<table class="widefat striped">
				<tbody>
				<?php foreach ( $row as $k => $v ) : ?>
					<tr>
						<th scope="row" style="width:220px;"><?php echo esc_html( (string) $k ); ?></th>
						<td><?php echo 'raw_payload_json' === $k ? '<pre class="twec-pre">' . esc_html( is_string( $v ) ? $v : '' ) . '</pre>' : esc_html( is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	esc_html_e( 'Payment not found.', 'planit-event-manager' );
	echo '</p></div>';
}

$req = array(
	'event_id' => isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'gateway'  => isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'df'       => isset( $_GET['df'] ) ? sanitize_text_field( wp_unslash( $_GET['df'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'dt'       => isset( $_GET['dt'] ) ? sanitize_text_field( wp_unslash( $_GET['dt'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
);

$table = new TWEC_Payment_Log_List_Table( $req );
$table->prepare_items();

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Payments', 'planit-event-manager' ); ?></h1>
	<?php
	if ( class_exists( 'TWEC_Payment_Log', false ) && method_exists( 'TWEC_Payment_Log', 'get_monthly_totals_trailing_gmt' ) ) {
		$mom = TWEC_Payment_Log::get_monthly_totals_trailing_gmt( 6 );
		if ( is_array( $mom ) && ! empty( $mom ) ) {
			?>
			<div class="twec-payment-mom" style="margin: 1em 0 2em;">
				<h2><?php esc_html_e( 'Revenue totals by month', 'planit-event-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Based on Stripe and PayPal rows in the PlanIt payment log (paid_at GMT). Amounts stored as gateway minor currency units (cents except zero-decimal ISO codes such as JPY). Display column uses currency-aware rounding.', 'planit-event-manager' ); ?></p>
				<table class="widefat striped" style="max-width: 960px;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Month (GMT)', 'planit-event-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Gateway', 'planit-event-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Currency', 'planit-event-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Total (display)', 'planit-event-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Total (stored minor)', 'planit-event-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Payments', 'planit-event-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $mom as $row ) : ?>
						<tr>
							<td><?php echo isset( $row['ym'] ) ? esc_html( (string) $row['ym'] ) : ''; ?></td>
							<td><?php echo isset( $row['gateway'] ) ? esc_html( (string) $row['gateway'] ) : ''; ?></td>
							<td><?php echo isset( $row['currency'] ) ? esc_html( strtoupper( (string) $row['currency'] ) ) : ''; ?></td>
							<td><?php echo isset( $row['total_minor'], $row['currency'] ) ? esc_html( TWEC_Payment_Log::format_amount_minor_for_display( (int) $row['total_minor'], (string) $row['currency'] ) ) : ( isset( $row['total_minor'] ) ? esc_html( (string) (int) $row['total_minor'] ) : '' ); ?></td>
							<td title="<?php esc_attr_e( 'Raw SUM(amount_minor) for audit', 'planit-event-manager' ); ?>"><?php echo isset( $row['total_minor'] ) ? esc_html( (string) (int) $row['total_minor'] ) : '0'; ?></td>
							<td><?php echo isset( $row['count'] ) ? esc_html( (string) (int) $row['count'] ) : '0'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}
	?>
	<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
		<input type="hidden" name="post_type" value="twec_event" />
		<input type="hidden" name="page" value="twec-payments" />
		<p>
			<label for="twec-pl-event"><?php esc_html_e( 'Event', 'planit-event-manager' ); ?></label>
			<?php
			wp_dropdown_posts(
				array(
					'name'            => 'event_id',
					'id'              => 'twec-pl-event',
					'post_type'       => 'twec_event',
					'show_option_all' => __( 'All events', 'planit-event-manager' ),
					'selected'        => (int) $req['event_id'],
					'numberposts'     => 500,
					'orderby'         => 'title',
					'order'           => 'ASC',
				)
			);
			?>
			<label for="twec-pl-gateway"><?php esc_html_e( 'Gateway', 'planit-event-manager' ); ?></label>
			<select name="gateway" id="twec-pl-gateway">
				<option value=""><?php esc_html_e( 'All', 'planit-event-manager' ); ?></option>
				<option value="stripe" <?php selected( $req['gateway'], 'stripe' ); ?>><?php esc_html_e( 'Stripe', 'planit-event-manager' ); ?></option>
				<option value="paypal" <?php selected( $req['gateway'], 'paypal' ); ?>><?php esc_html_e( 'PayPal', 'planit-event-manager' ); ?></option>
			</select>
			<label for="twec-pl-df"><?php esc_html_e( 'From (date, GMT)', 'planit-event-manager' ); ?></label>
			<input type="date" name="df" id="twec-pl-df" value="<?php echo esc_attr( $req['df'] ); ?>" />
			<label for="twec-pl-dt"><?php esc_html_e( 'To (date, GMT)', 'planit-event-manager' ); ?></label>
			<input type="date" name="dt" id="twec-pl-dt" value="<?php echo esc_attr( $req['dt'] ); ?>" />
			<label for="twec-pl-s"><?php esc_html_e( 'Search email / ref', 'planit-event-manager' ); ?></label>
			<input type="search" name="s" id="twec-pl-s" value="<?php echo esc_attr( $req['s'] ); ?>" />
			<?php submit_button( __( 'Filter', 'planit-event-manager' ), 'secondary', '', false ); ?>
		</p>
	</form>
	<?php $table->display(); ?>
</div>
