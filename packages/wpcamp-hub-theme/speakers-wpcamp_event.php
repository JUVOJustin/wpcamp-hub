<?php
/**
 * Speakers subpage for a single event — /event/<slug>/speakers/.
 *
 * Lists the speakers presenting at this event (event → sessions → speakers),
 * each with a link to their session in this event when one is linked.
 *
 * Routed here by the plugin's Speakers_Page (rewrite endpoint) via
 * `template_include` → `locate_template()`. Requires the WPCamp Hub plugin for
 * the Event/Session/User_Profile entities.
 *
 * @package wpcamp-hub
 */

use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Session;
use WPCAMP_HUB\Data\User_Profile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wpch_event_id = get_queried_object_id();
$wpch_event    = class_exists( Event::class ) ? Event::from( $wpch_event_id ) : null;
$wpch_speakers = $wpch_event ? $wpch_event->get_speakers() : array();
$wpch_title    = get_the_title( $wpch_event_id );

// Map of this event's session IDs, so a speaker's session can be matched to
// the one belonging to THIS event (a speaker may have sessions elsewhere).
$wpch_event_session_ids = array();
if ( $wpch_event ) {
	foreach ( $wpch_event->get_sessions() as $wpch_session ) {
		$wpch_event_session_ids[ $wpch_session->get_id() ] = true;
	}
}

/**
 * Render one speaker card, with a link to their session in this event.
 *
 * @param User_Profile      $person Speaker profile.
 * @param array<int,bool>   $event_session_ids Lookup of this event's session IDs.
 */
$wpch_render_card = static function ( User_Profile $person, array $event_session_ids ): void {
	$name    = $person->get_display_name();
	$company = $person->get_company();
	$role    = $person->get_role();
	$profile = $person->get_profile_url();
	$avatar  = $person->get_avatar_url();

	$subtitle = trim( implode( ' · ', array_filter( array( $role, $company ) ) ) );

	// The speaker's session that belongs to this event (first match wins).
	$session = null;
	foreach ( $person->get_sessions() as $candidate ) {
		if ( isset( $event_session_ids[ $candidate->get_id() ] ) ) {
			$session = $candidate;
			break;
		}
	}
	$session_title = $session instanceof Session ? get_the_title( $session->get_id() ) : '';
	$session_url   = $session instanceof Session ? (string) get_permalink( $session->get_id() ) : '';

	$haystack = strtolower( trim( $name . ' ' . $role . ' ' . $company . ' ' . $session_title ) );
	?>
	<article class="wpch-att-card" data-search="<?php echo esc_attr( $haystack ); ?>">
		<?php if ( '' !== $profile ) : ?>
			<a class="wpch-att-card__link" href="<?php echo esc_url( $profile ); ?>" target="_blank" rel="noopener noreferrer">
		<?php endif; ?>
		<div class="wpch-att-card__head">
			<?php if ( '' !== $avatar ) : ?>
				<img class="wpch-att-card__avatar" src="<?php echo esc_url( $avatar ); ?>" alt="" width="52" height="52" loading="lazy" decoding="async" />
			<?php else : ?>
				<span class="wpch-att-card__avatar wpch-att-card__avatar--initials" aria-hidden="true"><?php echo esc_html( wpcamp_hub_initials( $name ) ); ?></span>
			<?php endif; ?>
			<div class="wpch-att-card__person">
				<h3 class="wpch-att-card__name"><?php echo esc_html( $name ); ?></h3>
				<?php if ( '' !== $subtitle ) : ?>
					<div class="wpch-att-card__meta"><?php echo esc_html( $subtitle ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( '' !== $profile ) : ?>
			</a>
		<?php endif; ?>
		<?php if ( '' !== $session_url && '' !== $session_title ) : ?>
			<a class="wpch-att-card__session" href="<?php echo esc_url( $session_url ); ?>">
				<?php echo wpcamp_hub_icon( 'mic', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
				<span><?php echo esc_html( $session_title ); ?></span>
			</a>
		<?php endif; ?>
	</article>
	<?php
};
?>

<main class="wpch-attendees wpch-speakers">
	<section class="wpch-attendees__hero">
		<div class="wpch-attendees__inner">
			<div class="wpch-attendees__eyebrow">
				<a class="wpch-attendees__back" href="<?php echo esc_url( (string) get_permalink( $wpch_event_id ) ); ?>">
					<?php echo wpcamp_hub_icon( 'arrow-left', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<?php
					/* translators: %s: event title. */
					echo esc_html( sprintf( __( 'Back to %s', 'wpcamp-hub' ), $wpch_title ) );
					?>
				</a>
			</div>
			<h1 class="wpch-attendees__title"><?php esc_html_e( 'Speakers', 'wpcamp-hub' ); ?></h1>
			<p class="wpch-attendees__lead">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of speakers. */
						_n( '%d speaker is presenting.', '%d speakers are presenting.', count( $wpch_speakers ), 'wpcamp-hub' ),
						count( $wpch_speakers )
					)
				);
				esc_html_e( ' Browse who’s on the programme and jump straight to their session.', 'wpcamp-hub' );
				?>
			</p>
		</div>
	</section>

	<section class="wpch-attendees__body">
		<div class="wpch-attendees__inner">
			<div class="wpch-attendees__toolbar">
				<h2 class="wpch-attendees__subtitle"><?php esc_html_e( 'On the programme', 'wpcamp-hub' ); ?></h2>
				<div class="wpch-attendees__search">
					<?php echo wpcamp_hub_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<input
						type="search"
						class="wpch-attendees__search-input"
						placeholder="<?php esc_attr_e( 'Search by name, role or session', 'wpcamp-hub' ); ?>"
						aria-label="<?php esc_attr_e( 'Search speakers', 'wpcamp-hub' ); ?>"
					/>
				</div>
			</div>

			<?php if ( array() !== $wpch_speakers ) : ?>
				<div class="wpch-attendees__grid">
					<?php
					foreach ( $wpch_speakers as $wpch_speaker ) :
						$wpch_render_card( $wpch_speaker, $wpch_event_session_ids );
					endforeach;
					?>
				</div>
				<p class="wpch-attendees__empty" hidden>
					<?php esc_html_e( 'No speakers match your search.', 'wpcamp-hub' ); ?>
				</p>
			<?php else : ?>
				<div class="wpch-attendees__empty">
					<?php echo wpcamp_hub_icon( 'mic', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<p><?php esc_html_e( 'No speakers announced yet for this event.', 'wpcamp-hub' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>

<script>
( function () {
	var root = document.querySelector( '.wpch-speakers' );
	if ( ! root ) {
		return;
	}
	var input = root.querySelector( '.wpch-attendees__search-input' );
	var cards = Array.prototype.slice.call( root.querySelectorAll( '.wpch-att-card' ) );
	var empty = root.querySelector( '.wpch-attendees__empty[hidden]' );
	if ( ! input ) {
		return;
	}
	input.addEventListener( 'input', function () {
		var q = input.value.trim().toLowerCase();
		var shown = 0;
		cards.forEach( function ( card ) {
			var match = '' === q || ( card.getAttribute( 'data-search' ) || '' ).indexOf( q ) !== -1;
			card.hidden = ! match;
			if ( match ) {
				shown++;
			}
		} );
		if ( empty ) {
			empty.hidden = shown !== 0;
		}
	} );
}() );
</script>

<?php
get_footer();
