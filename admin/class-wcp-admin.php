<?php
/**
 * Admin-side surface.
 *
 * The admin footprint is deliberately small: plugin-list context, dependency
 * notices, asset loading for the settings screen and the portal-page health
 * notice. The settings screen itself lives in `WCP_Settings_Page` with its
 * markup under `admin/views/`.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires up admin assets and plugin-list metadata.
 */
class WCP_Admin {

	/**
	 * Stylesheet handle.
	 */
	const STYLE_HANDLE = 'wcp-admin';

	/**
	 * Script handle.
	 */
	const SCRIPT_HANDLE = 'wcp-admin';

	/**
	 * Enqueue admin assets on screens that need them.
	 *
	 * @param string $hook_suffix Current admin screen.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! $this->screen_needs_assets( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			WCP_Helper::asset_url( 'css/admin.css' ),
			array(),
			WCP_Helper::asset_version( 'css/admin.css' )
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WCP_Helper::asset_url( 'js/admin.js' ),
			array(),
			WCP_Helper::asset_version( 'js/admin.js' ),
			true
		);
	}

	/**
	 * Whether the current admin screen should load plugin assets.
	 *
	 * @param string $hook_suffix Current admin screen.
	 * @return bool
	 */
	private function screen_needs_assets( $hook_suffix ) {
		$screens = array( 'plugins.php' );

		// The settings screen's hook suffix depends on which menu it hangs
		// from, so it is matched by slug rather than listed literally.
		if ( class_exists( 'WCP_Settings_Page' ) && false !== strpos( (string) $hook_suffix, WCP_Settings_Page::SLUG ) ) {
			$screens[] = $hook_suffix;
		}

		/**
		 * Filter the admin screens that load plugin assets.
		 *
		 * @param string[] $screens     Admin hook suffixes.
		 * @param string   $hook_suffix Current screen.
		 */
		$screens = (array) apply_filters( 'wcp_admin_asset_screens', $screens, $hook_suffix );

		return in_array( $hook_suffix, $screens, true );
	}

	/**
	 * Add contextual links to the plugin's row action list.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$links = (array) $links;

		$docs = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( 'https://github.com/dhananjaysingh/woocommerce-customer-portal#readme' ),
			esc_html__( 'Documentation', 'woocommerce-customer-portal' )
		);

		array_unshift( $links, $docs );

		// The settings link is only offered to someone who could use it.
		if ( class_exists( 'WCP_Settings' ) && current_user_can( WCP_Settings::CAPABILITY ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( self::settings_url() ),
					esc_html__( 'Settings', 'woocommerce-customer-portal' )
				)
			);
		}

		return $links;
	}

	/**
	 * URL of the settings screen.
	 *
	 * @return string
	 */
	public static function settings_url() {
		$parent = class_exists( 'WooCommerce' ) ? 'admin.php' : 'options-general.php';

		return add_query_arg( 'page', WCP_Settings_Page::SLUG, admin_url( $parent ) );
	}

	/**
	 * Warn when the configured portal page has stopped being usable.
	 *
	 * Only when a page *was* configured and is now invalid. With nothing
	 * configured the plugin relies on shortcode detection and there is nothing
	 * to warn about -- an unsolicited notice on every admin screen is how
	 * plugins get deactivated.
	 *
	 * @return void
	 */
	public function portal_page_notice() {
		if ( ! class_exists( 'WCP_Settings' ) || ! current_user_can( WCP_Settings::CAPABILITY ) ) {
			return;
		}

		// The raw value, deliberately: the validated view has already replaced
		// a dead page ID with zero, which is exactly the case this warns about.
		$id = (int) WCP_Settings::raw( 'portal_page' );

		if ( $id <= 0 || WCP_Settings::portal_page() ) {
			return;
		}

		// Not on the settings screen itself, which already shows the note.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && false !== strpos( (string) $screen->id, WCP_Settings_Page::SLUG ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Customer Portal:', 'woocommerce-customer-portal' ),
			esc_html__( 'the selected portal page no longer exists or is not published.', 'woocommerce-customer-portal' ),
			esc_url( self::settings_url() ),
			esc_html__( 'Choose a page', 'woocommerce-customer-portal' )
		);
	}

	/**
	 * Add a shortcode hint beneath the plugin description.
	 *
	 * @param array  $meta        Existing row meta.
	 * @param string $plugin_file Plugin file being rendered.
	 * @return array
	 */
	public function plugin_row_meta( $meta, $plugin_file ) {
		if ( WCP_PLUGIN_BASENAME !== $plugin_file ) {
			return $meta;
		}

		// The click-to-copy labels travel as attributes so that the script
		// and the stylesheet never carry untranslatable text of their own.
		$meta   = (array) $meta;
		$meta[] = sprintf(
			'<span class="wcp-row-meta">%1$s <code title="%3$s" data-wcp-copied="%4$s">[%2$s]</code></span>',
			esc_html__( 'Add the portal to any page with', 'woocommerce-customer-portal' ),
			esc_html( WCP_SHORTCODE_TAG ),
			esc_attr__( 'Copy shortcode', 'woocommerce-customer-portal' ),
			esc_attr__( 'Copied', 'woocommerce-customer-portal' )
		);

		return $meta;
	}
}
