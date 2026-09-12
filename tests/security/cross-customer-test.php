<?php
/**
 * SECURITY GATE: customer isolation.
 *
 * Customer A, authenticated, attempts to reach or change Customer B's data by
 * every path the plugin exposes. A failure here is a security regression and
 * must fail the build.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group security
 */
class Cross_Customer_Test extends WCP_REST_Test_Case {

	/**
	 * @var int
	 */
	private $a;

	/**
	 * @var int
	 */
	private $b;

	/**
	 * @var WC_Order
	 */
	private $b_order;

	public function set_up() {
		parent::set_up();

		$this->a = $this->create_customer(
			array(
				'first_name' => 'Alpha',
				'user_email' => 'alpha@example.org',
			)
		);
		$this->b = $this->create_customer(
			array(
				'first_name' => 'Bravo',
				'user_email' => 'bravo@example.org',
				'user_pass'  => 'Bravo-Pass-1!',
			)
		);

		$this->b_order = $this->create_order( $this->b );

		$bc = new WC_Customer( $this->b );
		$bc->set_billing_city( 'BravoBillingCity' );
		$bc->set_shipping_city( 'BravoShippingCity' );
		$bc->set_billing_country( 'US' );
		$bc->set_shipping_country( 'US' );
		$bc->save();

		$this->login( $this->a );
	}

	/**
	 * Every field of B's that must never appear in a response to A.
	 *
	 * @return string[]
	 */
	private function b_markers() {
		return array( 'Bravo', 'bravo@example.org', 'BravoBillingCity', 'BravoShippingCity', 'Owner' . $this->b );
	}

	/**
	 * Assert nothing of B's appears in a string.
	 *
	 * @param string $haystack Rendered output or JSON.
	 * @param string $context  Assertion context.
	 */
	private function assertNoBData( $haystack, $context ) {
		foreach ( $this->b_markers() as $marker ) {
			$this->assertStringNotContainsString( $marker, $haystack, "$context leaked '$marker'" );
		}
	}

	/* --- Orders ----------------------------------------------------------- */

	public function test_a_cannot_read_b_order_via_repository() {
		$result = WCP_Orders::get_order( $this->b_order->get_id() );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 404, $result->get_error_data()['status'], 'Not-owned is indistinguishable from not-found.' );
		$this->assertSame( 'wcp_order_not_found', $result->get_error_code() );
	}

	public function test_a_cannot_read_b_order_via_rest() {
		$response = $this->request( 'GET', '/orders/' . $this->b_order->get_id() );

		$this->assertRestError( $response, 404, 'wcp_order_not_found' );
		$this->assertNoBData( wp_json_encode( $response->get_data() ), 'REST order detail' );
	}

	public function test_not_owned_and_not_found_are_byte_identical() {
		$not_owned = $this->request( 'GET', '/orders/' . $this->b_order->get_id() )->get_data();
		$not_found = $this->request( 'GET', '/orders/999999' )->get_data();

		$this->assertSame( $not_found, $not_owned, 'No way to tell a real ID from a fake one.' );
	}

	public function test_a_orders_collection_never_contains_b_order() {
		$this->create_order( $this->a );

		$response = $this->request( 'GET', '/orders', array( 'per_page' => 50 ) );
		$ids      = wp_list_pluck( $response->get_data()['orders'], 'id' );

		$this->assertNotContains( $this->b_order->get_id(), $ids );
		$this->assertSame( 1, $response->get_data()['pagination']['total'] );
	}

	public function test_query_filter_cannot_widen_customer_scope() {
		add_filter(
			'wcp_orders_query_args',
			function ( $args ) {
				$args['customer_id'] = 0;      // "All customers".
				unset( $args['customer_id'] );  // Or none at all.
				$args['customer'] = '';
				return $args;
			}
		);

		$result = WCP_Orders::get_orders( array( 'per_page' => 50 ) );

		remove_all_filters( 'wcp_orders_query_args' );

		$this->assertNotContains( $this->b_order->get_id(), wp_list_pluck( $result['orders'], 'id' ), 'customer_id is applied after the filter.' );
	}

	public function test_a_cannot_read_b_order_via_the_portal_url() {
		$this->set_query( WCP_Navigation::QUERY_VAR, 'orders' );
		$this->set_query( WCP_Navigation::ORDER_VAR, (string) $this->b_order->get_id() );

		$html = $this->render_portal();

		$this->set_query( WCP_Navigation::QUERY_VAR, null );
		$this->set_query( WCP_Navigation::ORDER_VAR, null );

		$this->assertStringContainsString( 'Order unavailable', $html );
		$this->assertNoBData( $html, 'Portal order page' );
	}

	/* --- Dashboard -------------------------------------------------------- */

	public function test_a_dashboard_metrics_are_a_only() {
		$summary = WCP_Dashboard::get_summary();

		$this->assertFalse( $summary['has_orders'], 'A has no orders; B has one.' );
		$this->assertSame( 0, $summary['order_count'] );

		$this->create_order( $this->a );

		$summary = WCP_Dashboard::get_summary();

		$this->assertSame( 1, $summary['order_count'] );
		$this->assertNotContains( $this->b_order->get_id(), wp_list_pluck( $summary['recent'], 'id' ) );
		$this->assertNoBData( wp_json_encode( $summary ), 'Dashboard summary' );
	}

	public function test_lifetime_value_is_per_customer() {
		$this->create_order(
			$this->a,
			array(
				'status' => 'completed',
				'items'  => array( array( $this->create_product( array( 'price' => '10.00' ) ), 1 ) ),
			)
		);

		$a_total = wc_get_customer_total_spent( $this->a );
		$b_total = wc_get_customer_total_spent( $this->b );

		$this->assertNotEquals( $a_total, $b_total );
		$this->assertStringContainsString( '10', WCP_Dashboard::get_summary()['total_spent'] );
	}

	/* --- Profile ---------------------------------------------------------- */

	public function test_a_cannot_read_b_profile() {
		$response = $this->request( 'GET', '/profile' );

		$this->assertSame( 'Alpha', $response->get_data()['values']['first_name'] );
		$this->assertNoBData( wp_json_encode( $response->get_data() ), 'Profile GET' );
	}

	public function test_a_cannot_modify_b_profile_by_naming_b() {
		$payload = array(
			'first_name'  => 'PWNED',
			'ID'          => $this->b,
			'user_id'     => $this->b,
			'customer_id' => $this->b,
			'user_email'  => 'alpha@example.org',
		);

		$response = $this->request( 'POST', '/profile', $payload );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'PWNED', get_userdata( $this->a )->first_name, 'The write landed on A.' );
		$this->assertSame( 'Bravo', get_userdata( $this->b )->first_name, 'B is untouched.' );
		$this->assertSame( 'bravo@example.org', get_userdata( $this->b )->user_email );
		$this->assertTrue( wp_check_password( 'Bravo-Pass-1!', get_userdata( $this->b )->user_pass, $this->b ) );
	}

	public function test_a_cannot_take_b_email_address() {
		$response = $this->request( 'POST', '/profile', array( 'user_email' => 'bravo@example.org' ) );

		$this->assertRestError( $response, 400 );
		$this->assertSame( 'alpha@example.org', get_userdata( $this->a )->user_email );
		$this->assertSame( 'bravo@example.org', get_userdata( $this->b )->user_email );
	}

	/* --- Addresses -------------------------------------------------------- */

	public function test_a_cannot_read_b_addresses() {
		$response = $this->request( 'GET', '/addresses' );

		$this->assertNoBData( wp_json_encode( $response->get_data() ), 'Addresses GET' );
		$this->assertFalse( $response->get_data()['billing']['isset'] );
	}

	/**
	 * @dataProvider address_types
	 */
	public function test_a_cannot_modify_b_address( $type ) {
		$payload = array(
			$type . '_first_name' => 'J',
			$type . '_last_name'  => 'R',
			$type . '_country'    => 'US',
			$type . '_address_1'  => '1 Main St',
			$type . '_city'       => 'PWNED',
			$type . '_state'      => 'TX',
			$type . '_postcode'   => '78701',
			$type . '_email'      => 'alpha@example.org',
			$type . '_phone'      => '+1 512 555 0142',
			'user_id'             => $this->b,
			'customer_id'         => $this->b,
			'ID'                  => $this->b,
		);

		$response = $this->request( 'POST', '/addresses/' . $type, $payload );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );

		$a = new WC_Customer( $this->a );
		$b = new WC_Customer( $this->b );

		$getter = 'get_' . $type . '_city';

		$this->assertSame( 'PWNED', $a->{$getter}(), 'Landed on A.' );
		$this->assertSame( 'billing' === $type ? 'BravoBillingCity' : 'BravoShippingCity', $b->{$getter}(), 'B untouched.' );
	}

	public function address_types() {
		return array( array( 'billing' ), array( 'shipping' ) );
	}

	public function test_billing_route_cannot_write_shipping_and_vice_versa() {
		$this->request(
			'POST',
			'/addresses/billing',
			array(
				'shipping_city' => 'PWNED',
				'billing_city'  => 'X',
			)
		);
		$this->request(
			'POST',
			'/addresses/shipping',
			array(
				'billing_city'  => 'PWNED',
				'shipping_city' => 'X',
			)
		);

		$a = new WC_Customer( $this->a );

		$this->assertNotSame( 'PWNED', $a->get_shipping_city() );
		$this->assertNotSame( 'PWNED', $a->get_billing_city() );
	}

	/* --- Guest orders ----------------------------------------------------- */

	public function test_guest_orders_are_unreadable_by_everyone() {
		$guest_order = $this->create_order( 0 );

		$this->assertInstanceOf( 'WP_Error', WCP_Orders::get_order( $guest_order->get_id() ) );
		$this->assertRestError( $this->request( 'GET', '/orders/' . $guest_order->get_id() ), 404 );
	}

	/* --- Privilege escalation --------------------------------------------- */

	public function test_no_write_can_change_role_or_capabilities() {
		$this->request(
			'POST',
			'/profile',
			array(
				'first_name'      => 'A',
				'role'            => 'administrator',
				'wp_capabilities' => array( 'administrator' => true ),
				'user_login'      => 'admin',
			)
		);

		$user = get_userdata( $this->a );

		$this->assertSame( array( 'customer' ), $user->roles );
		$this->assertFalse( user_can( $this->a, 'manage_options' ) );
		$this->assertFalse( user_can( $this->a, WCP_Settings::CAPABILITY ) );
	}
}
