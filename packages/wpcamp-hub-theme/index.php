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
		// Constrained layout container: children default to the theme.json
		// contentSize, while `.alignwide` / `.alignfull` blocks break out via
		// the core layout CSS. `has-global-padding` applies the root padding so
		// full-width blocks span edge-to-edge while their inner content keeps
		// the side gutters (useRootPaddingAwareAlignments).
		echo '<div class="entry-content wp-block-post-content is-layout-constrained has-global-padding">';
		the_content();
		echo '</div>';
	}
} else {
	echo '<p>' . esc_html__( 'Nothing found.', 'wpcamp-hub' ) . '</p>';
}

get_footer();
