<?php
/**
 * Speakers subpage for events — /event/<slug>/speakers/.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Frontend;

use WPCAMP_HUB\Data\Data_Structure;

/**
 * Exposes a per-event speakers directory at `/event/<slug>/speakers/`.
 *
 * Implemented as a rewrite endpoint on the event permalink (mirroring
 * {@see Attendees_Page}) so the directory is a genuine subpage of the event and
 * lists the speakers presenting at it (event → sessions → speakers).
 */
class Speakers_Page {

	/**
	 * Rewrite endpoint / query var name.
	 */
	public const ENDPOINT = 'speakers';

	/**
	 * Register the rewrite endpoint. Call on `init`.
	 *
	 * Adds `speakers` to the event permalink (EP_PERMALINK), so
	 * `/event/<slug>/speakers/` sets the `speakers` query var while still
	 * resolving the event as the queried object.
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_PERMALINK );
	}

	/**
	 * Whether the current request is an event's speakers subpage.
	 */
	public static function is_speakers_view(): bool {
		if ( ! is_singular( Data_Structure::POST_TYPE_EVENT ) ) {
			return false;
		}

		global $wp_query;
		return isset( $wp_query->query_vars[ self::ENDPOINT ] );
	}

	/**
	 * URL of the speakers subpage for an event.
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
	 * Point WordPress at the theme's speakers template for the subpage.
	 *
	 * Rendering is a theme concern: the plugin only routes the request. When the
	 * active theme provides `speakers-wpcamp_event.php` it is used; otherwise
	 * WordPress falls back to its normal single-event template.
	 *
	 * @param string $template Template path resolved by WordPress.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( ! self::is_speakers_view() ) {
			return $template;
		}

		$located = locate_template( array( 'speakers-wpcamp_event.php' ) );

		return '' !== $located ? $located : $template;
	}
}
