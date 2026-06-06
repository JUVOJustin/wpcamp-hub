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
	$start        = $event ? $event->get_start() : null;
	$end          = $event ? $event->get_end() : null;
	$location     = $event ? $event->get_location() : '';
	$official_url = $event ? $event->get_official_url() : '';

	$when = '';
	if ( $start instanceof DateTimeImmutable ) {
		$when = wp_date( 'l j F · H:i', $start->getTimestamp() );
		if ( $end instanceof DateTimeImmutable ) {
			$when .= '–' . wp_date( 'H:i', $end->getTimestamp() );
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

	// Map data: every event that has coordinates, with its rooms -> sessions.
	// Clicking an event marker reveals that event's rooms in the sidebar.
	$map_events = array();
	$all_event_posts = get_posts(
		array(
			'post_type'      => 'wpcamp_event',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		)
	);
	foreach ( $all_event_posts as $ev_id ) {
		$ev      = Event::from( $ev_id );
		$ev_geo  = $ev->get_coordinates();
		if ( null === $ev_geo ) {
			continue;
		}

		// Group this event's sessions by room.
		$rooms = array();
		foreach ( $ev->get_sessions() as $ev_session ) {
			$sid       = $ev_session->get_id();
			$track     = $ev_session->get_track();
			$ev_time   = $ev_session->get_start_time();
			$ev_ts     = '' !== $ev_time ? strtotime( $ev_time ) : 0;
			$room_name = (string) get_post_meta( $sid, 'wpcamp_room', true );
			$room_name = '' !== $room_name ? $room_name : __( 'Unassigned', 'wpcamp-hub' );

			$rooms[ $room_name ][] = array(
				'title' => get_the_title( $sid ),
				'track' => $track ? $track->get_name() : '',
				'color' => $track ? $track->get_color() : '#3858e9',
				'time'  => $ev_ts ? wp_date( 'D H:i', $ev_ts ) : '',
				'url'   => (string) get_permalink( $sid ),
			);
		}

		$room_list      = array();
		$session_count  = 0;
		foreach ( $rooms as $room_name => $room_sessions ) {
			$session_count += count( $room_sessions );
			$room_list[]    = array(
				'name'     => $room_name,
				'sessions' => $room_sessions,
			);
		}

		$ev_source       = $ev->get_source();
		$ev_source_meta  = isset( $wpch_sources[ $ev_source ] ) ? $wpch_sources[ $ev_source ] : $wpch_sources['community'];

		$map_events[] = array(
			'id'       => $ev_id,
			'current'  => $ev_id === get_the_ID(),
			'title'    => get_the_title( $ev_id ),
			'location' => $ev->get_location(),
			'lat'      => $ev_geo['latitude'],
			'lng'      => $ev_geo['longitude'],
			'color'    => $ev_source_meta['color'],
			'label'    => $ev_source_meta['label'],
			'icon'     => 'x' === $ev_source ? 'hash' : 'map-pin',
			'count'    => $session_count,
			'rooms'    => $room_list,
		);
	}
	wp_reset_postdata();
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
				<?php $wpch_attendees_url = class_exists( \WPCAMP_HUB\Frontend\Attendees_Page::class ) ? \WPCAMP_HUB\Frontend\Attendees_Page::url_for( get_the_ID() ) : ''; ?>
				<section class="wpch-event__section">
					<div class="wpch-event__section-head">
						<h2 class="wpch-event__section-title"><?php esc_html_e( 'Who’s going', 'wpcamp-hub' ); ?></h2>
						<?php if ( '' !== $wpch_attendees_url ) : ?>
							<a class="wpch-event__section-link" href="<?php echo esc_url( $wpch_attendees_url ); ?>">
								<?php esc_html_e( 'View all attendees', 'wpcamp-hub' ); ?>
							</a>
						<?php endif; ?>
					</div>
					<div class="wpch-event__attendees">
						<div class="wpch-event__stack">
							<?php
							foreach ( array_slice( $attendees, 0, 6 ) as $person ) :
								$user_id = $person->get_id();
								$name    = (string) ( $person->get_wp_entity()->display_name ?? '' );
								$avatar  = get_avatar(
									$user_id,
									76,
									'',
									$name,
									array(
										'class'         => 'wpch-event__avatar-img',
										'extra_attr'    => 'title="' . esc_attr( $name ) . '"',
										'force_display' => true,
									)
								);
								?>
								<?php if ( '' !== $avatar ) : ?>
									<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns escaped markup. ?>
								<?php else : ?>
									<span class="wpch-event__avatar" title="<?php echo esc_attr( $name ); ?>">
										<?php echo esc_html( wpcamp_hub_initials( $name ) ); ?>
									</span>
								<?php endif; ?>
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
					<?php
					$wpch_speakers_url = class_exists( \WPCAMP_HUB\Frontend\Speakers_Page::class ) ? \WPCAMP_HUB\Frontend\Speakers_Page::url_for( get_the_ID() ) : '';
					$wpch_sessions_url = class_exists( \WPCAMP_HUB\Frontend\Sessions_Page::class ) ? \WPCAMP_HUB\Frontend\Sessions_Page::url_for( get_the_ID() ) : '';
					?>
					<div class="wpch-sv__head">
						<h2 class="wpch-event__section-title"><?php esc_html_e( 'Sessions', 'wpcamp-hub' ); ?></h2>
						<?php if ( '' !== $wpch_sessions_url ) : ?>
							<a class="wpch-event__section-link" href="<?php echo esc_url( $wpch_sessions_url ); ?>">
								<?php esc_html_e( 'View all sessions', 'wpcamp-hub' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( '' !== $wpch_speakers_url ) : ?>
							<a class="wpch-event__section-link" href="<?php echo esc_url( $wpch_speakers_url ); ?>">
								<?php esc_html_e( 'View all speakers', 'wpcamp-hub' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( null !== $coords ) : ?>
							<div class="wpch-sv__switch" role="tablist">
								<button type="button" class="wpch-sv__btn is-active" data-view="list" aria-selected="true"><?php esc_html_e( 'List', 'wpcamp-hub' ); ?></button>
								<button type="button" class="wpch-sv__btn" data-view="map" aria-selected="false"><?php esc_html_e( 'Map', 'wpcamp-hub' ); ?></button>
							</div>
						<?php endif; ?>
					</div>

					<?php
					// The single-event page shows only a short preview of the
					// programme — the full schedule + timetable live on the
					// /sessions/ subpage. Cap the list and link out.
					$wpch_preview_count = 5;
					$wpch_preview_rows  = array_slice( $session_rows, 0, $wpch_preview_count );
					?>
					<?php // ---- List view (preview) ---- ?>
					<div class="wpch-sv__view wpch-sv__view--list is-active" data-view="list">
						<ul class="wpch-event__sessions">
							<?php foreach ( $wpch_preview_rows as $row ) : ?>
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
						<?php if ( '' !== $wpch_sessions_url && count( $session_rows ) > $wpch_preview_count ) : ?>
							<a class="wpch-event__section-link wpch-event__sessions-more" href="<?php echo esc_url( $wpch_sessions_url ); ?>">
								<?php
								/* translators: %d: total number of sessions. */
								echo esc_html( sprintf( __( 'View all %d sessions', 'wpcamp-hub' ), count( $session_rows ) ) );
								?>
							</a>
						<?php endif; ?>
					</div>

					<?php // ---- Map view (prototype split: map + venue sidebar) ---- ?>
					<?php if ( null !== $coords ) : ?>
						<div class="wpch-sv__view wpch-sv__view--map" data-view="map">
							<div class="wpch-sv__split">
								<div
									class="wpch-sv__map"
									id="wpch-event-map"
									data-events="<?php echo esc_attr( (string) wp_json_encode( $map_events ) ); ?>"
									data-current="<?php echo esc_attr( (string) get_the_ID() ); ?>"
								></div>
								<aside class="wpch-sv__sidebar" id="wpch-event-sidebar">
									<div class="wpch-sv__sidebar-empty">
										<?php esc_html_e( 'Select an event on the map to see its rooms and sessions.', 'wpcamp-hub' ); ?>
									</div>
								</aside>
							</div>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( array() !== $tweets ) : ?>
				<?php $wpch_tweets_url = class_exists( \WPCAMP_HUB\Frontend\Tweets_Page::class ) ? \WPCAMP_HUB\Frontend\Tweets_Page::url_for( get_the_ID() ) : ''; ?>
				<section class="wpch-event__section">
					<div class="wpch-event__section-head">
						<h2 class="wpch-event__section-title"><?php esc_html_e( 'From #WCEU', 'wpcamp-hub' ); ?></h2>
						<?php if ( '' !== $wpch_tweets_url ) : ?>
							<a class="wpch-event__section-link" href="<?php echo esc_url( $wpch_tweets_url ); ?>">
								<?php esc_html_e( 'View all posts', 'wpcamp-hub' ); ?>
							</a>
						<?php endif; ?>
					</div>
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
