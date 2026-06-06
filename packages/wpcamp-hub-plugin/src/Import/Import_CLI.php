<?php
/**
 * WP-CLI commands for the WordCamp import.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Import;

use WPCAMP_HUB\Data\Event;

/**
 * Run the WordCamp session/speaker import on demand.
 *
 * The daily run is fully automated through Action Scheduler; these commands
 * exist for verification and one-off backfills.
 */
class Import_CLI {

	/**
	 * Import all flagged WordCamps synchronously, walking every page.
	 *
	 * Unlike the scheduled run (which fans out bounded async jobs), this
	 * processes every page inline so the result is visible immediately.
	 *
	 * ## OPTIONS
	 *
	 * [--event=<id>]
	 * : Limit the import to a single event ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcamp-hub import-wordcamps
	 *     wp wpcamp-hub import-wordcamps --event=42
	 *
	 * @subcommand import-wordcamps
	 *
	 * @param array<int,string>    $args Positional arguments (unused).
	 * @param array<string,string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function import_wordcamps( array $args, array $assoc_args ): void {
		$events = isset( $assoc_args['event'] )
			? array_filter( array( $this->event_from_id( (int) $assoc_args['event'] ) ) )
			: Event::major_wordcamps();

		if ( array() === $events ) {
			\WP_CLI::warning( 'No importable WordCamp events found.' );
			return;
		}

		foreach ( $events as $event ) {
			$this->import_event( $event );
		}

		\WP_CLI::success( 'WordCamp import complete.' );
	}

	/**
	 * Resolve an importable event by ID.
	 *
	 * @param int $event_id Event post ID.
	 * @return Event|null
	 */
	private function event_from_id( int $event_id ): ?Event {
		if ( Event::get_post_type() !== get_post_type( $event_id ) ) {
			\WP_CLI::warning( sprintf( '#%d is not an event.', $event_id ) );
			return null;
		}

		$event = Event::from( $event_id );
		if ( ! $event->is_major_wordcamp() || '' === $event->get_official_url() ) {
			\WP_CLI::warning( sprintf( '#%d is not a major WordCamp with an official URL.', $event_id ) );
			return null;
		}

		return $event;
	}

	/**
	 * Import a single event, walking speakers then sessions to completion.
	 *
	 * @param Event $event Importable event.
	 */
	private function import_event( Event $event ): void {
		\WP_CLI::log( sprintf( 'Importing "%s" (#%d)…', get_the_title( $event->get_id() ), $event->get_id() ) );

		$importer = new WordCamp_Importer( $event );

		$page = 1;
		do {
			$result = $importer->import_speakers_page( $page );
			$page   = $result->next_page();
		} while ( null !== $page );
		\WP_CLI::log( sprintf( '  speakers: %d', $result->total_items ) );

		$page = 1;
		do {
			$result = $importer->import_sessions_page( $page );
			$page   = $result->next_page();
		} while ( null !== $page );
		\WP_CLI::log( sprintf( '  sessions: %d', $result->total_items ) );
	}
}
