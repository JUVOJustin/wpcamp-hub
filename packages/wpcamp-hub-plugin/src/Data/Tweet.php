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
	 * Author display name, falling back to the post title.
	 */
	public function get_author_name(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_author_name', true );
		$name  = is_string( $value ) ? trim( $value ) : '';

		return '' !== $name ? $name : get_the_title( $this->get_id() );
	}

	/**
	 * Author handle without the leading "@".
	 */
	public function get_author_handle(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_author_handle', true );

		return is_string( $value ) ? ltrim( trim( $value ), '@' ) : '';
	}

	/**
	 * Canonical tweet URL.
	 */
	public function get_url(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_tweet_url', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Tweet body text.
	 */
	public function get_text(): string {
		return (string) get_the_content( null, false, $this->wp_entity );
	}

	/**
	 * Tweet timestamp.
	 *
	 * @return \DateTimeImmutable|null Null when not set or unparseable.
	 */
	public function get_timestamp(): ?\DateTimeImmutable {
		$value = get_post_meta( $this->get_id(), 'wpcamp_timestamp', true );

		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		try {
			return new \DateTimeImmutable( $value, wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * First tweet label term assigned to this tweet.
	 */
	public function get_label(): ?Tweet_Label {
		$terms = get_the_terms( $this->get_id(), Data_Structure::TAXONOMY_TWEET_LABEL );

		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}

		return Tweet_Label::from( reset( $terms ) );
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
