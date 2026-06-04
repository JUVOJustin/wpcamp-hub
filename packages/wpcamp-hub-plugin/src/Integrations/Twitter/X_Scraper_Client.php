<?php
/**
 * REST client for the Xquik X (Twitter) scraper API.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Integrations\Twitter;

/**
 * Talks to the Xquik REST API directly via the WordPress HTTP API.
 *
 * Uses GET https://xquik.com/api/v1/x/tweets/search with an x-api-key header.
 * Paginates with the opaque next_cursor until the requested limit is reached.
 */
class X_Scraper_Client implements Tweet_Client_Interface {

	private const BASE_URL = 'https://xquik.com/api/v1';

	/**
	 * API key (xq_...).
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout;

	/**
	 * Construct the client.
	 *
	 * @param string $api_key API key.
	 * @param int    $timeout Request timeout in seconds.
	 */
	public function __construct( string $api_key, int $timeout = 20 ) {
		$this->api_key = $api_key;
		$this->timeout = $timeout;
	}

	/**
	 * Build the client from an API key.
	 *
	 * @param string $api_key Scraper API key.
	 * @return self
	 */
	public static function from_api_key( string $api_key ): self {
		return new self( $api_key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum results.
	 * @return array<int,array{id:string,text:string,handle:string,name:string,url:string,timestamp:string}>
	 */
	public function search( string $query, int $limit = 20 ): array {
		$limit       = max( 1, $limit );
		$collected   = array();
		$cursor      = '';
		$safety_stop = 20; // Hard cap on page requests.

		do {
			$page = $this->request_page( $query, $cursor );

			if ( null === $page ) {
				break;
			}

			foreach ( $page['tweets'] as $tweet ) {
				$normalised = $this->normalise( $tweet );
				if ( null !== $normalised ) {
					$collected[] = $normalised;
				}

				if ( count( $collected ) >= $limit ) {
					return array_slice( $collected, 0, $limit );
				}
			}

			$cursor = $page['next_cursor'];
		} while ( '' !== $cursor && $page['has_next_page'] && --$safety_stop > 0 );

		return array_slice( $collected, 0, $limit );
	}

	/**
	 * Fetch one page of search results.
	 *
	 * @param string $query  Search query.
	 * @param string $cursor Pagination cursor ('' for the first page).
	 * @return array{tweets:array<int,mixed>,has_next_page:bool,next_cursor:string}|null
	 */
	private function request_page( string $query, string $cursor ): ?array {
		$args = array( 'q' => $query );
		if ( '' !== $cursor ) {
			$args['cursor'] = $cursor;
		}

		$url = add_query_arg( array_map( 'rawurlencode', $args ), self::BASE_URL . '/x/tweets/search' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'x-api-key' => $this->api_key,
					'Accept'    => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return null;
		}

		return array(
			'tweets'        => isset( $body['tweets'] ) && is_array( $body['tweets'] ) ? $body['tweets'] : array(),
			'has_next_page' => ! empty( $body['has_next_page'] ),
			'next_cursor'   => isset( $body['next_cursor'] ) ? (string) $body['next_cursor'] : '',
		);
	}

	/**
	 * Normalise a raw API tweet to the importer shape.
	 *
	 * @param mixed $tweet Raw tweet from the API.
	 * @return array{id:string,text:string,handle:string,name:string,url:string,timestamp:string}|null
	 */
	private function normalise( mixed $tweet ): ?array {
		if ( ! is_array( $tweet ) ) {
			return null;
		}

		$id = isset( $tweet['id'] ) ? (string) $tweet['id'] : '';
		if ( '' === $id ) {
			return null;
		}

		$author = isset( $tweet['author'] ) && is_array( $tweet['author'] ) ? $tweet['author'] : array();
		$handle = isset( $author['username'] ) ? (string) $author['username'] : '';

		return array(
			'id'        => $id,
			'text'      => isset( $tweet['text'] ) ? (string) $tweet['text'] : '',
			'handle'    => $handle,
			'name'      => isset( $author['name'] ) ? (string) $author['name'] : '',
			'url'       => self::build_url( $handle, $id ),
			'timestamp' => isset( $tweet['createdAt'] ) ? (string) $tweet['createdAt'] : '',
		);
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
