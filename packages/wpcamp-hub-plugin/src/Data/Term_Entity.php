<?php
/**
 * Base wrapper for platform taxonomy terms.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Adds platform APIs around a native WP_Term instance.
 */
abstract class Term_Entity {

	/**
	 * Native WordPress term object.
	 *
	 * @var \WP_Term
	 */
	protected \WP_Term $wp_entity;

	/**
	 * Wrap a term ID or native term object.
	 *
	 * @param int|\WP_Term $term Term ID or object.
	 * @throws \InvalidArgumentException When the term cannot be wrapped by this entity.
	 */
	final public function __construct( int|\WP_Term $term ) {
		$wp_term = $term instanceof \WP_Term ? $term : get_term( $term, static::get_taxonomy() );

		if ( ! $wp_term instanceof \WP_Term || static::get_taxonomy() !== $wp_term->taxonomy ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid term entity.' ) );
		}

		$this->wp_entity = $wp_term;
	}

	/**
	 * Build an entity wrapper from an ID or WP_Term.
	 *
	 * @param int|\WP_Term $term Term ID or object.
	 * @return static
	 */
	public static function from( int|\WP_Term $term ): static {
		return new static( $term );
	}

	/**
	 * The registered taxonomy represented by the wrapper.
	 */
	abstract public static function get_taxonomy(): string;

	/**
	 * Native WordPress entity.
	 */
	public function get_wp_entity(): \WP_Term {
		return $this->wp_entity;
	}

	/**
	 * Native term ID.
	 */
	public function get_id(): int {
		return (int) $this->wp_entity->term_id;
	}

	/**
	 * Term display name.
	 */
	public function get_name(): string {
		return (string) $this->wp_entity->name;
	}

	/**
	 * Pass unknown property reads through to the native WP_Term.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( string $name ): mixed {
		return $this->wp_entity->{$name} ?? null;
	}
}
