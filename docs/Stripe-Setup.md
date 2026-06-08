# Stripe (v1) — PlanIt Event Manager

## Webhook URL

Use this exact URL in the [Stripe Dashboard](https://dashboard.stripe.com/webhooks) (or with the Stripe CLI for local development):

- `https://YOUR-DOMAIN/wp-json/planit/v1/stripe/webhook`

The plugin verifies the `Stripe-Signature` header with your **webhook signing secret** (`whsec_…`) saved in **Events → Settings → Payments → Webhook signing secret**.

## Event type

Select at least:

- `checkout.session.completed`

## Stripe CLI (local / staging)

1. [Install the Stripe CLI](https://stripe.com/docs/stripe-cli).
2. Login: `stripe login`
3. Forward events to your site:

```bash
stripe listen --forward-to "https://yoursite.test/wp-json/planit/v1/stripe/webhook"
```

The CLI prints a **webhook signing secret** (e.g. `whsec_…`). Use that value in PlanIt settings for local testing. Do not mix the CLI secret with a Dashboard endpoint secret.

## Test flow

1. Set **Mode** to **Test** and add **Test** API keys in PlanIt **Settings → Payments → Stripe** block.
2. Set **Feature price (minor units)** (e.g. `2900` for $29.00 in `usd`).
3. Start Checkout from the event editor (**Featured listing (Stripe)**) or with the shortcode `[twec_stripe_checkout]` on a single event.
4. Complete payment with a [test card](https://stripe.com/docs/testing) (e.g. `4242 4242 4242 4242`).

On success, Stripe sends `checkout.session.completed`; the plugin sets `_twec_stripe_paid`, `_twec_stripe_paid_at`, and `_twec_stripe_session_id` on the event.

## REST (optional)

- `POST /wp-json/planit/v1/stripe/create-checkout`  
  - **Headers:** `X-WP-Nonce` set to a valid `wp_rest` nonce (same-origin, logged in).  
  - **Body (JSON or form):** `event_id` (integer).  
  - **Response:** `{ "url": "https://checkout.stripe.com/…", "session_id": "cs_…" }`  

Requires a valid Premium license, **Payment gateway: Stripe**, and `edit_post` for that event.
