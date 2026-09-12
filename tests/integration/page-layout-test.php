<?php
/**
 * Page framing: which theme chrome the portal's own page suppresses.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group integration
 */
class Page_Layout_Test extends WCP_Test_Case {

	/**
	 * Put a portal page on screen and hand back a fresh layout controller.
	 *
	 * @param string $atts Shortcode attributes.
	 * @return WCP_Page_Layout
	 */
	private function visit_portal_page( $atts = 'layout="full"' ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Customer portal',
				'post_content' => '[' . WCP_SHORTCODE_TAG . ( $atts ? ' ' . $atts : '' ) . ']',
			)
		);

		$this->go_to( get_permalink( $page_id ) );

		return new WCP_Page_Layout();
	}

	/**
	 * Put an ordinary page on screen and hand back a fresh layout controller.
	 *
	 * @return WCP_Page_Layout
	 */
	private function visit_ordinary_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'About us',
				'post_content' => 'Just a page, with a footer of its own.',
			)
		);

		$this->go_to( get_permalink( $page_id ) );

		return new WCP_Page_Layout();
	}

	/**
	 * A footer template part, as a block theme emits it.
	 *
	 * @param array $attrs Block attributes.
	 * @return array
	 */
	private function footer_part( array $attrs = array() ) {
		if ( array() === $attrs ) {
			$attrs = array(
				'slug' => 'footer',
				'area' => 'footer',
			);
		}

		return array(
			'blockName' => 'core/template-part',
			'attrs'     => $attrs,
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Detection
	|--------------------------------------------------------------------------
	*/

	public function test_a_full_width_portal_page_ends_with_the_portals_own_footer() {
		$layout = $this->visit_portal_page();

		$this->assertTrue( $layout->has_portal() );
		$this->assertTrue( $layout->should_suppress_footer() );
		$this->assertContains( WCP_Page_Layout::BODY_CLASS_STANDALONE, $layout->body_class( array() ) );
	}

	public function test_a_contained_portal_leaves_the_page_ending_to_the_theme() {
		$layout = $this->visit_portal_page( 'layout="contained"' );

		$this->assertTrue( $layout->has_portal() );
		$this->assertFalse( $layout->should_suppress_footer() );
		$this->assertNotContains( WCP_Page_Layout::BODY_CLASS_STANDALONE, $layout->body_class( array() ) );
	}

	public function test_an_ordinary_page_is_left_completely_alone() {
		$layout = $this->visit_ordinary_page();

		$this->assertFalse( $layout->has_portal() );
		$this->assertFalse( $layout->should_suppress_footer() );
		$this->assertSame( array(), $layout->body_class( array() ) );
		$this->assertNull(
			$layout->filter_footer_block( null, $this->footer_part() ),
			'A footer template part on any other page renders exactly as the theme wrote it.'
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Block-theme suppression
	|--------------------------------------------------------------------------
	*/

	public function test_the_footer_template_part_is_dropped_on_the_portal_page() {
		$layout = $this->visit_portal_page();

		$this->assertSame( '', $layout->filter_footer_block( null, $this->footer_part() ) );
	}

	public function test_a_footer_part_without_a_stated_area_is_still_recognised_by_slug() {
		$layout = $this->visit_portal_page();

		$this->assertSame(
			'',
			$layout->filter_footer_block( null, $this->footer_part( array( 'slug' => 'footer' ) ) )
		);
	}

	public function test_no_other_template_part_is_touched() {
		$layout = $this->visit_portal_page();

		$parts = array(
			array(
				'slug' => 'header',
				'area' => 'header',
			),
			array(
				'slug' => 'sidebar',
				'area' => 'uncategorized',
			),
			array( 'slug' => 'footer-newsletter' ),
		);

		foreach ( $parts as $attrs ) {
			$this->assertNull(
				$layout->filter_footer_block( null, $this->footer_part( $attrs ) ),
				'Only the footer area is suppressed: ' . wp_json_encode( $attrs )
			);
		}
	}

	public function test_no_other_block_is_touched() {
		$layout = $this->visit_portal_page();

		$this->assertNull(
			$layout->filter_footer_block(
				null,
				array(
					'blockName' => 'core/group',
					'attrs'     => array( 'area' => 'footer' ),
				)
			)
		);
		$this->assertNull( $layout->filter_footer_block( null, array( 'blockName' => null ) ) );
		$this->assertNull( $layout->filter_footer_block( null, array() ) );
	}

	public function test_another_filters_decision_is_respected() {
		$layout = $this->visit_portal_page();

		$this->assertSame(
			'<footer>replaced</footer>',
			$layout->filter_footer_block( '<footer>replaced</footer>', $this->footer_part() ),
			'A short-circuit already in place is never overwritten.'
		);
	}

	public function test_a_site_can_keep_its_theme_footer_through_the_filter() {
		add_filter( 'wcp_suppress_theme_footer', '__return_false' );

		$layout = $this->visit_portal_page();

		$this->assertTrue( $layout->should_suppress_title(), 'The title decision is independent.' );
		$this->assertFalse( $layout->should_suppress_footer() );
		$this->assertNull( $layout->filter_footer_block( null, $this->footer_part() ) );
		$this->assertNotContains( WCP_Page_Layout::BODY_CLASS_STANDALONE, $layout->body_class( array() ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Fallback stylesheet
	|--------------------------------------------------------------------------
	*/

	public function test_the_fallback_stylesheet_stays_scoped_to_the_portals_own_page() {
		wp_register_style( WCP_Public::STYLE_HANDLE, false, array(), '1.0.0' );

		$layout = $this->visit_portal_page();
		$layout->print_chrome_fallback();

		$css = wp_styles()->get_data( WCP_Public::STYLE_HANDLE, 'after' );
		$css = is_array( $css ) ? implode( "\n", $css ) : (string) $css;

		$this->assertNotSame( '', $css, 'The portal page gets a fallback stylesheet.' );

		foreach ( array_filter( array_map( 'trim', explode( ',', str_replace( '{', ',', $css ) ) ) ) as $selector ) {
			if ( '' === $selector || false !== strpos( $selector, 'display' ) || '}' === $selector ) {
				continue;
			}

			$this->assertStringStartsWith(
				'.wcp-portal-',
				$selector,
				'Every selector is scoped to a portal-page body class: ' . $selector
			);
		}

		$this->assertStringNotContainsString(
			' footer {',
			$css,
			'The portal prints a <footer> of its own; a bare footer selector would hide it.'
		);
		$this->assertStringContainsString( ':not(.wcp-footer)', $css );
	}

	public function test_no_fallback_stylesheet_reaches_an_ordinary_page() {
		wp_register_style( WCP_Public::STYLE_HANDLE, false, array(), '1.0.0' );

		$layout = $this->visit_ordinary_page();
		$layout->print_chrome_fallback();

		$this->assertEmpty( wp_styles()->get_data( WCP_Public::STYLE_HANDLE, 'after' ) );
	}

	public function test_the_footer_selector_list_is_filterable() {
		wp_register_style( WCP_Public::STYLE_HANDLE, false, array(), '1.0.0' );

		add_filter(
			'wcp_theme_footer_selectors',
			static function () {
				return array( '.wcp-portal-standalone .my-theme-ending' );
			}
		);

		$layout = $this->visit_portal_page();
		$layout->print_chrome_fallback();

		$css = wp_styles()->get_data( WCP_Public::STYLE_HANDLE, 'after' );
		$css = is_array( $css ) ? implode( "\n", $css ) : (string) $css;

		$this->assertStringContainsString( '.wcp-portal-standalone .my-theme-ending', $css );
		$this->assertStringNotContainsString( '#colophon', $css );
	}
}
