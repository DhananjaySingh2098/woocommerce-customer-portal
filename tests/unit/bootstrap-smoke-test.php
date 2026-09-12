<?php
/**
 * Proves the test environment itself: WordPress, WooCommerce and the plugin
 * are all loaded, and the plugin booted in its full (not degraded) mode.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group unit
 */
class Bootstrap_Smoke_Test extends WCP_Test_Case {

	public function test_environment_is_complete() {
		$this->assertTrue( function_exists( 'wc_get_orders' ), 'WooCommerce must be loaded for integration coverage.' );
		$this->assertTrue( class_exists( 'WCP_Loader' ) );
		$this->assertTrue( class_exists( 'WCP_Orders' ), 'Full boot: order repository present.' );
		$this->assertTrue( shortcode_exists( WCP_SHORTCODE_TAG ) );
		$this->assertSame( '1.0.0', WCP_VERSION );
	}

	public function test_hpos_or_legacy_store_is_usable() {
		$order = $this->create_order( $this->create_customer() );
		$this->assertGreaterThan( 0, $order->get_id() );
		$this->assertInstanceOf( 'WC_Order', wc_get_order( $order->get_id() ) );
	}
}
