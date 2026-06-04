<?php
/**
 * Imports fetched X/Twitter posts into the tweet CPT.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Integrations\Twitter;

use WPCAMP_HUB\Data\Data_Structure;

/**
 * Fetches tweets and stores them as wpcamp_tweet posts.
 *
 * Downstream AI categorisation hooks in via two extension points:
 *  - filter `wpcamp_hub/tweet_import_data` — per tweet, before insert. Lets a
 *    callback enrich the post array (status, related event/attendee, terms) or
 *    return an empty array / false to skip the tweet.
 *  - action `wpcamp_hub/tweets_imported` — once, after the run, with the list
 *    of inserted post IDs for batch processing.
 */
class Tweet_Importer {

	public const FILTER_IMPORT_DATA = 'wpcamp_hub/tweet_import_data';
	public const ACTION_IMPORTED    = 'wpcamp_hub/tweets_imported';

	public const STATUS_PENDING = 'pending';

	/**
	 * Tweet source client.
	 *
	 * @var Tweet_Client_Interface
	 */
	private Tweet_Client_Interface $client;

	/**
	 * Construct the importer.
	 *
	 * @param Tweet_Client_Interface $client Tweet source.
	 */
	public function __construct( Tweet_Client_Interface $client ) {
		$this->client = $client;
	}

	/**
	 * Fetch tweets for a query and import new ones.
	 *
	 * @param string $query Search query (e.g. "#WCEU").
	 * @param int    $limit Maximum number of tweets to fetch.
	 * @return array{imported:int[],skipped:int,total:int} Result summary.
	 */
	public function import( string $query, int $limit = 20 ): array {
		$tweets   = $this->client->search( $query, $limit );
		$imported = array();
		$skipped  = 0;

		foreach ( $tweets as $tweet ) {
			$post_id = $this->import_tweet( $tweet );

			if ( null === $post_id ) {
				++$skipped;
				continue;
			}

			$imported[] = $post_id;
		}

		/**
		 * Fires once after an import run, for batch (e.g. AI) processing.
		 *
		 * @param int[]  $imported Inserted tweet post IDs.
		 * @param string $query    The query that produced them.
		 */
		do_action( self::ACTION_IMPORTED, $imported, $query );

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'total'    => count( $tweets ),
		);
	}

	/**
	 * Import a single normalised tweet.
	 *
	 * @param array{id:string,text:string,handle:string,name:string,url:string,timestamp:string} $tweet Normalised tweet.
	 * @return int|null Inserted post ID, or null when skipped/duplicate/failed.
	 */
	private function import_tweet( array $tweet ): ?int {
		if ( '' === $tweet['id'] || $this->tweet_exists( $tweet['id'] ) ) {
			return null;
		}

		$title = $this->build_title( $tweet['text'], $tweet['handle'] );

		$data = array(
			'post_type'    => Data_Structure::POST_TYPE_TWEET,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $tweet['text'],
			'meta_input'   => array(
				'wpcamp_tweet_id'          => $tweet['id'],
				'wpcamp_author_handle'     => $tweet['handle'],
				'wpcamp_author_name'       => $tweet['name'],
				'wpcamp_tweet_url'         => $tweet['url'],
				'wpcamp_timestamp'         => $tweet['timestamp'],
				'wpcamp_processing_status' => self::STATUS_PENDING,
			),
		);

		/**
		 * Filters the post data for a tweet before it is inserted.
		 *
		 * Return an empty array or false to skip the tweet. Callbacks (e.g. AI
		 * categorisation) may set processing status, relate the tweet to an
		 * event/attendee, or assign terms.
		 *
		 * @param array<string,mixed>                                                                $data  wp_insert_post() args.
		 * @param array{id:string,text:string,handle:string,name:string,url:string,timestamp:string} $tweet Normalised tweet.
		 */
		// Filters may return anything (incl. false/array to skip); validated below.
		$data = apply_filters( self::FILTER_IMPORT_DATA, $data, $tweet );

		// @phpstan-ignore-next-line booleanNot.alwaysFalse (a filter may return a non-array).
		if ( ! is_array( $data ) || array() === $data ) {
			return null;
		}

		$post_id = wp_insert_post( $data, true );

		if ( is_wp_error( $post_id ) ) {
			return null;
		}

		return (int) $post_id;
	}

	/**
	 * Whether a tweet with the given provider ID already exists.
	 *
	 * @param string $tweet_id Provider tweet ID.
	 * @return bool
	 */
	private function tweet_exists( string $tweet_id ): bool {
		$existing = get_posts(
			array(
				'post_type'              => Data_Structure::POST_TYPE_TWEET,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => 'wpcamp_tweet_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => $tweet_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return array() !== $existing;
	}

	/**
	 * Build a readable post title from the tweet text.
	 *
	 * @param string $text   Tweet text.
	 * @param string $handle Author handle.
	 * @return string
	 */
	private function build_title( string $text, string $handle ): string {
		$text = trim( wp_strip_all_tags( $text ) );

		if ( '' === $text ) {
			return '' !== $handle ? sprintf( '@%s', $handle ) : __( 'Tweet', 'wpcamp-hub' );
		}

		return wp_trim_words( $text, 12, '…' );
	}
}
