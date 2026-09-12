<?php
/**
 * The dashboard's real-data modules: setup state and store discovery.
 *
 * These cover the two places the redesign added new data rather than new
 * markup, because both make claims about the customer that have to stay true:
 * the setup percentage is a count of real conditions, and the discovery panel
 * must never surface a product the store has hidden.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group dashboard
 */
class Dashboard_Modules_Test extends WCP_Test_Case {
	/*
	|--------------------------------------------------------------------------
	| Account setup
	|--------------------------------------------------------------------------
	*/

	public function test_setup_counts_only_conditions_the_store_can_check() {
		$id = $this->create_customer();
		$this->login( $id );

		$state = WCP_Dashboard::setup_state( false );

		// Account, profile, billing, shipping, first order.
		$this->assertSame( 5, $state['total'] );

		// The fixture customer has a first and last name, so two of five.
		$this->assertSame( 2, $state['done'] );
		$this->assertSame( 40, $state['percent'] );
		$this->assertSame( 'billing', $state['next'] );
		$this->assertFalse( $state['complete'] );
	}

	public function test_setup_counts_a_missing_name_as_outstanding() {
		$id = $this->create_customer(
			array(
				'first_name' => '',
				'last_name'  => '',
			)
		);
		$this->login( $id );

		$state = WCP_Dashboard::setup_state( false );

		$this->assertSame( 'profile', $state['next'] );
		$this->assertSame( 1, $state['done'] );
	}

	public function test_setup_completes_when_every_condition_is_met() {
		$id = $this->create_customer();
		$this->login( $id );

		$customer = new WC_Customer( $id );
		$customer->set_billing_address_1( '1 Test Street' );
		$customer->set_billing_city( 'Testville' );
		$customer->set_billing_postcode( '12345' );
		$customer->set_billing_country( 'US' );
		$customer->set_shipping_address_1( '1 Test Street' );
		$customer->set_shipping_city( 'Testville' );
		$customer->set_shipping_postcode( '12345' );
		$customer->set_shipping_country( 'US' );
		$customer->save();

		$state = WCP_Dashboard::setup_state( true );

		$this->assertSame( 5, $state['done'] );
		$this->assertSame( 100, $state['percent'] );
		$this->assertSame( '', $state['next'] );
		$this->assertTrue( $state['complete'] );
	}

	public function test_setup_does_not_offer_steps_for_disabled_sections() {
		$this->login( $this->create_customer() );
		$this->save_settings( array( 'sections' => array( 'dashboard' ) ) );

		$state = WCP_Dashboard::setup_state( false );

		// Only "account created" survives; a step no one can complete is not
		// counted against the customer.
		$this->assertSame( 1, $state['total'] );
		$this->assertTrue( $state['complete'] );
	}

	/*
	|--------------------------------------------------------------------------
	| Store discovery
	|--------------------------------------------------------------------------
	*/

	public function test_discovery_returns_published_products_newest_first() {
		$older = $this->create_product( array( 'name' => 'Older product' ) );
		$older->set_date_created( '2020-01-01 00:00:00' );
		$older->save();

		$this->create_product( array( 'name' => 'Newer product' ) );

		$products = WCP_Dashboard::get_products();

		$this->assertNotEmpty( $products );
		$this->assertSame( 'Newer product', $products[0]['name'] );
		$this->assertArrayHasKey( 'permalink', $products[0] );
		$this->assertArrayHasKey( 'price_html', $products[0] );
		$this->assertSame( '', $products[0]['image'], 'No image is an empty string, never a placeholder URL.' );
	}

	public function test_discovery_never_returns_a_draft_product() {
		$this->create_product( array( 'name' => 'Listed product' ) );

		$product = $this->create_product( array( 'name' => 'Unreleased product' ) );
		$product->set_status( 'draft' );
		$product->save();

		$names = wp_list_pluck( WCP_Dashboard::get_products(), 'name' );

		$this->assertContains( 'Listed product', $names );
		$this->assertNotContains( 'Unreleased product', $names );
	}

	public function test_discovery_never_returns_a_catalog_hidden_product() {
		$this->create_product( array( 'name' => 'Listed product' ) );

		$product = $this->create_product( array( 'name' => 'Hidden product' ) );
		$product->set_catalog_visibility( 'hidden' );
		$product->save();

		$names = wp_list_pluck( WCP_Dashboard::get_products(), 'name' );

		$this->assertContains( 'Listed product', $names );
		$this->assertNotContains( 'Hidden product', $names );
	}

	public function test_discovery_limit_is_filterable_and_never_negative() {
		$this->create_product();
		$this->create_product();

		add_filter( 'wcp_dashboard_product_limit', '__return_zero' );
		$this->assertSame( array(), WCP_Dashboard::get_products() );
		remove_filter( 'wcp_dashboard_product_limit', '__return_zero' );

		$one = function () {
			return 1;
		};

		add_filter( 'wcp_dashboard_product_limit', $one );
		$this->assertCount( 1, WCP_Dashboard::get_products() );
		remove_filter( 'wcp_dashboard_product_limit', $one );
	}

	/*
	|--------------------------------------------------------------------------
	| The summary they feed
	|--------------------------------------------------------------------------
	*/

	public function test_summary_carries_setup_and_discovery_for_a_new_customer() {
		$this->create_product( array( 'name' => 'Shop item' ) );
		$this->login( $this->create_customer() );

		$summary = WCP_Dashboard::get_summary();

		$this->assertFalse( $summary['has_orders'] );
		$this->assertSame( 0, $summary['order_count'] );
		$this->assertSame( 40, $summary['setup']['percent'] );
		$this->assertNotEmpty( $summary['products'] );
	}

	public function test_summary_skips_discovery_while_setup_is_outstanding() {
		$this->create_product( array( 'name' => 'Shop item' ) );
		$id = $this->create_customer();
		$this->create_order( $id );
		$this->login( $id );

		$summary = WCP_Dashboard::get_summary();

		$this->assertTrue( $summary['has_orders'] );
		$this->assertFalse( $summary['setup']['complete'] );
		$this->assertSame( array(), $summary['products'], 'Setup comes first for a customer who still has one.' );
	}
}
