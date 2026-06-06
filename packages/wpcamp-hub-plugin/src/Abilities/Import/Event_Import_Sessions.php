<?php
/**
 * Internal ability for importing WordCamp event sessions.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Abilities\Import;

use WPCAMP_HUB\Abilities\Ability_Interface;
use WP_Error;

/**
 * Imports all session pages for one major WordCamp event.
 */
class Event_Import_Sessions implements Ability_Interface {

	/**
	 * Ability name.
	 */
	public static function get_name(): string {
		return 'wpcamp-hub/event-import-sessions';
	}

	/**
	 * Ability label.
	 */
	public static function get_label(): string {
		return __( 'Import WordCamp event sessions', 'wpcamp-hub' );
	}

	/**
	 * Ability description.
	 */
	public static function get_description(): string {
		return __( 'Imports all sessions for a configured major WordCamp event.', 'wpcamp-hub' );
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
		return Event_Import_Speakers::get_input_schema();
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
		return Event_Import_Speakers::check_permissions( $input );
	}

	/**
	 * Import all session pages for one event.
	 */
	public static function execute( mixed $input = null ): mixed {
		$event_id = Event_Import_Speakers::event_id_from_input( $input );
		if ( is_wp_error( $event_id ) ) {
			return $event_id;
		}

		$importer = Event_Import_Speakers::importer_for_event( $event_id );
		if ( is_wp_error( $importer ) ) {
			return $importer;
		}

		$page           = 1;
		$pages_imported = 0;
		$items_seen     = 0;
		$total_items    = 0;

		do {
			$result = $importer->import_sessions_page( $page );
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
}
