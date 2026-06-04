<?php
/**
 * Theme footer — inverse band with link columns.
 *
 * @package wpcamp-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Footer column menu locations. A column only renders when its location has a
 * menu assigned, and its heading is the assigned menu's name (set under
 * Appearance → Menus) — no hard-coded labels, no fallback content.
 */
$wpcamp_hub_footer_cols = array( 'footer-event', 'footer-community', 'footer-wordpress' );
?>
</main>

<footer class="foot">
	<div class="wrap foot-grid">
		<div>
			<?php echo wpcamp_hub_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( is_active_sidebar( 'footer-brand' ) ) : ?>
				<div class="foot-widgets">
					<?php dynamic_sidebar( 'footer-brand' ); ?>
				</div>
			<?php else : ?>
				<p class="foot-blurb">
					<?php esc_html_e( 'The companion hub for WordCamp Europe 2026. Built by the community, for the community.', 'wpcamp-hub' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php
		foreach ( $wpcamp_hub_footer_cols as $location ) :
			if ( ! has_nav_menu( $location ) ) {
				continue;
			}
			$heading = wpcamp_hub_menu_name( $location );
			?>
			<div>
				<?php if ( '' !== $heading ) : ?>
					<h4><?php echo esc_html( $heading ); ?></h4>
				<?php endif; ?>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => $location,
						'container'      => false,
						'items_wrap'     => '<ul>%3$s</ul>',
						'fallback_cb'    => false,
						'depth'          => 1,
					)
				);
				?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="wrap foot-bottom">
		<?php if ( has_nav_menu( 'legal' ) ) : ?>
			<nav class="foot-legal" aria-label="<?php esc_attr_e( 'Legal', 'wpcamp-hub' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'legal',
						'container'      => false,
						'items_wrap'     => '<ul>%3$s</ul>',
						'fallback_cb'    => false,
						'depth'          => 1,
					)
				);
				?>
			</nav>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<span><?php esc_html_e( 'Not affiliated with WordPress or WordCamp', 'wpcamp-hub' ); ?></span>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
