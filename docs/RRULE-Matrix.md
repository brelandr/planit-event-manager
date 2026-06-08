# RRULE support matrix (PlanIt) — `TWEC_RRule_Expand`

**Sources:** [includes/twec-rrule.php](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/twec-rrule.php) (`TWEC_RRule_Expand`), [class-twec-recurring.php](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/class-twec-recurring.php) (calls `expand()` when advanced mode is on).

**Recurring “simple” mode** (not advanced) uses `TWEC_Recurring::get_recurring_instances()` without `TWEC_RRule_Expand` — it steps daily/weekly/monthly/yearly with `get_next_occurrence()`. The matrix below applies to **advanced RRULE** and to `expand()`.

## Global limits

| Item | Value | Notes |
|------|--------|--------|
| `MAX_INSTANCES` (default) | 500 | Filter: `twec_recurring_max_instances` |
| Internal expansion guard | `k < 2000` in `while` | See `expand()`; caps iterations before `MAX_INSTANCES` may apply |
| `COUNT` and loop | `COUNT` limited by `min(COUNT, max)`; inner loop can exit early on `UNTIL` / range | |

## RFC 5545 — partial support in `parse_rrule` / `expand()`

| Feature | Status | Notes |
|---------|--------|--------|
| `FREQ` | **Partial** | `DAILY`, `WEEKLY`, `MONTHLY`, `YEARLY` in `instance_simple` / byday paths. No `SECONDLY` / `MINUTELY` / `HOURLY`. |
| `INTERVAL` | **Supported** | Min 1; applied in simple and BYDAY paths. |
| `UNTIL` | **Supported** | `parse_until()`: `YYYYMMDD` or `YYYYMMDDThhmmssZ`-style (prefix used); time component partially normalized. |
| `COUNT` | **Supported** | Capped at `max` (MAX_INSTANCES or filter). |
| `EXDATE` (exclusions) | **EXDATE in UI is separate** | Raw excluded **dates** via `exdates` string → `parse_exdates()` (Y-m-d lines). Excludes by **date key**; time-of-day in EXDATE not modeled. |
| `BYDAY` (weekly) | **Supported** | Comma-separated weekdays (e.g. `TU,TH`): all tokens expanded in **chronological** order per `INTERVAL` week. |
| `BYDAY` (monthly) | **Partial** | Nth / last (e.g. `-1FR`, `2TU`); `BYDAY` without ordinal falls back to simple MONTHLY. |
| `BYMONTHDAY` | **Not supported** | Omitted in parser; no branch in `expand()`. |
| `BYMONTH` | **Partial** | With `FREQ=YEARLY` and **positional** `BYDAY` (e.g. `1FR`, `-1TH`): `expand_yearly_bymonth_byday()`. Comma-separated month list (1–12). Plain weekday without ordinal is not supported on this path. |
| `BYYEARDAY` | **Not supported** | |
| `WKST` | **Not supported** | Week start fixed by PHP `DateTime` / local logic. |
| `RRULE` line folding / multiple rules | **Not supported** | Single `RRULE` string passed in. |

## DateTime / duration behavior

- **Duration** between `base_start` and `base_end` is applied as a **second delta** to each instance start; all-day edge cases follow PHP `strtotime` behavior — **known gap** for cross-midnight all-day in some locales.
- **EXDATE** matching uses **Y-m-d** of instance start: instances whose **day** is excluded are skipped. EXDATE for datetime-specific exclusions is not implemented.

## Premium UI ([class-twec-recurring.php](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/class-twec-recurring.php))

- Admin copy states the supported RRULE subset, including comma-separated weekly `BYDAY`.
- `generate_recurring_instances()` is a **no-op** placeholder; instance generation is **on-demand** via `get_recurring_instances()` (used by query/calendar paths).

## Test matrix (recommended for automated tests)

| # | Input sketch | Expect |
|---|----------------|--------|
| T1 | `FREQ=DAILY;INTERVAL=1;COUNT=3` + valid DTSTART/DTEND | 3 instances, no overflow |
| T2 | `UNTIL=20251201` (date form) + `FREQ=DAILY` | Stops at/before UNTIL |
| T3 | EXDATE list excluding middle day | 2 instances in a Jan1–Jan3 window (`UNTIL` + `EXDATE`); `COUNT` + `EXDATE` interaction is product-specific — see tests |
| T4 | `FREQ=MONTHLY;BYDAY=-1FR;INTERVAL=1` + known anchor | Last Friday of month |
| T5 | `FREQ=WEEKLY;BYDAY=TU;INTERVAL=1` | Single weekday; see T5b for `TU,TH` |
| T5b | `FREQ=WEEKLY;BYDAY=TU,TH;INTERVAL=1` | Multiple weekdays, chronological order |
| T6 | `MAX_INSTANCES` filter set to 5 | No more than 5 results |
| T7 | `FREQ=YEARLY;BYMONTH=10;BYDAY=1FR;INTERVAL=1;COUNT=2` + anchor before first occurrence | First Friday of October per year (see tests) |
| T8 | `FREQ=YEARLY;BYMONTH=10;BYDAY=1FR;INTERVAL=2;COUNT=2` | Every other year (see tests) |

## Gaps (future work)

1. **`BYMONTHDAY`**, **`BYSETPOS`**, **`FREQ=HOURLY`**, etc.
2. **All-day** and **time zone** in `UNTIL` / DTSTART (RFC 5545 floating vs TZID).
3. **UI “preview next N”** and validation when `COUNT` × complexity hits iteration cap.
4. Align [TWEC_Recurring::get_recurring_instances()](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/class-twec-recurring.php) non-advanced path with `TWEC_RRule_Expand` where rules overlap to avoid two sources of truth.

## References

- [ROADMAP-Partial-Features.md](file:///Users/randy/wordpress-plugins/planit-event-manager/docs/ROADMAP-Partial-Features.md)

## Maintainer note

`includes/twec-rrule.php` in this repo must stay **byte-identical** to the premium plugin copy. After RRULE changes, edit the file in **`planit-event-manager-premium`** (canonical), then run **`planit-event-manager-premium/scripts/sync-twec-rrule.sh`** from that checkout so this tree is updated.
