<?php
/**
 * Fired during plugin deactivation
 *
 * @package    WPCAMP_HUB
 */

namespace WPCAMP_HUB;

use WPCAMP_HUB\Import\WordCamp_Attendee_Importer;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @package    WPCAMP_HUB
 */
class Deactivator {

	/**
	 * This is the general callback run during the 'register_deactivation_hook' hook.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		WordCamp_Attendee_Importer::unschedule_daily_import();
	}
}
