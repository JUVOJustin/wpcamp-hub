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

Set the WordCamp flag on a `wpcamp_event` and provide the event's official URL:

| Meta key | Type | Purpose |
| --- | --- | --- |
| `wpcamp_is_major_wordcamp` | boolean | Marks the event as a native-stack WordCamp eligible for import. |
| `wpcamp_official_url` | URL | Official event URL. The importer derives the WordCamp REST root from this URL and normalizes it to `…/wp-json/wp/v2/`. |

Example official URL: `https://europe.wordcamp.org/2026/`

`Event::major_wordcamps()` returns every flagged event that also has an official URL.

## How the schedule works

`Import_Scheduler` (`src/Import/Import_Scheduler.php`) uses one recurring Action
Scheduler cron job in the shared `wpcamp-hub` group and fans out per-event async
jobs that invoke REST-visible, admin-only WordPress abilities:

1. **`wpcamp_hub/import_wordcamp_schedule`** — a single recurring **daily**
   master job, installed on `action_scheduler_init` (`schedule_daily_import()`
   is idempotent and uses Action Scheduler's `$unique` flag). On each run it
   scans `Event::major_wordcamps()` and queues per-event speaker and attendee
   jobs.
2. **`wpcamp_hub/import_wordcamp_event_speakers`** (`event_id`) — executes the
   `wpcamp-hub/event-import-speakers` ability. After that ability succeeds, it
   queues the session job for the same event.
3. **`wpcamp_hub/import_wordcamp_event_sessions`** (`event_id`) — executes the
   `wpcamp-hub/event-import-sessions` ability. Sessions relate to speaker
   profiles that were imported by the preceding speaker job.
4. **`wpcamp_hub/import_wordcamp_event_attendees`** (`event_id`) — executes the
   `wpcamp-hub/event-import-attendees` ability when the event has a configured
   attendees URL.

The abilities are visible through the WordPress Abilities REST API and require an
administrator (`manage_options`) to execute. Their input schema requires a
positive integer `event_id` and rejects additional properties. The recurring job
and pending per-event jobs are removed on plugin deactivation. Per-event Action
Scheduler jobs store `event_id` as a named argument.

## Pagination

`WordCamp_Client` (`src/Import/WordCamp_Client.php`) requests up to 100
items per page (`per_page=100`) and reads the WordCamp API's `X-WP-TotalPages`
and `X-WP-Total` response headers. Each fetch returns a `WordCamp_Page` value
object exposing `page`, `total_pages`, `total_items`, `has_more()`, and
`next_page()`, which the import abilities use to walk all pages.

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
| embedded `wcb_track` / `session_track` term | `wpcamp_track` taxonomy term (created if missing), such as Track 1, Track 2, Workshop 1, or Workshop 2 |
| `session_speakers[].id`, `meta._wcpt_speaker_id`, `_links.speakers`, or `_embedded.speakers[].id` | related attendee profiles (`session → user`) |

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

Speakers import before sessions. Sessions only relate to speaker profiles that
already have the matching `wpcamp_wordcamp_speaker` source ID, so relationships
come from the session payload rather than inferred speaker-side backfills.

**Identity & deduplication.** Speakers upsert against a shared identity so a
person imported as both a speaker and an attendee resolves to **one** user.
Matching tries, in order: this camp's speaker source ID
(`wpcamp_wordcamp_speaker` = `<host>:<id>`), then the WordPress.org username
(`wpcamp_wporg_username` meta, then the matching `user_login`). New speaker
profiles are created with the WordPress.org username as their `user_login` —
identical to the attendee importer's identifier — falling back to the speaker
slug and finally the source ID. Sessions dedupe by `wpcamp_source_id`.

## Action Scheduler dependency

Action Scheduler is a Composer dependency (`woocommerce/action-scheduler`). It is
**excluded from Strauss prefixing** (`exclude_from_copy`) because it ships its own
version-arbitration loader that must remain in the global namespace so the newest
copy across all active plugins wins. Its procedural bootstrap is required
explicitly in `wpcamp-hub.php` after the Composer autoloader.

## Tests

End-to-end coverage lives in `tests/php/WordCampImportTest.php`. The default suite
short-circuits `pre_http_request` with paginated fixtures and asserts:
header-driven pagination, session/speaker upserts, event assignment, track
mapping, speaker linking, idempotency on re-run, identity convergence with an
existing attendee, per-event fan-out, session queueing after a speaker import, and
recurring-job installation.

The same test file also includes an `external-http` test that imports from the
real WordCamp Europe 2026 REST API and verifies sessions, speaker profiles,
speaker links, and Track/Workshop terms. Run it explicitly because it depends on
the public network and live WordCamp data.

```bash
npm run test:php
npm run test:php:external
```
