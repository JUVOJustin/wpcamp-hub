<?php
/**
 * Tweets archive — the community feed.
 *
 * Two-column layout (mirrors the prototype Feed page): a sticky left filter rail
 * — categories with counts, events, and sort — beside the timeline. The timeline
 * cards are rendered by the plugin's Tweet_Feed so the initial server render and
 * the AJAX responses are identical. Filtering/pagination is AJAX; the <noscript>
 * fallback keeps it usable without JS.
 *
 * @package wpcamp-hub
 */

use WPCAMP_HUB\Frontend\Tweet_Feed;
use WPCAMP_HUB\Data\Feed_Category;
use WPCAMP_HUB\Data\Data_Structure;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

$has_feed = class_exists( Tweet_Feed::class );

// ---- Filter inputs (from the query string for no-JS / shareable URLs) --------
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public archive filters.
$active_cat    = isset( $_GET['category'] ) ? sanitize_key( wp_unslash( (string) $_GET['category'] ) ) : 'all';
$active_event  = isset( $_GET['event'] ) ? (int) $_GET['event'] : 0;
$active_search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
$active_sort   = isset( $_GET['sort'] ) && 'oldest' === $_GET['sort'] ? 'oldest' : 'newest';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// ---- Rail data: category list with counts, and the event options -------------
$categories = array();
$counts     = $has_feed ? Feed_Category::counts() : array( 'all' => 0 );
if ( $has_feed ) {
	$all_meta = Feed_Category::all();

	$categories[] = array(
		'key'   => 'all',
		'label' => __( 'All activity', 'wpcamp-hub' ),
		'color' => 'ink',
		'icon'  => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>',
		'count' => (int) ( $counts['all'] ?? 0 ),
	);
	foreach ( Feed_Category::order() as $cat_key ) {
		$meta         = $all_meta[ $cat_key ];
		$categories[] = array(
			'key'   => $cat_key,
			'label' => $meta['label'],
			'color' => $meta['color'],
			'icon'  => $meta['icon'],
			'count' => (int) ( $counts[ $cat_key ] ?? 0 ),
		);
	}
}

$event_options = array();
if ( $has_feed ) {
	$event_posts = get_posts(
		array(
			'post_type'           => Data_Structure::POST_TYPE_EVENT,
			'posts_per_page'      => -1,
			'post_status'         => 'publish',
			'orderby'             => 'title',
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
		)
	);
	foreach ( $event_posts as $event_post ) {
		$event_options[] = array(
			'id'    => (int) $event_post->ID,
			'title' => get_the_title( $event_post ),
		);
	}
}

/**
 * Render an inline rail icon from a Lucide path string.
 *
 * @param string $path Inner SVG path markup.
 * @return string SVG element.
 */
$rail_icon = static function ( string $path ): string {
	return sprintf(
		'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		$path
	);
};

// ---- Initial page render (page 1, honouring any URL filters) -----------------
$initial_filters = array(
	'category' => $active_cat,
	'event'    => $active_event,
	'search'   => $active_search,
	'sort'     => $active_sort,
	'paged'    => 1,
);
$initial_query = $has_feed ? new WP_Query( Tweet_Feed::query_args( $initial_filters ) ) : null;
$initial_cards = $initial_query instanceof WP_Query ? Tweet_Feed::render_cards( $initial_query ) : '';
$found         = $initial_query instanceof WP_Query ? (int) $initial_query->found_posts : 0;
$max_pages     = $initial_query instanceof WP_Query ? (int) $initial_query->max_num_pages : 0;
wp_reset_postdata();
?>
<main id="content" class="site-content wpch-tweets-archive">

	<section class="wpch-intro">
		<div class="wpch-intro__inner">
			<div class="wpch-intro__eyebrow"><?php esc_html_e( 'Community feed', 'wpcamp-hub' ); ?></div>
			<h1 class="wpch-intro__title"><?php esc_html_e( 'What the community’s saying', 'wpcamp-hub' ); ?></h1>
			<p class="wpch-intro__lead">
				<?php esc_html_e( 'Live activity from around #WCEU — who’s coming, who wants to meet, and every side event, dinner and meetup people are putting together.', 'wpcamp-hub' ); ?>
			</p>
		</div>
	</section>

	<section class="wpch-feed-archive">
		<div class="wrap wpch-feed-grid" data-feed-root>

			<?php // ---- left filter rail ---- ?>
			<aside class="wpch-feed-rail" data-feed-filters>
				<div class="wpch-feed-rail__label"><?php esc_html_e( 'Filter by', 'wpcamp-hub' ); ?></div>

				<div class="wpch-feed-rail__list" role="group" aria-label="<?php esc_attr_e( 'Filter by type', 'wpcamp-hub' ); ?>">
					<?php
					foreach ( $categories as $cat ) :
						$is_active = $active_cat === $cat['key'];
						?>
						<button
							type="button"
							class="wpch-feed-stat wpch-feed-stat--<?php echo esc_attr( $cat['color'] ); ?><?php echo $is_active ? ' is-active' : ''; ?>"
							data-category="<?php echo esc_attr( $cat['key'] ); ?>"
							aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
						>
							<span class="wpch-feed-stat__dot" aria-hidden="true"><?php echo $rail_icon( $cat['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
							<span class="wpch-feed-stat__label"><?php echo esc_html( $cat['label'] ); ?></span>
							<span class="wpch-feed-stat__count" data-count-for="<?php echo esc_attr( $cat['key'] ); ?>"><?php echo esc_html( number_format_i18n( $cat['count'] ) ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>

				<?php if ( array() !== $event_options ) : ?>
					<div class="wpch-feed-rail__group">
						<div class="wpch-feed-rail__label"><?php esc_html_e( 'Event', 'wpcamp-hub' ); ?></div>
						<select class="wpch-feed-rail__select" data-feed-event name="event">
							<option value="0"><?php esc_html_e( 'All events', 'wpcamp-hub' ); ?></option>
							<?php foreach ( $event_options as $opt ) : ?>
								<option value="<?php echo esc_attr( (string) $opt['id'] ); ?>" <?php selected( $active_event, $opt['id'] ); ?>>
									<?php echo esc_html( $opt['title'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<div class="wpch-feed-rail__group">
					<div class="wpch-feed-rail__label"><?php esc_html_e( 'Sort', 'wpcamp-hub' ); ?></div>
					<select class="wpch-feed-rail__select" data-feed-sort name="sort">
						<option value="newest" <?php selected( $active_sort, 'newest' ); ?>><?php esc_html_e( 'Newest first', 'wpcamp-hub' ); ?></option>
						<option value="oldest" <?php selected( $active_sort, 'oldest' ); ?>><?php esc_html_e( 'Oldest first', 'wpcamp-hub' ); ?></option>
					</select>
				</div>

				<noscript>
					<?php // Hidden carriers + submit so filtering works without JS. ?>
					<input type="hidden" name="category" value="<?php echo esc_attr( $active_cat ); ?>" />
					<button type="submit" class="wpch-feed-rail__submit"><?php esc_html_e( 'Apply filters', 'wpcamp-hub' ); ?></button>
				</noscript>
			</aside>

			<?php // ---- timeline ---- ?>
			<div class="wpch-feed-timeline">
				<label class="wpch-feed-search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search the conversation', 'wpcamp-hub' ); ?></span>
					<svg class="wpch-feed-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
					<input
						type="search"
						name="q"
						class="wpch-feed-search__input"
						data-feed-search
						value="<?php echo esc_attr( $active_search ); ?>"
						placeholder="<?php esc_attr_e( 'Search the conversation', 'wpcamp-hub' ); ?>"
					/>
				</label>

				<p class="wpch-feed-timeline__count" data-feed-count aria-live="polite">
					<?php
					/* translators: %s: number of posts. */
					echo esc_html( sprintf( _n( '%s post', '%s posts', $found, 'wpcamp-hub' ), number_format_i18n( $found ) ) );
					?>
				</p>

				<div
					class="wpch-feed-timeline__grid"
					data-feed-grid
					data-page="1"
					data-max-pages="<?php echo esc_attr( (string) max( 1, $max_pages ) ); ?>"
				><?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card markup escaped per field in the plugin renderer.
					echo $initial_cards;
				?></div>

				<p class="wpch-feed-timeline__empty" data-feed-empty<?php echo '' !== $initial_cards ? ' hidden' : ''; ?>>
					<?php esc_html_e( 'Nothing matches that yet. Try another filter.', 'wpcamp-hub' ); ?>
				</p>

				<div class="wpch-feed-timeline__more">
					<button
						type="button"
						class="btn btn-secondary"
						data-feed-more
						<?php echo $max_pages > 1 ? '' : 'hidden'; ?>
					>
						<?php esc_html_e( 'Load more', 'wpcamp-hub' ); ?>
					</button>
				</div>
			</div>

		</div>
	</section>

</main>
<?php
get_footer();
