---
title: WordCamp Session & Speaker Import
description: How the plugin imports sessions and speakers from native WordCamp sites and assigns them to hub events on a daily schedule.
visibility: internal
---

## Overview

Events flagged as **major WordCamps** — camps running the native WordCamp.org
tech stack with a public `wp-json/wp/v2/` REST API — automatically have their
**sessions** and **speakers** imported into the hub and assigned to the matching
event. The import runs **daily** via [Action Scheduler](https://actionscheduler.org/)
and needs no UI to operate.

- Speakers become attendee `User_Profile` records (subscribers).
- Sessions become `wpcamp_session` posts assigned to the originating event.
- Imports are **idempotent**: re-running updates existing records instead of
  duplicating, keyed by a stable `<host>:<id>` source identifier.

## Flagging an event for import

Set two meta fields on a `wpcamp_event`:

| Meta key | Type | Purpose |
| --- | --- | --- |
| `wpcamp_is_major_wordcamp` | boolean | Marks the event as a native-stack WordCamp eligible for import. |
| `wpcamp_wordcamp_api_url` | URL | The WordCamp site/API URL. Any of the site root, `…/wp-json`, `…/wp-json/wp/v2/`, or a full collection URL is accepted — it is normalized to the `wp/v2` REST root. |

Example API URL: `https://europe.wordcamp.org/2026/wp-json/wp/v2/`

`Event::major_wordcamps()` returns every flagged event that also has an API URL.
The accessors `Event::is_major_wordcamp()` and `Event::get_wordcamp_api_url()`
expose the per-event values.

## How the schedule works

`Import_Scheduler` (`src/Import/Import_Scheduler.php`) splits the work into
bounded Action Scheduler jobs in the shared `wpcamp-hub` group:

1. **`wpcamp_hub/import_wordcamp_schedule`** — a single recurring **daily**
   master job, installed on `action_scheduler_init` (`schedule_daily_import()`
   is idempotent and uses Action Scheduler's `$unique` flag). On each run it
   scans `Event::major_wordcamps()` and, per event, fans out **two independent**
   jobs via `queue_event_import()`.
2. **`wpcamp_hub/import_wordcamp_event_speakers`** (`event_id`, `page`) — imports
   **one page** of speakers, then reschedules itself for the next page.
3. **`wpcamp_hub/import_wordcamp_event_sessions`** (`event_id`, `page`) — imports
   **one page** of sessions, then reschedules itself for the next page.

Speakers and sessions are **independent** — neither chains into the other. A
session that references a not-yet-imported speaker fetches that speaker on demand,
so the two resources can run in any order or in parallel. Page-at-a-time
scheduling keeps every job small and lets Action Scheduler retry a single failed
page without re-running an entire camp. The recurring job and its pending
per-event jobs are removed on plugin deactivation (`unschedule_daily_import()`).

### Designed for one master sync per event

This importer deliberately mirrors the WordCamp **attendee** importer
(`src/Import/WordCamp_Attendee_Importer.php`): same `src/Import` namespace, same
`wpcamp-hub` Action Scheduler group, same `wpcamp_hub/…` hook convention, same
`action_scheduler_init` scheduling pattern, and the same positional
`[event_id, …]` job-argument shape. Because schedule, speakers, and attendees do
not depend on one another, the three can later be driven by **one master sync
job per event** that fans out three independent jobs — call
`Import_Scheduler::queue_event_import()` alongside the attendee importer's
per-event queue from a single master.

## Pagination

`WordCamp_Client` (`src/Import/WordCamp_Client.php`) requests up to 100
items per page (`per_page=100`) and reads the WordCamp API's `X-WP-TotalPages`
and `X-WP-Total` response headers. Each fetch returns a `WordCamp_Page` value
object exposing `page`, `total_pages`, `total_items`, `has_more()`, and
`next_page()`, which the scheduler and CLI use to walk all pages.

## What gets imported

### Sessions → `wpcamp_session`

Only timetable items with `_wcpt_session_type === 'session'` are imported; breaks,
lunches, and other custom items are skipped.

| Source (WordCamp REST) | Hub destination |
| --- | --- |
| `title.rendered` | post title |
| `content.rendered` | post content (`wp_kses_post`) |
| `excerpt.rendered` | post excerpt |
| `link` | `wpcamp_official_url` meta |
| `meta._wcpt_session_time` | `wpcamp_start_time` (ISO 8601) |
| `meta._wcpt_session_time` + `_wcpt_session_duration` | `wpcamp_end_time` (ISO 8601) |
| `id` | `wpcamp_source_id` meta (`<host>:<id>`) |
| embedded `wcb_track` term | `wpcamp_track` taxonomy term (created if missing) |
| `session_speakers[].id` | related attendee profiles (`session → user`) |

Each session is related to its event via `Relationships` (`session → event`) and
also stores `wpcamp_event`. `wpcamp_source` is set to `wordcamp`.

### Speakers → attendee `User_Profile`

| Source (WordCamp REST) | Hub destination |
| --- | --- |
| `title.rendered` | display name |
| `slug` | attendee login (`wc-<host>-<slug>`) |
| `id` | `wpcamp_wordcamp_speaker` user meta (`<host>:<id>`) |
| `link` | `wpcamp_wporg_profile_url` |
| `meta._wcpt_user_name` | `wpcamp_wporg_username` |
| largest `avatar_urls` | `wpcamp_avatar` |

A session that references a speaker not yet imported fetches and creates that
speaker on demand, so session/speaker page ordering does not matter.

**Identity & deduplication.** Speakers upsert against a shared identity so a
person imported as both a speaker and an attendee resolves to **one** user.
Matching tries, in order: this camp's speaker source ID
(`wpcamp_wordcamp_speaker` = `<host>:<id>`), then the WordPress.org username
(`wpcamp_wporg_username` meta, then the matching `user_login`). New speaker
profiles are created with the WordPress.org username as their `user_login` —
identical to the attendee importer's identifier — falling back to the speaker
slug and finally the source ID. Sessions dedupe by `wpcamp_source_id`.

## Running on demand (WP-CLI)

The daily run is automated, but a synchronous command exists for verification and
backfills:

```bash
# Import every flagged WordCamp, walking all pages inline.
wp wpcamp-hub import-wordcamps

# Limit to a single event.
wp wpcamp-hub import-wordcamps --event=42
```

## Action Scheduler dependency

Action Scheduler is a Composer dependency (`woocommerce/action-scheduler`). It is
**excluded from Strauss prefixing** (`exclude_from_copy`) because it ships its own
version-arbitration loader that must remain in the global namespace so the newest
copy across all active plugins wins. Its procedural bootstrap is required
explicitly in `wpcamp-hub.php` after the Composer autoloader.

## Tests

End-to-end coverage lives in `tests/php/WordCampImportTest.php`. It short-circuits
`pre_http_request` with paginated fixtures and asserts: header-driven pagination,
session/speaker upserts, event assignment, track mapping, speaker linking,
idempotency on re-run, identity convergence with an existing attendee, the
independent per-event fan-out, page self-rescheduling, and recurring-job
installation.

```bash
npm run test:php
```
