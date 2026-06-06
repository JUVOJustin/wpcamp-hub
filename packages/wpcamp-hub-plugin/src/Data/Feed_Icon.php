<?php
/**
 * Feed icon registry — named Lucide icons available to tweet labels.
 *
 * Editors choose a tweet label's icon by name in the term admin; the renderers
 * and the filter rail turn that name into an inline SVG via this registry. Keeps
 * raw SVG out of the database — only a known key is stored.
 *
 * Icons are Lucide (ISC); paths match the set used by the feed-card design.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Resolves named feed icons to Lucide SVG paths.
 */
class Feed_Icon {

	/**
	 * Default icon key used when a term has none (or an unknown key).
	 */
	public const DEFAULT_ICON = 'coffee';

	/**
	 * Icon registry: key => inner SVG path markup.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		return array(
			'plane'          => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
			'coffee'         => '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/><path d="M6 2v2"/>',
			'party-popper'   => '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L12 12"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/>',
			'check-circle'   => '<path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>',
			'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
			'calendar'       => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
			'hash'           => '<line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/>',
			'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'star'           => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.12 2.12 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.12 2.12 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16z"/>',
			'heart'          => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
			'megaphone'      => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
			'map-pin'        => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
			'utensils'       => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
		);
	}

	/**
	 * Icon keys for a select control.
	 *
	 * @return list<string>
	 */
	public static function keys(): array {
		return array_keys( self::all() );
	}

	/**
	 * Whether an icon key exists.
	 *
	 * @param string $key Icon key.
	 */
	public static function exists( string $key ): bool {
		return array_key_exists( $key, self::all() );
	}

	/**
	 * Inner SVG path for an icon key, falling back to the default.
	 *
	 * @param string $key Icon key.
	 */
	public static function path( string $key ): string {
		$all = self::all();

		return $all[ $key ] ?? $all[ self::DEFAULT_ICON ];
	}

	/**
	 * Render a complete inline SVG element for an icon key.
	 *
	 * @param string $key       Icon key.
	 * @param int    $size      Pixel size.
	 * @param string $css_class Optional class attribute.
	 * @return string SVG markup (safe inline; contains no user input).
	 */
	public static function svg( string $key, int $size = 14, string $css_class = '' ): string {
		$class_attr = '' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '';

		return sprintf(
			'<svg%1$s width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			$class_attr,
			$size,
			self::path( $key )
		);
	}
}
