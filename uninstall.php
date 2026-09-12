<?php
/**
 * Uninstall routine.
 *
 * Runs only when the user deletes the plugin from the WordPress admin (not on
 * deactivation). It removes what the plugin itself created and nothing else:
 *
 * - the `wcp_settings` option (admin settings),
 * - the `wcp_version` / `wcp_installed_at` bookkeeping options,
 * - the plugin's own transients (environment check, rate-limit counters and
 *   one-shot form feedback).
 *
 * It never touches WooCommerce data. Orders, customers, addresses and user
 * accounts belong to the store, not to this plugin, and survive uninstall
 * exactly as they were. The plugin stores no user meta, so there is nothing
 * of that kind to remove either.
 *
 * @package WooCommerce_Customer_Portal
 */

// Only ever run in an uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Options owned by this plugin.
 *
 * @var string[]
 */
$wcp_options = array(
	'wcp_settings',
	'wcp_version',
	'wcp_installed_at',
);

/**
 * Transient name prefixes owned by this plugin.
 *
 * Rate-limit counters are `wcp_rl_{bucket}_{user_id}` and form feedback is
 * `wcp_flash_{user_id}`; both are keyed per user, so they must be removed by
 * prefix rather than by name.
 *
 * @var string[]
 */
$wcp_transient_prefixes = array(
	'wcp_rl_',
	'wcp_flash_',
);

/**
 * Delete plugin data for a single site.
 *
 * @param string[] $options            Option names to remove.
 * @param string[] $transient_prefixes Transient name prefixes to remove.
 * @return void
 */
function wcp_uninstall_site( array $options, array $transient_prefixes ) {
	global $wpdb;

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_transient( 'wcp_environment_check' );

	// Per-user transients have no fixed names, so they are swept by prefix.
	// Both the value row and its `_transient_timeout_` twin are matched. When
	// a persistent object cache holds transients instead of the options
	// table, the remaining entries expire on their own within 15 minutes.
	foreach ( $transient_prefixes as $prefix ) {
		$like         = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup; there is no API for prefix deletion.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like,
				$timeout_like
			)
		);
	}

	wp_cache_flush();
}

if ( is_multisite() ) {
	$wcp_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wcp_site_ids as $wcp_site_id ) {
		switch_to_blog( (int) $wcp_site_id );
		wcp_uninstall_site( $wcp_options, $wcp_transient_prefixes );
		restore_current_blog();
	}
} else {
	wcp_uninstall_site( $wcp_options, $wcp_transient_prefixes );
}
