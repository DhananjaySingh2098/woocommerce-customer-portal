<?php
/**
 * Settings screen under WooCommerce → Customer Portal.
 *
 * Uses the WordPress Settings API, which brings the parts that are easy to get
 * subtly wrong for free: the nonce, the referer check, the capability gate on
 * `options.php`, and the redirect-with-notice after saving.
 *
 * Two capability checks, not one. `add_submenu_page()` decides who sees the
 * menu entry, and the render callback checks again before printing anything --
 * because a menu that is merely hidden is not an authorisation boundary, and
 * the screen is reachable by URL. `register_setting()` adds a third on write.
 *
 * The screen deliberately looks like WordPress rather than like the portal. An
 * administrator is in wp-admin; a settings page that ignores the surrounding
 * furniture reads as a plugin that thinks it is the whole site.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin's settings screen.
 */
class WCP_Settings_Page {

	/**
	 * Menu slug.
	 */
	const SLUG = 'wcp-settings';

	/**
	 * Register the submenu entry.
	 *
	 * @return void
	 */
	public function register_menu() {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';

		add_submenu_page(
			$parent,
			__( 'Customer Portal', 'woocommerce-customer-portal' ),
			__( 'Customer Portal', 'woocommerce-customer-portal' ),
			WCP_Settings::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the setting and its sanitiser.
	 *
	 * One option, one sanitiser. `WCP_Settings::sanitize()` handles every key
	 * explicitly and discards anything it does not recognise, so no crafted
	 * POST can add keys to the stored array.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			WCP_Settings::GROUP,
			WCP_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WCP_Settings', 'sanitize' ),
				'default'           => WCP_Settings::defaults(),
				// Never exposed through the REST options endpoint; these are
				// read through the plugin's own accessors.
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render() {
		// The menu is already gated, but the screen is reachable by URL and a
		// hidden control is not a permission.
		if ( ! current_user_can( WCP_Settings::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have permission to manage these settings.', 'woocommerce-customer-portal' ),
				'',
				array( 'response' => 403 )
			);
		}

		WCP_Settings::flush();

		$context = array(
			'settings'  => WCP_Settings::all(),
			'sections'  => $this->section_choices(),
			'pages'     => $this->page_choices(),
			'themes'    => $this->theme_choices(),
			'visuals'   => $this->visual_choices(),
			'page_note' => $this->portal_page_note(),
			'accent'    => $this->accent_report(),
		);

		WCP_Helper::render_admin_view( 'settings-page.php', $context );
	}

	/*
	|--------------------------------------------------------------------------
	| Choices
	|--------------------------------------------------------------------------
	*/

	/**
	 * Section slugs with their labels.
	 *
	 * @return array<string,string>
	 */
	private function section_choices() {
		$navigation = new WCP_Navigation();
		$choices    = array();

		// Read the shipped definitions rather than the filtered list, or a
		// section that is currently switched off would vanish from the screen
		// that switches it back on.
		foreach ( WCP_Navigation::all_slugs() as $slug ) {
			$item = $navigation->get_item( $slug );

			$choices[ $slug ] = $item && ! empty( $item['label'] )
				? $item['label']
				: ucfirst( str_replace( '-', ' ', $slug ) );
		}

		// `get_item()` reads the filtered list, so a disabled section returns
		// nothing; fall back to a readable label for those.
		foreach ( $choices as $slug => $label ) {
			if ( '' === $label ) {
				$choices[ $slug ] = ucfirst( str_replace( '-', ' ', $slug ) );
			}
		}

		return $choices;
	}

	/**
	 * Published pages that could host the portal.
	 *
	 * @return array<int,string>
	 */
	private function page_choices() {
		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'numberposts'      => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- An admin dropdown, rendered once, capped rather than unbounded.
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$choices = array();

		foreach ( $pages as $page ) {
			$title = get_the_title( $page );
			$label = '' !== trim( $title ) ? $title : sprintf(
				/* translators: %d: page ID. */
				__( '(no title) #%d', 'woocommerce-customer-portal' ),
				$page->ID
			);

			// Flag pages that actually contain the shortcode, so an
			// administrator is not guessing which page is the portal.
			if ( has_shortcode( (string) $page->post_content, WCP_SHORTCODE_TAG ) ) {
				$label .= ' ' . __( '— has portal shortcode', 'woocommerce-customer-portal' );
			}

			$choices[ (int) $page->ID ] = $label;
		}

		return $choices;
	}

	/**
	 * Theme choices.
	 *
	 * @return array<string,string>
	 */
	private function theme_choices() {
		return array(
			'system' => __( 'Follow the visitor’s system setting', 'woocommerce-customer-portal' ),
			'light'  => __( 'Light', 'woocommerce-customer-portal' ),
			'dark'   => __( 'Dark', 'woocommerce-customer-portal' ),
		);
	}

	/**
	 * Visual theme presets with their labels.
	 *
	 * @return array<string,array{label:string,text:string}>
	 */
	private function visual_choices() {
		return array(
			'aurora'   => array(
				'label' => __( 'Aurora', 'woocommerce-customer-portal' ),
				'text'  => __( 'Violet and indigo accents on clean surfaces, with a soft blue-violet glow.', 'woocommerce-customer-portal' ),
			),
			'obsidian' => array(
				'label' => __( 'Obsidian', 'woocommerce-customer-portal' ),
				'text'  => __( 'Graphite and charcoal with restrained violet-blue highlights.', 'woocommerce-customer-portal' ),
			),
			'pearl'    => array(
				'label' => __( 'Pearl', 'woocommerce-customer-portal' ),
				'text'  => __( 'Warm near-white surfaces, neutral shadows and a quiet blue accent.', 'woocommerce-customer-portal' ),
			),
			'midnight' => array(
				'label' => __( 'Midnight', 'woocommerce-customer-portal' ),
				'text'  => __( 'Deep navy surfaces lit by a cobalt and indigo glow.', 'woocommerce-customer-portal' ),
			),
			'emerald'  => array(
				'label' => __( 'Emerald', 'woocommerce-customer-portal' ),
				'text'  => __( 'Charcoal with a forest undertone and an emerald accent.', 'woocommerce-customer-portal' ),
			),
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Diagnostics
	|--------------------------------------------------------------------------
	*/

	/**
	 * Advice about the configured portal page.
	 *
	 * Warns rather than blocks. A misconfigured page should never stop the
	 * plugin working -- shortcode detection continues regardless of what is
	 * selected here -- but an administrator should be told what is wrong.
	 *
	 * @return array{tone:string,text:string}
	 */
	private function portal_page_note() {
		$id = (int) WCP_Settings::raw( 'portal_page' );

		if ( $id <= 0 ) {
			return array(
				'tone' => 'info',
				'text' => __( 'No page selected. The portal still renders wherever the shortcode appears; selecting a page here lets the plugin warn you if that page ever stops working.', 'woocommerce-customer-portal' ),
			);
		}

		$page = WCP_Settings::portal_page();

		if ( ! $page ) {
			return array(
				'tone' => 'warning',
				'text' => __( 'The selected page no longer exists or is not published. Choose another page, or clear the selection.', 'woocommerce-customer-portal' ),
			);
		}

		if ( ! has_shortcode( (string) $page->post_content, WCP_SHORTCODE_TAG ) ) {
			return array(
				'tone' => 'warning',
				'text' => sprintf(
					/* translators: %s: the portal shortcode. */
					__( 'That page does not contain the %s shortcode, so the portal will not appear on it. Add the shortcode to the page, or select a different one.', 'woocommerce-customer-portal' ),
					'[' . WCP_SHORTCODE_TAG . ']'
				),
			);
		}

		return array(
			'tone' => 'ok',
			'text' => __( 'This page contains the portal shortcode.', 'woocommerce-customer-portal' ),
		);
	}

	/**
	 * Contrast report for the configured accent.
	 *
	 * An accent is a background for button labels, so a pale one produces
	 * unreadable buttons. The text colour is chosen automatically, but if even
	 * the better of black and white falls short of WCAG AA the administrator
	 * should hear about it before customers do.
	 *
	 * @return array{hex:string,contrast:float,passes:bool,on:string}
	 */
	private function accent_report() {
		$hex   = WCP_Settings::sanitize_accent( WCP_Settings::get( 'accent' ) );
		$rgb   = WCP_Settings::hex_to_rgb( $hex );
		$on    = WCP_Settings::readable_on( $rgb );
		$ratio = WCP_Settings::contrast( $rgb, WCP_Settings::hex_to_rgb( $on ) );

		return array(
			'hex'      => $hex,
			'on'       => $on,
			'contrast' => round( $ratio, 2 ),
			'passes'   => $ratio >= 4.5,
		);
	}
}
