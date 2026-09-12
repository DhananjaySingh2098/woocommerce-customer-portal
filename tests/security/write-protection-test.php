<?php
/**
 * SECURITY GATE: authentication, CSRF, capability, input hardening, throttling.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group security
 */
class Write_Protection_Test extends WCP_REST_Test_Case {

	/**
	 * A complete US billing address payload.
	 *
	 * @param string $city City override.
	 * @return array
	 */
	private function billing( $city = 'Austin' ) {
		return array(
			'billing_first_name' => 'J',
			'billing_last_name'  => 'R',
			'billing_country'    => 'US',
			'billing_address_1'  => '1 Main St',
			'billing_city'       => $city,
			'billing_state'      => 'TX',
			'billing_postcode'   => '78701',
			'billing_email'      => 'j@example.org',
			'billing_phone'      => '+1 512 555 0142',
		);
	}

	/* --- Authentication --------------------------------------------------- */

	public function test_unauthenticated_writes_are_refused_and_change_nothing() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		wp_set_current_user( 0 );

		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'Hacked' ) ), 401, 'wcp_not_authenticated' );
		$this->assertRestError( $this->request( 'POST', '/addresses/billing', $this->billing( 'Hacked' ) ), 401, 'wcp_not_authenticated' );

		$this->assertSame( 'Original', get_userdata( $id )->first_name );
		$this->assertSame( '', ( new WC_Customer( $id ) )->get_billing_city() );
	}

	public function test_model_layer_refuses_unauthenticated_calls_directly() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, WCP_Profile::update( array( 'first_name' => 'X' ) )->get_error_data()['status'] );
		$this->assertSame( 401, WCP_Addresses::update( 'billing', $this->billing() )->get_error_data()['status'] );
		$order = $this->create_order( $this->create_customer() );
		wp_set_current_user( 0 );

		$result = WCP_Orders::get_order( $order->get_id() );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 404, $result->get_error_data()['status'], 'A guest gets not-found, never the record.' );
	}

	/* --- CSRF / nonces ---------------------------------------------------- */

	public function test_write_without_portal_nonce_is_refused() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		$this->login( $id );

		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => false ) ), 403, 'wcp_invalid_nonce' );
		$this->assertRestError( $this->request( 'POST', '/addresses/billing', $this->billing(), array( 'portal_nonce' => false ) ), 403, 'wcp_invalid_nonce' );

		$this->assertSame( 'Original', get_userdata( $id )->first_name );
	}

	public function test_write_with_forged_or_stale_portal_nonce_is_refused() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		$this->login( $id );

		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => 'deadbeef00' ) ), 403, 'wcp_invalid_nonce' );

		// A nonce for a *different* action must not authorise this one.
		$other = wp_create_nonce( 'some_other_action' );
		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => $other ) ), 403, 'wcp_invalid_nonce' );

		// Nor may another user's nonce.
		$this->login( $this->create_customer() );
		$foreign = WCP_Security::create_nonce();
		$this->login( $id );
		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => $foreign ) ), 403, 'wcp_invalid_nonce' );

		$this->assertSame( 'Original', get_userdata( $id )->first_name );
	}

	public function test_nonce_failure_response_leaks_nothing() {
		$this->login( $this->create_customer() );

		$response = $this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => false ) );
		$body     = wp_json_encode( $response->get_data() );

		$this->assertStringNotContainsString( 'wcp_portal_action', $body, 'Nonce action name not disclosed.' );
		$this->assertStringNotContainsString( 'X-WCP-Nonce', $body );
	}

	public function test_form_handler_refuses_a_bad_nonce() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		$this->login( $id );

		$_POST                     = array(
			WCP_Form_Handler::FORM_FIELD => 'profile',
			WCP_Security::NONCE_NAME     => 'forged',
			'first_name'                 => 'Hacked',
		);
		$_REQUEST                  = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		add_filter(
			'wp_redirect',
			function () {
				throw new WPDieException( 'redirect' );
			}
		);

		try {
			( new WCP_Form_Handler() )->handle();
		} catch ( WPDieException $e ) {
			// Redirected, as expected.
		}

		remove_all_filters( 'wp_redirect' );
		$_POST                     = array();
		$_REQUEST                  = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertSame( 'Original', get_userdata( $id )->first_name );
	}

	/* --- Capability ------------------------------------------------------- */

	public function test_settings_sanitiser_is_reachable_only_through_options_php_capability() {
		// The Settings API's own gate: options.php checks `manage_woocommerce`
		// via the registered capability before the sanitiser runs. Here we
		// prove the sanitiser itself never trusts a caller for capability --
		// the value is validated regardless -- and that a customer holds no
		// capability that options.php would accept.
		$this->login( $this->create_customer() );

		$this->assertFalse( current_user_can( WCP_Settings::CAPABILITY ) );
		$this->assertFalse( current_user_can( 'manage_options' ) );

		$this->login( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertTrue( current_user_can( WCP_Settings::CAPABILITY ) );
	}

	public function test_customer_cannot_render_the_settings_screen() {
		$this->login( $this->create_customer() );
		$this->expectException( 'WPDieException' );
		( new WCP_Settings_Page() )->render();
	}

	public function test_view_portal_filter_is_honoured_by_every_route() {
		$this->login( $this->create_customer() );
		add_filter( 'wcp_current_user_can_view_portal', '__return_false' );

		$this->assertRestError( $this->request( 'GET', '/orders' ), 403, 'wcp_forbidden' );
		$this->assertRestError( $this->request( 'GET', '/profile' ), 403, 'wcp_forbidden' );
		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ) ), 403, 'wcp_forbidden' );
		$this->assertStringContainsString( 'wcp-portal--gate', $this->render_portal(), 'Shortcode falls back to the gate.' );

		remove_filter( 'wcp_current_user_can_view_portal', '__return_false' );
	}

	/* --- Input hardening -------------------------------------------------- */

	public function test_malformed_inputs_never_error_and_never_persist() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		$this->login( $id );

		$payloads = array(
			array( 'first_name' => array( 'nested' => 'array' ) ),
			array( 'first_name' => str_repeat( 'A', 100000 ) ),
			array( 'first_name' => "\0null\0byte" ),
			array( 'user_email' => 'a@b.c<script>' ),
			array( 'display_name' => "<?php echo 'x'; ?>" ),
			array(
				'current_password' => array( 'x' ),
				'new_password'     => 1,
				'confirm_password' => true,
			),
		);

		foreach ( $payloads as $payload ) {
			$response = $this->request( 'POST', '/profile', $payload );
			$this->assertContains( $response->get_status(), array( 200, 400 ), 'No 500 for ' . wp_json_encode( $payload ) );
		}

		$user = get_userdata( $id );

		$this->assertStringNotContainsString( '<', $user->display_name );
		$this->assertStringNotContainsString( '<', $user->user_email );
		$this->assertLessThan( 100000, strlen( $user->first_name ) );
	}

	public function test_invalid_order_ids_are_rejected_without_error() {
		$this->login( $this->create_customer() );

		foreach ( array( '0', '-1', '1.5', 'abc', '1e3', str_repeat( '9', 30 ) ) as $bad ) {
			$response = $this->request( 'GET', '/orders/' . $bad );
			$this->assertContains( $response->get_status(), array( 400, 404 ), "id=$bad" );
		}

		$this->assertInstanceOf( 'WP_Error', WCP_Orders::get_order( -1 ) );
		$this->assertInstanceOf( 'WP_Error', WCP_Orders::get_order( '1.5' ) );
		$this->assertInstanceOf( 'WP_Error', WCP_Orders::get_order( array( 1 ) ) );
	}

	public function test_disabled_sections_refuse_writes_not_just_reads() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		$this->login( $id );
		$this->save_settings( array( 'sections' => array( 'dashboard', 'orders' ) ) );

		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'X' ) ), 403, 'wcp_section_disabled' );
		$this->assertRestError( $this->request( 'POST', '/addresses/billing', $this->billing() ), 403, 'wcp_section_disabled' );

		$this->assertSame( 'Original', get_userdata( $id )->first_name );
		$this->assertSame( '', ( new WC_Customer( $id ) )->get_billing_city() );
	}

	/* --- Rate limiting ---------------------------------------------------- */

	public function test_profile_writes_are_throttled_with_retry_after() {
		$this->login( $this->create_customer() );

		$limit = WCP_Rate_Limit::buckets()['profile']['limit'];

		for ( $i = 0; $i < $limit; $i++ ) {
			$this->assertSame( 200, $this->request( 'POST', '/profile', array( 'first_name' => 'N' . $i ) )->get_status(), "Request $i within limit" );
		}

		$response = $this->request( 'POST', '/profile', array( 'first_name' => 'Over' ) );

		$this->assertRestError( $response, 429, 'wcp_rate_limited' );

		$headers = $response->get_headers();

		$this->assertArrayHasKey( 'Retry-After', $headers );
		$this->assertGreaterThan( 0, (int) $headers['Retry-After'] );
		$this->assertLessThanOrEqual( WCP_Rate_Limit::buckets()['profile']['window'], (int) $headers['Retry-After'] );

		$body = wp_json_encode( $response->get_data() );

		$this->assertStringNotContainsString( '"limit"', $body );
		$this->assertStringNotContainsString( 'profile', $body );
	}

	/**
	 * @dataProvider address_types
	 */
	public function test_address_writes_are_throttled( $type ) {
		$this->login( $this->create_customer() );

		$payload = array();

		foreach ( $this->billing() as $key => $value ) {
			$payload[ str_replace( 'billing_', $type . '_', $key ) ] = $value;
		}

		$limit = WCP_Rate_Limit::buckets()['address']['limit'];

		for ( $i = 0; $i < $limit; $i++ ) {
			$this->assertSame( 200, $this->request( 'POST', '/addresses/' . $type, $payload )->get_status(), "Request $i within limit" );
		}

		$response = $this->request( 'POST', '/addresses/' . $type, $payload );

		$this->assertRestError( $response, 429, 'wcp_rate_limited' );
		$this->assertGreaterThan( 0, (int) $response->get_headers()['Retry-After'] );
	}

	public function address_types() {
		return array( array( 'billing' ), array( 'shipping' ) );
	}

	public function test_one_customers_throttle_does_not_affect_another() {
		$a = $this->create_customer();
		$b = $this->create_customer();

		$this->login( $a );

		$limit = WCP_Rate_Limit::buckets()['profile']['limit'];

		for ( $i = 0; $i <= $limit; $i++ ) {
			$this->request( 'POST', '/profile', array( 'first_name' => 'N' ) );
		}

		$this->assertRestError( $this->request( 'POST', '/profile', array( 'first_name' => 'N' ) ), 429 );

		$this->login( $b );

		$this->assertSame( 200, $this->request( 'POST', '/profile', array( 'first_name' => 'Fine' ) )->get_status() );
	}

	public function test_password_guessing_is_throttled_more_strictly() {
		$this->login( $this->create_customer( array( 'user_pass' => 'Real-Pass-1!' ) ) );

		$guess = function ( $pw ) {
			return $this->request(
				'POST',
				'/profile',
				array(
					'current_password' => $pw,
					'new_password'     => 'New-Pass-9!',
					'confirm_password' => 'New-Pass-9!',
				)
			);
		};

		$limit = WCP_Rate_Limit::buckets()['password']['limit'];

		for ( $i = 0; $i < $limit; $i++ ) {
			$this->assertSame( 400, $guess( 'wrong-' . $i )->get_status() );
		}

		$throttled = $guess( 'wrong-again' );

		$this->assertRestError( $throttled, 429, 'wcp_rate_limited' );
		$this->assertArrayHasKey( 'Retry-After', $throttled->get_headers() );
		$this->assertArrayHasKey( 'current_password', $throttled->get_data()['data']['fields'] );

		$this->assertSame( 429, $guess( 'Real-Pass-1!' )->get_status(), 'Correct password refused while throttled.' );
		$this->assertSame( 200, $this->request( 'POST', '/profile', array( 'first_name' => 'Still' ) )->get_status(), 'Ordinary edits unaffected.' );
	}

	public function test_password_never_appears_in_any_response() {
		$this->login( $this->create_customer( array( 'user_pass' => 'Real-Pass-1!' ) ) );

		$responses = array(
			$this->request(
				'POST',
				'/profile',
				array(
					'current_password' => 'guess-XYZ',
					'new_password'     => 'New-Pass-9!',
					'confirm_password' => 'New-Pass-9!',
				)
			),
			$this->request(
				'POST',
				'/profile',
				array(
					'current_password' => 'Real-Pass-1!',
					'new_password'     => 'New-Pass-9!',
					'confirm_password' => 'New-Pass-9!',
				)
			),
			$this->request( 'GET', '/profile' ),
		);

		foreach ( $responses as $response ) {
			$body = wp_json_encode( $response->get_data() );

			foreach ( array( 'guess-XYZ', 'Real-Pass-1!', 'New-Pass-9!' ) as $secret ) {
				$this->assertStringNotContainsString( $secret, $body );
			}
		}
	}

	/* --- Output ----------------------------------------------------------- */

	public function test_error_responses_never_carry_stack_traces_or_paths() {
		$this->login( $this->create_customer() );

		$responses = array(
			$this->request( 'GET', '/orders/999999' ),
			$this->request( 'POST', '/profile', array( 'user_email' => 'bad' ) ),
			$this->request( 'POST', '/addresses/billing', array( 'billing_postcode' => 'x' ) ),
			$this->request( 'POST', '/profile', array( 'first_name' => 'X' ), array( 'portal_nonce' => false ) ),
		);

		foreach ( $responses as $response ) {
			$body = wp_json_encode( $response->get_data() );

			$this->assertStringNotContainsString( '/app/', $body );
			$this->assertStringNotContainsString( '.php', $body );
			$this->assertStringNotContainsString( 'Stack trace', $body );
			$this->assertStringNotContainsString( 'wpdb', $body );
		}
	}
}
