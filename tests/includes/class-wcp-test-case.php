<?php
/**
 * Base test case.
 *
 * Adds factories for the things every test needs -- customers, orders, a
 * signed-in session -- and resets the plugin's per-request caches between
 * tests so one test's settings cannot leak into the next.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * Shared fixtures and helpers.
 */
abstract class WCP_Test_Case extends WP_UnitTestCase {

	/**
	 * Reset plugin state before each test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( WCP_Settings::OPTION );
		WCP_Settings::flush();

		wp_set_current_user( 0 );

		$this->clear_rate_limits();
		$this->reset_assets();
	}

	/**
	 * Forget that assets were enqueued.
	 *
	 * The loader's components are singletons that would live for one request
	 * in production; in a test process they live for the whole run, so the
	 * "already enqueued" flag and the localised config would otherwise carry
	 * from one test into the next.
	 *
	 * @return void
	 */
	protected function reset_assets() {
		$public = WCP_Loader::instance()->get( 'public' );

		if ( $public ) {
			$prop = new ReflectionProperty( $public, 'enqueued' );
			$prop->setAccessible( true );
			$prop->setValue( $public, false );
		}

		wp_dequeue_style( WCP_Public::STYLE_HANDLE );
		wp_dequeue_script( WCP_Public::SCRIPT_HANDLE );
		wp_deregister_style( WCP_Public::STYLE_HANDLE );
		wp_deregister_script( WCP_Public::SCRIPT_HANDLE );
	}

	/**
	 * Tidy up after each test.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		delete_option( WCP_Settings::OPTION );
		WCP_Settings::flush();

		$this->clear_rate_limits();

		parent::tear_down();
	}

	/**
	 * Drop every rate-limit transient.
	 *
	 * @return void
	 */
	protected function clear_rate_limits() {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . WCP_Rate_Limit::PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . WCP_Rate_Limit::PREFIX ) . '%'
			)
		);

		wp_cache_flush();
	}

	/**
	 * Create a customer and return the user ID.
	 *
	 * @param array $overrides User fields.
	 * @return int
	 */
	protected function create_customer( array $overrides = array() ) {
		static $n = 0;

		++$n;

		$defaults = array(
			'role'       => 'customer',
			'user_login' => 'customer' . $n . '_' . wp_rand( 1000, 9999 ),
			'user_pass'  => 'Test-Pass-' . $n . '!',
			'user_email' => 'customer' . $n . '_' . wp_rand( 1000, 9999 ) . '@example.org',
			'first_name' => 'First' . $n,
			'last_name'  => 'Last' . $n,
		);

		return (int) self::factory()->user->create( array_merge( $defaults, $overrides ) );
	}

	/**
	 * Sign in as a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	protected function login( $user_id ) {
		wp_set_current_user( (int) $user_id );
	}

	/**
	 * Create a simple product.
	 *
	 * @param array $props Product properties.
	 * @return WC_Product_Simple
	 */
	protected function create_product( array $props = array() ) {
		$product = new WC_Product_Simple();
		$product->set_name( isset( $props['name'] ) ? $props['name'] : 'Test Product' );
		$product->set_regular_price( isset( $props['price'] ) ? $props['price'] : '25.00' );
		$product->set_sku( isset( $props['sku'] ) ? $props['sku'] : 'SKU-' . wp_rand( 1000, 99999 ) );
		$product->set_status( 'publish' );
		$product->save();

		return $product;
	}

	/**
	 * Create an order for a customer.
	 *
	 * @param int   $customer_id Owner.
	 * @param array $props       `status`, `items` (array of [product, qty]).
	 * @return WC_Order
	 */
	protected function create_order( $customer_id, array $props = array() ) {
		$order = wc_create_order( array( 'customer_id' => (int) $customer_id ) );

		$items = isset( $props['items'] ) ? $props['items'] : array( array( $this->create_product(), 1 ) );

		foreach ( $items as $line ) {
			$order->add_product( $line[0], $line[1] );
		}

		$address = array(
			'first_name' => 'Order',
			'last_name'  => 'Owner' . $customer_id,
			'address_1'  => '1 Test Street',
			'city'       => 'Testville',
			'state'      => 'TX',
			'postcode'   => '73301',
			'country'    => 'US',
			'email'      => 'owner' . $customer_id . '@example.org',
		);

		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );
		$order->set_payment_method_title( 'Test Gateway' );
		$order->calculate_totals();
		$order->set_status( isset( $props['status'] ) ? $props['status'] : 'processing' );
		$order->save();

		return $order;
	}

	/**
	 * Store a settings array through the real sanitiser.
	 *
	 * @param array $settings Partial settings.
	 * @return void
	 */
	protected function save_settings( array $settings ) {
		update_option( WCP_Settings::OPTION, WCP_Settings::sanitize( array_merge( WCP_Settings::defaults(), $settings ) ) );
		WCP_Settings::flush();
	}

	/**
	 * Render the shortcode for the current session.
	 *
	 * @param string $atts Shortcode attribute string.
	 * @return string
	 */
	protected function render_portal( $atts = '' ) {
		return do_shortcode( '[' . WCP_SHORTCODE_TAG . ( $atts ? ' ' . $atts : '' ) . ']' );
	}

	/**
	 * Set a query argument as if it came from the URL.
	 *
	 * @param string $key   Argument name.
	 * @param string $value Value, or null to unset.
	 * @return void
	 */
	protected function set_query( $key, $value ) {
		if ( null === $value ) {
			unset( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification
		} else {
			$_GET[ $key ] = $value; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
}
