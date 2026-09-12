<?php
/**
 * Activation routine.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs once, when the plugin is activated.
 */
class WCP_Activator {

	/**
	 * Option storing the installed plugin version.
	 */
	const VERSION_OPTION = 'wcp_version';

	/**
	 * Option storing the first-install timestamp (GMT).
	 */
	const INSTALLED_OPTION = 'wcp_installed_at';

	/**
	 * Validate the environment and record install state.
	 *
	 * Refuses to activate on an unsupported PHP or WordPress version rather
	 * than leaving a half-working plugin behind.
	 *
	 * @return void
	 */
	public static function activate() {
		self::guard_environment();

		if ( ! get_option( self::INSTALLED_OPTION ) ) {
			add_option( self::INSTALLED_OPTION, gmdate( 'Y-m-d H:i:s' ) );
		}

		update_option( self::VERSION_OPTION, WCP_VERSION );

		// The plugin registers no rewrite rules of its own; flushing anyway
		// keeps activation idempotent should endpoints be added later.
		flush_rewrite_rules();
	}

	/**
	 * Abort activation when the environment is unsupported.
	 *
	 * @return void
	 */
	private static function guard_environment() {
		$reason = '';

		if ( version_compare( PHP_VERSION, WCP_MIN_PHP_VERSION, '<' ) ) {
			$reason = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'WooCommerce Customer Portal requires PHP %1$s or newer. This site is running PHP %2$s.', 'woocommerce-customer-portal' ),
				WCP_MIN_PHP_VERSION,
				PHP_VERSION
			);
		} elseif ( version_compare( get_bloginfo( 'version' ), WCP_MIN_WP_VERSION, '<' ) ) {
			$reason = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'WooCommerce Customer Portal requires WordPress %1$s or newer. This site is running WordPress %2$s.', 'woocommerce-customer-portal' ),
				WCP_MIN_WP_VERSION,
				get_bloginfo( 'version' )
			);
		}

		if ( '' === $reason ) {
			return;
		}

		deactivate_plugins( WCP_PLUGIN_BASENAME );

		wp_die(
			esc_html( $reason ),
			esc_html__( 'Plugin activation failed', 'woocommerce-customer-portal' ),
			array(
				'back_link' => true,
				'response'  => 200,
			)
		);
	}
}
