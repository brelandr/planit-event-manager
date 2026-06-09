<?php
/**
 * Settings page template.
 *
 * @package    The_Event_Calendar
 * @subpackage admin/partials
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local scope, not globals
$settings                = get_option( 'twec_settings', array() );
$hide_past_events        = isset( $settings['hide_past_events'] ) ? $settings['hide_past_events'] : 'no';
$events_per_page         = isset( $settings['events_per_page'] ) ? $settings['events_per_page'] : 10;
$google_maps_api_key     = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';
$seo_json_ld             = isset( $settings['seo_json_ld'] ) ? $settings['seo_json_ld'] : 'yes';
$seo_json_ld_graph       = isset( $settings['seo_json_ld_graph'] ) ? $settings['seo_json_ld_graph'] : 'no';
$seo_og                  = isset( $settings['seo_og'] ) ? $settings['seo_og'] : 'yes';
$hierarchical_urls       = isset( $settings['hierarchical_event_urls'] ) ? $settings['hierarchical_event_urls'] : 'no';
$seo_breadcrumb_json     = isset( $settings['seo_breadcrumb_json_ld'] ) ? $settings['seo_breadcrumb_json_ld'] : 'yes';
$calendar_interactivity      = isset( $settings['calendar_interactivity'] ) ? $settings['calendar_interactivity'] : 'yes';
$compact_list_interactivity  = isset( $settings['compact_list_interactivity'] ) ? $settings['compact_list_interactivity'] : '';
if ( '' === $compact_list_interactivity ) {
	$compact_list_interactivity = $calendar_interactivity;
}
$cookieless_view_counter = isset( $settings['cookieless_view_counter'] ) ? $settings['cookieless_view_counter'] : 'no';
$payment_gateway         = isset( $settings['payment_gateway'] ) ? $settings['payment_gateway'] : 'none';
$payment_mode            = isset( $settings['payment_mode'] ) ? $settings['payment_mode'] : 'test';
$stripe_test_publishable = isset( $settings['stripe_test_publishable_key'] ) ? $settings['stripe_test_publishable_key'] : '';
$stripe_test_secret      = isset( $settings['stripe_test_secret_key'] ) ? (string) $settings['stripe_test_secret_key'] : '';
$stripe_live_publishable = isset( $settings['stripe_live_publishable_key'] ) ? $settings['stripe_live_publishable_key'] : '';
$stripe_live_secret      = isset( $settings['stripe_live_secret_key'] ) ? (string) $settings['stripe_live_secret_key'] : '';
$stripe_webhook_sec      = isset( $settings['stripe_webhook_secret'] ) ? (string) $settings['stripe_webhook_secret'] : '';
$stripe_feature_minor    = isset( $settings['stripe_feature_price_minor'] ) ? (int) $settings['stripe_feature_price_minor'] : 0;
$stripe_currency         = isset( $settings['stripe_currency'] ) ? (string) $settings['stripe_currency'] : 'usd';
$stripe_product_name     = isset( $settings['stripe_product_name'] ) ? (string) $settings['stripe_product_name'] : '';
$stripe_success_url      = isset( $settings['stripe_checkout_success_url'] ) ? (string) $settings['stripe_checkout_success_url'] : '';
$stripe_cancel_url       = isset( $settings['stripe_checkout_cancel_url'] ) ? (string) $settings['stripe_checkout_cancel_url'] : '';
$stripe_webhook_url      = function_exists( 'rest_url' ) ? rest_url( 'planit/v1/stripe/webhook' ) : '';
$paypal_test_cid         = isset( $settings['paypal_test_client_id'] ) ? (string) $settings['paypal_test_client_id'] : '';
$paypal_test_sec         = isset( $settings['paypal_test_client_secret'] ) ? (string) $settings['paypal_test_client_secret'] : '';
$paypal_live_cid         = isset( $settings['paypal_live_client_id'] ) ? (string) $settings['paypal_live_client_id'] : '';
$paypal_live_sec         = isset( $settings['paypal_live_client_secret'] ) ? (string) $settings['paypal_live_client_secret'] : '';
$paypal_webhook_id       = isset( $settings['paypal_webhook_id'] ) ? (string) $settings['paypal_webhook_id'] : '';
$paypal_success_url      = isset( $settings['paypal_checkout_success_url'] ) ? (string) $settings['paypal_checkout_success_url'] : '';
$paypal_cancel_url       = isset( $settings['paypal_checkout_cancel_url'] ) ? (string) $settings['paypal_checkout_cancel_url'] : '';
$paypal_webhook_url      = function_exists( 'rest_url' ) ? rest_url( 'planit/v1/paypal/webhook' ) : '';
$woocommerce_tickets_en  = isset( $settings['woocommerce_tickets_enabled'] ) ? (string) $settings['woocommerce_tickets_enabled'] : 'no';
$woo_ticket_cta_list_def = isset( $settings['woocommerce_ticket_cta_list'] ) ? (string) $settings['woocommerce_ticket_cta_list'] : 'no';
$woo_ticket_cta_cal_def  = isset( $settings['woocommerce_ticket_cta_calendar'] ) ? (string) $settings['woocommerce_ticket_cta_calendar'] : 'no';
$woo_ticket_show_vc      = isset( $settings['woocommerce_ticket_show_view_cart'] ) ? (string) $settings['woocommerce_ticket_show_view_cart'] : 'yes';
$woo_ticket_req_buyer    = isset( $settings['woocommerce_ticket_require_buyer_details'] ) ? (string) $settings['woocommerce_ticket_require_buyer_details'] : 'yes';
$woo_btn_style           = isset( $settings['woocommerce_ticket_btn_style'] ) ? strtolower( sanitize_key( (string) $settings['woocommerce_ticket_btn_style'] ) ) : 'solid';
if ( ! in_array( $woo_btn_style, array( 'theme', 'solid', 'outline', 'custom' ), true ) ) {
	$woo_btn_style = 'solid';
}
$woo_btn_pri_bg  = isset( $settings['woocommerce_ticket_btn_primary_bg'] ) ? (string) $settings['woocommerce_ticket_btn_primary_bg'] : '#2271b1';
$woo_btn_pri_txt = isset( $settings['woocommerce_ticket_btn_primary_text'] ) ? (string) $settings['woocommerce_ticket_btn_primary_text'] : '#ffffff';
$woo_btn_pri_bg  = is_string( $woo_btn_pri_bg ) && sanitize_hex_color( strtolower( trim( $woo_btn_pri_bg ) ) ) ? sanitize_hex_color( strtolower( trim( $woo_btn_pri_bg ) ) ) : '#2271b1';
$woo_btn_pri_txt = is_string( $woo_btn_pri_txt ) && sanitize_hex_color( strtolower( trim( $woo_btn_pri_txt ) ) ) ? sanitize_hex_color( strtolower( trim( $woo_btn_pri_txt ) ) ) : '#ffffff';
$woo_btn_radius  = isset( $settings['woocommerce_ticket_btn_radius'] ) ? (int) $settings['woocommerce_ticket_btn_radius'] : 8;
if ( $woo_btn_radius < 0 ) {
	$woo_btn_radius = 0;
} elseif ( $woo_btn_radius > 32 ) {
	$woo_btn_radius = 32;
}
$woo_btn_sec_mode = isset( $settings['woocommerce_ticket_btn_secondary_mode'] ) ? sanitize_key( (string) $settings['woocommerce_ticket_btn_secondary_mode'] ) : 'outline';
if ( ! in_array( $woo_btn_sec_mode, array( 'outline', 'ghost', 'muted' ), true ) ) {
	$woo_btn_sec_mode = 'outline';
}
$event_reminders_en = isset( $settings['event_reminders_enabled'] ) ? (string) $settings['event_reminders_enabled'] : 'no';
$reminder_offset_h  = isset( $settings['reminder_offset_hours'] ) ? (int) $settings['reminder_offset_hours'] : 24;
if ( $reminder_offset_h < 1 ) {
	$reminder_offset_h = 1;
} elseif ( $reminder_offset_h > 168 ) {
	$reminder_offset_h = 168;
}
$twec_wc_active    = class_exists( 'WooCommerce' );
$ics_subscribe_url = add_query_arg( 'twec_feed', 'ics', home_url( '/' ) );
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php settings_errors( 'twec_settings' ); ?>
	
		<?php echo wp_kses_post( TWEC_Premium::get_upgrade_notice( '', 'admin' ) ); ?>
	
	<form method="post" action="options.php">
		<?php settings_fields( 'twec_settings_group' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="hide_past_events"><?php esc_html_e( 'Hide Past Events', 'planit-event-manager' ); ?></label>
				</th>
				<td>
					<select name="twec_settings[hide_past_events]" id="hide_past_events">
						<option value="no" <?php selected( $hide_past_events, 'no' ); ?>><?php esc_html_e( 'No', 'planit-event-manager' ); ?></option>
						<option value="yes" <?php selected( $hide_past_events, 'yes' ); ?>><?php esc_html_e( 'Yes', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Hide events that have already passed from calendar and list views.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="events_per_page"><?php esc_html_e( 'Events Per Page', 'planit-event-manager' ); ?></label>
				</th>
				<td>
					<input type="number" name="twec_settings[events_per_page]" id="events_per_page" value="<?php echo esc_attr( $events_per_page ); ?>" min="1" />
					<p class="description"><?php esc_html_e( 'Number of events to display per page in list view.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="google_maps_api_key"><?php esc_html_e( 'Google Maps API Key', 'planit-event-manager' ); ?></label>
				</th>
				<td>
					<input type="text" name="twec_settings[google_maps_api_key]" id="google_maps_api_key" value="<?php echo esc_attr( $google_maps_api_key ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Enter your Google Maps API key to enable map display for venues.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SEO: JSON-LD for events', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[seo_json_ld]" id="seo_json_ld">
						<option value="yes" <?php selected( $seo_json_ld, 'yes' ); ?>><?php esc_html_e( 'Enabled', 'planit-event-manager' ); ?></option>
						<option value="no" <?php selected( $seo_json_ld, 'no' ); ?>><?php esc_html_e( 'Disabled', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'On single event pages, output Schema.org Event structured data in the head (recommended for search).', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SEO: JSON-LD @graph (Organization + Event)', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[seo_json_ld_graph]" id="seo_json_ld_graph">
						<option value="no" <?php selected( $seo_json_ld_graph, 'no' ); ?>><?php esc_html_e( 'Single Event object (default)', 'planit-event-manager' ); ?></option>
						<option value="yes" <?php selected( $seo_json_ld_graph, 'yes' ); ?>><?php esc_html_e( 'Use @graph with linked Organization and Event', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Optional richer JSON-LD graph. Validate with Google’s Rich Results Test. Use one graph style to avoid duplicate SEO plugin output.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'URLs: category in event path', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[hierarchical_event_urls]" id="hierarchical_event_urls">
						<option value="no" <?php selected( $hierarchical_urls, 'no' ); ?>><?php esc_html_e( 'No (default: /events/event-slug/)', 'planit-event-manager' ); ?></option>
						<option value="yes" <?php selected( $hierarchical_urls, 'yes' ); ?>><?php esc_html_e( 'Yes: /events/category-slug/event-slug/ (uses first event category, or "uncategorized")', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'After changing this, save and click “Flush Permalinks” below (or visit Settings > Permalinks).', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SEO: BreadcrumbList JSON-LD', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[seo_breadcrumb_json_ld]" id="seo_breadcrumb_json_ld">
						<option value="yes" <?php selected( $seo_breadcrumb_json, 'yes' ); ?>><?php esc_html_e( 'Enabled', 'planit-event-manager' ); ?></option>
						<option value="no" <?php selected( $seo_breadcrumb_json, 'no' ); ?>><?php esc_html_e( 'Disabled', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'On single event and event archive pages, output Schema.org BreadcrumbList (disable if your SEO plugin already outputs it).', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Calendar: Interactivity API', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[calendar_interactivity]" id="calendar_interactivity">
						<option value="yes" <?php selected( $calendar_interactivity, 'yes' ); ?>><?php esc_html_e( 'Enabled (WordPress 6.5+)', 'planit-event-manager' ); ?></option>
						<option value="no" <?php selected( $calendar_interactivity, 'no' ); ?>><?php esc_html_e( 'Disabled (use jQuery / full request fallback only)', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Client-side month navigation and filters for the block/shortcode calendar. Disable for older themes or if you need to test LCP; shortcode attribute interactivity="no" or block toggle can override per block.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Compact list: enhanced preview', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[compact_list_interactivity]" id="compact_list_interactivity">
						<option value="yes" <?php selected( $compact_list_interactivity, 'yes' ); ?>><?php esc_html_e( 'Enabled', 'planit-event-manager' ); ?></option>
						<option value="no" <?php selected( $compact_list_interactivity, 'no' ); ?>><?php esc_html_e( 'Disabled', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'When enabled, the compact list popup fetches full event details from the REST API. Override per block or with shortcode interactivity="no".', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Analytics: cookieless view counter', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[cookieless_view_counter]" id="cookieless_view_counter">
						<option value="no" <?php selected( $cookieless_view_counter, 'no' ); ?>><?php esc_html_e( 'Off', 'planit-event-manager' ); ?></option>
						<option value="yes" <?php selected( $cookieless_view_counter, 'yes' ); ?>><?php esc_html_e( 'On (increment on each page view; no cookies)', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Server-side _twec_view_count on single event pages. Use an aggregate cache layer on high traffic sites.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Payments', 'planit-event-manager' ); ?></th>
				<td>
					<p>
						<label for="payment_gateway" class="screen-reader-text"><?php esc_html_e( 'Gateway', 'planit-event-manager' ); ?></label>
						<select name="twec_settings[payment_gateway]" id="payment_gateway">
							<option value="none" <?php selected( $payment_gateway, 'none' ); ?>><?php esc_html_e( 'None', 'planit-event-manager' ); ?></option>
							<option value="stripe" <?php selected( $payment_gateway, 'stripe' ); ?>><?php esc_html_e( 'Stripe (Checkout)', 'planit-event-manager' ); ?></option>
							<option value="paypal" <?php selected( $payment_gateway, 'paypal' ); ?>><?php esc_html_e( 'PayPal (Checkout)', 'planit-event-manager' ); ?></option>
						</select>
					</p>
					<p>
						<label for="payment_mode"><?php esc_html_e( 'Mode', 'planit-event-manager' ); ?></label>
						<select name="twec_settings[payment_mode]" id="payment_mode">
							<option value="test" <?php selected( $payment_mode, 'test' ); ?>><?php esc_html_e( 'Test', 'planit-event-manager' ); ?></option>
							<option value="live" <?php selected( $payment_mode, 'live' ); ?>><?php esc_html_e( 'Live', 'planit-event-manager' ); ?></option>
						</select>
					</p>
					<p class="description"><?php esc_html_e( 'Direct gateways (above) are for PlanIt’s featured-listing flow (Stripe or PayPal). Event ticket sales through WooCommerce are separate—enable them in the WooCommerce fieldset below when WooCommerce is installed. REST: …/planit/v1/stripe/create-checkout or …/planit/v1/paypal/create-checkout (logged-in, X-WP-Nonce + event_id) when a Premium license is active.', 'planit-event-manager' ); ?></p>
					<fieldset class="twec-stripe-settings" style="border:1px solid #c3c4c7; padding:12px; margin-top:10px; max-width: 640px;">
						<legend><strong><?php esc_html_e( 'Stripe: API keys, webhook & feature listing price', 'planit-event-manager' ); ?></strong></legend>
						<p class="description"><?php esc_html_e( 'Publishable/secret and Stripe webhook fields apply when the gateway is Stripe. Feature price, currency, and line item name apply to both Stripe and PayPal when those gateways are selected.', 'planit-event-manager' ); ?></p>
						<p><label for="stripe_test_publishable_key"><?php esc_html_e( 'Test publishable key', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="regular-text" name="twec_settings[stripe_test_publishable_key]" id="stripe_test_publishable_key" value="<?php echo esc_attr( $stripe_test_publishable ); ?>" autocomplete="off" />
						</p>
						<p><label for="stripe_test_secret_key"><?php esc_html_e( 'Test secret key', 'planit-event-manager' ); ?></label><br />
							<input type="password" class="regular-text" name="twec_settings[stripe_test_secret_key]" id="stripe_test_secret_key" value="" placeholder="<?php echo esc_attr( $stripe_test_secret ? __( 'Leave blank to keep saved key', 'planit-event-manager' ) : '' ); ?>" autocomplete="new-password" />
						</p>
						<p><label for="stripe_live_publishable_key"><?php esc_html_e( 'Live publishable key', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="regular-text" name="twec_settings[stripe_live_publishable_key]" id="stripe_live_publishable_key" value="<?php echo esc_attr( $stripe_live_publishable ); ?>" autocomplete="off" />
						</p>
						<p><label for="stripe_live_secret_key"><?php esc_html_e( 'Live secret key', 'planit-event-manager' ); ?></label><br />
							<input type="password" class="regular-text" name="twec_settings[stripe_live_secret_key]" id="stripe_live_secret_key" value="" placeholder="<?php echo esc_attr( $stripe_live_secret ? __( 'Leave blank to keep saved key', 'planit-event-manager' ) : '' ); ?>" autocomplete="new-password" />
						</p>
						<p><label for="stripe_webhook_secret"><?php esc_html_e( 'Webhook signing secret', 'planit-event-manager' ); ?></label><br />
							<input type="password" class="large-text" name="twec_settings[stripe_webhook_secret]" id="stripe_webhook_secret" value="" placeholder="<?php echo esc_attr( $stripe_webhook_sec ? __( 'Leave blank to keep saved secret', 'planit-event-manager' ) : '' ); ?>" autocomplete="new-password" />
						</p>
						<p class="description"><?php esc_html_e( 'From Stripe Dashboard → Developers → Webhooks, or the secret printed by stripe listen. Must start with whsec_.', 'planit-event-manager' ); ?></p>
						<?php if ( is_string( $stripe_webhook_url ) && '' !== $stripe_webhook_url ) : ?>
						<p><strong><?php esc_html_e( 'Webhook URL to register in Stripe', 'planit-event-manager' ); ?>:</strong><br /><code id="twec-stripe-webhook-url"><?php echo esc_html( $stripe_webhook_url ); ?></code></p>
						<?php endif; ?>
						<p>
							<label for="stripe_feature_price_minor"><?php esc_html_e( 'Feature price (minor units)', 'planit-event-manager' ); ?></label><br />
							<input type="number" name="twec_settings[stripe_feature_price_minor]" id="stripe_feature_price_minor" value="<?php echo esc_attr( (string) max( 0, $stripe_feature_minor ) ); ?>" min="0" class="small-text" />
						</p>
						<p class="description"><?php esc_html_e( 'Use the smallest currency units (e.g. USD cents): $25.00 USD = 2500 — not 25. If this value is below Stripe’s minimum and Event Cost on the event is high enough, checkout uses Event Cost instead.', 'planit-event-manager' ); ?></p>
						<p>
							<label for="stripe_currency"><?php esc_html_e( 'Currency', 'planit-event-manager' ); ?></label><br />
							<input type="text" name="twec_settings[stripe_currency]" id="stripe_currency" value="<?php echo esc_attr( $stripe_currency ); ?>" maxlength="3" class="small-text" style="text-transform:lowercase;" />
						</p>
						<p>
							<label for="stripe_product_name"><?php esc_html_e( 'Line item name', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="large-text" name="twec_settings[stripe_product_name]" id="stripe_product_name" value="<?php echo esc_attr( $stripe_product_name ); ?>" />
						</p>
						<p>
							<label for="stripe_checkout_success_url"><?php esc_html_e( 'Success URL (optional)', 'planit-event-manager' ); ?></label><br />
							<input type="url" class="large-text" name="twec_settings[stripe_checkout_success_url]" id="stripe_checkout_success_url" value="<?php echo $stripe_success_url ? esc_url( $stripe_success_url ) : ''; ?>" placeholder="<?php echo esc_attr( __( 'Default: event URL with ?twec_stripe=success', 'planit-event-manager' ) ); ?>" />
						</p>
						<p>
							<label for="stripe_checkout_cancel_url"><?php esc_html_e( 'Cancel URL (optional)', 'planit-event-manager' ); ?></label><br />
							<input type="url" class="large-text" name="twec_settings[stripe_checkout_cancel_url]" id="stripe_checkout_cancel_url" value="<?php echo $stripe_cancel_url ? esc_url( $stripe_cancel_url ) : ''; ?>" placeholder="<?php echo esc_attr( __( 'Default: event URL', 'planit-event-manager' ) ); ?>" />
						</p>
					</fieldset>
					<fieldset class="twec-paypal-settings" style="border:1px solid #c3c4c7; padding:12px; margin-top:10px; max-width: 640px;">
						<legend><strong><?php esc_html_e( 'PayPal: featured / paid listing', 'planit-event-manager' ); ?></strong></legend>
						<p class="description"><?php esc_html_e( 'Only used when the gateway above is set to PayPal. Uses the same feature price, currency, and line item name as in the fieldset above.', 'planit-event-manager' ); ?></p>
						<p><label for="paypal_test_client_id"><?php esc_html_e( 'Sandbox / Test client id', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="large-text" name="twec_settings[paypal_test_client_id]" id="paypal_test_client_id" value="<?php echo esc_attr( $paypal_test_cid ); ?>" autocomplete="off" />
						</p>
						<p><label for="paypal_test_client_secret"><?php esc_html_e( 'Sandbox / Test client secret', 'planit-event-manager' ); ?></label><br />
							<input type="password" class="large-text" name="twec_settings[paypal_test_client_secret]" id="paypal_test_client_secret" value="" placeholder="<?php echo esc_attr( $paypal_test_sec ? __( 'Leave blank to keep saved secret', 'planit-event-manager' ) : '' ); ?>" autocomplete="new-password" />
						</p>
						<p><label for="paypal_live_client_id"><?php esc_html_e( 'Live client id', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="large-text" name="twec_settings[paypal_live_client_id]" id="paypal_live_client_id" value="<?php echo esc_attr( $paypal_live_cid ); ?>" autocomplete="off" />
						</p>
						<p><label for="paypal_live_client_secret"><?php esc_html_e( 'Live client secret', 'planit-event-manager' ); ?></label><br />
							<input type="password" class="large-text" name="twec_settings[paypal_live_client_secret]" id="paypal_live_client_secret" value="" placeholder="<?php echo esc_attr( $paypal_live_sec ? __( 'Leave blank to keep saved secret', 'planit-event-manager' ) : '' ); ?>" autocomplete="new-password" />
						</p>
						<p>
							<label for="paypal_webhook_id"><?php esc_html_e( 'Webhook id', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="large-text" name="twec_settings[paypal_webhook_id]" id="paypal_webhook_id" value="<?php echo esc_attr( $paypal_webhook_id ); ?>" autocomplete="off" />
						</p>
						<p class="description"><?php esc_html_e( 'From PayPal Developer → Apps → your REST app → Webhooks (create listener for PAYMENT.CAPTURE.COMPLETED). The Webhook id is shown in the webhook’s details (not a secret, but do not log it in public).', 'planit-event-manager' ); ?></p>
						<?php if ( is_string( $paypal_webhook_url ) && '' !== $paypal_webhook_url ) : ?>
						<p><strong><?php esc_html_e( 'Webhook URL to use in PayPal', 'planit-event-manager' ); ?>:</strong><br /><code><?php echo esc_html( $paypal_webhook_url ); ?></code></p>
						<?php endif; ?>
						<p>
							<label for="paypal_checkout_success_url"><?php esc_html_e( 'Return URL (optional)', 'planit-event-manager' ); ?></label><br />
							<input type="url" class="large-text" name="twec_settings[paypal_checkout_success_url]" id="paypal_checkout_success_url" value="<?php echo $paypal_success_url ? esc_url( $paypal_success_url ) : ''; ?>" placeholder="<?php echo esc_attr( __( 'Default: event URL with ?twec_paypal=success', 'planit-event-manager' ) ); ?>" />
						</p>
						<p>
							<label for="paypal_checkout_cancel_url"><?php esc_html_e( 'Cancel URL (optional)', 'planit-event-manager' ); ?></label><br />
							<input type="url" class="large-text" name="twec_settings[paypal_checkout_cancel_url]" id="paypal_checkout_cancel_url" value="<?php echo $paypal_cancel_url ? esc_url( $paypal_cancel_url ) : ''; ?>" placeholder="<?php echo esc_attr( __( 'Default: event URL', 'planit-event-manager' ) ); ?>" />
						</p>
					</fieldset>
					<fieldset class="twec-woocommerce-settings" style="border:1px solid #c3c4c7; padding:12px; margin-top:10px; max-width: 640px;">
						<legend><strong><?php esc_html_e( 'WooCommerce: event tickets (optional)', 'planit-event-manager' ); ?></strong></legend>
						<p class="description"><?php esc_html_e( 'This menu is labeled “Events” in wp-admin—it is PlanIt Event Manager settings. Sell tickets via your WooCommerce checkout: activate WooCommerce, check the master switch below and save, then set a product ID on each event. This is separate from the “Payments” Stripe/PayPal block above (that one is for featured listings, not store cart checkout).', 'planit-event-manager' ); ?></p>
						<p class="description" style="border-left:4px solid #2271b1;padding-left:10px;margin:12px 0;">
							<strong><?php esc_html_e( 'Turn on WooCommerce ticket buttons:', 'planit-event-manager' ); ?></strong>
							<?php esc_html_e( 'Tick the checkbox below and click Save at the bottom of this page. Leaving it unchecked hides ticket buttons and the product ID box on events.', 'planit-event-manager' ); ?>
						</p>
						<?php if ( ! $twec_wc_active ) : ?>
						<p class="notice notice-warning inline" style="padding:8px;"><?php esc_html_e( 'WooCommerce is not active. Install and activate the WooCommerce plugin, then return here to enable ticket products on events.', 'planit-event-manager' ); ?></p>
						<?php endif; ?>
						<p>
							<label for="woocommerce_tickets_enabled">
								<input type="checkbox" name="twec_settings[woocommerce_tickets_enabled]" id="woocommerce_tickets_enabled" value="yes" <?php checked( $woocommerce_tickets_en, 'yes' ); ?> />
								<?php esc_html_e( 'Enable WooCommerce ticket sales (product ID on events + Get tickets)', 'planit-event-manager' ); ?>
							</label>
						</p>
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: shortcode */ __( 'Single event templates can show “Get tickets” automatically. You may also paste %s into the event content.', 'planit-event-manager' ), '[twec_wc_add_to_cart]' ) ); ?></p>
						<?php if ( $twec_wc_active && 'yes' === $woocommerce_tickets_en ) : ?>
						<p>
							<label for="woocommerce_ticket_cta_list">
								<input type="checkbox" name="twec_settings[woocommerce_ticket_cta_list]" id="woocommerce_ticket_cta_list" value="yes" <?php checked( $woo_ticket_cta_list_def, 'yes' ); ?> />
								<?php esc_html_e( 'Show “Get tickets” on event lists ([twec_list]) when a ticket product is linked', 'planit-event-manager' ); ?>
							</label>
						</p>
						<p>
							<label for="woocommerce_ticket_cta_calendar">
								<input type="checkbox" name="twec_settings[woocommerce_ticket_cta_calendar]" id="woocommerce_ticket_cta_calendar" value="yes" <?php checked( $woo_ticket_cta_cal_def, 'yes' ); ?> />
								<?php esc_html_e( 'Show ticket links on calendar views ([twec_calendar]) when a ticket product is linked', 'planit-event-manager' ); ?>
							</label>
						</p>
						<p>
							<input type="hidden" name="twec_settings[woocommerce_ticket_require_buyer_details]" value="no" />
							<label for="woocommerce_ticket_require_buyer_details">
								<input type="checkbox" name="twec_settings[woocommerce_ticket_require_buyer_details]" id="woocommerce_ticket_require_buyer_details" value="yes" <?php checked( $woo_ticket_req_buyer, 'yes' ); ?> />
								<?php esc_html_e( 'Require purchaser name, email, phone, and billing address at checkout when the cart contains an event ticket', 'planit-event-manager' ); ?>
							</label>
						</p>
						<p class="description"><?php esc_html_e( 'Uses WooCommerce billing fields (classic checkout and Checkout block). Disable if another plugin already collects this data or your store policy differs.', 'planit-event-manager' ); ?></p>
						<p class="description"><?php esc_html_e( 'Override per shortcode: tickets="yes" or tickets="no" on [twec_list] or [twec_calendar].', 'planit-event-manager' ); ?></p>
						<?php endif; ?>
						<?php if ( $twec_wc_active ) : ?>
						<hr style="margin:18px 0;border:none;border-top:1px solid #dcdcde;" />
						<p><strong><?php esc_html_e( 'Ticket buttons (appearance)', 'planit-event-manager' ); ?></strong></p>
						<p>
							<input type="hidden" name="twec_settings[woocommerce_ticket_show_view_cart]" value="no" />
							<label for="woocommerce_ticket_show_view_cart">
								<input type="checkbox" name="twec_settings[woocommerce_ticket_show_view_cart]" id="woocommerce_ticket_show_view_cart" value="yes" <?php checked( $woo_ticket_show_vc, 'yes' ); ?> />
								<?php esc_html_e( 'Show “View cart” next to Get tickets', 'planit-event-manager' ); ?>
							</label>
						</p>
						<p class="description"><?php esc_html_e( '“View cart” links to WooCommerce Cart so visitors can proceed to checkout.', 'planit-event-manager' ); ?></p>
						<p>
							<label for="woocommerce_ticket_btn_style"><?php esc_html_e( 'Button style preset', 'planit-event-manager' ); ?></label><br />
							<select name="twec_settings[woocommerce_ticket_btn_style]" id="woocommerce_ticket_btn_style">
								<option value="theme" <?php selected( $woo_btn_style, 'theme' ); ?>><?php esc_html_e( 'Theme / default (.button)', 'planit-event-manager' ); ?></option>
								<option value="solid" <?php selected( $woo_btn_style, 'solid' ); ?>><?php esc_html_e( 'PlanIt filled (recommended)', 'planit-event-manager' ); ?></option>
								<option value="outline" <?php selected( $woo_btn_style, 'outline' ); ?>><?php esc_html_e( 'Outline primary + secondary', 'planit-event-manager' ); ?></option>
								<option value="custom" <?php selected( $woo_btn_style, 'custom' ); ?>><?php esc_html_e( 'Custom colors', 'planit-event-manager' ); ?></option>
							</select>
						</p>
							<?php if ( 'custom' === $woo_btn_style ) : ?>
						<p><label for="woocommerce_ticket_btn_primary_bg"><?php esc_html_e( 'Primary button background', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="regular-text" name="twec_settings[woocommerce_ticket_btn_primary_bg]" id="woocommerce_ticket_btn_primary_bg" value="<?php echo esc_attr( $woo_btn_pri_bg ); ?>" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" placeholder="#2271b1" maxlength="7" />
						</p>
						<p><label for="woocommerce_ticket_btn_primary_text"><?php esc_html_e( 'Primary button text color', 'planit-event-manager' ); ?></label><br />
							<input type="text" class="regular-text" name="twec_settings[woocommerce_ticket_btn_primary_text]" id="woocommerce_ticket_btn_primary_text" value="<?php echo esc_attr( $woo_btn_pri_txt ); ?>" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" placeholder="#ffffff" maxlength="7" />
						</p>
						<p>
							<label for="woocommerce_ticket_btn_secondary_mode"><?php esc_html_e( '“View cart” style (secondary)', 'planit-event-manager' ); ?></label><br />
							<select name="twec_settings[woocommerce_ticket_btn_secondary_mode]" id="woocommerce_ticket_btn_secondary_mode">
								<option value="outline" <?php selected( $woo_btn_sec_mode, 'outline' ); ?>><?php esc_html_e( 'Outline using primary border color', 'planit-event-manager' ); ?></option>
								<option value="ghost" <?php selected( $woo_btn_sec_mode, 'ghost' ); ?>><?php esc_html_e( 'Ghost (text-link style)', 'planit-event-manager' ); ?></option>
								<option value="muted" <?php selected( $woo_btn_sec_mode, 'muted' ); ?>><?php esc_html_e( 'Muted gray pill', 'planit-event-manager' ); ?></option>
							</select>
						</p>
						<p><label for="woocommerce_ticket_btn_radius"><?php esc_html_e( 'Corner radius (px)', 'planit-event-manager' ); ?></label><br />
							<input type="number" class="small-text" name="twec_settings[woocommerce_ticket_btn_radius]" id="woocommerce_ticket_btn_radius" value="<?php echo esc_attr( (string) $woo_btn_radius ); ?>" min="0" max="32" step="1" />
						</p>
						<p class="description"><?php esc_html_e( 'Custom colors apply inline on the storefront when WooCommerce ticket buttons render.', 'planit-event-manager' ); ?></p>
						<?php endif; ?>
						<?php endif; ?>
					</fieldset>
					<fieldset class="twec-reminders-settings" style="border:1px solid #c3c4c7; padding:12px; margin-top:10px; max-width: 640px;">
						<legend><strong><?php esc_html_e( 'RSVP email reminders (Premium)', 'planit-event-manager' ); ?></strong></legend>
						<p class="description"><?php esc_html_e( 'Send a reminder to attendees who RSVP and opt in. Requires a valid PlanIt Premium license; hourly background processing uses WordPress (or Action Scheduler if WooCommerce is present).', 'planit-event-manager' ); ?></p>
						<p>
							<label for="event_reminders_enabled">
								<input type="checkbox" name="twec_settings[event_reminders_enabled]" id="event_reminders_enabled" value="yes" <?php checked( $event_reminders_en, 'yes' ); ?> />
								<?php esc_html_e( 'Enable event start reminders for RSVP list', 'planit-event-manager' ); ?>
							</label>
						</p>
						<p>
							<label for="reminder_offset_hours"><?php esc_html_e( 'Hours before start', 'planit-event-manager' ); ?></label><br />
							<input type="number" name="twec_settings[reminder_offset_hours]" id="reminder_offset_hours" value="<?php echo esc_attr( (string) $reminder_offset_h ); ?>" min="1" max="168" class="small-text" />
							<span class="description"><?php esc_html_e( 'Default: 24. A 2-hour send window is used so hourly cron can catch the right time.', 'planit-event-manager' ); ?></span>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SEO: Open Graph & Twitter', 'planit-event-manager' ); ?></th>
				<td>
					<select name="twec_settings[seo_og]" id="seo_og">
						<option value="yes" <?php selected( $seo_og, 'yes' ); ?>><?php esc_html_e( 'Enabled', 'planit-event-manager' ); ?></option>
						<option value="no" <?php selected( $seo_og, 'no' ); ?>><?php esc_html_e( 'Disabled', 'planit-event-manager' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Basic og: and twitter: tags on single event pages for better link previews. Disable if you use a dedicated SEO plugin that already outputs these for events.', 'planit-event-manager' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		if ( is_readable( PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-settings-ai.php' ) ) {
			include PLANIT_EVENT_MANAGER_DIR . 'admin/partials/twec-admin-settings-ai.php';
		}
		?>
		<?php submit_button(); ?>
	</form>
	
	<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php esc_html_e( 'Fix Permalink Issues', 'planit-event-manager' ); ?></h2>
		<p><?php esc_html_e( 'If event pages are showing 404 errors, click the button below to flush the permalink structure.', 'planit-event-manager' ); ?></p>
		<form method="post" action="">
			<?php wp_nonce_field( 'twec_flush_rewrite_rules' ); ?>
			<input type="hidden" name="twec_flush_rewrite_rules" value="1" />
			<?php submit_button( __( 'Flush Permalinks', 'planit-event-manager' ), 'secondary', 'flush_permalinks', false ); ?>
		</form>
		<p class="description"><?php esc_html_e( 'You can also go to Settings > Permalinks and click "Save Changes" to flush permalinks.', 'planit-event-manager' ); ?></p>
	</div>
	
	<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php esc_html_e( 'Test Events', 'planit-event-manager' ); ?></h2>
		<p><?php esc_html_e( 'Create sample test events to help you test and demonstrate the calendar functionality. These events will be marked as test events and can be easily deleted later.', 'planit-event-manager' ); ?></p>
		
		<?php
		global $twec_admin_instance;
		if ( ! $twec_admin_instance ) {
			$twec_admin_instance = new TWEC_Admin();
		}
		$test_events_count = $twec_admin_instance->get_test_events_count();
		?>
		
		<?php if ( $test_events_count > 0 ) : ?>
			<div class="notice notice-info inline" style="margin: 15px 0;">
				<p><strong>
					<?php
					/* translators: %d: Number of test events */
					printf( esc_html__( 'Found %d test event(s).', 'planit-event-manager' ), absint( $test_events_count ) );
					?>
				</strong></p>
			</div>
		<?php endif; ?>
		
		<form method="post" action="" style="margin-top: 15px;">
			<?php wp_nonce_field( 'twec_create_test_events' ); ?>
			<p>
				<input type="submit" name="twec_create_test_events" class="button button-primary" value="<?php esc_attr_e( 'Create 5 Test Events', 'planit-event-manager' ); ?>" <?php disabled( $test_events_count > 0 ); ?> />
				<?php if ( $test_events_count > 0 ) : ?>
					<span class="description" style="margin-left: 10px; color: #d63638;"><?php esc_html_e( 'Please delete existing test events first.', 'planit-event-manager' ); ?></span>
				<?php endif; ?>
			</p>
		</form>
		
		<?php if ( $test_events_count > 0 ) : ?>
			<form method="post" action="" style="margin-top: 15px;" id="twec-delete-test-events-form">
				<?php wp_nonce_field( 'twec_delete_test_events' ); ?>
				<p>
					<input type="submit" name="twec_delete_test_events" class="button button-secondary" value="<?php esc_attr_e( 'Delete All Test Events', 'planit-event-manager' ); ?>" />
					<span class="description" style="margin-left: 10px;"><?php esc_html_e( 'This will permanently delete all test events.', 'planit-event-manager' ); ?></span>
				</p>
			</form>
		<?php endif; ?>
		
		<p class="description" style="margin-top: 15px;"><?php esc_html_e( 'Test events include a mix of past and future events to help you test different calendar views and features.', 'planit-event-manager' ); ?></p>
	</div>

	<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php esc_html_e( 'Subscribe to calendar (iCal / Google Calendar)', 'planit-event-manager' ); ?></h2>
		<p><?php esc_html_e( 'Use this public URL in Apple Calendar, Google Calendar (Other calendars → From URL), or any feed reader that supports iCalendar:', 'planit-event-manager' ); ?></p>
		<p><code id="twec-ics-subscribe" style="word-break: break-all; display: inline-block; max-width: 100%;"><?php echo esc_html( $ics_subscribe_url ); ?></code></p>
		<p class="description"><?php esc_html_e( 'By default, only upcoming (not yet ended) events are included. You can limit by event category: append', 'planit-event-manager' ); ?>
			<code>&amp;twec_event_category=your-category-slug</code>
		</p>
	</div>
	
	<div class="twec-settings-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php esc_html_e( 'Display Options', 'planit-event-manager' ); ?></h2>
		<p><?php esc_html_e( 'In the block editor, add the "PlanIt Calendar" or "PlanIt Event List" block, or use shortcodes below.', 'planit-event-manager' ); ?></p>
		<h3><?php esc_html_e( 'Calendar View', 'planit-event-manager' ); ?></h3>
		<p><?php esc_html_e( 'To display the calendar on any page or post, use this shortcode:', 'planit-event-manager' ); ?></p>
		<code>[twec_calendar]</code> or <code>[twec_calendar view="month"]</code>
		<p><?php esc_html_e( 'Available views: day, month (Week, Year, Photo, and Map views available in Premium)', 'planit-event-manager' ); ?></p>
		
		<h3><?php esc_html_e( 'List View (Chronological)', 'planit-event-manager' ); ?></h3>
		<p><?php esc_html_e( 'To display a chronological list of events, use this shortcode:', 'planit-event-manager' ); ?></p>
		<code>[twec_list]</code> or <code>[twec_list per_page="10" past_events="hide"]</code>
		<p><?php esc_html_e( 'Options:', 'planit-event-manager' ); ?></p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><code>per_page</code> - Number of events per page (default: 10)</li>
			<li><code>past_events</code> - "hide" or "show" past events (default: hide)</li>
			<li><code>category</code> - Filter by category slug</li>
			<li><code>tag</code> - Filter by tag slug</li>
		</ul>
		<p><?php esc_html_e( 'You can also visit the events archive page at:', 'planit-event-manager' ); ?> <a href="<?php echo esc_url( get_post_type_archive_link( 'twec_event' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_post_type_archive_link( 'twec_event' ) ); ?></a></p>
	</div>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

