<?php
/**
 * Base wrapper for platform user profiles.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Adds platform APIs around a native WP_User instance.
 */
abstract class User_Entity {

	/**
	 * Native WordPress user object.
	 *
	 * @var \WP_User
	 */
	protected \WP_User $wp_entity;

	/**
	 * Wrap a user ID or native user object.
	 *
	 * @param int|\WP_User $user User ID or object.
	 * @throws \InvalidArgumentException When the user cannot be wrapped by this entity.
	 */
	final public function __construct( int|\WP_User $user ) {
		$wp_user = $user instanceof \WP_User ? $user : get_user_by( 'id', $user );

		if ( ! $wp_user instanceof \WP_User ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid user entity.' ) );
		}

		$this->wp_entity = $wp_user;
	}

	/**
	 * Build an entity wrapper from an ID or WP_User.
	 *
	 * @param int|\WP_User $user User ID or object.
	 * @return static
	 */
	public static function from( int|\WP_User $user ): static {
		return new static( $user );
	}

	/**
	 * Native WordPress entity.
	 */
	public function get_wp_entity(): \WP_User {
		return $this->wp_entity;
	}

	/**
	 * Native user ID.
	 */
	public function get_id(): int {
		return (int) $this->wp_entity->ID;
	}

	/**
	 * Return related entity IDs for this user.
	 *
	 * @param string $entity_type Target entity type.
	 * @return list<int>
	 */
	public function get_related( string $entity_type ): array {
		return Relationships::get_related( 'user', $this->get_id(), $entity_type );
	}

	/**
	 * Relate this user to another platform entity.
	 *
	 * @param string $entity_type Target entity type.
	 * @param int    $entity_id Target entity ID.
	 */
	public function relate_to( string $entity_type, int $entity_id ): void {
		Relationships::relate( 'user', $this->get_id(), $entity_type, $entity_id );
	}

	/**
	 * Pass unknown property reads through to the native WP_User.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( string $name ): mixed {
		return $this->wp_entity->{$name} ?? null;
	}
}
