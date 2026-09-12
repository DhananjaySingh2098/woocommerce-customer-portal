<?php
/**
 * Authentication foundation.
 *
 * The portal has no authentication system of its own. Identity is read from
 * the WordPress session and nowhere else: no customer ID query argument, no
 * cookie, no request body field. That single rule removes the entire class of
 * cross-customer data-exposure bugs before any data endpoint is written.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Session-backed identity resolution and account URLs.
 */
class WCP_Auth {

	/**
	 * Whether a customer is authenticated for this request.
	 *
	 * @return bool
	 */
	public static function is_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * The authenticated user's ID.
	 *
	 * This is the only source of customer identity in the plugin.
	 *
	 * @return int Zero when logged out.
	 */
	public static function current_customer_id() {
		return (int) get_current_user_id();
	}

	/**
	 * The authenticated user object.
	 *
	 * @return WP_User|null
	 */
	public static function current_user() {
		if ( ! self::is_logged_in() ) {
			return null;
		}

		$user = wp_get_current_user();

		return ( $user instanceof WP_User && $user->exists() ) ? $user : null;
	}

	/**
	 * Whether the current session may render the portal.
	 *
	 * @return bool
	 */
	public static function can_view_portal() {
		return null !== self::current_user() && WCP_Security::current_user_can_view_portal();
	}

	/*
	|----------------------------------------------------------------------
	| WooCommerce account URLs
	|----------------------------------------------------------------------
	|
	| Each accessor degrades to a safe value when WooCommerce or the My Account
	| page is unavailable, so templates never emit a broken link.
	|
	*/

	/**
	 * URL of the WooCommerce My Account page.
	 *
	 * @return string Empty string when unavailable.
	 */
	public static function get_my_account_url() {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		$url = wc_get_page_permalink( 'myaccount' );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * URL of a WooCommerce My Account endpoint.
	 *
	 * @param string $endpoint Endpoint slug, e.g. `orders` or `edit-address`.
	 * @return string Empty string when unavailable.
	 */
	public static function get_account_endpoint_url( $endpoint ) {
		if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
			return self::get_my_account_url();
		}

		$url = wc_get_account_endpoint_url( sanitize_key( $endpoint ) );

		return is_string( $url ) ? $url : self::get_my_account_url();
	}

	/**
	 * Login URL, returning the visitor to the portal page afterwards.
	 *
	 * @param string $redirect_to Optional. URL to return to after login.
	 * @return string
	 */
	public static function get_login_url( $redirect_to = '' ) {
		$redirect_to = $redirect_to ? $redirect_to : self::current_url();
		$account_url = self::get_my_account_url();

		if ( '' === $account_url ) {
			return wp_login_url( $redirect_to );
		}

		return add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $account_url );
	}

	/**
	 * Registration URL when open registration is enabled, otherwise empty.
	 *
	 * @return string
	 */
	public static function get_register_url() {
		if ( 'yes' !== get_option( 'woocommerce_enable_myaccount_registration' ) ) {
			return '';
		}

		return self::get_my_account_url();
	}

	/**
	 * Nonce-protected logout URL.
	 *
	 * @param string $redirect_to Optional. URL to return to after logout.
	 * @return string
	 */
	public static function get_logout_url( $redirect_to = '' ) {
		$redirect_to = $redirect_to ? $redirect_to : home_url( '/' );

		if ( function_exists( 'wc_logout_url' ) ) {
			return wc_logout_url( $redirect_to );
		}

		return wp_logout_url( $redirect_to );
	}

	/**
	 * The current front-end URL, rebuilt from trusted server state.
	 *
	 * Built from the permalink of the queried object where possible so that a
	 * spoofed `REQUEST_URI` cannot be reflected back into a redirect target.
	 *
	 * @return string
	 */
	public static function current_url() {
		$object_id = get_queried_object_id();

		if ( $object_id ) {
			$permalink = get_permalink( $object_id );

			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/' );
	}
}
