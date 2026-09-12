<?php
/**
 * WCP_Rate_Limit: thresholds, windows, isolation.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group unit
 * @covers WCP_Rate_Limit
 */
class Rate_Limit_Test extends WCP_Test_Case {

	public function test_unknown_bucket_never_limits() {
		$this->login( $this->create_customer() );

		WCP_Rate_Limit::record( 'nonexistent' );

		$this->assertTrue( WCP_Rate_Limit::check( 'nonexistent' ) );
	}

	public function test_allows_up_to_the_limit_then_refuses() {
		$this->login( $this->create_customer() );

		$limit = WCP_Rate_Limit::buckets()['password']['limit'];

		for ( $i = 0; $i < $limit; $i++ ) {
			$this->assertTrue( WCP_Rate_Limit::check( 'password' ), "Attempt $i should be allowed." );
			WCP_Rate_Limit::record( 'password' );
		}

		$error = WCP_Rate_Limit::check( 'password' );

		$this->assertInstanceOf( 'WP_Error', $error );
		$this->assertSame( 'wcp_rate_limited', $error->get_error_code() );
		$this->assertSame( 429, $error->get_error_data()['status'] );
	}

	public function test_retry_after_is_positive_and_within_the_window() {
		$this->login( $this->create_customer() );

		$bucket = WCP_Rate_Limit::buckets()['password'];

		for ( $i = 0; $i < $bucket['limit']; $i++ ) {
			WCP_Rate_Limit::record( 'password' );
		}

		$error = WCP_Rate_Limit::check( 'password' );
		$retry = WCP_Rate_Limit::retry_after( $error );

		$this->assertGreaterThan( 0, $retry );
		$this->assertLessThanOrEqual( $bucket['window'], $retry );
		$this->assertSame( 0, WCP_Rate_Limit::retry_after( new WP_Error( 'other', 'x' ) ), 'No hint on unrelated errors.' );
		$this->assertSame( 0, WCP_Rate_Limit::retry_after( 'not-an-error' ) );
	}

	public function test_error_message_reveals_nothing_about_the_bucket() {
		$this->login( $this->create_customer() );

		for ( $i = 0; $i < 30; $i++ ) {
			WCP_Rate_Limit::record( 'profile' );
		}

		$error = WCP_Rate_Limit::check( 'profile' );

		$this->assertInstanceOf( 'WP_Error', $error );
		$this->assertDoesNotMatchRegularExpression( '/\d/', $error->get_error_message(), 'No numbers in the message.' );
		$this->assertStringNotContainsString( 'profile', $error->get_error_message() );
	}

	public function test_one_customers_bucket_does_not_touch_anothers() {
		$a = $this->create_customer();
		$b = $this->create_customer();

		$this->login( $a );

		for ( $i = 0; $i < 5; $i++ ) {
			WCP_Rate_Limit::record( 'password' );
		}

		$this->assertInstanceOf( 'WP_Error', WCP_Rate_Limit::check( 'password' ), 'A is exhausted.' );

		$this->login( $b );

		$this->assertTrue( WCP_Rate_Limit::check( 'password' ), 'B is untouched.' );
	}

	public function test_clear_empties_the_bucket() {
		$this->login( $this->create_customer() );

		for ( $i = 0; $i < 5; $i++ ) {
			WCP_Rate_Limit::record( 'password' );
		}

		$this->assertInstanceOf( 'WP_Error', WCP_Rate_Limit::check( 'password' ) );

		WCP_Rate_Limit::clear( 'password' );

		$this->assertTrue( WCP_Rate_Limit::check( 'password' ) );
	}

	public function test_buckets_are_independent_of_each_other() {
		$this->login( $this->create_customer() );

		for ( $i = 0; $i < 5; $i++ ) {
			WCP_Rate_Limit::record( 'password' );
		}

		$this->assertInstanceOf( 'WP_Error', WCP_Rate_Limit::check( 'password' ) );
		$this->assertTrue( WCP_Rate_Limit::check( 'profile' ), 'Password exhaustion must not block ordinary profile edits.' );
		$this->assertTrue( WCP_Rate_Limit::check( 'address' ) );
	}

	public function test_filter_can_tune_but_not_disable() {
		add_filter(
			'wcp_rate_limit_buckets',
			function ( $buckets ) {
				$buckets['password'] = array(
					'limit'  => 0,
					'window' => 0,
				);
				$buckets['profile']  = array(
					'limit'  => 2,
					'window' => 60,
				);

				return $buckets;
			}
		);

		$buckets = WCP_Rate_Limit::buckets();

		$this->assertSame( 1, $buckets['password']['limit'], 'Zero is clamped to one, not treated as unlimited.' );
		$this->assertSame( 10, $buckets['password']['window'], 'Window floor of ten seconds.' );
		$this->assertSame( 2, $buckets['profile']['limit'] );

		remove_all_filters( 'wcp_rate_limit_buckets' );
	}

	public function test_password_bucket_is_stricter_than_the_others() {
		$buckets = WCP_Rate_Limit::buckets();

		$this->assertLessThan( $buckets['profile']['limit'], $buckets['password']['limit'] );
		$this->assertGreaterThan( $buckets['profile']['window'], $buckets['password']['window'] );
	}
}
