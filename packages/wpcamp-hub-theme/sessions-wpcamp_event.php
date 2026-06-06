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
	$s_id      = $wpch_session->get_id();
	$s_track   = $wpch_session->get_track();
	$s_time    = $wpch_session->get_start_time();
	$s_end     = $wpch_session->get_end_time();
	$s_ts      = '' !== $s_time ? strtotime( $s_time ) : 0;
	$s_end_ts  = '' !== $s_end ? strtotime( $s_end ) : 0;
	// Minutes-from-midnight drive the time-proportional timetable grid.
	$s_start_m = $s_ts ? ( (int) wp_date( 'G', $s_ts ) * 60 + (int) wp_date( 'i', $s_ts ) ) : 0;
	$s_end_m   = $s_end_ts ? ( (int) wp_date( 'G', $s_end_ts ) * 60 + (int) wp_date( 'i', $s_end_ts ) ) : 0;

	$wpch_rows[] = array(
		'title'    => get_the_title( $s_id ),
		'track'    => $s_track ? $s_track->get_name() : '',
		'color'    => $s_track ? $s_track->get_color() : '#3858e9',
		'room'     => (string) get_post_meta( $s_id, 'wpcamp_room', true ),
		'speakers' => implode( ', ', $wpch_session->get_speaker_names() ),
		'ts'       => $s_ts,
		'time'     => $s_ts ? wp_date( 'H:i', $s_ts ) : '',
		'end'      => $s_end_ts ? wp_date( 'H:i', $s_end_ts ) : '',
		'start_m'  => $s_start_m,
		'end_m'    => $s_end_m,
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
					</div>

					<?php // ---- Timetable (the only layout: true time grid, blocks span their duration) ---- ?>
					<div class="wpch-sv__view wpch-sv__view--timetable is-active" data-view="timetable">
						<?php
						// Group sessions by day. $wpch_rows is sorted by start time, so
						// day insertion order is chronological. Each Track (taxonomy term)
						// is a column; a session is placed in its track column and spans its
						// start/end minutes. Untracked sessions fall in a "General" column.
						$tt_days = array();
						foreach ( $wpch_rows as $row ) {
							$day_key  = '' !== $row['day'] ? $row['day'] : __( 'Unscheduled', 'wpcamp-hub' );
							$track    = '' !== $row['track'] ? $row['track'] : __( 'General', 'wpcamp-hub' );

							// Remember the track's colour for the column header.
							$tt_days[ $day_key ]['tracks'][ $track ] = $row['color'];
							$tt_days[ $day_key ]['sessions'][]       = array( 'col' => $track ) + $row;
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
							$day        = $tt_days[ $day_label ];
							$track_cols = $day['tracks']; // track name → colour.
							ksort( $track_cols, SORT_NATURAL | SORT_FLAG_CASE ); // columns ordered by track name.
							$tracks     = array_keys( $track_cols );
							$col_index  = array_flip( $tracks ); // track name → 0-based index.
							$cols       = count( $tracks );

							// Unique time boundaries (each session's start + end) become the
							// horizontal gridlines. A session block then spans from its start
							// line to its end line, so longer talks visibly overlap shorter
							// ones running alongside them.
							$bounds = array();
							foreach ( $day['sessions'] as $s ) {
								if ( $s['start_m'] > 0 ) {
									$bounds[ $s['start_m'] ] = true;
								}
								if ( $s['end_m'] > $s['start_m'] ) {
									$bounds[ $s['end_m'] ] = true;
								}
							}
							$bounds = array_keys( $bounds );
							sort( $bounds );
							$line_for = array_flip( $bounds ); // minute → 0-based boundary index.

							// Row track sizes proportional to each gap's duration (min 40px),
							// preceded by the sticky header row.
							$row_sizes = array( '46px' );
							for ( $b = 0; $b < count( $bounds ) - 1; $b++ ) {
								$gap         = max( 1, $bounds[ $b + 1 ] - $bounds[ $b ] );
								$row_sizes[] = 'minmax(' . max( 40, (int) round( $gap * 1.4 ) ) . 'px, auto)';
							}
							$grid_rows = implode( ' ', $row_sizes );
							?>
							<div class="wpch-tt-day<?php echo 0 === $i ? ' is-active' : ''; ?>" data-tt-day-panel="<?php echo esc_attr( (string) $i ); ?>">
								<div class="wpch-tt-wrap">
									<div
										class="wpch-tt wpch-tt--grid"
										style="--tt-cols:<?php echo esc_attr( (string) $cols ); ?>;grid-template-rows:<?php echo esc_attr( $grid_rows ); ?>;"
									>
										<div class="wpch-tt__head wpch-tt__corner"></div>
										<?php foreach ( $tracks as $track ) : ?>
											<div class="wpch-tt__head wpch-tt__cell-head" style="--wpch-track:<?php echo esc_attr( $track_cols[ $track ] ); ?>">
												<span class="wpch-tt__col-dot" aria-hidden="true"></span>
												<?php echo esc_html( $track ); ?>
											</div>
										<?php endforeach; ?>

										<?php // Full-height column rules so empty bands still read as a grid. ?>
										<?php foreach ( $tracks as $ti => $track ) : ?>
											<div class="wpch-tt__col-rule" style="grid-column:<?php echo esc_attr( (string) ( $ti + 2 ) ); ?>;grid-row:2 / -1;"></div>
										<?php endforeach; ?>

										<?php // Full-width horizontal rule at each time boundary (skip the first; the header border covers it). ?>
										<?php foreach ( $bounds as $bi => $minutes ) : ?>
											<?php if ( $bi > 0 ) : ?>
												<div class="wpch-tt__row-line" style="grid-row:<?php echo esc_attr( (string) ( $bi + 2 ) ); ?>;"></div>
											<?php endif; ?>
										<?php endforeach; ?>

										<?php // Time labels down the first column, one per boundary line. ?>
										<?php
										foreach ( $bounds as $bi => $minutes ) :
											// No label after the last line (it closes the final block).
											if ( $bi >= count( $bounds ) - 1 ) {
												continue;
											}
											$hh = str_pad( (string) intdiv( $minutes, 60 ), 2, '0', STR_PAD_LEFT );
											$mm = str_pad( (string) ( $minutes % 60 ), 2, '0', STR_PAD_LEFT );
											?>
											<div class="wpch-tt__time" style="grid-row:<?php echo esc_attr( (string) ( $bi + 2 ) ); ?>;grid-column:1;"><?php echo esc_html( $hh . ':' . $mm ); ?></div>
										<?php endforeach; ?>

										<?php // Session blocks, each spanning its start→end lines in its track column. ?>
										<?php
										foreach ( $day['sessions'] as $s ) :
											if ( ! isset( $line_for[ $s['start_m'] ] ) ) {
												continue;
											}
											$start_line = $line_for[ $s['start_m'] ] + 2;
											$end_line   = isset( $line_for[ $s['end_m'] ] ) && $s['end_m'] > $s['start_m']
												? $line_for[ $s['end_m'] ] + 2
												: $start_line + 1;
											$col        = ( $col_index[ $s['col'] ] ?? 0 ) + 2;
											$range      = '' !== $s['end'] ? $s['time'] . '–' . $s['end'] : $s['time'];
											?>
											<a
												class="wpch-tt__block"
												href="<?php echo esc_url( $s['url'] ); ?>"
												style="--wpch-track:<?php echo esc_attr( $s['color'] ); ?>;grid-column:<?php echo esc_attr( (string) $col ); ?>;grid-row:<?php echo esc_attr( $start_line . ' / ' . $end_line ); ?>;"
											>
												<?php if ( '' !== $s['room'] ) : ?>
													<span class="wpch-tt__type"><?php echo esc_html( $s['room'] ); ?></span>
												<?php endif; ?>
												<span class="wpch-tt__title"><?php echo esc_html( $s['title'] ); ?></span>
												<span class="wpch-tt__meta"><?php echo esc_html( $range ); ?></span>
											</a>
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
