<?php
/**
 * Contract for a tweet source client.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Integrations\Twitter;

/**
 * Fetches raw tweets for the importer.
 *
 * Implementations adapt a concrete provider (the X scraper SDK, a fixture, a
 * mock) to a normalised array shape the importer can map onto the CPT.
 */
interface Tweet_Client_Interface {

	/**
	 * Search for tweets matching a query.
	 *
	 * Each returned tweet is a normalised associative array:
	 *  - id        (string)  Provider tweet ID.
	 *  - text      (string)  Tweet text.
	 *  - handle    (string)  Author handle/username (without "@").
	 *  - name      (string)  Author display name.
	 *  - url       (string)  Canonical tweet URL.
	 *  - timestamp (string)  ISO 8601 timestamp (may be empty).
	 *
	 * @param string $query Search query (e.g. "#WCEU").
	 * @param int    $limit Maximum number of tweets to return.
	 * @return array<int,array{id:string,text:string,handle:string,name:string,url:string,timestamp:string}>
	 */
	public function search( string $query, int $limit = 20 ): array;
}
