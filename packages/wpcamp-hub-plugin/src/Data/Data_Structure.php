<?php
/**
 * Central data structure registration for WPCamp Hub.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Data;

/**
 * Registers platform post types, taxonomies, meta fields, and admin input surfaces.
 */
class Data_Structure {

	public const string POST_TYPE_EVENT          = 'wpcamp_event';
	public const string POST_TYPE_TWEET          = 'wpcamp_tweet';
	public const string POST_TYPE_SESSION        = 'wpcamp_session';
	public const string POST_TYPE_MEETING_INVITE = 'wpcamp_meeting';

	public const string TAXONOMY_EVENT_TYPE  = 'wpcamp_event_type';
	public const string TAXONOMY_TWEET_LABEL = 'wpcamp_tweet_label';
	public const string TAXONOMY_TRACK       = 'wpcamp_track';

	public const string TERM_META_COLOR = 'wpcamp_color';

	/**
	 * Register all data structures.
	 */
	public function register(): void {
		$this->register_post_types();
		$this->register_taxonomies();
		$this->register_term_meta();
		$this->register_post_meta();
		$this->register_user_meta();
		( new Relationships() )->register_meta();
	}

	/**
	 * Add Gutenberg-compatible meta boxes for post meta fields.
	 */
	public function register_meta_boxes(): void {
		foreach ( self::get_post_types() as $post_type => $config ) {
			add_meta_box(
				'wpcamp_hub_' . $post_type . '_details',
				(string) $config['singular'] . ' Details',
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'default',
				array(
					'__block_editor_compatible_meta_box' => true,
					'__back_compat_meta_box'             => true,
				)
			);
		}
	}

	/**
	 * Save meta values posted from the native meta boxes.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public function save_post_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( self::get_post_meta_fields()[ $post->post_type ] ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST['wpcamp_hub_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcamp_hub_meta_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpcamp_hub_save_meta' ) ) {
			return;
		}

		foreach ( self::get_post_meta_fields()[ $post->post_type ] as $meta_key => $field ) {
			$input_name = sanitize_key( $this->input_name( $meta_key ) );
			if ( ! isset( $_POST[ $input_name ] ) ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			$raw_value = sanitize_text_field( wp_unslash( $_POST[ $input_name ] ) );
			$value     = $this->sanitize_meta_input( $raw_value, $field );
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Post type configs keyed by post type.
	 *
	 * @return array<string,array{entity_type:string,singular:string,plural:string,menu_icon:string,supports:string[],taxonomies:string[]}>
	 */
	public static function get_post_types(): array {
		return array(
			self::POST_TYPE_EVENT          => array(
				'entity_type' => 'event',
				'singular'    => __( 'Event', 'wpcamp-hub' ),
				'plural'      => __( 'Events', 'wpcamp-hub' ),
				'menu_icon'   => 'dashicons-calendar-alt',
				'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				'taxonomies'  => array( self::TAXONOMY_EVENT_TYPE ),
			),
			self::POST_TYPE_TWEET          => array(
				'entity_type' => 'tweet',
				'singular'    => __( 'Tweet', 'wpcamp-hub' ),
				'plural'      => __( 'Tweets', 'wpcamp-hub' ),
				'menu_icon'   => 'dashicons-format-status',
				'supports'    => array( 'title', 'editor', 'custom-fields' ),
				'taxonomies'  => array( self::TAXONOMY_TWEET_LABEL ),
			),
			self::POST_TYPE_SESSION        => array(
				'entity_type' => 'session',
				'singular'    => __( 'Session', 'wpcamp-hub' ),
				'plural'      => __( 'Sessions', 'wpcamp-hub' ),
				'menu_icon'   => 'dashicons-welcome-learn-more',
				'supports'    => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
				'taxonomies'  => array( self::TAXONOMY_TRACK ),
			),
			self::POST_TYPE_MEETING_INVITE => array(
				'entity_type' => 'meeting_invite',
				'singular'    => __( 'Meeting Invite', 'wpcamp-hub' ),
				'plural'      => __( 'Meeting Invites', 'wpcamp-hub' ),
				'menu_icon'   => 'dashicons-groups',
				'supports'    => array( 'title', 'editor', 'custom-fields' ),
				'taxonomies'  => array(),
			),
		);
	}

	/**
	 * Taxonomy configs keyed by taxonomy.
	 *
	 * @return array<string,array{singular:string,plural:string,object_types:string[],terms:string[]}>
	 */
	public static function get_taxonomies(): array {
		return array(
			self::TAXONOMY_EVENT_TYPE  => array(
				'singular'     => __( 'Event Type', 'wpcamp-hub' ),
				'plural'       => __( 'Event Types', 'wpcamp-hub' ),
				'object_types' => array( self::POST_TYPE_EVENT ),
				'terms'        => array( 'WordCamp', 'Contributor Day', 'Side event', 'Meetup', 'Party', 'Workshop', 'Community event' ),
			),
			self::TAXONOMY_TWEET_LABEL => array(
				'singular'     => __( 'Tweet Label', 'wpcamp-hub' ),
				'plural'       => __( 'Tweet Labels', 'wpcamp-hub' ),
				'object_types' => array( self::POST_TYPE_TWEET ),
				'terms'        => array( 'Wants to meet', 'Going to WCEU', 'Community', 'Looking for help', 'Offering help', 'Speaking', 'Sponsoring', 'Hiring', 'Travel', 'Afterparty' ),
			),
			self::TAXONOMY_TRACK       => array(
				'singular'     => __( 'Track', 'wpcamp-hub' ),
				'plural'       => __( 'Tracks', 'wpcamp-hub' ),
				'object_types' => array( self::POST_TYPE_SESSION ),
				'terms'        => array( 'Developer', 'Community', 'Business', 'Design', 'Accessibility' ),
			),
		);
	}

	/**
	 * Post meta field configs grouped by post type.
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public static function get_post_meta_fields(): array {
		return array(
			self::POST_TYPE_EVENT          => array(
				'wpcamp_date_start'        => self::field( 'string', 'Event start date/time in ISO 8601 format.' ),
				'wpcamp_date_end'          => self::field( 'string', 'Event end date/time in ISO 8601 format.' ),
				'wpcamp_location'          => self::field( 'string', 'Human-readable event location.' ),
				'wpcamp_coordinates'       => self::field(
					'object',
					'Latitude and longitude coordinates.',
					true,
					array(
						'latitude'  => 'number',
						'longitude' => 'number',
					)
				),
				'wpcamp_official_url'      => self::field( 'string', 'Official event URL.', true, null, 'uri' ),
				'wpcamp_source'            => self::field( 'string', 'Event source such as curated, WordCamp, or Twitter/X.' ),
				'wpcamp_related_tweets'    => self::field( 'array', 'Related tweet IDs.' ),
				'wpcamp_related_attendees' => self::field( 'array', 'Related attendee user IDs.' ),
			),
			self::POST_TYPE_TWEET          => array(
				'wpcamp_tweet_id'          => self::field( 'string', 'Source tweet ID.', false ),
				'wpcamp_author_handle'     => self::field( 'string', 'Tweet author handle.', false ),
				'wpcamp_author_name'       => self::field( 'string', 'Tweet author display name.', false ),
				'wpcamp_tweet_url'         => self::field( 'string', 'Canonical tweet URL.', true, null, 'uri' ),
				'wpcamp_timestamp'         => self::field( 'string', 'Tweet timestamp in ISO 8601 format.', false ),
				'wpcamp_related_event'     => self::field( 'integer', 'Related event ID.' ),
				'wpcamp_related_attendee'  => self::field( 'integer', 'Related attendee user ID.' ),
				'wpcamp_processing_status' => self::field( 'string', 'Tweet processing status.' ),
			),
			self::POST_TYPE_SESSION        => array(
				'wpcamp_speakers'          => self::field( 'array', 'Speaker user IDs.' ),
				'wpcamp_event'             => self::field( 'integer', 'Parent event ID.' ),
				'wpcamp_start_time'        => self::field( 'string', 'Session start time in ISO 8601 format.' ),
				'wpcamp_end_time'          => self::field( 'string', 'Session end time in ISO 8601 format.' ),
				'wpcamp_related_event'     => self::field( 'integer', 'Related event ID.' ),
				'wpcamp_official_url'      => self::field( 'string', 'Official session URL.', true, null, 'uri' ),
				'wpcamp_source'            => self::field( 'string', 'Session source.' ),
				'wpcamp_related_attendees' => self::field( 'array', 'Related attendee user IDs.' ),
			),
			self::POST_TYPE_MEETING_INVITE => array(
				'wpcamp_person'        => self::field( 'integer', 'Attendee user ID for this invitation.' ),
				'wpcamp_source_tweet'  => self::field( 'integer', 'Source tweet ID for this invitation.', false ),
				'wpcamp_topic'         => self::field( 'string', 'Meeting topic.' ),
				'wpcamp_related_event' => self::field( 'integer', 'Related event ID.' ),
			),
		);
	}

	/**
	 * User meta field configs.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_user_meta_fields(): array {
		return array(
			'wpcamp_wporg_profile_url' => self::field( 'string', 'WordPress.org profile URL.', false, null, 'uri' ),
			'wpcamp_wporg_username'    => self::field( 'string', 'WordPress.org username or slug.', false ),
			'wpcamp_gravatar_hash'     => self::field( 'string', 'Gravatar hash identifier.', false ),
			'wpcamp_gravatar_profile'  => self::field( 'object', 'Raw Gravatar profile data.', false ),
			'wpcamp_bio'               => self::field( 'string', 'Public attendee biography.' ),
			'wpcamp_avatar'            => self::field( 'string', 'Public avatar URL.', true, null, 'uri' ),
			'wpcamp_social_links'      => self::field( 'array', 'Public social profile URLs.' ),
			'wpcamp_company'           => self::field( 'string', 'Company or organization.' ),
			'wpcamp_community_role'    => self::field( 'string', 'Community role.' ),
			'wpcamp_related_tweets'    => self::field( 'array', 'Related tweet IDs.', false ),
			'wpcamp_related_events'    => self::field( 'array', 'Related event IDs.' ),
			'wpcamp_meeting_available' => self::field( 'boolean', 'Whether the attendee is open to meetings.' ),
		);
	}

	/**
	 * Post meta fields exposed to the block editor.
	 *
	 * @return array<string,array<string,array{type:string,description:string,format:string|null,properties:array<string,string>|null}>>
	 */
	public function get_editor_post_meta_fields(): array {
		$editable_fields = array();

		foreach ( self::get_post_meta_fields() as $post_type => $fields ) {
			foreach ( $fields as $meta_key => $field ) {
				if ( empty( $field['show_in_rest'] ) ) {
					continue;
				}

				$editable_fields[ $post_type ][ $meta_key ] = array(
					'type'        => (string) $field['type'],
					'description' => (string) $field['description'],
					'format'      => is_string( $field['format'] ) ? $field['format'] : null,
					'properties'  => is_array( $field['properties'] ) ? $field['properties'] : null,
				);
			}
		}

		return $editable_fields;
	}

	/**
	 * Convert post type to platform entity type.
	 *
	 * @param string $post_type Registered post type.
	 */
	public static function post_type_to_entity_type( string $post_type ): string {
		$configs = self::get_post_types();
		return isset( $configs[ $post_type ] ) ? (string) $configs[ $post_type ]['entity_type'] : '';
	}

	/**
	 * Convert platform entity type to post type.
	 *
	 * @param string $entity_type Platform entity type.
	 */
	public static function entity_type_to_post_type( string $entity_type ): string {
		foreach ( self::get_post_types() as $post_type => $config ) {
			if ( $entity_type === $config['entity_type'] ) {
				return $post_type;
			}
		}

		return '';
	}

	/**
	 * Render the native meta box fields.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'wpcamp_hub_save_meta', 'wpcamp_hub_meta_nonce' );

		foreach ( self::get_post_meta_fields()[ $post->post_type ] as $meta_key => $field ) {
			$value = get_post_meta( $post->ID, $meta_key, true );
			printf(
				'<p><label for="%1$s"><strong>%2$s</strong></label><br />',
				esc_attr( $this->input_name( $meta_key ) ),
				esc_html( $field['description'] )
			);
			printf(
				'<input class="widefat" type="text" id="%1$s" name="%1$s" value="%2$s" /></p>',
				esc_attr( $this->input_name( $meta_key ) ),
				esc_attr( is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : (string) $value )
			);
		}
	}

	/**
	 * Register platform post types.
	 */
	private function register_post_types(): void {
		foreach ( self::get_post_types() as $post_type => $config ) {
			register_post_type(
				$post_type,
				array(
					'labels'          => array(
						'name'          => $config['plural'],
						'singular_name' => $config['singular'],
						'add_new_item'  => sprintf(
							/* translators: %s: post type singular label. */
							__( 'Add New %s', 'wpcamp-hub' ),
							$config['singular']
						),
						'edit_item'     => sprintf(
							/* translators: %s: post type singular label. */
							__( 'Edit %s', 'wpcamp-hub' ),
							$config['singular']
						),
					),
					'public'          => true,
					'show_in_rest'    => true,
					'has_archive'     => true,
					'menu_icon'       => $config['menu_icon'],
					'supports'        => $config['supports'],
					'taxonomies'      => $config['taxonomies'],
					'rewrite'         => array( 'slug' => str_replace( 'wpcamp_', '', $post_type ) ),
					'capability_type' => 'post',
				)
			);
		}
	}

	/**
	 * Register platform taxonomies.
	 *
	 * Default terms are NOT seeded here — that runs once on activation
	 * (see seed_terms()), so terms the user later renames or deletes are not
	 * recreated on every request.
	 */
	private function register_taxonomies(): void {
		foreach ( self::get_taxonomies() as $taxonomy => $config ) {
			register_taxonomy(
				$taxonomy,
				$config['object_types'],
				array(
					'labels'       => array(
						'name'          => $config['plural'],
						'singular_name' => $config['singular'],
					),
					'public'       => true,
					'hierarchical' => false,
					'show_in_rest' => true,
					'rewrite'      => array( 'slug' => str_replace( 'wpcamp_', '', $taxonomy ) ),
				)
			);
		}
	}

	/**
	 * Seed the default taxonomy terms.
	 *
	 * Idempotent and intended to run on activation only. Existing terms are
	 * left untouched, and terms the user removed are not recreated on normal
	 * requests.
	 */
	public function seed_terms(): void {
		foreach ( self::get_taxonomies() as $taxonomy => $config ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, $config['object_types'] );
			}

			foreach ( $config['terms'] as $term ) {
				if ( ! term_exists( $term, $taxonomy ) ) {
					wp_insert_term( $term, $taxonomy );
				}
			}
		}
	}

	/**
	 * Register taxonomy term meta (track accent colour).
	 */
	private function register_term_meta(): void {
		register_term_meta(
			self::TAXONOMY_TRACK,
			self::TERM_META_COLOR,
			array(
				'type'              => 'string',
				'description'       => __( 'Accent colour for the track (hex).', 'wpcamp-hub' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_hex_color',
				'default'           => '',
			)
		);
	}

	/**
	 * Render the colour field on the "Add Track" form.
	 */
	public function add_track_color_field(): void {
		?>
		<div class="form-field term-wpcamp-color-wrap">
			<label for="wpcamp-color"><?php esc_html_e( 'Accent colour', 'wpcamp-hub' ); ?></label>
			<input type="color" name="<?php echo esc_attr( self::TERM_META_COLOR ); ?>" id="wpcamp-color" value="#3858E9" />
			<p><?php esc_html_e( 'Used for the track legend and session card accents.', 'wpcamp-hub' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the colour field on the "Edit Track" form.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public function edit_track_color_field( \WP_Term $term ): void {
		$color = get_term_meta( $term->term_id, self::TERM_META_COLOR, true );
		$color = is_string( $color ) && '' !== $color ? $color : '#3858E9';
		?>
		<tr class="form-field term-wpcamp-color-wrap">
			<th scope="row">
				<label for="wpcamp-color"><?php esc_html_e( 'Accent colour', 'wpcamp-hub' ); ?></label>
			</th>
			<td>
				<input type="color" name="<?php echo esc_attr( self::TERM_META_COLOR ); ?>" id="wpcamp-color" value="<?php echo esc_attr( $color ); ?>" />
				<p class="description"><?php esc_html_e( 'Used for the track legend and session card accents.', 'wpcamp-hub' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist the track colour when a term is created or edited.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_track_color( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core handles the term-form nonce.
		if ( ! isset( $_POST[ self::TERM_META_COLOR ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$color = sanitize_hex_color( wp_unslash( $_POST[ self::TERM_META_COLOR ] ) );
		if ( null === $color || '' === $color ) {
			delete_term_meta( $term_id, self::TERM_META_COLOR );
			return;
		}
		update_term_meta( $term_id, self::TERM_META_COLOR, $color );
	}

	/**
	 * Register all CPT meta fields.
	 */
	private function register_post_meta(): void {
		foreach ( self::get_post_meta_fields() as $post_type => $fields ) {
			foreach ( $fields as $meta_key => $field ) {
				register_post_meta( $post_type, $meta_key, $this->meta_args( $field ) );
			}
		}
	}

	/**
	 * Register all profile meta fields.
	 */
	private function register_user_meta(): void {
		foreach ( self::get_user_meta_fields() as $meta_key => $field ) {
			register_meta( 'user', $meta_key, $this->meta_args( $field ) );
		}
	}

	/**
	 * Build a field config.
	 *
	 * @param string                    $type JSON schema type.
	 * @param string                    $description Field description.
	 * @param bool                      $show_in_rest Whether the field is exposed through REST.
	 * @param array<string,string>|null $properties Optional object property schema map.
	 * @param string|null               $format Optional JSON schema format.
	 * @return array<string,mixed>
	 */
	private static function field( string $type, string $description, bool $show_in_rest = true, ?array $properties = null, ?string $format = null ): array {
		return array(
			'type'         => $type,
			'description'  => $description,
			'show_in_rest' => $show_in_rest,
			'properties'   => $properties,
			'format'       => $format,
		);
	}

	/**
	 * Convert a field config into register_meta arguments.
	 *
	 * @param array<string,mixed> $field Field config.
	 * @return array<string,mixed>
	 */
	private function meta_args( array $field ): array {
		$schema = array(
			'type'        => $field['type'],
			'description' => $field['description'],
		);

		if ( 'array' === $field['type'] ) {
			$schema['items'] = array( 'type' => 'integer' );
		}

		if ( 'object' === $field['type'] && ! empty( $field['properties'] ) && is_array( $field['properties'] ) ) {
			$schema['properties'] = array();
			foreach ( $field['properties'] as $property => $type ) {
				$schema['properties'][ $property ] = array( 'type' => $type );
			}
		}

		if ( ! empty( $field['format'] ) ) {
			$schema['format'] = $field['format'];
		}

		$show_in_rest = ! empty( $field['show_in_rest'] ) ? array( 'schema' => $schema ) : false;

		return array(
			'type'              => $field['type'],
			'description'       => $field['description'],
			'single'            => true,
			'default'           => $this->default_for_type( (string) $field['type'] ),
			'sanitize_callback' => array( $this, 'sanitize_registered_meta' ),
			'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
			'show_in_rest'      => $show_in_rest,
		);
	}

	/**
	 * Sanitize registered meta based on scalar shape.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $meta_key Meta key.
	 * @param string $object_type Object type.
	 * @return mixed
	 */
	public function sanitize_registered_meta( mixed $value, string $meta_key, string $object_type ): mixed {
		$field = $this->find_field( $meta_key, $object_type );
		return $this->sanitize_meta_input( $value, $field );
	}

	/**
	 * Find a field config by meta key.
	 *
	 * @param string $meta_key Meta key.
	 * @param string $object_type Object type.
	 * @return array<string,mixed>
	 */
	private function find_field( string $meta_key, string $object_type ): array {
		if ( 'user' === $object_type && isset( self::get_user_meta_fields()[ $meta_key ] ) ) {
			return self::get_user_meta_fields()[ $meta_key ];
		}

		foreach ( self::get_post_meta_fields() as $fields ) {
			if ( isset( $fields[ $meta_key ] ) ) {
				return $fields[ $meta_key ];
			}
		}

		return self::field( 'string', 'Unknown meta field.' );
	}

	/**
	 * Sanitize a meta-box or REST meta value.
	 *
	 * @param mixed               $value Raw value.
	 * @param array<string,mixed> $field Field config.
	 * @return mixed
	 */
	private function sanitize_meta_input( mixed $value, array $field ): mixed {
		if ( 'integer' === $field['type'] ) {
			return absint( $value );
		}

		if ( 'boolean' === $field['type'] ) {
			return (bool) $value;
		}

		if ( 'array' === $field['type'] ) {
			$items = is_array( $value ) ? $value : explode( ',', (string) $value );
			return array_values( array_filter( array_map( 'absint', $items ) ) );
		}

		if ( 'object' === $field['type'] ) {
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				return is_array( $decoded ) ? $decoded : array();
			}
			return is_array( $value ) ? $value : array();
		}

		if ( 'uri' === ( $field['format'] ?? null ) ) {
			return esc_url_raw( (string) $value );
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Default meta value for a JSON schema type.
	 *
	 * @param string $type JSON schema type.
	 * @return mixed
	 */
	private function default_for_type( string $type ): mixed {
		if ( 'array' === $type ) {
			return array();
		}

		if ( 'object' === $type ) {
			return array();
		}

		if ( 'integer' === $type ) {
			return 0;
		}

		if ( 'boolean' === $type ) {
			return false;
		}

		return '';
	}

	/**
	 * Convert a meta key to a safe input field name.
	 *
	 * @param string $meta_key Meta key.
	 */
	private function input_name( string $meta_key ): string {
		return 'wpcamp_hub_meta_' . $meta_key;
	}
}
