<?php
/**
 * Sessions subpage for events — /event/<slug>/sessions/.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Frontend;

use WPCAMP_HUB\Data\Data_Structure;

/**
 * Exposes a per-event session list at `/event/<slug>/sessions/`.
 *
 * Implemented as a rewrite endpoint on the event permalink (mirroring
 * {@see Attendees_Page} / {@see Speakers_Page}) so the list is a genuine
 * subpage of the event and shows the sessions programmed for it.
 */
class Sessions_Page {

	/**
	 * Rewrite endpoint / query var name.
	 */
	public const ENDPOINT = 'sessions';

	/**
	 * Register the rewrite endpoint. Call on `init`.
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_PERMALINK );
	}

	/**
	 * Whether the current request is an event's sessions subpage.
	 */
	public static function is_sessions_view(): bool {
		if ( ! is_singular( Data_Structure::POST_TYPE_EVENT ) ) {
			return false;
		}

		global $wp_query;
		return isset( $wp_query->query_vars[ self::ENDPOINT ] );
	}

	/**
	 * URL of the sessions subpage for an event.
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
	 * Point WordPress at the theme's sessions template for the subpage.
	 *
	 * @param string $template Template path resolved by WordPress.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( ! self::is_sessions_view() ) {
			return $template;
		}

		$located = locate_template( array( 'sessions-wpcamp_event.php' ) );

		return '' !== $located ? $located : $template;
	}
}
