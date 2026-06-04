<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://justin-vogt.com
 * @since             1.0.0
 * @package           WPCAMP_HUB
 *
 * @wordpress-plugin
 * Plugin Name:       WPCAMP-HUB
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Requires PHP:      8.0
 * Requires at least: 6.9
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpcamp-hub
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
use WPCAMP_HUB\Activator;
use WPCAMP_HUB\Deactivator;
use WPCAMP_HUB\WPCAMP_HUB;
use WPCAMP_HUB\Uninstallor;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin absolute path
 */
define( 'WPCAMP_HUB_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCAMP_HUB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Use Composer PSR-4 Autoloading
 */
require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/**
 * The code that runs during plugin activation.
 */
function wpcamp_hub_activate(): void {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function wpcamp_hub_deactivate(): void {
	Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 */
function wpcamp_hub_uninstall(): void {
	Uninstallor::uninstall();
}

register_activation_hook( __FILE__, 'wpcamp_hub_activate' );
register_deactivation_hook( __FILE__, 'wpcamp_hub_deactivate' );
register_uninstall_hook( __FILE__, 'wpcamp_hub_uninstall' );
add_action( 'activated_plugin', array( Activator::class, 'network_activation' ), 10, 2 );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function wpcamp_hub_run(): void {
	$plugin = new WPCAMP_HUB();
	$plugin->run();
}
wpcamp_hub_run();
