<?php
/**
 * Theme setup and asset loading.
 *
 * @package wpcamp-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register theme support features and nav menus.
 */
function wpcamp_hub_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	register_nav_menus(
		array(
			'primary'         => __( 'Primary Menu', 'wpcamp-hub' ),
			'mobile'          => __( 'Mobile Bottom Bar', 'wpcamp-hub' ),
			'footer-event'    => __( 'Footer — Event', 'wpcamp-hub' ),
			'footer-community' => __( 'Footer — Community', 'wpcamp-hub' ),
			'footer-wordpress' => __( 'Footer — WordPress', 'wpcamp-hub' ),
			'legal'           => __( 'Footer — Legal (bottom bar)', 'wpcamp-hub' ),
		)
	);
}
add_action( 'after_setup_theme', 'wpcamp_hub_setup' );

/**
 * Register the footer widget area.
 *
 * Sits under the wordmark in the footer's first (brand) column. Use it to
 * edit the intro blurb or add any other widgets.
 */
function wpcamp_hub_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Footer', 'wpcamp-hub' ),
			'id'            => 'footer-brand',
			'description'   => __( 'Appears in the footer under the wordmark. Add a Paragraph/HTML block to edit the intro text.', 'wpcamp-hub' ),
			'before_widget' => '<div id="%1$s" class="foot-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4>',
			'after_title'   => '</h4>',
		)
	);
}
add_action( 'widgets_init', 'wpcamp_hub_widgets_init' );

/**
 * Enqueue fonts, design tokens, component styles and the icon library.
 */
/**
 * Version string for a theme-owned asset: its file modification time.
 *
 * Busts the browser cache whenever the file changes, so edits to CSS/JS show
 * up without bumping the theme version by hand. Falls back to the theme
 * version when the file is missing.
 *
 * @param string $relative_path Theme-relative asset path.
 * @return string Cache-busting version string.
 */
function wpcamp_hub_asset_version( $relative_path ) {
	$file = get_theme_file_path( $relative_path );
	if ( file_exists( $file ) ) {
		return (string) filemtime( $file );
	}

	return (string) wp_get_theme()->get( 'Version' );
}

function wpcamp_hub_enqueue_assets() {
	$theme   = wp_get_theme();
	$version = $theme->get( 'Version' );

	// Fonts — self-hosted (EB Garamond, Hanken Grotesk, JetBrains Mono).
	wp_enqueue_style(
		'wpcamp-hub-fonts',
		get_theme_file_uri( 'assets/css/fonts.css' ),
		array(),
		$version
	);

	// Design tokens (CSS custom properties: colours, type, spacing, radii).
	wp_enqueue_style(
		'wpcamp-hub-tokens',
		get_theme_file_uri( 'assets/css/tokens.css' ),
		array( 'wpcamp-hub-fonts' ),
		$version
	);

	// Component styles (header, footer, nav, buttons).
	wp_enqueue_style(
		'wpcamp-hub-theme',
		get_theme_file_uri( 'assets/css/theme.css' ),
		array( 'wpcamp-hub-tokens' ),
		wpcamp_hub_asset_version( 'assets/css/theme.css' )
	);

	// Root stylesheet (theme header; reserved for overrides).
	wp_enqueue_style(
		'wpcamp-hub-style',
		get_stylesheet_uri(),
		array( 'wpcamp-hub-theme' ),
		$version
	);

	// Icons are rendered inline as SVG (see wpcamp_hub_icon) — no external library.

	// Header behaviours (collapsible search).
	wp_enqueue_script(
		'wpcamp-hub-header',
		get_theme_file_uri( 'assets/js/header.js' ),
		array(),
		wpcamp_hub_asset_version( 'assets/js/header.js' ),
		true
	);

	// Single event: self-hosted Leaflet for the map view + the event-detail
	// view switcher / map init.
	if ( is_singular( 'wpcamp_event' ) ) {
		wp_enqueue_style(
			'leaflet',
			get_theme_file_uri( 'assets/leaflet/leaflet.css' ),
			array(),
			'1.9.4'
		);
		wp_enqueue_script(
			'leaflet',
			get_theme_file_uri( 'assets/leaflet/leaflet.js' ),
			array(),
			'1.9.4',
			true
		);
		wp_enqueue_script(
			'wpcamp-hub-event',
			get_theme_file_uri( 'assets/js/event.js' ),
			array( 'leaflet' ),
			wpcamp_hub_asset_version( 'assets/js/event.js' ),
			true
		);
		wp_localize_script(
			'wpcamp-hub-event',
			'wpcampHubEvent',
			array(
				'markerBase' => get_theme_file_uri( 'assets/leaflet/images/' ),
			)
		);
	}

	// Tweets archive: AJAX filter + pagination for the community feed.
	if ( is_post_type_archive( 'wpcamp_tweet' ) ) {
		wp_enqueue_script(
			'wpcamp-hub-tweet-feed',
			get_theme_file_uri( 'assets/js/tweet-feed.js' ),
			array(),
			wpcamp_hub_asset_version( 'assets/js/tweet-feed.js' ),
			true
		);
		wp_localize_script(
			'wpcamp-hub-tweet-feed',
			'wpcampHubFeed',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'wpcamp_tweet_feed',
				'nonce'   => wp_create_nonce( 'wpcamp_tweet_feed' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'wpcamp_hub_enqueue_assets' );

/**
 * Inner SVG markup for the icons used by the theme.
 *
 * Self-hosted Lucide icons (v1.17.0, ISC). Add an entry here to support a new
 * "licon-<name>" menu class — no external icon library is loaded.
 *
 * @return array<string,string> Map of icon name => inner SVG markup.
 */
function wpcamp_hub_icon_paths() {
	return array(
		'search'     => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
		'bookmark'   => '<path d="M17 3a2 2 0 0 1 2 2v15a1 1 0 0 1-1.496.868l-4.512-2.578a2 2 0 0 0-1.984 0l-4.512 2.578A1 1 0 0 1 5 20V5a2 2 0 0 1 2-2z"/>',
		'home'       => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		'hash'       => '<line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/>',
		'calendar'   => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
		'users'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>',
		'user-round' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
		'bell'       => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
		'clock'      => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'heart'      => '<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/>',
		'star'       => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
		'map-pin'    => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
		'x'          => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
	);
}

/**
 * Render an inline SVG icon.
 *
 * @param string $name Icon name (see wpcamp_hub_icon_paths()).
 * @param int    $size Pixel size.
 * @return string Inline <svg> markup, or empty string for an unknown icon.
 */
function wpcamp_hub_icon( $name, $size = 20 ) {
	$paths = wpcamp_hub_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="licon licon-%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		(int) $size,
		$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG markup.
	);
}

/**
 * Extract a Lucide icon name from a menu item's CSS classes.
 *
 * Recognises a class of the form "licon-<name>" (e.g. "licon-calendar")
 * assigned in the menu editor's "CSS Classes" field.
 *
 * @param array $classes Menu item CSS classes.
 * @return string Lucide icon name, or empty string if none.
 */
function wpcamp_hub_menu_item_icon( $classes ) {
	foreach ( (array) $classes as $class ) {
		if ( 0 === strpos( $class, 'licon-' ) ) {
			return substr( $class, 6 );
		}
	}
	return '';
}

/**
 * Prepend a Lucide icon to mobile bottom-bar menu items.
 *
 * Set an icon by adding a "licon-<name>" CSS class to the menu item
 * (Appearance → Menus → item → "CSS Classes"; enable the field under
 * Screen Options if hidden). The label is wrapped so it can be styled
 * or hidden independently of the icon.
 *
 * @param string   $item_output The menu item's starting HTML.
 * @param WP_Post  $item        Menu item data object.
 * @param int      $depth       Depth of the menu item.
 * @param stdClass $args        wp_nav_menu() arguments.
 * @return string
 */
function wpcamp_hub_mobile_menu_icons( $item_output, $item, $depth, $args ) {
	if ( empty( $args->theme_location ) || 'mobile' !== $args->theme_location ) {
		return $item_output;
	}

	$icon = wpcamp_hub_menu_item_icon( $item->classes );
	if ( '' === $icon ) {
		return $item_output;
	}

	// Inject the icon right after the opening <a ...> tag, and wrap the
	// remaining label text in a span for styling.
	return preg_replace(
		'/(<a\b[^>]*>)(.*)(<\/a>)/s',
		'$1' . wpcamp_hub_icon( $icon, 22 ) . '<span class="mobile-nav-label">$2</span>$3',
		$item_output,
		1
	);
}
add_filter( 'walker_nav_menu_start_el', 'wpcamp_hub_mobile_menu_icons', 10, 4 );

/**
 * Register theme options in the Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 */
function wpcamp_hub_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'wpcamp_hub_header',
		array(
			'title'    => __( 'Header', 'wpcamp-hub' ),
			'priority' => 30,
		)
	);

	$wp_customize->add_setting(
		'wpcamp_hub_tickets_url',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		'wpcamp_hub_tickets_url',
		array(
			'label'       => __( 'Get tickets link', 'wpcamp-hub' ),
			'description' => __( 'URL for the "Get tickets" button in the header. Leave empty to hide the button.', 'wpcamp-hub' ),
			'section'     => 'wpcamp_hub_header',
			'type'        => 'url',
		)
	);
}
add_action( 'customize_register', 'wpcamp_hub_customize_register' );

/**
 * Get the configured "Get tickets" URL.
 *
 * Returns an empty string when no link is set, so the header button can be
 * hidden entirely.
 *
 * @return string
 */
function wpcamp_hub_tickets_url() {
	return trim( (string) get_theme_mod( 'wpcamp_hub_tickets_url', '' ) );
}

/**
 * Get the display name of the menu assigned to a theme location.
 *
 * Lets footer column headings follow the menu's name (editable under
 * Appearance → Menus) instead of being hard-coded in the template.
 *
 * @param string $location Registered nav menu location.
 * @return string Menu name, or empty string if none assigned.
 */
function wpcamp_hub_menu_name( $location ) {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return '';
	}
	$menu = wp_get_nav_menu_object( $locations[ $location ] );
	return $menu ? $menu->name : '';
}

/**
 * Output the WPCAMP·HUB wordmark.
 *
 * @param string $class_attr Optional extra classes.
 * @return string
 */
function wpcamp_hub_wordmark( $class_attr = '' ) {
	$classes = trim( 'wordmark ' . $class_attr );
	return sprintf(
		'<a class="%1$s" href="%2$s">WPCAMP<span class="dot">&middot;</span>HUB</a>',
		esc_attr( $classes ),
		esc_url( home_url( '/' ) )
	);
}

/**
 * Build up-to-two-letter initials from a name for avatar placeholders.
 *
 * @param string $name Person/handle name.
 * @return string
 */
function wpcamp_hub_initials( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return '';
	}

	$parts    = preg_split( '/\s+/', $name );
	$initials = '';
	foreach ( array_slice( is_array( $parts ) ? $parts : array(), 0, 2 ) as $part ) {
		$initials .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
	}

	return $initials;
}
