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
}
