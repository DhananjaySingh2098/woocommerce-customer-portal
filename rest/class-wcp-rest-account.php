<?php
/**
 * REST routes for profile and addresses.
 *
 * The plugin's first mutating endpoints, so the gate is stricter than the read
 * routes in `WCP_REST_Orders`:
 *
 *   - authenticated, and allowed to view the portal;
 *   - carrying a valid `wcp_portal_action` nonce, checked explicitly. WordPress
 *     enforces `wp_rest` for cookie auth before this runs, but `wp_rest` covers
 *     the entire REST API. A second, portal-scoped nonce means holding a
 *     general REST nonce is not enough to change someone's email address.
 *
 * Neither route accepts a user or customer ID. The subject is always the
 * session, resolved inside `WCP_Profile` / `WCP_Addresses`, so there is no
 * parameter an attacker could aim at another account.
 *
 * The models own validation; these handlers only translate. A `WP_Error`
 * carrying a `fields` map is returned as-is, which is what lets the browser and
 * the no-JavaScript form path render identical field-level errors.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * `wcp/v1` profile and address routes.
 */
class WCP_REST_Account {

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
			'/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => array( $this, 'check_read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => array( $this, 'check_write' ),
					'args'                => $this->profile_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/addresses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_addresses' ),
					'permission_callback' => array( $this, 'check_read' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/addresses/(?P<type>billing|shipping)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_address' ),
					'permission_callback' => array( $this, 'check_read' ),
					'args'                => $this->address_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_address' ),
					'permission_callback' => array( $this, 'check_write' ),
					'args'                => $this->address_args(),
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
	 * Gate for read routes.
	 *
	 * @param WP_REST_Request|null $request Request, used to find the route's section.
	 * @return true|WP_Error
	 */
	public function check_read( $request = null ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'wcp_not_authenticated',
				__( 'You must be signed in to do that.', 'woocommerce-customer-portal' ),
				array( 'status' => 401 )
			);
		}

		if ( ! WCP_Security::current_user_can_view_portal() ) {
			return new WP_Error(
				'wcp_forbidden',
				__( 'You do not have permission to do that.', 'woocommerce-customer-portal' ),
				array( 'status' => 403 )
			);
		}

		// The route's section must be switched on. The sidebar hides a disabled
		// section; this is what makes it actually unavailable.
		$section = $this->section_for( $request );

		if ( ! WCP_Settings::is_enabled() || ( '' !== $section && ! WCP_Settings::section_enabled( $section ) ) ) {
			return new WP_Error(
				'wcp_section_disabled',
				__( 'That part of the portal is not available.', 'woocommerce-customer-portal' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Which portal section a request belongs to.
	 *
	 * @param WP_REST_Request|null $request Request.
	 * @return string `profile`, `addresses`, or an empty string.
	 */
	private function section_for( $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return '';
		}

		$route = (string) $request->get_route();

		if ( false !== strpos( $route, '/profile' ) ) {
			return 'profile';
		}

		if ( false !== strpos( $route, '/addresses' ) ) {
			return 'addresses';
		}

		return '';
	}

	/**
	 * Gate for write routes: everything the read gate requires, plus a nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_write( $request ) {
		$read = $this->check_read( $request );

		if ( is_wp_error( $read ) ) {
			return $read;
		}

		if ( ! WCP_Security::verify_rest_nonce( $request ) ) {
			return new WP_Error(
				'wcp_invalid_nonce',
				__( 'Your session has expired. Refresh the page and try again.', 'woocommerce-customer-portal' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Profile
	|--------------------------------------------------------------------------
	*/

	/**
	 * `GET /wcp/v1/profile`
	 *
	 * @return WP_REST_Response
	 */
	public function get_profile() {
		return rest_ensure_response(
			array(
				'fields' => $this->public_profile_fields(),
				'values' => WCP_Profile::get_values(),
			)
		);
	}

	/**
	 * `POST /wcp/v1/profile`
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_profile( $request ) {
		$input = array();

		// Only declared keys are forwarded. `WCP_Profile` allowlists again;
		// this is the outer of two independent gates, not the only one.
		foreach ( array_keys( $this->profile_args() ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$input[ $key ] = $request->get_param( $key );
			}
		}

		$result = WCP_Profile::update( $input );

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		return rest_ensure_response(
			array(
				'saved'   => true,
				'values'  => $result,
				'message' => __( 'Profile updated.', 'woocommerce-customer-portal' ),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Addresses
	|--------------------------------------------------------------------------
	*/

	/**
	 * `GET /wcp/v1/addresses`
	 *
	 * @return WP_REST_Response
	 */
	public function get_addresses() {
		$payload = array();

		foreach ( WCP_Addresses::get_types() as $type ) {
			$payload[ $type ] = array(
				'label'  => WCP_Addresses::get_label( $type ),
				'isset'  => WCP_Addresses::has_address( $type ),
				'values' => WCP_Addresses::get_values( $type ),
			);
		}

		return rest_ensure_response( $payload );
	}

	/**
	 * `GET /wcp/v1/addresses/{type}`
	 *
	 * Accepts an optional `country`, which is how the form asks what the field
	 * set becomes when the customer picks a different one. WooCommerce's locale
	 * rules stay on the server; the browser only renders what comes back.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_address( $request ) {
		$type = WCP_Addresses::sanitize_type( $request->get_param( 'type' ) );

		if ( '' === $type ) {
			return $this->unknown_type();
		}

		$country = $request->get_param( 'country' );
		$country = ( null === $country || '' === $country )
			? null
			: WCP_Addresses::sanitize_country( $country, $type );

		$fields = WCP_Addresses::get_fields( $type, $country );

		return rest_ensure_response(
			array(
				'type'   => $type,
				'label'  => WCP_Addresses::get_label( $type ),
				'fields' => array_values( array_map( array( $this, 'public_address_field' ), $fields ) ),
				'values' => WCP_Addresses::get_values( $type ),
			)
		);
	}

	/**
	 * `POST /wcp/v1/addresses/{type}`
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_address( $request ) {
		$type = WCP_Addresses::sanitize_type( $request->get_param( 'type' ) );

		if ( '' === $type ) {
			return $this->unknown_type();
		}

		// The field set for this type is the allowlist. A `shipping_*` key in a
		// billing request is simply not read, so one route cannot write the
		// other's data.
		$allowed = array_keys( WCP_Addresses::get_fields( $type ) );
		$body    = (array) $request->get_params();
		$input   = array();

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $body ) ) {
				$input[ $key ] = $body[ $key ];
			}
		}

		$result = WCP_Addresses::update( $type, $input );

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		return rest_ensure_response(
			array(
				'saved'     => true,
				'type'      => $type,
				'values'    => $result,
				'formatted' => WCP_Addresses::get_formatted( $type ),
				'message'   => sprintf(
					/* translators: %s: address label, e.g. "Billing address". */
					__( '%s saved.', 'woocommerce-customer-portal' ),
					WCP_Addresses::get_label( $type )
				),
			)
		);
	}

	/**
	 * Turn a model error into a response, attaching `Retry-After` when throttled.
	 *
	 * Returning the `WP_Error` directly would produce the right status and body
	 * but no header, and a 429 without `Retry-After` tells a client to back off
	 * without saying for how long. `rest_convert_error_to_response()` is used so
	 * the body keeps exactly the shape every other error on these routes has.
	 *
	 * @param WP_Error $error Model error.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function error_response( WP_Error $error ) {
		$retry = WCP_Rate_Limit::retry_after( $error );

		if ( ! $retry || ! function_exists( 'rest_convert_error_to_response' ) ) {
			return $error;
		}

		$response = rest_convert_error_to_response( $error );
		$response->header( 'Retry-After', (string) $retry );

		return $response;
	}

	/*
	|--------------------------------------------------------------------------
	| Shaping
	|--------------------------------------------------------------------------
	*/

	/**
	 * Profile fields, without anything internal.
	 *
	 * @return array
	 */
	private function public_profile_fields() {
		$out = array();

		foreach ( WCP_Profile::get_fields() as $key => $field ) {
			$out[] = array(
				'key'      => $key,
				'label'    => isset( $field['label'] ) ? $field['label'] : $key,
				'type'     => isset( $field['type'] ) ? $field['type'] : 'text',
				'required' => ! empty( $field['required'] ),
				'hint'     => isset( $field['hint'] ) ? $field['hint'] : '',
			);
		}

		return $out;
	}

	/**
	 * One address field, shaped for a client.
	 *
	 * @param array $field Prepared definition.
	 * @return array
	 */
	public function public_address_field( array $field ) {
		return array(
			'key'         => $field['key'],
			'label'       => $field['label'],
			'type'        => $field['type'],
			'required'    => $field['required'],
			'half'        => $field['half'],
			'placeholder' => $field['placeholder'],
			'options'     => $field['options'],
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Schema
	|--------------------------------------------------------------------------
	*/

	/**
	 * Accepted profile parameters.
	 *
	 * Passwords are deliberately not sanitised: any transformation would change
	 * the secret. They are compared and hashed, never stored or echoed.
	 *
	 * @return array
	 */
	private function profile_args() {
		$args = array();

		foreach ( WCP_Profile::get_fields() as $key => $field ) {
			$args[ $key ] = array(
				'type'              => 'string',
				'required'          => false,
				// Uniformly `sanitize_text_field`, including for the email:
				// `sanitize_email()` would silently empty an invalid address
				// before the model could tell the customer it was invalid.
				// `WCP_Profile` sanitises it properly once validated.
				'sanitize_callback' => 'sanitize_text_field',
			);
		}

		foreach ( array( 'current_password', 'new_password', 'confirm_password' ) as $key ) {
			$args[ $key ] = array(
				'type'     => 'string',
				'required' => false,
			);
		}

		return $args;
	}

	/**
	 * Accepted address parameters.
	 *
	 * The address fields themselves are not declared here: WooCommerce decides
	 * what they are per country, and `update_address()` allowlists against that
	 * definition at request time.
	 *
	 * @return array
	 */
	private function address_args() {
		return array(
			'type'    => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => WCP_Addresses::get_types(),
			),
			'country' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * The response for an address type that does not exist.
	 *
	 * @return WP_Error
	 */
	private function unknown_type() {
		return new WP_Error(
			'wcp_invalid_address_type',
			__( 'Unknown address type.', 'woocommerce-customer-portal' ),
			array( 'status' => 404 )
		);
	}
}
