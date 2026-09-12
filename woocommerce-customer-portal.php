<?php
/**
 * WooCommerce Customer Portal
 *
 * A premium, self-contained customer portal for WooCommerce stores. Renders a
 * polished application shell (sidebar, header, dashboard) on any page via the
 * [wcp_customer_portal] shortcode, using the current authenticated WordPress
 * user as the single source of identity.
 *
 * @package   WooCommerce_Customer_Portal
 * @author    Dhananjay Singh
 * @license   GPL-2.0-or-later
 * @link      https://github.com/dhananjaysingh/woocommerce-customer-portal
 *
 * @wordpress-plugin
 * Plugin Name:          WooCommerce Customer Portal
 * Plugin URI:           https://github.com/dhananjaysingh/woocommerce-customer-portal
 * Description:          A premium customer portal for WooCommerce — orders, addresses and account management in one polished, responsive interface. Add it to any page with the <code>[wcp_customer_portal]</code> shortcode.
 * Version:              1.0.0
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Author:               Dhananjay Singh
 * Author URI:           https://github.com/dhananjaysingh
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          woocommerce-customer-portal
 * Domain Path:          /languages
 * Requires Plugins:     woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      11.1
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
|
| Every constant is guarded so that a second copy of the plugin (or a test
| bootstrap that predefines paths) can never trigger a fatal redeclaration.
|
*/

if ( ! defined( 'WCP_VERSION' ) ) {
	define( 'WCP_VERSION', '1.0.0' );
}

if ( ! defined( 'WCP_PLUGIN_FILE' ) ) {
	define( 'WCP_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WCP_PLUGIN_DIR' ) ) {
	define( 'WCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WCP_PLUGIN_URL' ) ) {
	define( 'WCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WCP_PLUGIN_BASENAME' ) ) {
	define( 'WCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'WCP_SHORTCODE_TAG' ) ) {
	define( 'WCP_SHORTCODE_TAG', 'wcp_customer_portal' );
}

if ( ! defined( 'WCP_MIN_PHP_VERSION' ) ) {
	define( 'WCP_MIN_PHP_VERSION', '7.4' );
}

if ( ! defined( 'WCP_MIN_WP_VERSION' ) ) {
	define( 'WCP_MIN_WP_VERSION', '6.0' );
}

if ( ! defined( 'WCP_MIN_WC_VERSION' ) ) {
	define( 'WCP_MIN_WC_VERSION', '7.0' );
}

/*
|--------------------------------------------------------------------------
| Lifecycle hooks
|--------------------------------------------------------------------------
|
| Activation and deactivation callbacks are registered before anything else is
| loaded, and each pulls in only the class it needs. This keeps activation
| cheap and avoids booting the whole plugin during a lifecycle request.
|
*/

/**
 * Run activation checks and first-install bookkeeping.
 *
 * @return void
 */
function wcp_activate_plugin() {
	require_once WCP_PLUGIN_DIR . 'includes/class-wcp-activator.php';
	WCP_Activator::activate();
}
register_activation_hook( __FILE__, 'wcp_activate_plugin' );

/**
 * Clean up transient state on deactivation.
 *
 * @return void
 */
function wcp_deactivate_plugin() {
	require_once WCP_PLUGIN_DIR . 'includes/class-wcp-deactivator.php';
	WCP_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'wcp_deactivate_plugin' );

/*
|--------------------------------------------------------------------------
| Environment guards
|--------------------------------------------------------------------------
|
| The plugin refuses to boot — quietly, without a fatal error — when the host
| environment cannot support it. Each guard surfaces an admin notice instead.
|
*/

/**
 * Determine whether the host environment satisfies the minimum requirements.
 *
 * Returns a reason *code* rather than a translated string: this runs on
 * `plugins_loaded`, and building a translated message that early triggers
 * WordPress 6.7's just-in-time textdomain notice. The message is composed at
 * render time instead, by {@see WCP_Helper::notice_message()}.
 *
 * @return true|array True when supported, otherwise a reason descriptor.
 */
function wcp_check_environment() {
	if ( version_compare( PHP_VERSION, WCP_MIN_PHP_VERSION, '<' ) ) {
		return array(
			'code'     => 'php_version',
			'required' => WCP_MIN_PHP_VERSION,
			'current'  => PHP_VERSION,
		);
	}

	$wp_version = get_bloginfo( 'version' );

	if ( version_compare( $wp_version, WCP_MIN_WP_VERSION, '<' ) ) {
		return array(
			'code'     => 'wp_version',
			'required' => WCP_MIN_WP_VERSION,
			'current'  => $wp_version,
		);
	}

	return true;
}

/**
 * Determine whether an active, supported WooCommerce installation is present.
 *
 * @return true|array True when available, otherwise a reason descriptor.
 */
function wcp_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array( 'code' => 'wc_missing' );
	}

	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WCP_MIN_WC_VERSION, '<' ) ) {
		return array(
			'code'     => 'wc_version',
			'required' => WCP_MIN_WC_VERSION,
			'current'  => WC_VERSION,
		);
	}

	return true;
}

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

/**
 * Declare compatibility with WooCommerce opt-in features.
 *
 * Runs on `before_woocommerce_init` so the declaration lands before
 * WooCommerce evaluates plugin compatibility for High-Performance Order
 * Storage. Registered unconditionally because the hook only fires when
 * WooCommerce is present.
 *
 * @return void
 */
function wcp_declare_woocommerce_compatibility() {
	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCP_PLUGIN_FILE, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WCP_PLUGIN_FILE, true );
}
add_action( 'before_woocommerce_init', 'wcp_declare_woocommerce_compatibility' );

/**
 * Boot the plugin once all other plugins have loaded.
 *
 * Priority 20 guarantees WooCommerce (priority 10) has finished registering
 * its classes before the dependency check runs.
 *
 * @return void
 */
function wcp_bootstrap() {
	require_once WCP_PLUGIN_DIR . 'includes/class-wcp-helper.php';

	$environment = wcp_check_environment();
	$woocommerce = wcp_check_woocommerce();

	// A hard environment failure disables everything except the notice.
	if ( true !== $environment ) {
		WCP_Helper::add_blocking_notice( $environment );
		return;
	}

	require_once WCP_PLUGIN_DIR . 'includes/class-wcp-loader.php';

	if ( true !== $woocommerce ) {
		// Degrade gracefully: admin surface only, no portal, no front-end assets.
		WCP_Helper::add_blocking_notice( $woocommerce );
		WCP_Loader::instance()->run_without_woocommerce();
		return;
	}

	WCP_Loader::instance()->run();
}
add_action( 'plugins_loaded', 'wcp_bootstrap', 20 );

/**
 * Load the plugin text domain.
 *
 * @return void
 */
function wcp_load_textdomain() {
	load_plugin_textdomain( 'woocommerce-customer-portal', false, dirname( WCP_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'init', 'wcp_load_textdomain' );
