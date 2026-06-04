<?php
/**
 * Tests for WPCamp Hub data structure registration and wrappers.
 *
 * @package WPCAMP_HUB
 */

use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Event_Type;
use WPCAMP_HUB\Data\Meeting_Invite;
use WPCAMP_HUB\Data\Relationships;
use WPCAMP_HUB\Data\Session;
use WPCAMP_HUB\Data\Track;
use WPCAMP_HUB\Data\Tweet;
use WPCAMP_HUB\Data\Tweet_Label;
use WPCAMP_HUB\Data\User_Profile;

require_once __DIR__ . '/DataStructureFixture.php';

/**
 * Verifies the platform data model is registered and usable.
 */
class DataStructureTest extends WP_UnitTestCase {

	/**
	 * Ensure the central data structure is registered for each isolated test.
	 */
	public function set_up(): void {
		parent::set_up();

		( new Data_Structure() )->register();
	}

	/**
	 * Expected post types are registered.
	 */
	public function test_expected_post_types_are_registered(): void {
		foreach ( array_keys( Data_Structure::get_post_types() ) as $post_type ) {
			$this->assertTrue( post_type_exists( $post_type ), $post_type );
			$this->assertTrue( get_post_type_object( $post_type )->show_in_rest );
		}
	}

	/**
	 * Expected taxonomies and starter terms are registered.
	 */
	public function test_expected_taxonomies_are_registered_with_terms(): void {
		foreach ( Data_Structure::get_taxonomies() as $taxonomy => $config ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), $taxonomy );
			$this->assertTrue( get_taxonomy( $taxonomy )->show_in_rest );

			foreach ( $config['terms'] as $term ) {
				$this->assertNotNull( term_exists( $term, $taxonomy ), $term );
			}
		}
	}

	/**
	 * Post and user meta fields are registered with REST schemas where public.
	 */
	public function test_meta_fields_are_registered_with_schema(): void {
		foreach ( Data_Structure::get_post_meta_fields() as $post_type => $fields ) {
			$registered = get_registered_meta_keys( 'post', $post_type );

			foreach ( $fields as $meta_key => $field ) {
				$this->assertArrayHasKey( $meta_key, $registered );
				$this->assertSame( $field['description'], $registered[ $meta_key ]['description'] );
				$this->assertSame( ! empty( $field['show_in_rest'] ), false !== $registered[ $meta_key ]['show_in_rest'] );
			}
		}

		$registered_user_meta = get_registered_meta_keys( 'user' );

		foreach ( Data_Structure::get_user_meta_fields() as $meta_key => $field ) {
			$this->assertArrayHasKey( $meta_key, $registered_user_meta );
			$this->assertSame( ! empty( $field['show_in_rest'] ), false !== $registered_user_meta[ $meta_key ]['show_in_rest'] );
		}
	}

	/**
	 * Entity wrappers initialize from native IDs and expose the wrapped object.
	 */
	public function test_entity_wrappers_initialize_from_ids(): void {
		$data = DataStructureFixture::seed();

		$event   = Event::from( $data['posts']['event'] );
		$tweet   = Tweet::from( $data['posts']['tweet'] );
		$session = Session::from( $data['posts']['session'] );
		$meeting = Meeting_Invite::from( $data['posts']['meeting_invite'] );
		$user    = User_Profile::from( $data['users'][0] );

		$this->assertInstanceOf( WP_Post::class, $event->get_wp_entity() );
		$this->assertSame( 'WordCamp Europe 2026', $event->post_title );
		$this->assertSame( Data_Structure::POST_TYPE_TWEET, $tweet->get_wp_entity()->post_type );
		$this->assertSame( Data_Structure::POST_TYPE_SESSION, $session->get_wp_entity()->post_type );
		$this->assertSame( Data_Structure::POST_TYPE_MEETING_INVITE, $meeting->get_wp_entity()->post_type );
		$this->assertSame( 'subscriber', $user->get_wp_entity()->roles[0] );
		$this->assertStringContainsString( '@localhost', $user->get_wp_entity()->user_email );
	}

	/**
	 * Attendee profile creation must not send an invitation email.
	 */
	public function test_create_attendee_does_not_send_invite_email(): void {
		$mail_attempts = 0;
		$pre_wp_mail   = static function ( null|bool $return, array $atts ) use ( &$mail_attempts ): bool {
			++$mail_attempts;
			return false;
		};

		add_filter( 'pre_wp_mail', $pre_wp_mail, 10, 2 );
		try {
			User_Profile::create_attendee( 'no-invite-attendee', 'No Invite Attendee' );
		} finally {
			remove_filter( 'pre_wp_mail', $pre_wp_mail, 10 );
		}

		$this->assertSame( 0, $mail_attempts );
	}

	/**
	 * Term wrappers initialize from IDs.
	 */
	public function test_term_wrappers_initialize_from_ids(): void {
		$event_type = get_term_by( 'name', 'WordCamp', Data_Structure::TAXONOMY_EVENT_TYPE );
		$tweet_label = get_term_by( 'name', 'Wants to meet', Data_Structure::TAXONOMY_TWEET_LABEL );
		$track = get_term_by( 'name', 'Track 1', Data_Structure::TAXONOMY_TRACK );

		$this->assertInstanceOf( Event_Type::class, Event_Type::from( $event_type ) );
		$this->assertInstanceOf( Tweet_Label::class, Tweet_Label::from( $tweet_label ) );
		$this->assertInstanceOf( Track::class, Track::from( $track ) );
	}

	/**
	 * Seeded fixture relationships resolve through the central relationship service.
	 */
	public function test_relationships_are_persisted_and_resolved(): void {
		$data = DataStructureFixture::seed();

		$user = User_Profile::from( $data['users'][0] );
		$event = Event::from( $data['posts']['event'] );
		$meeting = Meeting_Invite::from( $data['posts']['meeting_invite'] );

		$this->assertContains( $data['posts']['event'], $user->get_related( 'event' ) );
		$this->assertContains( $data['users'][0], $event->get_related( 'user' ) );
		$this->assertContains( $data['posts']['tweet'], $meeting->get_related( 'tweet' ) );
		$this->assertContains( $data['posts']['session'], Relationships::get_related( 'user', $data['users'][0], 'session' ) );
	}

	/**
	 * Dedicated entity accessors expose typed relationship APIs.
	 */
	public function test_entity_relationship_accessors_return_wrappers(): void {
		$data = DataStructureFixture::seed();

		$event = Event::from( $data['posts']['event'] );
		$user = User_Profile::from( $data['users'][0] );
		$tweet = Tweet::from( $data['posts']['tweet'] );
		$session = Session::from( $data['posts']['session'] );
		$meeting = Meeting_Invite::from( $data['posts']['meeting_invite'] );

		$this->assertSame( $data['users'][0], $event->get_attendees()[0]->get_id() );
		$this->assertSame( $data['posts']['tweet'], $event->get_tweets()[0]->get_id() );
		$this->assertSame( $data['posts']['session'], $event->get_sessions()[0]->get_id() );
		$this->assertSame( $data['posts']['meeting_invite'], $event->get_meeting_invites()[0]->get_id() );

		$this->assertSame( $data['posts']['event'], $user->get_events()[0]->get_id() );
		$this->assertSame( $data['posts']['tweet'], $user->get_tweets()[0]->get_id() );
		$this->assertSame( $data['posts']['session'], $user->get_sessions()[0]->get_id() );
		$this->assertSame( $data['posts']['meeting_invite'], $user->get_meeting_invites()[0]->get_id() );

		$this->assertSame( $data['posts']['event'], $tweet->get_event()->get_id() );
		$this->assertSame( $data['users'][0], $tweet->get_attendees()[0]->get_id() );
		$this->assertSame( $data['posts']['meeting_invite'], $tweet->get_meeting_invites()[0]->get_id() );

		$this->assertSame( $data['posts']['event'], $session->get_event()->get_id() );
		$this->assertSame( $data['users'][0], $session->get_attendees()[0]->get_id() );

		$this->assertSame( $data['posts']['event'], $meeting->get_event()->get_id() );
		$this->assertSame( $data['posts']['tweet'], $meeting->get_source_tweet()->get_id() );
		$this->assertSame( $data['users'][0], $meeting->get_person()->get_id() );
	}
}
