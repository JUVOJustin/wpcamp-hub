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
 * Register theme support features.
 */
function wpcamp_hub_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'wpcamp-hub' ),
		)
	);
}
add_action( 'after_setup_theme', 'wpcamp_hub_setup' );

/**
 * Enqueue the theme stylesheet.
 */
function wpcamp_hub_enqueue_assets() {
	wp_enqueue_style(
		'wpcamp-hub-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'wpcamp_hub_enqueue_assets' );
