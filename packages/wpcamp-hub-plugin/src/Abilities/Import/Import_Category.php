<?php
/**
 * Ability category for internal import operations.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Abilities\Import;

use WPCAMP_HUB\Abilities\Ability_Category_Interface;

/**
 * Groups internal import abilities for WPCamp Hub.
 */
class Import_Category implements Ability_Category_Interface {

	/**
	 * Unique category slug.
	 */
	public static function get_slug(): string {
		return 'wpcamp-hub-import';
	}

	/**
	 * Human-readable category label.
	 */
	public static function get_label(): string {
		return __( 'WPCamp Hub Import', 'wpcamp-hub' );
	}

	/**
	 * Category purpose.
	 */
	public static function get_description(): string {
		return __( 'Internal abilities for importing WordCamp event data.', 'wpcamp-hub' );
	}

	/**
	 * Category metadata.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_meta(): array {
		return array();
	}
}
