<?php
/**
 * Rate limiting for mutating requests.
 *
 * Two buckets, because two different things are worth limiting:
 *
 *   - A generous per-action bucket on every write, which exists to stop a
 *     runaway script hammering the database. Normal use never reaches it.
 *   - A tight bucket on *failed* password changes, which is the one that
 *     matters. Counting only failures means a customer who legitimately
 *     changes their password twice is unaffected, while someone guessing the
 *     current password runs out of attempts quickly.
 *
 * Buckets are keyed on the authenticated user ID, not on an IP address. The
 * endpoints are already authenticated, so the session is the meaningful actor,
 * and an IP key would punish everyone behind one NAT while doing nothing about
 * an attacker with a session and a proxy pool.
 *
 * Both transports enforce this. Limiting only the REST route would leave the
 * no-JavaScript form path as a way around it, which is not a rate limit at all.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-user request throttling.
 */
class WCP_Rate_Limit {

	/**
	 * Transient prefix.
	 */
	const PREFIX = 'wcp_rl_';

	/**
	 * Bucket definitions: limit (attempts) and window (seconds).
	 *
	 * @return array<string,array{limit:int,window:int}>
	 */
	public static function buckets() {
		$buckets = array(
			// Every profile write. Deliberately loose.
			'profile'  => array(
				'limit'  => 30,
				'window' => 5 * MINUTE_IN_SECONDS,
			),
			// Every address write.
			'address'  => array(
				'limit'  => 30,
				'window' => 5 * MINUTE_IN_SECONDS,
			),
			// Failed password changes only.
			'password' => array(
				'limit'  => 5,
				'window' => 15 * MINUTE_IN_SECONDS,
			),
		);

		/**
		 * Filter the rate-limit buckets.
		 *
		 * A store behind a shared corporate gateway may need looser limits;
		 * one under attack may want tighter. Values are clamped to something
		 * sane, so a filter cannot switch limiting off by returning zero.
		 *
		 * @param array $buckets Bucket definitions.
		 */
		$buckets = (array) apply_filters( 'wcp_rate_limit_buckets', $buckets );

		foreach ( $buckets as $name => $bucket ) {
			$buckets[ $name ] = array(
				'limit'  => max( 1, (int) ( isset( $bucket['limit'] ) ? $bucket['limit'] : 1 ) ),
				'window' => max( 10, (int) ( isset( $bucket['window'] ) ? $bucket['window'] : 60 ) ),
			);
		}

		return $buckets;
	}

	/**
	 * Whether the current user may perform this action.
	 *
	 * Read-only: it inspects the bucket without consuming an attempt, so a
	 * caller can check before doing work and record afterwards.
	 *
	 * @param string $action Bucket name.
	 * @return true|WP_Error `WP_Error` with a 429 status when exhausted.
	 */
	public static function check( $action ) {
		$bucket = self::bucket( $action );

		if ( null === $bucket ) {
			return true;
		}

		$state = self::state( $action );

		if ( $state['count'] < $bucket['limit'] ) {
			return true;
		}

		$retry = max( 1, ( $state['start'] + $bucket['window'] ) - time() );

		// Deliberately vague. The message says to wait and for how long; it
		// does not say which bucket, what the limit is, or how many attempts
		// have been made, none of which the caller needs and all of which help
		// someone tuning an attack.
		return new WP_Error(
			'wcp_rate_limited',
			__( 'Too many attempts. Please wait a moment and try again.', 'woocommerce-customer-portal' ),
			array(
				'status'      => 429,
				'retry_after' => $retry,
			)
		);
	}

	/**
	 * Consume one attempt.
	 *
	 * @param string $action Bucket name.
	 * @return void
	 */
	public static function record( $action ) {
		$bucket = self::bucket( $action );

		if ( null === $bucket ) {
			return;
		}

		$state = self::state( $action );

		++$state['count'];

		// The transient expires with the window, so the bucket empties on its
		// own. The remaining lifetime is used rather than the full window, or
		// each attempt would extend the block.
		$remaining = max( 1, ( $state['start'] + $bucket['window'] ) - time() );

		set_transient( self::key( $action ), $state, $remaining );
	}

	/**
	 * Clear a bucket.
	 *
	 * Used after a successful password change: the failure counter exists to
	 * slow guessing, and a correct answer proves there was nothing to guess.
	 *
	 * @param string $action Bucket name.
	 * @return void
	 */
	public static function clear( $action ) {
		if ( null === self::bucket( $action ) ) {
			return;
		}

		delete_transient( self::key( $action ) );
	}

	/**
	 * Seconds a caller should wait, for a `Retry-After` header.
	 *
	 * @param WP_Error $error Error from `check()`.
	 * @return int Zero when the error carries no retry hint.
	 */
	public static function retry_after( $error ) {
		if ( ! $error instanceof WP_Error ) {
			return 0;
		}

		$data = $error->get_error_data();

		return isset( $data['retry_after'] ) ? max( 1, (int) $data['retry_after'] ) : 0;
	}

	/*
	|--------------------------------------------------------------------------
	| Internals
	|--------------------------------------------------------------------------
	*/

	/**
	 * One bucket definition.
	 *
	 * @param string $action Bucket name.
	 * @return array|null
	 */
	private static function bucket( $action ) {
		$buckets = self::buckets();
		$action  = sanitize_key( (string) $action );

		return isset( $buckets[ $action ] ) ? $buckets[ $action ] : null;
	}

	/**
	 * Current bucket state for the authenticated user.
	 *
	 * @param string $action Bucket name.
	 * @return array{count:int,start:int}
	 */
	private static function state( $action ) {
		$stored = get_transient( self::key( $action ) );

		if ( ! is_array( $stored ) || ! isset( $stored['count'], $stored['start'] ) ) {
			return array(
				'count' => 0,
				'start' => time(),
			);
		}

		return array(
			'count' => (int) $stored['count'],
			'start' => (int) $stored['start'],
		);
	}

	/**
	 * Transient key for the authenticated user and action.
	 *
	 * @param string $action Bucket name.
	 * @return string
	 */
	private static function key( $action ) {
		return self::PREFIX . sanitize_key( (string) $action ) . '_' . get_current_user_id();
	}
}
