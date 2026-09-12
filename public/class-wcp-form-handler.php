<?php
/**
 * Server-side form submission for profile and addresses.
 *
 * The portal's forms are progressively enhanced: script submits them to the
 * REST routes and renders the result in place, but with JavaScript unavailable
 * they post normally and land here. Both paths call the same
 * `WCP_Profile::update()` / `WCP_Addresses::update()`, so validation, the
 * allowlist and the ownership rule exist once and cannot drift apart -- the
 * same reason the templates and the REST routes share one orders repository.
 *
 * Post/Redirect/Get, so a refresh after saving never resubmits. The outcome
 * travels in a short-lived transient rather than the URL: field-level errors
 * and the values the customer typed are too big for a query string, and a URL
 * carrying "your password was wrong" is a URL that ends up in a browser
 * history, a bookmark or a support ticket.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles non-JavaScript portal form posts.
 */
class WCP_Form_Handler {

	/**
	 * Field naming the form being submitted.
	 */
	const FORM_FIELD = 'wcp_form';

	/**
	 * Query argument set on the redirect so the page knows to look for a flash.
	 */
	const RESULT_VAR = 'wcp_result';

	/**
	 * Transient prefix for one-shot form results.
	 */
	const FLASH_PREFIX = 'wcp_flash_';

	/**
	 * How long a result survives the redirect.
	 */
	const FLASH_TTL = 60;

	/**
	 * Memoised flash for this request.
	 *
	 * @var array|null
	 */
	private static $flash = null;

	/**
	 * Handle a submission, if this request is one.
	 *
	 * Runs on `template_redirect`, which is early enough to redirect before a
	 * byte of output has been sent.
	 *
	 * @return void
	 */
	public function handle() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below, once we know this is our form.
		if ( ! isset( $_POST[ self::FORM_FIELD ] ) || ! is_scalar( $_POST[ self::FORM_FIELD ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
		$form = sanitize_key( wp_unslash( (string) $_POST[ self::FORM_FIELD ] ) );

		if ( ! in_array( $form, array( 'profile', 'address' ), true ) ) {
			return;
		}

		$redirect = $this->redirect_target();

		if ( ! WCP_Auth::can_view_portal() ) {
			$this->finish( $redirect, $this->error_flash( $form, __( 'You must be signed in to do that.', 'woocommerce-customer-portal' ) ) );
		}

		if ( ! WCP_Security::verify_nonce() ) {
			$this->finish(
				$redirect,
				$this->error_flash( $form, __( 'Your session has expired. Refresh the page and try again.', 'woocommerce-customer-portal' ) )
			);
		}

		// A section an administrator has switched off is not writable either.
		// Hiding the form is not enough: the POST is the direct access.
		$section = ( 'profile' === $form ) ? 'profile' : 'addresses';

		if ( ! WCP_Settings::is_enabled() || ! WCP_Settings::section_enabled( $section ) ) {
			$this->finish(
				$redirect,
				$this->error_flash( $form, __( 'That part of the portal is not available.', 'woocommerce-customer-portal' ) )
			);
		}

		if ( 'profile' === $form ) {
			$this->handle_profile( $redirect );
		}

		$this->handle_address( $redirect );
	}

	/*
	|--------------------------------------------------------------------------
	| Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Profile submission.
	 *
	 * @param string $redirect Where to send the customer afterwards.
	 * @return void
	 */
	private function handle_profile( $redirect ) {
		$input = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle().
		$post = wp_unslash( $_POST );

		foreach ( array_keys( WCP_Profile::get_fields() ) as $key ) {
			if ( isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ) {
				$input[ $key ] = (string) $post[ $key ];
			}
		}

		foreach ( array( 'current_password', 'new_password', 'confirm_password' ) as $key ) {
			if ( isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ) {
				$input[ $key ] = (string) $post[ $key ];
			}
		}

		$result = WCP_Profile::update( $input );

		if ( is_wp_error( $result ) ) {
			$this->finish( $redirect, $this->wp_error_flash( 'profile', $result, $input ) );
		}

		$this->finish(
			$redirect,
			array(
				'form'    => 'profile',
				'status'  => 'success',
				'message' => __( 'Profile updated.', 'woocommerce-customer-portal' ),
			)
		);
	}

	/**
	 * Address submission.
	 *
	 * @param string $redirect Where to send the customer afterwards.
	 * @return void
	 */
	private function handle_address( $redirect ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle().
		$post = wp_unslash( $_POST );

		$type = isset( $post['wcp_address_type'] ) ? WCP_Addresses::sanitize_type( $post['wcp_address_type'] ) : '';

		if ( '' === $type ) {
			$this->finish( $redirect, $this->error_flash( 'address', __( 'Unknown address type.', 'woocommerce-customer-portal' ) ) );
		}

		$country_key = $type . '_country';
		$country     = isset( $post[ $country_key ] ) && is_scalar( $post[ $country_key ] )
			? WCP_Addresses::sanitize_country( (string) $post[ $country_key ], $type )
			: null;

		$input = array();

		foreach ( array_keys( WCP_Addresses::get_fields( $type, $country ) ) as $key ) {
			if ( isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ) {
				$input[ $key ] = (string) $post[ $key ];
			}
		}

		$result = WCP_Addresses::update( $type, $input );

		if ( is_wp_error( $result ) ) {
			$flash            = $this->wp_error_flash( 'address', $result, $input );
			$flash['address'] = $type;

			// Back to the form, not the list. Field errors are only actionable
			// where the fields are, and the list has none.
			$this->finish( $this->address_url( $type ), $flash );
		}

		$this->finish(
			$redirect,
			array(
				'form'    => 'address',
				'address' => $type,
				'status'  => 'success',
				'message' => sprintf(
					/* translators: %s: address label, e.g. "Billing address". */
					__( '%s saved.', 'woocommerce-customer-portal' ),
					WCP_Addresses::get_label( $type )
				),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Flash storage
	|--------------------------------------------------------------------------
	*/

	/**
	 * The edit URL for one address type.
	 *
	 * @param string $type Address type.
	 * @return string
	 */
	private function address_url( $type ) {
		$navigation = new WCP_Navigation();

		return $navigation->get_address_url( $type, WCP_Auth::current_url() );
	}

	/**
	 * Read and clear this request's flash, if any.
	 *
	 * One-shot: reading deletes it, so a later reload shows a clean form rather
	 * than a stale "saved" banner.
	 *
	 * @return array Empty when there is nothing to show.
	 */
	public static function consume_flash() {
		if ( null !== self::$flash ) {
			return self::$flash;
		}

		self::$flash = array();

		if ( '' === WCP_Security::get_query_arg( self::RESULT_VAR ) ) {
			return self::$flash;
		}

		$user_id = WCP_Auth::current_customer_id();

		if ( $user_id <= 0 ) {
			return self::$flash;
		}

		$stored = get_transient( self::FLASH_PREFIX . $user_id );

		if ( is_array( $stored ) ) {
			self::$flash = $stored;
			delete_transient( self::FLASH_PREFIX . $user_id );
		}

		return self::$flash;
	}

	/**
	 * Store a flash and redirect.
	 *
	 * @param string $redirect Destination.
	 * @param array  $flash    Payload.
	 * @return void
	 */
	private function finish( $redirect, array $flash ) {
		$user_id = WCP_Auth::current_customer_id();

		if ( $user_id > 0 ) {
			set_transient( self::FLASH_PREFIX . $user_id, $flash, self::FLASH_TTL );
		}

		wp_safe_redirect( add_query_arg( self::RESULT_VAR, '1', $redirect ) );
		exit;
	}

	/**
	 * Turn a model `WP_Error` into a flash.
	 *
	 * @param string   $form  Form name.
	 * @param WP_Error $error Model error.
	 * @param array    $input Submitted values.
	 * @return array
	 */
	private function wp_error_flash( $form, WP_Error $error, array $input ) {
		$data = $error->get_error_data();

		return array(
			'form'    => $form,
			'status'  => 'error',
			'message' => $error->get_error_message(),
			'fields'  => isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array(),
			// Repopulates the form so a rejected submission does not lose what
			// the customer typed. Passwords are stripped: they must never be
			// written to a transient, and never re-rendered into markup.
			'values'  => $this->strip_secrets( $input ),
		);
	}

	/**
	 * A flash carrying only a message.
	 *
	 * @param string $form    Form name.
	 * @param string $message Message.
	 * @return array
	 */
	private function error_flash( $form, $message ) {
		return array(
			'form'    => $form,
			'status'  => 'error',
			'message' => $message,
			'fields'  => array(),
			'values'  => array(),
		);
	}

	/**
	 * Remove password fields from a value map.
	 *
	 * @param array $input Submitted values.
	 * @return array
	 */
	private function strip_secrets( array $input ) {
		unset( $input['current_password'], $input['new_password'], $input['confirm_password'] );

		return $input;
	}

	/**
	 * Where to send the customer after handling a submission.
	 *
	 * Built from the queried page rather than from `REQUEST_URI`, and passed
	 * through `wp_safe_redirect()`, so a crafted referrer cannot bounce anyone
	 * off-site.
	 *
	 * @return string
	 */
	private function redirect_target() {
		$base = WCP_Auth::current_url();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle(); this only picks the return view.
		$section = isset( $_POST['wcp_return_section'] ) ? sanitize_key( wp_unslash( (string) $_POST['wcp_return_section'] ) ) : '';

		if ( '' === $section ) {
			return $base;
		}

		$navigation = new WCP_Navigation();
		$section    = WCP_Security::sanitize_section( $section, $navigation->get_slugs(), WCP_Navigation::DEFAULT_SECTION );

		return $navigation->get_section_url( $section, $base );
	}
}
