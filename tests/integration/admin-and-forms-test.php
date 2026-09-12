<?php
/**
 * Admin settings screen and the no-JavaScript form handler.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group integration
 */
class Admin_And_Forms_Test extends WCP_Test_Case {

	/* --- Settings screen -------------------------------------------------- */

	public function test_setting_is_registered_with_the_plugins_sanitiser() {
		// Called directly: WooCommerce's own `admin_init` handlers redirect, which
		// PHPUnit cannot survive, and they are not what is under test.
		( new WCP_Settings_Page() )->register_setting();

		$registered = get_registered_settings();

		$this->assertArrayHasKey( WCP_Settings::OPTION, $registered );
		$this->assertSame( array( 'WCP_Settings', 'sanitize' ), $registered[ WCP_Settings::OPTION ]['sanitize_callback'] );
		$this->assertFalse( $registered[ WCP_Settings::OPTION ]['show_in_rest'], 'Never exposed through /wp/v2/settings.' );
	}

	public function test_menu_is_registered_under_woocommerce_for_managers_only() {
		global $submenu;

		$this->login( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		( new WCP_Settings_Page() )->register_menu();

		$this->assertArrayHasKey( 'woocommerce', $submenu );

		$entry = current(
			array_filter(
				$submenu['woocommerce'],
				function ( $item ) {
					return WCP_Settings_Page::SLUG === $item[2];
				}
			)
		);

		$this->assertNotEmpty( $entry, 'Customer Portal appears under WooCommerce.' );
		$this->assertSame( WCP_Settings::CAPABILITY, $entry[1] );
	}

	public function test_screen_render_dies_without_the_capability() {
		$this->login( $this->create_customer() );

		$page = new WCP_Settings_Page();

		$this->expectException( 'WPDieException' );

		$page->render();
	}

	public function test_screen_renders_for_a_manager() {
		$this->login( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$page = new WCP_Settings_Page();

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wcp-admin__form', $html );
		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( WCP_Settings::OPTION . '[accent]', $html );
		$this->assertStringContainsString( '_wpnonce', $html, 'Settings API nonce present.' );
		$this->assertStringContainsString( '5.91:1', $html, 'Default accent contrast reported.' );
	}

	public function test_screen_flags_pages_that_contain_the_shortcode() {
		$with    = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Portal Page',
				'post_content' => '[' . WCP_SHORTCODE_TAG . ']',
			)
		);
		$without = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Plain Page',
				'post_content' => 'Hello',
			)
		);

		$this->login( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		( new WCP_Settings_Page() )->render();
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="' . $with . '"[^>]*>\s*Portal Page — has portal shortcode/', $html );
		$this->assertMatchesRegularExpression( '/value="' . $without . '"[^>]*>\s*Plain Page\s*</', $html );
	}

	public function test_saving_through_the_sanitiser_flushes_the_cache() {
		$this->assertTrue( WCP_Settings::is_enabled() );

		update_option(
			WCP_Settings::OPTION,
			WCP_Settings::sanitize(
				array(
					'enabled'  => '',
					'sections' => array( 'profile' ),
				)
			)
		);

		// The update_option_ hook calls WCP_Settings::flush().
		$this->assertFalse( WCP_Settings::is_enabled() );
		$this->assertSame( array( 'profile' ), WCP_Settings::enabled_sections() );
	}

	public function test_admin_notice_only_when_a_configured_page_goes_missing() {
		$this->login( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		$admin = new WCP_Admin();

		ob_start();
		$admin->portal_page_notice();
		$this->assertSame( '', ob_get_clean(), 'Nothing configured: nothing to say.' );

		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$this->save_settings( array( 'portal_page' => $page ) );

		ob_start();
		$admin->portal_page_notice();
		$this->assertSame( '', ob_get_clean(), 'Valid page: nothing to say.' );

		wp_delete_post( $page, true );
		WCP_Settings::flush();

		ob_start();
		$admin->portal_page_notice();
		$notice = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $notice );
		$this->assertStringContainsString( 'no longer exists', $notice );
	}

	/* --- No-JS form handler ---------------------------------------------- */

	/**
	 * Simulate a form POST and capture the redirect + flash.
	 *
	 * @param array $post POST body.
	 * @return array{redirect:string,flash:array}
	 */
	private function submit_form( array $post ) {
		$_POST                     = $post; // phpcs:ignore WordPress.Security.NonceVerification
		$_REQUEST                  = $post; // phpcs:ignore WordPress.Security.NonceVerification -- verify_nonce() reads $_REQUEST, as WordPress does.
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$redirect = '';

		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$redirect ) {
				$redirect = $location;
				throw new WPDieException( 'redirect' );
			}
		);

		try {
			( new WCP_Form_Handler() )->handle();
		} catch ( WPDieException $e ) {
			// Expected: the handler exits after redirecting.
		}

		remove_all_filters( 'wp_redirect' );

		$_POST                     = array(); // phpcs:ignore WordPress.Security.NonceVerification
		$_REQUEST                  = array(); // phpcs:ignore WordPress.Security.NonceVerification
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$flash = get_transient( WCP_Form_Handler::FLASH_PREFIX . get_current_user_id() );

		return array(
			'redirect' => $redirect,
			'flash'    => is_array( $flash ) ? $flash : array(),
		);
	}

	public function test_form_post_with_a_valid_nonce_saves_and_redirects_with_a_flash() {
		$id = $this->create_customer();
		$this->login( $id );

		$result = $this->submit_form(
			array(
				WCP_Form_Handler::FORM_FIELD => 'profile',
				WCP_Security::NONCE_NAME     => WCP_Security::create_nonce(),
				'wcp_return_section'         => 'profile',
				'first_name'                 => 'Posted',
			)
		);

		$this->assertStringContainsString( WCP_Form_Handler::RESULT_VAR . '=1', $result['redirect'] );
		$this->assertSame( 'success', $result['flash']['status'] );
		$this->assertSame( 'Posted', get_userdata( $id )->first_name );
	}

	public function test_form_post_with_a_bad_nonce_changes_nothing() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		$this->login( $id );

		$result = $this->submit_form(
			array(
				WCP_Form_Handler::FORM_FIELD => 'profile',
				WCP_Security::NONCE_NAME     => 'forged',
				'first_name'                 => 'Hacked',
			)
		);

		$this->assertSame( 'error', $result['flash']['status'] );
		$this->assertStringContainsString( 'expired', $result['flash']['message'] );
		$this->assertSame( 'Original', get_userdata( $id )->first_name );
	}

	public function test_form_post_never_stores_passwords_in_the_flash() {
		$this->login( $this->create_customer( array( 'user_pass' => 'Real-Pass-1!' ) ) );

		$result = $this->submit_form(
			array(
				WCP_Form_Handler::FORM_FIELD => 'profile',
				WCP_Security::NONCE_NAME     => WCP_Security::create_nonce(),
				'current_password'           => 'wrong-guess',
				'new_password'               => 'New-Pass-9!',
				'confirm_password'           => 'New-Pass-9!',
			)
		);

		$this->assertSame( 'error', $result['flash']['status'] );
		$this->assertStringNotContainsString( 'wrong-guess', wp_json_encode( $result['flash'] ) );
		$this->assertStringNotContainsString( 'New-Pass', wp_json_encode( $result['flash'] ) );
		$this->assertArrayNotHasKey( 'current_password', $result['flash']['values'] );
	}

	public function test_form_post_to_a_disabled_section_is_refused() {
		$id = $this->create_customer( array( 'first_name' => 'Original' ) );
		$this->login( $id );
		$this->save_settings( array( 'sections' => array( 'dashboard', 'orders' ) ) );

		$result = $this->submit_form(
			array(
				WCP_Form_Handler::FORM_FIELD => 'profile',
				WCP_Security::NONCE_NAME     => WCP_Security::create_nonce(),
				'first_name'                 => 'Changed',
			)
		);

		$this->assertSame( 'error', $result['flash']['status'] );
		$this->assertSame( 'Original', get_userdata( $id )->first_name );
	}

	public function test_failed_address_post_returns_to_the_form_not_the_list() {
		$this->login( $this->create_customer() );

		$result = $this->submit_form(
			array(
				WCP_Form_Handler::FORM_FIELD => 'address',
				WCP_Security::NONCE_NAME     => WCP_Security::create_nonce(),
				'wcp_address_type'           => 'billing',
				'billing_country'            => 'US',
				'billing_city'               => '',
			)
		);

		$this->assertStringContainsString( WCP_Navigation::ADDRESS_VAR . '=billing', $result['redirect'] );
		$this->assertSame( 'error', $result['flash']['status'] );
		$this->assertArrayHasKey( 'billing_city', $result['flash']['fields'] );
	}

	public function test_flash_is_one_shot() {
		$this->login( $this->create_customer() );

		$this->submit_form(
			array(
				WCP_Form_Handler::FORM_FIELD => 'profile',
				WCP_Security::NONCE_NAME     => WCP_Security::create_nonce(),
				'first_name'                 => 'X',
			)
		);

		$this->set_query( WCP_Form_Handler::RESULT_VAR, '1' );

		$this->assertNotEmpty( WCP_Form_Handler::consume_flash() );
		$this->assertFalse( get_transient( WCP_Form_Handler::FLASH_PREFIX . get_current_user_id() ), 'Consumed on first read.' );

		$this->set_query( WCP_Form_Handler::RESULT_VAR, null );
	}
}
