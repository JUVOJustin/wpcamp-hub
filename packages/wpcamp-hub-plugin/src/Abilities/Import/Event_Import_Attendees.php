<?php
/**
 * Ability for importing WordCamp event attendees.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Abilities\Import;

use WPCAMP_HUB\Abilities\Ability_Interface;
use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Import\WordCamp_Attendee_Importer;
use WP_Error;

/**
 * Imports attendees for one configured WordCamp event.
 */
class Event_Import_Attendees implements Ability_Interface {

	/**
	 * Ability name.
	 */
	public static function get_name(): string {
		return 'wpcamp-hub/event-import-attendees';
	}

	/**
	 * Ability label.
	 */
	public static function get_label(): string {
		return __( 'Import WordCamp event attendees', 'wpcamp-hub' );
	}

	/**
	 * Ability description.
	 */
	public static function get_description(): string {
		return __( 'Imports attendees for a configured WordCamp event.', 'wpcamp-hub' );
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
	 * Output schema for attendee import summaries.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'event_id'       => array( 'type' => 'integer' ),
				'attendees_url'  => array( 'type' => 'string' ),
				'imported_count' => array( 'type' => 'integer' ),
			),
			'required'             => array( 'event_id', 'attendees_url', 'imported_count' ),
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
	 * Only administrators may execute the ability.
	 */
	public static function check_permissions( mixed $input = null ): bool|WP_Error {
		return Event_Import_Speakers::check_permissions( $input );
	}

	/**
	 * Import attendees for one configured event.
	 */
	public static function execute( mixed $input = null ): mixed {
		$event_id = Event_Import_Speakers::event_id_from_input( $input );
		if ( is_wp_error( $event_id ) ) {
			return $event_id;
		}

		if ( Data_Structure::POST_TYPE_EVENT !== get_post_type( $event_id ) ) {
			return new WP_Error(
				'wpcamp_hub_invalid_event',
				__( 'The event_id must reference a WPCamp event.', 'wpcamp-hub' )
			);
		}

		$attendees_url = get_post_meta( $event_id, 'wpcamp_attendees_url', true );
		if ( ! is_string( $attendees_url ) || '' === esc_url_raw( $attendees_url ) ) {
			return new WP_Error(
				'wpcamp_hub_event_attendees_url_missing',
				__( 'The event must have a valid attendees URL.', 'wpcamp-hub' )
			);
		}

		try {
			$imported = ( new WordCamp_Attendee_Importer() )->import_event_attendees( $event_id, $attendees_url );
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'wpcamp_hub_attendees_import_failed',
				esc_html( $e->getMessage() )
			);
		}

		return array(
			'event_id'       => $event_id,
			'attendees_url'  => esc_url_raw( $attendees_url ),
			'imported_count' => count( $imported ),
		);
	}
}
