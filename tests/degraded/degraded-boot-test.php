<?php
/**
 * Behaviour with WooCommerce absent.
 *
 * Runs under `WCP_TESTS_WITHOUT_WC=1` (see phpunit-degraded.xml.dist), which
 * makes the bootstrap skip WooCommerce entirely so the plugin takes its
 * degraded path: no fatal, no front-end assets, shortcode claimed but silent,
 * an admin notice for people who can fix it.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group degraded
 */
class Degraded_Boot_Test extends WP_UnitTestCase {

	public function test_environment_is_actually_degraded() {
		$this->assertFalse( class_exists( 'WooCommerce' ), 'This suite must run without WooCommerce.' );
		$this->assertFalse( function_exists( 'wc_get_orders' ) );
	}

	public function test_plugin_booted_without_fatal_and_without_customer_classes() {
		$this->assertTrue( class_exists( 'WCP_Loader' ) );
		$this->assertTrue( class_exists( 'WCP_Security' ), 'Security primitives load in every mode.' );
		$this->assertFalse( class_exists( 'WCP_Orders' ), 'No order code is loaded without WooCommerce.' );
		$this->assertFalse( class_exists( 'WCP_REST_Orders' ) );
		$this->assertFalse( class_exists( 'WCP_Settings_Page' ), 'No settings screen without WooCommerce to settle under.' );
	}

	public function test_shortcode_is_claimed_but_renders_nothing_for_visitors() {
		$this->assertTrue( shortcode_exists( WCP_SHORTCODE_TAG ), 'Claimed, so pages never show a raw [wcp_customer_portal].' );

		wp_set_current_user( 0 );
		$this->assertSame( '', do_shortcode( '[' . WCP_SHORTCODE_TAG . ']' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( '', do_shortcode( '[' . WCP_SHORTCODE_TAG . ']' ), 'Nothing for a logged-in non-manager either.' );
	}

	public function test_managers_see_an_explanatory_notice_in_place_of_the_portal() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = do_shortcode( '[' . WCP_SHORTCODE_TAG . ']' );

		$this->assertStringContainsString( 'inactive', $html );
		$this->assertStringContainsString( 'WooCommerce', $html );
		$this->assertStringNotContainsString( 'wcp-portal--', $html, 'No portal shell.' );
	}

	public function test_no_rest_routes_are_registered() {
		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'rest_api_init' );

		$this->assertSame(
			array(),
			array_filter(
				array_keys( $wp_rest_server->get_routes() ),
				function ( $r ) {
					return 0 === strpos( $r, '/wcp/' );
				}
			)
		);

		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function test_no_front_end_assets_are_enqueued() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		do_shortcode( '[' . WCP_SHORTCODE_TAG . ']' );
		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_style_is( 'wcp-portal', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'wcp-portal', 'enqueued' ) );
	}

	public function test_admin_notice_names_the_missing_dependency() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'plugins' );

		ob_start();
		do_action( 'admin_notices' );
		$notices = ob_get_clean();

		$this->assertStringContainsString( 'WooCommerce', $notices );
		$this->assertStringContainsString( 'notice', $notices );
	}
}
