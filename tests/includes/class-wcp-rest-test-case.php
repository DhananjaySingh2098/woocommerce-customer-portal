<?php
/**
 * Base test case for REST routes.
 *
 * Dispatches requests through the real `WP_REST_Server`, so permission
 * callbacks, argument schemas and error-to-response conversion all run exactly
 * as they would for a browser. Nothing here shortcuts the stack.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * REST helpers.
 */
abstract class WCP_REST_Test_Case extends WCP_Test_Case {

	/**
	 * The REST server.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Boot the REST server with the plugin's routes registered.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear the server down.
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		parent::tear_down();
	}

	/**
	 * Dispatch a request to a `wcp/v1` route.
	 *
	 * @param string $method  HTTP method.
	 * @param string $route   Route path after `/wcp/v1`.
	 * @param array  $params  Body or query parameters.
	 * @param array  $options `nonce` (bool, default true when logged in) and
	 *                        `portal_nonce` (bool, default true for writes).
	 * @return WP_REST_Response
	 */
	protected function request( $method, $route, array $params = array(), array $options = array() ) {
		$request = new WP_REST_Request( $method, '/wcp/v1' . $route );

		$logged_in   = is_user_logged_in();
		$is_write    = in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true );
		$send_rest   = array_key_exists( 'nonce', $options ) ? $options['nonce'] : $logged_in;
		$send_portal = array_key_exists( 'portal_nonce', $options ) ? $options['portal_nonce'] : ( $logged_in && $is_write );

		if ( $send_rest ) {
			$request->set_header( 'X-WP-Nonce', true === $send_rest ? wp_create_nonce( 'wp_rest' ) : (string) $send_rest );
		}

		if ( $send_portal ) {
			$request->set_header( 'X-WCP-Nonce', true === $send_portal ? WCP_Security::create_nonce() : (string) $send_portal );
		}

		if ( $is_write ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		} else {
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Assert a response carries an error with the given status.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param int              $status   Expected status.
	 * @param string           $code     Optional expected error code.
	 * @return void
	 */
	protected function assertRestError( $response, $status, $code = '' ) {
		$this->assertSame( $status, $response->get_status(), 'Unexpected status. Body: ' . wp_json_encode( $response->get_data() ) );

		if ( '' !== $code ) {
			$data = $response->get_data();
			$this->assertSame( $code, isset( $data['code'] ) ? $data['code'] : null );
		}
	}
}
