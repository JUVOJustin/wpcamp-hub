<?php
/**
 * Fired during plugin activation
 *
 * @package    WPCAMP_HUB
 */

namespace WPCAMP_HUB;

use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Frontend\Attendees_Page;
use WPCAMP_HUB\Frontend\Speakers_Page;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @link https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 * @package    WPCAMP_HUB
 */
class Activator {

	/**
	 * This is the general callback run during the 'register_activation_hook' hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Seed the default taxonomy terms once. Doing this on activation (not on
		// every init) means terms the user later renames or deletes are not
		// recreated on each request.
		( new Data_Structure() )->seed_terms();

		// Register CPTs and the per-event endpoints, then flush rewrite rules so
		// /event/<slug>/attendees/ and /speakers/ resolve without a manual
		// permalinks re-save.
		( new Data_Structure() )->register();
		( new Attendees_Page() )->add_endpoint();
		( new Speakers_Page() )->add_endpoint();
		flush_rewrite_rules();
	}

	/**
	 * Add logic to the activation on a network site.
	 *
	 * @param string $plugin Plugin file loaded.
	 * @param bool   $network_wide Indicates if loaded network wide.
	 * @return void
	 */
	public static function network_activation( string $plugin, bool $network_wide ): void {

		if ( ! str_contains( $plugin, WPCAMP_HUB::PLUGIN_NAME ) || ! $network_wide ) {
			return;
		}

		// phpcs:disable Squiz.PHP.CommentedOutCode.Found

		// Network deactivate
		// deactivate_plugins( $plugin, false, true );

		// Activate on single site
		// activate_plugins( $plugin );

		// phpcs:enable
	}
}
