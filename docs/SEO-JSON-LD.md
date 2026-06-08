# PlanIt Event Manager – Event structured data (JSON-LD)

This plugin outputs **Schema.org** `Event` data on single event pages (when **SEO: JSON-LD** is enabled under *Events → Settings*).

## What is output

- **`@type`**: `Event` (Google rich results for events expect `Event` with valid dates and location/attendance where applicable; see [Google Event structured data](https://developers.google.com/search/docs/appearance/structured-data/event)).
- **`eventStatus`**: `https://schema.org/EventScheduled` (cancelled/postponed can be added later).
- **`eventAttendanceMode`**: driven by the per-event **Attendance** field:
  - In person → `OfflineEventAttendanceMode`
  - Online → `OnlineEventAttendanceMode` (location uses `VirtualLocation` with your online URL)
  - Hybrid → `MixedEventAttendanceMode` (location can include place + virtual)
- **`location`**: `Place` (venue) and/or `VirtualLocation` (online/hybrid) as required.
- **`organizer`**: `Organization` from the linked **Organizer** (name, email, phone, url when present). If no organizer is selected, the **site** is used as a fallback `Organization` (name + URL, optional logo from the theme/site logo).
- **`offers`**: when a **cost** is set (Premium), an `Offer` is included; validate price formats for your region in Search Console.
- **Filters**: `twec_event_json_ld` filters the final array; `twec_seo_output_event_meta` can disable all output; `twec_seo_event_currency` filters offer currency (default `USD`).

## Testing

1. **Google Rich Results Test**: paste a published single-event URL.
2. **Settings**: Disable JSON-LD if a dedicated SEO plugin already prints event schema and you see duplicates.

## VirtualEvent / ScheduledEvent

Google’s current documentation centers on the **`Event`** type with `eventAttendanceMode` and dates—not separate `@type` values such as `VirtualEvent` for general rich results. This plugin therefore keeps `Event` and maps attendance correctly. You can still adjust the graph via `twec_event_json_ld` if a future spec or regional requirement needs a different shape.
