<?php
/**
 * Internal ability for importing WordCamp event speakers.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Abilities\Import;

use WPCAMP_HUB\Abilities\Ability_Interface;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Import\WordCamp_Importer;
use WP_Error;

/**
 * Imports all speaker pages for one major WordCamp event.
 */
class Event_Import_Speakers implements Ability_Interface {

	/**
	 * Ability name.
	 */
	public static function get_name(): string {
		return 'wpcamp-hub/event-import-speakers';
	}

	/**
	 * Ability label.
	 */
	public static function get_label(): string {
		return __( 'Import WordCamp event speakers', 'wpcamp-hub' );
	}

	/**
	 * Ability description.
	 */
	public static function get_description(): string {
		return __( 'Imports all speakers for a configured major WordCamp event.', 'wpcamp-hub' );
	}

	/**
	 * Ability category.
	 *
	 * @return class-string<Import_Category>
	 */
	public static function get_category(): string {
		return Import_Category::class;
	}

	/**
	 * Strict input schema for the event import.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'event_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Major WordCamp event post ID.', 'wpcamp-hub' ),
				),
			),
			'required'             => array( 'event_id' ),
		);
	}

	/**
	 * Output schema for import summaries.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'event_id'       => array( 'type' => 'integer' ),
				'pages_imported' => array( 'type' => 'integer' ),
				'items_seen'     => array( 'type' => 'integer' ),
				'total_items'    => array( 'type' => 'integer' ),
			),
			'required'             => array( 'event_id', 'pages_imported', 'items_seen', 'total_items' ),
		);
	}

	/**
	 * Ability safety annotations.
	 *
	 * @return array{readonly: bool, destructive: bool, idempotent: bool}
	 */
	public static function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	/**
	 * Expose the admin-only ability through REST for agents and tools.
	 */
	public static function show_rest(): bool {
		return true;
	}

	/**
	 * Only administrators may execute the ability through the Abilities API.
	 */
	public static function check_permissions( mixed $input = null ): bool|WP_Error {
		unset( $input );

		return current_user_can( 'manage_options' );
	}

	/**
	 * Import all speaker pages for one event.
	 */
	public static function execute( mixed $input = null ): mixed {
		$event_id = self::event_id_from_input( $input );
		if ( is_wp_error( $event_id ) ) {
			return $event_id;
		}

		$importer = self::importer_for_event( $event_id );
		if ( is_wp_error( $importer ) ) {
			return $importer;
		}

		$page           = 1;
		$pages_imported = 0;
		$items_seen     = 0;
		$total_items    = 0;

		do {
			$result = $importer->import_speakers_page( $page );
			++$pages_imported;
			$items_seen  += count( $result->items );
			$total_items  = $result->total_items;
			$page         = $result->next_page();
		} while ( null !== $page );

		return array(
			'event_id'       => $event_id,
			'pages_imported' => $pages_imported,
			'items_seen'     => $items_seen,
			'total_items'    => $total_items,
		);
	}

	/**
	 * Validate and return the event ID from ability input.
	 */
	public static function event_id_from_input( mixed $input ): int|WP_Error {
		if ( ! is_array( $input ) || ! array_key_exists( 'event_id', $input ) || ! is_int( $input['event_id'] ) || $input['event_id'] < 1 ) {
			return new WP_Error(
				'wpcamp_hub_invalid_event_id',
				__( 'A positive integer event_id is required.', 'wpcamp-hub' )
			);
		}

		return $input['event_id'];
	}

	/**
	 * Build an importer for a valid major WordCamp event.
	 */
	public static function importer_for_event( int $event_id ): WordCamp_Importer|WP_Error {
		if ( Event::get_post_type() !== get_post_type( $event_id ) ) {
			return new WP_Error(
				'wpcamp_hub_invalid_event',
				__( 'The event_id must reference a WPCamp event.', 'wpcamp-hub' )
			);
		}

		$event = Event::from( $event_id );
		if ( ! $event->is_major_wordcamp() || '' === $event->get_official_url() ) {
			return new WP_Error(
				'wpcamp_hub_event_not_importable',
				__( 'The event must be a major WordCamp with an official URL.', 'wpcamp-hub' )
			);
		}

		try {
			return new WordCamp_Importer( $event );
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'wpcamp_hub_importer_unavailable',
				esc_html( $e->getMessage() )
			);
		}
	}
}
