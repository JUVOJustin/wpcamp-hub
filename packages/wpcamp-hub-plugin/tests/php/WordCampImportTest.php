<?php
/**
 * End-to-end tests for the WordCamp session/speaker import.
 *
 * Exercises the full pipeline against a mocked WordCamp REST API: paginated
 * client fetches, idempotent speaker/session upserts, event assignment, track
 * mapping, and the Action Scheduler fan-out.
 *
 * @package WPCAMP_HUB
 */

use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Session;
use WPCAMP_HUB\Data\User_Profile;
use WPCAMP_HUB\Import\Import_Scheduler;
use WPCAMP_HUB\Import\WordCamp_Client;
use WPCAMP_HUB\Import\WordCamp_Importer;

/**
 * Verifies the WordCamp import imports, assigns, and de-duplicates correctly.
 */
class WordCampImportTest extends WP_UnitTestCase {

	private const API_URL = 'https://europe.wordcamp.org/2026/wp-json/wp/v2/';
	private const HOST     = 'europe.wordcamp.org';

	/**
	 * Canned HTTP responses keyed by a "<resource>:<page>" lookup.
	 *
	 * @var array<string,array{body:string,total:int,pages:int}>
	 */
	private array $responses = array();

	/**
	 * Set up the data model and the HTTP short-circuit.
	 */
	public function set_up(): void {
		parent::set_up();

		( new Data_Structure() )->register();

		add_filter( 'pre_http_request', array( $this, 'serve_response' ), 10, 3 );
	}

	/**
	 * Tear down the HTTP short-circuit.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'serve_response' ), 10 );
		$this->responses = array();

		parent::tear_down();
	}

	/**
	 * Short-circuit wp_remote_get with a canned response based on the URL.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt Short-circuit value.
	 * @param array<string,mixed>                 $args Request args.
	 * @param string                              $url Request URL.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function serve_response( $preempt, array $args, string $url ): array|\WP_Error {
		$query    = array();
		$parsed   = wp_parse_url( $url );
		$resource = '';

		if ( isset( $parsed['path'] ) && preg_match( '#/wp/v2/(sessions|speakers)(/(\d+))?$#', $parsed['path'], $m ) ) {
			$resource = $m[1];
			$single   = isset( $m[3] ) ? (int) $m[3] : 0;
		} else {
			return new WP_Error( 'unexpected_url', 'Unexpected request: ' . $url );
		}

		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}

		// Single-resource fetch (used when linking a not-yet-imported speaker).
		if ( ! empty( $single ) ) {
			return $this->http_response( wp_json_encode( $this->find_single( $resource, $single ) ), 1, 1 );
		}

		$page = isset( $query['page'] ) ? (int) $query['page'] : 1;
		$key  = $resource . ':' . $page;

		if ( ! isset( $this->responses[ $key ] ) ) {
			return $this->http_response( '[]', 0, 1 );
		}

		$canned = $this->responses[ $key ];

		return $this->http_response( $canned['body'], $canned['total'], $canned['pages'] );
	}

	/**
	 * The import creates sessions, assigns them to the event, links speakers as
	 * attendees, maps tracks, and walks every page.
	 */
	public function test_full_import_assigns_sessions_speakers_and_tracks(): void {
		$this->queue_two_pages_of_speakers();
		$this->queue_two_pages_of_sessions();

		$event    = $this->make_major_wordcamp();
		$importer = new WordCamp_Importer( $event );

		$this->walk( array( $importer, 'import_speakers_page' ) );
		$this->walk( array( $importer, 'import_sessions_page' ) );

		// Three real sessions imported; the "break" item is skipped.
		$sessions = $event->get_sessions();
		$this->assertCount( 3, $sessions );

		$titles = array_map( static fn( Session $s ): string => get_the_title( $s->get_id() ), $sessions );
		sort( $titles );
		$this->assertSame( array( 'Building Blocks', 'Lightning Intro', 'Scaling WordPress' ), $titles );

		// Every imported session is related back to the event.
		foreach ( $sessions as $session ) {
			$this->assertContains( $event->get_id(), $session->get_related( 'event' ), 'session not linked to event' );
			$this->assertSame( WordCamp_Importer::SOURCE, get_post_meta( $session->get_id(), 'wpcamp_source', true ) );
		}

		// Speakers were created as attendee profiles and linked to sessions.
		$blocks = $this->session_by_title( $sessions, 'Building Blocks' );
		$names  = $blocks->get_speaker_names();
		sort( $names );
		$this->assertSame( array( 'Ada Lovelace', 'Grace Hopper' ), $names );

		// Track term was created and assigned.
		$track = $blocks->get_track();
		$this->assertNotNull( $track );
		$this->assertSame( 'Developer', get_term( $track->get_id() )->name );

		// Start/end times derived from session_time + duration.
		$this->assertNotEmpty( $blocks->get_start_time() );
		$this->assertNotEmpty( $blocks->get_end_time() );
	}

	/**
	 * Re-running the import updates existing records instead of duplicating.
	 */
	public function test_import_is_idempotent(): void {
		$this->queue_two_pages_of_speakers();
		$this->queue_two_pages_of_sessions();

		$event    = $this->make_major_wordcamp();
		$importer = new WordCamp_Importer( $event );

		$import = function () use ( $importer ): void {
			$this->walk( array( $importer, 'import_speakers_page' ) );
			$this->walk( array( $importer, 'import_sessions_page' ) );
		};

		$import();
		$first_sessions = count( $event->get_sessions() );
		$first_users    = count( get_users( array( 'fields' => 'ID' ) ) );

		$import();
		$this->assertSame( $first_sessions, count( $event->get_sessions() ), 'sessions duplicated on re-run' );
		$this->assertSame( $first_users, count( get_users( array( 'fields' => 'ID' ) ) ), 'speakers duplicated on re-run' );
	}

	/**
	 * Re-importing a speaker updates the existing attendee profile fields.
	 */
	public function test_speaker_profile_is_upserted_on_rerun(): void {
		$event    = $this->make_major_wordcamp();
		$importer = new WordCamp_Importer( $event );

		$this->responses['speakers:1'] = array(
			'body'  => wp_json_encode( array( $this->speaker( 6886, 'grace-hopper', 'Grace Hopper' ) ) ),
			'total' => 1,
			'pages' => 1,
		);

		$importer->import_speakers_page( 1 );

		$user_ids = get_users(
			array(
				'fields'     => 'ID',
				'meta_key'   => 'wpcamp_wordcamp_speaker', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => self::HOST . ':6886', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$this->assertCount( 1, $user_ids );
		$user_id = (int) reset( $user_ids );

		$this->responses['speakers:1'] = array(
			'body'  => wp_json_encode( array( $this->speaker( 6886, 'grace-hopper-updated', 'Grace Hopper Updated' ) ) ),
			'total' => 1,
			'pages' => 1,
		);

		$importer->import_speakers_page( 1 );

		$this->assertSame( 1, count( get_users( array( 'meta_key' => 'wpcamp_wordcamp_speaker' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$this->assertSame( 'Grace Hopper Updated', get_userdata( $user_id )->display_name );
		$this->assertSame( 'Grace Hopper Updated', get_user_meta( $user_id, 'nickname', true ) );
		$this->assertSame( 'https://' . self::HOST . '/2026/speaker/grace-hopper-updated/', get_user_meta( $user_id, 'wpcamp_wporg_profile_url', true ) );
		$this->assertSame( 'https://example.test/grace-hopper-updated-96.jpg', get_user_meta( $user_id, 'wpcamp_avatar', true ) );
	}

	/**
	 * A speaker who already exists as an attendee (same WordPress.org username)
	 * converges to one user instead of creating a duplicate. This keeps the
	 * speaker importer compatible with the attendee importer's identity model.
	 */
	public function test_speaker_converges_with_existing_attendee_by_wporg_username(): void {
		// Simulate the attendee importer having already created this person,
		// keyed by their WordPress.org username (its `create_attendee` identifier).
		$existing = User_Profile::create_attendee( 'graceh', 'Grace H.' );
		update_user_meta( $existing->get_id(), 'wpcamp_wporg_username', 'graceh' );

		$before = count( get_users( array( 'fields' => 'ID' ) ) );

		$event    = $this->make_major_wordcamp();
		$importer = new WordCamp_Importer( $event );

		// Speaker page 1 carries Grace with the same _wcpt_user_name = graceh.
		$this->responses['speakers:1'] = array(
			'body'  => wp_json_encode(
				array(
					array(
						'id'    => 6886,
						'slug'  => 'grace-hopper',
						'link'  => 'https://' . self::HOST . '/2026/speaker/grace-hopper/',
						'title' => array( 'rendered' => 'Grace Hopper' ),
						'meta'  => array( '_wcpt_user_name' => 'graceh' ),
					),
				)
			),
			'total' => 1,
			'pages' => 1,
		);

		$importer->import_speakers_page( 1 );

		$this->assertSame( $before, count( get_users( array( 'fields' => 'ID' ) ) ), 'speaker should reuse the existing attendee' );
		// The speaker source ID is now also recorded on that same user.
		$this->assertSame(
			self::HOST . ':6886',
			get_user_meta( $existing->get_id(), 'wpcamp_wordcamp_speaker', true )
		);
		// Display name updated from the richer speaker record.
		$this->assertSame( 'Grace Hopper', get_userdata( $existing->get_id() )->display_name );
	}

	/**
	 * The client reads pagination metadata from the response headers.
	 */
	public function test_client_reports_pagination_from_headers(): void {
		$this->queue_two_pages_of_sessions();

		$client = new WordCamp_Client( self::API_URL );

		$page_one = $client->get_sessions( 1 );
		$this->assertSame( 1, $page_one->page );
		$this->assertSame( 2, $page_one->total_pages );
		$this->assertTrue( $page_one->has_more() );
		$this->assertSame( 2, $page_one->next_page() );

		$page_two = $client->get_sessions( 2 );
		$this->assertFalse( $page_two->has_more() );
		$this->assertNull( $page_two->next_page() );
	}

	/**
	 * The client normalizes assorted URL shapes to the wp/v2 API root.
	 */
	public function test_client_normalizes_base_url(): void {
		$expected = self::API_URL;

		$this->assertSame( $expected, ( new WordCamp_Client( 'https://europe.wordcamp.org/2026' ) )->get_base_url() );
		$this->assertSame( $expected, ( new WordCamp_Client( 'https://europe.wordcamp.org/2026/' ) )->get_base_url() );
		$this->assertSame( $expected, ( new WordCamp_Client( 'https://europe.wordcamp.org/2026/wp-json' ) )->get_base_url() );
		$this->assertSame( $expected, ( new WordCamp_Client( self::API_URL . 'sessions?per_page=10' ) )->get_base_url() );
		$this->assertSame( self::HOST, ( new WordCamp_Client( self::API_URL ) )->get_host() );
	}

	/**
	 * The master job fans out independent speaker AND session imports per
	 * flagged event only — the two resources do not chain into each other, so
	 * they can run alongside the attendee import as a 3-way fan-out.
	 */
	public function test_master_fans_out_independent_jobs_per_flagged_event(): void {
		$major   = $this->make_major_wordcamp();
		$curated = self::factory()->post->create( array( 'post_type' => Data_Structure::POST_TYPE_EVENT ) );

		$enqueued = $this->capture_enqueued( static fn() => ( new Import_Scheduler() )->fan_out() );

		// One speakers job + one sessions job for the flagged camp; nothing for
		// the curated (unflagged) event.
		$this->assertCount( 2, $enqueued, 'flagged camp should fan out exactly two jobs' );

		$hooks = array_column( $enqueued, 'hook' );
		sort( $hooks );
		$this->assertSame(
			array( Import_Scheduler::AS_SESSIONS_HOOK, Import_Scheduler::AS_SPEAKERS_HOOK ),
			$hooks
		);

		foreach ( $enqueued as $job ) {
			$this->assertSame( array( $major->get_id(), 1 ), $job['args'], 'positional [event_id, page] args' );
		}
		$this->assertNotSame( $curated, $major->get_id() );
	}

	/**
	 * The per-page jobs import data and self-reschedule page-by-page, exactly as
	 * Action Scheduler invokes them (positional args). Speakers and sessions are
	 * independent — neither chains into the other.
	 */
	public function test_page_jobs_import_data_and_self_reschedule(): void {
		$this->queue_two_pages_of_speakers();
		$this->queue_two_pages_of_sessions();

		$event     = $this->make_major_wordcamp();
		$scheduler = new Import_Scheduler();

		$enqueued = $this->capture_enqueued(
			static function () use ( $scheduler, $event ): void {
				// Action Scheduler invokes callbacks with array_values() of the
				// stored args, i.e. positionally — mirror that here.
				$scheduler->import_speakers( $event->get_id(), 1 ); // page 1 → reschedules page 2.
				$scheduler->import_speakers( $event->get_id(), 2 ); // last page → no chaining.
				$scheduler->import_sessions( $event->get_id(), 1 ); // page 1 → reschedules page 2.
				$scheduler->import_sessions( $event->get_id(), 2 ); // last page → no chaining.
			}
		);

		// Only the two "next page" jobs are enqueued; speakers never enqueue a
		// session job and vice versa.
		$this->assertSame(
			array(
				array( Import_Scheduler::AS_SPEAKERS_HOOK, array( $event->get_id(), 2 ) ),
				array( Import_Scheduler::AS_SESSIONS_HOOK, array( $event->get_id(), 2 ) ),
			),
			array_map( static fn( array $j ): array => array( $j['hook'], $j['args'] ), $enqueued )
		);

		// Data actually landed and is linked to the event.
		$this->assertCount( 3, $event->get_sessions() );
		$this->assertCount( 2, get_users( array( 'meta_key' => 'wpcamp_wordcamp_speaker' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}

	/**
	 * A non-importable event ID is ignored by the page jobs.
	 */
	public function test_page_jobs_ignore_unflagged_events(): void {
		$plain = self::factory()->post->create( array( 'post_type' => Data_Structure::POST_TYPE_EVENT ) );

		$enqueued = $this->capture_enqueued(
			static fn() => ( new Import_Scheduler() )->import_speakers( $plain, 1 )
		);

		$this->assertCount( 0, $enqueued, 'unflagged event must not schedule work' );
	}

	/**
	 * schedule_daily_import() installs exactly one recurring daily master job.
	 */
	public function test_schedule_daily_import_registers_recurring_job_once(): void {
		$scheduler = new Import_Scheduler();

		$scheduler->schedule_daily_import();
		$scheduler->schedule_daily_import();

		$this->assertTrue( as_has_scheduled_action( Import_Scheduler::AS_HOOK, array(), Import_Scheduler::GROUP ) );

		$actions = as_get_scheduled_actions(
			array(
				'hook'   => Import_Scheduler::AS_HOOK,
				'group'  => Import_Scheduler::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertCount( 1, $actions, 'duplicate recurring jobs scheduled' );

		Import_Scheduler::unschedule_daily_import();
		$this->assertFalse( as_has_scheduled_action( Import_Scheduler::AS_HOOK, array(), Import_Scheduler::GROUP ) );
	}

	/**
	 * The new event/session/user meta fields are registered.
	 */
	public function test_import_meta_fields_are_registered(): void {
		$event_meta = get_registered_meta_keys( 'post', Data_Structure::POST_TYPE_EVENT );
		$this->assertArrayHasKey( 'wpcamp_is_major_wordcamp', $event_meta );
		$this->assertArrayHasKey( 'wpcamp_wordcamp_api_url', $event_meta );

		$session_meta = get_registered_meta_keys( 'post', Data_Structure::POST_TYPE_SESSION );
		$this->assertArrayHasKey( 'wpcamp_source_id', $session_meta );

		$user_meta = get_registered_meta_keys( 'user' );
		$this->assertArrayHasKey( 'wpcamp_wordcamp_speaker', $user_meta );
	}

	/**
	 * Run a callback while capturing every Action Scheduler async enqueue.
	 *
	 * @param callable $action Code that triggers enqueues.
	 * @return list<array{hook:string,args:array<int,mixed>}> Captured jobs in order.
	 */
	private function capture_enqueued( callable $action ): array {
		$enqueued = array();
		$capture  = static function ( $pre, $hook = '', $args = array() ) use ( &$enqueued ) {
			$enqueued[] = array(
				'hook' => $hook,
				'args' => $args,
			);
			return 1; // Short-circuit Action Scheduler with a fake action ID.
		};

		add_filter( 'pre_as_enqueue_async_action', $capture, 10, 3 );
		try {
			$action();
		} finally {
			remove_filter( 'pre_as_enqueue_async_action', $capture, 10 );
		}

		return $enqueued;
	}

	/**
	 * Create a flagged, API-configured WordCamp event.
	 */
	private function make_major_wordcamp(): Event {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => Data_Structure::POST_TYPE_EVENT,
				'post_title' => 'WordCamp Europe 2026',
			)
		);

		update_post_meta( $post_id, 'wpcamp_is_major_wordcamp', true );
		update_post_meta( $post_id, 'wpcamp_wordcamp_api_url', self::API_URL );

		return Event::from( $post_id );
	}

	/**
	 * Walk a paginated importer callback until it reports no further pages.
	 *
	 * @param callable $page_importer Callback taking a page number, returning a WordCamp_Page.
	 */
	private function walk( callable $page_importer ): void {
		$page = 1;
		do {
			$result = $page_importer( $page );
			$page   = $result->next_page();
		} while ( null !== $page );
	}

	/**
	 * Find a session wrapper in a list by its post title.
	 *
	 * @param list<Session> $sessions Session wrappers.
	 * @param string        $title Target title.
	 */
	private function session_by_title( array $sessions, string $title ): Session {
		foreach ( $sessions as $session ) {
			if ( $title === get_the_title( $session->get_id() ) ) {
				return $session;
			}
		}

		$this->fail( "Session not found: {$title}" );
	}

	/**
	 * Build a wp_remote_get-style response array.
	 *
	 * @param string $body Response body.
	 * @param int    $total Total item count header.
	 * @param int    $pages Total page count header.
	 * @return array<string,mixed>
	 */
	private function http_response( string $body, int $total, int $pages ): array {
		return array(
			'headers'  => array(
				'x-wp-total'      => (string) $total,
				'x-wp-totalpages' => (string) $pages,
			),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/**
	 * Look up a single item from the queued page fixtures by source ID.
	 *
	 * @param string $resource Resource (sessions|speakers).
	 * @param int    $id Source post ID.
	 * @return array<string,mixed>
	 */
	private function find_single( string $resource, int $id ): array {
		foreach ( $this->responses as $key => $canned ) {
			if ( 0 !== strpos( $key, $resource . ':' ) ) {
				continue;
			}
			foreach ( (array) json_decode( $canned['body'], true ) as $item ) {
				if ( is_array( $item ) && (int) ( $item['id'] ?? 0 ) === $id ) {
					return $item;
				}
			}
		}

		return array();
	}

	/**
	 * Queue two pages of speakers (one per page).
	 */
	private function queue_two_pages_of_speakers(): void {
		$this->responses['speakers:1'] = array(
			'body'  => wp_json_encode( array( $this->speaker( 6886, 'grace-hopper', 'Grace Hopper' ) ) ),
			'total' => 2,
			'pages' => 2,
		);
		$this->responses['speakers:2'] = array(
			'body'  => wp_json_encode( array( $this->speaker( 6887, 'ada-lovelace', 'Ada Lovelace' ) ) ),
			'total' => 2,
			'pages' => 2,
		);
	}

	/**
	 * Queue two pages of sessions (two then two, one of which is a break).
	 */
	private function queue_two_pages_of_sessions(): void {
		$this->responses['sessions:1'] = array(
			'body'  => wp_json_encode(
				array(
					$this->session( 11298, 'Lightning Intro', 'Track 1', 1780747800, 600, array( 6886 ) ),
					$this->session( 11299, 'Building Blocks', 'Developer', 1780751400, 2400, array( 6886, 6887 ) ),
				)
			),
			'total' => 3,
			'pages' => 2,
		);
		$this->responses['sessions:2'] = array(
			'body'  => wp_json_encode(
				array(
					$this->session( 11300, 'Scaling WordPress', 'Track 1', 1780758600, 2400, array( 6887 ) ),
					$this->break_item( 11301, 'Coffee Break' ),
				)
			),
			'total' => 3,
			'pages' => 2,
		);
	}

	/**
	 * Build a speaker fixture item.
	 *
	 * @param int    $id Speaker post ID.
	 * @param string $slug Speaker slug.
	 * @param string $name Display name.
	 * @return array<string,mixed>
	 */
	private function speaker( int $id, string $slug, string $name ): array {
		return array(
			'id'          => $id,
			'slug'        => $slug,
			'link'        => 'https://' . self::HOST . '/2026/speaker/' . $slug . '/',
			'title'       => array( 'rendered' => $name ),
			'content'     => array( 'rendered' => '<p>Bio of ' . $name . '</p>' ),
			'avatar_urls' => array(
				'24' => 'https://example.test/' . $slug . '-24.jpg',
				'96' => 'https://example.test/' . $slug . '-96.jpg',
			),
			'meta'        => array( '_wcpt_user_name' => $slug ),
		);
	}

	/**
	 * Build a session fixture item.
	 *
	 * @param int        $id Session post ID.
	 * @param string     $title Session title.
	 * @param string     $track Track term name.
	 * @param int        $time Session start unix timestamp.
	 * @param int        $duration Duration in seconds.
	 * @param list<int>  $speaker_ids Speaker post IDs.
	 * @return array<string,mixed>
	 */
	private function session( int $id, string $title, string $track, int $time, int $duration, array $speaker_ids ): array {
		return array(
			'id'               => $id,
			'slug'             => sanitize_title( $title ),
			'link'             => 'https://' . self::HOST . '/2026/session/' . sanitize_title( $title ) . '/',
			'title'            => array( 'rendered' => $title ),
			'content'          => array( 'rendered' => '<p>About ' . $title . '</p>' ),
			'excerpt'          => array( 'rendered' => 'About ' . $title ),
			'meta'             => array(
				'_wcpt_session_time'     => $time,
				'_wcpt_session_duration' => $duration,
				'_wcpt_session_type'     => 'session',
			),
			'session_speakers' => array_map(
				static fn( int $sid ): array => array( 'id' => (string) $sid ),
				$speaker_ids
			),
			'_embedded'        => array(
				'wp:term' => array(
					// WordCamp reports the track under the internal `wcb_track`
					// taxonomy in the embedded term object.
					array(
						array(
							'taxonomy' => 'wcb_track',
							'name'     => $track,
						),
					),
				),
			),
		);
	}

	/**
	 * Build a non-session timetable item (break) that must be skipped.
	 *
	 * @param int    $id Post ID.
	 * @param string $title Title.
	 * @return array<string,mixed>
	 */
	private function break_item( int $id, string $title ): array {
		return array(
			'id'    => $id,
			'slug'  => sanitize_title( $title ),
			'title' => array( 'rendered' => $title ),
			'meta'  => array( '_wcpt_session_type' => 'custom' ),
		);
	}
}
