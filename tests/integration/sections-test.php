<?php
/**
 * Server-rendered sections: dashboard, orders, order detail, profile, addresses.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group integration
 */
class Sections_Test extends WCP_Test_Case {

	/**
	 * Render the portal at a section, with optional extra query args.
	 *
	 * @param string $section Section slug.
	 * @param array  $query   Extra query args.
	 * @return string
	 */
	private function render_section( $section, array $query = array() ) {
		$this->set_query( WCP_Navigation::QUERY_VAR, $section );

		foreach ( $query as $k => $v ) {
			$this->set_query( $k, $v );
		}

		$html = $this->render_portal();

		$this->set_query( WCP_Navigation::QUERY_VAR, null );

		foreach ( array_keys( $query ) as $k ) {
			$this->set_query( $k, null );
		}

		return $html;
	}

	/* --- Dashboard -------------------------------------------------------- */

	public function test_dashboard_shows_real_metrics() {
		$id = $this->create_customer();
		$this->create_order( $id, array( 'status' => 'completed' ) );
		$this->create_order( $id, array( 'status' => 'processing' ) );
		$this->login( $id );

		$html = $this->render_section( 'dashboard' );

		$this->assertStringContainsString( 'Total orders', $html );
		$this->assertMatchesRegularExpression( '/wcp-metric__value[^"]*">\s*2\s*</', $html );
		$this->assertStringContainsString( 'Lifetime value', $html );
		$this->assertStringContainsString( 'Recent orders', $html );
		$this->assertSame( 2, substr_count( $html, 'class="wcp-mini-order"' ) );
		$this->assertStringContainsString( 'Recent activity', $html );
	}

	public function test_dashboard_empty_state_for_a_customer_with_no_orders() {
		$this->login( $this->create_customer() );

		$html = $this->render_section( 'dashboard' );

		// A zero is real state, so it is stated rather than hidden -- but
		// nothing pretends there is order history behind it.
		$this->assertStringContainsString( 'No orders yet', $html );
		$this->assertStringContainsString( 'Total orders', $html );
		$this->assertMatchesRegularExpression( '/wcp-metric__value[^"]*">\s*0\s*</', $html );
		$this->assertStringNotContainsString( 'class="wcp-mini-order"', $html );
		$this->assertStringNotContainsString( 'Lifetime value', $html );
		$this->assertStringNotContainsString( 'Recent activity', $html );

		// The screen is composed, not empty: setup steps and quick actions.
		$this->assertStringContainsString( 'Getting started', $html );
		$this->assertStringContainsString( 'Quick actions', $html );
	}

	public function test_dashboard_account_health_states_every_saved_detail() {
		$id = $this->create_customer();
		$this->login( $id );

		$customer = new WC_Customer( $id );
		$customer->set_billing_address_1( '1 Test Street' );
		$customer->set_billing_city( 'Testville' );
		$customer->set_billing_postcode( '12345' );
		$customer->set_billing_country( 'US' );
		$customer->save();

		$html = $this->render_section( 'dashboard' );

		$this->assertStringContainsString( 'Account health', $html );
		$this->assertStringContainsString( 'Billing address', $html );
		$this->assertStringContainsString( 'Shipping address', $html );

		// Both states are words, not only colours: one saved, one not.
		$this->assertStringContainsString( 'wcp-health__state--ok', $html );
		$this->assertStringContainsString( 'wcp-health__state--todo', $html );
		$this->assertStringContainsString( '>Added<', str_replace( array( "\n", "\t" ), '', $html ) );
		$this->assertStringContainsString( '>Missing<', str_replace( array( "\n", "\t" ), '', $html ) );
	}

	public function test_dashboard_discovery_lists_real_products_and_never_calls_them_recommendations() {
		$this->create_product( array( 'name' => 'Kiln Ceramic Mug' ) );
		$this->login( $this->create_customer() );

		$html = $this->render_section( 'dashboard' );

		$this->assertStringContainsString( 'Latest products', $html );
		$this->assertStringContainsString( 'Kiln Ceramic Mug', $html );
		$this->assertStringNotContainsString( 'Recommended', $html );
	}

	public function test_dashboard_quick_actions_drop_disabled_sections() {
		$this->login( $this->create_customer() );
		$this->save_settings( array( 'sections' => array( 'dashboard', 'orders' ) ) );

		$html = $this->render_section( 'dashboard' );

		$this->assertStringContainsString( 'Quick actions', $html );
		$this->assertStringContainsString( 'View orders', $html );
		$this->assertStringNotContainsString( 'Manage profile', $html );
		$this->assertStringNotContainsString( 'Billing address', $html );
	}

	public function test_dashboard_hides_order_data_when_orders_are_disabled() {
		$id = $this->create_customer();
		$this->create_order( $id );
		$this->login( $id );
		$this->save_settings( array( 'sections' => array( 'dashboard', 'addresses', 'profile' ) ) );

		$html = $this->render_section( 'dashboard' );

		$this->assertStringNotContainsString( 'Total orders', $html );
		$this->assertStringNotContainsString( 'class="wcp-mini-order"', $html );
	}

	/* --- Orders ----------------------------------------------------------- */

	public function test_orders_list_paginates_at_ten() {
		$id = $this->create_customer();

		for ( $i = 0; $i < 12; $i++ ) {
			$this->create_order( $id );
		}

		$this->login( $id );

		$page1 = $this->render_section( 'orders' );

		$this->assertSame( 10, substr_count( $page1, 'class="wcp-order-row"' ) );
		$this->assertStringContainsString( 'Page 1 of 2', $page1 );
		$this->assertStringContainsString( 'rel="next"', $page1 );

		$page2 = $this->render_section( 'orders', array( WCP_Navigation::PAGE_VAR => '2' ) );

		$this->assertSame( 2, substr_count( $page2, 'class="wcp-order-row"' ) );
		$this->assertStringContainsString( 'Page 2 of 2', $page2 );
		$this->assertStringContainsString( 'rel="prev"', $page2 );
	}

	public function test_orders_list_shows_only_the_customers_own_orders() {
		$a = $this->create_customer();
		$b = $this->create_customer();
		$this->create_order( $a );
		$b_order = $this->create_order( $b );
		$this->login( $a );

		$html = $this->render_section( 'orders' );

		$this->assertSame( 1, substr_count( $html, 'class="wcp-order-row"' ) );
		$this->assertStringNotContainsString( 'wcp_order=' . $b_order->get_id(), $html );
	}

	public function test_order_detail_renders_items_totals_and_addresses() {
		$id      = $this->create_customer();
		$product = $this->create_product(
			array(
				'name'  => 'Aster Walnut Desk',
				'price' => '749.00',
			)
		);
		$order   = $this->create_order(
			$id,
			array(
				'status' => 'completed',
				'items'  => array( array( $product, 2 ) ),
			)
		);
		$this->login( $id );

		$html = $this->render_section( 'orders', array( WCP_Navigation::ORDER_VAR => (string) $order->get_id() ) );

		$this->assertStringContainsString( 'Order #' . $order->get_order_number(), $html );
		$this->assertStringContainsString( 'Aster Walnut Desk', $html );
		$this->assertStringContainsString( 'wcp-line-item__qty">2<', $html );
		$this->assertStringContainsString( 'wcp-totals', $html );
		$this->assertStringContainsString( 'Testville', $html );
		$this->assertStringContainsString( 'Test Gateway', $html );
		$this->assertStringContainsString( 'wcp-timeline', $html, 'Completed orders get a timeline.' );
		$this->assertStringContainsString( 'Back to orders', $html );
	}

	public function test_order_detail_for_another_customers_order_is_the_error_state() {
		$a     = $this->create_customer();
		$b     = $this->create_customer();
		$order = $this->create_order( $b );
		$this->login( $a );

		$html = $this->render_section( 'orders', array( WCP_Navigation::ORDER_VAR => (string) $order->get_id() ) );

		$this->assertStringContainsString( 'Order unavailable', $html );
		$this->assertStringNotContainsString( 'wcp-order__summary', $html );
		$this->assertStringNotContainsString( 'Owner' . $b, $html, 'Nothing of B leaks.' );
	}

	public function test_order_detail_for_a_nonexistent_or_negative_id_is_the_error_state() {
		$this->login( $this->create_customer() );

		$this->assertStringContainsString( 'Order unavailable', $this->render_section( 'orders', array( WCP_Navigation::ORDER_VAR => '999999' ) ) );
		$this->assertStringNotContainsString( 'wcp-order__summary', $this->render_section( 'orders', array( WCP_Navigation::ORDER_VAR => '-1' ) ) );
	}

	public function test_disabled_orders_section_is_unreachable_by_url() {
		$id    = $this->create_customer();
		$order = $this->create_order( $id );
		$this->login( $id );
		$this->save_settings( array( 'sections' => array( 'dashboard', 'profile' ) ) );

		$html = $this->render_section( 'orders', array( WCP_Navigation::ORDER_VAR => (string) $order->get_id() ) );

		$this->assertStringContainsString( 'data-wcp-section="dashboard"', $html, 'Collapsed to the landing section.' );
		$this->assertStringNotContainsString( 'wcp-order__summary', $html );
		$this->assertStringNotContainsString( 'data-wcp-section-link="orders"', $html, 'Not in the sidebar either.' );
	}

	/* --- Profile ---------------------------------------------------------- */

	public function test_profile_renders_fields_with_values_and_no_password_value() {
		$id = $this->create_customer(
			array(
				'first_name' => 'Jordan',
				'user_email' => 'jordan@example.org',
				'user_pass'  => 'Secret-Pass-1!',
			)
		);
		$this->login( $id );

		$html = $this->render_section( 'profile' );

		$this->assertStringContainsString( 'name="first_name"', $html );
		$this->assertStringContainsString( 'value="Jordan"', $html );
		$this->assertStringContainsString( 'value="jordan@example.org"', $html );
		$this->assertStringContainsString( 'name="current_password"', $html );
		$this->assertStringNotContainsString( 'Secret-Pass', $html );
		$this->assertStringContainsString( 'name="' . WCP_Security::NONCE_NAME . '"', $html, 'No-JS path carries the nonce.' );
	}

	/* --- Addresses -------------------------------------------------------- */

	public function test_addresses_overview_shows_empty_cards_then_saved_cards() {
		$id = $this->create_customer();
		$this->login( $id );

		$empty = $this->render_section( 'addresses' );

		$this->assertSame( 2, substr_count( $empty, 'Not set yet' ) );
		$this->assertStringContainsString( 'Add billing address', $empty );

		WCP_Addresses::update(
			'billing',
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

		$saved = $this->render_section( 'addresses' );

		$this->assertSame( 1, substr_count( $saved, 'Not set yet' ) );
		$this->assertStringContainsString( 'Austin, TX 78701', $saved );
		$this->assertStringContainsString( 'Edit billing address', $saved );
	}

	public function test_address_edit_form_is_built_from_woocommerce_fields() {
		$this->login( $this->create_customer() );

		$html = $this->render_section( 'addresses', array( WCP_Navigation::ADDRESS_VAR => 'billing' ) );

		$this->assertStringContainsString( 'data-wcp-address-type="billing"', $html );
		$this->assertStringContainsString( 'name="billing_country"', $html );
		$this->assertStringContainsString( '<select', $html, 'Country is a select.' );
		$this->assertStringContainsString( 'name="wcp_address_type" value="billing"', $html );
		$this->assertStringContainsString( 'aria-describedby=', $html, 'Errors are wired even before they exist.' );
	}

	public function test_address_edit_rejects_unknown_type() {
		$this->login( $this->create_customer() );

		$html = $this->render_section( 'addresses', array( WCP_Navigation::ADDRESS_VAR => 'admin' ) );

		$this->assertStringNotContainsString( 'data-wcp-address-type', $html );
		$this->assertStringContainsString( 'Saved addresses', $html, 'Falls back to the overview.' );
	}

	/* --- appearance ---------------------------------------------------------- */

	public function test_head_script_resolves_both_appearance_axes_before_paint() {
		$this->save_settings(
			array(
				'theme'        => 'dark',
				'visual_theme' => 'obsidian',
			)
		);

		$script = $this->render_head_script();

		$this->assertStringContainsString( 'id="wcp-theme-boot"', $script );
		$this->assertStringContainsString( "getItem('wcp-theme')", $script, 'Reads the stored appearance.' );
		$this->assertStringContainsString( "getItem('wcp-visual')", $script, 'Reads the stored visual theme.' );
		$this->assertStringContainsString( "s='dark'", $script, 'Admin default appearance is the fallback.' );
		$this->assertStringContainsString( "v='obsidian'", $script, 'Admin default visual theme is the fallback.' );
		$this->assertStringContainsString(
			'/^(' . implode( '|', WCP_Settings::VISUAL_THEMES ) . ')$/',
			$script,
			'Only the registered presets are accepted from storage.'
		);
		$this->assertStringContainsString( "setAttribute('data-wcp-theme'", $script );
		$this->assertStringContainsString( "setAttribute('data-wcp-visual'", $script );
	}

	public function test_head_script_never_carries_anything_but_validated_constants() {
		// A tampered option row cannot smuggle script through the resolver.
		update_option(
			WCP_Settings::OPTION,
			array(
				'theme'        => "dark');alert(1);//",
				'visual_theme' => '</script><script>alert(1)</script>',
			)
		);
		WCP_Settings::flush();

		$script = $this->render_head_script();

		$this->assertStringNotContainsString( 'alert', $script );
		$this->assertStringContainsString( "s='system'", $script );
		$this->assertStringContainsString( "v='aurora'", $script );
	}

	/**
	 * Visit a page hosting the shortcode and capture the pre-paint script.
	 *
	 * @return string
	 */
	private function render_head_script() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[' . WCP_SHORTCODE_TAG . ']',
			)
		);

		$this->go_to( get_permalink( $page_id ) );

		$theme = new WCP_Theme( new WCP_Page_Layout() );

		ob_start();
		$theme->print_head_script();

		return ob_get_clean();
	}
}
