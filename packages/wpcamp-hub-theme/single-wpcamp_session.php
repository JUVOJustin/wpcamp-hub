<?php
/**
 * Single session detail page.
 *
 * Reuses the shared single-page header (title + meta row) and adds the
 * session's speakers, time, and related event, then the content, followed by a
 * link out to the official/source article.
 *
 * Requires the WPCamp Hub plugin for the Session/Event/User_Profile entities.
 *
 * @package wpcamp-hub
 */

use WPCAMP_HUB\Data\Session;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$wpch_session_id = get_the_ID();
		$wpch_session    = class_exists( Session::class ) ? Session::from( $wpch_session_id ) : null;

		// Track (accent colour for the header).
		$wpch_track = $wpch_session ? $wpch_session->get_track() : null;
		$wpch_accent = $wpch_track ? $wpch_track->get_color() : '#3858e9';

		// Time range, formatted from the stored ISO 8601 start/end.
		$wpch_start = $wpch_session ? $wpch_session->get_start_time() : '';
		$wpch_end   = $wpch_session ? $wpch_session->get_end_time() : '';
		$wpch_start_ts = '' !== $wpch_start ? strtotime( $wpch_start ) : 0;
		$wpch_end_ts   = '' !== $wpch_end ? strtotime( $wpch_end ) : 0;
		$wpch_when = '';
		if ( $wpch_start_ts ) {
			$wpch_when = wp_date( 'D j M · H:i', $wpch_start_ts );
			if ( $wpch_end_ts ) {
				$wpch_when .= '–' . wp_date( 'H:i', $wpch_end_ts );
			}
		}

		// Speakers (attendee profiles related to this session).
		$wpch_speakers = $wpch_session ? $wpch_session->get_attendees() : array();

		// Related event.
		$wpch_event = $wpch_session ? $wpch_session->get_event() : null;

		// External main article (official/source URL).
		$wpch_official_url = (string) get_post_meta( $wpch_session_id, 'wpcamp_official_url', true );
		?>
		<main id="content" class="site-content wpch-attendees wpch-session" style="--wpch-accent:<?php echo esc_attr( $wpch_accent ); ?>">

			<section class="wpch-attendees__hero">
				<div class="wpch-attendees__inner">
					<?php if ( null !== $wpch_event ) : ?>
						<div class="wpch-attendees__eyebrow">
							<a class="wpch-attendees__back" href="<?php echo esc_url( (string) get_permalink( $wpch_event->get_id() ) ); ?>">
								<?php echo wpcamp_hub_icon( 'arrow-left', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
								<?php
								/* translators: %s: event title. */
								echo esc_html( sprintf( __( 'Back to %s', 'wpcamp-hub' ), get_the_title( $wpch_event->get_id() ) ) );
								?>
							</a>
						</div>
					<?php endif; ?>

					<h1 class="wpch-attendees__title"><?php the_title(); ?></h1>

					<div class="wpch-event__meta wpch-session__meta">
						<?php if ( $wpch_track ) : ?>
							<span class="wpch-event__metaitem">
								<span class="wpch-session__track-dot" style="--wpch-track:<?php echo esc_attr( $wpch_accent ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( $wpch_track->get_name() ); ?>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $wpch_when ) : ?>
							<span class="wpch-event__metaitem">
								<?php echo wpcamp_hub_icon( 'clock', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
								<?php echo esc_html( $wpch_when ); ?>
							</span>
						<?php endif; ?>

						<?php if ( array() !== $wpch_speakers ) : ?>
							<span class="wpch-event__metaitem">
								<?php echo wpcamp_hub_icon( 'mic', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
								<?php
								$wpch_names = array_map(
									static fn( $profile ): string => $profile->get_display_name(),
									$wpch_speakers
								);
								echo esc_html( implode( ', ', array_filter( $wpch_names ) ) );
								?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="wpch-attendees__body">
				<div class="wpch-attendees__inner">
					<div class="wpch-event__description wpch-session__content">
						<?php the_content(); ?>
					</div>

					<?php if ( '' !== $wpch_official_url ) : ?>
						<p class="wpch-session__source">
							<a class="wpch-event__section-link" href="<?php echo esc_url( $wpch_official_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Read the full article', 'wpcamp-hub' ); ?>
								<?php echo wpcamp_hub_icon( 'external-link', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			</section>

		</main>
		<?php
	endwhile;
endif;

get_footer();
