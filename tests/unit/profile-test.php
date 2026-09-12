<?php
/**
 * WCP_Profile: validation, password handling, allowlisting.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group unit
 * @covers WCP_Profile
 */
class Profile_Test extends WCP_Test_Case {

	public function test_unauthenticated_update_is_refused() {
		$result = WCP_Profile::update( array( 'first_name' => 'X' ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_valid_update_persists() {
		$id = $this->create_customer();
		$this->login( $id );

		$result = WCP_Profile::update(
			array(
				'first_name'   => 'Updated',
				'display_name' => 'Updated Name',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'Updated', get_userdata( $id )->first_name );
		$this->assertSame( 'Updated Name', get_userdata( $id )->display_name );
	}

	public function test_display_name_is_required() {
		$this->login( $this->create_customer() );

		$result = WCP_Profile::update( array( 'display_name' => '   ' ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertArrayHasKey( 'display_name', $result->get_error_data()['fields'] );
	}

	public function test_invalid_email_is_reported_as_invalid_not_empty() {
		$this->login( $this->create_customer() );

		$result = WCP_Profile::update( array( 'user_email' => 'not-an-email' ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$message = $result->get_error_data()['fields']['user_email'];
		$this->assertStringContainsString( 'valid', $message );
		$this->assertStringNotContainsString( 'empty', $message );
	}

	public function test_duplicate_email_is_rejected() {
		$other = $this->create_customer( array( 'user_email' => 'taken@example.org' ) );
		$this->login( $this->create_customer() );

		$result = WCP_Profile::update( array( 'user_email' => 'TAKEN@example.org' ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertArrayHasKey( 'user_email', $result->get_error_data()['fields'] );
		$this->assertSame( 'taken@example.org', get_userdata( $other )->user_email, 'Other account untouched.' );
	}

	public function test_own_email_unchanged_is_fine() {
		$id = $this->create_customer( array( 'user_email' => 'mine@example.org' ) );
		$this->login( $id );

		$this->assertIsArray( WCP_Profile::update( array( 'user_email' => 'Mine@Example.org' ) ) );
	}

	public function test_privileged_keys_are_ignored() {
		$id           = $this->create_customer();
		$admin_before = get_userdata( 1 )->first_name;
		$this->login( $id );

		$result = WCP_Profile::update(
			array(
				'first_name'      => 'Jordan',
				'ID'              => 1,
				'user_login'      => 'admin',
				'role'            => 'administrator',
				'wp_capabilities' => array( 'administrator' => true ),
				'user_pass'       => 'pwned',
				'user_url'        => 'http://evil.test',
			)
		);

		$this->assertIsArray( $result );

		$user = get_userdata( $id );

		$this->assertSame( 'Jordan', $user->first_name );
		$this->assertFalse( user_can( $id, 'manage_options' ) );
		$this->assertSame( array( 'customer' ), $user->roles );
		$this->assertSame( '', $user->user_url );
		$this->assertNotSame( 'admin', $user->user_login );
		$this->assertSame( $admin_before, get_userdata( 1 )->first_name, 'ID => 1 did not redirect the write to the administrator.' );
	}

	/* --- password --------------------------------------------------------- */

	public function test_password_change_requires_the_current_password() {
		$id = $this->create_customer( array( 'user_pass' => 'Original-Pass-1!' ) );
		$this->login( $id );

		$result = WCP_Profile::update(
			array(
				'current_password' => 'wrong',
				'new_password'     => 'Brand-New-Pass-9!',
				'confirm_password' => 'Brand-New-Pass-9!',
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertArrayHasKey( 'current_password', $result->get_error_data()['fields'] );
		$this->assertTrue( wp_check_password( 'Original-Pass-1!', get_userdata( $id )->user_pass, $id ), 'Password unchanged.' );
	}

	public function test_short_and_mismatched_passwords_are_rejected() {
		$id = $this->create_customer( array( 'user_pass' => 'Original-Pass-1!' ) );
		$this->login( $id );

		$short = WCP_Profile::update(
			array(
				'current_password' => 'Original-Pass-1!',
				'new_password'     => 'short',
				'confirm_password' => 'short',
			)
		);
		$this->assertArrayHasKey( 'new_password', $short->get_error_data()['fields'] );

		$mismatch = WCP_Profile::update(
			array(
				'current_password' => 'Original-Pass-1!',
				'new_password'     => 'Brand-New-Pass-9!',
				'confirm_password' => 'Different-9!',
			)
		);
		$this->assertArrayHasKey( 'confirm_password', $mismatch->get_error_data()['fields'] );

		$backslash = WCP_Profile::update(
			array(
				'current_password' => 'Original-Pass-1!',
				'new_password'     => 'has\\backslash1',
				'confirm_password' => 'has\\backslash1',
			)
		);
		$this->assertArrayHasKey( 'new_password', $backslash->get_error_data()['fields'] );
	}

	public function test_valid_password_change_persists_and_keeps_the_session() {
		$id = $this->create_customer( array( 'user_pass' => 'Original-Pass-1!' ) );
		$this->login( $id );

		$result = WCP_Profile::update(
			array(
				'current_password' => 'Original-Pass-1!',
				'new_password'     => 'Brand-New-Pass-9!',
				'confirm_password' => 'Brand-New-Pass-9!',
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( wp_check_password( 'Brand-New-Pass-9!', get_userdata( $id )->user_pass, $id ) );
		$this->assertSame( $id, get_current_user_id(), 'Still signed in after the change.' );
		$this->assertArrayNotHasKey( 'new_password', $result, 'Passwords never come back.' );
		$this->assertArrayNotHasKey( 'current_password', $result );
	}

	public function test_empty_password_fields_mean_no_change() {
		$id = $this->create_customer( array( 'user_pass' => 'Original-Pass-1!' ) );
		$this->login( $id );

		$result = WCP_Profile::update(
			array(
				'first_name'       => 'Only',
				'current_password' => '',
				'new_password'     => '',
				'confirm_password' => '',
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( wp_check_password( 'Original-Pass-1!', get_userdata( $id )->user_pass, $id ) );
	}

	public function test_failed_password_attempts_are_throttled_and_the_correct_one_refused_while_throttled() {
		$id = $this->create_customer( array( 'user_pass' => 'Original-Pass-1!' ) );
		$this->login( $id );

		$change = function ( $current ) {
			return WCP_Profile::update(
				array(
					'current_password' => $current,
					'new_password'     => 'Brand-New-Pass-9!',
					'confirm_password' => 'Brand-New-Pass-9!',
				)
			);
		};

		for ( $i = 0; $i < 5; $i++ ) {
			$r = $change( 'wrong-' . $i );
			$this->assertSame( 400, $r->get_error_data()['status'], "Attempt $i is a normal validation failure." );
		}

		$sixth = $change( 'wrong-6' );
		$this->assertSame( 429, $sixth->get_error_data()['status'] );
		$this->assertArrayHasKey( 'current_password', $sixth->get_error_data()['fields'], 'Still names the field for the form.' );
		$this->assertGreaterThan( 0, WCP_Rate_Limit::retry_after( $sixth ) );

		$correct = $change( 'Original-Pass-1!' );
		$this->assertSame( 429, $correct->get_error_data()['status'], 'No finish line while throttled.' );
		$this->assertTrue( wp_check_password( 'Original-Pass-1!', get_userdata( $id )->user_pass, $id ), 'Password did not change.' );

		// An ordinary edit is unaffected by the password bucket.
		$this->assertIsArray( WCP_Profile::update( array( 'first_name' => 'Still works' ) ) );
	}

	public function test_successful_change_clears_the_failure_bucket() {
		$id = $this->create_customer( array( 'user_pass' => 'Original-Pass-1!' ) );
		$this->login( $id );

		for ( $i = 0; $i < 3; $i++ ) {
			WCP_Profile::update(
				array(
					'current_password' => 'wrong',
					'new_password'     => 'Brand-New-Pass-9!',
					'confirm_password' => 'Brand-New-Pass-9!',
				)
			);
		}

		$ok = WCP_Profile::update(
			array(
				'current_password' => 'Original-Pass-1!',
				'new_password'     => 'Brand-New-Pass-9!',
				'confirm_password' => 'Brand-New-Pass-9!',
			)
		);
		$this->assertIsArray( $ok );

		$this->assertTrue( WCP_Rate_Limit::check( 'password' ), 'Bucket emptied by success.' );
	}
}
