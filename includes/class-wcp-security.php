<?php
/**
 * Security primitives.
 *
 * Every value that crosses a trust boundary — request input, template data,
 * future REST payloads — passes through this class. Keeping the rules in one
 * place makes them auditable and keeps the audit surface small.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitisation, escaping and capability helpers.
 */
class WCP_Security {

	/**
	 * Nonce action used by portal requests.
	 *
	 * Every mutating endpoint -- REST and plain form alike -- verifies this
	 * nonce in addition to the WordPress REST nonce, so a write is protected
	 * by default rather than as an afterthought.
	 */
	const NONCE_ACTION = 'wcp_portal_action';

	/**
	 * Name of the nonce field/header.
	 */
	const NONCE_NAME = 'wcp_nonce';

	/*
	|----------------------------------------------------------------------
	| Request input
	|----------------------------------------------------------------------
	*/

	/**
	 * Read a sanitised scalar from the query string.
	 *
	 * Read-only navigation state only. Never use this for identity: the
	 * customer is always resolved from the authenticated session.
	 *
	 * @param string $key     Query argument name.
	 * @param string $fallback Fallback when absent or invalid.
	 * @return string
	 */
	public static function get_query_arg( $key, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation state, no side effects.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		return sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
	}

	/**
	 * Reduce an arbitrary value to a known portal section slug.
	 *
	 * Anything unrecognised collapses to the default section, so a crafted URL
	 * can never steer template resolution.
	 *
	 * @param mixed  $value           Untrusted value.
	 * @param array  $allowed         Allowed slugs.
	 * @param string $default_section Fallback slug.
	 * @return string
	 */
	public static function sanitize_section( $value, array $allowed, $default_section = 'dashboard' ) {
		if ( ! is_scalar( $value ) ) {
			return $default_section;
		}

		$value = sanitize_key( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : $default_section;
	}

	/*
	|----------------------------------------------------------------------
	| Nonces
	|----------------------------------------------------------------------
	*/

	/**
	 * Create a nonce for portal actions.
	 *
	 * @return string
	 */
	public static function create_nonce() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Verify a nonce supplied via request body or header.
	 *
	 * @param string|null $nonce Optional explicit nonce value.
	 * @return bool
	 */
	public static function verify_nonce( $nonce = null ) {
		if ( null === $nonce ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This *is* the verification routine.
			$nonce = isset( $_REQUEST[ self::NONCE_NAME ] ) && is_scalar( $_REQUEST[ self::NONCE_NAME ] )
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
				? sanitize_text_field( wp_unslash( (string) $_REQUEST[ self::NONCE_NAME ] ) )
				: '';
		}

		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Verify the portal's action nonce on a REST request.
	 *
	 * Read routes do not need this: WordPress already refuses to resolve a
	 * cookie into a logged-in user without a valid `wp_rest` nonce, so an
	 * unauthenticated request never reaches a permission callback as a user.
	 *
	 * Write routes are different. `wp_rest` is a single nonce covering the whole
	 * REST API, so anything that can obtain one can reach every route. The
	 * portal's own nonce is scoped to `wcp_portal_action`, which means a page
	 * that legitimately holds a `wp_rest` nonce for some unrelated purpose still
	 * cannot drive a profile or address change with it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function verify_rest_nonce( $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return false;
		}

		$nonce = $request->get_header( 'x_wcp_nonce' );

		if ( ! $nonce ) {
			$nonce = $request->get_param( self::NONCE_NAME );
		}

		return is_string( $nonce ) && '' !== $nonce && self::verify_nonce( $nonce );
	}

	/*
	|----------------------------------------------------------------------
	| Capabilities
	|----------------------------------------------------------------------
	*/

	/**
	 * Whether the current session may view the customer portal.
	 *
	 * The only requirement is an authenticated WordPress user. The portal
	 * scopes every read to that user, so no extra capability is needed and
	 * none is granted.
	 *
	 * @return bool
	 */
	public static function current_user_can_view_portal() {
		/**
		 * Filter whether the current user may view the portal.
		 *
		 * @param bool $can_view Default: whether a user is logged in.
		 */
		return (bool) apply_filters( 'wcp_current_user_can_view_portal', is_user_logged_in() );
	}

	/**
	 * Whether the current user may manage plugin settings.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Guard a resource against cross-customer access.
	 *
	 * Compares a resource owner against the authenticated user. Used for
	 * orders and addresses; defined here so the rule lives in one place.
	 *
	 * @param int $owner_id User ID that owns the resource.
	 * @return bool
	 */
	public static function owns_resource( $owner_id ) {
		$owner_id   = absint( $owner_id );
		$current_id = get_current_user_id();

		return $owner_id > 0 && $current_id > 0 && $owner_id === $current_id;
	}

	/*
	|----------------------------------------------------------------------
	| Escaping
	|----------------------------------------------------------------------
	*/

	/**
	 * Escape a URL, returning an empty string for disallowed protocols.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function esc_url( $url ) {
		return esc_url( (string) $url, array( 'http', 'https', 'mailto' ) );
	}

	/**
	 * Allowed-HTML map for WooCommerce-formatted prices.
	 *
	 * Several WooCommerce accessors return markup rather than plain text --
	 * `get_formatted_order_total()`, `get_order_item_totals()` and friends wrap
	 * amounts in `<span class="woocommerce-Price-amount">`, and a discounted
	 * line arrives as `<del>` plus `<ins>`. Escaping that with `esc_html()`
	 * would print the tags; passing it through raw would trust it. `wp_kses`
	 * with this narrow map keeps the formatting and drops anything else.
	 *
	 * @return array
	 */
	public static function allowed_price_html() {
		return array(
			'span'   => array( 'class' => true ),
			'bdi'    => array( 'class' => true ),
			'del'    => array( 'class' => true ),
			'ins'    => array( 'class' => true ),
			'small'  => array( 'class' => true ),
			'strong' => array( 'class' => true ),
			'br'     => array(),
		);
	}

	/**
	 * Allowed-HTML map for formatted addresses.
	 *
	 * `get_formatted_billing_address()` joins the address lines with `<br/>`
	 * according to the country's format, so the breaks have to survive.
	 *
	 * @return array
	 */
	public static function allowed_address_html() {
		return array(
			'br'     => array(),
			'span'   => array( 'class' => true ),
			'strong' => array(),
			'a'      => array(
				'href'  => true,
				'class' => true,
			),
		);
	}

	/**
	 * Allowed-HTML map for order item meta.
	 *
	 * `wc_display_item_meta()` renders variation attributes and any public
	 * custom meta as a definition-style list. WooCommerce has already removed
	 * hidden keys by this point; this map bounds what the surviving markup may
	 * contain.
	 *
	 * @return array
	 */
	public static function allowed_meta_html() {
		return array(
			'ul'     => array( 'class' => true ),
			'li'     => array( 'class' => true ),
			'p'      => array( 'class' => true ),
			'dl'     => array( 'class' => true ),
			'dt'     => array( 'class' => true ),
			'dd'     => array( 'class' => true ),
			'span'   => array( 'class' => true ),
			'strong' => array( 'class' => true ),
			'em'     => array( 'class' => true ),
			'br'     => array(),
			'a'      => array(
				'href'   => true,
				'class'  => true,
				'rel'    => true,
				'target' => true,
			),
		);
	}

	/**
	 * Read a positive integer from the query string.
	 *
	 * Navigation state only -- never identity. A missing, negative or
	 * non-numeric value collapses to the default.
	 *
	 * @param string $key     Query argument name.
	 * @param int    $fallback Fallback.
	 * @return int
	 */
	public static function get_query_int( $key, $fallback = 0 ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation state, no side effects.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return (int) $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		return self::positive_int( wp_unslash( $_GET[ $key ] ), (int) $fallback );
	}

	/**
	 * Reduce a value to a positive integer, or a default.
	 *
	 * `absint()` is the wrong tool for an identifier: it turns `-35` into `35`,
	 * which is a real record. A negative, fractional or non-numeric value is
	 * not an ID and collapses to the default instead.
	 *
	 * @param mixed $value   Untrusted value.
	 * @param int   $fallback Fallback.
	 * @return int
	 */
	public static function positive_int( $value, $fallback = 0 ) {
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return (int) $fallback;
		}

		if ( 0 >= (float) $value || floor( (float) $value ) !== (float) $value ) {
			return (int) $fallback;
		}

		return (int) $value;
	}

	/**
	 * Allowed-HTML map for short, translator-facing rich text.
	 *
	 * @return array
	 */
	public static function allowed_inline_html() {
		return array(
			'strong' => array(),
			'em'     => array(),
			'span'   => array( 'class' => true ),
			'br'     => array(),
			'a'      => array(
				'href'   => true,
				'class'  => true,
				'rel'    => true,
				'target' => true,
			),
		);
	}
}
