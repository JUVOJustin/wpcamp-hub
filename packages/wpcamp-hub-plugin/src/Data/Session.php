<?php
/**
 * Session entity wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents timetable sessions inside events.
 */
class Session extends Post_Entity {

	/**
	 * The registered post type represented by the wrapper.
	 */
	public static function get_post_type(): string {
		return Data_Structure::POST_TYPE_SESSION;
	}

	/**
	 * Event this session belongs to.
	 */
	public function get_event(): ?Event {
		$event_ids = $this->get_related( 'event' );
		$event_id  = reset( $event_ids );

		return false === $event_id ? null : Event::from( $event_id );
	}

	/**
	 * Attendee profiles related to this session.
	 *
	 * @return User_Profile[]
	 */
	public function get_attendees(): array {
		return array_map( static fn( int $user_id ): User_Profile => User_Profile::from( $user_id ), $this->get_related( 'user' ) );
	}
}
