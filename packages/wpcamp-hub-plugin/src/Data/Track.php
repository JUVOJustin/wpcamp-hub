<?php
/**
 * Track term wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents the track taxonomy.
 */
class Track extends Term_Entity {

	/**
	 * The registered taxonomy represented by the wrapper.
	 */
	public static function get_taxonomy(): string {
		return Data_Structure::TAXONOMY_TRACK;
	}

	/**
	 * Accent colour for the track.
	 *
	 * @param string $fallback Colour to use when none is set.
	 * @return string Hex colour.
	 */
	public function get_color( string $fallback = '#3858E9' ): string {
		$color = get_term_meta( $this->get_id(), Data_Structure::TERM_META_COLOR, true );

		return is_string( $color ) && '' !== $color ? $color : $fallback;
	}

	/**
	 * All track terms as wrappers, ordered by name.
	 *
	 * @return list<self>
	 */
	public static function all(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::get_taxonomy(),
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map( static fn( \WP_Term $term ): self => self::from( $term ), $terms );
	}
}
