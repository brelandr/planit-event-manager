# PayPal (Checkout) — PlanIt Event Manager

## Webhook URL

In the [PayPal Developer Dashboard](https://developer.paypal.com/), open your REST app → **Webhooks** → add a listener with this URL:

- `https://YOUR-DOMAIN/wp-json/planit/v1/paypal/webhook`

Subscribe to at least:

- `PAYMENT.CAPTURE.COMPLETED`

Copy the **Webhook ID** (from the webhook’s details) into **Events → Settings → Payments → PayPal → Webhook id**. The plugin verifies incoming notifications with PayPal’s `verify-webhook-signature` API using that id.

## Credentials

1. In **Settings → Payments**, set **Gateway** to **PayPal (Checkout)** and **Mode** to **Test** or **Live**.
2. Enter the **Client ID** and **Client secret** for the matching environment (Sandbox vs Live) from the same REST app.
3. **Feature price (minor units)**, **Currency**, and **Line item name** are configured in the **Stripe: API keys, webhook & feature listing price** fieldset; they apply to both gateways even on PayPal-only sites.

## Test flow

1. Use **Sandbox** credentials and Test mode, or **Live** keys with Live mode.
2. Set feature price and currency in the **Stripe: API keys, webhook & feature listing price** fieldset (shared with PayPal).
3. Start Checkout from the event editor (**Featured listing (PayPal)**) or place `[twec_paypal_checkout]` on a single event page.
4. Approve the order in the PayPal window.

On capture completion, PayPal posts `PAYMENT.CAPTURE.COMPLETED`; the plugin sets `_twec_paypal_paid`, `_twec_paypal_paid_at`, and `_twec_paypal_capture_id` on the event (idempotent per capture id).

## Sandbox verification (E2E checklist)

Run this on a **staging or local** site with **Test** mode and **Sandbox** app credentials, webhook URL matching this site, and event `PAYMENT.CAPTURE.COMPLETED` enabled.

1. In **Settings → Payments**, set gateway **PayPal (Checkout)**, **Mode** to **Test**, and save Sandbox client id, secret, and webhook id.
2. In PayPal Developer → your app → **Webhooks**, point the listener at `https://YOUR-SITE/wp-json/planit/v1/paypal/webhook` and ensure **PAYMENT.CAPTURE.COMPLETED** is selected.
3. Set **Feature price (minor units)** to at least `1` (and currency) in the same fieldset; create or open a `twec_event` and start checkout (**Pay with PayPal** in the event editor or shortcode on the event URL).
4. Approve the payment in the PayPal **Sandbox** window.
5. Confirm the event post has:
   - `_twec_paypal_paid` = `1`
   - `_twec_paypal_paid_at` = MySQL datetime string
   - `_twec_paypal_capture_id` = the PayPal capture id (repeated webhook deliveries for the same capture are ignored)

You can verify meta in the database, with a post-meta inspection plugin, or with `wp post meta get EVENT_ID _twec_paypal_paid` in WP-CLI.

## REST (optional)

- `POST /wp-json/planit/v1/paypal/create-checkout`  
  - **Headers:** `X-WP-Nonce` = valid `wp_rest` nonce (same-origin, logged in).  
  - **Body (JSON or form):** `event_id` (integer).  
  - **Response:** approval URL to complete payment in the browser.  

Requires a valid Premium license, **Payment gateway: PayPal**, and `edit_post` for that event.

## Optional: NDJSON debug file

File logging is **off** by default. For troubleshooting, you can return a writable absolute path from the `twec_paypal_debug_log_path` filter (for example a file under your uploads directory). No secrets or PII should be logged; the plugin’s debug lines are diagnostic only.
