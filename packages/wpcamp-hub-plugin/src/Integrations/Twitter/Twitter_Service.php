<?php
/**
 * Twitter integration entry point.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Integrations\Twitter;

/**
 * Resolves the API key, builds the importer and runs imports.
 *
 * API key resolution order:
 *  1. Filter `wpcamp_hub/twitter_api_key`.
 *  2. Option `wpcamp_hub_twitter_api_key`.
 *  3. Constant `WPCAMP_HUB_X_API_KEY`.
 *  4. Environment variable `X_TWITTER_SCRAPER_API_KEY`.
 */
class Twitter_Service {

	public const OPTION_API_KEY = 'wpcamp_hub_twitter_api_key';

	/**
	 * Resolve the scraper API key.
	 *
	 * @return string Empty string when none configured.
	 */
	public function get_api_key(): string {
		$key = (string) get_option( self::OPTION_API_KEY, '' );

		if ( '' === $key && defined( 'WPCAMP_HUB_X_API_KEY' ) ) {
			$key = (string) constant( 'WPCAMP_HUB_X_API_KEY' );
		}

		if ( '' === $key ) {
			$env = getenv( 'X_TWITTER_SCRAPER_API_KEY' );
			$key = false !== $env ? (string) $env : '';
		}

		/**
		 * Filters the resolved scraper API key.
		 *
		 * @param string $key API key.
		 */
		return (string) apply_filters( 'wpcamp_hub/twitter_api_key', $key );
	}

	/**
	 * Whether the integration has an API key configured.
	 */
	public function is_configured(): bool {
		return '' !== $this->get_api_key();
	}

	/**
	 * Build an importer using the configured client.
	 *
	 * The client is filterable so tests/integrations can inject their own.
	 *
	 * @return Tweet_Importer|null Null when no API key is configured.
	 */
	public function get_importer(): ?Tweet_Importer {
		/**
		 * Filters the tweet client used by the importer.
		 *
		 * Return a Tweet_Client_Interface to override the default scraper
		 * client (e.g. for tests or an alternative provider).
		 *
		 * @param Tweet_Client_Interface|null $client  Client override.
		 * @param Twitter_Service             $service This service.
		 */
		$client = apply_filters( 'wpcamp_hub/twitter_client', null, $this );

		if ( ! $client instanceof Tweet_Client_Interface ) {
			$api_key = $this->get_api_key();
			if ( '' === $api_key ) {
				return null;
			}
			$client = X_Scraper_Client::from_api_key( $api_key );
		}

		return new Tweet_Importer( $client );
	}

	/**
	 * Run an import for a query.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum tweets to fetch.
	 * @return array{imported:int[],skipped:int,total:int}|\WP_Error
	 */
	public function import( string $query, int $limit = 20 ): array|\WP_Error {
		$importer = $this->get_importer();

		if ( null === $importer ) {
			return new \WP_Error(
				'wpcamp_hub_twitter_unconfigured',
				__( 'No X/Twitter API key configured.', 'wpcamp-hub' )
			);
		}

		return $importer->import( $query, $limit );
	}
}
