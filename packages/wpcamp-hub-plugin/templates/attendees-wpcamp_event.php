<?php
/**
 * Attendees subpage for a single event — /event/<slug>/attendees/.
 *
 * Mirrors the prototype "Find your people" directory, but scoped to one event:
 * the people listed are exactly those related to this event through the
 * platform relationship map ({@see \WPCAMP_HUB\Data\Event::get_attendees()}).
 *
 * Bundled fallback template; a theme may override it with
 * `attendees-wpcamp_event.php`.
 *
 * @package WPCAMP_HUB
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

// Self-contained initials fallback (the bundled template must not depend on
// theme helper functions, which may be absent under another theme).
$wpch_initials = static function ( string $name ): string {
	$name  = trim( $name );
	$parts = preg_split( '/\s+/', $name ) ?: array();
	$first = isset( $parts[0] ) ? mb_substr( $parts[0], 0, 1 ) : '';
	$last  = count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '';

	return strtoupper( $first . $last );
};

/**
 * Render one attendee card, mirroring the prototype PersonCard.
 *
 * @param User_Profile $person Attendee profile.
 * @param callable     $initials Initials fallback renderer.
 */
$wpch_render_card = static function ( User_Profile $person, callable $initials ): void {
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
				<span class="wpch-att-card__avatar wpch-att-card__avatar--initials" aria-hidden="true"><?php echo esc_html( $initials( $name ) ); ?></span>
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
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
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
						$wpch_render_card( $wpch_person, $wpch_initials );
					endforeach;
					?>
				</div>
				<p class="wpch-attendees__empty" hidden>
					<?php esc_html_e( 'No attendees match your search.', 'wpcamp-hub' ); ?>
				</p>
			<?php else : ?>
				<div class="wpch-attendees__empty">
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
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
