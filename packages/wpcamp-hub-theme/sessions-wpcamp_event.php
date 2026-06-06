<?php
/**
 * Sessions subpage for a single event — /event/<slug>/sessions/.
 *
 * Lists every session programmed for this event (event → sessions), reusing
 * the single-event session-list markup so cards look consistent.
 *
 * Routed here by the plugin's Sessions_Page (rewrite endpoint) via
 * `template_include` → `locate_template()`. Requires the WPCamp Hub plugin for
 * the Event/Session entities.
 *
 * @package wpcamp-hub
 */

use WPCAMP_HUB\Data\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wpch_event_id = get_queried_object_id();
$wpch_event    = class_exists( Event::class ) ? Event::from( $wpch_event_id ) : null;
$wpch_sessions = $wpch_event ? $wpch_event->get_sessions() : array();
$wpch_title    = get_the_title( $wpch_event_id );

// Normalise + sort session rows by start time (shared shape with the single
// event template's list view).
$wpch_rows = array();
foreach ( $wpch_sessions as $wpch_session ) {
	$s_id    = $wpch_session->get_id();
	$s_track = $wpch_session->get_track();
	$s_time  = $wpch_session->get_start_time();
	$s_ts    = '' !== $s_time ? strtotime( $s_time ) : 0;

	$wpch_rows[] = array(
		'title'    => get_the_title( $s_id ),
		'track'    => $s_track ? $s_track->get_name() : '',
		'color'    => $s_track ? $s_track->get_color() : '#3858e9',
		'room'     => (string) get_post_meta( $s_id, 'wpcamp_room', true ),
		'speakers' => implode( ', ', $wpch_session->get_speaker_names() ),
		'ts'       => $s_ts,
		'time'     => $s_ts ? wp_date( 'H:i', $s_ts ) : '',
		'day'      => $s_ts ? wp_date( 'D j M', $s_ts ) : '',
		'url'      => (string) get_permalink( $s_id ),
	);
}
usort( $wpch_rows, static fn( array $a, array $b ): int => $a['ts'] <=> $b['ts'] );
?>

<main class="wpch-attendees wpch-event-sessions">
	<section class="wpch-attendees__hero">
		<div class="wpch-attendees__inner">
			<div class="wpch-attendees__eyebrow"><?php echo esc_html( $wpch_title ); ?></div>
			<h1 class="wpch-attendees__title"><?php esc_html_e( 'Sessions', 'wpcamp-hub' ); ?></h1>
			<p class="wpch-attendees__lead">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of sessions. */
						_n( '%d session on the programme.', '%d sessions on the programme.', count( $wpch_rows ), 'wpcamp-hub' ),
						count( $wpch_rows )
					)
				);
				esc_html_e( ' Browse the full schedule and open any talk for details.', 'wpcamp-hub' );
				?>
			</p>
		</div>
	</section>

	<section class="wpch-attendees__body">
		<div class="wpch-attendees__inner">
			<?php if ( array() !== $wpch_rows ) : ?>
				<div class="wpch-sv">
					<div class="wpch-sv__head">
						<h2 class="wpch-attendees__subtitle"><?php esc_html_e( 'Full programme', 'wpcamp-hub' ); ?></h2>
						<div class="wpch-sv__switch" role="tablist">
							<button type="button" class="wpch-sv__btn is-active" data-view="list" aria-selected="true"><?php esc_html_e( 'List', 'wpcamp-hub' ); ?></button>
							<button type="button" class="wpch-sv__btn" data-view="timetable" aria-selected="false"><?php esc_html_e( 'Timetable', 'wpcamp-hub' ); ?></button>
						</div>
					</div>

					<?php // ---- List view (with search) ---- ?>
					<div class="wpch-sv__view wpch-sv__view--list is-active" data-view="list">
						<div class="wpch-attendees__search wpch-event-sessions__search">
							<?php echo wpcamp_hub_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
							<input
								type="search"
								class="wpch-attendees__search-input"
								placeholder="<?php esc_attr_e( 'Search by title, track or speaker', 'wpcamp-hub' ); ?>"
								aria-label="<?php esc_attr_e( 'Search sessions', 'wpcamp-hub' ); ?>"
							/>
						</div>
						<ul class="wpch-event__sessions">
							<?php foreach ( $wpch_rows as $row ) : ?>
								<li
									class="wpch-event__session"
									style="--wpch-track:<?php echo esc_attr( $row['color'] ); ?>"
									data-search="<?php echo esc_attr( strtolower( trim( $row['title'] . ' ' . $row['track'] . ' ' . $row['speakers'] ) ) ); ?>"
								>
									<a href="<?php echo esc_url( $row['url'] ); ?>">
										<span class="wpch-event__session-dot" aria-hidden="true"></span>
										<span class="wpch-event__session-title"><?php echo esc_html( $row['title'] ); ?></span>
										<?php if ( '' !== $row['track'] ) : ?>
											<span class="wpch-event__session-track"><?php echo esc_html( $row['track'] ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $row['day'] || '' !== $row['time'] ) : ?>
											<span class="wpch-event__session-time"><?php echo esc_html( trim( $row['day'] . ' ' . $row['time'] ) ); ?></span>
										<?php endif; ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="wpch-attendees__empty" hidden>
							<?php esc_html_e( 'No sessions match your search.', 'wpcamp-hub' ); ?>
						</p>
					</div>

					<?php // ---- Timetable view (rooms × times grid, one day at a time) ---- ?>
					<div class="wpch-sv__view wpch-sv__view--timetable" data-view="timetable">
						<?php
						// Build the timetable model grouped by day. Rows are already
						// sorted by start time, so day insertion order is chronological.
						$tt_days = array();
						foreach ( $wpch_rows as $row ) {
							$day_key = '' !== $row['day'] ? $row['day'] : __( 'Unscheduled', 'wpcamp-hub' );
							$room    = '' !== $row['room'] ? $row['room'] : __( 'Unassigned', 'wpcamp-hub' );
							$time    = '' !== $row['time'] ? $row['time'] : '—';

							$tt_days[ $day_key ]['rooms'][ $room ]          = true;
							$tt_days[ $day_key ]['times'][ $time ]          = true;
							$tt_days[ $day_key ]['cells'][ $time ][ $room ] = $row;
						}
						$tt_day_labels = array_keys( $tt_days );
						?>

						<?php if ( count( $tt_day_labels ) > 1 ) : ?>
							<div class="wpch-tt-tabs seg" role="tablist">
								<?php foreach ( $tt_day_labels as $i => $day_label ) : ?>
									<button
										type="button"
										class="wpch-tt-tab<?php echo 0 === $i ? ' is-active' : ''; ?>"
										data-tt-day="<?php echo esc_attr( (string) $i ); ?>"
										aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
									><?php echo esc_html( $day_label ); ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php
						foreach ( $tt_day_labels as $i => $day_label ) :
							$day   = $tt_days[ $day_label ];
							$rooms = array_keys( $day['rooms'] );
							$times = array_keys( $day['times'] );
							sort( $times );
							$cols = count( $rooms );
							?>
							<div class="wpch-tt-day<?php echo 0 === $i ? ' is-active' : ''; ?>" data-tt-day-panel="<?php echo esc_attr( (string) $i ); ?>">
								<div class="wpch-tt-wrap">
									<div class="wpch-tt" style="--tt-cols:<?php echo esc_attr( (string) $cols ); ?>">
										<div class="wpch-tt__head wpch-tt__corner"></div>
										<?php foreach ( $rooms as $room ) : ?>
											<div class="wpch-tt__head wpch-tt__cell-head"><?php echo esc_html( $room ); ?></div>
										<?php endforeach; ?>

										<?php foreach ( $times as $time ) : ?>
											<div class="wpch-tt__time"><?php echo esc_html( $time ); ?></div>
											<?php
											foreach ( $rooms as $room ) :
												$cell = $day['cells'][ $time ][ $room ] ?? null;
												?>
												<div class="wpch-tt__track">
													<?php if ( null !== $cell ) : ?>
														<a class="wpch-tt__block" href="<?php echo esc_url( $cell['url'] ); ?>"
															style="--wpch-track:<?php echo esc_attr( $cell['color'] ); ?>">
															<?php if ( '' !== $cell['track'] ) : ?>
																<span class="wpch-tt__type"><?php echo esc_html( $cell['track'] ); ?></span>
															<?php endif; ?>
															<span class="wpch-tt__title"><?php echo esc_html( $cell['title'] ); ?></span>
															<span class="wpch-tt__meta"><?php echo esc_html( $cell['time'] ); ?></span>
														</a>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else : ?>
				<div class="wpch-attendees__empty">
					<?php echo wpcamp_hub_icon( 'calendar', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<p><?php esc_html_e( 'No sessions announced yet for this event.', 'wpcamp-hub' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>

<script>
( function () {
	var root = document.querySelector( '.wpch-event-sessions' );
	if ( ! root ) {
		return;
	}

	// List-view search.
	var input = root.querySelector( '.wpch-attendees__search-input' );
	if ( input ) {
		var items = Array.prototype.slice.call(
			root.querySelectorAll( '.wpch-event__session' )
		);
		var empty = root.querySelector( '.wpch-attendees__empty[hidden]' );
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			var shown = 0;
			items.forEach( function ( item ) {
				var match =
					'' === q ||
					( item.getAttribute( 'data-search' ) || '' ).indexOf( q ) !==
						-1;
				item.hidden = ! match;
				if ( match ) {
					shown++;
				}
			} );
			if ( empty ) {
				empty.hidden = shown !== 0;
			}
		} );
	}

	// Timetable day tabs — show one day's grid at a time.
	var tabs = Array.prototype.slice.call(
		root.querySelectorAll( '.wpch-tt-tab' )
	);
	var panels = Array.prototype.slice.call(
		root.querySelectorAll( '[data-tt-day-panel]' )
	);
	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			var day = tab.getAttribute( 'data-tt-day' );
			tabs.forEach( function ( t ) {
				var on = t.getAttribute( 'data-tt-day' ) === day;
				t.classList.toggle( 'is-active', on );
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			panels.forEach( function ( p ) {
				p.classList.toggle(
					'is-active',
					p.getAttribute( 'data-tt-day-panel' ) === day
				);
			} );
		} );
	} );
}() );
</script>

<?php
get_footer();
