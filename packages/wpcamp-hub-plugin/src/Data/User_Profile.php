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
	 */
	public static function create_attendee( string $identifier, string $name ): self {
		$slug    = sanitize_user( $identifier, true );
		$user_id = wp_insert_user(
			array(
				'user_login'   => $slug,
				'user_email'   => $slug . '@localhost.local',
				'display_name' => $name,
				'nickname'     => $name,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( esc_html( $user_id->get_error_message() ) );
		}

		return new self( (int) $user_id );
	}
}
