<?php
/**
 * Events archive — overview of all events.
 *
 * @package wpcamp-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

/**
 * Source meta: label + accent colour preset.
 *
 * @var array<string,array{label:string,color:string}> $wpch_sources
 */
$wpch_sources = array(
	'official'  => array(
		'label' => __( 'Official', 'wpcamp-hub' ),
		'color' => 'var(--wp--preset--color--brand)',
	),
	'community' => array(
		'label' => __( 'Community', 'wpcamp-hub' ),
		'color' => 'var(--wp--preset--color--fest-gold)',
	),
	'x'         => array(
		'label' => __( 'From #WCEU', 'wpcamp-hub' ),
		'color' => 'var(--wp--preset--color--fest-teal)',
	),
);
?>
<main id="content" class="site-content wpch-events-archive">

	<section class="wpch-intro">
		<div class="wpch-intro__inner">
			<div class="wpch-intro__eyebrow"><?php esc_html_e( 'Across the WordCamps', 'wpcamp-hub' ); ?></div>
			<h1 class="wpch-intro__title"><?php esc_html_e( 'Everything happening around the community', 'wpcamp-hub' ); ?></h1>
			<p class="wpch-intro__lead">
				<?php esc_html_e( 'Talks and workshops, sponsor evenings, contributor tables, dinners and the meetups people are dreaming up — across WordCamps, all in one place.', 'wpcamp-hub' ); ?>
			</p>
		</div>
	</section>

	<section class="wpch-events">
		<div class="wrap">
			<?php if ( have_posts() ) : ?>
				<div class="wpch-events__grid">
					<?php
					while ( have_posts() ) :
						the_post();

						$event_id = get_the_ID();
						$source   = (string) get_post_meta( $event_id, 'wpcamp_source', true );
						$meta     = isset( $wpch_sources[ $source ] ) ? $wpch_sources[ $source ] : null;

						$start = (string) get_post_meta( $event_id, 'wpcamp_date_start', true );
						$loc   = (string) get_post_meta( $event_id, 'wpcamp_location', true );
						$terms = get_the_terms( $event_id, 'wpcamp_event_type' );
						$type  = is_array( $terms ) && array() !== $terms ? $terms[0]->name : '';

						$when = '' !== $start ? wp_date( 'D j M · H:i', strtotime( $start ) ) : '';
						?>
						<article class="wpch-event-card"<?php echo null !== $meta ? ' style="--wpch-src:' . esc_attr( $meta['color'] ) . '"' : ''; ?>>
							<a class="wpch-event-card__link" href="<?php the_permalink(); ?>">
								<span class="wpch-event-card__accent" aria-hidden="true"></span>
								<div class="wpch-event-card__body">
									<div class="wpch-event-card__top">
										<?php if ( null !== $meta ) : ?>
											<span class="wpch-event-card__badge">
												<?php echo esc_html( $meta['label'] ); ?>
											</span>
										<?php endif; ?>
										<?php if ( '' !== $type ) : ?>
											<span class="wpch-event-card__type"><?php echo esc_html( $type ); ?></span>
										<?php endif; ?>
									</div>
									<h2 class="wpch-event-card__title"><?php the_title(); ?></h2>
									<p class="wpch-event-card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
									<div class="wpch-event-card__meta">
										<?php if ( '' !== $when ) : ?>
											<span class="wpch-event-card__metaitem"><?php echo esc_html( $when ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $loc ) : ?>
											<span class="wpch-event-card__metaitem"><?php echo esc_html( $loc ); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</a>
						</article>
						<?php
					endwhile;
					?>
				</div>

				<?php
				the_posts_pagination(
					array(
						'mid_size'  => 1,
						'prev_text' => __( 'Previous', 'wpcamp-hub' ),
						'next_text' => __( 'Next', 'wpcamp-hub' ),
					)
				);
				?>
			<?php else : ?>
				<p><?php esc_html_e( 'No events yet.', 'wpcamp-hub' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

</main>
<?php
get_footer();
