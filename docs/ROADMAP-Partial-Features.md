# PlanIt — follow-up epics (partially built or roadmap)

Workstreams below are **separate** from the WooCommerce ticket v1 integration. Prioritize in product order; technical order suggested.

**Detailed specs and matrices (implementation-ready documentation):**

- [Reminders-Spec.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/Reminders-Spec.md) — v1 audience, opt-in, 24h offset, scheduler, acceptance criteria.
- [RRULE-Matrix.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/RRULE-Matrix.md) — `TWEC_RRule_Expand` support vs gaps, test matrix, link to [class-twec-recurring.php](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/class-twec-recurring.php).
- [WP-Collaboration-RD.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/WP-Collaboration-RD.md) — `TWEC_EXPERIMENTAL_EDITOR_COMMANDS` and Gutenberg R&D.

## 1. Email reminders (e.g. 24h before event)

- **Status (Premium):** **Shipped** — per-RSVP opt-in, templates, offsets/cron + Action Scheduler, GDPR export/erasure in Premium privacy, optional SMS (Twilio). See the Premium docs and `planit-event-manager-premium/includes/class-twec-reminders.php`.
- **Status (Free):** Spec remains in [Reminders-Spec.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/Reminders-Spec.md); runtime reminders are **not** part of the free package.
- **Follow-ups:** Low-traffic cron caveats; template/preview polish; product-led unsubscribe depth.

## 2. Recurring / RRULE depth

- **Status:** Parser and recurring support exist in Premium paths; full RFC 5545 edge cases, validation UI, and stress testing remain. See [RRULE-Matrix.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/RRULE-Matrix.md).
- **Work:** [twec-rrule.php](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/twec-rrule.php) and recurring classes — use the matrix to drive tests; all-day and timezone edge cases, UI for UNTIL/COUNT, performance on large series.

## 3. Collab / editor R&D (WordPress 6.x+)

- **Status:** Hook and **feature flag** (`TWEC_EXPERIMENTAL_EDITOR_COMMANDS` / `twec_experimental_editor_commands`); not a productized command set by default. See [WP-Collaboration-RD.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/WP-Collaboration-RD.md).
- **Work:** Re-evaluate as core Block Editor and Interactivity APIs stabilize; enable the flag only for spikes; ship minimal commands when `@wordpress/commands` (or successor) is stable for third parties.

## 4. Other niceties (from product backlog)

- Cookieless view counter, submission/RSVP REST — incremental hardening and rate limits.
- Optional: “paid until” expiries, refunds rules — product decisions first.

**Suggested order:** RRULE / calendar edge cases (risk) → free/premium UX parity where intentional → collab (API-dependent).
