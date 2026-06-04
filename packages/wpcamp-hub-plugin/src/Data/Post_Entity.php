<?php
/**
 * Base wrapper for platform post entities.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Adds platform APIs around a native WP_Post instance.
 */
abstract class Post_Entity {

	/**
	 * Native WordPress post object.
	 *
	 * @var \WP_Post
	 */
	protected \WP_Post $wp_entity;

	/**
	 * Wrap a post ID or native post object.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 * @throws \InvalidArgumentException When the post cannot be wrapped by this entity.
	 */
	final public function __construct( int|\WP_Post $post ) {
		$wp_post = $post instanceof \WP_Post ? $post : get_post( $post );

		if ( ! $wp_post instanceof \WP_Post || static::get_post_type() !== $wp_post->post_type ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid post entity.' ) );
		}

		$this->wp_entity = $wp_post;
	}

	/**
	 * Build an entity wrapper from an ID or WP_Post.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 * @return static
	 */
	public static function from( int|\WP_Post $post ): static {
		return new static( $post );
	}

	/**
	 * The registered post type represented by the wrapper.
	 */
	abstract public static function get_post_type(): string;

	/**
	 * Native WordPress entity.
	 */
	public function get_wp_entity(): \WP_Post {
		return $this->wp_entity;
	}

	/**
	 * Native post ID.
	 */
	public function get_id(): int {
		return (int) $this->wp_entity->ID;
	}

	/**
	 * Return related entity IDs for this post.
	 *
	 * @param string $entity_type Target entity type.
	 * @return list<int>
	 */
	public function get_related( string $entity_type ): array {
		return Relationships::get_related( static::get_entity_type(), $this->get_id(), $entity_type );
	}

	/**
	 * Relate this post to another platform entity.
	 *
	 * @param string $entity_type Target entity type.
	 * @param int    $entity_id Target entity ID.
	 */
	public function relate_to( string $entity_type, int $entity_id ): void {
		Relationships::relate( static::get_entity_type(), $this->get_id(), $entity_type, $entity_id );
	}

	/**
	 * Platform entity type key.
	 */
	public static function get_entity_type(): string {
		return Data_Structure::post_type_to_entity_type( static::get_post_type() );
	}

	/**
	 * Pass unknown property reads through to the native WP_Post.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( string $name ): mixed {
		return $this->wp_entity->{$name} ?? null;
	}
}
