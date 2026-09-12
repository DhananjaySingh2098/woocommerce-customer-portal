<?php
/**
 * WCP_Order_Status: tones, labels, timeline, custom statuses.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group unit
 * @covers WCP_Order_Status
 */
class Order_Status_Test extends WCP_Test_Case {

	/**
	 * @dataProvider tone_provider
	 */
	public function test_core_statuses_map_to_tones( $status, $tone ) {
		$this->assertSame( $tone, WCP_Order_Status::tone( $status ) );
	}

	public function tone_provider() {
		return array(
			array( 'completed', 'success' ),
			array( 'processing', 'info' ),
			array( 'on-hold', 'warning' ),
			array( 'pending', 'warning' ),
			array( 'cancelled', 'neutral' ),
			array( 'refunded', 'neutral' ),
			array( 'failed', 'danger' ),
			array( 'wc-completed', 'success' ),
			array( 'WC-FAILED', 'danger' ),
		);
	}

	public function test_unknown_status_falls_back_to_neutral_and_still_has_a_label() {
		$described = WCP_Order_Status::describe( 'awaiting-shipment' );

		$this->assertSame( 'neutral', $described['tone'] );
		$this->assertSame( 'wcp-order-status--neutral', $described['class'] );
		$this->assertSame( 'Awaiting Shipment', $described['label'], 'Title-cased slug when WooCommerce has no label.' );
		$this->assertSame( 'awaiting-shipment', $described['slug'] );
	}

	public function test_store_registered_status_uses_woocommerce_label() {
		add_filter(
			'wc_order_statuses',
			function ( $statuses ) {
				$statuses['wc-awaiting-stock'] = 'Awaiting stock';
				return $statuses;
			}
		);

		$this->assertSame( 'Awaiting stock', WCP_Order_Status::label( 'awaiting-stock' ) );

		remove_all_filters( 'wc_order_statuses' );
	}

	public function test_tone_filter_is_restricted_to_known_tones() {
		add_filter(
			'wcp_order_status_tone',
			function () {
				return 'danger';
			}
		);
		$this->assertSame( 'danger', WCP_Order_Status::tone( 'custom' ) );
		remove_all_filters( 'wcp_order_status_tone' );

		add_filter(
			'wcp_order_status_tone',
			function () {
				return 'red; } .x{';
			}
		);
		$this->assertSame( 'neutral', WCP_Order_Status::tone( 'custom' ), 'A filter cannot inject a class name.' );
		remove_all_filters( 'wcp_order_status_tone' );
	}

	public function test_class_is_always_a_safe_html_class() {
		foreach ( array( 'completed', 'x<script>', '../../', '' ) as $status ) {
			$this->assertMatchesRegularExpression( '/^wcp-order-status--[a-z]+$/', WCP_Order_Status::describe( $status )['class'] );
		}
	}

	public function test_empty_status_has_empty_label() {
		$this->assertSame( '', WCP_Order_Status::label( '' ) );
	}

	public function test_timeline_only_for_linear_progress() {
		$this->assertSame( array(), WCP_Order_Status::timeline( 'cancelled' ) );
		$this->assertSame( array(), WCP_Order_Status::timeline( 'refunded' ) );
		$this->assertSame( array(), WCP_Order_Status::timeline( 'failed' ) );
		$this->assertSame( array(), WCP_Order_Status::timeline( 'custom' ) );
	}

	public function test_timeline_marks_done_current_upcoming() {
		$steps = WCP_Order_Status::timeline( 'processing' );

		$this->assertCount( 3, $steps );
		$this->assertSame( array( 'done', 'current', 'upcoming' ), wp_list_pluck( $steps, 'state' ) );
		$this->assertSame( array( 'pending', 'processing', 'completed' ), wp_list_pluck( $steps, 'key' ) );

		$this->assertSame( array( 'done', 'done', 'current' ), wp_list_pluck( WCP_Order_Status::timeline( 'completed' ), 'state' ) );
		$this->assertSame( array( 'current', 'upcoming', 'upcoming' ), wp_list_pluck( WCP_Order_Status::timeline( 'pending' ), 'state' ) );
	}
}
