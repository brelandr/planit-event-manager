<?php
/**
 * Admin list table for twec_payment_log.
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table', false ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Payment log WP_List_Table wrapper.
 */
class TWEC_Payment_Log_List_Table extends WP_List_Table {

	/**
	 * @var array<string,mixed>
	 */
	protected $request_args = array();

	/**
	 * @param array<string,mixed> $request_args Parsed $_GET filters.
	 */
	public function __construct( $request_args ) {
		parent::__construct(
			array(
				'singular' => __( 'payment', 'planit-event-manager' ),
				'plural'   => __( 'payments', 'planit-event-manager' ),
				'ajax'     => false,
			)
		);
		$this->request_args = is_array( $request_args ) ? $request_args : array();
	}

	/**
	 * @return void
	 */
	public function prepare_items() {
		if ( ! class_exists( 'TWEC_Payment_Log', false ) ) {
			return;
		}

		$per_page = 25;
		$paged    = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only paging.

		$df     = isset( $this->request_args['df'] ) ? (string) $this->request_args['df'] : '';
		$dt     = isset( $this->request_args['dt'] ) ? (string) $this->request_args['dt'] : '';
		$df_sql = '';
		$dt_sql = '';
		if ( '' !== $df && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $df ) ) {
			$df_sql = $df . ' 00:00:00';
		}
		if ( '' !== $dt && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dt ) ) {
			$dt_sql = $dt . ' 23:59:59';
		}

		$args = array(
			'per_page'      => $per_page,
			'paged'         => max( 1, $paged ),
			'event_id'      => isset( $this->request_args['event_id'] ) ? (int) $this->request_args['event_id'] : 0,
			'gateway'       => isset( $this->request_args['gateway'] ) ? (string) $this->request_args['gateway'] : '',
			'search'        => isset( $this->request_args['s'] ) ? (string) $this->request_args['s'] : '',
			'date_from_gmt' => $df_sql,
			'date_to_gmt'   => $dt_sql,
		);

		$result      = TWEC_Payment_Log::query( $args );
		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => (int) $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( max( 1, (int) $result['total'] ) / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	/**
	 * @return string[]
	 */
	public function get_columns() {
		return array(
			'paid_at_gmt' => __( 'Paid (GMT)', 'planit-event-manager' ),
			'event_id'    => __( 'Event', 'planit-event-manager' ),
			'gateway'     => __( 'Gateway', 'planit-event-manager' ),
			'amount'      => __( 'Amount', 'planit-event-manager' ),
			'buyer'       => __( 'Buyer', 'planit-event-manager' ),
			'wp_user_id'  => __( 'WP user', 'planit-event-manager' ),
			'gateway_ref' => __( 'Reference', 'planit-event-manager' ),
		);
	}

	/**
	 * @param array<string,string> $item Row.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'paid_at_gmt':
				return esc_html( isset( $item['paid_at_gmt'] ) ? (string) $item['paid_at_gmt'] : '' );
			case 'event_id':
				$eid = isset( $item['event_id'] ) ? (int) $item['event_id'] : 0;
				if ( $eid <= 0 ) {
					return '';
				}
				$url  = add_query_arg( array( 'post' => $eid ), admin_url( 'post.php?action=edit' ) );
				$link = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( get_the_title( $eid ) ) );
				return $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped anchor only.
			case 'gateway':
				return esc_html( isset( $item['gateway'] ) ? strtoupper( (string) $item['gateway'] ) : '' );
			case 'amount':
				$m = isset( $item['amount_minor'] ) ? (int) $item['amount_minor'] : 0;
				$c = isset( $item['currency'] ) ? strtolower( (string) $item['currency'] ) : '';
				if ( class_exists( 'TWEC_Payments_Stripe', false ) ) {
					return esc_html( TWEC_Payments_Stripe::format_minor_for_display( $m, $c ) );
				}
				return esc_html( number_format( $m / 100, 2 ) . ' ' . strtoupper( $c ) );
			case 'buyer':
				$em = isset( $item['buyer_email'] ) ? (string) $item['buyer_email'] : '';
				$nm = isset( $item['buyer_name'] ) ? (string) $item['buyer_name'] : '';
				return esc_html( trim( $nm ) ) . ( $nm && $em ? '<br>' : '' ) .
					( '' !== $em ? '<small>' . esc_html( $em ) . '</small>' : '' );
			case 'wp_user_id':
				$u = isset( $item['wp_user_id'] ) ? (int) $item['wp_user_id'] : 0;
				if ( $u <= 0 ) {
					return '&mdash;';
				}
				$ulink = admin_url( 'user-edit.php?user_id=' . (int) $u );
				return sprintf( '<a href="%s">%d</a>', esc_url( $ulink ), $u );
			case 'gateway_ref':
				return $this->render_gateway_ref_column( $item );
		}

		return '';
	}

	/**
	 * @param array<string,string> $item Row.
	 * @return string
	 */
	protected function render_gateway_ref_column( $item ) {
		$r    = isset( $item['gateway_ref'] ) ? (string) $item['gateway_ref'] : '';
		$show = strlen( $r ) > 36 ? substr( $r, 0, 36 ) . '…' : $r;
		$id   = isset( $item['id'] ) ? (int) $item['id'] : 0;
		$url  = add_query_arg(
			array(
				'post_type'  => 'twec_event',
				'page'       => 'twec-payments',
				'payment_id' => $id,
			),
			admin_url( 'edit.php' )
		);
		$html = esc_html( $show );
		return $html . $this->row_actions(
			array(
				'view' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View details', 'planit-event-manager' ) . '</a>',
			)
		);
	}
}
