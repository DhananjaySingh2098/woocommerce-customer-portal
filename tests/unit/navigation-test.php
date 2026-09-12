<?php
/**
 * WCP_Navigation: section whitelisting under settings.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group unit
 * @covers WCP_Navigation
 */
class Navigation_Test extends WCP_Test_Case {

	public function test_all_slugs_is_settings_independent() {
		$this->save_settings( array( 'sections' => array( 'profile' ) ) );

		$this->assertSame( array( 'dashboard', 'orders', 'addresses', 'profile' ), WCP_Navigation::all_slugs() );
	}

	public function test_items_exclude_disabled_sections() {
		$this->save_settings( array( 'sections' => array( 'dashboard', 'profile' ) ) );

		$nav = new WCP_Navigation();

		$this->assertSame( array( 'dashboard', 'profile' ), $nav->get_slugs() );
		$this->assertNull( $nav->get_item( 'orders' ) );
	}

	public function test_requested_disabled_section_collapses_to_the_landing_section() {
		$this->save_settings( array( 'sections' => array( 'dashboard', 'profile' ) ) );

		$nav = new WCP_Navigation();

		$this->assertSame( 'dashboard', $nav->get_current_section( 'orders' ) );
		$this->assertSame( 'dashboard', $nav->get_current_section( '<script>' ) );
		$this->assertSame( 'profile', $nav->get_current_section( 'profile' ) );
	}

	public function test_landing_section_falls_through_when_dashboard_is_off() {
		$this->save_settings(
			array(
				'sections'        => array( 'addresses', 'profile' ),
				'default_section' => 'orders',
			)
		);

		$nav = new WCP_Navigation();

		$this->assertSame( 'addresses', $nav->get_default_section() );
		$this->assertSame( 'addresses', $nav->get_current_section( null ) );
	}

	public function test_bare_url_belongs_to_the_landing_section() {
		$this->save_settings(
			array(
				'sections'        => array( 'orders', 'profile' ),
				'default_section' => 'orders',
			)
		);

		$nav = new WCP_Navigation();

		$this->assertSame( 'https://example.org/p/', $nav->get_section_url( 'orders', 'https://example.org/p/' ) );
		$this->assertStringContainsString( 'wcp_section=profile', $nav->get_section_url( 'profile', 'https://example.org/p/' ) );
	}

	public function test_filter_added_sections_are_not_switched_off_by_settings() {
		add_filter(
			'wcp_navigation_items',
			function ( $items ) {
				$items[] = array(
					'slug'          => 'rewards',
					'label'         => 'Rewards',
					'title'         => 'Rewards',
					'subtitle'      => '',
					'icon'          => 'sparkle',
					'status'        => 'ready',
					'endpoint'      => '',
					'description'   => '',
					'benefit_title' => '',
					'benefit_text'  => '',
				);
				return $items;
			}
		);

		$this->save_settings( array( 'sections' => array( 'dashboard' ) ) );

		$nav = new WCP_Navigation();

		$this->assertContains( 'rewards', $nav->get_slugs(), 'A section the settings screen never knew about cannot be disabled by it.' );
		$this->assertNotContains( 'orders', $nav->get_slugs() );

		remove_all_filters( 'wcp_navigation_items' );
	}

	public function test_order_and_address_urls_are_built_safely() {
		$nav = new WCP_Navigation();

		$this->assertStringContainsString( 'wcp_order=35', $nav->get_order_url( 35, 'https://example.org/p/' ) );
		$this->assertStringNotContainsString( 'wcp_order', $nav->get_order_url( -1, 'https://example.org/p/' ) );
		$this->assertStringContainsString( 'wcp_address=billing', $nav->get_address_url( 'billing', 'https://example.org/p/' ) );
		$this->assertStringNotContainsString( 'wcp_address', $nav->get_address_url( 'admin', 'https://example.org/p/' ) );
	}
}
