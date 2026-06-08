# Regression checklist: direct payments (Stripe / PayPal)

Run after changes to **WooCommerce ticket** code or shared settings so PlanIt’s **direct** gateways keep working.

## Preconditions

- Premium license active (if your site gates checkout on license).
- Test event with `edit_post` access as the purchasing user.

## Stripe (Checkout)

1. **Settings → Payments:** Gateway **Stripe (Checkout)**, valid test keys, **Feature price** at least `1` in minor units, webhook secret set; Stripe Dashboard webhook to `…/wp-json/planit/v1/stripe/webhook`.
2. From the event admin **Featured listing (Stripe)** (or the Stripe shortcode on the front if used), start checkout, pay with a test card.
3. **Expect:** Webhook or session path records paid meta; event shows as paid in the meta box / intended UI.

## PayPal (Checkout)

1. Gateway **PayPal (Checkout)**, Sandbox credentials, **Webhook id** and PayPal listener on `…/wp-json/planit/v1/paypal/webhook` for `PAYMENT.CAPTURE.COMPLETED`.
2. Complete a Sandbox purchase from the event **Pay with PayPal** path (or PayPal shortcode).
3. **Expect:** `_twec_paypal_paid` and related meta set after capture.

## WooCommerce (optional)

- Direct Stripe/PayPal flows are **independent** of the Woo ticket checkbox. Toggling **WooCommerce: event tickets** on or off should not change `payment_gateway` or direct API keys. If you see interference, file a bug with settings export (redact secrets).
