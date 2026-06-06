<?php
/**
 * Tests for REST API authentication policy.
 *
 * @package WPCAMP_HUB
 */

use WPCAMP_HUB\API\REST_API_Authentication;

/**
 * Verifies core REST API routes require a logged-in user.
 */
class RESTAPIAuthenticationTest extends WP_UnitTestCase {

	/**
	 * Original request URI value.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Preserve request globals for isolated route tests.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->request_uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$this->set_request_uri( '/' );
		wp_set_current_user( 0 );
	}

	/**
	 * Restore request globals.
	 */
	public function tear_down(): void {
		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}

		unset( $_GET['rest_route'] );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Existing REST auth errors are preserved.
	 */
	public function test_existing_rest_authentication_error_is_preserved(): void {
		$error  = new WP_Error( 'existing_error', 'Existing error.' );
		$access = ( new REST_API_Authentication() )->require_authentication_for_all_rest_api_requests( $error );

		$this->assertSame( $error, $access );
	}

	/**
	 * Anonymous users cannot access core WordPress REST routes.
	 */
	public function test_anonymous_core_rest_api_request_requires_authentication(): void {
		$this->set_request_uri( '/wp-json/wp/v2/posts' );

		$access = ( new REST_API_Authentication() )->require_authentication_for_all_rest_api_requests( null );

		$this->assertWPError( $access );
		$this->assertSame( 'rest_cannot_access', $access->get_error_code() );
		$this->assertSame( rest_authorization_required_code(), $access->get_error_data()['status'] );
	}

	/**
	 * Logged-in users can continue through normal REST permission checks.
	 */
	public function test_logged_in_core_rest_api_request_keeps_existing_access(): void {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$this->set_request_uri( '/wp-json/wp/v2/posts' );

		$access = ( new REST_API_Authentication() )->require_authentication_for_all_rest_api_requests( null );

		$this->assertNull( $access );
	}

	/**
	 * Non-core REST namespaces are left to their own permission callbacks.
	 */
	public function test_non_core_rest_api_request_keeps_existing_access(): void {
		$this->set_request_uri( '/wp-json/wpcamp-hub/v1/events' );

		$access = ( new REST_API_Authentication() )->require_authentication_for_all_rest_api_requests( null );

		$this->assertNull( $access );
	}

	/**
	 * Abilities REST routes keep their own permission handling.
	 */
	public function test_abilities_rest_api_request_keeps_existing_access(): void {
		$this->set_request_uri( '/wp-json/wp-abilities/v1/example/ability/run' );

		$access = ( new REST_API_Authentication() )->require_authentication_for_all_rest_api_requests( null );

		$this->assertNull( $access );
	}

	/**
	 * Set the current request URI used by add_query_arg().
	 *
	 * @param string $request_uri Request URI to expose to the authentication policy.
	 */
	private function set_request_uri( string $request_uri ): void {
		$_SERVER['REQUEST_URI'] = $request_uri;
		unset( $_GET['rest_route'] );
	}
}
