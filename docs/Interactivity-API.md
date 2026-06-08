# PlanIt Event Manager: Calendar interactivity (WordPress 6.5+)

## Overview

On WordPress 6.5 and later, the event calendar can use the [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) (`@wordpress/interactivity` script module) and a small client store in `public/js/twec-calendar-view.js` so **Previous / Next / view / Today** update the calendar without a full page reload. Navigation still loads HTML via the existing `admin-ajax.php` action `twec_get_calendar` (same as the jQuery path).

## Configuration

- **Settings → Event Calendar → Calendar: Interactivity API** — global default (`yes` / `no`), stored in `twec_settings[calendar_interactivity]`.
- **Shortcode** — `[twec_calendar interactivity="no"]` or `interactivity="yes"` to override for that embed.
- **Block** (PlanIt calendar) — sidebar toggle **“Use Interactivity API (WP 6.5+)”** maps to the shortcode override.
- **Filter** — `twec_use_interactivity` (boolean) receives the default and the shortcode/block attribute array.

## Fallback (AJAX / older WordPress)

- If Interactivity is off, APIs are missing, or the embed opts out, the first non-Interactivity `.twec-calendar-wrapper` on the page uses `public/js/twec-public.js` (jQuery) with the same AJAX action and nonce. Wrappers with `data-wp-interactive="planit/calendar"` are left to the interactivity store.

## LCP and performance

- **Measuring LCP:** Use Chrome DevTools → Performance, or the Web Vitals extension; compare a cold load of a page with only the month calendar, global Interactivity on vs off, and block per-block off (`interactivity="no"`) to isolate script module cost.
- **Recommendation:** If LCP regresses on block themes, disable Interactivity globally or per block and rely on the jQuery path; server-rendered first paint and AJAX updates remain available.

## Developer notes

- Store namespace: `planit/calendar` (`public/js/twec-calendar-view.js`).
- States are bootstrapped with `wp_interactivity_state()` in `public/partials/twec-calendar.php` when `twec_calendar_should_use_interactivity()` is true.
