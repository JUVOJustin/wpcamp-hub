---
title: WordCamp Attendee Importer
description: How the plugin imports attendee profiles from WordCamp attendee pages using URL-derived identity signals.
visibility: internal
---

## Purpose

The attendee importer creates local attendee users for events that have a `wpcamp_attendees_url` value.
The importer intentionally treats the attendee page as an identity URL source only.
Page markup, text, CSS classes, and element proximity must not decide whether two identities belong to the same person.

## Flow

1. Validate each configured attendees page URL with WordPress safe HTTP URL rules.
2. Fetch each configured attendees page.
3. Discover structural parsing rules for attendee rows with a deterministic Camptix fallback or the WordPress AI Client.
4. Scrape supported URLs from the full HTML document.
5. Extract identity signals from those URLs.
6. Preserve row-level context when the page exposes one attendee per repeated wrapper.
7. Merge identities that appear in the same parsed attendee row.
8. Upsert attendee users and relate them to the event.

## Scheduling

Attendees share the WordCamp import scheduler. The daily dispatcher job queues a
per-event attendee job when an event has a valid `wpcamp_attendees_url` value:

```text
wpcamp_hub/import_wordcamp_schedule
```

The per-event job executes the REST-visible, admin-only attendee import ability:

```text
wpcamp_hub/import_wordcamp_event_attendees
wpcamp-hub/event-import-attendees
```

Each event crawl action receives `event_id` as a named Action Scheduler argument.
This keeps the daily schedule stable while allowing individual event pages to
crawl independently in the Action Scheduler queue.

The importer only queues and fetches URLs accepted by WordPress' safe HTTP URL validation. This supports public
attendee pages on any host while rejecting unsafe URL shapes before the scheduled server-side request runs.

## URL Extraction

The scraper accepts only these attendee-page URL shapes:

- WordPress.org profile links from `<a href>`:
  - `https://profiles.wordpress.org/{username}/`
  - `https://profiles.wordpress.org/{username}/profile`
  - `https://wordpress.org/support/users/{username}/`
- Gravatar avatar URLs from `<img src>`:
  - `https://secure.gravatar.com/avatar/{hash}`
  - `https://www.gravatar.com/avatar/{hash}`
  - `https://gravatar.com/avatar/{hash}`

The extraction pass scans all matching anchors and images in the document. It does not require attendee names,
printed text, sibling order, or event-specific layout details to create attendee identities.

When the page exposes one attendee per repeated wrapper, the importer records a row-level identity group.
That group can include the row's Gravatar URL, WordPress.org URL, Twitter/X profile URL, and website URL.
This improves imports for Camptix attendee lists such as WordCamp Germany 2023 and WordCamp Asia 2026
while keeping URL extraction as the only source of imported attendee data.

## AI-Assisted Structural Profiles

The importer first recognizes common Camptix attendee lists by the `tix-attendee-list` marker and uses each `<li>` as
one attendee row. For other page structures, it uses the WordPress AI Client to identify the repeated wrapper for one
attendee. The AI request is used only for schema discovery. It receives the first 20,000 characters of the fetched
public attendees page HTML and returns a parsing profile such as:

```json
{
  "item_tag": "div",
  "item_id": "",
  "item_class": "attendee-card",
  "confidence": 0.95
}
```

The source attendees URL is not included in the prompt. The AI response is not used as attendee data and does not
decide identity relationships. PHP validates the profile, then the normal HTML processor extracts only accepted URLs
from inside each repeated wrapper. Recognized Camptix lists can be processed without an AI request; other page
structures require a valid AI parsing profile.

The prompt uses `as_json_response( $schema )` so the AI Client returns a structured JSON parsing profile.

Parsing profiles can also be supplied in tests or site-specific code through:

```php
add_filter(
    'wpcamp_hub_attendee_importer_ai_parsing_profile',
    static function (): array {
        return array(
            'item_tag'   => 'div',
            'item_class' => 'attendee-card',
            'confidence' => 0.95,
        );
    }
);
```

## Local AI Provider Setup

The local `wp-env` configuration installs and activates the WordPress.org
`ai-provider-for-openai` connector automatically through the `plugins` list.

Keep the API key out of git by creating `packages/wpcamp-hub-plugin/.wp-env.override.json` locally:

```json
{
  "config": {
    "OPENAI_API_KEY": "sk-..."
  }
}
```

Restart `wp-env` after adding or changing the key so the generated `wp-config.php` contains the constant.

In CI, the application-test job writes the same ignored override file before `wp-env` starts when a repository secret
named `OPENAI_API_KEY` is available. Pull requests without that secret still run deterministic unit fixtures by
injecting parsing profiles through the test filter and by exercising Camptix parsing that does not need an AI request.

## Grouping And Decoupling

The importer does not fetch public WordPress.org or Gravatar profile payloads during import to decide whether two
URLs belong to the same person. The AI-derived parsing rules identify the repeated attendee wrapper, and PHP groups
only the accepted URLs inside that wrapper. If a WordPress.org URL and a Gravatar URL are in the same parsed attendee
row, they become one attendee. If they are in different parsed rows, they remain separate attendees.

## Upsert Rules

After enrichment and decoupling, the importer upserts local WordPress users:

- Prefer the WordPress.org username as the stable local identifier.
- Use `gravatar-{first 20 hash characters}` only when no WordPress.org username is known.
- Prefer the WordPress.org profile name for display name.
- Fall back to the Gravatar display name.
- Fall back to the stable identifier when no profile name is available.
- Store row-level website URLs as the WordPress user URL.
- Store row-level Twitter/X URLs in `wpcamp_social_links`.

## Camptix Examples

WordCamp Germany 2023 and WordCamp Asia 2026 expose attendees through Camptix list markup:

```html
<li>
  <img src="https://secure.gravatar.com/avatar/{hash}?s=96&amp;d=mm&amp;r=g" />
  <a class="tix-attendee-twitter" href="http://twitter.com/example">@example</a>
  <a class="tix-attendee-url tix-website" href="https://example.com">example.com</a>
</li>
```

For this shape, the importer treats the `<li>` as one attendee row. The Gravatar URL remains the identity signal,
the Twitter/X URL becomes a social link, and the website URL becomes the WordPress user URL.

The importer stores the raw public profile payloads in user meta so future migrations can reprocess imported identities
without scraping the attendee page again.
