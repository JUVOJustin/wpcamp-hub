<?php
/**
 * WP-CLI command for importing tweets.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Integrations\Twitter;

/**
 * Import X/Twitter posts into the tweet CPT.
 */
class Tweet_Import_Command {

	/**
	 * Service.
	 *
	 * @var Twitter_Service
	 */
	private Twitter_Service $service;

	/**
	 * Construct the command.
	 *
	 * @param Twitter_Service $service Twitter service.
	 */
	public function __construct( Twitter_Service $service ) {
		$this->service = $service;
	}

	/**
	 * Import tweets matching a search query.
	 *
	 * ## OPTIONS
	 *
	 * --query=<query>
	 * : The search query, e.g. "#WCEU".
	 *
	 * [--limit=<limit>]
	 * : Maximum number of tweets to fetch.
	 * ---
	 * default: 20
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcamp import-tweets --query="#WCEU" --limit=50
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$query = isset( $assoc_args['query'] ) ? (string) $assoc_args['query'] : '';
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;

		if ( '' === $query ) {
			\WP_CLI::error( 'Provide a query with --query.' );
		}

		$result = $this->service->import( $query, $limit );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success(
			sprintf(
				'Imported %d, skipped %d (of %d fetched).',
				count( $result['imported'] ),
				$result['skipped'],
				$result['total']
			)
		);
	}
}
