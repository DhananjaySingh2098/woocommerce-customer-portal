<?php
/**
 * Read-only view of the authenticated customer.
 *
 * Exposes identity only — display name, email, avatar monogram, membership
 * date. Orders, addresses and profile editing are deliberately absent; they
 * live in `WCP_Orders`, `WCP_Addresses` and `WCP_Profile` behind the same
 * ownership checks.
 *
 * An instance can only ever be built from the current session, so there is no
 * code path that loads "some other customer".
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Identity accessor for the signed-in customer.
 */
class WCP_Account {

	/**
	 * The authenticated user.
	 *
	 * @var WP_User
	 */
	private $user;

	/**
	 * Build from the current session.
	 *
	 * @param WP_User $user Authenticated user.
	 */
	private function __construct( WP_User $user ) {
		$this->user = $user;
	}

	/**
	 * Create an account view for the current session.
	 *
	 * @return WCP_Account|null Null when logged out.
	 */
	public static function for_current_user() {
		$user = WCP_Auth::current_user();

		return $user ? new self( $user ) : null;
	}

	/*
	|----------------------------------------------------------------------
	| Identity
	|----------------------------------------------------------------------
	*/

	/**
	 * User ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return (int) $this->user->ID;
	}

	/**
	 * Best available full name, falling back to the account username.
	 *
	 * @return string
	 */
	public function get_display_name() {
		$candidates = array(
			trim( $this->user->first_name . ' ' . $this->user->last_name ),
			(string) $this->user->display_name,
			(string) $this->user->user_nicename,
		);

		foreach ( $candidates as $candidate ) {
			if ( '' !== trim( $candidate ) ) {
				return trim( $candidate );
			}
		}

		return __( 'Customer', 'woocommerce-customer-portal' );
	}

	/**
	 * Short, friendly name for greetings.
	 *
	 * @return string
	 */
	public function get_greeting_name() {
		$first = trim( (string) $this->user->first_name );

		if ( '' !== $first ) {
			return $first;
		}

		$display = $this->get_display_name();
		$parts   = preg_split( '/\s+/u', $display );

		return isset( $parts[0] ) && '' !== $parts[0] ? $parts[0] : $display;
	}

	/**
	 * Account email address.
	 *
	 * @return string
	 */
	public function get_email() {
		return (string) $this->user->user_email;
	}

	/**
	 * Monogram initials for the avatar.
	 *
	 * @return string
	 */
	public function get_initials() {
		return WCP_Helper::initials( $this->get_display_name() );
	}

	/**
	 * Registration timestamp, formatted with the site's date format.
	 *
	 * Real data read from `wp_users.user_registered` — never a placeholder.
	 *
	 * @return string Empty string when unavailable.
	 */
	public function get_member_since() {
		$timestamp = $this->get_registered_timestamp();

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( (string) get_option( 'date_format', 'F j, Y' ), $timestamp );
	}

	/**
	 * Registration date in a compact form, for the status card.
	 *
	 * Same real timestamp as `get_member_since()`, formatted short enough to
	 * sit on one line of a compact card.
	 *
	 * @return string Empty string when unavailable.
	 */
	public function get_member_since_short() {
		$timestamp = $this->get_registered_timestamp();

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date(
			/* translators: compact month and year, e.g. "Sep 2026". Uses PHP date() format codes. */
			_x( 'M Y', 'compact member-since date format', 'woocommerce-customer-portal' ),
			$timestamp
		);
	}

	/**
	 * Registration timestamp, machine readable.
	 *
	 * @return string ISO 8601 date, or an empty string.
	 */
	public function get_member_since_iso() {
		$timestamp = $this->get_registered_timestamp();

		return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
	}

	/**
	 * Parsed `user_registered`, or zero when it is unusable.
	 *
	 * @return int
	 */
	private function get_registered_timestamp() {
		$registered = (string) $this->user->user_registered;

		if ( '' === $registered || '0000-00-00 00:00:00' === $registered ) {
			return 0;
		}

		$timestamp = strtotime( $registered . ' UTC' );

		return $timestamp ? (int) $timestamp : 0;
	}

	/**
	 * A stable hue shift (0–18) derived from the account identity.
	 *
	 * Gives each customer a consistent, individual monogram tint without
	 * storing anything or calling an external avatar service. It is a shift
	 * rather than a hue because the base belongs to the visual theme: the
	 * stylesheet adds this to the preset's own avatar hue, so a monogram is
	 * always recognisably the customer's *and* recognisably part of the theme
	 * it is sitting in.
	 *
	 * @return int
	 */
	public function get_avatar_shift() {
		$seed = crc32( $this->get_email() . '|' . $this->get_id() );

		// A narrow band: enough to tell two customers apart, never enough to
		// wander out of the preset's palette.
		return (int) ( abs( $seed ) % 19 );
	}
}
