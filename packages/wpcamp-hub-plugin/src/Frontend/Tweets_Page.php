<?php
/**
 * Tweets subpage for events — /event/<slug>/tweets/.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Frontend;

use WPCAMP_HUB\Data\Data_Structure;

/**
 * Exposes a per-event tweet feed at `/event/<slug>/tweets/`.
 *
 * Implemented as a rewrite endpoint on the event permalink (mirroring
 * {@see Attendees_Page} / {@see Speakers_Page}) so the feed is a genuine
 * subpage of the event and lists the tweets related to it.
 */
class Tweets_Page {

	/**
	 * Rewrite endpoint / query var name.
	 */
	public const ENDPOINT = 'tweets';

	/**
	 * Register the rewrite endpoint. Call on `init`.
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_PERMALINK );
	}

	/**
	 * Whether the current request is an event's tweets subpage.
	 */
	public static function is_tweets_view(): bool {
		if ( ! is_singular( Data_Structure::POST_TYPE_EVENT ) ) {
			return false;
		}

		global $wp_query;
		return isset( $wp_query->query_vars[ self::ENDPOINT ] );
	}

	/**
	 * URL of the tweets subpage for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	public static function url_for( int $event_id ): string {
		$permalink = get_permalink( $event_id );
		if ( false === $permalink ) {
			return '';
		}

		return trailingslashit( trailingslashit( $permalink ) . self::ENDPOINT );
	}

	/**
	 * Point WordPress at the theme's tweets template for the subpage.
	 *
	 * @param string $template Template path resolved by WordPress.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( ! self::is_tweets_view() ) {
			return $template;
		}

		$located = locate_template( array( 'tweets-wpcamp_event.php' ) );

		return '' !== $located ? $located : $template;
	}
}
