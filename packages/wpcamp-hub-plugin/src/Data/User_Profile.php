<?php
/**
 * Attendee profile wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents a first-class attendee profile backed by WP_User.
 */
class User_Profile extends User_Entity {

	/**
	 * Create an attendee profile as a subscriber with a local placeholder email.
	 *
	 * @param string $identifier Stable local identifier.
	 * @param string $name Display name.
	 * @return self
	 * @throws \RuntimeException When WordPress cannot create the attendee.
	 */
	public static function create_attendee( string $identifier, string $name ): self {
		$slug = sanitize_user( $identifier, true );
		// Suppress WordPress' new-user notification emails — these are imported
		// profiles, not real sign-ups. A random password is supplied so WordPress
		// does not emit a "user_pass is required" notice; the profiles never
		// authenticate directly.
		add_filter( 'wp_send_new_user_notification_to_admin', '__return_false' );
		add_filter( 'wp_send_new_user_notification_to_user', '__return_false' );

		try {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $slug,
					'user_email'   => $slug . '@localhost.local',
					'user_pass'    => wp_generate_password( 24, true, true ),
					'display_name' => $name,
					'nickname'     => $name,
					'role'         => 'subscriber',
				)
			);
		} finally {
			remove_filter( 'wp_send_new_user_notification_to_admin', '__return_false' );
			remove_filter( 'wp_send_new_user_notification_to_user', '__return_false' );
		}

		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( esc_html( $user_id->get_error_message() ) );
		}

		return new self( (int) $user_id );
	}

	/**
	 * Public display name.
	 */
	public function get_display_name(): string {
		return (string) ( $this->get_wp_entity()->display_name ?? '' );
	}

	/**
	 * Company or organization.
	 */
	public function get_company(): string {
		$value = get_user_meta( $this->get_id(), 'wpcamp_company', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Community role (e.g. Developer, Organiser).
	 */
	public function get_role(): string {
		$value = get_user_meta( $this->get_id(), 'wpcamp_community_role', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Public biography.
	 */
	public function get_bio(): string {
		$value = get_user_meta( $this->get_id(), 'wpcamp_bio', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Public avatar URL, or '' when none is stored.
	 *
	 * Prefers the imported `wpcamp_avatar` URL (WordPress.org / Gravatar);
	 * callers should fall back to get_avatar()/initials when this is empty.
	 */
	public function get_avatar_url(): string {
		$value = get_user_meta( $this->get_id(), 'wpcamp_avatar', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Canonical public profile URL (WordPress.org profile), or ''.
	 */
	public function get_profile_url(): string {
		$value = get_user_meta( $this->get_id(), 'wpcamp_wporg_profile_url', true );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Personal website URL (the WordPress `user_url` field), or ''.
	 */
	public function get_website(): string {
		return (string) ( $this->get_wp_entity()->user_url ?? '' );
	}

	/**
	 * Events related to this attendee.
	 *
	 * @return list<Event>
	 */
	public function get_events(): array {
		return array_map( static fn( int $event_id ): Event => Event::from( $event_id ), $this->get_related( 'event' ) );
	}

	/**
	 * Tweets related to this attendee.
	 *
	 * @return list<Tweet>
	 */
	public function get_tweets(): array {
		return array_map( static fn( int $tweet_id ): Tweet => Tweet::from( $tweet_id ), $this->get_related( 'tweet' ) );
	}

	/**
	 * Sessions related to this attendee.
	 *
	 * @return list<Session>
	 */
	public function get_sessions(): array {
		return array_map( static fn( int $session_id ): Session => Session::from( $session_id ), $this->get_related( 'session' ) );
	}

	/**
	 * Meeting invites related to this attendee.
	 *
	 * @return list<Meeting_Invite>
	 */
	public function get_meeting_invites(): array {
		$meeting_ids = Relationships::get_referencing( 'meeting_invite', 'user', $this->get_id() );

		return array_map( static fn( int $meeting_id ): Meeting_Invite => Meeting_Invite::from( $meeting_id ), $meeting_ids );
	}
}
