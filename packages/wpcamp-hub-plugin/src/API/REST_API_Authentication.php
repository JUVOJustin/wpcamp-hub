<?php
/**
 * REST API authentication policy.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\API;

/**
 * Requires authentication for WordPress core REST API routes.
 */
class REST_API_Authentication {

	/**
	 * Require logged-in access for core WordPress REST API requests.
	 *
	 * @param mixed $access Existing REST authentication result.
	 * @return mixed Existing access result or a WP_Error denial.
	 */
	public function require_authentication_for_all_rest_api_requests( mixed $access ): mixed {
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$current_url = wp_parse_url( add_query_arg( array() ) );

		if (
			isset( $current_url['path'] ) &&
			str_contains( $current_url['path'], 'wp-json/wp-abilities/' )
		) {
			return $access;
		}

		if (
			! isset( $current_url['path'] ) ||
			! str_contains( $current_url['path'], 'wp-json/wp/' )
		) {
			return $access;
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_cannot_access',
				__( 'Authentication is required for all REST API requests.', 'wpcamp-hub' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $access;
	}
}
