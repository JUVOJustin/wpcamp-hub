<?php
/**
 * Tweet entity wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents fetched and curated X/Twitter posts.
 */
class Tweet extends Post_Entity {

	/**
	 * The registered post type represented by the wrapper.
	 */
	public static function get_post_type(): string {
		return Data_Structure::POST_TYPE_TWEET;
	}

	/**
	 * Event this tweet points to.
	 */
	public function get_event(): ?Event {
		$event_ids = $this->get_related( 'event' );
		$event_id  = reset( $event_ids );

		return false === $event_id ? null : Event::from( $event_id );
	}

	/**
	 * Attendee profiles related to this tweet.
	 *
	 * @return list<User_Profile>
	 */
	public function get_attendees(): array {
		return array_map( static fn( int $user_id ): User_Profile => User_Profile::from( $user_id ), $this->get_related( 'user' ) );
	}

	/**
	 * Meeting invites extracted from this tweet.
	 *
	 * @return list<Meeting_Invite>
	 */
	public function get_meeting_invites(): array {
		$meeting_ids = Relationships::get_referencing( 'meeting_invite', 'tweet', $this->get_id() );

		return array_map( static fn( int $meeting_id ): Meeting_Invite => Meeting_Invite::from( $meeting_id ), $meeting_ids );
	}
}
