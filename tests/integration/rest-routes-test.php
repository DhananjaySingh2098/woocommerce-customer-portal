<?php
/**
 * Every REST route: successful and rejected requests.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group integration
 */
class REST_Routes_Test extends WCP_REST_Test_Case {

	/* --- Orders ----------------------------------------------------------- */

	public function test_orders_collection_returns_own_orders_with_pagination() {
		$id = $this->create_customer();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_order( $id );
		}

		$this->login( $id );

		$response = $this->request( 'GET', '/orders', array( 'per_page' => 2 ) );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertCount( 2, $data['orders'] );
		$this->assertSame( 3, $data['pagination']['total'] );
		$this->assertSame( 2, $data['pagination']['total_pages'] );
		$this->assertTrue( $data['pagination']['has_next'] );
		$this->assertSame( '3', $response->get_headers()['X-WP-Total'] );

		$order = $data['orders'][0];

		foreach ( array( 'id', 'number', 'date', 'status', 'item_count', 'total', 'currency' ) as $key ) {
			$this->assertArrayHasKey( $key, $order );
		}

		$this->assertArrayNotHasKey( 'total_html', $order, 'Display markup is not part of the API shape.' );
		$this->assertStringNotContainsString( '<', $order['total'] );
	}

	public function test_orders_collection_rejects_out_of_range_arguments() {
		$this->login( $this->create_customer() );

		$this->assertRestError( $this->request( 'GET', '/orders', array( 'per_page' => 9999 ) ), 400 );
		$this->assertRestError( $this->request( 'GET', '/orders', array( 'page' => 0 ) ), 400 );
		$this->assertRestError( $this->request( 'GET', '/orders', array( 'page' => 'abc' ) ), 400 );
	}

	public function test_single_order_success_and_shape() {
		$id    = $this->create_customer();
		$order = $this->create_order( $id, array( 'status' => 'completed' ) );
		$this->login( $id );

		$response = $this->request( 'GET', '/orders/' . $order->get_id() );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( $order->get_id(), $data['id'] );
		$this->assertCount( 3, $data['timeline'] );
		$this->assertCount( 1, $data['items'] );
		$this->assertArrayHasKey( 'billing', $data );
		$this->assertArrayHasKey( 'downloads', $data );
		$this->assertStringNotContainsString( '<br', $data['billing'], 'Address markup is stripped for the API.' );
	}

	public function test_single_order_rejections() {
		$this->login( $this->create_customer() );

		$this->assertRestError( $this->request( 'GET', '/orders/999999' ), 404, 'wcp_order_not_found' );
		$this->assertSame( 404, $this->request( 'GET', '/orders/abc' )->get_status(), 'Non-numeric never matches the route.' );
		$this->assertSame( 404, $this->request( 'GET', '/orders/-5' )->get_status() );
	}

	public function test_orders_routes_require_authentication() {
		$this->assertRestError( $this->request( 'GET', '/orders' ), 401, 'wcp_not_authenticated' );
		$this->assertRestError( $this->request( 'GET', '/orders/1' ), 401, 'wcp_not_authenticated' );
	}

	public function test_orders_routes_refuse_when_the_section_is_disabled() {
		$id    = $this->create_customer();
		$order = $this->create_order( $id );
		$this->login( $id );
		$this->save_settings( array( 'sections' => array( 'dashboard', 'profile' ) ) );

		$this->assertRestError( $this->request( 'GET', '/orders' ), 403, 'wcp_section_disabled' );
		$this->assertRestError( $this->request( 'GET', '/orders/' . $order->get_id() ), 403, 'wcp_section_disabled' );
	}

	/* --- Profile ---------------------------------------------------------- */

	public function test_profile_get_and_post() {
		$id = $this->create_customer( array( 'first_name' => 'Before' ) );
		$this->login( $id );

		$get = $this->request( 'GET', '/profile' );

		$this->assertSame( 200, $get->get_status() );
		$this->assertSame( 'Before', $get->get_data()['values']['first_name'] );
		$this->assertArrayNotHasKey( 'user_pass', $get->get_data()['values'] );

		$post = $this->request( 'POST', '/profile', array( 'first_name' => 'After' ) );

		$this->assertSame( 200, $post->get_status() );
		$this->assertTrue( $post->get_data()['saved'] );
		$this->assertSame( 'After', get_userdata( $id )->first_name );
	}

	public function test_profile_post_validation_errors_carry_a_field_map() {
		$this->login( $this->create_customer() );

		$response = $this->request( 'POST', '/profile', array( 'user_email' => 'nope' ) );

		$this->assertRestError( $response, 400, 'wcp_invalid_profile' );
		$this->assertArrayHasKey( 'user_email', $response->get_data()['data']['fields'] );
	}

	public function test_profile_write_requires_the_portal_nonce() {
		$this->login( $this->create_customer() );

		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => false ) ), 403, 'wcp_invalid_nonce' );
		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => 'forged' ) ), 403, 'wcp_invalid_nonce' );
	}

	public function test_profile_routes_require_authentication() {
		$this->assertRestError( $this->request( 'GET', '/profile' ), 401 );
		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ) ), 401 );
	}

	public function test_profile_routes_refuse_when_the_section_is_disabled() {
		$this->login( $this->create_customer() );
		$this->save_settings( array( 'sections' => array( 'dashboard', 'orders' ) ) );

		$this->assertRestError( $this->request( 'GET', '/profile' ), 403, 'wcp_section_disabled' );
		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ) ), 403, 'wcp_section_disabled' );
	}

	/* --- Addresses -------------------------------------------------------- */

	public function test_addresses_collection_and_single_with_country_switch() {
		$this->login( $this->create_customer() );

		$all = $this->request( 'GET', '/addresses' );

		$this->assertSame( 200, $all->get_status() );
		$this->assertArrayHasKey( 'billing', $all->get_data() );
		$this->assertArrayHasKey( 'shipping', $all->get_data() );
		$this->assertFalse( $all->get_data()['billing']['isset'] );

		$us = $this->request( 'GET', '/addresses/billing', array( 'country' => 'US' ) )->get_data();
		$gb = $this->request( 'GET', '/addresses/billing', array( 'country' => 'GB' ) )->get_data();

		$state_us = current(
			array_filter(
				$us['fields'],
				function ( $f ) {
					return 'billing_state' === $f['key'];
				}
			)
		);
		$state_gb = current(
			array_filter(
				$gb['fields'],
				function ( $f ) {
					return 'billing_state' === $f['key'];
				}
			)
		);

		$this->assertSame( 'select', $state_us['type'] );
		$this->assertSame( 'text', $state_gb['type'] );
	}

	public function test_address_post_success_and_formatted_response() {
		$id = $this->create_customer();
		$this->login( $id );

		$response = $this->request(
			'POST',
			'/addresses/billing',
			array(
				'billing_first_name' => 'J',
				'billing_last_name'  => 'R',
				'billing_country'    => 'US',
				'billing_address_1'  => '1 Main St',
				'billing_city'       => 'Austin',
				'billing_state'      => 'TX',
				'billing_postcode'   => '78701',
				'billing_email'      => 'j@example.org',
				'billing_phone'      => '+1 512 555 0142',
			)
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertTrue( $response->get_data()['saved'] );
		$this->assertStringContainsString( 'Austin', $response->get_data()['formatted'] );
		$this->assertSame( 'Austin', ( new WC_Customer( $id ) )->get_billing_city() );
	}

	public function test_address_post_validation_and_type_rejections() {
		$this->login( $this->create_customer() );

		$bad = $this->request(
			'POST',
			'/addresses/billing',
			array(
				'billing_country'  => 'US',
				'billing_postcode' => 'nope',
			)
		);
		$this->assertRestError( $bad, 400, 'wcp_invalid_address' );
		$this->assertArrayHasKey( 'billing_postcode', $bad->get_data()['data']['fields'] );

		$this->assertSame( 404, $this->request( 'POST', '/addresses/admin', array( 'billing_city' => 'X' ) )->get_status(), 'Unknown type never matches a route.' );
		$this->assertSame( 404, $this->request( 'GET', '/addresses/other' )->get_status() );
	}

	public function test_address_write_requires_the_portal_nonce() {
		$this->login( $this->create_customer() );

		$this->assertRestError( $this->request( 'POST', '/addresses/shipping', array( 'shipping_city' => 'X' ), array( 'portal_nonce' => false ) ), 403, 'wcp_invalid_nonce' );
	}

	public function test_address_routes_require_authentication_and_enabled_section() {
		$this->assertRestError( $this->request( 'GET', '/addresses' ), 401 );
		$this->assertRestError( $this->request( 'POST', '/addresses/billing', array( 'billing_city' => 'X' ) ), 401 );

		$this->login( $this->create_customer() );
		$this->save_settings( array( 'sections' => array( 'dashboard' ) ) );

		$this->assertRestError( $this->request( 'GET', '/addresses' ), 403, 'wcp_section_disabled' );
		$this->assertRestError( $this->request( 'POST', '/addresses/billing', array( 'billing_city' => 'X' ) ), 403, 'wcp_section_disabled' );
	}

	/* --- Portal disabled -------------------------------------------------- */

	public function test_every_route_refuses_when_the_portal_is_switched_off() {
		$id = $this->create_customer();
		$this->create_order( $id );
		$this->login( $id );
		$this->save_settings( array( 'enabled' => false ) );

		foreach ( array( array( 'GET', '/orders' ), array( 'GET', '/profile' ), array( 'GET', '/addresses' ) ) as $route ) {
			$this->assertRestError( $this->request( $route[0], $route[1] ), 403, 'wcp_section_disabled' );
		}

		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ) ), 403, 'wcp_section_disabled' );
	}

	/* --- Route registry --------------------------------------------------- */

	public function test_exactly_the_documented_routes_exist() {
		$routes = array_keys( $this->server->get_routes( 'wcp/v1' ) );

		$expected = array(
			'/wcp/v1',
			'/wcp/v1/orders',
			'/wcp/v1/orders/(?P<id>[\d]+)',
			'/wcp/v1/profile',
			'/wcp/v1/addresses',
			'/wcp/v1/addresses/(?P<type>billing|shipping)',
		);

		sort( $routes );
		sort( $expected );

		$this->assertSame( $expected, $routes );
	}

	public function test_no_route_uses_a_permissive_permission_callback() {
		foreach ( $this->server->get_routes( 'wcp/v1' ) as $route => $handlers ) {
			// WordPress registers the namespace index itself; it is not ours.
			if ( '/wcp/v1' === $route ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				if ( ! isset( $handler['callback'] ) ) {
					continue;
				}

				$this->assertArrayHasKey( 'permission_callback', $handler, "$route declares a permission callback" );
				$this->assertNotSame( '__return_true', $handler['permission_callback'], "$route must not be open" );
			}
		}
	}
}
