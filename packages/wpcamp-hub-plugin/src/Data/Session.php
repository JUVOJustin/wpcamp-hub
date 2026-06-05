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
	 * Track term assigned to this session.
	 */
	public function get_track(): ?Track {
		$terms = get_the_terms( $this->get_id(), Data_Structure::TAXONOMY_TRACK );

		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}

		return Track::from( reset( $terms ) );
	}

	/**
	 * Session start time as a stored ISO 8601 string.
	 */
	public function get_start_time(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_start_time', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Session end time as a stored ISO 8601 string.
	 */
	public function get_end_time(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_end_time', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Source identifier for an imported session (empty for curated sessions).
	 */
	public function get_source_id(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_source_id', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Room/location for this session (free-form meta).
	 */
	public function get_room(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_room', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Display names of the session speakers.
	 *
	 * @return list<string>
	 */
	public function get_speaker_names(): array {
		return array_map(
			static fn( User_Profile $profile ): string => (string) ( $profile->get_wp_entity()->display_name ?? '' ),
			$this->get_attendees()
		);
	}

	/**
	 * Attendee profiles related to this session.
	 *
	 * @return list<User_Profile>
	 */
	public function get_attendees(): array {
		return array_map( static fn( int $user_id ): User_Profile => User_Profile::from( $user_id ), $this->get_related( 'user' ) );
	}
}
