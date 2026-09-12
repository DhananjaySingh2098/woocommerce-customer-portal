<?php
/**
 * PHPUnit bootstrap.
 *
 * Boots the WordPress test framework (via wp-phpunit), installs WooCommerce
 * into the test database, then loads this plugin -- in that order, because the
 * plugin's own bootstrap checks for WooCommerce on `plugins_loaded` and
 * degrades without it.
 *
 * Configuration comes from the environment so the same file serves a laptop
 * and CI:
 *
 *   WP_CORE_DIR       WordPress core (ABSPATH). Default: /var/www/html
 *   WC_PLUGIN_DIR     WooCommerce plugin directory. Default: {core}/wp-content/plugins/woocommerce
 *   WP_TESTS_DB_*     Test database. Never point this at a real site: the
 *                     framework truncates it on every run.
 *
 * @package WooCommerce_Customer_Portal
 */

$wcp_root = dirname( __DIR__ );

require_once $wcp_root . '/vendor/autoload.php';

$wcp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wcp_tests_dir ) {
	$wcp_tests_dir = $wcp_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $wcp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found at {$wcp_tests_dir}. Run `composer install`.\n" );
	exit( 1 );
}

// wp-phpunit reads the config path from this variable.
if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $wcp_root . '/tests/wp-tests-config.php' );
}

require_once $wcp_tests_dir . '/includes/functions.php';

/**
 * Resolve the WooCommerce plugin file.
 *
 * @return string Empty when not found.
 */
function wcp_tests_wc_plugin_file() {
	$dir = getenv( 'WC_PLUGIN_DIR' );

	if ( ! $dir ) {
		$core = getenv( 'WP_CORE_DIR' ) ? getenv( 'WP_CORE_DIR' ) : '/var/www/html';
		$dir  = rtrim( $core, '/' ) . '/wp-content/plugins/woocommerce';
	}

	$file = rtrim( $dir, '/' ) . '/woocommerce.php';

	return file_exists( $file ) ? $file : '';
}

/**
 * Load WooCommerce, then the plugin under test.
 *
 * Skipping WooCommerce is deliberate when WCP_TESTS_WITHOUT_WC is set: that is
 * how the degraded-boot behaviour gets its own coverage.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		if ( ! getenv( 'WCP_TESTS_WITHOUT_WC' ) ) {
			$wc = wcp_tests_wc_plugin_file();

			if ( '' === $wc ) {
				fwrite( STDERR, "WooCommerce not found. Set WC_PLUGIN_DIR, or set WCP_TESTS_WITHOUT_WC=1 to run only the degraded-boot suite.\n" );
				exit( 1 );
			}

			require_once $wc;
		}

		require_once dirname( __DIR__ ) . '/woocommerce-customer-portal.php';
	}
);

/**
 * Install WooCommerce's tables and roles once WordPress is up.
 *
 * Mirrors WooCommerce's own test bootstrap. Roles are reloaded afterwards
 * because `WC_Install` registers the `customer` role, and the role cache was
 * primed before it existed.
 */
tests_add_filter(
	'setup_theme',
	function () {
		if ( getenv( 'WCP_TESTS_WITHOUT_WC' ) || ! class_exists( 'WC_Install' ) ) {
			return;
		}

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		if ( ! defined( 'WC_REMOVE_ALL_DATA' ) ) {
			define( 'WC_REMOVE_ALL_DATA', true );
		}

		$wc_dir = dirname( wcp_tests_wc_plugin_file() );

		if ( file_exists( $wc_dir . '/uninstall.php' ) ) {
			include $wc_dir . '/uninstall.php';
		}

		WC_Install::install();

		if ( class_exists( '\Automattic\WooCommerce\Admin\Install' ) ) {
			\Automattic\WooCommerce\Admin\Install::create_tables();
			\Automattic\WooCommerce\Admin\Install::create_events();
		}

		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required after WC_Install registers roles.
		wp_roles();
	}
);

require_once $wcp_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/includes/class-wcp-test-case.php';
require_once __DIR__ . '/includes/class-wcp-rest-test-case.php';
