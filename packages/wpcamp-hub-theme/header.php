<?php
/**
 * Theme header — sticky nav, search and ticket CTA.
 *
 * @package wpcamp-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="hdr">
	<div class="wrap hdr-in">
		<?php echo wpcamp_hub_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="nav" aria-label="<?php esc_attr_e( 'Primary', 'wpcamp-hub' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'nav-list',
						'fallback_cb'    => false,
						'depth'          => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<div class="hdr-right">
			<div class="hdr-search">
				<button class="icon-btn icon-btn-sm" type="button" id="hdr-search-toggle" aria-expanded="false" aria-controls="hdr-search-panel" title="<?php esc_attr_e( 'Search', 'wpcamp-hub' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'wpcamp-hub' ); ?>">
					<?php echo wpcamp_hub_icon( 'search', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
				<div class="hdr-search-panel" id="hdr-search-panel" hidden>
					<?php get_search_form(); ?>
				</div>
			</div>

			<?php if ( wpcamp_hub_schedule_enabled() ) : ?>
				<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( home_url( '/schedule/' ) ); ?>">
					<?php echo wpcamp_hub_icon( 'bookmark', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><span class="hide-sm"><?php esc_html_e( 'My', 'wpcamp-hub' ); ?> </span><?php esc_html_e( 'schedule', 'wpcamp-hub' ); ?></span>
				</a>
			<?php endif; ?>

			<?php $wpcamp_hub_tickets = wpcamp_hub_tickets_url(); ?>
			<?php if ( '' !== $wpcamp_hub_tickets ) : ?>
				<a class="btn btn-primary btn-sm hide-sm" href="<?php echo esc_url( $wpcamp_hub_tickets ); ?>">
					<?php esc_html_e( 'Get tickets', 'wpcamp-hub' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>

<?php
// Mobile bottom tab bar (shown <=860px). Falls back to nothing if no menu is assigned.
if ( has_nav_menu( 'mobile' ) ) :
	?>
	<nav class="mobile-nav" aria-label="<?php esc_attr_e( 'Mobile', 'wpcamp-hub' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'mobile',
				'container'      => false,
				'menu_class'     => 'mobile-nav-list',
				'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
				'fallback_cb'    => false,
				'depth'          => 1,
			)
		);
		?>
	</nav>
	<?php
endif;
?>

<main id="content" class="site-content">
