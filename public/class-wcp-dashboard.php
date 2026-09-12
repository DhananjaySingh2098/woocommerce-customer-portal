<?php
/**
 * Dashboard summary: metrics, recent orders, activity.
 *
 * Every number here is read from WooCommerce for the authenticated customer.
 * Nothing is estimated, projected or compared against a period the store does
 * not record -- which is why there are no percentage deltas on the metric
 * cards. A "+12% this month" would require a prior-period figure that nothing
 * in this plugin has, so it would be decoration pretending to be data.
 *
 * The customer is `get_current_user_id()`. Order data is read through
 * `WCP_Orders`, which already owns the ownership gate, so this class adds no
 * second path to order records and no second place for that rule to live.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the authenticated customer's dashboard summary.
 */
class WCP_Dashboard {

	/**
	 * Orders shown in the Recent orders panel.
	 */
	const RECENT_LIMIT = 4;

	/**
	 * Orders scanned when assembling the activity feed.
	 *
	 * Larger than the recent list because one order can contribute several
	 * events, and the newest events are not necessarily the newest orders.
	 */
	const ACTIVITY_SCAN = 10;

	/**
	 * Events shown in the activity feed.
	 */
	const ACTIVITY_LIMIT = 6;

	/**
	 * Products shown in the store discovery panel.
	 */
	const PRODUCT_LIMIT = 4;

	/**
	 * Everything the dashboard renders.
	 *
	 * @return array
	 */
	public static function get_summary() {
		$customer_id = WCP_Auth::current_customer_id();

		if ( $customer_id <= 0 ) {
			return self::empty_summary();
		}

		// If an administrator has switched Orders off, the dashboard does not
		// quietly show order data through a side door.
		if ( class_exists( 'WCP_Settings' ) && ! WCP_Settings::section_enabled( 'orders' ) ) {
			return self::empty_summary();
		}

		// One query serves the count, the recent list and the activity feed.
		$page = WCP_Orders::get_orders( array( 'per_page' => self::ACTIVITY_SCAN ) );
		$all  = (array) $page['orders'];

		if ( empty( $all ) ) {
			return self::empty_summary();
		}

		$setup = self::setup_state( true );

		return array(
			'has_orders'  => true,
			'order_count' => (int) $page['total'],
			'total_spent' => self::total_spent( $customer_id ),
			'latest'      => $all[0],
			'recent'      => array_slice( $all, 0, self::RECENT_LIMIT ),
			'activity'    => self::build_activity( $all ),
			'addresses'   => self::address_status(),
			'setup'       => $setup,
			'products'    => empty( $setup['complete'] ) ? array() : self::get_products(),
		);
	}

	/**
	 * The summary for a customer with nothing ordered yet.
	 *
	 * Still a full dashboard: the account state is real, the setup steps are
	 * real, and the products are the store's own newest listings. Only the
	 * order figures are absent, because there are none.
	 *
	 * @return array
	 */
	private static function empty_summary() {
		return array(
			'has_orders'  => false,
			'order_count' => 0,
			'total_spent' => '',
			'latest'      => null,
			'recent'      => array(),
			'activity'    => array(),
			'addresses'   => self::address_status(),
			'setup'       => self::setup_state( false ),
			'products'    => self::get_products(),
		);
	}

	/**
	 * Lifetime value, formatted.
	 *
	 * `wc_get_customer_total_spent()` is WooCommerce's own figure: it sums the
	 * customer's orders in paid statuses through the active data store, so it
	 * is HPOS-correct and matches what the store's own reports would say.
	 * Deriving it here by adding up order totals would quietly disagree with
	 * WooCommerce about refunds and unpaid orders.
	 *
	 * @param int $customer_id The authenticated customer.
	 * @return string Formatted price markup, or an empty string.
	 */
	private static function total_spent( $customer_id ) {
		if ( ! function_exists( 'wc_get_customer_total_spent' ) || ! function_exists( 'wc_price' ) ) {
			return '';
		}

		$total = wc_get_customer_total_spent( $customer_id );

		return wc_price( (float) $total );
	}

	/**
	 * Whether each address has been saved.
	 *
	 * @return array
	 */
	private static function address_status() {
		$available = class_exists( 'WCP_Addresses' )
			&& ( ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'addresses' ) );

		if ( ! $available ) {
			return array(
				'billing'  => false,
				'shipping' => false,
				'saved'    => 0,
				'total'    => 0,
			);
		}

		$types = WCP_Addresses::get_types();
		$saved = 0;
		$state = array();

		foreach ( $types as $type ) {
			$state[ $type ] = WCP_Addresses::has_address( $type );

			if ( $state[ $type ] ) {
				++$saved;
			}
		}

		$state['saved'] = $saved;
		$state['total'] = count( $types );

		return $state;
	}

	/*
	|--------------------------------------------------------------------------
	| Account setup
	|--------------------------------------------------------------------------
	*/

	/**
	 * How far through setting up the account the customer actually is.
	 *
	 * Every step is a condition the store can answer truthfully right now: the
	 * account exists, a name is on file, an address is saved, an order has been
	 * placed. Nothing is scored, weighted or projected -- the percentage is
	 * simply completed steps over offered steps, so "60% complete" always means
	 * "three of these five things are done" and clicking through can fix it.
	 *
	 * Steps belonging to a section an administrator has switched off are not
	 * offered at all, rather than counted as permanently incomplete.
	 *
	 * @param bool $has_orders Whether the customer has ordered.
	 * @return array
	 */
	public static function setup_state( $has_orders ) {
		$settings = class_exists( 'WCP_Settings' );
		$user     = WCP_Auth::current_user();

		$steps = array(
			array(
				'key'  => 'account',
				'done' => (bool) $user,
			),
		);

		if ( ! $settings || WCP_Settings::section_enabled( 'profile' ) ) {
			$steps[] = array(
				'key'  => 'profile',
				'done' => self::profile_complete( $user ),
			);
		}

		if ( ! $settings || WCP_Settings::section_enabled( 'addresses' ) ) {
			$addresses = self::address_status();

			foreach ( array( 'billing', 'shipping' ) as $type ) {
				if ( ! array_key_exists( $type, $addresses ) ) {
					continue;
				}

				$steps[] = array(
					'key'  => $type,
					'done' => ! empty( $addresses[ $type ] ),
				);
			}
		}

		if ( ! $settings || WCP_Settings::section_enabled( 'orders' ) ) {
			$steps[] = array(
				'key'  => 'order',
				'done' => (bool) $has_orders,
			);
		}

		$total = count( $steps );
		$done  = 0;
		$next  = '';

		foreach ( $steps as $step ) {
			if ( $step['done'] ) {
				++$done;

				continue;
			}

			if ( '' === $next ) {
				$next = $step['key'];
			}
		}

		return array(
			'steps'    => $steps,
			'done'     => $done,
			'total'    => $total,
			'percent'  => $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0,
			'next'     => $next,
			'complete' => ( $done === $total ),
		);
	}

	/**
	 * Whether the customer has a name on file.
	 *
	 * Both halves, because a first name alone is what WooCommerce fills in from
	 * a guest checkout and is not the same as a completed profile.
	 *
	 * @param WP_User|null $user Authenticated user.
	 * @return bool
	 */
	private static function profile_complete( $user ) {
		if ( ! $user ) {
			return false;
		}

		return '' !== trim( (string) $user->first_name ) && '' !== trim( (string) $user->last_name );
	}

	/*
	|--------------------------------------------------------------------------
	| Store discovery
	|--------------------------------------------------------------------------
	*/

	/**
	 * The most recently published products, for the discovery panel.
	 *
	 * Deliberately *not* a recommendation: nothing here looks at the customer,
	 * so the panel is labelled for what it is. Only catalog-visible published
	 * products are returned, which is the same set the shop page would show, so
	 * the portal can never surface something the store has hidden.
	 *
	 * @return array<int,array>
	 */
	public static function get_products() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		/**
		 * Filter how many products the store discovery panel requests.
		 *
		 * @param int $limit Number of products.
		 */
		$limit = (int) apply_filters( 'wcp_dashboard_product_limit', self::PRODUCT_LIMIT );

		if ( $limit < 1 ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'limit'      => $limit,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'visibility' => 'catalog',
				'return'     => 'objects',
			)
		);

		if ( ! is_array( $products ) ) {
			return array();
		}

		$prepared = array();

		foreach ( $products as $product ) {
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$image_id = (int) $product->get_image_id();

			$prepared[] = array(
				'id'         => (int) $product->get_id(),
				'name'       => (string) $product->get_name(),
				'permalink'  => (string) $product->get_permalink(),
				'price_html' => (string) $product->get_price_html(),
				'image'      => $image_id > 0 ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '',
				'on_sale'    => (bool) $product->is_on_sale(),
			);
		}

		return $prepared;
	}

	/*
	|--------------------------------------------------------------------------
	| Activity
	|--------------------------------------------------------------------------
	*/

	/**
	 * A genuine activity feed, or nothing.
	 *
	 * WooCommerce does not keep a status history on an order, so a timeline of
	 * "status changed to X" cannot be reconstructed -- and inventing one was
	 * out of the question. What WooCommerce *does* record are three timestamps
	 * per order: when it was placed, when it was paid, and when it was
	 * completed. Those are real, they are the customer's own, and they are
	 * enough for an honest feed.
	 *
	 * Events with no timestamp are skipped rather than guessed at, so an
	 * unpaid order contributes one entry and a completed one contributes three.
	 *
	 * @param array $orders Summary DTOs, newest order first.
	 * @return array<int,array>
	 */
	private static function build_activity( array $orders ) {
		$events = array();

		foreach ( $orders as $order ) {
			$events[] = self::event( $order, 'placed', $order['date_iso'], $order['date_label'] );

			if ( ! empty( $order['paid_iso'] ) ) {
				$events[] = self::event( $order, 'paid', $order['paid_iso'], $order['paid_label'] );
			}

			if ( ! empty( $order['completed_iso'] ) ) {
				$events[] = self::event( $order, 'completed', $order['completed_iso'], $order['completed_label'] );
			}
		}

		$events = self::collapse_simultaneous( array_filter( $events ) );

		// Newest first. Ties keep their relative order, which for an order
		// placed and paid in the same second reads correctly.
		usort(
			$events,
			function ( $a, $b ) {
				if ( $a['timestamp'] === $b['timestamp'] ) {
					return 0;
				}

				return ( $a['timestamp'] > $b['timestamp'] ) ? -1 : 1;
			}
		);

		return array_slice( $events, 0, self::ACTIVITY_LIMIT );
	}

	/**
	 * Drop milestones an order reached at effectively the same moment.
	 *
	 * A store that marks an order paid and completed in the same action -- a
	 * virtual product, a manual bulk update -- produces two entries seconds
	 * apart saying almost the same thing. Both are true, and listing both is
	 * still noise, so the more advanced milestone stands for the pair.
	 *
	 * Nothing is invented or reordered: only a duplicate view of one instant is
	 * removed, and only within a single order.
	 *
	 * @param array $events Unsorted events.
	 * @return array
	 */
	private static function collapse_simultaneous( array $events ) {
		$rank = array(
			'placed'    => 0,
			'paid'      => 1,
			'completed' => 2,
		);

		$kept = array();

		foreach ( $events as $event ) {
			$bucket = $event['order_id'] . '|' . (int) floor( $event['timestamp'] / MINUTE_IN_SECONDS );

			if ( ! isset( $kept[ $bucket ] ) ) {
				$kept[ $bucket ] = $event;

				continue;
			}

			$existing = $kept[ $bucket ];

			if ( $rank[ $event['kind'] ] > $rank[ $existing['kind'] ] ) {
				$kept[ $bucket ] = $event;
			}
		}

		return array_values( $kept );
	}

	/**
	 * Build one activity event.
	 *
	 * @param array  $order Summary DTO.
	 * @param string $kind  `placed`, `paid` or `completed`.
	 * @param string $iso   ISO 8601 timestamp.
	 * @param string $label Formatted date.
	 * @return array|null Null when the timestamp is unusable.
	 */
	private static function event( array $order, $kind, $iso, $label ) {
		if ( '' === $iso ) {
			return null;
		}

		$timestamp = strtotime( $iso );

		if ( ! $timestamp ) {
			return null;
		}

		$copy = array(
			'placed'    => array(
				'icon' => 'orders',
				'tone' => 'neutral',
				/* translators: %s: order number. */
				'text' => __( 'Order #%s placed', 'woocommerce-customer-portal' ),
			),
			'paid'      => array(
				'icon' => 'card',
				'tone' => 'info',
				/* translators: %s: order number. */
				'text' => __( 'Payment received for #%s', 'woocommerce-customer-portal' ),
			),
			'completed' => array(
				'icon' => 'check',
				'tone' => 'success',
				/* translators: %s: order number. */
				'text' => __( 'Order #%s completed', 'woocommerce-customer-portal' ),
			),
		);

		if ( ! isset( $copy[ $kind ] ) ) {
			return null;
		}

		return array(
			'kind'       => $kind,
			'order_id'   => (int) $order['id'],
			'number'     => (string) $order['number'],
			'icon'       => $copy[ $kind ]['icon'],
			'tone'       => $copy[ $kind ]['tone'],
			'text'       => sprintf( $copy[ $kind ]['text'], $order['number'] ),
			'date_iso'   => $iso,
			'date_label' => $label,
			'timestamp'  => $timestamp,
			'relative'   => self::relative( $timestamp ),
		);
	}

	/**
	 * Human-readable age of a timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function relative( $timestamp ) {
		$now = time();

		if ( $timestamp > $now ) {
			return '';
		}

		return sprintf(
			/* translators: %s: human-readable time difference, e.g. "2 days". */
			__( '%s ago', 'woocommerce-customer-portal' ),
			human_time_diff( $timestamp, $now )
		);
	}
}
