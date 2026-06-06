<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    WPCAMP_HUB
 */

namespace WPCAMP_HUB;

use WPCAMP_HUB\API\REST_API_Authentication;
use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Import\WordCamp_Attendee_Importer;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WPCAMP_HUB
 * @author     Justin Vogt <mail@juvo-design.de>
 */
class WPCAMP_HUB {


	public const string PLUGIN_NAME    = 'wpcamp-hub';
	public const string PLUGIN_VERSION = '1.0.0';

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin
	 *
	 * @var Loader
	 */
	protected Loader $loader;

	/**
	 * Central data structure registration.
	 *
	 * @var Data_Structure
	 */
	protected Data_Structure $data_structure;

	/**
	 * WordCamp attendee import orchestration.
	 *
	 * @var WordCamp_Attendee_Importer
	 */
	protected WordCamp_Attendee_Importer $attendee_importer;

	/**
	 * REST API authentication policy.
	 *
	 * @var REST_API_Authentication
	 */
	protected REST_API_Authentication $rest_api_authentication;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		$this->loader->add_action( 'init', $this, 'register_blocks' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies(): void {

		$this->loader                  = new Loader();
		$this->data_structure          = new Data_Structure();
		$this->attendee_importer       = new WordCamp_Attendee_Importer();
		$this->rest_api_authentication = new REST_API_Authentication();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale(): void {
		load_plugin_textdomain(
			'wpcamp-hub',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks(): void {

		$this->loader->add_action( 'add_meta_boxes', $this->data_structure, 'register_meta_boxes', 10, 0 );
		$this->loader->add_action( 'save_post', $this->data_structure, 'save_post_meta', 10, 2 );
		$this->loader->add_action( 'enqueue_block_editor_assets', $this, 'add_editor_block_data', 10, 0 );

		// Track accent colour field on the taxonomy term forms.
		$this->loader->add_action( 'wpcamp_track_add_form_fields', $this->data_structure, 'add_track_color_field', 10, 0 );
		$this->loader->add_action( 'wpcamp_track_edit_form_fields', $this->data_structure, 'edit_track_color_field', 10, 1 );
		$this->loader->add_action( 'created_wpcamp_track', $this->data_structure, 'save_track_color', 10, 1 );
		$this->loader->add_action( 'edited_wpcamp_track', $this->data_structure, 'save_track_color', 10, 1 );

		// Tweet label accent colour + icon fields on the taxonomy term forms.
		$this->loader->add_action( 'wpcamp_tweet_label_add_form_fields', $this->data_structure, 'add_tweet_label_fields', 10, 0 );
		$this->loader->add_action( 'wpcamp_tweet_label_edit_form_fields', $this->data_structure, 'edit_tweet_label_fields', 10, 1 );
		$this->loader->add_action( 'created_wpcamp_tweet_label', $this->data_structure, 'save_tweet_label_fields', 10, 1 );
		$this->loader->add_action( 'edited_wpcamp_tweet_label', $this->data_structure, 'save_tweet_label_fields', 10, 1 );

		add_action(
			'admin_enqueue_scripts',
			function () {
				$this->enqueue_entrypoint( 'wpcamp-hub-admin' );
			},
			100
		);
	}

	/**
	 * Add data consumed by editor blocks.
	 */
	public function add_editor_block_data(): void {
		wp_add_inline_script(
			generate_block_asset_handle( 'wpcamp-hub/post-meta-panel', 'editorScript' ),
			'window.wpcamp_hub = window.wpcamp_hub || {}; window.wpcamp_hub.postMetaFields = ' . wp_json_encode( $this->data_structure->get_editor_post_meta_fields() ) . ';',
			'before'
		);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks(): void {

		$this->loader->add_action( 'init', $this->data_structure, 'register' );
		$this->loader->add_filter( 'rest_authentication_errors', $this->rest_api_authentication, 'require_authentication_for_all_rest_api_requests', 11, 1 );
		$this->loader->add_action( 'action_scheduler_init', $this->attendee_importer, 'schedule_daily_import', 10, 0 );
		$this->loader->add_action( WordCamp_Attendee_Importer::AS_HOOK, $this->attendee_importer, 'import_scheduled_events', 10, 0 );
		$this->loader->add_action( WordCamp_Attendee_Importer::AS_EVENT_HOOK, $this->attendee_importer, 'import_event_attendees', 10, 2 );

		// Community tweet feed — AJAX-paginated archive cards.
		$tweet_feed = new \WPCAMP_HUB\Frontend\Tweet_Feed();
		$this->loader->add_action( 'wp_ajax_' . \WPCAMP_HUB\Frontend\Tweet_Feed::AJAX_ACTION, $tweet_feed, 'ajax_feed', 10, 0 );
		$this->loader->add_action( 'wp_ajax_nopriv_' . \WPCAMP_HUB\Frontend\Tweet_Feed::AJAX_ACTION, $tweet_feed, 'ajax_feed', 10, 0 );

		// Per-event attendees subpage — /event/<slug>/attendees/.
		$attendees_page = new \WPCAMP_HUB\Frontend\Attendees_Page();
		$this->loader->add_action( 'init', $attendees_page, 'add_endpoint', 10, 0 );
		$this->loader->add_filter( 'template_include', $attendees_page, 'template_include', 20, 1 );

		// Per-event speakers subpage — /event/<slug>/speakers/.
		$speakers_page = new \WPCAMP_HUB\Frontend\Speakers_Page();
		$this->loader->add_action( 'init', $speakers_page, 'add_endpoint', 10, 0 );
		$this->loader->add_filter( 'template_include', $speakers_page, 'template_include', 20, 1 );

		// Serve the imported attendee avatar everywhere (front end + admin).
		$avatar = new \WPCAMP_HUB\Frontend\Avatar();
		$this->loader->add_filter( 'get_avatar_url', $avatar, 'filter_url', 10, 3 );

		add_action(
			'wp_enqueue_scripts',
			function () {
				$this->enqueue_entrypoint( 'wpcamp-hub-frontend' );
			},
			100
		);
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}

	/**
	 * Enqueue a webpack entrypoint
	 *
	 * @param string              $entry Name of the entrypoint defined in webpack.config.js.
	 * @param array<string,mixed> $localize_data Array of associated data. See https://developer.wordpress.org/reference/functions/wp_localize_script/ .
	 */
	private function enqueue_entrypoint( string $entry, array $localize_data = array() ): void {

		// Try to get WordPress filesystem. If not possible load it.
		global $wp_filesystem;
		if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
			WP_Filesystem();
		}

		$filesystem = new \WP_Filesystem_Direct( false );

		$asset_file = WPCAMP_HUB_PATH . "/build/{$entry}.asset.php";
		if ( ! $filesystem->exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;
		if ( ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return;
		}

		if ( $filesystem->exists( WPCAMP_HUB_PATH . "build/{$entry}.js" ) ) {
			wp_enqueue_script(
				self::PLUGIN_NAME . "/{$entry}",
				WPCAMP_HUB_URL . "build/{$entry}.js",
				$asset['dependencies'],
				$asset['version'],
				true
			);

			// Potentially add localize data
			if ( ! empty( $localize_data ) ) {
				wp_localize_script(
					self::PLUGIN_NAME . "/{$entry}",
					str_replace( '-', '_', self::PLUGIN_NAME ),
					$localize_data
				);
			}

			// Load JSON translations
			wp_set_script_translations( self::PLUGIN_NAME . "/{$entry}", 'wpcamp-hub', WPCAMP_HUB_PATH . 'languages/' );
		}

		if ( $filesystem->exists( WPCAMP_HUB_PATH . "build/{$entry}.css" ) ) {
			wp_enqueue_style(
				self::PLUGIN_NAME . "/{$entry}",
				WPCAMP_HUB_URL . "build/{$entry}.css",
				array(),
				$asset['version']
			);
		}
	}

	/**
	 * Register Gutenberg blocks.
	 *
	 * Registers all Gutenberg blocks from the Blocks directory.
	 * Block assets are loaded from the build/Blocks directory using a manifest file.
	 * Uses the metadata collection API (WP 6.8+).
	 *
	 * JSON Translations are loaded automatically. Use `npm run i18n:compile` to generate translation files from .po files.
	 *
	 * To localize scripts you need to use `wp_localize_script`.
	 * The handle can be generated with `generate_block_asset_handle('wpcamp-hub/block-name', 'editorScript')`.
	 *
	 * @link https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 * @link https://developer.wordpress.org/reference/functions/generate_block_asset_handle/
	 *
	 * @return void
	 */
	public function register_blocks(): void {

		$manifest_file = WPCAMP_HUB_PATH . 'build/blocks-manifest.php';
		$blocks_folder = WPCAMP_HUB_PATH . 'build/Blocks';

		if ( ! is_readable( $manifest_file ) || ! is_dir( $blocks_folder ) ) {
			return;
		}

		wp_register_block_types_from_metadata_collection( $blocks_folder, $manifest_file );
	}
}
