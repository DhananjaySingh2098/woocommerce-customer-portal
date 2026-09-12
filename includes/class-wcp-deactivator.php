<?php
/**
 * Deactivation routine.
 *
 * Deactivation is reversible: it clears runtime caches and scheduled work but
 * never touches customer data or stored settings. Destructive cleanup belongs
 * in uninstall.php.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs when the plugin is deactivated.
 */
class WCP_Deactivator {

	/**
	 * Clear transient state left behind by the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::clear_scheduled_events();

		delete_transient( 'wcp_environment_check' );

		flush_rewrite_rules();
	}

	/**
	 * Unschedule any cron events registered by the plugin.
	 *
	 * @return void
	 */
	private static function clear_scheduled_events() {
		$events = array(
			// Reserved for later phases; unscheduling a hook that was never
			// scheduled is a no-op, so this stays safe as the list grows.
			'wcp_daily_maintenance',
		);

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );

			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
				$timestamp = wp_next_scheduled( $event );
			}
		}
	}
}
