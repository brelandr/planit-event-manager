# PlanIt AI Integration (WordPress 7.0)

PlanIt integrates with WordPress core AI features. **All AI features are disabled by default.** Enable them under **Events → Settings → AI**.

## Prerequisites

1. **WordPress 7.0** (or a development build with the WP AI Client and Abilities API).
2. A **connector** configured under **Settings → Connectors** (OpenAI, Anthropic, or another supported provider). PlanIt does not bundle API keys.
3. For Premium-only AI routes and abilities: valid **PlanIt Premium** license.

## Settings

| Setting | Purpose |
|---------|---------|
| Master enable | Required for any AI feature |
| Admin assist | Event editor sidebar panel (`draft-description`, taxonomy, social snippet, alt text) |
| Abilities API | Registers `planit/*` abilities for agents and MCP |
| Public assistant | Front-end **PlanIt Event Assistant** block |
| Command palette | Block editor commands / client-side abilities |
| Temperature preset | `factual` (lower) or `creative` for generation |

When no connector is configured, admin UI shows a notice linking to **Settings → Connectors**. On WordPress versions without `wp_ai_client_prompt()`, AI UI is hidden and routes return errors gracefully.

## Admin assist (event editor)

On `twec_event` edit screens, the **PlanIt AI Assist** panel calls server-side routes only—prompts never run in the browser.

- **Block editor:** sidebar panel (`twec-event-ai-assist.js`).
- **Classic editor:** **PlanIt AI Assist** metabox (`twec-event-ai-classic.js`) when the block editor is disabled for events.

| Route | Method | Purpose |
|-------|--------|---------|
| `/wp-json/planit/v1/ai/publish-prep` | POST | Readiness checks + combined publish copy |
| `/wp-json/planit/v1/ai/draft-description` | POST | Description + excerpt |
| `/wp-json/planit/v1/ai/suggest-taxonomy` | POST | Category/tag slug suggestions |
| `/wp-json/planit/v1/ai/social-snippet` | POST | Short social/OG blurb |
| `/wp-json/planit/v1/ai/alt-text` | POST | Featured image alt text |
| `/wp-json/planit/v1/ai/venue-description` | POST | Venue page description (venue editor) |
| `/wp-json/planit/v1/ai/organizer-bio` | POST | Organizer bio (organizer editor) |
| `/wp-json/planit/v1/ai/create-from-text` | POST | Natural language → new event draft |
| `/wp-json/planit/v1/ai/parse-event-draft` | POST | Parse NL into structured event fields |

Each request requires `post_id`, per-post nonce (`twec_ai_assist_{id}`), and `edit_post` capability. Generated text is shown as a preview; content is applied only when the editor clicks **Accept**.

## Public event assistant

Add the **PlanIt Event Assistant** block. When the public assistant setting is on and a connector exists, visitors can ask questions such as “What’s happening this weekend?”

- **Route:** `POST /wp-json/planit/v1/ai/public-query`
- **Grounding:** Server loads upcoming events (configurable day window) and sends structured JSON to the model—no hallucinated dates.
- **Rate limiting:** Per-IP transient (default 10 requests / 5 minutes).
- **Privacy:** Visitor IP is used only for rate limiting, not sent to the AI provider. Query logging is off by default.

## Abilities API (agents / MCP)

Category: `planit-events`

### Free abilities

| ID | Access | Description |
|----|--------|-------------|
| `planit/list-upcoming-events` | Read | `days`, `category`, `limit` |
| `planit/get-event` | Read | Single event + `planit_event` payload |
| `planit/search-events` | Read | Text + date window |
| `planit/create-event-draft` | Write | Creates draft with validated meta; optional `natural_language` |
| `planit/update-event` | Write | Updates an existing event from structured args |

Read abilities may expose `mcp.public: true` in meta when registered.

### Premium abilities (licensed)

| ID | Description |
|----|-------------|
| `planit/preview-recurrence` | RRULE preview dates for an event |
| `planit/rsvp-summary` | RSVP and waitlist counts (no emails) |
| `planit/moderate-submission` | Pending submission summary + optional AI suggestion |

Hooks: `twec_register_abilities`, `twec_ability_create_event_draft_args`.

## Premium admin AI routes

Requires Premium license + admin assist enabled:

| Route | Purpose | Admin UI |
|-------|---------|----------|
| `POST .../ai/rrule-from-text` | Natural language → RRULE + summary | Recurring event metabox → **Build RRULE with AI** |
| `POST .../ai/email-template` | RSVP reminder email draft | Events → Emails → **Draft reminder email with AI** |
| `POST .../ai/import-map` | CSV column → field mapping | Events → Import → **Suggest mapping with AI** |

## Command palette / client abilities

When enabled, the block editor registers:

- `planit/open-new-event`
- `planit/list-events-this-week`
- `planit/ai-draft-from-prompt` — calls `POST .../ai/create-from-text` when admin assist + AI available

WordPress 7.0 uses `@wordpress/core-abilities` when present; older builds fall back to `wp.commands`.

## Data sent to AI providers

When generation runs, the site may send to the **configured connector provider**:

- Prompt text built from event title, dates, venue, categories, or visitor question
- For public queries: titles, dates, and URLs of upcoming events (no attendee PII)

Review your provider’s terms and billing. See `readme.txt` **External Services** for disclosure links.

## Compatibility

| Environment | Behavior |
|-------------|----------|
| WP 6.2 | No AI UI; no fatals |
| WP 7.0, no connector | Settings notice; routes return unavailable |
| WP 7.0 + connector | Full flow when settings enabled |

## Filters

- `twec_ai_public_query_locale` — public assistant reply locale (Premium)
- `twec_experimental_editor_commands` — legacy command palette toggle
