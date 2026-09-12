<?php
/**
 * Order repository and data-transfer objects.
 *
 * The single place the portal reads order data. Two rules hold everywhere in
 * this class and are the reason it exists:
 *
 *   1. The customer is `get_current_user_id()`. There is no parameter, filter
 *      or argument that can change whose orders are returned -- the query
 *      argument is overwritten after the caller's arguments are merged, so even
 *      a caller that passes `customer_id` cannot widen the scope.
 *   2. Nothing leaves this class as a `WC_Order`. Callers receive flat arrays
 *      containing only fields chosen for display, so a template or REST
 *      response cannot accidentally expose internal state or private meta.
 *
 * All reads go through the WooCommerce CRUD API (`wc_get_orders`,
 * `wc_get_order`), which routes through the active data store. That keeps the
 * plugin correct under High-Performance Order Storage and under the legacy post
 * tables without knowing which is in use. No SQL is written here.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the authenticated customer's orders.
 */
class WCP_Orders {

	/**
	 * Orders per page when the caller does not say.
	 */
	const DEFAULT_PER_PAGE = 10;

	/**
	 * Hard ceiling on page size.
	 *
	 * Caps how much work one request can ask the database for, whatever the
	 * URL or REST payload says.
	 */
	const MAX_PER_PAGE = 50;

	/*
	|--------------------------------------------------------------------------
	| Queries
	|--------------------------------------------------------------------------
	*/

	/**
	 * A page of the current customer's orders.
	 *
	 * @param array $args Optional. `page` and `per_page`; anything else is ignored.
	 * @return array{orders:array,page:int,per_page:int,total:int,total_pages:int,has_prev:bool,has_next:bool}
	 */
	public static function get_orders( $args = array() ) {
		$page     = self::sanitize_page( isset( $args['page'] ) ? $args['page'] : 1 );
		$per_page = self::sanitize_per_page( isset( $args['per_page'] ) ? $args['per_page'] : self::DEFAULT_PER_PAGE );

		$empty = array(
			'orders'      => array(),
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => 0,
			'total_pages' => 0,
			'has_prev'    => false,
			'has_next'    => false,
		);

		$customer_id = self::current_customer_id();

		if ( $customer_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return $empty;
		}

		$query = array(
			'limit'    => $per_page,
			'paged'    => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'type'     => 'shop_order',
			'status'   => self::visible_statuses(),
		);

		/**
		 * Filter the order query arguments.
		 *
		 * Customer scoping is applied *after* this filter and cannot be
		 * overridden -- a filter may narrow the result set, never widen it.
		 *
		 * @param array $query       Query arguments.
		 * @param int   $customer_id The authenticated customer.
		 */
		$query = (array) apply_filters( 'wcp_orders_query_args', $query, $customer_id );

		// Deliberately last. Whatever a caller or filter asked for, the query
		// is scoped to the authenticated session and nothing else.
		$query['customer_id'] = $customer_id;

		$results = wc_get_orders( $query );

		if ( ! is_object( $results ) || ! isset( $results->orders ) ) {
			return $empty;
		}

		$dtos = array();

		foreach ( (array) $results->orders as $order ) {
			if ( self::is_readable_by_current_user( $order ) ) {
				$dtos[] = self::to_summary( $order );
			}
		}

		$total       = isset( $results->total ) ? (int) $results->total : count( $dtos );
		$total_pages = isset( $results->max_num_pages ) ? (int) $results->max_num_pages : 1;

		return array(
			'orders'      => $dtos,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'has_prev'    => $page > 1,
			'has_next'    => $page < $total_pages,
		);
	}

	/**
	 * One order belonging to the current customer.
	 *
	 * @param int $order_id Requested order ID. Untrusted.
	 * @return array|WP_Error Detail DTO, or an error with a safe code.
	 */
	public static function get_order( $order_id ) {
		$order_id = WCP_Security::positive_int( $order_id );

		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return new WP_Error( 'wcp_order_not_found', __( 'That order could not be found.', 'woocommerce-customer-portal' ), array( 'status' => 404 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'wcp_order_not_found', __( 'That order could not be found.', 'woocommerce-customer-portal' ), array( 'status' => 404 ) );
		}

		if ( ! self::is_readable_by_current_user( $order ) ) {
			// Deliberately the same message and shape as "not found". Telling an
			// attacker that an ID exists but belongs to someone else confirms
			// the ID -- and confirming IDs is how enumeration starts.
			return new WP_Error( 'wcp_order_not_found', __( 'That order could not be found.', 'woocommerce-customer-portal' ), array( 'status' => 404 ) );
		}

		return self::to_detail( $order );
	}

	/**
	 * Whether the authenticated customer may read this order.
	 *
	 * The whole security model for orders is this method. Every path that
	 * returns order data passes through it.
	 *
	 * @param mixed $order Candidate order.
	 * @return bool
	 */
	public static function is_readable_by_current_user( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		// Refunds and drafts are order-shaped but are not the customer's
		// order history.
		if ( 'shop_order' !== $order->get_type() ) {
			return false;
		}

		if ( ! in_array( $order->get_status(), self::visible_statuses( false ), true ) ) {
			return false;
		}

		// A guest order has customer ID 0, which `owns_resource()` rejects for
		// every session -- including a logged-in one.
		return WCP_Security::owns_resource( $order->get_customer_id() );
	}

	/*
	|--------------------------------------------------------------------------
	| Data-transfer objects
	|--------------------------------------------------------------------------
	*/

	/**
	 * List-row representation of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function to_summary( WC_Order $order ) {
		$created = $order->get_date_created();
		$count   = self::count_items( $order );

		// Paid and completed timestamps are facts about the customer's own
		// order, recorded by WooCommerce -- not internal metadata. They are
		// carried on the DTO so the dashboard can build a genuine activity
		// feed without any caller ever handling a WC_Order.
		$paid      = $order->get_date_paid();
		$completed = $order->get_date_completed();

		return array(
			'id'              => $order->get_id(),
			'number'          => (string) $order->get_order_number(),
			'date_iso'        => $created ? $created->date( 'c' ) : '',
			'date_label'      => $created ? wc_format_datetime( $created ) : '',
			'status'          => WCP_Order_Status::describe( $order->get_status() ),
			'item_count'      => $count,
			'items_label'     => sprintf(
				/* translators: %s: number of items in an order. */
				_n( '%s item', '%s items', $count, 'woocommerce-customer-portal' ),
				number_format_i18n( $count )
			),
			'items_teaser'    => self::items_teaser( $order ),
			'total_html'      => $order->get_formatted_order_total(),
			'currency'        => $order->get_currency(),
			'paid_iso'        => $paid ? $paid->date( 'c' ) : '',
			'paid_label'      => $paid ? wc_format_datetime( $paid ) : '',
			'completed_iso'   => $completed ? $completed->date( 'c' ) : '',
			'completed_label' => $completed ? wc_format_datetime( $completed ) : '',
		);
	}

	/**
	 * Full representation of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function to_detail( WC_Order $order ) {
		$detail = self::to_summary( $order );

		$detail['timeline']        = WCP_Order_Status::timeline( $order->get_status() );
		$detail['items']           = self::line_items( $order );
		$detail['totals']          = self::totals( $order );
		$detail['billing_html']    = $order->get_formatted_billing_address();
		$detail['shipping_html']   = $order->get_formatted_shipping_address();
		$detail['billing_email']   = $order->get_billing_email();
		$detail['billing_phone']   = $order->get_billing_phone();
		$detail['payment_method']  = $order->get_payment_method_title();
		$detail['shipping_method'] = $order->get_shipping_method();
		$detail['customer_note']   = $order->get_customer_note();
		$detail['downloads']       = self::downloads( $order );

		return $detail;
	}

	/*
	|--------------------------------------------------------------------------
	| DTO parts
	|--------------------------------------------------------------------------
	*/

	/**
	 * Line items, reduced to what a customer sees on a receipt.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private static function line_items( WC_Order $order ) {
		$items = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			// A product deleted since purchase leaves the line item intact.
			// The order still has to render, so every product-derived field is
			// optional.
			$image     = '';
			$permalink = '';

			if ( $product instanceof WC_Product ) {
				$image_id = $product->get_image_id();

				if ( $image_id ) {
					$src   = wp_get_attachment_image_url( (int) $image_id, 'woocommerce_thumbnail' );
					$image = $src ? $src : '';
				}

				if ( $product->is_visible() ) {
					// The item is passed so a variation resolves to its own URL.
					$permalink = $product->get_permalink( $item );
				}
			}

			$items[] = array(
				'id'         => (int) $item_id,
				'name'       => $item->get_name(),
				'quantity'   => (int) $item->get_quantity(),
				'image'      => $image,
				'permalink'  => $permalink,
				// `wc_display_item_meta` renders variation attributes and any
				// public custom meta. WooCommerce filters out hidden keys
				// (those prefixed with `_`) before this point, so private data
				// never reaches the markup.
				'meta_html'  => wc_display_item_meta( $item, array( 'echo' => false ) ),
				'sku'        => $product instanceof WC_Product ? $product->get_sku() : '',
				'total_html' => $order->get_formatted_line_subtotal( $item ),
			);
		}

		return $items;
	}

	/**
	 * Totals rows as WooCommerce itself computes them.
	 *
	 * Using `get_order_item_totals()` rather than assembling the rows by hand
	 * means discounts, per-line taxes, multiple shipping lines, fees and
	 * store-specific tax display settings all come out right, and stay right
	 * when a store changes its tax configuration.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int,array{key:string,label:string,value:string,is_total:bool}>
	 */
	private static function totals( WC_Order $order ) {
		$rows = array();

		foreach ( (array) $order->get_order_item_totals() as $key => $row ) {
			// Payment method gets its own card; repeating it here reads as a
			// mistake.
			if ( 'payment_method' === $key ) {
				continue;
			}

			if ( ! isset( $row['label'], $row['value'] ) ) {
				continue;
			}

			$rows[] = array(
				'key'      => sanitize_key( (string) $key ),
				'label'    => (string) $row['label'],
				'value'    => (string) $row['value'],
				'is_total' => ( 'order_total' === $key ),
			);
		}

		return $rows;
	}

	/**
	 * Downloadable files the customer is entitled to.
	 *
	 * `get_downloadable_items()` already applies WooCommerce's own entitlement
	 * rules -- order status, download limits and expiry -- so nothing further
	 * is inferred here.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private static function downloads( WC_Order $order ) {
		if ( ! $order->has_downloadable_item() || ! $order->is_download_permitted() ) {
			return array();
		}

		$downloads = array();

		foreach ( (array) $order->get_downloadable_items() as $item ) {
			if ( empty( $item['download_url'] ) ) {
				continue;
			}

			$downloads[] = array(
				'name'         => isset( $item['download_name'] ) ? (string) $item['download_name'] : '',
				'product_name' => isset( $item['product_name'] ) ? (string) $item['product_name'] : '',
				'url'          => (string) $item['download_url'],
				'remaining'    => isset( $item['downloads_remaining'] ) && '' !== $item['downloads_remaining']
					? (string) $item['downloads_remaining']
					: '',
				'expires'      => ! empty( $item['access_expires'] )
					? date_i18n( get_option( 'date_format' ), strtotime( $item['access_expires'] ) )
					: '',
			);
		}

		return $downloads;
	}

	/**
	 * Short human summary of what is in an order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private static function items_teaser( WC_Order $order ) {
		$names = array();

		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();

			if ( count( $names ) >= 2 ) {
				break;
			}
		}

		if ( empty( $names ) ) {
			return '';
		}

		$remaining = self::count_distinct_items( $order ) - count( $names );
		$teaser    = implode( ', ', $names );

		if ( $remaining > 0 ) {
			$teaser .= ' ' . sprintf(
				/* translators: %s: number of further items in an order. */
				_n( '+%s more', '+%s more', $remaining, 'woocommerce-customer-portal' ),
				number_format_i18n( $remaining )
			);
		}

		return $teaser;
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Total quantity across an order's line items.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	private static function count_items( WC_Order $order ) {
		$count = 0;

		foreach ( $order->get_items() as $item ) {
			$count += (int) $item->get_quantity();
		}

		return $count;
	}

	/**
	 * Number of distinct line items.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	private static function count_distinct_items( WC_Order $order ) {
		return count( $order->get_items() );
	}

	/**
	 * The authenticated customer.
	 *
	 * @return int
	 */
	private static function current_customer_id() {
		return WCP_Auth::current_customer_id();
	}

	/**
	 * Statuses a customer is shown.
	 *
	 * `wc_get_order_statuses()` excludes internal states such as `trash` and
	 * `checkout-draft`, and includes any status the store has registered.
	 *
	 * @param bool $prefixed Whether to return `wc-` prefixed keys.
	 * @return string[]
	 */
	private static function visible_statuses( $prefixed = true ) {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return $prefixed
				? array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed' )
				: array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
		}

		$statuses = array_keys( wc_get_order_statuses() );

		if ( $prefixed ) {
			return $statuses;
		}

		return array_map( array( __CLASS__, 'strip_status_prefix' ), $statuses );
	}

	/**
	 * Drop WooCommerce's `wc-` status prefix.
	 *
	 * @param string $status Prefixed status.
	 * @return string
	 */
	public static function strip_status_prefix( $status ) {
		return WCP_Order_Status::normalize( $status );
	}

	/**
	 * Clamp a requested page number.
	 *
	 * @param mixed $page Untrusted page value.
	 * @return int
	 */
	public static function sanitize_page( $page ) {
		$page = is_scalar( $page ) ? absint( $page ) : 1;

		return max( 1, $page );
	}

	/**
	 * Clamp a requested page size.
	 *
	 * @param mixed $per_page Untrusted page size.
	 * @return int
	 */
	public static function sanitize_per_page( $per_page ) {
		$per_page = is_scalar( $per_page ) ? absint( $per_page ) : self::DEFAULT_PER_PAGE;

		if ( $per_page <= 0 ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		return min( self::MAX_PER_PAGE, $per_page );
	}
}
