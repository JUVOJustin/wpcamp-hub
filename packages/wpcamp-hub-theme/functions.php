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
function wpcamp_hub_enqueue_assets() {
	$theme   = wp_get_theme();
	$version = $theme->get( 'Version' );

	// Fonts — EB Garamond (display), Hanken Grotesk (sans), JetBrains Mono (mono).
	wp_enqueue_style(
		'wpcamp-hub-fonts',
		'https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Hanken+Grotesk:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=JetBrains+Mono:wght@400;500;700&display=swap',
		array(),
		null
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
		$version
	);

	// Root stylesheet (theme header; reserved for overrides).
	wp_enqueue_style(
		'wpcamp-hub-style',
		get_stylesheet_uri(),
		array( 'wpcamp-hub-theme' ),
		$version
	);

	// Lucide icons (used by the header search button & mobile nav).
	wp_enqueue_script(
		'lucide',
		'https://unpkg.com/lucide@latest/dist/umd/lucide.js',
		array(),
		null,
		true
	);
	wp_add_inline_script(
		'lucide',
		'document.addEventListener("DOMContentLoaded",function(){if(window.lucide){window.lucide.createIcons();}});'
	);

	// Header behaviours (collapsible search).
	wp_enqueue_script(
		'wpcamp-hub-header',
		get_theme_file_uri( 'assets/js/header.js' ),
		array(),
		$version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'wpcamp_hub_enqueue_assets' );

/**
 * Render an inline Lucide icon placeholder.
 *
 * @param string $name Lucide icon name.
 * @param int    $size Pixel size.
 * @return string HTML <i data-lucide> element.
 */
function wpcamp_hub_icon( $name, $size = 20 ) {
	return sprintf(
		'<i class="licon" data-lucide="%1$s" style="width:%2$dpx;height:%2$dpx" aria-hidden="true"></i>',
		esc_attr( $name ),
		(int) $size
	);
}

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
