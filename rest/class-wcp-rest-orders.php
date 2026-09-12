<?php
/**
 * REST routes for orders.
 *
 * These endpoints are a second consumer of `WCP_Orders`, not a second
 * implementation. Both the server-rendered templates and these routes call the
 * same repository, so the ownership check cannot be enforced in one place and
 * forgotten in the other -- there is only one place.
 *
 * On nonces: for a browser request these routes are cookie-authenticated, and
 * WordPress refuses to resolve a cookie into a logged-in user unless the
 * request carries a valid `wp_rest` nonce in `X-WP-Nonce`. That check happens
 * in `rest_cookie_check_errors()`, before any permission callback runs, so a
 * nonce-less browser request arrives here as a logged-out visitor and is
 * rejected with a 401 -- verified, not assumed.
 *
 * Re-checking the nonce here would therefore add nothing, and would actively
 * break the auth schemes that legitimately carry no nonce at all, such as
 * application passwords. So the permission callback checks identity and
 * capability, and leaves nonce enforcement to the layer that owns it.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * `wcp/v1` order routes.
 */
class WCP_REST_Orders {

	/**
	 * Route namespace.
	 */
	const NAMESPACE_V1 = 'wcp/v1';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/orders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_orders' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->collection_args(),
				),
				'schema' => array( $this, 'get_collection_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/orders/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Order ID.', 'woocommerce-customer-portal' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Permissions
	|--------------------------------------------------------------------------
	*/

	/**
	 * Gate every route in this controller.
	 *
	 * Deliberately does not consider the requested order at all. Whether *this*
	 * order belongs to *this* customer is decided by `WCP_Orders`, which owns
	 * that rule; duplicating it here would create a second copy to keep in sync.
	 * This callback answers only "may this session read its own orders", and
	 * nonce enforcement is left to WordPress -- see the class doc block.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_permission( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress passes the request to every permission callback; this one gates on the session alone.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'wcp_not_authenticated',
				__( 'You must be signed in to view orders.', 'woocommerce-customer-portal' ),
				array( 'status' => 401 )
			);
		}

		if ( ! WCP_Security::current_user_can_view_portal() ) {
			return new WP_Error(
				'wcp_forbidden',
				__( 'You do not have permission to view this.', 'woocommerce-customer-portal' ),
				array( 'status' => 403 )
			);
		}

		// Orders switched off by an administrator are not readable by API
		// either -- the sidebar entry is the visible half of the setting, and
		// this is the enforced half.
		if ( ! WCP_Settings::is_enabled() || ! WCP_Settings::section_enabled( 'orders' ) ) {
			return new WP_Error(
				'wcp_section_disabled',
				__( 'That part of the portal is not available.', 'woocommerce-customer-portal' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate an order ID.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function validate_id( $value ) {
		return WCP_Security::positive_int( $value ) > 0;
	}

	/*
	|--------------------------------------------------------------------------
	| Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * `GET /wcp/v1/orders`
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_orders( $request ) {
		$result = WCP_Orders::get_orders(
			array(
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);

		$response = rest_ensure_response(
			array(
				'orders'     => array_map( array( $this, 'prepare_summary' ), $result['orders'] ),
				'pagination' => array(
					'page'        => $result['page'],
					'per_page'    => $result['per_page'],
					'total'       => $result['total'],
					'total_pages' => $result['total_pages'],
					'has_prev'    => $result['has_prev'],
					'has_next'    => $result['has_next'],
				),
			)
		);

		// The same numbers as the body, in the headers WordPress clients expect.
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['total_pages'] );

		return $response;
	}

	/**
	 * `GET /wcp/v1/orders/{id}`
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_order( $request ) {
		$order = WCP_Orders::get_order( $request->get_param( 'id' ) );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		return rest_ensure_response( $this->prepare_detail( $order ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Response shaping
	|--------------------------------------------------------------------------
	*/

	/**
	 * Strip display-only HTML out of a summary for API consumers.
	 *
	 * The templates want WooCommerce's formatted markup; an API consumer wants
	 * text it can put anywhere. Both come from the same DTO, so the two views
	 * can never drift apart in content -- only in presentation.
	 *
	 * @param array $order Summary DTO.
	 * @return array
	 */
	public function prepare_summary( array $order ) {
		return array(
			'id'           => (int) $order['id'],
			'number'       => (string) $order['number'],
			'date'         => (string) $order['date_iso'],
			'date_label'   => (string) $order['date_label'],
			'status'       => array(
				'slug'  => $order['status']['slug'],
				'label' => $order['status']['label'],
				'tone'  => $order['status']['tone'],
			),
			'item_count'   => (int) $order['item_count'],
			'items_label'  => (string) $order['items_label'],
			'items_teaser' => (string) $order['items_teaser'],
			'total'        => $this->to_text( $order['total_html'] ),
			'currency'     => (string) $order['currency'],
		);
	}

	/**
	 * Full order for API consumers.
	 *
	 * @param array $order Detail DTO.
	 * @return array
	 */
	public function prepare_detail( array $order ) {
		$payload = $this->prepare_summary( $order );

		$payload['timeline'] = array_values( (array) $order['timeline'] );

		$payload['items'] = array_map(
			function ( $item ) {
				return array(
					'id'        => (int) $item['id'],
					'name'      => (string) $item['name'],
					'quantity'  => (int) $item['quantity'],
					'sku'       => (string) $item['sku'],
					'image'     => (string) $item['image'],
					'permalink' => (string) $item['permalink'],
					'meta'      => $this->to_text( $item['meta_html'] ),
					'total'     => $this->to_text( $item['total_html'] ),
				);
			},
			(array) $order['items']
		);

		$payload['totals'] = array_map(
			function ( $row ) {
				return array(
					'key'      => (string) $row['key'],
					'label'    => $this->to_text( $row['label'] ),
					'value'    => $this->to_text( $row['value'] ),
					'is_total' => (bool) $row['is_total'],
				);
			},
			(array) $order['totals']
		);

		$payload['billing']  = $this->to_text( $order['billing_html'] );
		$payload['shipping'] = $this->to_text( $order['shipping_html'] );

		$payload['payment_method']  = (string) $order['payment_method'];
		$payload['shipping_method'] = (string) $order['shipping_method'];
		$payload['customer_note']   = (string) $order['customer_note'];

		$payload['downloads'] = array_map(
			function ( $download ) {
				return array(
					'name'         => (string) $download['name'],
					'product_name' => (string) $download['product_name'],
					'url'          => esc_url_raw( $download['url'] ),
					'remaining'    => (string) $download['remaining'],
					'expires'      => (string) $download['expires'],
				);
			},
			(array) $order['downloads']
		);

		return $payload;
	}

	/**
	 * Reduce WooCommerce's display markup to plain text.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private function to_text( $html ) {
		$text = wp_strip_all_tags( (string) $html, true );

		return trim( html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Schema
	|--------------------------------------------------------------------------
	*/

	/**
	 * Arguments accepted by the collection route.
	 *
	 * `per_page` is bounded here as well as in the repository. The repository's
	 * clamp is the one that protects the database; this one gives a caller a
	 * clear 400 instead of silently returning something other than what was
	 * asked for.
	 *
	 * @return array
	 */
	private function collection_args() {
		return array(
			'page'     => array(
				'description'       => __( 'Page of orders to return.', 'woocommerce-customer-portal' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __( 'Orders per page.', 'woocommerce-customer-portal' ),
				'type'              => 'integer',
				'default'           => WCP_Orders::DEFAULT_PER_PAGE,
				'minimum'           => 1,
				'maximum'           => WCP_Orders::MAX_PER_PAGE,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Public schema for the collection route.
	 *
	 * @return array
	 */
	public function get_collection_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wcp_order',
			'type'       => 'object',
			'properties' => array(
				'id'           => array( 'type' => 'integer' ),
				'number'       => array( 'type' => 'string' ),
				'date'         => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'date_label'   => array( 'type' => 'string' ),
				'status'       => array(
					'type'       => 'object',
					'properties' => array(
						'slug'  => array( 'type' => 'string' ),
						'label' => array( 'type' => 'string' ),
						'tone'  => array( 'type' => 'string' ),
					),
				),
				'item_count'   => array( 'type' => 'integer' ),
				'items_label'  => array( 'type' => 'string' ),
				'items_teaser' => array( 'type' => 'string' ),
				'total'        => array( 'type' => 'string' ),
				'currency'     => array( 'type' => 'string' ),
			),
		);
	}
}
