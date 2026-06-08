# PlanIt: WordPress collaboration & command palette (R&D)

## Scope

**Real-time co-editing** and shared session transport for event posts are **not** implemented in the plugin. They depend on future WordPress / Gutenberg collaboration APIs. This document tracks the intended integration path.

## Feature flag (R&D)

- **Constant:** `TWEC_EXPERIMENTAL_EDITOR_COMMANDS` — set to `true` in `wp-config.php` to register the block-editor hook. **Default: `false`** (see [class-twec-collab-rd.php](file:///Users/randy/wordpress-plugins/planit-event-manager/includes/class-twec-collab-rd.php)).
- **Filter:** `twec_experimental_editor_commands` — return `true` to enable the same behavior without a constant (e.g. for a must-use plugin on staging).
- **When disabled:** the admin bar “New PlanIt event” link still works; the `twec-editor-commands` script and `twec_register_editor_commands` **action** are not run, avoiding accidental coupling to editor packages on production LTS sites.

**Target LTS for first “real” command (suggestion):** WordPress 6.5+ with a verified `@wordpress/commands` or successor pattern in release notes. Re-evaluate each major branch.

**Minimal v1 product (behind flag):** two command-palette entries — **“Add PlanIt event”** (navigate to new `twec_event`) and **“PlanIt event calendar settings”** (opens `edit.php?post_type=twec_event&page=twec-settings`, if the user can `manage_options`). Implemented in [`admin/js/twec-editor-commands.js`](file:///Users/randy/wordpress-plugins/planit-event-manager/admin/js/twec-editor-commands.js) via `wp.data.dispatch( 'core/commands' )` / `wp.commands.store` when available. No Yjs / shared cursor until core documents a supported path.

## What ships today

- **Admin bar:** quick link “New PlanIt event” to `post-new.php?post_type=twec_event` (see `class-twec-collab-rd.php`), useful for manual testing and for users who do not use the block editor’s command UI yet.
- **Block editor (flag on only):** script handle **`twec-editor-commands`**, enqueued on **`enqueue_block_editor_assets`** for the `twec_event` post editor. Dependencies: **`wp-data`**, **`wp-i18n`**, and **`wp-commands`** when that script is registered in core (typical on WordPress 6.4+ with the block editor). Localized object: **`window.twecEditorCommands`**. Fires action **`twec_register_editor_commands`** after the script is registered so other code can extend.
- **When disabled:** the command script and action are not loaded (admin bar link unchanged).

## What to track in core

- Gutenberg: `@wordpress/commands`, editor command store, and any **experimental** collaboration packages — treat as unstable until the release post drops the “experimental” label for your use case.
- WordPress 7.x: release notes and dev notes for **collaboration / command** APIs. Prefer feature detection in JS (`wp.commands` or equivalent) over version compares.

## Suggested timeboxed spike (when a beta exists)

1. In a local environment, set `define( 'TWEC_EXPERIMENTAL_EDITOR_COMMANDS', true );` in `wp-config.php` (or use the `twec_experimental_editor_commands` filter) and enable the relevant Gutenberg or core feature flags.
2. Enqueue a small editor script (depends on `wp-commands` if available) that registers: “Add PlanIt event” (navigate) and “Open PlanIt settings” (admin URL from localized data); listen to `twec_register_editor_commands` from a small registered script.
3. No server-side event subscription until a documented collaboration channel exists for `twec_event`. Turn the flag off before deploying to production until commands are product-ready.

## References

- [Interactivity / block roadmap](Interactivity-API.md) in this plugin is separate: it targets front-of-site calendar interactivity, not co-editing in the admin.
