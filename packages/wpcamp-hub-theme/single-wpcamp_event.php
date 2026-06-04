<?php
/**
 * Single event — full detail with relations.
 *
 * @package wpcamp-hub
 */

use WPCAMP_HUB\Data\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

/**
 * Source meta: label + accent colour preset.
 *
 * @var array<string,array{label:string,color:string}> $wpch_sources
 */
$wpch_sources = array(
	'official'  => array(
		'label' => __( 'Official', 'wpcamp-hub' ),
		'color' => 'var(--wp--preset--color--brand, #3858e9)',
	),
	'community' => array(
		'label' => __( 'Community', 'wpcamp-hub' ),
		'color' => 'var(--wp--preset--color--fest-gold, #ffb020)',
	),
	'x'         => array(
		'label' => __( 'From #WCEU', 'wpcamp-hub' ),
		'color' => 'var(--wp--preset--color--fest-teal, #14b8a6)',
	),
);

while ( have_posts() ) :
	the_post();

	$event = class_exists( Event::class ) ? Event::from( get_the_ID() ) : null;

	$source       = $event ? $event->get_source() : '';
	$source_meta  = isset( $wpch_sources[ $source ] ) ? $wpch_sources[ $source ] : $wpch_sources['community'];
	$accent       = $source_meta['color'];
	$type         = $event ? $event->get_type() : null;
	$type_label   = $type ? $type->get_name() : '';
	$start        = $event ? $event->get_start() : '';
	$end          = $event ? $event->get_end() : '';
	$location     = $event ? $event->get_location() : '';
	$official_url = $event ? $event->get_official_url() : '';

	$when = '';
	if ( '' !== $start ) {
		$when = wp_date( 'l j F · H:i', strtotime( $start ) );
		if ( '' !== $end ) {
			$when .= '–' . wp_date( 'H:i', strtotime( $end ) );
		}
	}

	$attendees = $event ? $event->get_attendees() : array();
	$sessions  = $event ? $event->get_sessions() : array();
	$tweets    = $event ? $event->get_tweets() : array();
	$coords    = $event ? $event->get_coordinates() : null;

	// Precompute normalised session rows shared by the list, map and timetable.
	$session_rows = array();
	foreach ( $sessions as $session ) {
		$s_id    = $session->get_id();
		$s_track = $session->get_track();
		$s_time  = $session->get_start_time();
		$s_ts    = '' !== $s_time ? strtotime( $s_time ) : 0;

		$session_rows[] = array(
			'id'    => $s_id,
			'title' => get_the_title( $s_id ),
			'track' => $s_track ? $s_track->get_name() : '',
			'color' => $s_track ? $s_track->get_color() : '#3858e9',
			'room'  => (string) get_post_meta( $s_id, 'wpcamp_room', true ),
			'ts'    => $s_ts,
			'time'  => $s_ts ? wp_date( 'H:i', $s_ts ) : '',
			'day'   => $s_ts ? wp_date( 'D j M', $s_ts ) : '',
			'url'   => (string) get_permalink( $s_id ),
		);
	}
	// Sort by start time for the timetable.
	usort(
		$session_rows,
		static fn( array $a, array $b ): int => $a['ts'] <=> $b['ts']
	);
	?>
	<main id="content" class="site-content wpch-event" style="--wpch-accent:<?php echo esc_attr( $accent ); ?>">

		<header class="wpch-event__header">
			<div class="wrap">
				<div class="wpch-event__badges">
					<span class="wpch-event__badge"><?php echo esc_html( $source_meta['label'] ); ?></span>
					<?php if ( '' !== $type_label ) : ?>
						<span class="wpch-event__type">
							<span class="wpch-event__dot" aria-hidden="true"></span>
							<?php echo esc_html( $type_label ); ?>
						</span>
					<?php endif; ?>
				</div>
				<h1 class="wpch-event__title"><?php the_title(); ?></h1>
				<div class="wpch-event__meta">
					<?php if ( '' !== $when ) : ?>
						<span class="wpch-event__metaitem"><?php echo esc_html( $when ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $location ) : ?>
						<span class="wpch-event__metaitem"><?php echo esc_html( $location ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<div class="wrap wpch-event__body">

			<div class="wpch-event__description">
				<?php the_content(); ?>
				<?php if ( '' !== $official_url ) : ?>
					<p>
						<a class="btn btn-primary" href="<?php echo esc_url( $official_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Event details', 'wpcamp-hub' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( array() !== $attendees ) : ?>
				<section class="wpch-event__section">
					<h2 class="wpch-event__section-title"><?php esc_html_e( 'Who’s going', 'wpcamp-hub' ); ?></h2>
					<div class="wpch-event__attendees">
						<div class="wpch-event__stack" aria-hidden="true">
							<?php
							foreach ( array_slice( $attendees, 0, 6 ) as $person ) :
								$name = (string) ( $person->get_wp_entity()->display_name ?? '' );
								?>
								<span class="wpch-event__avatar" title="<?php echo esc_attr( $name ); ?>">
									<?php echo esc_html( wpcamp_hub_initials( $name ) ); ?>
								</span>
							<?php endforeach; ?>
						</div>
						<span class="wpch-event__going">
							<?php
							/* translators: %d: number of attendees. */
							echo esc_html( sprintf( _n( '%d person going', '%d people going', count( $attendees ), 'wpcamp-hub' ), count( $attendees ) ) );
							?>
						</span>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( array() !== $session_rows ) : ?>
				<section class="wpch-event__section wpch-sv"
					<?php if ( null !== $coords ) : ?>
						data-lat="<?php echo esc_attr( (string) $coords['latitude'] ); ?>"
						data-lng="<?php echo esc_attr( (string) $coords['longitude'] ); ?>"
						data-label="<?php echo esc_attr( get_the_title() . ( '' !== $location ? ' — ' . $location : '' ) ); ?>"
					<?php endif; ?>
				>
					<div class="wpch-sv__head">
						<h2 class="wpch-event__section-title"><?php esc_html_e( 'Sessions', 'wpcamp-hub' ); ?></h2>
						<div class="wpch-sv__switch" role="tablist">
							<button type="button" class="wpch-sv__btn is-active" data-view="list" aria-selected="true"><?php esc_html_e( 'List', 'wpcamp-hub' ); ?></button>
							<?php if ( null !== $coords ) : ?>
								<button type="button" class="wpch-sv__btn" data-view="map" aria-selected="false"><?php esc_html_e( 'Map', 'wpcamp-hub' ); ?></button>
							<?php endif; ?>
							<button type="button" class="wpch-sv__btn" data-view="timetable" aria-selected="false"><?php esc_html_e( 'Timetable', 'wpcamp-hub' ); ?></button>
						</div>
					</div>

					<?php // ---- List view ---- ?>
					<div class="wpch-sv__view wpch-sv__view--list is-active" data-view="list">
						<ul class="wpch-event__sessions">
							<?php foreach ( $session_rows as $row ) : ?>
								<li class="wpch-event__session" style="--wpch-track:<?php echo esc_attr( $row['color'] ); ?>">
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
					</div>

					<?php // ---- Map view ---- ?>
					<?php if ( null !== $coords ) : ?>
						<div class="wpch-sv__view wpch-sv__view--map" data-view="map">
							<div class="wpch-sv__map-wrap">
								<div
									class="wpch-sv__map"
									id="wpch-event-map"
									data-sessions="<?php echo esc_attr( (string) wp_json_encode( array_map( static fn( array $r ): array => array( 'title' => $r['title'], 'track' => $r['track'], 'time' => trim( $r['day'] . ' ' . $r['time'] ), 'room' => $r['room'], 'url' => $r['url'] ), $session_rows ) ) ); ?>"
								></div>
								<p class="wpch-sv__map-note">
									<?php echo esc_html( '' !== $location ? $location : __( 'Event location', 'wpcamp-hub' ) ); ?>
								</p>
							</div>
						</div>
					<?php endif; ?>

					<?php // ---- Timetable view ---- ?>
					<div class="wpch-sv__view wpch-sv__view--timetable" data-view="timetable">
						<div class="wpch-tt">
							<?php
							// Group sessions by day for the timetable.
							$by_day = array();
							foreach ( $session_rows as $row ) {
								$day_key                = '' !== $row['day'] ? $row['day'] : __( 'Unscheduled', 'wpcamp-hub' );
								$by_day[ $day_key ][]   = $row;
							}
							foreach ( $by_day as $day_label => $rows ) :
								?>
								<div class="wpch-tt__day">
									<div class="wpch-tt__day-label"><?php echo esc_html( $day_label ); ?></div>
									<div class="wpch-tt__rows">
										<?php foreach ( $rows as $row ) : ?>
											<a class="wpch-tt__block" href="<?php echo esc_url( $row['url'] ); ?>" style="--wpch-track:<?php echo esc_attr( $row['color'] ); ?>">
												<span class="wpch-tt__time"><?php echo esc_html( '' !== $row['time'] ? $row['time'] : '—' ); ?></span>
												<span class="wpch-tt__title"><?php echo esc_html( $row['title'] ); ?></span>
												<?php if ( '' !== $row['room'] ) : ?>
													<span class="wpch-tt__room"><?php echo esc_html( $row['room'] ); ?></span>
												<?php endif; ?>
												<?php if ( '' !== $row['track'] ) : ?>
													<span class="wpch-tt__track"><?php echo esc_html( $row['track'] ); ?></span>
												<?php endif; ?>
											</a>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( array() !== $tweets ) : ?>
				<section class="wpch-event__section">
					<h2 class="wpch-event__section-title"><?php esc_html_e( 'From #WCEU', 'wpcamp-hub' ); ?></h2>
					<div class="wpch-event__tweets">
						<?php
						foreach ( array_slice( $tweets, 0, 4 ) as $tweet ) :
							$t_id     = $tweet->get_id();
							$t_handle = (string) get_post_meta( $t_id, 'wpcamp_author_handle', true );
							$t_name   = (string) get_post_meta( $t_id, 'wpcamp_author_name', true );
							$t_url    = (string) get_post_meta( $t_id, 'wpcamp_tweet_url', true );
							?>
							<article class="wpch-event__tweet">
								<div class="wpch-event__tweet-head">
									<span class="wpch-event__avatar" aria-hidden="true"><?php echo esc_html( wpcamp_hub_initials( $t_name ) ); ?></span>
									<div>
										<div class="wpch-event__tweet-name"><?php echo esc_html( '' !== $t_name ? $t_name : get_the_title( $t_id ) ); ?></div>
										<?php if ( '' !== $t_handle ) : ?>
											<div class="wpch-event__tweet-handle">@<?php echo esc_html( $t_handle ); ?></div>
										<?php endif; ?>
									</div>
								</div>
								<p class="wpch-event__tweet-text"><?php echo esc_html( get_the_content( null, false, $t_id ) ); ?></p>
								<?php if ( '' !== $t_url ) : ?>
									<a class="wpch-event__tweet-link" href="<?php echo esc_url( $t_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'View on X', 'wpcamp-hub' ); ?>
									</a>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

		</div>

	</main>
	<?php
endwhile;

get_footer();
