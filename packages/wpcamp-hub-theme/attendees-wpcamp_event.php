<?php
/**
 * Attendees subpage for a single event — /event/<slug>/attendees/.
 *
 * Mirrors the prototype "Find your people" directory, but scoped to one event:
 * the people listed are exactly those related to this event through the
 * platform relationship map ({@see \WPCAMP_HUB\Data\Event::get_attendees()}).
 *
 * Routed here by the plugin's Attendees_Page (rewrite endpoint) via
 * `template_include` → `locate_template()`. Requires the WPCamp Hub plugin for
 * the Event/User_Profile entities.
 *
 * @package wpcamp-hub
 */

use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\User_Profile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wpch_event_id = get_queried_object_id();
$wpch_event    = class_exists( Event::class ) ? Event::from( $wpch_event_id ) : null;
$wpch_people   = $wpch_event ? $wpch_event->get_attendees() : array();
$wpch_title    = get_the_title( $wpch_event_id );

/**
 * Render one attendee card, mirroring the prototype PersonCard.
 *
 * @param User_Profile $person Attendee profile.
 */
$wpch_render_card = static function ( User_Profile $person ): void {
	$name    = $person->get_display_name();
	$company = $person->get_company();
	$role    = $person->get_role();
	$profile = $person->get_profile_url();
	$avatar  = $person->get_avatar_url();

	// Subtitle: "Role · Company", whichever parts exist.
	$subtitle = trim( implode( ' · ', array_filter( array( $role, $company ) ) ) );

	$haystack = strtolower( trim( $name . ' ' . $role . ' ' . $company ) );
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
	</article>
	<?php
};
?>

<main class="wpch-attendees">
	<section class="wpch-attendees__hero">
		<div class="wpch-attendees__inner">
			<div class="wpch-attendees__eyebrow"><?php echo esc_html( $wpch_title ); ?></div>
			<h1 class="wpch-attendees__title"><?php esc_html_e( 'Find your people', 'wpcamp-hub' ); ?></h1>
			<p class="wpch-attendees__lead">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of attendees. */
						_n( '%d person is going.', '%d people are going.', count( $wpch_people ), 'wpcamp-hub' ),
						count( $wpch_people )
					)
				);
				esc_html_e( ' Profiles are enriched from WordPress.org and Gravatar — search, find common ground, and say hello early.', 'wpcamp-hub' );
				?>
			</p>
		</div>
	</section>

	<section class="wpch-attendees__body">
		<div class="wpch-attendees__inner">
			<div class="wpch-attendees__toolbar">
				<h2 class="wpch-attendees__subtitle"><?php esc_html_e( 'Everyone going', 'wpcamp-hub' ); ?></h2>
				<div class="wpch-attendees__search">
					<?php echo wpcamp_hub_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<input
						type="search"
						class="wpch-attendees__search-input"
						placeholder="<?php esc_attr_e( 'Search by name, role or company', 'wpcamp-hub' ); ?>"
						aria-label="<?php esc_attr_e( 'Search attendees', 'wpcamp-hub' ); ?>"
					/>
				</div>
			</div>

			<?php if ( array() !== $wpch_people ) : ?>
				<div class="wpch-attendees__grid">
					<?php
					foreach ( $wpch_people as $wpch_person ) :
						$wpch_render_card( $wpch_person );
					endforeach;
					?>
				</div>
				<p class="wpch-attendees__empty" hidden>
					<?php esc_html_e( 'No attendees match your search.', 'wpcamp-hub' ); ?>
				</p>
			<?php else : ?>
				<div class="wpch-attendees__empty">
					<?php echo wpcamp_hub_icon( 'users', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG from the theme icon set. ?>
					<p><?php esc_html_e( 'No attendees yet for this event.', 'wpcamp-hub' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>

<script>
( function () {
	var root = document.querySelector( '.wpch-attendees' );
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
