<?php
/**
 * Feed category meta — the PHP mirror of the feed-card block's categories.js.
 *
 * Maps a tweet's `wpcamp_tweet_label` term onto one of the design's five feed
 * categories (label, accent colour modifier and inline Lucide icon path) so the
 * server-rendered community feed looks identical to the editor's Feed Card.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Resolves feed category presentation for a tweet.
 */
class Feed_Category {

	/**
	 * Category presentation, keyed by design category slug.
	 *
	 * Mirrors `src/Blocks/feed-card/categories.js`. Icons are Lucide paths (ISC).
	 *
	 * @return array<string,array{label:string,color:string,icon:string}>
	 */
	public static function all(): array {
		return array(
			'attendance'    => array(
				'label' => __( 'Going to WCEU', 'wpcamp-hub' ),
				'color' => 'fest-teal',
				// Lucide: plane.
				'icon'  => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
			),
			'networking'    => array(
				'label' => __( 'Wants to meet', 'wpcamp-hub' ),
				'color' => 'brand',
				// Lucide: coffee.
				'icon'  => '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/><path d="M6 2v2"/>',
			),
			'sideevent'     => array(
				'label' => __( 'Side event', 'wpcamp-hub' ),
				'color' => 'fest-coral',
				// Lucide: party-popper.
				'icon'  => '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L12 12"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/>',
			),
			'participation' => array(
				'label' => __( 'Attending an event', 'wpcamp-hub' ),
				'color' => 'fest-violet',
				// Lucide: circle-check-big.
				'icon'  => '<path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>',
			),
			'community'     => array(
				'label' => __( 'Community', 'wpcamp-hub' ),
				'color' => 'fest-gold',
				// Lucide: message-circle.
				'icon'  => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
			),
		);
	}

	/**
	 * Canonical display order of the categories (mirrors the prototype rail).
	 *
	 * @return list<string>
	 */
	public static function order(): array {
		return array( 'attendance', 'networking', 'sideevent', 'participation', 'community' );
	}

	/**
	 * Tweet label term slugs that resolve to a given category.
	 *
	 * Used to translate a category filter into a tweet_label tax query.
	 *
	 * @param string $category Category slug (a key of self::all()).
	 * @return list<string> Term slugs (possibly empty).
	 */
	public static function labels_for_category( string $category ): array {
		return array_keys(
			array_filter(
				self::label_map(),
				static fn( string $cat ): bool => $cat === $category
			)
		);
	}

	/**
	 * Published tweet counts per category, plus an "all" total.
	 *
	 * A category count reflects what filtering by that category actually
	 * returns: tweets carrying a label term that maps to it. Unlabeled tweets
	 * only count toward "all" (matching the tax-query filter, which excludes
	 * them from every individual category).
	 *
	 * @return array<string,int> Keyed by category slug, with an extra "all" key.
	 */
	public static function counts(): array {
		$counts = array( 'all' => 0 );
		foreach ( self::order() as $category ) {
			$counts[ $category ] = 0;
		}

		$tweet_ids = get_posts(
			array(
				'post_type'              => Data_Structure::POST_TYPE_TWEET,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);

		$label_map = self::label_map();

		foreach ( $tweet_ids as $tweet_id ) {
			++$counts['all'];

			$terms = get_the_terms( $tweet_id, Data_Structure::TAXONOMY_TWEET_LABEL );
			$slug  = is_array( $terms ) && array() !== $terms ? reset( $terms )->slug : '';

			// Only labelled tweets contribute to a category — same rule the
			// tax-query filter applies.
			if ( isset( $label_map[ $slug ] ) ) {
				++$counts[ $label_map[ $slug ] ];
			}
		}

		return $counts;
	}

	/**
	 * Map a tweet_label term slug onto a design category slug.
	 *
	 * Falls back to "networking" — the design's default feed accent.
	 *
	 * @return array<string,string>
	 */
	private static function label_map(): array {
		return array(
			'going-to-wceu'    => 'attendance',
			'wants-to-meet'    => 'networking',
			'looking-for-help' => 'networking',
			'offering-help'    => 'community',
			'community'        => 'community',
			'afterparty'       => 'sideevent',
			'travel'           => 'sideevent',
			'speaking'         => 'participation',
			'sponsoring'       => 'participation',
			'hiring'           => 'participation',
		);
	}

	/**
	 * Resolve the design category slug for a tweet_label term slug.
	 *
	 * Falls back to "networking" — the design's default feed accent.
	 *
	 * @param string $term_slug Tweet label term slug.
	 * @return string Category slug (a key of self::all()).
	 */
	public static function slug_for_label( string $term_slug ): string {
		return self::label_map()[ $term_slug ] ?? 'networking';
	}

	/**
	 * Resolve the design category slug for a tweet.
	 *
	 * @param Tweet $tweet Tweet wrapper.
	 * @return string Category slug (a key of self::all()).
	 */
	public static function for_tweet( Tweet $tweet ): string {
		$label = $tweet->get_label();
		$slug  = $label ? $label->get_wp_entity()->slug : '';

		return self::slug_for_label( $slug );
	}

	/**
	 * Presentation meta for a tweet, guaranteed non-null.
	 *
	 * The tweet's own label term may override the colour and icon (set in the
	 * term admin). When it does, `color_hex` carries the chosen hex and `icon`
	 * is the term's icon path; otherwise both fall back to the mapped design
	 * category. `color` is always the preset modifier name for the fallback
	 * CSS class.
	 *
	 * @param Tweet $tweet Tweet wrapper.
	 * @return array{key:string,label:string,color:string,color_hex:string,icon:string}
	 */
	public static function meta_for_tweet( Tweet $tweet ): array {
		$key   = self::for_tweet( $tweet );
		$all   = self::all();
		$meta  = $all[ $key ] ?? $all['networking'];
		$label = $tweet->get_label();

		$color_hex = '';
		$icon      = $meta['icon'];

		if ( null !== $label ) {
			$term_id   = $label->get_id();
			$term_hex  = get_term_meta( $term_id, Data_Structure::TERM_META_COLOR, true );
			$term_icon = get_term_meta( $term_id, Data_Structure::TERM_META_ICON, true );

			if ( is_string( $term_hex ) && '' !== $term_hex ) {
				$color_hex = $term_hex;
			}
			if ( is_string( $term_icon ) && '' !== $term_icon && Feed_Icon::exists( $term_icon ) ) {
				$icon = Feed_Icon::path( $term_icon );
			}
		}

		return array(
			'key'       => $key,
			'label'     => $meta['label'],
			'color'     => $meta['color'],
			'color_hex' => $color_hex,
			'icon'      => $icon,
		);
	}
}
