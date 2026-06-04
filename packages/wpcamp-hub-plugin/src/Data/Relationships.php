<?php
/**
 * Relationship storage for platform entities.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Stores entity relationships in one normalized meta map per object.
 */
class Relationships {

	public const string META_KEY = '_wpcamp_hub_relationships';

	/**
	 * Relationship rules keyed by source and target entity types.
	 *
	 * @var array<string,array<string,bool>>
	 */
	private static array $rules = array(
		'user'           => array(
			'tweet'   => true,
			'event'   => true,
			'session' => true,
		),
		'tweet'          => array(
			'event' => false,
			'user'  => true,
		),
		'meeting_invite' => array(
			'tweet' => false,
			'user'  => false,
			'event' => false,
		),
		'session'        => array(
			'event' => false,
			'user'  => true,
		),
	);

	/**
	 * Relate two platform entities.
	 *
	 * @param string $from_type Source entity type.
	 * @param int    $from_id Source entity ID.
	 * @param string $to_type Target entity type.
	 * @param int    $to_id Target entity ID.
	 */
	public static function relate( string $from_type, int $from_id, string $to_type, int $to_id ): void {
		self::validate_rule( $from_type, $to_type );
		self::add_relation( $from_type, $from_id, $to_type, $to_id );

		if ( ! empty( self::$rules[ $from_type ][ $to_type ] ) ) {
			self::add_relation( $to_type, $to_id, $from_type, $from_id );
		}
	}

	/**
	 * Return related entity IDs.
	 *
	 * @param string $from_type Source entity type.
	 * @param int    $from_id Source entity ID.
	 * @param string $to_type Target entity type.
	 * @return list<int>
	 */
	public static function get_related( string $from_type, int $from_id, string $to_type ): array {
		$relationships = self::get_relationships( $from_type, $from_id );

		if ( empty( $relationships[ $to_type ] ) ) {
			return array();
		}

		return array_map( 'intval', $relationships[ $to_type ] );
	}

	/**
	 * Return source entity IDs that point at the target entity.
	 *
	 * @param string $source_type Source entity type to search.
	 * @param string $target_type Target entity type.
	 * @param int    $target_id Target entity ID.
	 * @return list<int>
	 */
	public static function get_referencing( string $source_type, string $target_type, int $target_id ): array {
		$source_ids = self::get_entity_ids( $source_type );

		return array_values(
			array_filter(
				$source_ids,
				static fn( int $source_id ): bool => in_array( $target_id, self::get_related( $source_type, $source_id, $target_type ), true )
			)
		);
	}

	/**
	 * Register relationship meta for all storage object types.
	 */
	public function register_meta(): void {
		$args = array(
			'type'              => 'object',
			'description'       => __( 'WPCamp Hub relationship map keyed by target entity type.', 'wpcamp-hub' ),
			'single'            => true,
			'default'           => array(),
			'sanitize_callback' => array( self::class, 'sanitize_relationships' ),
			'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
			),
		);

		foreach ( Data_Structure::get_post_types() as $post_type => $config ) {
			register_post_meta( $post_type, self::META_KEY, $args );
		}

		register_meta( 'user', self::META_KEY, $args );
	}

	/**
	 * Sanitize a relationship map.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array<string,list<int>>
	 */
	public static function sanitize_relationships( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$relationships = array();
		foreach ( $value as $entity_type => $ids ) {
			if ( ! is_string( $entity_type ) || ! is_array( $ids ) ) {
				continue;
			}

			$relationships[ sanitize_key( $entity_type ) ] = array_values(
				array_unique(
					array_filter(
						array_map( 'absint', $ids )
					)
				)
			);
		}

		return $relationships;
	}

	/**
	 * Add one direct relationship edge.
	 *
	 * @param string $from_type Source entity type.
	 * @param int    $from_id Source entity ID.
	 * @param string $to_type Target entity type.
	 * @param int    $to_id Target entity ID.
	 */
	private static function add_relation( string $from_type, int $from_id, string $to_type, int $to_id ): void {
		$relationships               = self::get_relationships( $from_type, $from_id );
		$relationships[ $to_type ]   = $relationships[ $to_type ] ?? array();
		$relationships[ $to_type ][] = $to_id;
		$relationships[ $to_type ]   = array_values( array_unique( array_map( 'absint', $relationships[ $to_type ] ) ) );

		self::update_relationships( $from_type, $from_id, $relationships );
	}

	/**
	 * Read the full relationship map for an object.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @return array<string,list<int>>
	 */
	private static function get_relationships( string $entity_type, int $entity_id ): array {
		$raw = 'user' === $entity_type
			? get_user_meta( $entity_id, self::META_KEY, true )
			: get_post_meta( $entity_id, self::META_KEY, true );

		return self::sanitize_relationships( $raw );
	}

	/**
	 * Return IDs for a platform entity type.
	 *
	 * @param string $entity_type Entity type.
	 * @return list<int>
	 */
	private static function get_entity_ids( string $entity_type ): array {
		if ( 'user' === $entity_type ) {
			$user_query = new \WP_User_Query(
				array(
					'fields' => 'ID',
					'number' => -1,
				)
			);

			return array_values( array_map( 'intval', $user_query->get_results() ) );
		}

		$post_type = Data_Structure::entity_type_to_post_type( $entity_type );
		if ( '' === $post_type ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		return array_values( array_map( 'intval', $post_ids ) );
	}

	/**
	 * Persist the full relationship map for an object.
	 *
	 * @param string                  $entity_type Entity type.
	 * @param int                     $entity_id Entity ID.
	 * @param array<string,list<int>> $relationships Relationship map.
	 */
	private static function update_relationships( string $entity_type, int $entity_id, array $relationships ): void {
		if ( 'user' === $entity_type ) {
			update_user_meta( $entity_id, self::META_KEY, $relationships );
			return;
		}

		update_post_meta( $entity_id, self::META_KEY, $relationships );
	}

	/**
	 * Validate a relationship rule before persistence.
	 *
	 * @param string $from_type Source entity type.
	 * @param string $to_type Target entity type.
	 */
	private static function validate_rule( string $from_type, string $to_type ): void {
		if ( ! isset( self::$rules[ $from_type ][ $to_type ] ) ) {
			throw new \InvalidArgumentException( esc_html( "Unsupported relationship: {$from_type} -> {$to_type}" ) );
		}
	}
}
