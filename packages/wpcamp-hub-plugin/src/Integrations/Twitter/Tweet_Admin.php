<?php
/**
 * Admin UI for the Twitter import.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Integrations\Twitter;

use WPCAMP_HUB\Data\Data_Structure;

/**
 * Adds an "Import tweets" page under the Tweets CPT menu with an API key
 * setting and a manual run trigger.
 */
class Tweet_Admin {

	private const PAGE_SLUG  = 'wpcamp-hub-tweet-import';
	private const NONCE_RUN  = 'wpcamp_hub_run_tweet_import';
	private const OPTION_KEY = Twitter_Service::OPTION_API_KEY;

	/**
	 * Service.
	 *
	 * @var Twitter_Service
	 */
	private Twitter_Service $service;

	/**
	 * Construct the admin UI.
	 *
	 * @param Twitter_Service $service Twitter service.
	 */
	public function __construct( Twitter_Service $service ) {
		$this->service = $service;
	}

	/**
	 * Register the API key setting.
	 */
	public function register_settings(): void {
		register_setting(
			'wpcamp_hub_twitter',
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Add the import page under the Tweets CPT menu.
	 */
	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Data_Structure::POST_TYPE_TWEET,
			__( 'Import tweets', 'wpcamp-hub' ),
			__( 'Import tweets', 'wpcamp-hub' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle the manual import form submission.
	 */
	public function maybe_run_import(): void {
		if ( ! isset( $_POST['wpcamp_hub_run_import'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_RUN ) ) {
			return;
		}

		$query = isset( $_POST['wpcamp_hub_query'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcamp_hub_query'] ) ) : '';
		$limit = isset( $_POST['wpcamp_hub_limit'] ) ? absint( wp_unslash( $_POST['wpcamp_hub_limit'] ) ) : 20;

		if ( '' === $query ) {
			$this->add_notice( 'error', __( 'Enter a search query.', 'wpcamp-hub' ) );
			return;
		}

		$result = $this->service->import( $query, $limit > 0 ? $limit : 20 );

		if ( is_wp_error( $result ) ) {
			$this->add_notice( 'error', $result->get_error_message() );
			return;
		}

		$this->add_notice(
			'success',
			sprintf(
				/* translators: 1: imported count, 2: skipped count, 3: total fetched. */
				__( 'Imported %1$d, skipped %2$d (of %3$d fetched).', 'wpcamp-hub' ),
				count( $result['imported'] ),
				$result['skipped'],
				$result['total']
			)
		);
	}

	/**
	 * Render the import page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import tweets', 'wpcamp-hub' ); ?></h1>

			<?php $this->print_notices(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'wpcamp_hub_twitter' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wpcamp_hub_api_key"><?php esc_html_e( 'X/Twitter API key', 'wpcamp-hub' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="wpcamp_hub_api_key"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>"
								value="<?php echo esc_attr( (string) get_option( self::OPTION_KEY, '' ) ); ?>"
								class="regular-text"
								autocomplete="off"
							/>
							<p class="description">
								<?php esc_html_e( 'Used by the X scraper. Can also be set via the WPCAMP_HUB_X_API_KEY constant.', 'wpcamp-hub' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save key', 'wpcamp-hub' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Run import', 'wpcamp-hub' ); ?></h2>
			<?php if ( ! $this->service->is_configured() ) : ?>
				<p><em><?php esc_html_e( 'Configure an API key above to enable imports.', 'wpcamp-hub' ); ?></em></p>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_RUN ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wpcamp_hub_query"><?php esc_html_e( 'Search query', 'wpcamp-hub' ); ?></label>
						</th>
						<td>
							<input type="text" id="wpcamp_hub_query" name="wpcamp_hub_query" value="#WCEU" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpcamp_hub_limit"><?php esc_html_e( 'Limit', 'wpcamp-hub' ); ?></label>
						</th>
						<td>
							<input type="number" id="wpcamp_hub_limit" name="wpcamp_hub_limit" value="20" min="1" max="200" class="small-text" />
						</td>
					</tr>
				</table>
				<?php
				submit_button(
					__( 'Import now', 'wpcamp-hub' ),
					'primary',
					'wpcamp_hub_run_import',
					true,
					$this->service->is_configured() ? array() : array( 'disabled' => 'disabled' )
				);
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Queue an admin notice for the current request.
	 *
	 * @param string $type    "success" or "error".
	 * @param string $message Notice text.
	 */
	private function add_notice( string $type, string $message ): void {
		$this->notices[] = array(
			'type'    => $type,
			'message' => $message,
		);
	}

	/**
	 * Notices queued during this request.
	 *
	 * @var array<int,array{type:string,message:string}>
	 */
	private array $notices = array();

	/**
	 * Print queued notices.
	 */
	private function print_notices(): void {
		foreach ( $this->notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				'error' === $notice['type'] ? 'error' : 'success',
				esc_html( $notice['message'] )
			);
		}
	}
}
