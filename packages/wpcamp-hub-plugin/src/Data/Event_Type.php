<?php
/**
 * Event type term wrapper.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Represents the event_type taxonomy.
 */
class Event_Type extends Term_Entity {

	/**
	 * The registered taxonomy represented by the wrapper.
	 */
	public static function get_taxonomy(): string {
		return Data_Structure::TAXONOMY_EVENT_TYPE;
	}
}
