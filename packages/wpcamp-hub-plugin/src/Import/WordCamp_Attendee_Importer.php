<?php
/**
 * WordCamp attendee page importer.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Import;

use WPCAMP_HUB\Data\Data_Structure;
use WPCAMP_HUB\Data\Event;
use WPCAMP_HUB\Data\Relationships;
use WPCAMP_HUB\Data\User_Profile;

/**
 * Imports WordCamp attendee pages by accepting only WordPress.org profile and Gravatar URLs.
 */
class WordCamp_Attendee_Importer {

	public const string AS_HOOK                   = 'wpcamp_hub/import_wordcamp_attendees';
	public const string AS_EVENT_HOOK             = 'wpcamp_hub/upsert_event_attendees';
	public const string AS_GROUP                  = 'wpcamp-hub';
	private const float MIN_AI_PROFILE_CONFIDENCE = 0.6;
	private const int AI_HTML_CONTEXT_LIMIT       = 30000;

	/**
	 * Register the recurring importer job when Action Scheduler is ready.
	 */
	public function schedule_daily_import(): void {
		if ( false !== as_next_scheduled_action( self::AS_HOOK, array(), self::AS_GROUP ) ) {
			return;
		}

		as_schedule_recurring_action(
			time() + HOUR_IN_SECONDS,
			DAY_IN_SECONDS,
			self::AS_HOOK,
			array(),
			self::AS_GROUP,
			true
		);
	}

	/**
	 * Remove pending importer jobs.
	 */
	public static function unschedule_daily_import(): void {
		as_unschedule_all_actions( self::AS_HOOK, array(), self::AS_GROUP );
		as_unschedule_all_actions( self::AS_EVENT_HOOK, array(), self::AS_GROUP );
	}

	/**
	 * Queue individual imports for events that have an attendees page configured.
	 */
	public function import_scheduled_events(): void {
		foreach ( $this->get_event_attendee_pages() as $event_id => $attendees_url ) {
			$this->queue_event_import( (int) $event_id, $attendees_url );
		}
	}

	/**
	 * Queue one event attendee import unless an equivalent action is already pending.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $attendees_url WordCamp attendees page URL.
	 */
	private function queue_event_import( int $event_id, string $attendees_url ): void {
		if ( ! self::is_allowed_attendees_url( $attendees_url ) ) {
			return;
		}

		$args = array( $event_id, $attendees_url );
		if ( false !== as_has_scheduled_action( self::AS_EVENT_HOOK, $args, self::AS_GROUP ) ) {
			return;
		}

		as_enqueue_async_action( self::AS_EVENT_HOOK, $args, self::AS_GROUP, true );
	}

	/**
	 * Fetch and import attendees for one event.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $attendees_url WordCamp attendees page URL.
	 * @return list<int> Imported attendee user IDs.
	 * @throws \RuntimeException When fetch fails or import fails.
	 */
	public function import_event_attendees( int $event_id, string $attendees_url ): array {
		if ( ! self::is_allowed_attendees_url( $attendees_url ) ) {
			throw new \RuntimeException( 'Disallowed attendees URL: ' . esc_url_raw( $attendees_url ) );
		}

		$response = wp_safe_remote_get(
			$attendees_url,
			array(
				'timeout'            => 20,
				'redirection'        => 5,
				'reject_unsafe_urls' => true,
				'user-agent'         => 'WPCamp Hub/' . ( defined( 'WPCAMP_HUB_VERSION' ) ? WPCAMP_HUB_VERSION : '1.0.0' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Failed to fetch attendees page: ' . esc_html( $response->get_error_message() ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new \RuntimeException( 'Attendees page returned HTTP status: ' . absint( $status_code ) );
		}

		return $this->import_attendees_from_html( $event_id, wp_remote_retrieve_body( $response ), $attendees_url );
	}

	/**
	 * Import attendees from a fetched page body.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $html Attendees page HTML.
	 * @param string $source_url Source attendees page URL.
	 * @return list<int> Imported attendee user IDs.
	 */
	public function import_attendees_from_html( int $event_id, string $html, string $source_url ): array {
		$user_ids = array();

		foreach ( $this->extract_attendees( $html, $source_url ) as $attendee ) {
			$user_ids[] = $this->upsert_attendee( $event_id, $attendee, $source_url );
		}

		return array_values( array_unique( array_filter( $user_ids ) ) );
	}

	/**
	 * Extract attendee candidates from URL patterns only.
	 *
	 * @param string $html Attendees page HTML.
	 * @param string $source_url Source attendees page URL.
	 * @return list<array<string,mixed>>
	 */
	public function extract_attendees( string $html, string $source_url = '' ): array {
		return $this->resolve_attendees( $this->extract_identity_urls( $html, $source_url ) );
	}

	/**
	 * Extract supported identity URLs from any matching element on the page.
	 *
	 * @param string $html Attendees page HTML.
	 * @param string $source_url Source attendees page URL.
	 * @return array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,identity_groups:list<array<string,mixed>>}
	 * @throws \RuntimeException When AI profile discovery fails.
	 */
	public function extract_identity_urls( string $html, string $source_url = '' ): array {
		$ai_profile = $this->discover_ai_parsing_profile( $html, $source_url );

		return $this->extract_identity_urls_with_profile( $html, $ai_profile );
	}

	/**
	 * Validate that scheduled attendee fetches use a public URL shape accepted by WordPress.
	 *
	 * @param string $url Candidate attendees page URL.
	 */
	public static function is_allowed_attendees_url( string $url ): bool {
		return false !== wp_http_validate_url( $url );
	}

	/**
	 * Extract supported identity URLs using one optional attendee wrapper profile.
	 *
	 * @param string              $html Attendees page HTML.
	 * @param array<string,mixed> $profile Parsing profile.
	 * @return array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,identity_groups:list<array<string,mixed>>}
	 */
	private function extract_identity_urls_with_profile( string $html, array $profile ): array {
		$processor       = new \WP_HTML_Tag_Processor( $html );
		$wporg_usernames = array();
		$gravatar_hashes = array();
		$identity_groups = array();
		$current_group   = null;
		$group_depth     = 0;

		while ( $processor->next_token() ) {
			$tag_name = (string) $processor->get_tag();

			if ( $this->is_profile_wrapper_open( $processor, $profile, $current_group ) ) {
				$this->append_identity_group( $identity_groups, $current_group );
				$current_group = $this->empty_identity_group();
				$group_depth   = 1;
				continue;
			}

			if ( null !== $current_group && $this->is_group_depth_tag( $tag_name, $profile ) ) {
				if ( $processor->is_tag_closer() ) {
					--$group_depth;
					if ( 0 >= $group_depth ) {
						$this->append_identity_group( $identity_groups, $current_group );
						$current_group = null;
						$group_depth   = 0;
					}
					continue;
				}

				++$group_depth;
			}

			if ( null === $current_group ) {
				continue;
			}

			if ( 'A' === $tag_name && ! $processor->is_tag_closer() ) {
				$href     = $processor->get_attribute( 'href' );
				$username = is_string( $href ) ? self::extract_wporg_username( $href ) : '';
				$url      = is_string( $href ) ? $this->normalize_extracted_url( $href ) : '';

				if ( '' !== $username ) {
					$wporg_usernames[ $username ]                  = 'https://profiles.wordpress.org/' . $username . '/';
					$current_group['wporg_usernames'][ $username ] = $username;
				} elseif ( '' !== $url ) {
					if ( self::is_twitter_url( $url ) ) {
						$current_group['twitter_urls'][ $url ] = $url;
					} elseif ( $this->is_public_profile_hint_url( $url ) ) {
						$current_group['website_urls'][ $url ] = $url;
					}
				}

				continue;
			}

			if ( 'IMG' === $tag_name && ! $processor->is_tag_closer() ) {
				$src  = $processor->get_attribute( 'src' );
				$hash = is_string( $src ) ? self::extract_gravatar_hash( $src ) : '';

				if ( '' !== $hash ) {
					$gravatar_hashes[ $hash ]                  = esc_url_raw( html_entity_decode( (string) $src, ENT_QUOTES ) );
					$current_group['gravatar_hashes'][ $hash ] = $hash;
				}
			}
		}

		$this->append_identity_group( $identity_groups, $current_group );

		return array(
			'wporg_usernames' => $wporg_usernames,
			'gravatar_hashes' => $gravatar_hashes,
			'identity_groups' => $identity_groups,
		);
	}

	/**
	 * Determine whether the current token starts a profiled attendee wrapper.
	 *
	 * @param \WP_HTML_Tag_Processor   $processor HTML processor.
	 * @param array<string,mixed>      $profile Parsing profile.
	 * @param array<string,mixed>|null $current_group Current active group.
	 */
	private function is_profile_wrapper_open(
		\WP_HTML_Tag_Processor $processor,
		array $profile,
		?array $current_group
	): bool {
		if ( null !== $current_group || $processor->is_tag_closer() ) {
			return false;
		}

		if ( ! $this->is_group_depth_tag( (string) $processor->get_tag(), $profile ) ) {
			return false;
		}

		$item_id = isset( $profile['item_id'] ) && is_string( $profile['item_id'] ) ? $profile['item_id'] : '';
		if ( '' !== $item_id && $processor->get_attribute( 'id' ) !== $item_id ) {
			return false;
		}

		$item_class = isset( $profile['item_class'] ) && is_string( $profile['item_class'] ) ? $profile['item_class'] : '';
		if ( '' !== $item_class ) {
			$class_attribute = $processor->get_attribute( 'class' );
			if ( ! is_string( $class_attribute ) || ! $this->class_attribute_contains( $class_attribute, $item_class ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether a tag participates in wrapper depth tracking.
	 *
	 * @param string              $tag_name Current tag.
	 * @param array<string,mixed> $profile Parsing profile.
	 */
	private function is_group_depth_tag( string $tag_name, array $profile ): bool {
		$item_tag = isset( $profile['item_tag'] ) && is_string( $profile['item_tag'] ) ? $profile['item_tag'] : '';
		return '' !== $item_tag && strtoupper( $item_tag ) === strtoupper( $tag_name );
	}

	/**
	 * Determine whether a class attribute has one exact class token.
	 *
	 * @param string $class_attribute Class attribute.
	 * @param string $class_name Class name.
	 */
	private function class_attribute_contains( string $class_attribute, string $class_name ): bool {
		$classes = preg_split( '/\s+/', trim( $class_attribute ) );
		return is_array( $classes ) && in_array( $class_name, $classes, true );
	}

	/**
	 * Discover an attendee wrapper profile with the WordPress AI Client.
	 *
	 * @param string $html Attendees page HTML.
	 * @param string $source_url Source attendees page URL.
	 * @return array<string,mixed>
	 * @throws \RuntimeException When AI client is available but fails to generate a valid profile.
	 */
	private function discover_ai_parsing_profile( string $html, string $source_url ): array {
		$filtered_profile = apply_filters(
			'wpcamp_hub_attendee_importer_ai_parsing_profile',
			null,
			$html,
			$source_url
		);

		if ( is_array( $filtered_profile ) ) {
			$validated = $this->validate_ai_parsing_profile( $filtered_profile );
			if ( null !== $validated ) {
				return $validated;
			}
		}

		$static_profile = $this->discover_static_parsing_profile( $html );
		if ( null !== $static_profile ) {
			return $static_profile;
		}

		if ( '' === $source_url ) {
			throw new \RuntimeException( 'No source URL available and no static parsing profile matched' );
		}

		$cache_key      = 'wpcamp_hub_ai_parser_' . md5( $source_url );
		$cached_profile = get_transient( $cache_key );
		if ( is_array( $cached_profile ) ) {
			$validated = $this->validate_ai_parsing_profile( $cached_profile );
			if ( null !== $validated ) {
				return $validated;
			}
		}

		$profile = $this->generate_ai_parsing_profile( $html );
		set_transient( $cache_key, $profile, WEEK_IN_SECONDS );
		return $profile;
	}

	/**
	 * Recognize common attendee page structures that do not need AI discovery.
	 *
	 * @param string $html Attendees page HTML.
	 * @return array<string,mixed>|null
	 */
	private function discover_static_parsing_profile( string $html ): ?array {
		if ( ! str_contains( $html, 'tix-attendee-list' ) ) {
			return null;
		}

		return array(
			'item_tag'   => 'li',
			'confidence' => 1.0,
		);
	}

	/**
	 * Ask the WordPress AI Client for a deterministic parsing profile.
	 *
	 * @param string $html Attendees page HTML.
	 * @return array<string,mixed>
	 * @throws \RuntimeException When AI client fails or returns invalid response.
	 */
	private function generate_ai_parsing_profile( string $html ): array {
		$prompt = sprintf(
			"%s\n\nFetched HTML context, truncated to the first %d characters:\n%s",
			$this->ai_parser_system_instruction(),
			self::AI_HTML_CONTEXT_LIMIT,
			$this->ai_html_context( $html )
		);

		// @phpstan-ignore-next-line The WordPress AI Client function is provided by WordPress 7.0.
		$json = wp_ai_client_prompt( $prompt )
			->as_json_response( $this->ai_parsing_profile_schema() )
			->generate_text();
		if ( is_wp_error( $json ) ) {
			throw new \RuntimeException( 'AI parsing profile generation failed: ' . esc_html( $json->get_error_message() ) );
		}
		if ( ! is_string( $json ) ) {
			throw new \RuntimeException( 'AI parsing profile generation returned invalid response type' );
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( 'AI parsing profile generation returned invalid JSON' );
		}

		$validated = $this->validate_ai_parsing_profile( $decoded );
		if ( null === $validated ) {
			throw new \RuntimeException( 'AI parsing profile validation failed: confidence too low or invalid structure' );
		}

		return $validated;
	}

	/**
	 * Return the bounded fetched page context sent to the AI parser.
	 *
	 * @param string $html Attendees page HTML.
	 */
	private function ai_html_context( string $html ): string {
		$clean_html = $this->clean_html_for_ai( $html );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $clean_html, 0, self::AI_HTML_CONTEXT_LIMIT );
		}

		return substr( $clean_html, 0, self::AI_HTML_CONTEXT_LIMIT );
	}

	/**
	 * Clean HTML for AI parsing by extracting only the body content.
	 *
	 * @param string $html Raw HTML.
	 * @return string Body HTML.
	 */
	private function clean_html_for_ai( string $html ): string {
		$processor  = \WP_HTML_Processor::create_full_parser( $html );
		$body_html  = '';
		$in_body    = false;
		$skip_tag   = '';
		$skip_depth = 0;

		while ( $processor->next_token() ) {
			$tag_name   = strtoupper( (string) $processor->get_tag() );
			$token_type = $processor->get_token_type();

			if ( 'BODY' === $tag_name && ! $processor->is_tag_closer() ) {
				$in_body = true;
				continue;
			}
			if ( 'BODY' === $tag_name && $processor->is_tag_closer() ) {
				break;
			}
			if ( ! $in_body ) {
				continue;
			}

			if ( '#text' === $token_type && '' !== $skip_tag ) {
				continue;
			}

			if ( '#tag' === $token_type ) {
				if ( '' !== $skip_tag ) {
					if ( $tag_name === $skip_tag && $processor->is_tag_closer() ) {
						--$skip_depth;
						if ( 0 === $skip_depth ) {
							$skip_tag = '';
						}
					} elseif ( $tag_name === $skip_tag && ! $processor->is_tag_closer() ) {
						++$skip_depth;
					}
					continue;
				}

				if ( ! $processor->is_tag_closer() ) {
					if ( in_array( $tag_name, array( 'SCRIPT', 'STYLE', 'NOSCRIPT', 'IFRAME' ), true ) ) {
						$token = $processor->serialize_token();
						if ( str_contains( strtolower( $token ), '</' . strtolower( $tag_name ) . '>' ) ) {
							continue;
						}
						$skip_tag   = $tag_name;
						$skip_depth = 1;
						continue;
					}
				}
			}
			if ( '#funky-comment' === $token_type ) {
				continue;
			}

			$body_html .= $processor->serialize_token();
		}

		return $body_html;
	}

	/**
	 * Return system instructions for AI-assisted parsing profile discovery.
	 */
	private function ai_parser_system_instruction(): string {
		return implode(
			"\n",
			array(
				'You inspect WordCamp attendee page HTML and return a parsing profile.',
				'Do not extract attendee records or personal data.',
				'Return only structural selectors that PHP can validate deterministically.',
				'Prefer the smallest repeated wrapper for one attendee, such as li, article, or div.',
				'item_tag, item_id, and item_class must describe the same repeated attendee element.',
				'Use item_id only when the repeated attendee element itself has that exact id.',
				'Use item_class only when each repeated attendee element itself has that class token.',
				'Do not put ancestor container ids or classes in item_id or item_class.',
				'Leave item_id and item_class empty when the repeated attendee element has no stable id or class.',
				'If uncertain, return low confidence.',
			)
		);
	}

	/**
	 * Return the JSON schema expected from the AI Client.
	 *
	 * @return array<string,mixed>
	 */
	private function ai_parsing_profile_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'item_tag'         => array(
					'type'        => 'string',
					'description' => 'Tag name of the repeated element that wraps exactly one attendee.',
				),
				'item_id'          => array(
					'type'        => 'string',
					'description' => 'ID on the repeated attendee element itself, or an empty string. Never use an ancestor container ID.',
				),
				'item_class'       => array(
					'type'        => 'string',
					'description' => 'Class token on each repeated attendee element itself, or an empty string. Never use an ancestor container class.',
				),
				'avatar_selector'  => array(
					'type'        => 'string',
					'description' => 'Optional selector for avatar URLs inside one attendee wrapper.',
				),
				'wporg_selector'   => array(
					'type'        => 'string',
					'description' => 'Optional selector for WordPress.org URLs inside one attendee wrapper.',
				),
				'twitter_selector' => array(
					'type'        => 'string',
					'description' => 'Optional selector for Twitter or X URLs inside one attendee wrapper.',
				),
				'website_selector' => array(
					'type'        => 'string',
					'description' => 'Optional selector for website URLs inside one attendee wrapper.',
				),
				'confidence'       => array(
					'type'        => 'number',
					'description' => 'Confidence from 0 to 1.',
				),
				'reason'           => array(
					'type'        => 'string',
					'description' => 'Short explanation of the repeated attendee wrapper choice.',
				),
			),
			'required'             => array(
				'item_tag',
				'item_id',
				'item_class',
				'avatar_selector',
				'wporg_selector',
				'twitter_selector',
				'website_selector',
				'confidence',
				'reason',
			),
		);
	}

	/**
	 * Normalize and validate an AI parsing profile before use.
	 *
	 * @param array<string,mixed> $profile Raw profile.
	 * @return array<string,mixed>|null
	 */
	private function validate_ai_parsing_profile( array $profile ): ?array {
		$item_tag   = isset( $profile['item_tag'] ) && is_string( $profile['item_tag'] ) ? strtoupper( $profile['item_tag'] ) : '';
		$item_id    = isset( $profile['item_id'] ) && is_string( $profile['item_id'] ) ? trim( $profile['item_id'] ) : '';
		$item_class = isset( $profile['item_class'] ) && is_string( $profile['item_class'] ) ? trim( $profile['item_class'] ) : '';
		$confidence = isset( $profile['confidence'] ) && is_numeric( $profile['confidence'] ) ? (float) $profile['confidence'] : 0.0;

		if (
			! preg_match( '/^[A-Z][A-Z0-9-]*$/', $item_tag ) ||
			in_array( $item_tag, array( 'A', 'IMG', 'SCRIPT', 'STYLE' ), true ) ||
			self::MIN_AI_PROFILE_CONFIDENCE > $confidence
		) {
			return null;
		}

		if ( '' !== $item_id && ! preg_match( '/^[A-Za-z][A-Za-z0-9_:.:-]*$/', $item_id ) ) {
			return null;
		}

		if ( '' !== $item_class && ! preg_match( '/^[A-Za-z_-][A-Za-z0-9_-]*$/', $item_class ) ) {
			return null;
		}

		return array(
			'item_tag'         => $item_tag,
			'item_id'          => $item_id,
			'item_class'       => $item_class,
			'avatar_selector'  => isset( $profile['avatar_selector'] ) && is_string( $profile['avatar_selector'] ) ? $profile['avatar_selector'] : '',
			'wporg_selector'   => isset( $profile['wporg_selector'] ) && is_string( $profile['wporg_selector'] ) ? $profile['wporg_selector'] : '',
			'twitter_selector' => isset( $profile['twitter_selector'] ) && is_string( $profile['twitter_selector'] ) ? $profile['twitter_selector'] : '',
			'website_selector' => isset( $profile['website_selector'] ) && is_string( $profile['website_selector'] ) ? $profile['website_selector'] : '',
			'confidence'       => $confidence,
			'reason'           => isset( $profile['reason'] ) && is_string( $profile['reason'] ) ? $profile['reason'] : '',
		);
	}

	/**
	 * Build an empty identity group for one attendee row.
	 *
	 * @return array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,website_urls:array<string,string>,twitter_urls:array<string,string>}
	 */
	private function empty_identity_group(): array {
		return array(
			'wporg_usernames' => array(),
			'gravatar_hashes' => array(),
			'website_urls'    => array(),
			'twitter_urls'    => array(),
		);
	}

	/**
	 * Append a non-empty identity group.
	 *
	 * @param list<array<string,mixed>> $groups Extracted attendee groups.
	 * @param array<string,mixed>|null  $group Current group.
	 */
	private function append_identity_group( array &$groups, ?array $group ): void {
		if (
			null === $group ||
			(
				empty( $group['wporg_usernames'] ) &&
				empty( $group['gravatar_hashes'] )
			)
		) {
			return;
		}

		$groups[] = array(
			'wporg_usernames' => array_values( $group['wporg_usernames'] ),
			'gravatar_hashes' => array_values( $group['gravatar_hashes'] ),
			'website_urls'    => array_values( $group['website_urls'] ),
			'twitter_urls'    => array_values( $group['twitter_urls'] ),
		);
	}

	/**
	 * Extract a WordPress.org profile username from accepted profile URL shapes.
	 *
	 * @param string $url Profile URL.
	 */
	public static function extract_wporg_username( string $url ): string {
		$parts = wp_parse_url( html_entity_decode( $url, ENT_QUOTES ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return '';
		}

		$host = strtolower( (string) $parts['host'] );
		$path = trim( (string) $parts['path'], '/' );

		if ( 'profiles.wordpress.org' === $host && preg_match( '#^([^/]+)(?:/profile)?$#', $path, $matches ) ) {
			return sanitize_user( rawurldecode( $matches[1] ), true );
		}

		if ( 'wordpress.org' === $host && preg_match( '#^support/users/([^/]+)/?$#', $path, $matches ) ) {
			return sanitize_user( rawurldecode( $matches[1] ), true );
		}

		return '';
	}

	/**
	 * Extract the Gravatar identifier from accepted avatar URL shapes.
	 *
	 * @param string $url Gravatar avatar URL.
	 */
	public static function extract_gravatar_hash( string $url ): string {
		$parts = wp_parse_url( html_entity_decode( $url, ENT_QUOTES ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return '';
		}

		$host = strtolower( (string) $parts['host'] );
		if ( ! in_array( $host, array( 'secure.gravatar.com', 'www.gravatar.com', 'gravatar.com' ), true ) ) {
			return '';
		}

		if ( ! preg_match( '#/avatar/([a-f0-9]{32,64})(?:$|[/?])#', strtolower( (string) $parts['path'] ), $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * Determine whether a URL is a Twitter/X profile hint.
	 *
	 * @param string $url URL to inspect.
	 */
	private static function is_twitter_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );
		$path = trim( (string) $parts['path'], '/' );

		if ( ! in_array( $host, array( 'twitter.com', 'www.twitter.com', 'x.com', 'www.x.com' ), true ) ) {
			return false;
		}

		return '' !== $path && ! str_contains( $path, '/' );
	}

	/**
	 * Normalize an extracted URL before storing it as profile context.
	 *
	 * @param string $url Raw URL.
	 */
	private function normalize_extracted_url( string $url ): string {
		$decoded = html_entity_decode( $url, ENT_QUOTES );
		$parts   = wp_parse_url( $decoded );

		if (
			! is_array( $parts ) ||
			empty( $parts['host'] ) ||
			empty( $parts['scheme'] ) ||
			! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true )
		) {
			return '';
		}

		return esc_url_raw( $decoded );
	}

	/**
	 * Determine whether a URL should be stored as attendee profile context.
	 *
	 * @param string $url URL to inspect.
	 */
	private function is_public_profile_hint_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );
		return ! in_array(
			$host,
			array(
				'profiles.wordpress.org',
				'wordpress.org',
				'secure.gravatar.com',
				'www.gravatar.com',
				'gravatar.com',
			),
			true
		);
	}

	/**
	 * Create or update one attendee user and relate it to the event.
	 *
	 * @param int                 $event_id Event post ID.
	 * @param array<string,mixed> $attendee Extracted attendee data.
	 * @param string              $source_url Source attendees page URL.
	 */
	private function upsert_attendee( int $event_id, array $attendee, string $source_url ): int {
		$user_id = $this->find_existing_attendee( $attendee );

		if ( 0 === $user_id ) {
			$user_id = User_Profile::create_attendee( $attendee['identifier'], $attendee['name'] )->get_id();
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $attendee['name'],
				'nickname'     => $attendee['name'],
				'user_url'     => $attendee['website_url'],
			)
		);

		update_user_meta( $user_id, 'wpcamp_wporg_username', $attendee['wporg_username'] );
		update_user_meta( $user_id, 'wpcamp_wporg_profile_url', $attendee['wporg_profile_url'] );
		update_user_meta( $user_id, 'wpcamp_gravatar_hash', $attendee['gravatar_hash'] );
		update_user_meta( $user_id, 'wpcamp_avatar', $attendee['avatar_url'] );
		update_user_meta( $user_id, 'wpcamp_social_links', $attendee['social_links'] );
		update_user_meta( $user_id, 'wpcamp_attendee_source_url', esc_url_raw( $source_url ) );
		update_user_meta( $user_id, 'wpcamp_last_imported_at', gmdate( 'c' ) );

		if ( ! empty( $attendee['wporg_profile'] ) ) {
			update_user_meta( $user_id, 'wpcamp_wporg_profile', $attendee['wporg_profile'] );
			$this->apply_wporg_profile( $user_id, $attendee['wporg_profile'] );
		}

		if ( ! empty( $attendee['gravatar_profile'] ) ) {
			update_user_meta( $user_id, 'wpcamp_gravatar_profile', $attendee['gravatar_profile'] );
			$this->apply_gravatar_profile( $user_id, $attendee['gravatar_profile'] );
		}

		Relationships::relate( 'user', $user_id, 'event', $event_id );
		update_post_meta( $event_id, 'wpcamp_related_attendees', Event::from( $event_id )->get_related( 'user' ) );

		return $user_id;
	}

	/**
	 * Apply basic public WordPress.org profile fields to the local attendee.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $profile WordPress.org profile API response.
	 */
	private function apply_wporg_profile( int $user_id, array $profile ): void {
		if ( ! empty( $profile['name'] ) && is_string( $profile['name'] ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => sanitize_text_field( $profile['name'] ),
					'nickname'     => sanitize_text_field( $profile['name'] ),
				)
			);
		}

		if ( ! empty( $profile['description'] ) && is_string( $profile['description'] ) ) {
			update_user_meta( $user_id, 'wpcamp_bio', wp_strip_all_tags( $profile['description'] ) );
		}

		if ( ! empty( $profile['avatar_urls']['96'] ) && is_string( $profile['avatar_urls']['96'] ) ) {
			update_user_meta(
				$user_id,
				'wpcamp_avatar',
				esc_url_raw( set_url_scheme( $profile['avatar_urls']['96'], 'https' ) )
			);
		}
	}

	/**
	 * Apply basic public Gravatar profile fields to the local attendee.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $profile Gravatar profile API response.
	 */
	private function apply_gravatar_profile( int $user_id, array $profile ): void {
		if ( ! empty( $profile['display_name'] ) && is_string( $profile['display_name'] ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => sanitize_text_field( $profile['display_name'] ),
					'nickname'     => sanitize_text_field( $profile['display_name'] ),
				)
			);
		}

		if ( ! empty( $profile['avatar_url'] ) && is_string( $profile['avatar_url'] ) ) {
			update_user_meta( $user_id, 'wpcamp_avatar', esc_url_raw( set_url_scheme( $profile['avatar_url'], 'https' ) ) );
		}

		if (
			'' === get_user_meta( $user_id, 'wpcamp_bio', true ) &&
			! empty( $profile['description'] ) &&
			is_string( $profile['description'] )
		) {
			update_user_meta( $user_id, 'wpcamp_bio', wp_strip_all_tags( $profile['description'] ) );
		}

		if ( ! empty( $profile['company'] ) && is_string( $profile['company'] ) ) {
			update_user_meta( $user_id, 'wpcamp_company', sanitize_text_field( $profile['company'] ) );
		}

		if ( ! empty( $profile['job_title'] ) && is_string( $profile['job_title'] ) ) {
			update_user_meta( $user_id, 'wpcamp_community_role', sanitize_text_field( $profile['job_title'] ) );
		}
	}

	/**
	 * Build attendee records by grouping identity URLs from parsed attendee rows.
	 *
	 * @param array $signals Extracted identity URLs.
	 * @phpstan-param array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,identity_groups:list<array<string,mixed>>} $signals
	 * @return list<array<string,mixed>>
	 */
	private function resolve_attendees( array $signals ): array {
		$wporg_profiles    = array();
		$gravatar_profiles = array();
		$parents           = array();

		foreach ( $signals['wporg_usernames'] as $username => $profile_url ) {
			unset( $profile_url );
			$node             = 'wporg:' . $username;
			$parents[ $node ] = $node;
		}

		foreach ( $signals['gravatar_hashes'] as $hash => $avatar_url ) {
			unset( $avatar_url );
			$node             = 'gravatar:' . $hash;
			$parents[ $node ] = $node;
		}

		foreach ( $signals['identity_groups'] as $identity_group ) {
			$nodes = $this->identity_nodes_from_group( $identity_group );
			if ( count( $nodes ) < 2 ) {
				continue;
			}

			$first_node = array_shift( $nodes );
			foreach ( $nodes as $node ) {
				$this->union_identity_nodes( $parents, $first_node, $node );
			}
		}

		$groups = array();
		foreach ( array_keys( $parents ) as $node ) {
			$groups[ $this->find_identity_root( $parents, $node ) ][] = $node;
		}

		$attendees = array();
		foreach ( $groups as $nodes ) {
			$attendee = $this->attendee_from_identity_nodes(
				$nodes,
				$signals,
				$wporg_profiles,
				$gravatar_profiles
			);
			if ( null !== $attendee ) {
				$attendees[ $attendee['identifier'] ] = $attendee;
			}
		}

		return array_values( $attendees );
	}

	/**
	 * Return identity nodes represented by an attendee row.
	 *
	 * @param array<string,mixed> $identity_group Extracted identity group.
	 * @return list<string>
	 */
	private function identity_nodes_from_group( array $identity_group ): array {
		$nodes = array();

		foreach ( $identity_group['wporg_usernames'] ?? array() as $username ) {
			if ( is_string( $username ) && '' !== $username ) {
				$nodes[] = 'wporg:' . $username;
			}
		}

		foreach ( $identity_group['gravatar_hashes'] ?? array() as $hash ) {
			if ( is_string( $hash ) && '' !== $hash ) {
				$nodes[] = 'gravatar:' . $hash;
			}
		}

		return array_values( array_unique( $nodes ) );
	}

	/**
	 * Merge two identity nodes into the same attendee group.
	 *
	 * @param array<string,string> $parents Identity parent pointers.
	 * @param string               $left First identity node.
	 * @param string               $right Second identity node.
	 */
	private function union_identity_nodes( array &$parents, string $left, string $right ): void {
		$left_root  = $this->find_identity_root( $parents, $left );
		$right_root = $this->find_identity_root( $parents, $right );

		if ( $left_root !== $right_root ) {
			$parents[ $right_root ] = $left_root;
		}
	}

	/**
	 * Find the root node for one identity.
	 *
	 * @param array<string,string> $parents Identity parent pointers.
	 * @param string               $node Identity node.
	 */
	private function find_identity_root( array &$parents, string $node ): string {
		if ( ! isset( $parents[ $node ] ) ) {
			$parents[ $node ] = $node;
		}

		if ( $parents[ $node ] !== $node ) {
			$parents[ $node ] = $this->find_identity_root( $parents, $parents[ $node ] );
		}

		return $parents[ $node ];
	}

	/**
	 * Convert one merged identity group into an attendee payload.
	 *
	 * @param array $nodes Identity nodes in the group.
	 * @param array $signals Extracted identity URLs.
	 * @param array $wporg_profiles WordPress.org profile payloads keyed by username.
	 * @param array $gravatar_profiles Gravatar profile payloads keyed by hash.
	 * @phpstan-param array<string> $nodes
	 * @phpstan-param array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,identity_groups:list<array<string,mixed>>} $signals
	 * @phpstan-param array<string,array<string,mixed>> $wporg_profiles
	 * @phpstan-param array<string,array<string,mixed>> $gravatar_profiles
	 * @return array<string,mixed>|null
	 */
	private function attendee_from_identity_nodes(
		array $nodes,
		array $signals,
		array $wporg_profiles,
		array $gravatar_profiles
	): ?array {
		$wporg_username = '';
		$gravatar_hash  = '';

		foreach ( $nodes as $node ) {
			if ( str_starts_with( $node, 'wporg:' ) && '' === $wporg_username ) {
				$wporg_username = substr( $node, 6 );
			}

			if ( str_starts_with( $node, 'gravatar:' ) && '' === $gravatar_hash ) {
				$gravatar_hash = substr( $node, 9 );
			}
		}

		if ( '' === $wporg_username && '' === $gravatar_hash ) {
			return null;
		}

		$wporg_profile    = '' === $wporg_username ? array() : ( $wporg_profiles[ $wporg_username ] ?? array() );
		$gravatar_profile = '' === $gravatar_hash ? array() : ( $gravatar_profiles[ $gravatar_hash ] ?? array() );
		$identifier       = '' === $wporg_username ? 'gravatar-' . substr( $gravatar_hash, 0, 20 ) : $wporg_username;
		$name             = $this->attendee_name_from_profiles( $identifier, $wporg_profile, $gravatar_profile );
		$context          = $this->context_from_identity_nodes( $nodes, $signals );

		return array(
			'name'              => $name,
			'identifier'        => $identifier,
			'wporg_username'    => $wporg_username,
			'wporg_profile_url' => $this->wporg_profile_url( $wporg_username, $signals ),
			'wporg_profile'     => $wporg_profile,
			'gravatar_hash'     => $gravatar_hash,
			'avatar_url'        => $this->resolved_avatar_url( $gravatar_hash, $wporg_profile, $signals ),
			'gravatar_profile'  => $gravatar_profile,
			'website_url'       => $context['website_url'],
			'social_links'      => $context['social_links'],
		);
	}

	/**
	 * Return row context attached to the resolved identity nodes.
	 *
	 * @param array $nodes Identity nodes in the group.
	 * @param array $signals Extracted identity URLs.
	 * @phpstan-param array<string> $nodes
	 * @phpstan-param array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,identity_groups:list<array<string,mixed>>} $signals
	 * @return array{website_url:string,social_links:list<string>}
	 */
	private function context_from_identity_nodes( array $nodes, array $signals ): array {
		$node_lookup  = array_fill_keys( $nodes, true );
		$website_urls = array();
		$social_links = array();

		foreach ( $signals['identity_groups'] as $identity_group ) {
			if ( ! $this->identity_group_matches_nodes( $identity_group, $node_lookup ) ) {
				continue;
			}

			foreach ( $identity_group['website_urls'] ?? array() as $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$website_urls[ $url ] = $url;
				}
			}

			foreach ( $identity_group['twitter_urls'] ?? array() as $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$social_links[ $url ] = $url;
				}
			}
		}

		return array(
			'website_url'  => reset( $website_urls ) ?: '',
			'social_links' => array_values( $social_links ),
		);
	}

	/**
	 * Determine whether an identity group belongs to a resolved attendee.
	 *
	 * @param array<string,mixed> $identity_group Extracted identity group.
	 * @param array<string,bool>  $node_lookup Resolved identity nodes.
	 */
	private function identity_group_matches_nodes( array $identity_group, array $node_lookup ): bool {
		foreach ( $this->identity_nodes_from_group( $identity_group ) as $node ) {
			if ( isset( $node_lookup[ $node ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Choose a display name from enriched profile payloads.
	 *
	 * @param string              $fallback Fallback identifier.
	 * @param array<string,mixed> $wporg_profile WordPress.org profile payload.
	 * @param array<string,mixed> $gravatar_profile Gravatar profile payload.
	 */
	private function attendee_name_from_profiles(
		string $fallback,
		array $wporg_profile,
		array $gravatar_profile
	): string {
		if ( ! empty( $wporg_profile['name'] ) && is_string( $wporg_profile['name'] ) ) {
			return sanitize_text_field( $wporg_profile['name'] );
		}

		if ( ! empty( $gravatar_profile['display_name'] ) && is_string( $gravatar_profile['display_name'] ) ) {
			return sanitize_text_field( $gravatar_profile['display_name'] );
		}

		return $fallback;
	}

	/**
	 * Return a normalized WordPress.org profile URL.
	 *
	 * @param string $username WordPress.org username.
	 * @param array  $signals Extracted identity URLs.
	 * @phpstan-param array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,identity_groups:list<array<string,mixed>>} $signals
	 */
	private function wporg_profile_url( string $username, array $signals ): string {
		if ( '' === $username ) {
			return '';
		}

		return $signals['wporg_usernames'][ $username ] ?? 'https://profiles.wordpress.org/' . $username . '/';
	}

	/**
	 * Return the best avatar URL for one resolved attendee.
	 *
	 * @param string $gravatar_hash Gravatar hash.
	 * @param array  $wporg_profile WordPress.org profile payload.
	 * @param array  $signals Extracted identity URLs.
	 * @phpstan-param array<string,mixed> $wporg_profile
	 * @phpstan-param array{wporg_usernames:array<string,string>,gravatar_hashes:array<string,string>,identity_groups:list<array<string,mixed>>} $signals
	 */
	private function resolved_avatar_url( string $gravatar_hash, array $wporg_profile, array $signals ): string {
		if ( '' !== $gravatar_hash ) {
			return $signals['gravatar_hashes'][ $gravatar_hash ] ?? '';
		}

		return $this->avatar_url_from_wporg_profile( $wporg_profile );
	}

	/**
	 * Return a profile avatar URL from a WordPress.org profile payload.
	 *
	 * @param array<string,mixed> $profile WordPress.org profile payload.
	 */
	private function avatar_url_from_wporg_profile( array $profile ): string {
		if ( empty( $profile['avatar_urls'] ) || ! is_array( $profile['avatar_urls'] ) ) {
			return '';
		}

		foreach ( $profile['avatar_urls'] as $url ) {
			if ( is_string( $url ) ) {
				return esc_url_raw( set_url_scheme( $url, 'https' ) );
			}
		}

		return '';
	}

	/**
	 * Find an existing attendee by imported identity.
	 *
	 * @param array<string,string> $attendee Extracted attendee data.
	 */
	private function find_existing_attendee( array $attendee ): int {
		$user = get_user_by( 'login', $attendee['identifier'] );
		if ( false !== $user ) {
			return (int) $user->ID;
		}

		$users = get_users(
			array(
				'fields' => 'ID',
				'number' => -1,
			)
		);

		foreach ( $users as $user_id ) {
			if (
				'' !== $attendee['wporg_username'] &&
				get_user_meta( (int) $user_id, 'wpcamp_wporg_username', true ) === $attendee['wporg_username']
			) {
				return (int) $user_id;
			}

			if (
				'' !== $attendee['gravatar_hash'] &&
				get_user_meta( (int) $user_id, 'wpcamp_gravatar_hash', true ) === $attendee['gravatar_hash']
			) {
				return (int) $user_id;
			}
		}

		return 0;
	}

	/**
	 * Return configured attendees pages keyed by event ID.
	 *
	 * @return array<int,string>
	 */
	private function get_event_attendee_pages(): array {
		$event_ids = get_posts(
			array(
				'post_type'      => Data_Structure::POST_TYPE_EVENT,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		$pages = array();
		foreach ( $event_ids as $event_id ) {
			$url = get_post_meta( (int) $event_id, 'wpcamp_attendees_url', true );
			if ( is_string( $url ) && '' !== esc_url_raw( $url ) ) {
				$pages[ (int) $event_id ] = esc_url_raw( $url );
			}
		}

		return $pages;
	}
}
