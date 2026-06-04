<?php
/**
 * Tweet label term wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents the tweet_label taxonomy.
 */
class Tweet_Label extends Term_Entity {

	/**
	 * The registered taxonomy represented by the wrapper.
	 */
	public static function get_taxonomy(): string {
		return Data_Structure::TAXONOMY_TWEET_LABEL;
	}
}
