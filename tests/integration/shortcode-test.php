<?php
/**
 * Shortcode rendering across authentication and configuration states.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group integration
 */
class Shortcode_Test extends WCP_Test_Case {

	public function test_logged_out_renders_the_gate_and_no_customer_data() {
		$html = $this->render_portal();

		$this->assertStringContainsString( 'wcp-portal--gate', $html );
		$this->assertStringContainsString( 'wcp-authcard', $html );
		$this->assertStringNotContainsString( 'wcp-portal--app', $html );
		$this->assertStringNotContainsString( 'wcp_nonce', $html, 'No nonce is baked into a cacheable logged-out page.' );
		$this->assertStringNotContainsString( 'data-wcp-section', $html );
	}

	public function test_logged_in_renders_the_application_shell() {
		$id = $this->create_customer(
			array(
				'first_name'   => 'Casey',
				'display_name' => 'Casey Nolan',
				'user_email'   => 'casey@example.org',
			)
		);
		$this->login( $id );

		$html = $this->render_portal();

		$this->assertStringContainsString( 'wcp-portal--app', $html );
		$this->assertStringContainsString( 'data-wcp-section="dashboard"', $html );
		$this->assertStringContainsString( 'Casey', $html );
		$this->assertStringContainsString( 'data-wcp-nav-indicator', $html );
		$this->assertStringContainsString( 'data-wcp-appearance-trigger', $html );
		foreach ( WCP_Settings::VISUAL_THEMES as $preset ) {
			$this->assertStringContainsString(
				sprintf( 'data-wcp-visual-option="%s"', $preset ),
				$html,
				'Every registered preset is offered in the switcher.'
			);
		}
		$this->assertStringContainsString( 'data-wcp-motion="on"', $html );
		$this->assertStringContainsString( 'data-wcp-3d="on"', $html );
		$this->assertStringNotContainsString( 'wcp-portal--gate', $html );
	}

	public function test_customer_data_is_escaped_in_the_shell() {
		// WordPress strips tags from user names on save, so a name cannot carry
		// a payload to the template. A WooCommerce address field can.
		$id = $this->create_customer();
		$this->login( $id );

		$customer = new WC_Customer( $id );
		$customer->set_billing_city( 'X<img src=x onerror=alert(1)>' );
		$customer->set_billing_first_name( 'J' );
		$customer->set_billing_country( 'US' );
		$customer->save();

		$this->set_query( WCP_Navigation::QUERY_VAR, 'addresses' );
		$overview = $this->render_portal();
		$this->set_query( WCP_Navigation::ADDRESS_VAR, 'billing' );
		$form = $this->render_portal();
		$this->set_query( WCP_Navigation::QUERY_VAR, null );
		$this->set_query( WCP_Navigation::ADDRESS_VAR, null );

		foreach ( array( $overview, $form ) as $html ) {
			$this->assertStringNotContainsString( '<img src=x', $html );
			$this->assertStringContainsString( '&lt;img src=x', $html );
		}
	}

	public function test_layout_attribute_is_whitelisted() {
		$this->login( $this->create_customer() );

		$this->assertStringContainsString( 'data-wcp-layout="contained"', $this->render_portal( 'layout="contained"' ) );
		$this->assertStringContainsString( 'data-wcp-layout="full"', $this->render_portal( 'layout="full"' ) );
		$this->assertStringContainsString( 'data-wcp-layout="full"', $this->render_portal( 'layout="<script>"' ), 'Unknown values fall to the default.' );
	}

	public function test_section_attribute_is_whitelisted() {
		$this->login( $this->create_customer() );

		$this->assertStringContainsString( 'data-wcp-section="profile"', $this->render_portal( 'section="profile"' ) );
		$this->assertStringContainsString( 'data-wcp-section="dashboard"', $this->render_portal( 'section="../../wp-config"' ) );
	}

	public function test_disabled_portal_renders_nothing_for_customers() {
		$this->save_settings( array( 'enabled' => false ) );
		$this->login( $this->create_customer() );

		$this->assertSame( '', trim( $this->render_portal() ) );
	}

	public function test_disabled_portal_shows_a_notice_only_to_managers() {
		$this->save_settings( array( 'enabled' => false ) );
		$this->login( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->render_portal();

		$this->assertStringContainsString( 'switched off', $html );
		$this->assertStringContainsString( 'wcp-settings', $html, 'Links to the settings screen.' );
		$this->assertStringNotContainsString( 'wcp-portal--app', $html );
	}

	public function test_disabled_portal_loads_no_assets() {
		$this->save_settings( array( 'enabled' => false ) );
		$this->login( $this->create_customer() );

		$this->render_portal();

		$this->assertFalse( wp_style_is( WCP_Public::STYLE_HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_script_is( WCP_Public::SCRIPT_HANDLE, 'enqueued' ) );
	}

	public function test_rendering_enqueues_assets_once() {
		$this->login( $this->create_customer() );

		$this->render_portal();
		$this->render_portal();

		$this->assertTrue( wp_style_is( WCP_Public::STYLE_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_script_is( WCP_Public::SCRIPT_HANDLE, 'enqueued' ) );

		$data = wp_scripts()->get_data( WCP_Public::SCRIPT_HANDLE, 'data' );

		$this->assertSame( 1, substr_count( (string) $data, 'wcpPortalConfig' ), 'Localised exactly once.' );
		$this->assertStringContainsString( 'restNonce', (string) $data );
	}

	public function test_logged_out_config_carries_no_nonces() {
		$this->render_portal();

		$data = wp_scripts()->get_data( WCP_Public::SCRIPT_HANDLE, 'data' );

		$this->assertStringContainsString( '"nonce":""', (string) $data );
		$this->assertStringContainsString( '"restNonce":""', (string) $data );
	}
}
