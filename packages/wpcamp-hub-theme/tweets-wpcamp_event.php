<?php
/**
 * Tweets subpage for a single event — /event/<slug>/tweets/.
 *
 * Lists the community tweets related to this event (event → tweets), reusing
 * the plugin's canonical feed-card markup ({@see Tweet_Feed::render_card()}).
 *
 * Routed here by the plugin's Tweets_Page (rewrite endpoint) via
 * `template_include` → `locate_template()`. Requires the WPCamp Hub plugin for
 * the Event/Tweet entities.
 *
 * @package wpcamp-hub
 */

use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Tweet;
use WPCAMP_HUB\Frontend\Tweet_Feed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wpch_event_id = get_queried_object_id();
$wpch_event    = class_exists( Event::class ) ? Event::from( $wpch_event_id ) : null;
$wpch_tweets   = $wpch_event ? $wpch_event->get_tweets() : array();
$wpch_title    = get_the_title( $wpch_event_id );
$wpch_has_feed = class_exists( Tweet_Feed::class );
?>

<main class="wpch-attendees wpch-event-tweets">
	<section class="wpch-attendees__hero">
		<div class="wpch-attendees__inner">
			<div class="wpch-attendees__eyebrow"><?php echo esc_html( $wpch_title ); ?></div>
			<h1 class="wpch-attendees__title"><?php esc_html_e( 'From the community', 'wpcamp-hub' ); ?></h1>
			<p class="wpch-attendees__lead">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of tweets. */
						_n( '%d post about this event.', '%d posts about this event.', count( $wpch_tweets ), 'wpcamp-hub' ),
						count( $wpch_tweets )
					)
				);
				esc_html_e( ' What attendees are saying on the social web.', 'wpcamp-hub' );
				?>
			</p>
		</div>
	</section>

	<section class="wpch-attendees__body">
		<div class="wpch-attendees__inner">
			<div class="wpch-attendees__toolbar">
				<h2 class="wpch-attendees__subtitle"><?php esc_html_e( 'All posts', 'wpcamp-hub' ); ?></h2>
				<div class="wpch-attendees__search">
					<?php echo wpcamp_hub_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<input
						type="search"
						class="wpch-attendees__search-input"
						placeholder="<?php esc_attr_e( 'Search posts', 'wpcamp-hub' ); ?>"
						aria-label="<?php esc_attr_e( 'Search posts', 'wpcamp-hub' ); ?>"
					/>
				</div>
			</div>

			<?php if ( $wpch_has_feed && array() !== $wpch_tweets ) : ?>
				<div class="wpch-feed-timeline">
					<div class="wpch-feed-timeline__grid">
						<?php
						foreach ( $wpch_tweets as $wpch_tweet ) :
							if ( ! $wpch_tweet instanceof Tweet ) :
								continue;
							endif;
							$haystack = strtolower( trim( $wpch_tweet->get_author_name() . ' ' . $wpch_tweet->get_author_handle() . ' ' . $wpch_tweet->get_text() ) );
							?>
							<div class="wpch-feed-item" data-search="<?php echo esc_attr( $haystack ); ?>">
								<?php echo Tweet_Feed::render_card( $wpch_tweet ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card markup is escaped within Tweet_Feed. ?>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="wpch-attendees__empty" hidden>
						<?php esc_html_e( 'No posts match your search.', 'wpcamp-hub' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="wpch-attendees__empty">
					<?php echo wpcamp_hub_icon( 'hash', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<p><?php esc_html_e( 'No community posts yet for this event.', 'wpcamp-hub' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>

<script>
( function () {
	var root = document.querySelector( '.wpch-event-tweets' );
	if ( ! root ) {
		return;
	}
	var input = root.querySelector( '.wpch-attendees__search-input' );
	var items = Array.prototype.slice.call( root.querySelectorAll( '.wpch-feed-item' ) );
	var empty = root.querySelector( '.wpch-attendees__empty[hidden]' );
	if ( ! input ) {
		return;
	}
	input.addEventListener( 'input', function () {
		var q = input.value.trim().toLowerCase();
		var shown = 0;
		items.forEach( function ( item ) {
			var match = '' === q || ( item.getAttribute( 'data-search' ) || '' ).indexOf( q ) !== -1;
			item.hidden = ! match;
			if ( match ) {
				shown++;
			}
		} );
		if ( empty ) {
			empty.hidden = shown !== 0;
		}
	} );
}() );
</script>

<?php
get_footer();
