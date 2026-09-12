<?php
/**
 * WCP_Addresses: WooCommerce-driven field sets, validation, allowlisting.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group unit
 * @covers WCP_Addresses
 */
class Addresses_Test extends WCP_Test_Case {

	/**
	 * A complete, valid US billing address.
	 *
	 * @return array
	 */
	private function valid_billing() {
		return array(
			'billing_first_name' => 'Jordan',
			'billing_last_name'  => 'Rivera',
			'billing_country'    => 'US',
			'billing_address_1'  => '412 Kingsway Terrace',
			'billing_city'       => 'Austin',
			'billing_state'      => 'TX',
			'billing_postcode'   => '78701',
			'billing_email'      => 'jordan@example.org',
			'billing_phone'      => '+1 512 555 0142',
		);
	}

	public function test_type_is_whitelisted() {
		$this->assertSame( 'billing', WCP_Addresses::sanitize_type( 'billing' ) );
		$this->assertSame( 'shipping', WCP_Addresses::sanitize_type( 'SHIPPING' ) );
		$this->assertSame( '', WCP_Addresses::sanitize_type( 'admin' ) );
		$this->assertSame( '', WCP_Addresses::sanitize_type( array( 'billing' ) ) );
		// `sanitize_key()` strips the dots and slashes, leaving a known type; the
		// whitelist still holds because the result is a real, allowed value.
		$this->assertSame( 'billing', WCP_Addresses::sanitize_type( '../billing' ) );
		$this->assertSame( '', WCP_Addresses::sanitize_type( 'billing2' ) );
	}

	public function test_fields_come_from_woocommerce_and_change_with_country() {
		$this->login( $this->create_customer() );

		$us = WCP_Addresses::get_fields( 'billing', 'US' );
		$gb = WCP_Addresses::get_fields( 'billing', 'GB' );

		$this->assertSame( 'select', $us['billing_state']['type'], 'US has registered states.' );
		$this->assertArrayHasKey( 'TX', $us['billing_state']['options'] );
		$this->assertSame( 'text', $gb['billing_state']['type'], 'GB has none.' );
		$this->assertNotSame( $us['billing_postcode']['label'], $gb['billing_postcode']['label'], 'ZIP Code vs Postcode.' );
		$this->assertTrue( $us['billing_first_name']['half'] );
		$this->assertSame( 'select', $us['billing_country']['type'] );
	}

	public function test_unauthenticated_update_is_refused() {
		$result = WCP_Addresses::update( 'billing', $this->valid_billing() );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_valid_address_persists_and_normalises() {
		$id = $this->create_customer();
		$this->login( $id );

		$input                     = $this->valid_billing();
		$input['billing_postcode'] = ' 78701 ';

		$result = WCP_Addresses::update( 'billing', $input );

		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );

		$customer = new WC_Customer( $id );

		$this->assertSame( 'Austin', $customer->get_billing_city() );
		$this->assertSame( 'TX', $customer->get_billing_state() );
		$this->assertSame( '78701', $customer->get_billing_postcode() );
		$this->assertTrue( WCP_Addresses::has_address( 'billing' ) );
		$this->assertFalse( WCP_Addresses::has_address( 'shipping' ), 'Billing save never touches shipping.' );
	}

	public function test_uk_postcode_is_normalised_to_convention() {
		$this->login( $this->create_customer() );

		$result = WCP_Addresses::update(
			'shipping',
			array(
				'shipping_first_name' => 'J',
				'shipping_last_name'  => 'R',
				'shipping_country'    => 'GB',
				'shipping_address_1'  => '7 Marlborough Hill',
				'shipping_city'       => 'Bristol',
				'shipping_postcode'   => 'bs1 4tr',
				'shipping_phone'      => '+44 117 496 0182',
			)
		);

		$this->assertIsArray( $result, is_wp_error( $result ) ? wp_json_encode( $result->get_error_data() ) : '' );
		$this->assertSame( 'BS1 4TR', $result['shipping_postcode'] );
	}

	public function test_required_fields_are_reported_per_field() {
		$this->login( $this->create_customer() );

		$result = WCP_Addresses::update( 'billing', array( 'billing_country' => 'US' ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 400, $result->get_error_data()['status'] );

		$fields = $result->get_error_data()['fields'];

		foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode', 'billing_email' ) as $key ) {
			$this->assertArrayHasKey( $key, $fields, "$key should be required for US" );
		}

		$this->assertArrayNotHasKey( 'billing_address_2', $fields, 'Optional fields are not flagged.' );
	}

	public function test_postcode_is_validated_against_country() {
		$this->login( $this->create_customer() );

		$input                     = $this->valid_billing();
		$input['billing_postcode'] = 'NOT-A-ZIP';

		$result = WCP_Addresses::update( 'billing', $input );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertArrayHasKey( 'billing_postcode', $result->get_error_data()['fields'] );
	}

	public function test_state_must_be_one_of_the_countrys_states() {
		$this->login( $this->create_customer() );

		$input                  = $this->valid_billing();
		$input['billing_state'] = 'ZZ';

		$result = WCP_Addresses::update( 'billing', $input );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertArrayHasKey( 'billing_state', $result->get_error_data()['fields'] );
	}

	public function test_country_outside_the_stores_list_is_rejected() {
		$this->login( $this->create_customer() );

		$this->assertSame( '', WCP_Addresses::sanitize_country( 'XX', 'billing' ) );
		$this->assertSame( 'US', WCP_Addresses::sanitize_country( 'us', 'billing' ) );
		$this->assertSame( '', WCP_Addresses::sanitize_country( '<script>', 'billing' ) );
	}

	public function test_email_and_phone_are_validated() {
		$this->login( $this->create_customer() );

		$input                  = $this->valid_billing();
		$input['billing_email'] = 'not-an-email';
		$input['billing_phone'] = 'call me maybe';

		$result = WCP_Addresses::update( 'billing', $input );

		$this->assertInstanceOf( 'WP_Error', $result );
		$fields = $result->get_error_data()['fields'];
		$this->assertArrayHasKey( 'billing_email', $fields );
		$this->assertArrayHasKey( 'billing_phone', $fields );
	}

	public function test_keys_outside_the_field_set_are_ignored() {
		$id = $this->create_customer();
		$this->login( $id );

		$input                    = $this->valid_billing();
		$input['shipping_city']   = 'PWNED';
		$input['billing_evil']    = 'x';
		$input['user_id']         = 1;
		$input['wp_capabilities'] = array( 'administrator' => true );

		$result = WCP_Addresses::update( 'billing', $input );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'shipping_city', $result );
		$this->assertArrayNotHasKey( 'billing_evil', $result );

		$customer = new WC_Customer( $id );

		$this->assertSame( '', $customer->get_shipping_city(), 'Billing route cannot write shipping.' );
		$this->assertSame( '', (string) $customer->get_meta( 'billing_evil' ) );
		$this->assertFalse( user_can( $id, 'manage_options' ) );
	}

	public function test_non_scalar_values_become_empty() {
		$this->login( $this->create_customer() );

		$input                 = $this->valid_billing();
		$input['billing_city'] = array( 'Austin' );

		$result = WCP_Addresses::update( 'billing', $input );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertArrayHasKey( 'billing_city', $result->get_error_data()['fields'] );
	}

	public function test_form_values_default_country_without_marking_the_address_set() {
		$this->login( $this->create_customer() );

		$form = WCP_Addresses::get_form_values( 'billing' );

		$this->assertNotSame( '', $form['billing_country'], 'Form gets a default country so the field set is coherent.' );
		$this->assertFalse( WCP_Addresses::has_address( 'billing' ), 'But the address is still considered unset.' );
	}

	public function test_formatted_address_uses_woocommerce_locale_format() {
		$this->login( $this->create_customer() );
		WCP_Addresses::update( 'billing', $this->valid_billing() );

		$formatted = WCP_Addresses::get_formatted( 'billing' );

		$this->assertStringContainsString( 'Austin, TX 78701', wp_strip_all_tags( $formatted ), 'US format: city, state zip on one line.' );
		$this->assertStringNotContainsString( 'jordan@example.org', $formatted, 'Email is not part of the printed address.' );
	}
}
