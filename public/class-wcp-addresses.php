<?php
/**
 * Billing and shipping addresses: read, validate, persist.
 *
 * The field set is not written here. It comes from
 * `WC()->countries->get_address_fields()`, which is what makes the form correct
 * in every locale WooCommerce knows about: a German address asks for a
 * different set, in a different order, with different required flags and
 * different labels than a Japanese one, and a store that has customised its
 * checkout fields through WooCommerce's filters gets those customisations here
 * too, for free.
 *
 * That definition doubles as the allowlist. `update()` reads only keys that
 * WooCommerce declared for the requested address type, so a crafted payload
 * cannot write arbitrary customer meta -- and cannot write billing data
 * through the shipping route.
 *
 * The customer is always `get_current_user_id()`. No method accepts a customer
 * ID, so no request can address another customer's record.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * The authenticated customer's addresses.
 */
class WCP_Addresses {

	/**
	 * Address types this class will act on.
	 *
	 * @return string[]
	 */
	public static function get_types() {
		return array( 'billing', 'shipping' );
	}

	/**
	 * Reduce an arbitrary value to a known address type.
	 *
	 * @param mixed $type Untrusted value.
	 * @return string Empty string when unrecognised.
	 */
	public static function sanitize_type( $type ) {
		$type = is_scalar( $type ) ? sanitize_key( (string) $type ) : '';

		return in_array( $type, self::get_types(), true ) ? $type : '';
	}

	/**
	 * Human label for an address type.
	 *
	 * @param string $type Address type.
	 * @return string
	 */
	public static function get_label( $type ) {
		return 'shipping' === $type
			? __( 'Shipping address', 'woocommerce-customer-portal' )
			: __( 'Billing address', 'woocommerce-customer-portal' );
	}

	/*
	|--------------------------------------------------------------------------
	| Field definitions
	|--------------------------------------------------------------------------
	*/

	/**
	 * WooCommerce's field definitions for one address type.
	 *
	 * @param string      $type    `billing` or `shipping`.
	 * @param string|null $country Optional country override; defaults to the
	 *                             customer's stored country.
	 * @return array<string,array> Keyed by prefixed field name, in WooCommerce's order.
	 */
	public static function get_fields( $type, $country = null ) {
		$type = self::sanitize_type( $type );

		if ( '' === $type || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}

		if ( null === $country ) {
			$country = self::get_country( $type );
		}

		$country = self::sanitize_country( $country, $type );
		$fields  = WC()->countries->get_address_fields( $country, $type . '_' );

		if ( ! is_array( $fields ) ) {
			return array();
		}

		// WooCommerce orders by `priority`; the array order is not reliable.
		uasort( $fields, array( __CLASS__, 'sort_by_priority' ) );

		$prepared = array();

		foreach ( $fields as $key => $field ) {
			$prepared[ $key ] = self::prepare_field( $key, (array) $field, $type, $country );
		}

		return $prepared;
	}

	/**
	 * Normalise one WooCommerce field definition for the portal's renderer.
	 *
	 * @param string $key     Prefixed field key.
	 * @param array  $field   WooCommerce definition.
	 * @param string $type    Address type.
	 * @param string $country Resolved country.
	 * @return array
	 */
	private static function prepare_field( $key, array $field, $type, $country ) {
		$input_type = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$options    = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

		if ( 'country' === $input_type ) {
			$input_type = 'select';
			$options    = self::get_countries( $type );
		}

		if ( 'state' === $input_type ) {
			$states = self::get_states( $country );

			// A country with no registered states gets a free-text field --
			// which is exactly what WooCommerce's own checkout does.
			if ( empty( $states ) ) {
				$input_type = 'text';
				$options    = array();
			} else {
				$input_type = 'select';
				$options    = $states;
			}
		}

		// Anything WooCommerce reports that this renderer has no control for
		// falls back to a text input rather than being dropped: the value still
		// round-trips, which beats silently losing a store's custom field.
		if ( ! in_array( $input_type, array( 'text', 'email', 'tel', 'select' ), true ) ) {
			$input_type = 'text';
		}

		return array(
			'key'          => $key,
			'label'        => isset( $field['label'] ) ? (string) $field['label'] : $key,
			'type'         => $input_type,
			'required'     => ! empty( $field['required'] ),
			'options'      => $options,
			'placeholder'  => isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '',
			'autocomplete' => isset( $field['autocomplete'] ) ? (string) $field['autocomplete'] : '',
			'validate'     => isset( $field['validate'] ) ? (array) $field['validate'] : array(),
			// WooCommerce marks half-width fields with `form-row-first` /
			// `form-row-last`; the portal uses that to pair them on one line.
			'half'         => self::is_half_width( $field ),
			'priority'     => isset( $field['priority'] ) ? (int) $field['priority'] : 0,
		);
	}

	/**
	 * Whether WooCommerce declared this field as half-width.
	 *
	 * @param array $field WooCommerce definition.
	 * @return bool
	 */
	private static function is_half_width( array $field ) {
		$classes = isset( $field['class'] ) ? (array) $field['class'] : array();

		foreach ( $classes as $class ) {
			if ( in_array( $class, array( 'form-row-first', 'form-row-last' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sort comparator for WooCommerce field priority.
	 *
	 * @param array $a First field.
	 * @param array $b Second field.
	 * @return int
	 */
	private static function sort_by_priority( $a, $b ) {
		$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 0;
		$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 0;

		if ( $pa === $pb ) {
			return 0;
		}

		return ( $pa < $pb ) ? -1 : 1;
	}

	/*
	|--------------------------------------------------------------------------
	| Values
	|--------------------------------------------------------------------------
	*/

	/**
	 * Stored values for one address type.
	 *
	 * @param string $type Address type.
	 * @return array<string,string>
	 */
	public static function get_values( $type ) {
		$type     = self::sanitize_type( $type );
		$customer = self::get_customer();

		if ( '' === $type || ! $customer ) {
			return array();
		}

		$values = array();

		foreach ( array_keys( self::get_fields( $type ) ) as $key ) {
			$values[ $key ] = self::read_value( $customer, $key );
		}

		return $values;
	}

	/**
	 * Values for rendering an edit form.
	 *
	 * Identical to `get_values()` except that an unset country is filled in
	 * with the one the field set was built for. Without this the form
	 * contradicts itself: the fields would be laid out for the store's base
	 * country -- asking for a State and a ZIP Code -- while the country control
	 * itself showed nothing selected.
	 *
	 * Kept separate from `get_values()` so that `has_address()` still sees an
	 * empty address as empty rather than as one with a country.
	 *
	 * @param string $type Address type.
	 * @return array<string,string>
	 */
	public static function get_form_values( $type ) {
		$type   = self::sanitize_type( $type );
		$values = self::get_values( $type );

		if ( '' === $type ) {
			return $values;
		}

		$key = $type . '_country';

		if ( array_key_exists( $key, $values ) && '' === $values[ $key ] ) {
			$values[ $key ] = self::get_country( $type );
		}

		return $values;
	}

	/**
	 * Whether an address has been filled in at all.
	 *
	 * @param string $type Address type.
	 * @return bool
	 */
	public static function has_address( $type ) {
		foreach ( self::get_values( $type ) as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * WooCommerce's own formatted rendering of a stored address.
	 *
	 * Uses the country's address format, so the preview card reads the way an
	 * address is written in that country rather than in a fixed order.
	 *
	 * @param string $type Address type.
	 * @return string HTML, or an empty string when unset.
	 */
	public static function get_formatted( $type ) {
		$type     = self::sanitize_type( $type );
		$customer = self::get_customer();

		if ( '' === $type || ! $customer || ! self::has_address( $type ) ) {
			return '';
		}

		$address = 'shipping' === $type
			? $customer->get_shipping()
			: $customer->get_billing();

		if ( ! is_array( $address ) ) {
			return '';
		}

		unset( $address['email'], $address['phone'] );

		return WC()->countries->get_formatted_address( $address );
	}

	/*
	|--------------------------------------------------------------------------
	| Update
	|--------------------------------------------------------------------------
	*/

	/**
	 * Validate and persist one address.
	 *
	 * @param string $type  Address type.
	 * @param array  $input Raw submitted values.
	 * @return array|WP_Error Fresh values on success.
	 */
	public static function update( $type, array $input ) {
		$type = self::sanitize_type( $type );

		if ( '' === $type ) {
			return new WP_Error(
				'wcp_invalid_address_type',
				__( 'Unknown address type.', 'woocommerce-customer-portal' ),
				array( 'status' => 404 )
			);
		}

		$customer = self::get_customer();

		if ( ! $customer ) {
			return new WP_Error(
				'wcp_not_authenticated',
				__( 'You must be signed in to do that.', 'woocommerce-customer-portal' ),
				array( 'status' => 401 )
			);
		}

		// The country decides which fields exist and which are required, so it
		// has to be resolved before anything else is validated.
		$limited = WCP_Rate_Limit::check( 'address' );

		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		WCP_Rate_Limit::record( 'address' );

		$country_key = $type . '_country';
		$country     = isset( $input[ $country_key ] ) && is_scalar( $input[ $country_key ] )
			? self::sanitize_country( (string) $input[ $country_key ], $type )
			: self::get_country( $type );

		$fields = self::get_fields( $type, $country );

		if ( empty( $fields ) ) {
			return new WP_Error(
				'wcp_address_unavailable',
				__( 'Address editing is unavailable right now.', 'woocommerce-customer-portal' ),
				array( 'status' => 503 )
			);
		}

		$errors = array();
		$clean  = array();

		foreach ( $fields as $key => $field ) {
			$raw = array_key_exists( $key, $input ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';

			$value = self::sanitize_field( $key, $raw, $field, $country );

			if ( $field['required'] && '' === $value ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: field label. */
					__( '%s is required.', 'woocommerce-customer-portal' ),
					$field['label']
				);
				continue;
			}

			$message = self::validate_field( $key, $value, $field, $country );

			if ( '' !== $message ) {
				$errors[ $key ] = $message;
				continue;
			}

			$clean[ $key ] = $value;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'wcp_invalid_address',
				_n(
					'Please correct the highlighted field.',
					'Please correct the highlighted fields.',
					count( $errors ),
					'woocommerce-customer-portal'
				),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		foreach ( $clean as $key => $value ) {
			self::write_value( $customer, $key, $value );
		}

		$customer->save();

		/**
		 * Fires after a customer updates one of their own addresses.
		 *
		 * @param string      $type     `billing` or `shipping`.
		 * @param array       $values   Persisted values.
		 * @param WC_Customer $customer The authenticated customer.
		 */
		do_action( 'wcp_address_updated', $type, $clean, $customer );

		return self::get_values( $type );
	}

	/*
	|--------------------------------------------------------------------------
	| Sanitisation and validation
	|--------------------------------------------------------------------------
	*/

	/**
	 * Sanitise one field by its declared type.
	 *
	 * @param string $key     Field key.
	 * @param string $raw     Raw value.
	 * @param array  $field   Prepared definition.
	 * @param string $country Resolved country.
	 * @return string
	 */
	private static function sanitize_field( $key, $raw, array $field, $country ) {
		$raw = trim( $raw );

		if ( self::field_is( $key, 'email' ) ) {
			return sanitize_email( $raw );
		}

		if ( self::field_is( $key, 'postcode' ) ) {
			// Normalises spacing and case to the country's convention, so
			// "sw1a1aa" is stored as "SW1A 1AA".
			return $raw ? wc_format_postcode( $raw, $country ) : '';
		}

		if ( self::field_is( $key, 'country' ) ) {
			return self::sanitize_country( $raw, self::type_of( $key ) );
		}

		return sanitize_text_field( $raw );
	}

	/**
	 * Validate one field's value.
	 *
	 * @param string $key     Field key.
	 * @param string $value   Sanitised value.
	 * @param array  $field   Prepared definition.
	 * @param string $country Resolved country.
	 * @return string Error message, or an empty string.
	 */
	private static function validate_field( $key, $value, array $field, $country ) {
		if ( '' === $value ) {
			return '';
		}

		// A select's value must be one of the options it offered. This is what
		// stops a crafted payload storing a state that does not exist in the
		// chosen country.
		if ( 'select' === $field['type'] && ! empty( $field['options'] ) && ! isset( $field['options'][ $value ] ) ) {
			return sprintf(
				/* translators: %s: field label. */
				__( 'Choose a valid %s.', 'woocommerce-customer-portal' ),
				strtolower( $field['label'] )
			);
		}

		if ( self::field_is( $key, 'email' ) && ! is_email( $value ) ) {
			return __( 'That email address does not look valid.', 'woocommerce-customer-portal' );
		}

		if ( self::field_is( $key, 'postcode' ) && class_exists( 'WC_Validation' ) ) {
			if ( ! WC_Validation::is_postcode( $value, $country ) ) {
				return __( 'That postcode is not valid for the selected country.', 'woocommerce-customer-portal' );
			}
		}

		if ( self::field_is( $key, 'phone' ) && class_exists( 'WC_Validation' ) ) {
			if ( ! WC_Validation::is_phone( $value ) ) {
				return __( 'That phone number does not look valid.', 'woocommerce-customer-portal' );
			}
		}

		return '';
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Whether a prefixed key names a particular field.
	 *
	 * @param string $key  Prefixed key, e.g. `billing_postcode`.
	 * @param string $name Unprefixed name, e.g. `postcode`.
	 * @return bool
	 */
	private static function field_is( $key, $name ) {
		return (bool) preg_match( '/^(billing|shipping)_' . preg_quote( $name, '/' ) . '$/', $key );
	}

	/**
	 * The address type a prefixed key belongs to.
	 *
	 * @param string $key Prefixed key.
	 * @return string
	 */
	private static function type_of( $key ) {
		return 0 === strpos( $key, 'shipping_' ) ? 'shipping' : 'billing';
	}

	/**
	 * The countries selectable for an address type.
	 *
	 * Billing and shipping can legitimately differ: a store may sell worldwide
	 * but ship to a shorter list.
	 *
	 * @param string $type Address type.
	 * @return array<string,string>
	 */
	public static function get_countries( $type ) {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}

		$countries = 'shipping' === $type
			? WC()->countries->get_shipping_countries()
			: WC()->countries->get_allowed_countries();

		return is_array( $countries ) ? $countries : array();
	}

	/**
	 * States registered for a country.
	 *
	 * @param string $country Country code.
	 * @return array<string,string>
	 */
	public static function get_states( $country ) {
		if ( '' === $country || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}

		$states = WC()->countries->get_states( $country );

		return is_array( $states ) ? $states : array();
	}

	/**
	 * Constrain a country code to the list the store actually offers.
	 *
	 * @param string $country Raw country code.
	 * @param string $type    Address type.
	 * @return string Empty string when not offered.
	 */
	public static function sanitize_country( $country, $type ) {
		$country   = strtoupper( sanitize_text_field( (string) $country ) );
		$countries = self::get_countries( $type );

		if ( isset( $countries[ $country ] ) ) {
			return $country;
		}

		return '';
	}

	/**
	 * The customer's stored country for an address type, or the store default.
	 *
	 * @param string $type Address type.
	 * @return string
	 */
	private static function get_country( $type ) {
		$customer = self::get_customer();

		if ( $customer ) {
			$stored = 'shipping' === $type ? $customer->get_shipping_country() : $customer->get_billing_country();
			$stored = self::sanitize_country( (string) $stored, $type );

			if ( '' !== $stored ) {
				return $stored;
			}
		}

		if ( function_exists( 'WC' ) && WC()->countries ) {
			$base = self::sanitize_country( WC()->countries->get_base_country(), $type );

			if ( '' !== $base ) {
				return $base;
			}
		}

		$countries = self::get_countries( $type );

		return $countries ? (string) key( $countries ) : '';
	}

	/**
	 * Read one prefixed field off the customer.
	 *
	 * @param WC_Customer $customer Customer.
	 * @param string      $key      Prefixed key.
	 * @return string
	 */
	private static function read_value( WC_Customer $customer, $key ) {
		$getter = 'get_' . $key;

		if ( is_callable( array( $customer, $getter ) ) ) {
			return (string) $customer->{$getter}();
		}

		// A field a store added through WooCommerce's filters lives in meta.
		return (string) $customer->get_meta( $key );
	}

	/**
	 * Write one prefixed field to the customer.
	 *
	 * Mirrors WooCommerce's own address handler: a declared property goes
	 * through its setter, anything else through meta.
	 *
	 * @param WC_Customer $customer Customer.
	 * @param string      $key      Prefixed key.
	 * @param string      $value    Sanitised value.
	 * @return void
	 */
	private static function write_value( WC_Customer $customer, $key, $value ) {
		$setter = 'set_' . $key;

		if ( is_callable( array( $customer, $setter ) ) ) {
			$customer->{$setter}( $value );

			return;
		}

		$customer->update_meta_data( $key, $value );
	}

	/**
	 * The authenticated customer as a WooCommerce customer object.
	 *
	 * @return WC_Customer|null
	 */
	private static function get_customer() {
		$user_id = WCP_Auth::current_customer_id();

		if ( $user_id <= 0 || ! class_exists( 'WC_Customer' ) ) {
			return null;
		}

		try {
			return new WC_Customer( $user_id );
		} catch ( Exception $e ) {
			return null;
		}
	}
}
