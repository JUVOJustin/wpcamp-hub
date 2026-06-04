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
	 * Attendee profiles related to this event.
	 *
	 * @return User_Profile[]
	 */
	public function get_attendees(): array {
		return array_map( static fn( int $user_id ): User_Profile => User_Profile::from( $user_id ), $this->get_related( 'user' ) );
	}

	/**
	 * Tweets related to this event.
	 *
	 * @return Tweet[]
	 */
	public function get_tweets(): array {
		$tweet_ids = array_unique(
			array_merge(
				$this->get_related( 'tweet' ),
				Relationships::get_referencing( 'tweet', 'event', $this->get_id() )
			)
		);

		return array_map( static fn( int $tweet_id ): Tweet => Tweet::from( $tweet_id ), $tweet_ids );
	}

	/**
	 * Sessions related to this event.
	 *
	 * @return Session[]
	 */
	public function get_sessions(): array {
		$session_ids = array_unique(
			array_merge(
				$this->get_related( 'session' ),
				Relationships::get_referencing( 'session', 'event', $this->get_id() )
			)
		);

		return array_map( static fn( int $session_id ): Session => Session::from( $session_id ), $session_ids );
	}

	/**
	 * Meeting invites related to this event.
	 *
	 * @return Meeting_Invite[]
	 */
	public function get_meeting_invites(): array {
		$meeting_ids = Relationships::get_referencing( 'meeting_invite', 'event', $this->get_id() );

		return array_map( static fn( int $meeting_id ): Meeting_Invite => Meeting_Invite::from( $meeting_id ), $meeting_ids );
	}
}
