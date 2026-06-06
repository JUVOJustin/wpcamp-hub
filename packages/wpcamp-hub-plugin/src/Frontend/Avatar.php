<?php
/**
 * Global avatar override for platform attendee profiles.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Frontend;

/**
 * Serves the imported `wpcamp_avatar` URL wherever WordPress renders an avatar.
 *
 * Attendee profiles store their public avatar (WordPress.org / Gravatar) as the
 * `wpcamp_avatar` user-meta URL. By filtering {@see 'get_avatar_url'} — the hook
 * every avatar path funnels through (`get_avatar()`, `get_avatar_data()`, the
 * admin user list, comments, the REST API) — that image shows everywhere,
 * front end and admin, instead of only where the meta is read directly.
 *
 * @see https://developer.wordpress.org/reference/hooks/get_avatar_url/
 */
class Avatar {

	/**
	 * User-meta key holding the public avatar URL.
	 */
	public const META_KEY = 'wpcamp_avatar';

	/**
	 * Replace the avatar URL with the stored attendee avatar when present.
	 *
	 * @param string              $url Default avatar URL.
	 * @param mixed               $id_or_email User ID, email, WP_User, WP_Post or WP_Comment.
	 * @param array<string,mixed> $args Avatar args (unused).
	 * @return string Avatar URL.
	 */
	public function filter_url( string $url, mixed $id_or_email, array $args ): string {
		unset( $args );

		$user_id = self::resolve_user_id( $id_or_email );
		if ( 0 === $user_id ) {
			return $url;
		}

		$custom = get_user_meta( $user_id, self::META_KEY, true );

		return is_string( $custom ) && '' !== $custom ? $custom : $url;
	}

	/**
	 * Resolve the various `get_avatar_url` identifiers to a user ID.
	 *
	 * Accepts every shape WordPress passes: a numeric user ID, an email, a
	 * WP_User, a WP_Post (uses its author), or a WP_Comment (uses its user ID,
	 * falling back to the comment author email).
	 *
	 * @param mixed $id_or_email Identifier supplied by the filter.
	 * @return int User ID, or 0 when it cannot be resolved.
	 */
	private static function resolve_user_id( mixed $id_or_email ): int {
		if ( is_numeric( $id_or_email ) ) {
			return (int) $id_or_email;
		}

		if ( $id_or_email instanceof \WP_User ) {
			return (int) $id_or_email->ID;
		}

		if ( $id_or_email instanceof \WP_Post ) {
			return (int) $id_or_email->post_author;
		}

		if ( $id_or_email instanceof \WP_Comment ) {
			if ( ! empty( $id_or_email->user_id ) ) {
				return (int) $id_or_email->user_id;
			}

			return self::user_id_from_email( (string) $id_or_email->comment_author_email );
		}

		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			return self::user_id_from_email( $id_or_email );
		}

		return 0;
	}

	/**
	 * Look up a user ID by email address.
	 *
	 * @param string $email Email address.
	 * @return int User ID, or 0 when no user matches.
	 */
	private static function user_id_from_email( string $email ): int {
		if ( '' === $email ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );

		return $user instanceof \WP_User ? (int) $user->ID : 0;
	}
}
