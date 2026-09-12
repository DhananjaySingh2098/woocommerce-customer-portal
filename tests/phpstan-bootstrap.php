<?php
/**
 * Constants the plugin bootstrap defines at runtime, declared for PHPStan.
 *
 * Analysis never executes the plugin file, so the guarded `define()` calls in
 * it are invisible to the analyser. These mirror them with representative
 * values; only the names and types matter.
 *
 * @package WooCommerce_Customer_Portal
 */

// The WordPress extension's own bootstrap may already define ABSPATH.
defined( 'ABSPATH' ) || define( 'ABSPATH', '/var/www/html/' );
define( 'WCP_VERSION', '1.0.0' );
define( 'WCP_PLUGIN_FILE', __DIR__ . '/../woocommerce-customer-portal.php' );
define( 'WCP_PLUGIN_DIR', __DIR__ . '/../' );
define( 'WCP_PLUGIN_URL', 'https://example.org/wp-content/plugins/woocommerce-customer-portal/' );
define( 'WCP_PLUGIN_BASENAME', 'woocommerce-customer-portal/woocommerce-customer-portal.php' );
define( 'WCP_SHORTCODE_TAG', 'wcp_customer_portal' );
define( 'WCP_MIN_PHP_VERSION', '7.4' );
define( 'WCP_MIN_WP_VERSION', '6.0' );
define( 'WCP_MIN_WC_VERSION', '7.0' );
define( 'WP_UNINSTALL_PLUGIN', true );
