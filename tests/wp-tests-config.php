<?php
/**
 * WordPress test configuration.
 *
 * Every value is read from the environment, with the same placeholder
 * defaults WordPress core's own sample config uses, so this file contains no
 * credentials of its own and the same file runs unchanged in CI.
 *
 * The database named here is DESTROYED on every test run. Never point it at
 * a site you care about.
 *
 * @package WooCommerce_Customer_Portal
 */

$wcp_env = function ( $key, $fallback ) {
	$value = getenv( $key );

	return ( false === $value || '' === $value ) ? $fallback : $value;
};

define( 'ABSPATH', rtrim( $wcp_env( 'WP_CORE_DIR', '/var/www/html' ), '/' ) . '/' );

define( 'DB_NAME', $wcp_env( 'WP_TESTS_DB_NAME', 'wordpress_test' ) );
define( 'DB_USER', $wcp_env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', $wcp_env( 'WP_TESTS_DB_PASSWORD', 'root' ) );
define( 'DB_HOST', $wcp_env( 'WP_TESTS_DB_HOST', '127.0.0.1' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', $wcp_env( 'WP_TESTS_DOMAIN', 'example.org' ) );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'WCP Test Site' );
define( 'WP_PHP_BINARY', 'php' );

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SCRIPT_DEBUG', true );

// Keeps WooCommerce quiet during install.
define( 'WC_TAX_ROUNDING_MODE', 2 );
define( 'WC_USE_TRANSACTIONS', false );
