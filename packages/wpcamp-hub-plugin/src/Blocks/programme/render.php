<?php
/**
 * Server render for the Programme Excerpt block.
 *
 * Handles two dynamic options on top of the static markup:
 *  - legendSource = "tracks": build the legend from the wpcamp_track terms.
 *  - contentSource = "sessions": query wpcamp_session and render session cards,
 *    instead of the manually placed InnerBlocks.
 *
 * @package WPCAMP_HUB
 *
 * @var array<string,mixed> $attributes Block attributes.
 * @var string              $content    InnerBlocks (manual cards) markup.
 * @var WP_Block            $block      Block instance.
 */

use WPCAMP_HUB\Data\Track;
use WPCAMP_HUB\Data\Session;
use WPCAMP_HUB\Data\Data_Structure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eyebrow        = isset( $attributes['eyebrow'] ) ? $attributes['eyebrow'] : '';
$heading        = isset( $attributes['heading'] ) ? $attributes['heading'] : '';
$link_label     = isset( $attributes['linkLabel'] ) ? $attributes['linkLabel'] : '';
$link_url       = isset( $attributes['linkUrl'] ) ? $attributes['linkUrl'] : '';
$show_legend    = ! isset( $attributes['showLegend'] ) || $attributes['showLegend'];
$legend_source  = isset( $attributes['legendSource'] ) ? $attributes['legendSource'] : 'manual';
$content_source = isset( $attributes['contentSource'] ) ? $attributes['contentSource'] : 'manual';
$columns        = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
$sessions_count = isset( $attributes['sessionsCount'] ) ? (int) $attributes['sessionsCount'] : 3;
$legend_attr    = isset( $attributes['legend'] ) && is_array( $attributes['legend'] ) ? $attributes['legend'] : array();

/**
 * Build the legend item list, from either the manual attribute or the tracks.
 *
 * @return array<int,array{name:string,color:string}>
 */
$legend_items = static function () use ( $legend_source, $legend_attr ): array {
	if ( 'tracks' === $legend_source ) {
		return array_map(
			static fn( Track $track ): array => array(
				'name'  => $track->get_name(),
				'color' => $track->get_color(),
			),
			Track::all()
		);
	}

	return array_map(
		static fn( $item ): array => array(
			'name'  => isset( $item['name'] ) ? (string) $item['name'] : '',
			'color' => isset( $item['color'] ) ? (string) $item['color'] : '#3858E9',
		),
		$legend_attr
	);
};

/**
 * Render a single session card from a Session entity.
 *
 * @param Session $session Session wrapper.
 * @return string Card HTML.
 */
$render_session_card = static function ( Session $session ): string {
	$track   = $session->get_track();
	$accent  = $track ? $track->get_color() : '#3858E9';
	$t_label = $track ? $track->get_name() : '';

	$start = $session->get_start_time();
	$time  = '' !== $start ? wp_date( 'H:i', strtotime( $start ) ) : '';
	$room  = $session->get_room();
	$meta  = trim( $time . ( '' !== $room ? ' · ' . $room : '' ), ' ·' );

	$speakers = $session->get_speaker_names();
	$speaker  = array() !== $speakers ? implode( ', ', $speakers ) : '';

	$url   = (string) get_post_meta( $session->get_id(), 'wpcamp_official_url', true );
	$title = get_the_title( $session->get_id() );

	ob_start();
	?>
	<article class="wp-block-wpcamp-hub-session-card wpch-card" style="--wpch-card-accent:<?php echo esc_attr( $accent ); ?>">
		<?php if ( '' !== $url ) : ?>
			<a class="wpch-card__link" href="<?php echo esc_url( $url ); ?>">
		<?php endif; ?>
		<div class="wpch-card__head" aria-hidden="true"></div>
		<div class="wpch-card__body">
			<div class="wpch-card__top">
				<span class="wpch-card__type"><?php echo esc_html__( 'Session', 'wpcamp-hub' ); ?></span>
				<?php if ( '' !== $t_label ) : ?>
					<span class="wpch-card__track">
						<span class="wpch-card__dot" aria-hidden="true"></span>
						<span class="wpch-card__track-label"><?php echo esc_html( $t_label ); ?></span>
					</span>
				<?php endif; ?>
			</div>
			<h3 class="wpch-card__title"><?php echo esc_html( $title ); ?></h3>
			<p class="wpch-card__blurb"><?php echo esc_html( get_the_excerpt( $session->get_id() ) ); ?></p>
			<div class="wpch-card__foot">
				<span class="wpch-card__avatar" aria-hidden="true"></span>
				<div class="wpch-card__person">
					<div class="wpch-card__speaker-name"><?php echo esc_html( $speaker ); ?></div>
					<div class="wpch-card__meta"><?php echo esc_html( $meta ); ?></div>
				</div>
			</div>
		</div>
		<?php if ( '' !== $url ) : ?>
			</a>
		<?php endif; ?>
	</article>
	<?php
	return (string) ob_get_clean();
};

// ---- cards markup ----------------------------------------------------------
if ( 'sessions' === $content_source ) {
	$query = new WP_Query(
		array(
			'post_type'           => Data_Structure::POST_TYPE_SESSION,
			'posts_per_page'      => $sessions_count > 0 ? $sessions_count : 3,
			'post_status'         => 'publish',
			'orderby'             => 'meta_value',
			'meta_key'            => 'wpcamp_start_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
		)
	);

	$cards = '';
	foreach ( $query->posts as $session_post ) {
		$cards .= $render_session_card( Session::from( $session_post ) );
	}
	wp_reset_postdata();

	$grid_inner = $cards;
} else {
	// Manual mode: use the InnerBlocks output.
	$grid_inner = $content;
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wpch-programme' ) );
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="wpch-programme__inner">
		<div class="wpch-programme__header">
			<div class="wpch-programme__heading-group">
				<?php if ( '' !== $eyebrow ) : ?>
					<div class="wpch-programme__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $heading ) : ?>
					<h2 class="wpch-programme__heading"><?php echo wp_kses_post( $heading ); ?></h2>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $link_label ) : ?>
				<a class="wpch-programme__link" href="<?php echo esc_url( '' !== $link_url ? $link_url : '#' ); ?>">
					<?php echo wp_kses_post( $link_label ); ?><span aria-hidden="true"> &rarr;</span>
				</a>
			<?php endif; ?>
		</div>

		<?php
		if ( $show_legend ) :
			$items = $legend_items();
			if ( array() !== $items ) :
				?>
				<div class="wpch-programme__legend">
					<?php foreach ( $items as $item ) : ?>
						<span class="wpch-programme__legend-item">
							<span class="wpch-programme__legend-dot" style="background:<?php echo esc_attr( $item['color'] ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( $item['name'] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
				<?php
			endif;
		endif;
		?>

		<div class="wpch-programme__grid" style="--wpch-programme-cols:<?php echo esc_attr( (string) $columns ); ?>">
			<?php echo $grid_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card markup is escaped per field above; InnerBlocks output is pre-rendered. ?>
		</div>
	</div>
</section>
