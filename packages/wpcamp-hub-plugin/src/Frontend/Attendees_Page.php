<?php
/**
 * Attendees subpage for events — /event/<slug>/attendees/.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Frontend;

use WPCAMP_HUB\Data\Data_Structure;

/**
 * Exposes a per-event attendees directory at `/event/<slug>/attendees/`.
 *
 * Implemented as a rewrite endpoint on the event permalink so the directory is
 * a genuine subpage of the event (it inherits the event in the main query) and
 * lists the people attached to that event via the platform relationship map.
 */
class Attendees_Page {

	/**
	 * Rewrite endpoint / query var name.
	 */
	public const ENDPOINT = 'attendees';

	/**
	 * Register the rewrite endpoint. Call on `init`.
	 *
	 * Adds `attendees` to the event permalink (EP_PERMALINK), so
	 * `/event/<slug>/attendees/` sets the `attendees` query var while still
	 * resolving the event as the queried object.
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_PERMALINK );
	}

	/**
	 * Whether the current request is an event's attendees subpage.
	 */
	public static function is_attendees_view(): bool {
		if ( ! is_singular( Data_Structure::POST_TYPE_EVENT ) ) {
			return false;
		}

		// The endpoint query var is set (to '' for a bare endpoint hit) whenever
		// the attendees segment is present.
		global $wp_query;
		return isset( $wp_query->query_vars[ self::ENDPOINT ] );
	}

	/**
	 * URL of the attendees subpage for an event.
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
	 * Point WordPress at the theme's attendees template for the subpage.
	 *
	 * Rendering is a theme concern: the plugin only routes the request. When the
	 * active theme provides `attendees-wpcamp_event.php` it is used; otherwise
	 * WordPress falls back to its normal single-event template.
	 *
	 * @param string $template Template path resolved by WordPress.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( ! self::is_attendees_view() ) {
			return $template;
		}

		$located = locate_template( array( 'attendees-wpcamp_event.php' ) );

		return '' !== $located ? $located : $template;
	}
}
