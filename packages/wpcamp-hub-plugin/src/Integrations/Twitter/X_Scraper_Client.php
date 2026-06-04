<?php
/**
 * Adapter around the X (Twitter) scraper SDK.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Integrations\Twitter;

use WPCAMP_HUB\Dependencies\XTwitterScraper\Client;

/**
 * Wraps the prefixed XTwitterScraper SDK and normalises its tweet objects.
 */
class X_Scraper_Client implements Tweet_Client_Interface {

	/**
	 * SDK client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Construct the adapter.
	 *
	 * @param Client $client Configured scraper client.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
	}

	/**
	 * Build the adapter from an API key.
	 *
	 * @param string $api_key Scraper API key.
	 * @return self
	 */
	public static function from_api_key( string $api_key ): self {
		return new self( new Client( apiKey: $api_key ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum results.
	 * @return array<int,array{id:string,text:string,handle:string,name:string,url:string,timestamp:string}>
	 */
	public function search( string $query, int $limit = 20 ): array {
		$paginated = $this->client->x->tweets->search( q: $query, limit: $limit );

		$tweets = isset( $paginated->tweets ) ? (array) $paginated->tweets : array();

		$normalised = array();
		foreach ( $tweets as $tweet ) {
			$handle = isset( $tweet->author->username ) ? (string) $tweet->author->username : '';
			$id     = isset( $tweet->id ) ? (string) $tweet->id : '';

			if ( '' === $id ) {
				continue;
			}

			$normalised[] = array(
				'id'        => $id,
				'text'      => isset( $tweet->text ) ? (string) $tweet->text : '',
				'handle'    => $handle,
				'name'      => isset( $tweet->author->name ) ? (string) $tweet->author->name : '',
				'url'       => self::build_url( $handle, $id ),
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- SDK property.
				'timestamp' => isset( $tweet->createdAt ) ? (string) $tweet->createdAt : '',
			);
		}

		return $normalised;
	}

	/**
	 * Build a canonical tweet URL.
	 *
	 * @param string $handle Author handle.
	 * @param string $id     Tweet ID.
	 * @return string
	 */
	private static function build_url( string $handle, string $id ): string {
		$handle = '' !== $handle ? $handle : 'i';

		return sprintf( 'https://x.com/%s/status/%s', $handle, $id );
	}
}
