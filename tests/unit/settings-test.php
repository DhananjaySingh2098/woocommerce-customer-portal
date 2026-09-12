<?php
/**
 * WCP_Settings: sanitisation, validation and derived state.
 *
 * @package WooCommerce_Customer_Portal
 */

/**
 * @group unit
 * @covers WCP_Settings
 */
class Settings_Test extends WCP_Test_Case {

	/* --- Defaults and read path ------------------------------------------ */

	public function test_fresh_install_resolves_to_enabled_with_every_section() {
		delete_option( WCP_Settings::OPTION );
		WCP_Settings::flush();

		$this->assertTrue( WCP_Settings::is_enabled(), 'A fresh install must not switch the portal off.' );
		$this->assertSame( array( 'dashboard', 'orders', 'addresses', 'profile' ), WCP_Settings::enabled_sections() );
		$this->assertSame( 'dashboard', WCP_Settings::default_section() );
		$this->assertSame( 'system', WCP_Settings::get( 'theme' ) );
		$this->assertSame( '#5b4ce0', WCP_Settings::get( 'accent' ) );
		$this->assertSame( 'aurora', WCP_Settings::visual_theme() );
		$this->assertTrue( WCP_Settings::get( 'motion' ), 'Motion is on by default.' );
		$this->assertTrue( WCP_Settings::get( 'effects_3d' ), '3D effects are on by default.' );
	}

	public function test_settings_saved_before_the_appearance_keys_existed_keep_the_new_defaults() {
		// A Phase 5 option row has no visual_theme/motion/effects_3d keys.
		update_option(
			WCP_Settings::OPTION,
			array(
				'enabled' => true,
				'accent'  => '#0f766e',
			)
		);
		WCP_Settings::flush();

		$this->assertSame( 'aurora', WCP_Settings::visual_theme() );
		$this->assertTrue( WCP_Settings::get( 'motion' ) );
		$this->assertTrue( WCP_Settings::get( 'effects_3d' ) );
		$this->assertSame( '#0f766e', WCP_Settings::get( 'accent' ), 'Existing keys are untouched.' );
	}

	public function test_explicitly_saved_disabled_state_wins_over_default() {
		update_option( WCP_Settings::OPTION, array( 'enabled' => false ) );
		WCP_Settings::flush();

		$this->assertFalse( WCP_Settings::is_enabled() );
	}

	public function test_garbage_in_the_option_row_is_repaired_on_read() {
		update_option( WCP_Settings::OPTION, 'not-an-array' );
		WCP_Settings::flush();

		$this->assertTrue( WCP_Settings::is_enabled() );
		$this->assertSame( WCP_Settings::defaults()['sections'], WCP_Settings::get( 'sections' ) );
	}

	/* --- sanitize(): shape ------------------------------------------------ */

	public function test_unknown_keys_are_discarded() {
		$clean = WCP_Settings::sanitize(
			array(
				'enabled'  => '1',
				'evil'     => 'payload',
				'sections' => array( 'orders' ),
			)
		);

		$this->assertArrayNotHasKey( 'evil', $clean );
		$this->assertSame( array( 'enabled', 'portal_page', 'sections', 'default_section', 'accent', 'theme', 'visual_theme', 'motion', 'effects_3d' ), array_keys( $clean ) );
	}

	public function test_non_array_input_returns_defaults() {
		$this->assertSame( WCP_Settings::defaults(), WCP_Settings::sanitize( 'x' ) );
		$this->assertSame( WCP_Settings::defaults(), WCP_Settings::sanitize( null ) );
	}

	/* --- sections --------------------------------------------------------- */

	public function test_only_known_sections_survive() {
		$clean = WCP_Settings::sanitize( array( 'sections' => array( 'orders', 'admin', '../etc', 'profile', 'orders' ) ) );

		$this->assertSame( array( 'orders', 'profile' ), $clean['sections'], 'Unknown slugs dropped, duplicates collapsed, navigation order kept.' );
	}

	public function test_empty_section_selection_restores_all() {
		$clean = WCP_Settings::sanitize( array( 'sections' => array() ) );

		$this->assertSame( array( 'dashboard', 'orders', 'addresses', 'profile' ), $clean['sections'] );

		$clean = WCP_Settings::sanitize( array( 'sections' => array( 'bogus' ) ) );

		$this->assertSame( array( 'dashboard', 'orders', 'addresses', 'profile' ), $clean['sections'], 'All-invalid is treated as empty.' );
	}

	public function test_section_values_must_be_scalar() {
		$clean = WCP_Settings::sanitize( array( 'sections' => array( array( 'orders' ), 'profile' ) ) );

		$this->assertSame( array( 'profile' ), $clean['sections'] );
	}

	/* --- default section -------------------------------------------------- */

	public function test_default_section_must_be_enabled() {
		$clean = WCP_Settings::sanitize(
			array(
				'sections'        => array( 'orders', 'profile' ),
				'default_section' => 'addresses',
			)
		);

		$this->assertSame( 'orders', $clean['default_section'], 'Falls to the first enabled when Dashboard is also off.' );
	}

	public function test_default_section_prefers_dashboard_when_enabled() {
		$clean = WCP_Settings::sanitize(
			array(
				'sections'        => array( 'dashboard', 'orders' ),
				'default_section' => 'profile',
			)
		);

		$this->assertSame( 'dashboard', $clean['default_section'] );
	}

	public function test_default_section_kept_when_valid() {
		$clean = WCP_Settings::sanitize(
			array(
				'sections'        => array( 'dashboard', 'orders' ),
				'default_section' => 'orders',
			)
		);

		$this->assertSame( 'orders', $clean['default_section'] );
	}

	public function test_runtime_default_section_never_returns_a_disabled_one() {
		// Simulate a row saved before Orders was disabled.
		update_option(
			WCP_Settings::OPTION,
			array(
				'sections'        => array( 'addresses', 'profile' ),
				'default_section' => 'orders',
			)
		);
		WCP_Settings::flush();

		$this->assertSame( 'addresses', WCP_Settings::default_section() );
		$this->assertFalse( WCP_Settings::section_enabled( 'orders' ) );
		$this->assertTrue( WCP_Settings::section_enabled( 'profile' ) );
	}

	/* --- accent ----------------------------------------------------------- */

	/**
	 * @dataProvider accent_provider
	 */
	public function test_accent_sanitisation( $input, $expected ) {
		$this->assertSame( $expected, WCP_Settings::sanitize_accent( $input ) );
	}

	public function accent_provider() {
		return array(
			'valid 6-digit'         => array( '#0f766e', '#0f766e' ),
			'valid 3-digit'         => array( '#abc', '#abc' ),
			'missing hash added'    => array( '0f766e', '#0f766e' ),
			'uppercase kept'        => array( '#ABCDEF', '#ABCDEF' ),
			'css injection'         => array( 'red; } body { display:none } .x{', '#5b4ce0' ),
			'expression'            => array( 'expression(alert(1))', '#5b4ce0' ),
			'url'                   => array( 'url(javascript:alert(1))', '#5b4ce0' ),
			'named colour rejected' => array( 'red', '#5b4ce0' ),
			'rgb() rejected'        => array( 'rgb(1,2,3)', '#5b4ce0' ),
			'too long'              => array( '#1234567', '#5b4ce0' ),
			'empty'                 => array( '', '#5b4ce0' ),
			'array'                 => array( array( '#000' ), '#5b4ce0' ),
			'whitespace trimmed'    => array( '  #123456 ', '#123456' ),
		);
	}

	public function test_accent_palette_derives_every_token_from_a_validated_hex() {
		$this->save_settings( array( 'accent' => '#0f766e' ) );

		$palette = WCP_Settings::accent_palette();

		$this->assertSame( '#0f766e', $palette['--wcp-primary'] );
		$this->assertSame( '15, 118, 110', $palette['--wcp-primary-rgb'] );

		foreach ( $palette as $property => $value ) {
			$this->assertMatchesRegularExpression( '/^--wcp-[a-z-]+$/', $property );
			$this->assertMatchesRegularExpression( '/^(#[0-9a-f]{6}|\d{1,3}, \d{1,3}, \d{1,3})$/', $value, "$property must be a hex or rgb triplet" );
		}
	}

	public function test_dark_palette_is_lifted_and_blends_towards_the_dark_canvas() {
		$this->save_settings( array( 'accent' => '#0f766e' ) );

		$light = WCP_Settings::accent_palette( false );
		$dark  = WCP_Settings::accent_palette( true );

		$this->assertNotSame( $light['--wcp-primary'], $dark['--wcp-primary'] );

		$lr = WCP_Settings::hex_to_rgb( $light['--wcp-primary'] );
		$dr = WCP_Settings::hex_to_rgb( $dark['--wcp-primary'] );

		$this->assertGreaterThan( array_sum( $lr ), array_sum( $dr ), 'Dark variant is lighter than the light one.' );

		$soft = WCP_Settings::hex_to_rgb( $dark['--wcp-primary-soft'] );
		$this->assertLessThan( 120, array_sum( $soft ) / 3, 'Dark soft background stays dark.' );
	}

	public function test_readable_text_colour_is_chosen_by_contrast() {
		$this->assertSame( '#ffffff', WCP_Settings::readable_on( array( 15, 118, 110 ) ), 'Dark teal takes white text.' );
		$this->assertSame( '#10111c', WCP_Settings::readable_on( array( 250, 230, 120 ) ), 'Pale yellow takes dark text.' );
		$this->assertSame( '#ffffff', WCP_Settings::readable_on( array( 0, 0, 0 ) ) );
		$this->assertSame( '#10111c', WCP_Settings::readable_on( array( 255, 255, 255 ) ) );
	}

	public function test_contrast_ratio_matches_wcag_reference_values() {
		$this->assertEqualsWithDelta( 21.0, WCP_Settings::contrast( array( 0, 0, 0 ), array( 255, 255, 255 ) ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, WCP_Settings::contrast( array( 128, 128, 128 ), array( 128, 128, 128 ) ), 0.001 );
	}

	/* --- visual theme, motion, 3D ------------------------------------------ */

	/**
	 * @dataProvider visual_theme_provider
	 */
	public function test_visual_theme_is_restricted_to_the_registered_presets( $input, $expected ) {
		$clean = WCP_Settings::sanitize( array( 'visual_theme' => $input ) );

		$this->assertSame( $expected, $clean['visual_theme'] );
	}

	public function visual_theme_provider() {
		return array(
			array( 'aurora', 'aurora' ),
			array( 'obsidian', 'obsidian' ),
			array( 'pearl', 'pearl' ),
			array( 'midnight', 'midnight' ),
			array( 'emerald', 'emerald' ),
			array( 'OBSIDIAN', 'obsidian' ),
			array( 'MIDNIGHT', 'midnight' ),
			array( 'neon', 'aurora' ),
			array( '<script>', 'aurora' ),
			array( 'pearl-dark', 'aurora' ),
			array( 'emerald2', 'aurora' ),
			array( array( 'pearl' ), 'aurora' ),
			array( '', 'aurora' ),
		);
	}

	public function test_motion_and_3d_are_booleans_from_checkboxes() {
		$on = WCP_Settings::sanitize(
			array(
				'motion'     => '1',
				'effects_3d' => 'yes',
			)
		);

		$this->assertTrue( $on['motion'] );
		$this->assertTrue( $on['effects_3d'] );

		// A submitted form omits unchecked boxes entirely.
		$off = WCP_Settings::sanitize( array( 'enabled' => '1' ) );

		$this->assertFalse( $off['motion'] );
		$this->assertFalse( $off['effects_3d'] );
	}

	public function test_visual_theme_reaches_the_head_script_and_root_attributes() {
		$this->save_settings(
			array(
				'visual_theme' => 'pearl',
				'motion'       => false,
				'effects_3d'   => false,
			)
		);

		$this->assertSame( 'pearl', WCP_Theme::admin_visual_default() );

		$config = WCP_Theme::get_config();

		$this->assertSame( 'pearl', $config['visualDefault'] );
		$this->assertFalse( $config['motion'] );
		$this->assertFalse( $config['effects3d'] );

		$flags = WCP_Shortcodes::appearance_flags();

		$this->assertSame( 'pearl', $flags['visual'] );
		$this->assertFalse( $flags['motion'] );
		$this->assertFalse( $flags['effects_3d'] );
	}

	/* --- theme ------------------------------------------------------------ */

	/**
	 * @dataProvider theme_provider
	 */
	public function test_theme_is_restricted_to_three_values( $input, $expected ) {
		$clean = WCP_Settings::sanitize( array( 'theme' => $input ) );

		$this->assertSame( $expected, $clean['theme'] );
	}

	public function theme_provider() {
		return array(
			array( 'dark', 'dark' ),
			array( 'light', 'light' ),
			array( 'system', 'system' ),
			array( 'DARK', 'dark' ),
			array( '<script>alert(1)</script>', 'system' ),
			array( 'auto', 'system' ),
			array( '', 'system' ),
			array( array( 'dark' ), 'system' ),
		);
	}

	/* --- portal page ------------------------------------------------------ */

	public function test_portal_page_must_be_a_published_page() {
		$page  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		$post  = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( $page, WCP_Settings::sanitize( array( 'portal_page' => $page ) )['portal_page'] );
		$this->assertSame( 0, WCP_Settings::sanitize( array( 'portal_page' => $draft ) )['portal_page'], 'Draft rejected.' );
		$this->assertSame( 0, WCP_Settings::sanitize( array( 'portal_page' => $post ) )['portal_page'], 'Post (not page) rejected.' );
		$this->assertSame( 0, WCP_Settings::sanitize( array( 'portal_page' => 999999 ) )['portal_page'], 'Nonexistent rejected.' );
		$this->assertSame( 0, WCP_Settings::sanitize( array( 'portal_page' => '-5' ) )['portal_page'] );
	}

	public function test_portal_page_accessor_returns_null_once_unpublished() {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$this->save_settings( array( 'portal_page' => $page ) );

		$this->assertInstanceOf( 'WP_Post', WCP_Settings::portal_page() );

		wp_update_post(
			array(
				'ID'          => $page,
				'post_status' => 'draft',
			)
		);
		WCP_Settings::flush();

		$this->assertNull( WCP_Settings::portal_page() );
	}
}
