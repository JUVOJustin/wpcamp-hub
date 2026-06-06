<?php
/**
 * Imports WordCamp sessions and speakers into the hub data model.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Import;

use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Session;
use WPCAMP_HUB\Data\User_Profile;

/**
 * Turns a WordCamp event's REST collections into hub entities.
 *
 * Speakers become attendee {@see User_Profile} records and sessions become
 * `wpcamp_session` posts assigned to the originating event. Every import is
 * idempotent: speakers and sessions are matched back to previously imported
 * records by a stable source identifier (`<host>:<id>`), so repeated daily runs
 * update rather than duplicate.
 */
class WordCamp_Importer {

	/**
	 * Source key written to imported sessions and events.
	 */
	public const SOURCE = 'wordcamp';

	/**
	 * Event being imported into.
	 *
	 * @var Event
	 */
	private Event $event;

	/**
	 * REST client for the event's WordCamp site.
	 *
	 * @var WordCamp_Client
	 */
	private WordCamp_Client $client;

	/**
	 * Build an importer for an event.
	 *
	 * @param Event                $event Target event (must expose a WordCamp API URL).
	 * @param WordCamp_Client|null $client Optional pre-built client (injected in tests).
	 * @throws \InvalidArgumentException When the event has no WordCamp API URL.
	 */
	public function __construct( Event $event, ?WordCamp_Client $client = null ) {
		$api_url = $event->get_wordcamp_api_url();
		if ( null === $client && '' === $api_url ) {
			throw new \InvalidArgumentException( esc_html( 'Event has no WordCamp API URL configured.' ) );
		}

		$this->event  = $event;
		$this->client = $client ?? new WordCamp_Client( $api_url );
	}

	/**
	 * REST client used by this importer.
	 */
	public function get_client(): WordCamp_Client {
		return $this->client;
	}

	/**
	 * Import a single page of speakers.
	 *
	 * @param int $page 1-based page number.
	 * @return WordCamp_Page The fetched page (so callers can drive pagination).
	 */
	public function import_speakers_page( int $page = 1 ): WordCamp_Page {
		$result = $this->client->get_speakers( $page );

		foreach ( $result->items as $speaker ) {
			$this->upsert_speaker( $speaker );
		}

		return $result;
	}

	/**
	 * Import a single page of sessions.
	 *
	 * @param int $page 1-based page number.
	 * @return WordCamp_Page The fetched page (so callers can drive pagination).
	 */
	public function import_sessions_page( int $page = 1 ): WordCamp_Page {
		$result = $this->client->get_sessions( $page );

		foreach ( $result->items as $session ) {
			$this->upsert_session( $session );
		}

		return $result;
	}

	/**
	 * Create or update an attendee profile for a WordCamp speaker.
	 *
	 * @param array<string,mixed> $speaker Decoded speaker item.
	 * @return User_Profile|null The attendee profile, or null when the item is unusable.
	 */
	public function upsert_speaker( array $speaker ): ?User_Profile {
		$source_id = $this->source_id( $speaker );
		$name      = $this->rendered( $speaker['title'] ?? '' );
		if ( '' === $source_id || '' === $name ) {
			return null;
		}

		$wporg_username = $this->wporg_username( $speaker );

		// Converge on the same identity the attendee importer uses: match an
		// existing profile by this camp's speaker source ID, then by the shared
		// WordPress.org username, so a person imported as both a speaker and an
		// attendee resolves to a single user.
		$profile = $this->find_attendee( $source_id, $wporg_username );
		if ( null === $profile ) {
			$profile = User_Profile::create_attendee( $this->speaker_identifier( $speaker, $wporg_username, $source_id ), $name );
		} else {
			wp_update_user(
				array(
					'ID'           => $profile->get_id(),
					'display_name' => $name,
					'nickname'     => $name,
				)
			);
		}

		// Record this camp's speaker source ID so the speaker importer can find
		// the profile again on the next run.
		update_user_meta( $profile->get_id(), 'wpcamp_wordcamp_speaker', $source_id );

		$this->apply_speaker_meta( $profile->get_id(), $speaker );

		return $profile;
	}

	/**
	 * Create or update a hub session from a WordCamp session item.
	 *
	 * @param array<string,mixed> $data Decoded session item.
	 * @return Session|null The session wrapper, or null when the item is unusable.
	 */
	public function upsert_session( array $data ): ?Session {
		// Only timetable sessions are imported — skip breaks, lunches, etc.
		$type = isset( $data['meta']['_wcpt_session_type'] ) ? (string) $data['meta']['_wcpt_session_type'] : 'session';
		if ( 'session' !== $type ) {
			return null;
		}

		$source_id = $this->source_id( $data );
		$title     = $this->rendered( $data['title'] ?? '' );
		if ( '' === $source_id || '' === $title ) {
			return null;
		}

		$post_data = array(
			'post_type'    => Data_Structure::POST_TYPE_SESSION,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $this->rendered_html( $data['content'] ?? '' ),
			'post_excerpt' => $this->rendered( $data['excerpt'] ?? '' ),
		);

		$existing = $this->find_session( $source_id );
		if ( null !== $existing ) {
			$post_data['ID'] = $existing->get_id();
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return null;
		}
		$post_id = (int) $post_id;

		$this->apply_session_meta( $post_id, $source_id, $data );
		$this->assign_track( $post_id, $data );

		$session = Session::from( $post_id );
		$this->assign_to_event( $session );
		$this->assign_speakers( $session, $data );

		return $session;
	}

	/**
	 * Persist scalar session meta from a WordCamp item.
	 *
	 * @param int                 $post_id Session post ID.
	 * @param string              $source_id Stable source identifier.
	 * @param array<string,mixed> $data Decoded session item.
	 */
	private function apply_session_meta( int $post_id, string $source_id, array $data ): void {
		update_post_meta( $post_id, 'wpcamp_source', self::SOURCE );
		update_post_meta( $post_id, 'wpcamp_source_id', $source_id );

		if ( isset( $data['link'] ) && is_string( $data['link'] ) ) {
			update_post_meta( $post_id, 'wpcamp_official_url', esc_url_raw( $data['link'] ) );
		}

		$start = isset( $data['meta']['_wcpt_session_time'] ) ? (int) $data['meta']['_wcpt_session_time'] : 0;
		if ( $start > 0 ) {
			$duration = isset( $data['meta']['_wcpt_session_duration'] ) ? (int) $data['meta']['_wcpt_session_duration'] : 0;
			update_post_meta( $post_id, 'wpcamp_start_time', $this->to_iso8601( $start ) );
			if ( $duration > 0 ) {
				update_post_meta( $post_id, 'wpcamp_end_time', $this->to_iso8601( $start + $duration ) );
			}
		}
	}

	/**
	 * Persist avatar / profile meta for an imported speaker.
	 *
	 * @param int                 $user_id Attendee user ID.
	 * @param array<string,mixed> $speaker Decoded speaker item.
	 */
	private function apply_speaker_meta( int $user_id, array $speaker ): void {
		if ( isset( $speaker['link'] ) && is_string( $speaker['link'] ) ) {
			update_user_meta( $user_id, 'wpcamp_wporg_profile_url', esc_url_raw( $speaker['link'] ) );
		}

		$username = $this->wporg_username( $speaker );
		if ( '' !== $username ) {
			update_user_meta( $user_id, 'wpcamp_wporg_username', $username );
		}

		$avatar = $this->largest_avatar( $speaker );
		if ( '' !== $avatar ) {
			update_user_meta( $user_id, 'wpcamp_avatar', esc_url_raw( $avatar ) );
		}
	}

	/**
	 * Assign the session to its WordCamp track, creating the term when needed.
	 *
	 * @param int                 $post_id Session post ID.
	 * @param array<string,mixed> $data Decoded session item.
	 */
	private function assign_track( int $post_id, array $data ): void {
		$track_name = '';

		// The embedded term carries the human label. WordCamp reports the track
		// under its internal `wcb_track` taxonomy (the `session_track` REST field
		// is just an alias), so accept either name.
		$track_taxonomies = array( 'wcb_track', 'session_track' );
		if ( isset( $data['_embedded']['wp:term'] ) && is_array( $data['_embedded']['wp:term'] ) ) {
			foreach ( $data['_embedded']['wp:term'] as $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}
				foreach ( $group as $term ) {
					if ( is_array( $term ) && isset( $term['taxonomy'], $term['name'] ) && in_array( $term['taxonomy'], $track_taxonomies, true ) ) {
						$track_name = (string) $term['name'];
						break 2;
					}
				}
			}
		}

		if ( '' === $track_name ) {
			return;
		}

		$existing = term_exists( $track_name, Data_Structure::TAXONOMY_TRACK );
		if ( ! is_array( $existing ) ) {
			$existing = wp_insert_term( $track_name, Data_Structure::TAXONOMY_TRACK );
		}

		if ( is_array( $existing ) ) {
			wp_set_object_terms( $post_id, (int) $existing['term_id'], Data_Structure::TAXONOMY_TRACK );
		}
	}

	/**
	 * Relate the session to its originating event (idempotently).
	 *
	 * @param Session $session Imported session.
	 */
	private function assign_to_event( Session $session ): void {
		if ( ! in_array( $this->event->get_id(), $session->get_related( 'event' ), true ) ) {
			$session->relate_to( 'event', $this->event->get_id() );
		}
		update_post_meta( $session->get_id(), 'wpcamp_event', $this->event->get_id() );
	}

	/**
	 * Relate the session to its speaker attendee profiles.
	 *
	 * Speakers are matched to attendees previously imported by source ID. Any
	 * speaker not yet imported is fetched and created on demand so a session
	 * page processed before its speakers still links correctly.
	 *
	 * @param Session             $session Imported session.
	 * @param array<string,mixed> $data Decoded session item.
	 */
	private function assign_speakers( Session $session, array $data ): void {
		$speakers = isset( $data['session_speakers'] ) && is_array( $data['session_speakers'] ) ? $data['session_speakers'] : array();

		$related = $session->get_related( 'user' );

		foreach ( $speakers as $speaker ) {
			if ( ! is_array( $speaker ) || ! isset( $speaker['id'] ) ) {
				continue;
			}

			$speaker_post_id = (int) $speaker['id'];
			$source_id       = $this->client->get_host() . ':' . $speaker_post_id;
			$profile         = $this->find_attendee( $source_id, '' );

			if ( null === $profile ) {
				$fetched = $this->client->get_speaker( $speaker_post_id );
				$profile = is_array( $fetched ) ? $this->upsert_speaker( $fetched ) : null;
			}

			if ( null !== $profile && ! in_array( $profile->get_id(), $related, true ) ) {
				$session->relate_to( 'user', $profile->get_id() );
				$related[] = $profile->get_id();
			}
		}
	}

	/**
	 * Find an existing attendee/speaker profile by a stable identifier.
	 *
	 * Matching is resilient and shared with the attendee importer: first this
	 * camp's speaker source ID (`wpcamp_wordcamp_speaker`), then the WordPress.org
	 * username (`wpcamp_wporg_username` meta or the matching `user_login`). The
	 * username keys let a speaker and the same person imported as an attendee
	 * converge to one user.
	 *
	 * @param string $source_id Speaker source identifier (`<host>:<id>`).
	 * @param string $wporg_username WordPress.org username (may be empty).
	 * @return User_Profile|null
	 */
	private function find_attendee( string $source_id, string $wporg_username ): ?User_Profile {
		$by_source = $this->find_user_by_meta( 'wpcamp_wordcamp_speaker', $source_id );
		if ( null !== $by_source ) {
			return $by_source;
		}

		if ( '' === $wporg_username ) {
			return null;
		}

		$by_username_meta = $this->find_user_by_meta( 'wpcamp_wporg_username', $wporg_username );
		if ( null !== $by_username_meta ) {
			return $by_username_meta;
		}

		$by_login = get_user_by( 'login', sanitize_user( $wporg_username, true ) );

		return false === $by_login ? null : User_Profile::from( (int) $by_login->ID );
	}

	/**
	 * Find a single user by an exact meta key/value, or null.
	 *
	 * @param string $meta_key Meta key.
	 * @param string $meta_value Meta value.
	 * @return User_Profile|null
	 */
	private function find_user_by_meta( string $meta_key, string $meta_value ): ?User_Profile {
		if ( '' === $meta_value ) {
			return null;
		}

		$users = get_users(
			array(
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		$user_id = reset( $users );

		return false === $user_id ? null : User_Profile::from( (int) $user_id );
	}

	/**
	 * Find a previously imported session by source ID.
	 *
	 * @param string $source_id Stable source identifier.
	 * @return Session|null
	 */
	private function find_session( string $source_id ): ?Session {
		$posts = get_posts(
			array(
				'post_type'      => Data_Structure::POST_TYPE_SESSION,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => 'wpcamp_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$post_id = ! empty( $posts ) ? (int) reset( $posts ) : 0;

		return 0 === $post_id ? null : Session::from( $post_id );
	}

	/**
	 * Build the stable source identifier (`<host>:<id>`) for an item.
	 *
	 * @param array<string,mixed> $item Decoded API item.
	 * @return string Source identifier, or '' when the item has no ID.
	 */
	private function source_id( array $item ): string {
		$id = isset( $item['id'] ) ? (int) $item['id'] : 0;

		return $id > 0 ? $this->client->get_host() . ':' . $id : '';
	}

	/**
	 * Build the attendee identifier (→ `user_login`) for a new speaker profile.
	 *
	 * Prefers the WordPress.org username so the login matches the attendee
	 * importer's identifier for the same person; falls back to the speaker slug
	 * (namespaced by host) and finally the source ID.
	 *
	 * @param array<string,mixed> $speaker Decoded speaker item.
	 * @param string              $wporg_username WordPress.org username (may be empty).
	 * @param string              $source_id Source identifier fallback.
	 * @return string
	 */
	private function speaker_identifier( array $speaker, string $wporg_username, string $source_id ): string {
		if ( '' !== $wporg_username ) {
			return $wporg_username;
		}

		$slug = isset( $speaker['slug'] ) ? sanitize_title( (string) $speaker['slug'] ) : '';
		$host = sanitize_title( $this->client->get_host() );

		return '' !== $slug ? "wc-{$host}-{$slug}" : 'wc-' . sanitize_title( str_replace( ':', '-', $source_id ) );
	}

	/**
	 * Extract the WordPress.org username from a speaker item.
	 *
	 * @param array<string,mixed> $speaker Decoded speaker item.
	 * @return string Username, or '' when absent.
	 */
	private function wporg_username( array $speaker ): string {
		$username = isset( $speaker['meta']['_wcpt_user_name'] ) ? (string) $speaker['meta']['_wcpt_user_name'] : '';

		return sanitize_text_field( trim( $username ) );
	}

	/**
	 * Largest available avatar URL from a speaker item.
	 *
	 * @param array<string,mixed> $speaker Decoded speaker item.
	 * @return string
	 */
	private function largest_avatar( array $speaker ): string {
		if ( ! isset( $speaker['avatar_urls'] ) || ! is_array( $speaker['avatar_urls'] ) ) {
			return '';
		}

		$sizes = array_map( 'intval', array_keys( $speaker['avatar_urls'] ) );
		if ( array() === $sizes ) {
			return '';
		}

		$largest = (string) max( $sizes );

		return isset( $speaker['avatar_urls'][ $largest ] ) ? (string) $speaker['avatar_urls'][ $largest ] : '';
	}

	/**
	 * Extract a rendered string from a string or `{rendered: ...}` REST field.
	 *
	 * @param mixed $value REST field value.
	 * @return string Decoded, tag-stripped, trimmed text.
	 */
	private function rendered( mixed $value ): string {
		if ( is_array( $value ) && isset( $value['rendered'] ) ) {
			$value = $value['rendered'];
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5 ) ) );
	}

	/**
	 * Extract a rendered HTML string, sanitized for storage as post content.
	 *
	 * @param mixed $value REST field value.
	 * @return string Sanitized HTML.
	 */
	private function rendered_html( mixed $value ): string {
		if ( is_array( $value ) && isset( $value['rendered'] ) ) {
			$value = $value['rendered'];
		}

		return is_string( $value ) ? trim( wp_kses_post( $value ) ) : '';
	}

	/**
	 * Format a unix timestamp as an ISO 8601 string in the site timezone.
	 *
	 * @param int $timestamp Unix timestamp (UTC).
	 * @return string
	 */
	private function to_iso8601( int $timestamp ): string {
		return wp_date( 'c', $timestamp ) ?: gmdate( 'c', $timestamp );
	}
}
