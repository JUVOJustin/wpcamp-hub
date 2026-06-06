<?php
/**
 * Action Scheduler wiring for the daily WordCamp schedule import.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Import;

use WPCAMP_HUB\Abilities\Import\Event_Import_Sessions;
use WPCAMP_HUB\Abilities\Import\Event_Import_Speakers;
use WPCAMP_HUB\Abilities\Import\Event_Import_Attendees;
use WPCAMP_HUB\Data\Event;

/**
 * Schedules and runs the recurring WordCamp import.
 *
 * The work is split into bounded Action Scheduler jobs in the shared
 * {@see GROUP} group:
 *
 *  - A single daily {@see AS_HOOK} master job scans every event flagged as a
 *    major WordCamp and fans out per-event imports.
 *  - {@see AS_SPEAKERS_HOOK} (`event_id`) invokes the internal
 *    `wpcamp-hub/event-import-speakers` ability, then queues the session job
 *    only after the speaker ability succeeds.
 *  - {@see AS_SESSIONS_HOOK} (`event_id`) invokes the internal
 *    `wpcamp-hub/event-import-sessions` ability.
 *  - {@see AS_ATTENDEES_HOOK} (`event_id`) invokes the internal
 *    `wpcamp-hub/event-import-attendees` ability.
 *
 * The import logic lives in internal Abilities API operations with
 * administrator-only permissions; Action Scheduler only decides when those
 * operations run asynchronously.
 */
class Import_Scheduler {

	/**
	 * Recurring master job: scan flagged events and fan out per-event imports.
	 */
	public const string AS_HOOK = 'wpcamp_hub/import_wordcamp_schedule';

	/**
	 * Per-event session import.
	 */
	public const string AS_SESSIONS_HOOK = 'wpcamp_hub/import_wordcamp_event_sessions';

	/**
	 * Per-event speaker import.
	 */
	public const string AS_SPEAKERS_HOOK = 'wpcamp_hub/import_wordcamp_event_speakers';

	/**
	 * Per-event attendee import.
	 */
	public const string AS_ATTENDEES_HOOK = 'wpcamp_hub/import_wordcamp_event_attendees';

	/**
	 * Action Scheduler group shared by all WPCamp Hub import jobs.
	 */
	public const string GROUP = 'wpcamp-hub';

	/**
	 * Register the action callbacks.
	 *
	 * Wired through the Loader: the recurring job is (re)installed on
	 * `action_scheduler_init`, and the import callbacks handle the queued jobs.
	 */
	public function register_hooks(): void {
		add_action( self::AS_HOOK, array( $this, 'fan_out' ) );
		add_action( self::AS_SESSIONS_HOOK, array( $this, 'import_sessions' ), 10, 1 );
		add_action( self::AS_SPEAKERS_HOOK, array( $this, 'import_speakers' ), 10, 1 );
		add_action( self::AS_ATTENDEES_HOOK, array( $this, 'import_attendees' ), 10, 1 );
	}

	/**
	 * Ensure the recurring daily master job is scheduled.
	 *
	 * Idempotent: safe to call on every `action_scheduler_init` and on
	 * activation. Does nothing until Action Scheduler is loaded.
	 */
	public function schedule_daily_import(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( false !== as_next_scheduled_action( self::AS_HOOK, array(), self::GROUP ) ) {
			return;
		}

		as_schedule_recurring_action(
			time() + HOUR_IN_SECONDS,
			DAY_IN_SECONDS,
			self::AS_HOOK,
			array(),
			self::GROUP,
			true
		);
	}

	/**
	 * Remove all scheduled import jobs. Call on deactivation.
	 */
	public static function unschedule_daily_import(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::AS_HOOK, array(), self::GROUP );
		as_unschedule_all_actions( self::AS_SESSIONS_HOOK );
		as_unschedule_all_actions( self::AS_SPEAKERS_HOOK );
		as_unschedule_all_actions( self::AS_ATTENDEES_HOOK );
		as_unschedule_all_actions( 'wpcamp_hub/import_wordcamp_attendees' );
		as_unschedule_all_actions( 'wpcamp_hub/upsert_event_attendees' );
	}

	/**
	 * Master job: queue speaker imports per flagged camp.
	 */
	public function fan_out(): void {
		foreach ( Event::major_wordcamps() as $event ) {
			$this->queue_event_import( $event->get_id() );
		}
	}

	/**
	 * Queue per-event speaker and attendee imports for a single event.
	 *
	 * @param int $event_id Event post ID.
	 */
	public function queue_event_import( int $event_id ): void {
		$this->enqueue( self::AS_SPEAKERS_HOOK, $event_id );
		if ( $this->has_attendees_url( $event_id ) ) {
			$this->enqueue( self::AS_ATTENDEES_HOOK, $event_id );
		}
	}

	/**
	 * Import sessions for an event through the internal sessions ability.
	 *
	 * @param int $event_id Event post ID.
	 */
	public function import_sessions( int $event_id ): void {
		if ( ! $this->is_importable_event( $event_id ) ) {
			return;
		}

		Event_Import_Sessions::execute( array( 'event_id' => $event_id ) );
	}

	/**
	 * Import speakers, then queue the session ability job after success.
	 *
	 * @param int $event_id Event post ID.
	 */
	public function import_speakers( int $event_id ): void {
		if ( ! $this->is_importable_event( $event_id ) ) {
			return;
		}

		$result = Event_Import_Speakers::execute( array( 'event_id' => $event_id ) );
		if ( is_wp_error( $result ) ) {
			return;
		}

		$this->enqueue( self::AS_SESSIONS_HOOK, $event_id );
	}

	/**
	 * Import attendees for an event through the attendee ability.
	 *
	 * @param int $event_id Event post ID.
	 */
	public function import_attendees( int $event_id ): void {
		if ( ! $this->has_attendees_url( $event_id ) ) {
			return;
		}

		Event_Import_Attendees::execute( array( 'event_id' => $event_id ) );
	}

	/**
	 * Determine whether an event can be imported.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function is_importable_event( int $event_id ): bool {
		if ( Event::get_post_type() !== get_post_type( $event_id ) ) {
			return false;
		}

		$event = Event::from( $event_id );

		return $event->is_major_wordcamp() && '' !== $event->get_official_url();
	}

	/**
	 * Determine whether an event has a configured attendees URL.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function has_attendees_url( int $event_id ): bool {
		if ( Event::get_post_type() !== get_post_type( $event_id ) ) {
			return false;
		}

		$value = get_post_meta( $event_id, 'wpcamp_attendees_url', true );

		return is_string( $value ) && '' !== esc_url_raw( $value );
	}

	/**
	 * Enqueue a single one-off import job (unless an identical one is pending).
	 *
	 * Args are stored by parameter name so Action Scheduler invokes callbacks with
	 * explicit named arguments.
	 *
	 * @param string $hook Action hook.
	 * @param int    $event_id Event post ID.
	 */
	private function enqueue( string $hook, int $event_id ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$args = array( 'event_id' => $event_id );
		if ( false !== as_has_scheduled_action( $hook, $args, self::GROUP ) ) {
			return;
		}

		as_enqueue_async_action( $hook, $args, self::GROUP, true );
	}
}
