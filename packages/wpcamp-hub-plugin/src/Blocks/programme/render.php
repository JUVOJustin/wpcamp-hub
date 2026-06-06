<?php
/**
 * Server render for the Programme Excerpt block.
 *
 * Handles two dynamic options on top of the static markup:
 *  - legendSource = "tracks": build the legend from the wpcamp_track terms.
 *  - contentSource = "sessions": query wpcamp_session and render session cards,
 *    instead of the manually placed InnerBlocks.
 *
 * @package WPCAMP_HUB
 *
 * @var array<string,mixed> $attributes Block attributes.
 * @var string              $content    InnerBlocks (manual cards) markup.
 * @var WP_Block            $block      Block instance.
 */

use WPCAMP_HUB\Data\Track;
use WPCAMP_HUB\Data\Session;
use WPCAMP_HUB\Data\Tweet;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Frontend\Tweet_Feed;
use WPCAMP_HUB\Frontend\Sessions_Page;
use WPCAMP_HUB\Frontend\Tweets_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eyebrow        = isset( $attributes['eyebrow'] ) ? $attributes['eyebrow'] : '';
$heading        = isset( $attributes['heading'] ) ? $attributes['heading'] : '';
$link_label     = isset( $attributes['linkLabel'] ) ? $attributes['linkLabel'] : '';
$link_url       = isset( $attributes['linkUrl'] ) ? $attributes['linkUrl'] : '';
$show_legend    = ! isset( $attributes['showLegend'] ) || $attributes['showLegend'];
$legend_source  = isset( $attributes['legendSource'] ) ? $attributes['legendSource'] : 'manual';
$content_source = isset( $attributes['contentSource'] ) ? $attributes['contentSource'] : 'manual';
$columns        = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
$sessions_count = isset( $attributes['sessionsCount'] ) ? (int) $attributes['sessionsCount'] : 3;
$legend_attr    = isset( $attributes['legend'] ) && is_array( $attributes['legend'] ) ? $attributes['legend'] : array();
$event_id       = isset( $attributes['eventId'] ) ? (int) $attributes['eventId'] : 0;

// Resolve the linked event, when set and valid.
$event = null;
if ( $event_id > 0 && class_exists( Event::class ) && Event::get_post_type() === get_post_type( $event_id ) ) {
	$event = Event::from( $event_id );
}

// Tracks actually present in the rendered session cards (name => colour),
// populated while building the cards below. The dynamic legend is limited to
// these so it only reflects what the query shows.
$visible_tracks = array();

/**
 * Build the legend item list.
 *
 * For the "tracks" source the legend is limited to the tracks that appear in
 * the rendered session cards ($visible_tracks), preserving the canonical track
 * order. The manual source lists exactly what the author configured.
 *
 * @param array<string,string> $visible_tracks Tracks present in the rendered cards (name => colour).
 * @return array<int,array{name:string,color:string}>
 */
$legend_items = static function ( array $visible_tracks ) use ( $legend_source, $legend_attr, $content_source ): array {
	if ( 'tracks' === $legend_source ) {
		$tracks = Track::all();

		// Limit to the tracks present in the rendered cards when those cards are
		// sessions; other content modes have no session tracks to scope against,
		// so the legend describes the full taxonomy.
		if ( 'sessions' === $content_source ) {
			$tracks = array_values(
				array_filter(
					$tracks,
					static fn( Track $track ): bool => isset( $visible_tracks[ $track->get_name() ] )
				)
			);
		}

		return array_map(
			static fn( Track $track ): array => array(
				'name'  => $track->get_name(),
				'color' => $track->get_color(),
			),
			$tracks
		);
	}

	return array_map(
		static fn( $item ): array => array(
			'name'  => isset( $item['name'] ) ? (string) $item['name'] : '',
			'color' => isset( $item['color'] ) ? (string) $item['color'] : '#3858E9',
		),
		$legend_attr
	);
};

/**
 * Render a single session card from a Session entity.
 *
 * @param Session $session Session wrapper.
 * @return string Card HTML.
 */
$render_session_card = static function ( Session $session ): string {
	$track   = $session->get_track();
	$accent  = $track ? $track->get_color() : '#3858E9';
	$t_label = $track ? $track->get_name() : '';

	$start = $session->get_start_time();
	$time  = '' !== $start ? wp_date( 'H:i', strtotime( $start ) ) : '';
	$meta  = $time;

	$speakers = $session->get_speaker_names();
	$speaker  = array() !== $speakers ? implode( ', ', $speakers ) : '';

	$url   = (string) get_post_meta( $session->get_id(), 'wpcamp_official_url', true );
	$title = get_the_title( $session->get_id() );

	ob_start();
	?>
	<article class="wp-block-wpcamp-hub-session-card wpch-card" style="--wpch-card-accent:<?php echo esc_attr( $accent ); ?>">
		<?php if ( '' !== $url ) : ?>
			<a class="wpch-card__link" href="<?php echo esc_url( $url ); ?>">
		<?php endif; ?>
		<div class="wpch-card__head" aria-hidden="true"></div>
		<div class="wpch-card__body">
			<div class="wpch-card__top">
				<span class="wpch-card__type"><?php echo esc_html__( 'Session', 'wpcamp-hub' ); ?></span>
				<?php if ( '' !== $t_label ) : ?>
					<span class="wpch-card__track">
						<span class="wpch-card__dot" aria-hidden="true"></span>
						<span class="wpch-card__track-label"><?php echo esc_html( $t_label ); ?></span>
					</span>
				<?php endif; ?>
			</div>
			<h3 class="wpch-card__title"><?php echo esc_html( $title ); ?></h3>
			<p class="wpch-card__blurb"><?php echo esc_html( get_the_excerpt( $session->get_id() ) ); ?></p>
			<div class="wpch-card__foot">
				<span class="wpch-card__avatar" aria-hidden="true"></span>
				<div class="wpch-card__person">
					<div class="wpch-card__speaker-name"><?php echo esc_html( $speaker ); ?></div>
					<div class="wpch-card__meta"><?php echo esc_html( $meta ); ?></div>
				</div>
			</div>
		</div>
		<?php if ( '' !== $url ) : ?>
			</a>
		<?php endif; ?>
	</article>
	<?php
	return (string) ob_get_clean();
};

$limit = $sessions_count > 0 ? $sessions_count : 3;

// ---- cards markup ----------------------------------------------------------
if ( 'sessions' === $content_source ) {
	// Sessions: scoped to the linked event when set, otherwise the latest
	// sessions site-wide.
	if ( $event instanceof Event ) {
		$sessions = $event->get_sessions();
		usort(
			$sessions,
			static fn( Session $a, Session $b ): int => strcmp( $a->get_start_time(), $b->get_start_time() )
		);
		$sessions = array_slice( $sessions, 0, $limit );
	} else {
		$query    = new WP_Query(
			array(
				'post_type'           => Data_Structure::POST_TYPE_SESSION,
				'posts_per_page'      => $limit,
				'post_status'         => 'publish',
				'orderby'             => 'meta_value',
				'meta_key'            => 'wpcamp_start_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
			)
		);
		$sessions = array_map( static fn( $p ): Session => Session::from( $p ), $query->posts );
		wp_reset_postdata();
	}

	$cards = '';
	foreach ( $sessions as $session ) {
		$track = $session->get_track();
		if ( null !== $track ) {
			// Track which tracks are actually present, keyed by name so the
			// legend can be limited to what the query renders.
			$visible_tracks[ $track->get_name() ] = $track->get_color();
		}
		$cards .= $render_session_card( $session );
	}
	$grid_inner = $cards;
} elseif ( 'tweets' === $content_source && class_exists( Tweet_Feed::class ) ) {
	// Tweets: scoped to the linked event when set, otherwise the latest tweets
	// site-wide.
	if ( $event instanceof Event ) {
		$tweets = array_slice( $event->get_tweets(), 0, $limit );
	} else {
		$query  = new WP_Query(
			array(
				'post_type'           => Data_Structure::POST_TYPE_TWEET,
				'posts_per_page'      => $limit,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
			)
		);
		$tweets = array_map( static fn( $p ): Tweet => Tweet::from( $p ), $query->posts );
		wp_reset_postdata();
	}

	$cards = '';
	foreach ( $tweets as $tweet ) {
		$cards .= Tweet_Feed::render_card( $tweet );
	}
	$grid_inner = $cards;
} else {
	// Manual mode: use the InnerBlocks output.
	$grid_inner = $content;
}

// Auto-fill the section link (URL + label) to the event's subpage when not set
// manually. The label only auto-adapts while it is empty or still the default
// "All events", so an author-chosen label is preserved.
if ( $event instanceof Event ) {
	$default_label  = __( 'All events', 'wpcamp-hub' );
	$label_is_blank = '' === trim( (string) $link_label ) || $default_label === $link_label;

	if ( 'tweets' === $content_source && class_exists( Tweets_Page::class ) ) {
		if ( '' === $link_url ) {
			$link_url = Tweets_Page::url_for( $event->get_id() );
		}
		if ( $label_is_blank ) {
			$link_label = __( 'All posts', 'wpcamp-hub' );
		}
	} elseif ( 'sessions' === $content_source && class_exists( Sessions_Page::class ) ) {
		if ( '' === $link_url ) {
			$link_url = Sessions_Page::url_for( $event->get_id() );
		}
		if ( $label_is_blank ) {
			$link_label = __( 'All sessions', 'wpcamp-hub' );
		}
	}
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wpch-programme' ) );
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="wpch-programme__inner">
		<div class="wpch-programme__header">
			<div class="wpch-programme__heading-group">
				<?php if ( '' !== $eyebrow ) : ?>
					<div class="wpch-programme__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $heading ) : ?>
					<h2 class="wpch-programme__heading"><?php echo wp_kses_post( $heading ); ?></h2>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $link_label ) : ?>
				<a class="wpch-programme__link" href="<?php echo esc_url( '' !== $link_url ? $link_url : '#' ); ?>">
					<?php echo wp_kses_post( $link_label ); ?><span aria-hidden="true"> &rarr;</span>
				</a>
			<?php endif; ?>
		</div>

		<?php
		if ( $show_legend ) :
			$items = $legend_items( $visible_tracks );
			if ( array() !== $items ) :
				?>
				<div class="wpch-programme__legend">
					<?php foreach ( $items as $item ) : ?>
						<span class="wpch-programme__legend-item">
							<span class="wpch-programme__legend-dot" style="background:<?php echo esc_attr( $item['color'] ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( $item['name'] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
				<?php
			endif;
		endif;
		?>

		<div class="wpch-programme__grid" style="--wpch-programme-cols:<?php echo esc_attr( (string) $columns ); ?>">
			<?php echo $grid_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card markup is escaped per field above; InnerBlocks output is pre-rendered. ?>
		</div>
	</div>
</section>
