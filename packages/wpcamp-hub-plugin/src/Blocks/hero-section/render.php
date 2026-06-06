<?php
/**
 * Server render for the Hero Section block.
 *
 * When an event is linked (`eventId`), the date, location, attendee badge and
 * the two calls to action are derived from it live; any manually-set attribute
 * still overrides the event-derived value. With no event linked the block keeps
 * its manual fields and decorative defaults.
 *
 * @package WPCAMP_HUB
 *
 * @var array<string,mixed> $attributes Block attributes.
 * @var string              $content    Saved inner markup (unused — dynamic).
 * @var WP_Block            $block      Block instance.
 */

use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\User_Profile;
use WPCAMP_HUB\Frontend\Attendees_Page;
use WPCAMP_HUB\Frontend\Sessions_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event_id = isset( $attributes['eventId'] ) ? (int) $attributes['eventId'] : 0;
$event    = null;
if ( $event_id > 0 && class_exists( Event::class ) && Event::get_post_type() === get_post_type( $event_id ) ) {
	$event = Event::from( $event_id );
}

/**
 * Resolve a field: a non-empty manual attribute always wins; otherwise the
 * event-derived value; otherwise ''.
 *
 * @param string $manual Manual attribute value.
 * @param string $derived Event-derived value.
 * @return string
 */
$resolve = static function ( string $manual, string $derived ): string {
	$manual = trim( $manual );
	return '' !== $manual ? $manual : $derived;
};

// ---- Event-derived values (empty when no event linked) ----
$ev_location   = '';
$ev_date       = '';
$ev_eyebrow    = '';
$attendees     = array();
$tickets_url   = '';
$explore_url   = '';
$attendees_url = '';

if ( $event instanceof Event ) {
	$ev_eyebrow  = get_the_title( $event->get_id() );
	$ev_location = $event->get_location();
	$tickets_url = $event->get_official_url();
	$attendees   = $event->get_attendees();

	$start = $event->get_start();
	$end   = $event->get_end();
	if ( $start instanceof DateTimeImmutable ) {
		$ev_date = wp_date( 'j M Y', $start->getTimestamp() );
		if ( $end instanceof DateTimeImmutable ) {
			// Compact range: "12–14 June 2026" style when within one month.
			$ev_date = wp_date( 'j', $start->getTimestamp() ) . '–' . wp_date( 'j M Y', $end->getTimestamp() );
		}
	}

	if ( class_exists( Sessions_Page::class ) ) {
		$explore_url = Sessions_Page::url_for( $event->get_id() );
	}
	if ( class_exists( Attendees_Page::class ) ) {
		$attendees_url = Attendees_Page::url_for( $event->get_id() );
	}
}

// ---- Resolved (manual overrides event) ----
$eyebrow  = $resolve( (string) ( $attributes['eyebrow'] ?? '' ), $ev_eyebrow );
$location = $resolve( (string) ( $attributes['location'] ?? '' ), $ev_location );
$heading  = (string) ( $attributes['heading'] ?? '' );
$lead     = (string) ( $attributes['lead'] ?? '' );
$date     = $resolve( (string) ( $attributes['dateLabel'] ?? '' ), $ev_date );

// CTA links: a manually-entered URL always wins; otherwise the event-derived
// link (official URL / sessions page). A button renders only when it has a URL.
$tickets_url = $resolve( (string) ( $attributes['ticketsUrl'] ?? '' ), $tickets_url );
$explore_url = $resolve( (string) ( $attributes['exploreUrl'] ?? '' ), $explore_url );

$show_badge    = ! empty( $attributes['showBadge'] );
$tickets_label = (string) ( $attributes['ticketsLabel'] ?? __( 'Get tickets', 'wpcamp-hub' ) );
$explore_label = (string) ( $attributes['exploreLabel'] ?? __( 'Explore events', 'wpcamp-hub' ) );
$going_sub     = (string) ( $attributes['goingSubtext'] ?? '' );

$image_url = (string) ( $attributes['imageUrl'] ?? '' );
$image_alt = (string) ( $attributes['imageAlt'] ?? '' );

// Attendee count text (live from the event when linked).
$going_text = '';
if ( $event instanceof Event ) {
	$count = count( $attendees );
	/* translators: %s: formatted attendee count. */
	$going_text = sprintf( __( '%s going', 'wpcamp-hub' ), number_format_i18n( $count ) );
}

/**
 * Build the avatar circles for the badge: real attendee avatars first, padded
 * with decorative tone circles up to four.
 *
 * @param list<User_Profile> $people Attendees.
 * @return string Avatar markup.
 */
$render_avatars = static function ( array $people ): string {
	$tones = array( 'b300', 'b400', 'b200', 'b300' );
	$out   = '';
	$shown = 0;
	foreach ( $people as $person ) {
		if ( $shown >= 4 ) {
			break;
		}
		$name   = $person->get_display_name();
		$avatar = get_avatar_url( $person->get_id() );
		if ( is_string( $avatar ) && '' !== $avatar ) {
			$out .= sprintf(
				'<img class="wpch-hero__avatar wpch-hero__avatar-img" src="%1$s" alt="%2$s" width="34" height="34" loading="lazy" decoding="async" />',
				esc_url( $avatar ),
				esc_attr( $name )
			);
			++$shown;
		}
	}
	for ( $i = $shown; $i < 4; $i++ ) {
		$out .= sprintf( '<span class="wpch-hero__avatar wpch-hero__avatar--%s" aria-hidden="true"></span>', esc_attr( $tones[ $i % count( $tones ) ] ) );
	}

	return $out;
};

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wpch-hero' ) );

// Geometric decorative art (used when no custom image is set).
$geo_art = '<svg viewBox="0 0 360 460" preserveAspectRatio="xMidYMid slice" width="100%" height="100%" aria-hidden="true" focusable="false">'
	. '<circle cx="250" cy="120" r="120" fill="#3858E9" /><path d="M0 320 A150 150 0 0 1 300 320 Z" fill="#FF5A4D" />'
	. '<circle cx="96" cy="150" r="60" fill="#FFB020" /><circle cx="250" cy="120" r="40" fill="#ECE7FF" />'
	. '<circle cx="300" cy="300" r="34" fill="#14B8A6" /><g fill="#15235e"><circle cx="60" cy="60" r="8" /><circle cx="92" cy="60" r="8" /><circle cx="124" cy="60" r="8" /></g>'
	. '<rect x="40" y="400" width="120" height="16" rx="8" fill="#7C5CFF" /></svg>';
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped markup. ?>>
	<div class="wpch-hero__inner">
		<div class="wpch-hero__content">
			<div class="wpch-hero__eyebrow-row">
				<?php if ( '' !== $eyebrow ) : ?>
					<span class="wpch-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $location ) : ?>
					<span class="wpch-hero__pill wpch-hero__pill--location">
						<svg class="wpch-hero__pin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" /><circle cx="12" cy="10" r="3" /></svg>
						<span class="wpch-hero__pill-label"><?php echo esc_html( $location ); ?></span>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $heading ) : ?>
				<h1 class="wpch-hero__title"><?php echo esc_html( $heading ); ?></h1>
			<?php endif; ?>

			<?php if ( '' !== $lead ) : ?>
				<p class="wpch-hero__lead"><?php echo esc_html( $lead ); ?></p>
			<?php endif; ?>

			<div class="wpch-hero__actions-row">
				<div class="wpch-hero__actions wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
					<?php if ( '' !== $tickets_url && '' !== $tickets_label ) : ?>
						<div class="wp-block-button">
							<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $tickets_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $tickets_label ); ?>
							</a>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $explore_url && '' !== $explore_label ) : ?>
						<div class="wp-block-button is-style-outline">
							<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $explore_url ); ?>">
								<?php echo esc_html( $explore_label ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $date ) : ?>
					<span class="wpch-hero__when"><?php echo esc_html( $date ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpch-hero__media">
			<div class="wpch-hero__geo">
				<?php if ( '' !== $image_url ) : ?>
					<img class="wpch-hero__img" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
				<?php else : ?>
					<?php echo $geo_art; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
				<?php endif; ?>
			</div>

			<span class="wpch-hero__confetti wpch-hero__confetti--1" aria-hidden="true"></span>
			<span class="wpch-hero__confetti wpch-hero__confetti--2" aria-hidden="true"></span>
			<span class="wpch-hero__confetti wpch-hero__confetti--3" aria-hidden="true"></span>

			<?php if ( '' !== $date ) : ?>
				<div class="wpch-hero__pill wpch-hero__pill--rotated">
					<span class="wpch-hero__perf" aria-hidden="true"></span>
					<span class="wpch-hero__date"><?php echo esc_html( $date ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $show_badge && ( '' !== $going_text || array() === $attendees ) ) : ?>
				<?php
				$badge_open  = '' !== $attendees_url ? '<a class="wpch-hero__float-card" href="' . esc_url( $attendees_url ) . '">' : '<div class="wpch-hero__float-card">';
				$badge_close = '' !== $attendees_url ? '</a>' : '</div>';
				echo $badge_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_url above.
				?>
					<div class="wpch-hero__stack" aria-hidden="true">
						<?php echo $render_avatars( $attendees ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar markup escaped in builder. ?>
					</div>
					<div>
						<?php if ( '' !== $going_text ) : ?>
							<div class="wpch-hero__going-count"><?php echo esc_html( $going_text ); ?></div>
						<?php endif; ?>
						<?php if ( '' !== $going_sub ) : ?>
							<div class="wpch-hero__going-sub"><?php echo esc_html( $going_sub ); ?></div>
						<?php endif; ?>
					</div>
				<?php echo $badge_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal closing tag. ?>
			<?php endif; ?>
		</div>
	</div>
</section>
