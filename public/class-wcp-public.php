<?php
/**
 * Front-end asset handling.
 *
 * Assets are registered on every front-end request but enqueued only where the
 * portal actually renders. Detection runs twice: a content scan during
 * `wp_enqueue_scripts` (so styles land in <head> with no flash of unstyled
 * content), and a late fallback from the shortcode itself for content that a
 * scan cannot see — widgets, blocks and template-part shortcodes.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and conditionally enqueues portal assets.
 */
class WCP_Public {

	/**
	 * Stylesheet handle.
	 */
	const STYLE_HANDLE = 'wcp-portal';

	/**
	 * Script handle.
	 */
	const SCRIPT_HANDLE = 'wcp-portal';

	/**
	 * Whether assets have already been enqueued this request.
	 *
	 * @var bool
	 */
	private $enqueued = false;

	/**
	 * Page-level layout negotiation.
	 *
	 * @var WCP_Page_Layout
	 */
	private $page_layout;

	/**
	 * Constructor.
	 *
	 * @param WCP_Page_Layout $page_layout Page framing controller.
	 */
	public function __construct( WCP_Page_Layout $page_layout ) {
		$this->page_layout = $page_layout;
	}

	/**
	 * Register handles without enqueuing them.
	 *
	 * @return void
	 */
	public function register_assets() {
		// Idempotent: `enqueue_assets()` calls this too, because a block theme
		// can render the shortcode before `wp_enqueue_scripts` has fired and
		// registration cannot be assumed to have happened yet.
		if ( wp_style_is( self::STYLE_HANDLE, 'registered' ) && wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_style(
			self::STYLE_HANDLE,
			WCP_Helper::asset_url( 'css/portal.css' ),
			array(),
			WCP_Helper::asset_version( 'css/portal.css' )
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			WCP_Helper::asset_url( 'js/portal.js' ),
			array(),
			WCP_Helper::asset_version( 'js/portal.js' ),
			true
		);
	}

	/**
	 * Enqueue assets when the current page renders the portal.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if ( ! $this->current_page_has_portal() ) {
			return;
		}

		// A disabled portal renders nothing, so it should cost nothing.
		if ( class_exists( 'WCP_Settings' ) && ! WCP_Settings::is_enabled() ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Print the administrator's accent as CSS custom properties.
	 *
	 * Only when the accent has been changed from the shipped default -- the
	 * stylesheet already carries the default, so repeating it would be noise.
	 *
	 * Every value is produced by `WCP_Settings::accent_palette()` from a hex
	 * that has passed `sanitize_hex_color()`, and is re-checked here against a
	 * strict pattern before it is written into a stylesheet. Nothing typed by
	 * an administrator reaches CSS as a string; only colours the plugin
	 * derived from a colour it validated.
	 *
	 * Dark mode gets a lightened variant, for the same reason the shipped dark
	 * accent is lighter than the light one: a saturated colour that reads on
	 * white sinks into a dark surface.
	 *
	 * @return void
	 */
	public function print_accent_styles() {
		if ( ! $this->enqueued || ! class_exists( 'WCP_Settings' ) || ! WCP_Settings::accent_is_custom() ) {
			return;
		}

		$light = WCP_Settings::accent_palette();
		$dark  = WCP_Settings::accent_palette( true );

		$css = '.wcp-portal{' . $this->declarations( $light ) . '}'
			. "\n" . '[data-wcp-theme="dark"] .wcp-portal{' . $this->declarations( $dark ) . '}';

		wp_add_inline_style( self::STYLE_HANDLE, $css );
	}

	/**
	 * Serialise a validated palette to CSS declarations.
	 *
	 * @param array $palette Property => value.
	 * @return string
	 */
	private function declarations( array $palette ) {
		$out = '';

		foreach ( $palette as $property => $value ) {
			// Property names are the plugin's own constants; values are either a
			// `#rrggbb` hex or an `r, g, b` integer triplet. Anything else is
			// dropped rather than printed.
			if ( ! preg_match( '/^--wcp-[a-z-]+$/', $property ) ) {
				continue;
			}

			if ( ! preg_match( '/^(#[0-9a-f]{6}|\d{1,3}, \d{1,3}, \d{1,3})$/i', (string) $value ) ) {
				continue;
			}

			$out .= $property . ':' . $value . ';';
		}

		return $out;
	}

	/**
	 * Enqueue portal assets. Idempotent and safe to call from a shortcode.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( $this->enqueued ) {
			return;
		}

		$this->enqueued = true;

		// Block themes render post content early -- before `wp_enqueue_scripts`
		// has fired -- so the shortcode's late enqueue can arrive first. Without
		// this, `wp_enqueue_script()` would queue a handle that does not exist
		// yet (which still resolves once registration happens, so the file
		// loads) while `wp_localize_script()` silently returns false, and the
		// script's configuration would never reach the page.
		$this->register_assets();

		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );

		wp_localize_script( self::SCRIPT_HANDLE, 'wcpPortalConfig', $this->get_script_config() );
	}

	/**
	 * Whether assets were enqueued during this request.
	 *
	 * @return bool
	 */
	public function is_enqueued() {
		return $this->enqueued;
	}

	/**
	 * Data handed to the front-end script.
	 *
	 * Carries no customer data. The nonce is only issued to authenticated
	 * sessions so that a cached logged-out page can never bake one in.
	 *
	 * @return array
	 */
	private function get_script_config() {
		$logged_in = WCP_Auth::is_logged_in();

		return array(
			'queryVar'    => WCP_Navigation::QUERY_VAR,
			'nonce'       => $logged_in ? WCP_Security::create_nonce() : '',
			// WordPress will not resolve a cookie into a logged-in user for a
			// REST request without this, so a client that wants the orders API
			// needs it. Issued only to authenticated sessions, so a cached
			// logged-out page can never bake one in.
			'restUrl'     => esc_url_raw( rest_url( WCP_REST_Orders::NAMESPACE_V1 . '/' ) ),
			'restNonce'   => $logged_in ? wp_create_nonce( 'wp_rest' ) : '',
			'mobileBreak' => 1024,
			'theme'       => WCP_Theme::get_config(),
			'sections'    => WCP_Navigation::QUERY_VAR,
			'i18n'        => array(
				'openMenu'          => __( 'Open navigation menu', 'woocommerce-customer-portal' ),
				'closeMenu'         => __( 'Close navigation menu', 'woocommerce-customer-portal' ),
				'networkError'      => __( 'Could not reach the server. Check your connection and try again.', 'woocommerce-customer-portal' ),
				'sessionExpired'    => __( 'Your session has expired. Refresh the page and try again.', 'woocommerce-customer-portal' ),
				'selectPlaceholder' => __( 'Select…', 'woocommerce-customer-portal' ),
			),
		);
	}

	/**
	 * Detect the portal on the queried page.
	 *
	 * Delegated so that asset loading and page framing can never disagree
	 * about whether this page is the portal.
	 *
	 * @return bool
	 */
	private function current_page_has_portal() {
		return $this->page_layout->has_portal();
	}
}
