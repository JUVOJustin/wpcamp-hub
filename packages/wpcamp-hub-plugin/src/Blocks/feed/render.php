<?php
/**
 * Server render for the Community Feed block.
 *
 * Builds the feed grouped by event hashtag: each event that has curated tweets
 * becomes its own feed, rendered as a grid of feed cards. Card markup mirrors
 * the static `wpcamp-hub/feed-card` block so both share one stylesheet.
 *
 * @package WPCAMP_HUB
 *
 * @var array<string,mixed> $attributes Block attributes.
 * @var string              $content    Saved inner markup (unused — dynamic).
 * @var WP_Block            $block      Block instance.
 */

use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Feed_Category;
use WPCAMP_HUB\Data\Data_Structure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eyebrow          = isset( $attributes['eyebrow'] ) ? (string) $attributes['eyebrow'] : '';
$heading          = isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '';
$link_label       = isset( $attributes['linkLabel'] ) ? (string) $attributes['linkLabel'] : '';
$link_url         = isset( $attributes['linkUrl'] ) ? (string) $attributes['linkUrl'] : '';
$events_count     = isset( $attributes['eventsCount'] ) ? (int) $attributes['eventsCount'] : 3;
$tweets_per_event = isset( $attributes['tweetsPerEvent'] ) ? (int) $attributes['tweetsPerEvent'] : 4;
$columns          = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
$show_empty       = isset( $attributes['showEmpty'] ) && $attributes['showEmpty'];

$events_count     = $events_count > 0 ? $events_count : 3;
$tweets_per_event = $tweets_per_event > 0 ? $tweets_per_event : 4;
$columns          = max( 1, min( 4, $columns ) );

/**
 * Render a single feed card from a tweet, mirroring the feed-card block markup.
 *
 * @param \WPCAMP_HUB\Data\Tweet $tweet Tweet wrapper.
 * @return string Card HTML.
 */
$render_feed_card = static function ( $tweet ): string {
	$meta = Feed_Category::meta_for_tweet( $tweet );

	$name   = $tweet->get_author_name();
	$handle = $tweet->get_author_handle();
	$url    = $tweet->get_url();
	$text   = $tweet->get_text();
	$stamp  = $tweet->get_timestamp();

	// Build "@handle · 2h"-style sub-line.
	$handle_line = '' !== $handle ? '@' . $handle : '';
	if ( $stamp instanceof \DateTimeImmutable ) {
		$ago         = human_time_diff( $stamp->getTimestamp(), time() );
		$ago_text    = sprintf( /* translators: %s: human time difference, e.g. "2 hours". */ __( '%s ago', 'wpcamp-hub' ), $ago );
		$handle_line = '' !== $handle_line ? $handle_line . ' · ' . $ago_text : $ago_text;
	}

	$icon = sprintf(
		'<svg class="wpch-feed__pill-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		$meta['icon']
	);

	$avatar = get_avatar(
		'',
		38,
		'',
		$name,
		array(
			'class'         => 'wpch-feed__avatar',
			'force_display' => true,
		)
	);
	if ( '' === $avatar ) {
		$avatar = '<span class="wpch-feed__avatar" aria-hidden="true"></span>';
	}

	// A custom term colour is applied inline (with a derived tint); otherwise
	// the preset modifier class supplies the colour.
	$style = '';
	if ( '' !== $meta['color_hex'] ) {
		$style = sprintf(
			'--wpch-feed-color:%1$s;--wpch-feed-tint:color-mix(in srgb, %1$s 12%%, #fff);',
			$meta['color_hex']
		);
	}

	ob_start();
	?>
	<article
		class="wp-block-wpcamp-hub-feed-card wpch-feed wpch-feed--<?php echo esc_attr( $meta['color'] ); ?>"
		<?php echo '' !== $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>
	>
		<?php if ( '' !== $url ) : ?>
			<a class="wpch-feed__link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
		<?php endif; ?>
		<div class="wpch-feed__head">
			<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns escaped markup. ?>
			<div class="wpch-feed__person">
				<div class="wpch-feed__author"><?php echo esc_html( $name ); ?></div>
				<?php if ( '' !== $handle_line ) : ?>
					<div class="wpch-feed__handle"><?php echo esc_html( $handle_line ); ?></div>
				<?php endif; ?>
			</div>
		</div>

		<p class="wpch-feed__text"><?php echo esc_html( $text ); ?></p>

		<span class="wpch-feed__pill">
			<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG built from the category map. ?>
			<?php echo esc_html( $meta['label'] ); ?>
		</span>
		<?php if ( '' !== $url ) : ?>
			</a>
		<?php endif; ?>
	</article>
	<?php
	return (string) ob_get_clean();
};

// ---- Gather events that have tweets -----------------------------------------
$event_posts = get_posts(
	array(
		'post_type'           => Data_Structure::POST_TYPE_EVENT,
		'posts_per_page'      => -1,
		'post_status'         => 'publish',
		'orderby'             => 'meta_value',
		'meta_key'            => 'wpcamp_date_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'order'               => 'ASC',
		'ignore_sticky_posts' => true,
	)
);

$groups = array();
foreach ( $event_posts as $event_post ) {
	if ( count( $groups ) >= $events_count ) {
		break;
	}

	$event  = Event::from( $event_post );
	$tweets = $event->get_tweets();

	if ( array() === $tweets && ! $show_empty ) {
		continue;
	}

	$cards = '';
	foreach ( array_slice( $tweets, 0, $tweets_per_event ) as $tweet ) {
		$cards .= $render_feed_card( $tweet );
	}

	$groups[] = array(
		'event'   => $event,
		'hashtag' => '#' . preg_replace( '/[^A-Za-z0-9]/', '', get_the_title( $event->get_id() ) ),
		'count'   => count( $tweets ),
		'cards'   => $cards,
	);
}

if ( array() === $groups ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wpch-feeds' ) );
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="wpch-feeds__inner">
		<?php if ( '' !== $eyebrow || '' !== $heading || '' !== $link_label ) : ?>
			<div class="wpch-feeds__header">
				<div class="wpch-feeds__heading-group">
					<?php if ( '' !== $eyebrow ) : ?>
						<div class="wpch-feeds__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== $heading ) : ?>
						<h2 class="wpch-feeds__heading"><?php echo wp_kses_post( $heading ); ?></h2>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $link_label ) : ?>
					<a class="wpch-feeds__link" href="<?php echo esc_url( '' !== $link_url ? $link_url : '#' ); ?>">
						<?php echo wp_kses_post( $link_label ); ?><span aria-hidden="true"> &rarr;</span>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php foreach ( $groups as $group ) : ?>
			<?php
			$event     = $group['event'];
			$event_id  = $event->get_id();
			$event_url = get_permalink( $event_id );
			$location  = $event->get_location();
			$start     = $event->get_start();
			$when      = $start instanceof \DateTimeImmutable ? wp_date( 'j M', $start->getTimestamp() ) : '';
			$meta_bits = array_filter( array( $when, $location ) );
			?>
			<div class="wpch-feeds__group">
				<div class="wpch-feeds__group-head">
					<h3 class="wpch-feeds__group-title">
						<a href="<?php echo esc_url( $event_url ); ?>"><?php echo esc_html( $group['hashtag'] ); ?></a>
					</h3>
					<div class="wpch-feeds__group-meta">
						<span class="wpch-feeds__group-event"><?php echo esc_html( get_the_title( $event_id ) ); ?></span>
						<?php if ( array() !== $meta_bits ) : ?>
							<span class="wpch-feeds__group-sep" aria-hidden="true">·</span>
							<span class="wpch-feeds__group-when"><?php echo esc_html( implode( ' · ', $meta_bits ) ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( '' !== $group['cards'] ) : ?>
					<div class="wpch-feeds__grid" style="--wpch-feeds-cols:<?php echo esc_attr( (string) $columns ); ?>">
						<?php echo $group['cards']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card markup is escaped per field above. ?>
					</div>
				<?php else : ?>
					<p class="wpch-feeds__empty"><?php esc_html_e( 'No posts for this event yet.', 'wpcamp-hub' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
