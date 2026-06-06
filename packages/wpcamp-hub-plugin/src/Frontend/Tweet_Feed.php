<?php
/**
 * Tweet feed — the community archive: filtered, AJAX-paginated tweet cards.
 *
 * Owns the canonical tweet-card markup so the `archive-wpcamp_tweet.php` theme
 * template and the AJAX endpoint render identical cards. Card design mirrors the
 * `wpcamp-hub/feed-card` block (the `wpch-feed` classes).
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Frontend;

use WPCAMP_HUB\Data\Tweet;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Feed_Category;
use WPCAMP_HUB\Data\Data_Structure;

/**
 * Renders and serves the community tweet feed.
 */
class Tweet_Feed {

	/**
	 * AJAX action name (used for both wp_ajax_ and wp_ajax_nopriv_).
	 */
	public const AJAX_ACTION = 'wpcamp_tweet_feed';

	/**
	 * Cards shown per page / AJAX batch.
	 */
	public const PER_PAGE = 12;

	/**
	 * Build the Twitter/X profile-image URL for a handle.
	 *
	 * Uses unavatar.io, which resolves and proxies the account's current X
	 * avatar (with its own fallback) — so no API token or stored image is needed.
	 *
	 * @param string $handle Author handle (with or without a leading "@").
	 * @return string Profile image URL, or '' when the handle is empty/invalid.
	 */
	public static function twitter_avatar_url( string $handle ): string {
		$handle = ltrim( trim( $handle ), '@' );
		// X usernames are alphanumeric + underscore; guard against anything else.
		if ( '' === $handle || ! preg_match( '/^[A-Za-z0-9_]+$/', $handle ) ) {
			return '';
		}

		return sprintf( 'https://unavatar.io/x/%s', rawurlencode( $handle ) );
	}

	/**
	 * Build the WP_Query arguments for a feed request.
	 *
	 * @param array<string,mixed> $filters Raw filter input (category, label, event, search, sort, paged).
	 * @return array<string,mixed> WP_Query args.
	 */
	public static function query_args( array $filters ): array {
		$category = isset( $filters['category'] ) ? sanitize_key( (string) $filters['category'] ) : '';
		$label    = isset( $filters['label'] ) ? sanitize_title( (string) $filters['label'] ) : '';
		$event    = isset( $filters['event'] ) ? (int) $filters['event'] : 0;
		$search   = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
		$sort     = isset( $filters['sort'] ) && 'oldest' === $filters['sort'] ? 'ASC' : 'DESC';
		$paged    = isset( $filters['paged'] ) ? max( 1, (int) $filters['paged'] ) : 1;

		$args = array(
			'post_type'           => Data_Structure::POST_TYPE_TWEET,
			'post_status'         => 'publish',
			'posts_per_page'      => self::PER_PAGE,
			'paged'               => $paged,
			'orderby'             => 'date',
			'order'               => $sort,
			'ignore_sticky_posts' => true,
		);

		// Category filter — the rail filters by design category, which maps to a
		// set of tweet_label terms. A category with no mapped terms (or an
		// explicit label) falls through to the single-label branch below.
		$term_slugs = array();
		if ( '' !== $category && 'all' !== $category ) {
			$term_slugs = Feed_Category::labels_for_category( $category );
		} elseif ( '' !== $label ) {
			$term_slugs = array( $label );
		}

		if ( array() !== $term_slugs ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Data_Structure::TAXONOMY_TWEET_LABEL,
					'field'    => 'slug',
					'terms'    => $term_slugs,
				),
			);
		}

		if ( $event > 0 && Data_Structure::POST_TYPE_EVENT === get_post_type( $event ) ) {
			// Tweet ↔ event links live in the relationship map, not post meta, so
			// resolve the event's tweet IDs and constrain the query to them.
			$tweet_ids = array_map(
				static fn( Tweet $tweet ): int => $tweet->get_id(),
				Event::from( $event )->get_tweets()
			);

			// No tweets for this event → force an empty result set.
			$args['post__in'] = array() !== $tweet_ids ? $tweet_ids : array( 0 );
		}

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		return $args;
	}

	/**
	 * Render every card in a query into a single HTML string.
	 *
	 * @param \WP_Query $query Tweet query.
	 * @return string Concatenated card markup (empty string when no posts).
	 */
	public static function render_cards( \WP_Query $query ): string {
		if ( ! $query->have_posts() ) {
			return '';
		}

		$html = '';
		foreach ( $query->posts as $post ) {
			$html .= self::render_card( Tweet::from( $post ) );
		}

		return $html;
	}

	/**
	 * Render a single tweet as a feed card.
	 *
	 * @param Tweet $tweet Tweet wrapper.
	 * @return string Card HTML.
	 */
	public static function render_card( Tweet $tweet ): string {
		$meta = Feed_Category::meta_for_tweet( $tweet );

		$name   = $tweet->get_author_name();
		$handle = $tweet->get_author_handle();
		$url    = $tweet->get_url();
		$text   = $tweet->get_text();
		$stamp  = $tweet->get_timestamp();

		// We rarely have a real display name, so the primary line is the handle
		// (falling back to the name/title only when no handle is stored). The
		// subline then carries just the relative time.
		$display = '' !== $handle ? '@' . $handle : $name;

		$time_line = '';
		if ( $stamp instanceof \DateTimeImmutable ) {
			$ago = human_time_diff( $stamp->getTimestamp(), time() );
			/* translators: %s: human time difference, e.g. "2 hours". */
			$time_line = sprintf( __( '%s ago', 'wpcamp-hub' ), $ago );
		}

		$icon = sprintf(
			'<svg class="wpch-feed__pill-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
			$meta['icon']
		);

		// Prefer the author's Twitter/X profile image (derived from the handle);
		// fall back to get_avatar(), then a blank placeholder.
		$twitter_avatar = self::twitter_avatar_url( $handle );
		if ( '' !== $twitter_avatar ) {
			$avatar = sprintf(
				'<img class="wpch-feed__avatar" src="%1$s" alt="%2$s" width="38" height="38" loading="lazy" decoding="async" referrerpolicy="no-referrer" />',
				esc_url( $twitter_avatar ),
				esc_attr( $display )
			);
		} else {
			$avatar = get_avatar(
				'',
				38,
				'',
				$display,
				array(
					'class'         => 'wpch-feed__avatar',
					'force_display' => true,
				)
			);
		}
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
					<div class="wpch-feed__author"><?php echo esc_html( $display ); ?></div>
					<?php if ( '' !== $time_line ) : ?>
						<div class="wpch-feed__handle"><?php echo esc_html( $time_line ); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<p class="wpch-feed__text"><?php echo esc_html( $text ); ?></p>

			<div class="wpch-feed__foot">
				<span class="wpch-feed__pill">
					<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG from the category map. ?>
					<?php echo esc_html( $meta['label'] ); ?>
				</span>
			</div>
			<?php if ( '' !== $url ) : ?>
				</a>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX handler: return filtered/paginated cards as JSON.
	 */
	public function ajax_feed(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce verified above.
		$filters = array(
			'category' => isset( $_REQUEST['category'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['category'] ) ) : '',
			'event'    => isset( $_REQUEST['event'] ) ? (int) $_REQUEST['event'] : 0,
			'search'   => isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['search'] ) ) : '',
			'sort'     => isset( $_REQUEST['sort'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['sort'] ) ) : '',
			'paged'    => isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$query = new \WP_Query( self::query_args( $filters ) );
		$html  = self::render_cards( $query );

		wp_reset_postdata();

		wp_send_json_success(
			array(
				'html'     => $html,
				'page'     => (int) $filters['paged'],
				'maxPages' => (int) $query->max_num_pages,
				'found'    => (int) $query->found_posts,
				'hasMore'  => (int) $filters['paged'] < (int) $query->max_num_pages,
			)
		);
	}
}
