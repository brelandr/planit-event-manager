# Event email reminders (v1) — product & technical spec

This document **locks v1 scope** for a future implementation. It does not ship code by itself; use it as acceptance criteria for development.

## Product decisions (v1)

| Decision | Choice | Notes |
|----------|--------|--------|
| **Audience** | **RSVP list only** | Only addresses that have successfully RSVP’d via `[twec_rsvp]` / `POST /planit/v1/rsvp`. Site-wide “notify everyone” or marketing lists are out of scope. |
| **Opt-in to receive a reminder** | **Explicit opt-in** (checkbox on RSVP) | Default **on** the checkbox (“Remind me before the event”) to maximize usefulness; off means no email. Stored per `(event_id, email)` to align with `twec_rsvp_emails` (see data model). |
| **When to send** | **24 hours before event start** (configurable in settings later: e.g. 1h, 1 week) | v1 uses a single **offset** setting in `twec_settings` (e.g. `reminder_offset_hours` = 24). |
| **Unsubscribe** | **One link per email** (token that marks “no reminders for this event+email” or global opt-out) | v1: per-event is enough. Do not log full email addresses in debug logs. |

## Data model (v1)

- **Existing:** `_twec_rsvp_emails` (array of emails), `twec_rsvp_recorded` action on [class-twec-premium-pillars.php](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/class-twec-premium-pillars.php).
- **New (implementation):** either:
  - **Option A:** post meta `twec_rsvp_reminder` = map `md5( strtolower( email ) ) => '1'|'0'` for opt-in, or
  - **Option B:** separate meta keys `_twec_rsvp_reminder_{hash}` (clutter) — **prefer Option A** as serialized object or JSON in one meta.
- **Dedupe (sent state):** post meta or site option `twec_reminder_sent` keyed by `event_id|email_hash|offset` to prevent double-send if cron runs twice (TTL or permanent until event end).

**Send time source:** event start = `_twec_event_start_date` (and time fields) in **event** timezone meta when set, else [site timezone](https://developer.wordpress.org/reference/functions/wp_timezone_string/).

## Scheduler strategy

| Strategy | When to use |
|----------|--------------|
| **Action Scheduler** | If `function_exists( 'as_schedule_recurring_action' )` (WooCommerce or other plugin has loaded Action Scheduler) — prefer **per-scheduled** or **sweep** job. |
| **WP-Cron (fallback)** | Single recurring hook (e.g. `twec_reminder_sweep` **hourly**) that selects events with start time in **\[now+23h, now+25h\]** (wide window) for 24h offset, then sends and marks sent. Wider window compensates for missed crons. |
| **Not in v1** | Per-user `wp_schedule_single_event` for every RSVP (does not scale, fragile on low traffic). |

## Email content (v1)

- **Transport:** `wp_mail()` with HTML or plain text template under `includes/emails/` (implementation detail).
- **Content:** event title, start time in locale format, **link to event**, unsubscribe link, site name. No third-party images required.

## Acceptance criteria (v1)

1. If reminders are **disabled** in settings, no emails send and no cron errors.
2. Only emails that **RSVP’d** and **opted in** receive a message.
3. Each `(event, email, offset)` pair receives **at most one** reminder for that occurrence.
4. If the event is **all-day** or **timezone boundary**, the computed “24h before” is **not off by a day** (document test cases for implementation QA).
5. Unsubscribe link works without logging in; further reminders for that pair stop.
6. **Multisite / caching:** no reliance on per-request in-memory only (persist sent state).

### Implementation QA checklist (AC4)

Run these **manually** after deploying the premium sweep (`TWEC_Reminders::run_sweep` + `maybe_send_for_event` unix **send-at** window). Document results in your release notes.

| # | Scenario | Setup | Expect |
|---|----------|--------|--------|
| 1 | **All-day start** | Event with `_twec_event_all_day` = `1`, start stored as `Y-m-d 00:00:00` in event TZ; `reminder_offset_hours` = 24; hourly sweep fires when wall clock is ~24h before that midnight instant (± slack). | One reminder per opted-in RSVP; **not** shifted by a full calendar day. |
| 2 | **Event TZ ≠ site TZ** | Event `_twec_event_timezone` = e.g. `America/New_York`, site `wp_timezone` = `Europe/London`; start at a civil time that differs by date across zones. | Send time is **N hours before event-local start** (unix comparison), not “site string BETWEEN” on `_twec_event_start_date`. |
| 3 | **Large offset** | Set `reminder_offset_hours` near max (e.g. 168). | Reminder sends in the configured window before start; loose meta prune still includes the event (adjust `twec_reminder_sweep_prune_days_behind` if you tune aggressively). |
| 4 | **Cron delay** | Force cron / AS ~1h late once. | Dedupe meta `_twec_reminder_sent` still prevents double send for the same `(event, email, offset)` key. |
| **Large catalogs** | Many upcoming events in the meta prune window. | The sweep uses paged queries (`twec_reminder_sweep_batch_size`, default 200, max 500) so delivery is not capped at a single page of posts. |

### Operator tuning

- **Loose meta prune:** The query uses `_twec_event_start_date` between derived bounds, then **send time** is decided in PHP using event timezone and unix offset (`twec_reminder_sweep_prune_days_behind`, `twec_reminder_sweep_slack_seconds`). If `reminder_offset_hours` is large (up to 168), ensure prune lookahead (`offset + 72` hours in code) remains sufficient or adjust filters per site.
- **Batch size:** `add_filter( 'twec_reminder_sweep_batch_size', function() { return 100; } );` lowers per-tick DB load; the sweep runs hourly (or Action Scheduler equivalent).


- SMS, push, WooCommerce “my account” digests, recurring **series** reminders (one email per parent vs per instance — product choice later).
- “Remind me 15 minutes before” (requires finer cron or queue).

## References

- [ROADMAP-Partial-Features.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/ROADMAP-Partial-Features.md)
- `planit/v1/rsvp` REST in PlanIt Premium pillars.
