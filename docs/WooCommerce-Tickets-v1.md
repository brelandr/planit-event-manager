# WooCommerce event tickets (v1) — PlanIt Event Manager

## Scope

- **One WooCommerce product per event:** each `twec_event` may store a linked Woo product ID (`_twec_wc_product_id`). Ticket price, tax, and inventory are **managed in WooCommerce**, not duplicated in PlanIt.
- **Parallel with direct gateways:** Stripe / PayPal under **Settings → Payments** apply only to PlanIt’s **direct** “featured / paid listing” flow. They are **not** used when selling tickets through Woo.
- **Optional dependency:** WooCommerce is **not** required to run PlanIt. When Woo is inactive, a notice may appear in settings; ticket UI is hidden or disabled.

## Data model (post meta on `twec_event`)

| Meta key | Type | Description |
|----------|------|-------------|
| `_twec_wc_product_id` | int | WooCommerce product (or **variation** ID for variable products). `0` or empty = not selling via Woo. |
| `_twec_wc_ticket_sale_count` | int | Cumulative quantity sold (best-effort) when orders hit **Completed**. For reporting; Woo remains source of truth for orders. |
| `_twec_wc_last_order_id` | string | Last WC order id that updated this event (audit / support). |

## Settings (`twec_settings`)

- `woocommerce_tickets_enabled` — `yes` or `no`. When `yes` and `WooCommerce` is active, event meta box and shortcode output are available.

## Order hook (source of truth)

- **`woocommerce_order_status_completed`** — when an order moves to **Completed**, line items are scanned. For each line, product (and variation) IDs are matched against events with `_twec_wc_product_id`, sale count is increased, and `twec_woo_event_ticket_sold` fires.

> **Note:** If your store uses **Processing** as the final paid state, either complete orders in WC or add a small custom `processing` handler in a child plugin.

## Admin UX

- **Events → Settings → Payments:** a **WooCommerce** subsection explains that ticket sales are separate from the Stripe/PayPal gateway and must be turned on here.
- **Event editor:** with Woo active + feature enabled, a **WooCommerce ticket product** field (ID) + link to edit the product. Invalid IDs are rejected on save.

## Front end

- Shortcode: **`[twec_wc_add_to_cart]`** with optional `event_id` (default: current `twec_event` in the loop). Renders a button or link to the standard add-to-cart URL for the linked product, or nothing if not configured.

## Security

- Save restricted by `current_user_can( 'edit_post' )` and a dedicated nonce. No card or payment data is handled by PlanIt for the Woo path.

## Premium

- Same behaviour and options in `planit-event-manager-premium` (mirrored files).
