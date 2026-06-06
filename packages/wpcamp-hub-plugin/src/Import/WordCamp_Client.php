<?php
/**
 * Thin client for the native WordCamp REST API.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Import;

/**
 * Fetches paginated collections (sessions, speakers) from a WordCamp site's
 * `wp-json/wp/v2/` API.
 *
 * The client fetches a single page per call and reports the total number of
 * pages from the `X-WP-TotalPages` response header. Callers walk pages
 * incrementally so each scheduled run stays bounded.
 */
class WordCamp_Client {

	/**
	 * Default items requested per page (the WordCamp API caps this at 100).
	 */
	public const PER_PAGE = 100;

	/**
	 * Base API URL, normalized to end in `/wp-json/wp/v2/`.
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Build a client for a single WordCamp site.
	 *
	 * Accepts any URL pointing at (or into) a WordCamp site and normalizes it
	 * to the `wp/v2` REST root, so a site root, an API root, or a full
	 * collection URL are all acceptable inputs.
	 *
	 * @param string $url WordCamp site URL or REST API URL.
	 */
	public function __construct( string $url ) {
		$this->base_url = self::normalize_base_url( $url );
	}

	/**
	 * The normalized `wp/v2` API root this client targets.
	 */
	public function get_base_url(): string {
		return $this->base_url;
	}

	/**
	 * Fetch one page of sessions.
	 *
	 * @param int $page 1-based page number.
	 * @return WordCamp_Page Page result.
	 * @throws \RuntimeException When the request fails or returns an error status.
	 */
	public function get_sessions( int $page = 1 ): WordCamp_Page {
		return $this->get_collection( 'sessions', $page );
	}

	/**
	 * Fetch one page of speakers.
	 *
	 * @param int $page 1-based page number.
	 * @return WordCamp_Page Page result.
	 * @throws \RuntimeException When the request fails or returns an error status.
	 */
	public function get_speakers( int $page = 1 ): WordCamp_Page {
		return $this->get_collection( 'speakers', $page );
	}

	/**
	 * Host of the WordCamp site, used to namespace source identifiers.
	 */
	public function get_host(): string {
		$host = wp_parse_url( $this->base_url, PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}

	/**
	 * Fetch a single page of a collection.
	 *
	 * @param string $collection Collection resource (e.g. sessions, speakers).
	 * @param int    $page 1-based page number.
	 * @return WordCamp_Page Page result.
	 * @throws \RuntimeException When the request fails or returns an error status.
	 */
	private function get_collection( string $collection, int $page ): WordCamp_Page {
		$url = add_query_arg(
			array(
				'per_page' => self::PER_PAGE,
				'page'     => max( 1, $page ),
				'_embed'   => '1',
			),
			$this->base_url . $collection
		);

		$response = $this->request( $url );

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new \RuntimeException(
				esc_html( sprintf( 'WordCamp API returned HTTP %1$d for %2$s', $status, $url ) )
			);
		}

		$items = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$total_pages = (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' );
		$total_items = (int) wp_remote_retrieve_header( $response, 'x-wp-total' );

		return new WordCamp_Page(
			array_values( array_filter( $items, 'is_array' ) ),
			max( 1, $page ),
			max( 1, $total_pages ),
			$total_items
		);
	}

	/**
	 * Perform a GET request against the WordCamp API.
	 *
	 * @param string $url Fully-qualified request URL.
	 * @return array<string,mixed> Raw wp_remote_get response.
	 * @throws \RuntimeException When the transport fails.
	 */
	private function request( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'WPCamp Hub/' . \WPCAMP_HUB\WPCAMP_HUB::PLUGIN_VERSION . '; ' . home_url(),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( $response->get_error_message() ) );
		}

		return $response;
	}

	/**
	 * Normalize an arbitrary WordCamp URL to its `wp/v2` REST root.
	 *
	 * @param string $url Input URL.
	 * @return string Normalized API root ending in a slash.
	 */
	private static function normalize_base_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		// Drop the query string and any collection segment the caller passed in.
		$query_position = strpos( $url, '?' );
		if ( false !== $query_position ) {
			$url = substr( $url, 0, $query_position );
		}
		$url = preg_replace( '#/(sessions|speakers|session_track)\b.*$#', '/', $url ) ?? $url;
		$url = rtrim( $url, '/' );

		// Already an API root.
		if ( str_ends_with( $url, '/wp-json/wp/v2' ) ) {
			return $url . '/';
		}

		// A wp-json root without the namespace.
		if ( str_ends_with( $url, '/wp-json' ) ) {
			return $url . '/wp/v2/';
		}

		// A bare site URL.
		return $url . '/wp-json/wp/v2/';
	}
}
