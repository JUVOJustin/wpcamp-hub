<?php
/**
 * Meeting invite entity wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents open one-to-one meeting opportunities.
 */
class Meeting_Invite extends Post_Entity {

	/**
	 * The registered post type represented by the wrapper.
	 */
	public static function get_post_type(): string {
		return Data_Structure::POST_TYPE_MEETING_INVITE;
	}

	/**
	 * Attendee profile this meeting invite is for.
	 */
	public function get_person(): ?User_Profile {
		$user_ids = $this->get_related( 'user' );
		$user_id  = reset( $user_ids );

		return false === $user_id ? null : User_Profile::from( $user_id );
	}

	/**
	 * Tweet this meeting invite was extracted from.
	 */
	public function get_source_tweet(): ?Tweet {
		$tweet_ids = $this->get_related( 'tweet' );
		$tweet_id  = reset( $tweet_ids );

		return false === $tweet_id ? null : Tweet::from( $tweet_id );
	}

	/**
	 * Event related to this meeting invite.
	 */
	public function get_event(): ?Event {
		$event_ids = $this->get_related( 'event' );
		$event_id  = reset( $event_ids );

		return false === $event_id ? null : Event::from( $event_id );
	}
}
