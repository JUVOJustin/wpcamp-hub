<?php
/**
 * Search form.
 *
 * @package wpcamp-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcamp_hub_sf_id = 'search-field-' . wp_unique_id();
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $wpcamp_hub_sf_id ); ?>">
		<?php esc_html_e( 'Search for:', 'wpcamp-hub' ); ?>
	</label>
	<input
		type="search"
		id="<?php echo esc_attr( $wpcamp_hub_sf_id ); ?>"
		class="search-field"
		placeholder="<?php esc_attr_e( 'Search…', 'wpcamp-hub' ); ?>"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		name="s"
	/>
	<button type="submit" class="search-submit" aria-label="<?php esc_attr_e( 'Search', 'wpcamp-hub' ); ?>">
		<?php echo wpcamp_hub_icon( 'search', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</button>
</form>
