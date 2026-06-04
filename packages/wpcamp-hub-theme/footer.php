<?php
/**
 * Theme footer.
 *
 * @package wpcamp-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
</main>

<footer class="site-footer">
	<p>
		&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
		<?php bloginfo( 'name' ); ?>
	</p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
