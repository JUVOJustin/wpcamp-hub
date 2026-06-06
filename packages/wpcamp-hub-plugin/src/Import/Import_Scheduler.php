<?php
/**
 * Action Scheduler wiring for the daily WordCamp schedule/speaker import.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Import;

use WPCAMP_HUB\Data\Event;

/**
 * Schedules and runs the recurring WordCamp session and speaker import.
 *
 * The work is split into bounded Action Scheduler jobs in the shared
 * {@see GROUP} group:
 *
 *  - A single daily {@see AS_HOOK} master job scans every event flagged as a
 *    major WordCamp and fans out per-event imports.
 *  - {@see AS_SPEAKERS_HOOK} (`event_id`, `page`) imports one page of speakers
 *    and reschedules itself for the next page.
 *  - {@see AS_SESSIONS_HOOK} (`event_id`, `page`) imports one page of sessions
 *    and reschedules itself for the next page.
 *
 * Speakers and sessions are scheduled as **independent** per-event jobs — they
 * do not chain into one another. A session that references a speaker not yet
 * imported fetches that speaker on demand, so the two resources can run in any
 * order or in parallel. This mirrors the WordCamp attendee importer
 * ({@see \WPCAMP_HUB\Import\WordCamp_Attendee_Importer}) so all three resources
 * (schedule, speakers, attendees) can later be driven by one master sync job
 * that fans out three independent jobs per event.
 *
 * Page-at-a-time scheduling keeps each job small and lets Action Scheduler
 * retry a single failed page without re-running an entire camp.
 */
class Import_Scheduler {

	/**
	 * Recurring master job: scan flagged events and fan out per-event imports.
	 */
	public const string AS_HOOK = 'wpcamp_hub/import_wordcamp_schedule';

	/**
	 * Per-event, per-page speaker import.
	 */
	public const string AS_SPEAKERS_HOOK = 'wpcamp_hub/import_wordcamp_event_speakers';

	/**
	 * Per-event, per-page session import.
	 */
	public const string AS_SESSIONS_HOOK = 'wpcamp_hub/import_wordcamp_event_sessions';

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
		add_action( self::AS_SPEAKERS_HOOK, array( $this, 'import_speakers' ), 10, 2 );
		add_action( self::AS_SESSIONS_HOOK, array( $this, 'import_sessions' ), 10, 2 );
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
	 * Remove all scheduled schedule/speaker import jobs. Call on deactivation.
	 */
	public static function unschedule_daily_import(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::AS_HOOK, array(), self::GROUP );
		as_unschedule_all_actions( self::AS_SPEAKERS_HOOK, array(), self::GROUP );
		as_unschedule_all_actions( self::AS_SESSIONS_HOOK, array(), self::GROUP );
	}

	/**
	 * Master job: queue independent speaker and session imports per flagged camp.
	 */
	public function fan_out(): void {
		foreach ( Event::major_wordcamps() as $event ) {
			$this->queue_event_import( $event->get_id() );
		}
	}

	/**
	 * Queue the per-event speaker and session imports for a single event.
	 *
	 * Public so a future unified master sync can fan a single event out to all
	 * of its resources (schedule, speakers, attendees) in one place.
	 *
	 * @param int $event_id Event post ID.
	 */
	public function queue_event_import( int $event_id ): void {
		$this->enqueue( self::AS_SPEAKERS_HOOK, $event_id, 1 );
		$this->enqueue( self::AS_SESSIONS_HOOK, $event_id, 1 );
	}

	/**
	 * Import one page of speakers for an event, then continue to the next page.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $page 1-based page number.
	 */
	public function import_speakers( int $event_id, int $page ): void {
		$importer = $this->importer_for( $event_id );
		if ( null === $importer ) {
			return;
		}

		$result = $importer->import_speakers_page( $page );

		$next = $result->next_page();
		if ( null !== $next ) {
			$this->enqueue( self::AS_SPEAKERS_HOOK, $event_id, $next );
		}
	}

	/**
	 * Import one page of sessions for an event, then continue to the next page.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $page 1-based page number.
	 */
	public function import_sessions( int $event_id, int $page ): void {
		$importer = $this->importer_for( $event_id );
		if ( null === $importer ) {
			return;
		}

		$result = $importer->import_sessions_page( $page );

		$next = $result->next_page();
		if ( null !== $next ) {
			$this->enqueue( self::AS_SESSIONS_HOOK, $event_id, $next );
		}
	}

	/**
	 * Build an importer for an event, or null when it is not importable.
	 *
	 * @param int $event_id Event post ID.
	 * @return WordCamp_Importer|null
	 */
	private function importer_for( int $event_id ): ?WordCamp_Importer {
		if ( Event::get_post_type() !== get_post_type( $event_id ) ) {
			return null;
		}

		$event = Event::from( $event_id );
		if ( ! $event->is_major_wordcamp() || '' === $event->get_official_url() ) {
			return null;
		}

		try {
			return new WordCamp_Importer( $event );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Enqueue a single one-off page job (unless an identical one is pending).
	 *
	 * Args are stored positionally (`[event_id, page]`) — Action Scheduler
	 * invokes the callback with `array_values()` of the stored args.
	 *
	 * @param string $hook Action hook.
	 * @param int    $event_id Event post ID.
	 * @param int    $page Page number.
	 */
	private function enqueue( string $hook, int $event_id, int $page ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$args = array( $event_id, $page );
		if ( false !== as_has_scheduled_action( $hook, $args, self::GROUP ) ) {
			return;
		}

		as_enqueue_async_action( $hook, $args, self::GROUP, true );
	}
}
