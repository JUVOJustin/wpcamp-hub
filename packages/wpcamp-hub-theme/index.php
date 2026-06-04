<?php
/**
 * Minimal main template file.
 *
 * @package wpcamp-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		the_title( '<h1>', '</h1>' );
		the_content();
	}
} else {
	echo '<p>' . esc_html__( 'Nothing found.', 'wpcamp-hub' ) . '</p>';
}

get_footer();
