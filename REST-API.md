# PlanIt Event Manager – REST API

The `twec_event` post type is registered with `show_in_rest: true`, so it appears in the WordPress REST API under the standard posts controller.

## Base URL

- Collection: `/wp-json/wp/v2/twec_event`
- Single: `/wp-json/wp/v2/twec_event/{id}`

Public access follows normal WordPress rules (published content is readable when the REST API is not restricted by other plugins).

## Custom field: `planit_event`

Each event response includes an object field:

- **`planit_event`** – `start_date`, `end_date`, `all_day`, `venue` (post ID), `organizer` (post ID), `cost`, `website`, `timezone` (meta-driven; may be `null`).

## Collection query parameters

Standard `wp/v2` query args apply (`per_page`, `search`, `orderby`, etc.). In addition:

| Parameter      | Description |
|----------------|-------------|
| `twec_after`   | Only events whose `_twec_event_start_date` is **on or after** this value (string, e.g. `Y-m-d` or `Y-m-d H:i:s`). |
| `twec_before`  | Only events whose `_twec_event_start_date` is **on or before** this value. |

You can combine `twec_after` and `twec_before` for a window.

### Example

Upcoming events starting on or after today (site time):

```
GET /wp-json/wp/v2/twec_event?per_page=20&twec_after=2026-04-27
```

## Creating and updating events (write access)

Write access uses the **core** `wp/v2/twec_event` endpoints. Authorisation follows normal WordPress capability checks (`rest_cannot_create`, `rest_cannot_edit`, `edit_post`, `publish_posts`, etc.) for the `twec_event` post type.

### Application Passwords (integrations and headless clients)

For server-to-server or third-party apps, use **[Application Passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)** (WordPress 5.6+). Create a password for a user that can create/publish events, then authenticate REST requests with Basic auth or the `Authorization` header as documented for your environment.

### Creating a post with Event Data meta

`POST /wp-json/wp/v2/twec_event` with a JSON body including `title`, `status`, and `meta` keys that match post meta (underscore-prefixed):

- `_twec_event_all_day` – `'1'` or `'0'`
- `_twec_event_start_date`, `_twec_event_end_date` – stored datetime strings (same shape as saved in the editor; use `Y-m-d` date portion where applicable)
- `_twec_event_start_time`, `_twec_event_end_time` – `H:i:s`

Invalid date ranges are rejected by **`TWEC_REST::rest_pre_dispatch_validate_twec_meta`** before the request is processed (see `includes/class-twec-rest.php`).

### Updating meta on an existing event

`POST` or `PATCH /wp-json/wp/v2/twec_event/{id}` with a `meta` object; the same validation runs when any of the Event Data keys appear in the patch.

### Custom field `planit_event`

`planit_event` is exposed for **read** via `register_rest_field`; it aggregates event metadata for API consumers. Prefer reading this field in `GET` responses; writes go through **`meta`** as above.

## Quick Add (embedded calendar)

Privileged users can create a draft or published event via a dedicated route (same storage rules as `TWEC_Event_Datetime` in `includes/class-twec-event-datetime.php`):

- **`POST /wp-json/planit/v1/events/quick-add`**
- Body JSON: `title` (required), `status` (`draft`|`publish`), `all_day`, `start_date`, `end_date`, `start_time`, `end_time` (see `TWEC_REST::register_quick_add_route` in `includes/class-twec-rest.php`).

Filters:

- **`twec_quick_add_capability`** – default `edit_posts`; raise to restrict who may call the route.
- **`twec_quick_add_allowed`** – final boolean allow/deny filter.

Use Quick Add for logged-in, same-site UI flows; integrations that need full CRUD should use **`/wp/v2/twec_event`** with Application Passwords or OAuth as appropriate.

## `planit/v1` routes (recurrence preview, RSVP tooling)

PlanIt registers namespaces beyond core `wp/v2` under **`/wp-json/planit/v1/…`**.

- **Recurrence RRULE preview** (admin editor tooling) is implemented in **`includes/class-twec-recurring.php`** when the recurring feature is active.
- **Submissions + RSVP + check-in** are implemented in **`includes/class-twec-premium-pillars.php`**. Many callback paths also require a valid **Premium license** (`TWEC_License::is_licensed()`) where noted in code—even if the route exists in the build, unlicensed callers may receive **403**.

Authentication for these routes typically relies on a **cookie session** plus JSON body field **`nonce`** verified against action **`wp_rest`** (same pattern as other same-site `fetch` calls).

### `POST /wp-json/planit/v1/recurrence/preview`

Returns the first matching recurring instance **start/end pairs** for a draft or saved event, using the engine in **`includes/twec-rrule.php`**.

| Aspect | Detail |
|--------|--------|
| **Permission callback** | Caller must have **`edit_posts`**. |
| **Body (JSON)** | **`nonce`** (`wp_rest`), **`post_id`** (event), **`rrule`** string, optional **`exdates`** string. |
| **`nonce` + capability** | **`nonce`** must verify; user must be able to **`edit_post(post_id)`**. |
| **200 response** | `{ "preview": [ { "start": "…", "end": "…" }, … ] }` — capped (implementation slices; see `rest_rrule_preview` in `TWEC_Recurring`). |
| **Typical errors** | **400** invalid JSON, empty RRULE handling, or missing event start meta; **403** bad nonce or no access to post; **500** if the RRULE engine is unavailable. |

Event start/end must exist in meta before preview (see error text in `rest_rrule_preview`).

### `POST /wp-json/planit/v1/rsvp/checkin`

Staff validation of an attendee who RSVPed: confirms the email is on the RSVP list and that the **`token`** matches the stored per-attendee secret (issued when exporting CSV or confirming RSVP, depending on flows).

**Alias:** **`POST /wp-json/planit/v1/rsvp-scan`** — identical handler, permission model, and JSON body; use either path (blueprint naming used `rsvp-scan`).

| Aspect | Detail |
|--------|--------|
| **Permission callback** | Must be **logged in** and have **`edit_posts`** (same-site staff). The callback also enforces **`edit_post(event_id)`**. |
| **Premium** | Returns **403** **`twec_premium`** if Premium is not licensed. |
| **Rate limiting** | Per logged-in staff user, per clock minute (transient bucket). Default **60** successful nonce checks per minute per user; **429** with code **`rate_limit`** when exceeded. Developer filter: **`twec_rsvp_checkin_rate_limit_per_minute`** (return **`0`** or less to disable). Applies equally to **`/rsvp/checkin`** and **`/rsvp-scan`**. |
| **Body (JSON)** | **`nonce`** (`wp_rest`), **`event_id`**, **`email`**, **`token`**. |
| **200 response** | `{ "ok": true, "event_id": <int>, "email": "<email>" }` |
| **Related** | Public RSVP create: `POST /wp-json/planit/v1/rsvp`. Cancel: `POST /wp-json/planit/v1/rsvp/cancel` (see `TWEC_Premium_Pillars::register_rest`). |

### Premium vs free packaging

The **free.org** package and the **Premium** add-on may both ship overlapping `planit/v1` implementations for convenience; **authorization and licensing** determine what succeeds at runtime. When Premium is not active or not licensed, expect **403** responses from gated callbacks even if routes are registered after a merge.

## AI routes (`planit/v1/ai/*`)

Implemented in **`includes/class-twec-ai.php`** (free). All routes require **AI enabled** in **Events → Settings → AI** and a site **connector** (`wp_ai_client_prompt()`). Admin routes require `edit_post` (or `manage_options` for import map) and a per-action nonce.

| Route | Auth | Body | Response |
|-------|------|------|----------|
| `POST .../ai/draft-description` | `twec_ai_assist_{post_id}` | `post_id`, `nonce` | `{ description, excerpt }` |
| `POST .../ai/suggest-taxonomy` | same | same | `{ categories[], tags[] }` |
| `POST .../ai/social-snippet` | same | same | `{ snippet }` |
| `POST .../ai/alt-text` | same | same | `{ alt_text }` |
| `POST .../ai/public-query` | optional logged-out | `query`, `days` | `{ answer, events[] }` — only when public assistant enabled; rate-limited |

**Premium** (license + admin assist): `rrule-from-text`, `email-template`, `import-map` in **`includes/class-twec-ai-premium.php`**.

See **`docs/AI-Integration.md`** for setup, privacy, and abilities.

## Abilities API

When **Abilities** is enabled, PlanIt registers category **`planit-events`** (`includes/class-twec-abilities.php`):

| Ability ID | Type |
|------------|------|
| `planit/list-upcoming-events` | Read |
| `planit/get-event` | Read |
| `planit/search-events` | Read |
| `planit/create-event-draft` | Write (draft) |

Premium (licensed): `planit/preview-recurrence`, `planit/rsvp-summary`, `planit/moderate-submission`.

Extensibility: `do_action( 'twec_register_abilities' )`, `apply_filters( 'twec_ability_create_event_draft_args', $args )`.

## Manual QA checklist (release regression)

Before tagging a release, spot-check these in **wp-admin → Site Editor / page editor**:

- Insert each **PlanIt block** (`calendar`, `event-list`, RSVP, submission, PayPal, Stripe, WooCommerce tickets): ServerSideRender preview loads without PHP errors; payment/WC blocks show editor placeholders where gated. When `admin/css/twec-blocks-editor.css` is bundled, blocks attach **`editor_style`** handle `planit-twec-blocks-editor`; confirm gateway/WC placeholders pick up `.twec-block-editor-preview-placeholder` (dashed frame, readable copy).
- **Quick Add** on the front-end calendar (logged-in user with caps): invalid date ranges show feedback before POST; successful create refreshes **Interactivity** calendars (`twecPlanitReloadCalendar` path) and the **AJAX fallback** still requests `response_format=compact` and `calendar_payload_version=2` with `grid` hydration when applicable.
- **REST write path** (staging): `POST /wp/v2/twec_event` or `PATCH` with realistic `meta`; invalid ranges return 400 via `rest_pre_dispatch_validate_twec_meta`.

Automated checks in this repo:

- `php -l` on touched PHP files.
- `./vendor/bin/phpunit -c phpunit.xml.dist` (see `tests/`).
- `./vendor/bin/phpcs --standard=phpcs.xml` (optional: limit to changed paths to keep signal high).
- `create-plugin-zip.sh` for a distribution zip (excludes `vendor/`, `tests/`, and most dev files per script).
