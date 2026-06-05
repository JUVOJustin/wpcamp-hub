<?php
/**
 * Tests for the WordCamp attendee importer.
 *
 * @package WPCAMP_HUB
 */

use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Import\WordCamp_Attendee_Importer;

/**
 * Verifies attendee import parsing, enrichment, and scheduling.
 */
class WordCampAttendeeImporterTest extends WP_UnitTestCase {

	private const string ATTENDEES_URL = 'https://europe.wordcamp.org/2026/community/attendees/';
	private const string HASH_WESTGUARD = '2ec060b5c8173af4c535e8aec48bfd09';
	private const string HASH_FORUM_AVATAR = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const string HASH_GRAVATAR_ONLY = 'cccccccccccccccccccccccccccccccc';
	private const string HASH_GERMANY_ONE = '6dde7f578e5530884238e7173f768ae3a890b6d66eb99262a82f2c494a1b67d4';
	private const string HASH_GERMANY_TWO = '4b8cce9a350e185895c9f3f32b13a23b488efa84a5e8879f3de5ae645bf4371d';
	private const string HASH_ASIA_ONE = '08c6b82cdb36b12f124ac0e2e0c60cd1f4aece282e57ce2b040c96299cff2c30';
	private const string HASH_ASIA_TWO = 'a0a2acd90b801ab16eb66c12499a824fbbc9e8ad414aa82acca5f6d1e91f58ca';

	/**
	 * Ensure data structures are registered for isolated tests.
	 */
	public function set_up(): void {
		parent::set_up();

		( new Data_Structure() )->register();
	}

	/**
	 * Cleanup scheduled jobs and HTTP mocks.
	 */
	public function tear_down(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WordCamp_Attendee_Importer::AS_HOOK, array(), WordCamp_Attendee_Importer::AS_GROUP );
			as_unschedule_all_actions( WordCamp_Attendee_Importer::AS_EVENT_HOOK, array(), WordCamp_Attendee_Importer::AS_GROUP );
		}

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'wpcamp_hub_attendee_importer_ai_parsing_profile' );

		parent::tear_down();
	}

	/**
	 * Provide deterministic parsing rules in tests that do not call the real AI Client.
	 *
	 * @param string $item_tag Attendee wrapper tag.
	 * @param string $item_class Optional wrapper class.
	 */
	private function use_parsing_profile( string $item_tag, string $item_class = '' ): void {
		add_filter(
			'wpcamp_hub_attendee_importer_ai_parsing_profile',
			static function () use ( $item_tag, $item_class ): array {
				return array(
					'item_tag'   => $item_tag,
					'item_class' => $item_class,
					'confidence' => 1.0,
				);
			}
		);
	}

	/**
	 * The parser accepts WordPress.org profile URLs and Gravatar avatar URLs as independent identity signals.
	 */
	public function test_extract_identity_urls_filters_directly_for_wporg_and_gravatar_urls(): void {
		$this->use_parsing_profile( 'article', 'person-card' );

		$importer = new WordCamp_Attendee_Importer();
		$signals  = $importer->extract_identity_urls( $this->sample_attendees_html() );

		$this->assertSame(
			array(
				'westguard'    => 'https://profiles.wordpress.org/westguard/',
				'forumuser'    => 'https://profiles.wordpress.org/forumuser/',
				'not-gravatar' => 'https://profiles.wordpress.org/not-gravatar/',
			),
			$signals['wporg_usernames']
		);
		$this->assertSame(
			array(
				self::HASH_WESTGUARD      => 'https://secure.gravatar.com/avatar/' . self::HASH_WESTGUARD . '?s=190&d=blank&r=g',
				self::HASH_FORUM_AVATAR   => 'https://secure.gravatar.com/avatar/' . self::HASH_FORUM_AVATAR . '?s=190',
				self::HASH_GRAVATAR_ONLY  => 'https://secure.gravatar.com/avatar/' . self::HASH_GRAVATAR_ONLY . '?s=190',
			),
			$signals['gravatar_hashes']
		);
		$this->assertCount( 5, $signals['identity_groups'] );
	}

	/**
	 * Enrichment decides which URL identities belong to the same attendee, not markup proximity.
	 */
	public function test_extract_attendees_enriches_and_decouples_url_identities_without_list_markup(): void {
		$this->use_parsing_profile( 'article', 'person-card' );
		add_filter( 'pre_http_request', array( $this, 'mock_profile_http_responses' ), 10, 3 );

		$importer  = new WordCamp_Attendee_Importer();
		$attendees = $importer->extract_attendees( $this->sample_attendees_html() );

		$this->assertCount( 5, $attendees );
		$this->assertSame( 'westguard', $attendees[0]['identifier'] );
		$this->assertSame( 'westguard', $attendees[0]['name'] );
		$this->assertSame( 'westguard', $attendees[0]['wporg_username'] );
		$this->assertSame( '2ec060b5c8173af4c535e8aec48bfd09', $attendees[0]['gravatar_hash'] );
		$this->assertSame( array(), $attendees[0]['wporg_profile'] );
		$this->assertSame( array(), $attendees[0]['gravatar_profile'] );

		$this->assertSame( 'forumuser', $attendees[1]['identifier'] );
		$this->assertSame( 'forumuser', $attendees[1]['name'] );
		$this->assertSame( '', $attendees[1]['gravatar_hash'] );

		$this->assertSame( 'not-gravatar', $attendees[2]['identifier'] );
		$this->assertSame( '', $attendees[2]['gravatar_hash'] );

		$this->assertSame( 'gravatar-aaaaaaaaaaaaaaaaaaaa', $attendees[3]['identifier'] );
		$this->assertSame( '', $attendees[3]['wporg_username'] );
		$this->assertSame( self::HASH_FORUM_AVATAR, $attendees[3]['gravatar_hash'] );

		$this->assertSame( 'gravatar-cccccccccccccccccccc', $attendees[4]['identifier'] );
		$this->assertSame( '', $attendees[4]['wporg_username'] );
		$this->assertSame( 'cccccccccccccccccccccccccccccccc', $attendees[4]['gravatar_hash'] );
	}

	/**
	 * Germany 2023 uses Camptix attendee markup with 64-character Gravatar hashes.
	 */
	public function test_extract_identity_urls_supports_germany_2023_camptix_markup(): void {
		$this->use_parsing_profile( 'li' );

		$importer = new WordCamp_Attendee_Importer();
		$signals  = $importer->extract_identity_urls( $this->germany_2023_attendees_html() );

		$this->assertSame( array(), $signals['wporg_usernames'] );
		$this->assertSame(
			array(
				self::HASH_GERMANY_ONE => 'https://secure.gravatar.com/avatar/' . self::HASH_GERMANY_ONE . '?s=96&d=mm&r=g',
				self::HASH_GERMANY_TWO => 'https://secure.gravatar.com/avatar/' . self::HASH_GERMANY_TWO . '?s=96&d=mm&r=g',
			),
			$signals['gravatar_hashes']
		);
		$this->assertSame(
			array(
				array(
					'wporg_usernames' => array(),
					'gravatar_hashes' => array( self::HASH_GERMANY_ONE ),
					'website_urls'    => array(),
					'twitter_urls'    => array( 'http://twitter.com/Schlessera' ),
				),
				array(
					'wporg_usernames' => array(),
					'gravatar_hashes' => array( self::HASH_GERMANY_TWO ),
					'website_urls'    => array( 'https://example.com/' ),
					'twitter_urls'    => array(),
				),
			),
			$signals['identity_groups']
		);
	}

	/**
	 * Germany 2023 row context is carried into the resolved attendee payload.
	 */
	public function test_extract_attendees_uses_germany_2023_li_context_for_profile_links(): void {
		$this->use_parsing_profile( 'li' );
		add_filter( 'pre_http_request', array( $this, 'mock_profile_http_responses' ), 10, 3 );

		$importer  = new WordCamp_Attendee_Importer();
		$attendees = $importer->extract_attendees( $this->germany_2023_attendees_html() );

		$this->assertCount( 2, $attendees );
		$this->assertSame( self::HASH_GERMANY_ONE, $attendees[0]['gravatar_hash'] );
		$this->assertSame( array( 'http://twitter.com/Schlessera' ), $attendees[0]['social_links'] );
		$this->assertSame( '', $attendees[0]['website_url'] );
		$this->assertSame( self::HASH_GERMANY_TWO, $attendees[1]['gravatar_hash'] );
		$this->assertSame( array(), $attendees[1]['social_links'] );
		$this->assertSame( 'https://example.com/', $attendees[1]['website_url'] );
	}

	/**
	 * Asia 2026 has Camptix rows with Gravatar, Twitter, and website URLs together.
	 */
	public function test_extract_attendees_uses_asia_2026_li_context_for_twitter_and_website(): void {
		$this->use_parsing_profile( 'li' );
		add_filter( 'pre_http_request', array( $this, 'mock_profile_http_responses' ), 10, 3 );

		$importer  = new WordCamp_Attendee_Importer();
		$attendees = $importer->extract_attendees( $this->asia_2026_attendees_html() );

		$this->assertCount( 2, $attendees );
		$this->assertSame( self::HASH_ASIA_ONE, $attendees[0]['gravatar_hash'] );
		$this->assertSame( array(), $attendees[0]['social_links'] );
		$this->assertSame( 'https://aryanjalan.com', $attendees[0]['website_url'] );
		$this->assertSame( self::HASH_ASIA_TWO, $attendees[1]['gravatar_hash'] );
		$this->assertSame( array( 'http://twitter.com/wpwebinfotech' ), $attendees[1]['social_links'] );
		$this->assertSame( 'https://wpwebinfotech.com/', $attendees[1]['website_url'] );
	}

	/**
	 * AI-discovered wrapper profiles can group non-list attendee rows without mapping attendee data.
	 */
	public function test_extract_attendees_uses_ai_profile_for_non_list_attendee_wrappers(): void {
		add_filter(
			'wpcamp_hub_attendee_importer_ai_parsing_profile',
			static function (): array {
				return array(
					'item_tag'   => 'div',
					'item_class' => 'attendee-card',
					'confidence' => 0.95,
				);
			}
		);
		add_filter( 'pre_http_request', array( $this, 'mock_profile_http_responses' ), 10, 3 );

		$html = '<section class="people">'
			. '<div class="attendee-card"><img src="https://secure.gravatar.com/avatar/' . self::HASH_WESTGUARD . '?s=96" />'
			. '<a href="https://profiles.wordpress.org/westguard/">westguard</a>'
			. '<a href="https://wsform.com">wsform.com</a>'
			. '<a href="https://x.com/westguard">x.com/westguard</a></div>'
			. '</section>';

		$importer  = new WordCamp_Attendee_Importer();
		$attendees = $importer->extract_attendees( $html, 'https://example.test/attendees/' );

		$this->assertCount( 1, $attendees );
		$this->assertSame( 'westguard', $attendees[0]['identifier'] );
		$this->assertSame( self::HASH_WESTGUARD, $attendees[0]['gravatar_hash'] );
		$this->assertSame( 'https://wsform.com', $attendees[0]['website_url'] );
		$this->assertSame( array( 'https://x.com/westguard' ), $attendees[0]['social_links'] );
	}

	/**
	 * A daily recurring Action Scheduler job is registered once.
	 */
	public function test_schedule_daily_import_registers_recurring_action(): void {
		$importer = new WordCamp_Attendee_Importer();
		$importer->schedule_daily_import();
		$importer->schedule_daily_import();

		$actions = as_get_scheduled_actions(
			array(
				'hook'   => WordCamp_Attendee_Importer::AS_HOOK,
				'group'  => WordCamp_Attendee_Importer::AS_GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);

		$this->assertCount( 1, $actions );
	}

	/**
	 * The scheduled importer fans out per-event crawl jobs and imports attendees through the event job.
	 */
	public function test_scheduled_import_queues_event_jobs_and_populates_attendees(): void {
		$this->use_parsing_profile( 'article', 'person-card' );

		$event_id = wp_insert_post(
			array(
				'post_type'   => Data_Structure::POST_TYPE_EVENT,
				'post_title'  => 'WordCamp Europe 2026',
				'post_status' => 'publish',
			),
			true
		);

		$this->assertIsInt( $event_id );
		update_post_meta( $event_id, 'wpcamp_attendees_url', self::ATTENDEES_URL );

		add_filter( 'pre_http_request', array( $this, 'mock_profile_http_responses' ), 10, 3 );

		$action_id = as_schedule_single_action(
			time(),
			WordCamp_Attendee_Importer::AS_HOOK,
			array(),
			WordCamp_Attendee_Importer::AS_GROUP
		);
		$this->assertIsInt( $action_id );
		$this->assertGreaterThan( 0, $action_id );

		$pending = as_get_scheduled_actions(
			array(
				'hook'   => WordCamp_Attendee_Importer::AS_HOOK,
				'group'  => WordCamp_Attendee_Importer::AS_GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertContains( $action_id, array_map( 'intval', $pending ) );

		ActionScheduler_QueueRunner::instance()->process_action( $action_id, 'PHPUnit' );

		$event_actions = as_get_scheduled_actions(
			array(
				'hook'   => WordCamp_Attendee_Importer::AS_EVENT_HOOK,
				'group'  => WordCamp_Attendee_Importer::AS_GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertCount( 1, $event_actions );

		ActionScheduler_QueueRunner::instance()->process_action( (int) reset( $event_actions ), 'PHPUnit' );

		$user = get_user_by( 'login', 'westguard' );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( 'westguard', $user->display_name );
		$this->assertSame( 'westguard@localhost.local', $user->user_email );
		$this->assertSame( 'westguard', get_user_meta( $user->ID, 'wpcamp_wporg_username', true ) );
		$this->assertSame(
			'https://profiles.wordpress.org/westguard/',
			get_user_meta( $user->ID, 'wpcamp_wporg_profile_url', true )
		);
		$this->assertSame( self::HASH_WESTGUARD, get_user_meta( $user->ID, 'wpcamp_gravatar_hash', true ) );
		$this->assertSame( '', get_user_meta( $user->ID, 'wpcamp_bio', true ) );
		$this->assertSame( array(), get_user_meta( $user->ID, 'wpcamp_wporg_profile', true ) );
		$this->assertSame( array(), get_user_meta( $user->ID, 'wpcamp_gravatar_profile', true ) );
		$this->assertContains( (int) $user->ID, Event::from( (int) $event_id )->get_related( 'user' ) );

		$gravatar_only_user = get_user_by( 'login', 'gravatar-cccccccccccccccccccc' );
		$this->assertInstanceOf( WP_User::class, $gravatar_only_user );
		$this->assertSame( 'gravatar-cccccccccccccccccccc', $gravatar_only_user->display_name );
		$this->assertSame( '', get_user_meta( $gravatar_only_user->ID, 'wpcamp_wporg_username', true ) );
		$this->assertSame( '', get_user_meta( $gravatar_only_user->ID, 'wpcamp_wporg_profile_url', true ) );
		$this->assertSame(
			self::HASH_GRAVATAR_ONLY,
			get_user_meta( $gravatar_only_user->ID, 'wpcamp_gravatar_hash', true )
		);
		$this->assertSame( '', get_user_meta( $gravatar_only_user->ID, 'wpcamp_company', true ) );
		$this->assertSame( '', get_user_meta( $gravatar_only_user->ID, 'wpcamp_community_role', true ) );
		$this->assertSame( '', get_user_meta( $gravatar_only_user->ID, 'wpcamp_bio', true ) );
		$this->assertSame( array(), get_user_meta( $gravatar_only_user->ID, 'wpcamp_wporg_profile', true ) );
		$this->assertSame( array(), get_user_meta( $gravatar_only_user->ID, 'wpcamp_gravatar_profile', true ) );
		$this->assertContains( (int) $gravatar_only_user->ID, Event::from( (int) $event_id )->get_related( 'user' ) );
	}

	/**
	 * Mock public profile APIs used by the importer.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt Existing preempted response.
	 * @param array<string,mixed>                 $parsed_args HTTP arguments.
	 * @param string                              $url Requested URL.
	 * @return false|array<string,mixed>|\WP_Error
	 */
	public function mock_profile_http_responses(
		false|array|\WP_Error $preempt,
		array $parsed_args,
		string $url
	): false|array|\WP_Error {
		unset( $parsed_args );

		if ( self::ATTENDEES_URL === $url ) {
			return $this->http_response( $this->sample_attendees_html() );
		}

		if ( 'https://profiles.wordpress.org/wp-json/wporg/v1/users/westguard' === $url ) {
			return $this->http_response(
				wp_json_encode(
					array(
						'id'          => 16328268,
						'name'        => 'Mark Westguard',
						'url'         => 'https://wsform.com',
						'description' => 'Founder of WS Form, a WordPress form plugin.',
						'link'        => 'https://profiles.wordpress.org/westguard/',
						'slug'        => 'westguard',
						'avatar_urls' => array(
							'96' => '//www.gravatar.com/avatar/' . self::HASH_WESTGUARD . '?s=96&r=g&d=mm',
						),
					)
				)
			);
		}

		if ( 'https://profiles.wordpress.org/wp-json/wporg/v1/users/gravataronly' === $url ) {
			return $this->http_response(
				wp_json_encode(
					array(
						'id'          => 42,
						'name'        => 'Gravatar Only',
						'description' => 'WordPress.org profile biography.',
						'link'        => 'https://profiles.wordpress.org/gravataronly/',
						'slug'        => 'gravataronly',
						'avatar_urls' => array(
							'96' => '//www.gravatar.com/avatar/' . self::HASH_GRAVATAR_ONLY . '?s=96&r=g&d=mm',
						),
					)
				)
			);
		}

		if ( 'https://api.gravatar.com/v3/profiles/' . self::HASH_WESTGUARD === $url ) {
			return $this->http_response(
				wp_json_encode(
					array(
						'hash'         => self::HASH_WESTGUARD,
						'display_name' => 'Mark Westguard',
						'description'  => 'Gravatar profile data.',
					)
				)
			);
		}

		if ( 'https://api.gravatar.com/v3/profiles/' . self::HASH_GRAVATAR_ONLY === $url ) {
			return $this->http_response(
				wp_json_encode(
					array(
						'hash'              => self::HASH_GRAVATAR_ONLY,
						'display_name'      => 'Gravatar Only',
						'avatar_url'        => 'https://0.gravatar.com/avatar/' . self::HASH_GRAVATAR_ONLY,
						'description'       => 'Gravatar-only biography.',
						'company'           => 'Gravatar Co',
						'job_title'         => 'Organizer',
						'verified_accounts' => array(
							array(
								'service_type'  => 'wordpress',
								'service_label' => 'WordPress.org',
								'url'           => 'https://profiles.wordpress.org/gravataronly/',
								'is_hidden'     => false,
							),
						),
					)
				)
			);
		}

		if (
			str_starts_with( $url, 'https://profiles.wordpress.org/wp-json/wporg/v1/users/' ) ||
			str_starts_with( $url, 'https://api.gravatar.com/v3/profiles/' )
		) {
			return $this->http_response( '{}', 404 );
		}

		return $preempt;
	}

	/**
	 * Build a WordPress HTTP API response.
	 *
	 * @param string|false $body Response body.
	 * @param int          $status_code HTTP status code.
	 * @return array<string,mixed>
	 */
	private function http_response( string|false $body, int $status_code = 200 ): array {
		return array(
			'headers'  => array(),
			'body'     => false === $body ? '' : $body,
			'response' => array(
				'code'    => $status_code,
				'message' => 200 === $status_code ? 'OK' : 'Not Found',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Representative attendee HTML with valid and invalid URL patterns.
	 */
	private function sample_attendees_html(): string {
		return <<<HTML
<section class="attendees">
	<article class="person-card">
		<img src="https://secure.gravatar.com/avatar/2ec060b5c8173af4c535e8aec48bfd09?s=190&#038;d=blank&#038;r=g" />
		<div><span>Mark</span> <span>Westguard</span></div>
		<a href="https://profiles.wordpress.org/westguard/profile">profiles.wordpress.org/westguard</a>
	</article>
	<article class="person-card">
		<div>Forum User</div>
		<a href="https://wordpress.org/support/users/forumuser/">wordpress.org/support/users/forumuser</a>
	</article>
	<article class="person-card">
		<img src="https://secure.gravatar.com/avatar/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa?s=190" />
	</article>
	<article class="person-card sponsor-row">
		<img src="https://example.com/avatar/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb?s=190" />
		<a href="https://profiles.wordpress.org/not-gravatar/">profiles.wordpress.org/not-gravatar</a>
	</article>
	<article class="person-card">
		<img src="https://secure.gravatar.com/avatar/cccccccccccccccccccccccccccccccc?s=190" />
		<a href="http://adispiac">adispiac</a>
	</article>
</section>
HTML;
	}

	/**
	 * Representative subset from https://germany.wordcamp.org/2023/teilnehmende-attendees/.
	 */
	private function germany_2023_attendees_html(): string {
		return '<ul id="tix-attendees" class="tix-attendee-list">'
			. '<li><img alt="" src="https://secure.gravatar.com/avatar/' . self::HASH_GERMANY_ONE
			. '?s=96&#038;d=mm&#038;r=g" srcset="https://secure.gravatar.com/avatar/'
			. self::HASH_GERMANY_ONE . '?s=192&#038;d=mm&#038;r=g 2x" />'
			. '<a class="tix-field tix-attendee-twitter" href="http://twitter.com/Schlessera">@Schlessera</a>'
			. '</li>'
			. '<li><img alt="" src="https://secure.gravatar.com/avatar/' . self::HASH_GERMANY_TWO
			. '?s=96&#038;d=mm&#038;r=g" srcset="https://secure.gravatar.com/avatar/'
			. self::HASH_GERMANY_TWO . '?s=192&#038;d=mm&#038;r=g 2x" />'
			. '<a class="tix-field tix-attendee-url tix-website" href="https://example.com/">example.com</a>'
			. '</li>'
			. '</ul>';
	}

	/**
	 * Representative subset from https://asia.wordcamp.org/2026/attendees/.
	 */
	private function asia_2026_attendees_html(): string {
		return '<ul class="tix-attendee-list tix-columns-3">'
			. '<li><img alt="" src="https://secure.gravatar.com/avatar/' . self::HASH_ASIA_ONE
			. '?s=96&#038;d=mm&#038;r=g" srcset="https://secure.gravatar.com/avatar/'
			. self::HASH_ASIA_ONE . '?s=192&#038;d=mm&#038;r=g 2x" />'
			. '<div class="tix-field tix-attendee-name">Aryan Jalan</div>'
			. '<a class="tix-field tix-attendee-url tix-website" href="https://aryanjalan.com">aryanjalan.com</a>'
			. '</li>'
			. '<li><img alt="" src="https://secure.gravatar.com/avatar/' . self::HASH_ASIA_TWO
			. '?s=96&#038;d=mm&#038;r=g" srcset="https://secure.gravatar.com/avatar/'
			. self::HASH_ASIA_TWO . '?s=192&#038;d=mm&#038;r=g 2x" />'
			. '<div class="tix-field tix-attendee-name">WPWeb Infotech</div>'
			. '<a class="tix-field tix-attendee-twitter" href="http://twitter.com/wpwebinfotech">@wpwebinfotech</a>'
			. '<a class="tix-field tix-attendee-url tix-website" href="https://wpwebinfotech.com/">wpwebinfotech.com</a>'
			. '</li>'
			. '</ul>';
	}
}
