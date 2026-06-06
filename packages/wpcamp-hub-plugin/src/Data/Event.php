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
	 * Whether this event is a major WordCamp running the native WordCamp tech
	 * stack, and is therefore eligible for automated session/speaker import.
	 */
	public function is_major_wordcamp(): bool {
		return (bool) get_post_meta( $this->get_id(), 'wpcamp_is_major_wordcamp', true );
	}

	/**
	 * Base WordCamp REST API URL configured for this event.
	 */
	public function get_wordcamp_api_url(): string {
		$value = get_post_meta( $this->get_id(), 'wpcamp_wordcamp_api_url', true );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * All events flagged as major WordCamps with a configured API URL.
	 *
	 * @return list<self>
	 */
	public static function major_wordcamps(): array {
		$post_ids = get_posts(
			array(
				'post_type'      => Data_Structure::POST_TYPE_EVENT,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => 'wpcamp_is_major_wordcamp',
						'value' => '1',
					),
				),
			)
		);

		$events = array();
		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			$event = self::from( $post_id );
			if ( '' !== $event->get_wordcamp_api_url() ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Event start date/time.
	 *
	 * @return \DateTimeImmutable|null Null when not set or unparseable.
	 */
	public function get_start(): ?\DateTimeImmutable {
		return self::parse_datetime( get_post_meta( $this->get_id(), 'wpcamp_date_start', true ) );
	}

	/**
	 * Event end date/time.
	 *
	 * @return \DateTimeImmutable|null Null when not set or unparseable.
	 */
	public function get_end(): ?\DateTimeImmutable {
		return self::parse_datetime( get_post_meta( $this->get_id(), 'wpcamp_date_end', true ) );
	}

	/**
	 * Parse a stored date/time meta value into an immutable datetime.
	 *
	 * Uses the site timezone so formatting matches WordPress.
	 *
	 * @param mixed $value Stored meta value (ISO 8601 string expected).
	 * @return \DateTimeImmutable|null
	 */
	private static function parse_datetime( mixed $value ): ?\DateTimeImmutable {
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
	 * Geo coordinates for the event.
	 *
	 * @return array{latitude:float,longitude:float}|null Null when not set.
	 */
	public function get_coordinates(): ?array {
		$value = get_post_meta( $this->get_id(), 'wpcamp_coordinates', true );

		if ( ! is_array( $value ) || ! isset( $value['latitude'], $value['longitude'] ) ) {
			return null;
		}

		return array(
			'latitude'  => (float) $value['latitude'],
			'longitude' => (float) $value['longitude'],
		);
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
