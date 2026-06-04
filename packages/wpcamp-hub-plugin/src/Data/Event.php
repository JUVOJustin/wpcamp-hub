<?php
/**
 * Event entity wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents curated events and main WordCamp anchors.
 */
class Event extends Post_Entity {

	/**
	 * The registered post type represented by the wrapper.
	 */
	public static function get_post_type(): string {
		return Data_Structure::POST_TYPE_EVENT;
	}

	/**
	 * Event start date/time (stored ISO 8601 string).
	 */
	public function get_start(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_date_start', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Event end date/time (stored ISO 8601 string).
	 */
	public function get_end(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_date_end', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Human-readable location.
	 */
	public function get_location(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_location', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Official event URL.
	 */
	public function get_official_url(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_official_url', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Source key (e.g. official / community / x).
	 */
	public function get_source(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_source', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Event type term assigned to this event.
	 */
	public function get_type(): ?Event_Type {
		$terms = get_the_terms( $this->get_id(), Data_Structure::TAXONOMY_EVENT_TYPE );

		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}

		return Event_Type::from( reset( $terms ) );
	}

	/**
	 * Attendee profiles related to this event.
	 *
	 * @return list<User_Profile>
	 */
	public function get_attendees(): array {
		return array_map( static fn( int $user_id ): User_Profile => User_Profile::from( $user_id ), $this->get_related( 'user' ) );
	}

	/**
	 * Tweets related to this event.
	 *
	 * @return list<Tweet>
	 */
	public function get_tweets(): array {
		$tweet_ids = array_values(
			array_unique(
				array_merge(
					$this->get_related( 'tweet' ),
					Relationships::get_referencing( 'tweet', 'event', $this->get_id() )
				)
			)
		);

		return array_map( static fn( int $tweet_id ): Tweet => Tweet::from( $tweet_id ), $tweet_ids );
	}

	/**
	 * Sessions related to this event.
	 *
	 * @return list<Session>
	 */
	public function get_sessions(): array {
		$session_ids = array_values(
			array_unique(
				array_merge(
					$this->get_related( 'session' ),
					Relationships::get_referencing( 'session', 'event', $this->get_id() )
				)
			)
		);

		return array_map( static fn( int $session_id ): Session => Session::from( $session_id ), $session_ids );
	}

	/**
	 * Meeting invites related to this event.
	 *
	 * @return list<Meeting_Invite>
	 */
	public function get_meeting_invites(): array {
		$meeting_ids = Relationships::get_referencing( 'meeting_invite', 'event', $this->get_id() );

		return array_map( static fn( int $meeting_id ): Meeting_Invite => Meeting_Invite::from( $meeting_id ), $meeting_ids );
	}
}
