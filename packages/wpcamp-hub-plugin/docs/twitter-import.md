# X/Twitter import

Fetches X/Twitter posts and stores them as `wpcamp_tweet` posts. This is the
**fetch + import** layer only — AI categorisation hooks in via the filters
below (separate work).

## Architecture

`src/Integrations/Twitter/`

| Class | Role |
| --- | --- |
| `Tweet_Client_Interface` | Contract returning normalised tweet arrays. |
| `X_Scraper_Client` | Adapter around the prefixed `XTwitterScraper\Client` SDK. |
| `Tweet_Importer` | Fetch → dedupe (by tweet id) → filter → insert into the CPT → batch action. |
| `Twitter_Service` | Resolves the API key, builds the importer, runs imports. |
| `Tweet_Import_Command` | WP-CLI command. |
| `Tweet_Admin` | Settings + manual run page under the Tweets CPT. |

The scraper SDK is pulled via Composer (`xquik/x-twitter-scraper`) and prefixed
by Strauss to `WPCAMP_HUB\Dependencies\XTwitterScraper`.

## Field mapping

| Tweet field | CPT storage | Exposed |
| --- | --- | --- |
| Tweet ID | meta `wpcamp_tweet_id` | no |
| Author handle | meta `wpcamp_author_handle` | no |
| Author name | meta `wpcamp_author_name` | no |
| Tweet text | `post_content` (+ trimmed `post_title`) | yes |
| Tweet URL | meta `wpcamp_tweet_url` | no |
| Timestamp | meta `wpcamp_timestamp` | no |
| Related event | relationship (`event`) | — |
| Related attendee | relationship (`user`) | — |
| Processing status | meta `wpcamp_processing_status` (default `pending`) | — |

## Triggers

- **WP-CLI:** `wp wpcamp import-tweets --query="#WCEU" --limit=50`
- **Admin:** *Tweets → Import tweets* (set the API key, run a query).
- **Code:** `( new Twitter_Service() )->import( '#WCEU', 50 )`.

## API key

Resolved in order: filter `wpcamp_hub/twitter_api_key` → option
`wpcamp_hub_twitter_api_key` → constant `WPCAMP_HUB_X_API_KEY` → env
`X_TWITTER_SCRAPER_API_KEY`.

## Extension points (for AI categorisation)

```php
// Per tweet, BEFORE insert. Enrich the post array or return [] / false to skip.
add_filter( 'wpcamp_hub/tweet_import_data', function ( array $data, array $tweet ) {
    // $tweet: id, text, handle, name, url, timestamp
    // e.g. set status, relate to an event/attendee, assign wpcamp_tweet_label terms
    $data['meta_input']['wpcamp_processing_status'] = 'attendance';
    return $data;
}, 10, 2 );

// Once, AFTER an import run — batch processing.
add_action( 'wpcamp_hub/tweets_imported', function ( array $post_ids, string $query ) {
    // queue AI categorisation for $post_ids
}, 10, 2 );

// Override the client (tests / alternative provider).
add_filter( 'wpcamp_hub/twitter_client', fn() => new My_Client(), 10 );
```

The intended extractions for the AI layer: people joining WordCamp, open 1:1
meeting invites, events, and whether people are joining events.
