<?php
/**
 * Test fixture loader for the WPCamp Hub data structure.
 *
 * @package WPCAMP_HUB
 */

use WPCAMP_HUB\Data\Relationships;
use WPCAMP_HUB\Data\User_Profile;

/**
 * Seeds deterministic example data from the fixture dump.
 */
class DataStructureFixture {

	/**
	 * Seed fixture users, posts, meta, and relationships.
	 *
	 * @return array{users:int[],posts:array<string,int>}
	 */
	public static function seed(): array {
		$fixture = self::load_fixture();
		$user_ids = array();
		$post_ids = array();

		foreach ( $fixture['users'] as $user ) {
			$profile    = User_Profile::create_attendee( $user['identifier'], $user['name'] );
			$user_ids[] = $profile->get_id();

			foreach ( $user['meta'] as $meta_key => $value ) {
				update_user_meta( $profile->get_id(), $meta_key, $value );
			}
		}

		foreach ( $fixture['posts'] as $post ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => $post['post_type'],
					'post_title'   => $post['post_title'],
					'post_content' => $post['post_content'],
					'post_status'  => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				throw new RuntimeException( esc_html( $post_id->get_error_message() ) );
			}

			$post_ids[ $post['key'] ] = (int) $post_id;

			foreach ( $post['meta'] as $meta_key => $value ) {
				update_post_meta( (int) $post_id, $meta_key, $value );
			}
		}

		foreach ( $fixture['relationships'] as $relationship ) {
			Relationships::relate(
				$relationship[0],
				self::resolve_reference( $relationship[1], $user_ids, $post_ids ),
				$relationship[2],
				self::resolve_reference( $relationship[3], $user_ids, $post_ids )
			);
		}

		return array(
			'users' => $user_ids,
			'posts' => $post_ids,
		);
	}

	/**
	 * Load the JSON fixture dump.
	 *
	 * @return array<string,mixed>
	 */
	private static function load_fixture(): array {
		$contents = file_get_contents( dirname( __DIR__ ) . '/fixtures/data-structure.json' );
		if ( false === $contents ) {
			throw new RuntimeException( 'Unable to load data structure fixture.' );
		}

		$decoded = json_decode( $contents, true );
		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( 'Invalid data structure fixture.' );
		}

		return $decoded;
	}

	/**
	 * Resolve a fixture reference into a WordPress object ID.
	 *
	 * @param string            $reference Fixture reference.
	 * @param int[]             $user_ids Seeded user IDs.
	 * @param array<string,int> $post_ids Seeded post IDs.
	 */
	private static function resolve_reference( string $reference, array $user_ids, array $post_ids ): int {
		if ( 0 === strpos( $reference, 'user:' ) ) {
			return $user_ids[ (int) substr( $reference, 5 ) ];
		}

		if ( 0 === strpos( $reference, 'post:' ) ) {
			return $post_ids[ substr( $reference, 5 ) ];
		}

		return absint( $reference );
	}
}
