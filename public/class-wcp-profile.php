<?php
/**
 * Profile fields: read, validate, persist.
 *
 * The rules that hold everywhere in this class:
 *
 *   1. The user is `get_current_user_id()`. No method takes a user ID, so
 *      there is no code path that edits someone else's profile.
 *   2. Only the fields declared in `get_fields()` are ever read out of the
 *      submitted payload. Anything else in the request is discarded before
 *      validation, so a crafted payload cannot reach `wp_update_user()` --
 *      no mass assignment, and no arbitrary user meta.
 *   3. Passwords are WordPress's business. The current password is verified
 *      with `wp_check_password()` and the new one is written with
 *      `wp_update_user()`, which hashes it and invalidates other sessions.
 *      Nothing here implements authentication of its own.
 *
 * Validation failures come back as a `WP_Error` carrying a `fields` map, so the
 * REST route and the no-JavaScript form path render the same messages against
 * the same inputs.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * The authenticated customer's profile.
 */
class WCP_Profile {

	/**
	 * Minimum length WordPress itself will accept for a new password.
	 *
	 * WordPress has no hard minimum; this is the portal's floor, applied before
	 * the value reaches `wp_update_user()`.
	 */
	const MIN_PASSWORD_LENGTH = 8;

	/**
	 * Editable profile fields.
	 *
	 * This list *is* the allowlist. `update()` reads nothing outside it.
	 *
	 * @return array<string,array>
	 */
	public static function get_fields() {
		$fields = array(
			'first_name'   => array(
				'label'        => __( 'First name', 'woocommerce-customer-portal' ),
				'type'         => 'text',
				'required'     => false,
				'autocomplete' => 'given-name',
			),
			'last_name'    => array(
				'label'        => __( 'Last name', 'woocommerce-customer-portal' ),
				'type'         => 'text',
				'required'     => false,
				'autocomplete' => 'family-name',
			),
			'display_name' => array(
				'label'        => __( 'Display name', 'woocommerce-customer-portal' ),
				'type'         => 'text',
				'required'     => true,
				'hint'         => __( 'How your name appears across the store.', 'woocommerce-customer-portal' ),
				'autocomplete' => 'nickname',
			),
			'user_email'   => array(
				'label'        => __( 'Email address', 'woocommerce-customer-portal' ),
				'type'         => 'email',
				'required'     => true,
				'hint'         => __( 'Used for order updates and signing in.', 'woocommerce-customer-portal' ),
				'autocomplete' => 'email',
			),
		);

		/**
		 * Filter the editable profile fields.
		 *
		 * Removing a field removes it from the allowlist as well, so it can no
		 * longer be written. Adding one does not grant write access on its own:
		 * `apply_value()` only knows how to persist the built-in fields.
		 *
		 * @param array $fields Field definitions.
		 */
		return (array) apply_filters( 'wcp_profile_fields', $fields );
	}

	/**
	 * Current values for the authenticated customer.
	 *
	 * @return array<string,string>
	 */
	public static function get_values() {
		$user = WCP_Auth::current_user();

		if ( ! $user ) {
			return array();
		}

		return array(
			'first_name'   => (string) $user->first_name,
			'last_name'    => (string) $user->last_name,
			'display_name' => (string) $user->display_name,
			'user_email'   => (string) $user->user_email,
		);
	}

	/**
	 * Validate and persist a profile change.
	 *
	 * @param array $input Raw submitted values.
	 * @return array|WP_Error Fresh values on success.
	 */
	public static function update( array $input ) {
		$user = WCP_Auth::current_user();

		if ( ! $user ) {
			return new WP_Error(
				'wcp_not_authenticated',
				__( 'You must be signed in to do that.', 'woocommerce-customer-portal' ),
				array( 'status' => 401 )
			);
		}

		// Consumed before any work: an exhausted bucket must cost nothing.
		$limited = WCP_Rate_Limit::check( 'profile' );

		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		WCP_Rate_Limit::record( 'profile' );

		$fields = self::get_fields();
		$errors = array();
		$clean  = array();

		// Only declared fields are read. Everything else in $input is ignored.
		foreach ( $fields as $key => $field ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = self::sanitize_value( $key, $input[ $key ] );

			if ( ! empty( $field['required'] ) && '' === $value ) {
				/* translators: %s: field label. */
				$errors[ $key ] = sprintf( __( '%s cannot be empty.', 'woocommerce-customer-portal' ), $field['label'] );
				continue;
			}

			$clean[ $key ] = $value;
		}

		if ( array_key_exists( 'user_email', $clean ) ) {
			$email_error = self::validate_email( $clean['user_email'], $user );

			if ( '' !== $email_error ) {
				$errors['user_email'] = $email_error;
				unset( $clean['user_email'] );
			} else {
				$clean['user_email'] = sanitize_email( $clean['user_email'] );
			}
		}

		$password = self::validate_password_change( $input, $user, $errors );

		// A throttled password change is reported as a 429 -- the honest
		// status -- while still naming the field, so the form can point at it.
		if ( $password instanceof WP_Error ) {
			$data           = (array) $password->get_error_data();
			$data['fields'] = array( 'current_password' => $password->get_error_message() );

			return new WP_Error( $password->get_error_code(), $password->get_error_message(), $data );
		}

		if ( ! empty( $errors ) ) {
			return self::field_error( $errors );
		}

		if ( empty( $clean ) && null === $password ) {
			// Nothing to do. Treated as success so an accidental empty submit
			// is not reported as a failure.
			return self::get_values();
		}

		$update = array( 'ID' => $user->ID );

		foreach ( $clean as $key => $value ) {
			$update[ $key ] = $value;
		}

		if ( null !== $password ) {
			$update['user_pass'] = $password;
		}

		$result = wp_update_user( $update );

		if ( is_wp_error( $result ) ) {
			// WordPress's own message, mapped onto the field it concerns so the
			// form can point at it. Never a raw error dump.
			$code  = $result->get_error_code();
			$field = ( 'existing_user_email' === $code || 'invalid_email' === $code ) ? 'user_email' : '';

			return self::field_error(
				$field ? array( $field => $result->get_error_message() ) : array(),
				$result->get_error_message()
			);
		}

		if ( null !== $password ) {
			// `wp_update_user()` destroys every session for the user, including
			// this one. Re-issuing the cookie keeps the customer signed in on
			// the device that just changed the password, and signs out the rest
			// -- which is the behaviour a password change should have.
			wp_set_auth_cookie( $user->ID, false );
			wp_set_current_user( $user->ID );
		}

		return self::get_values();
	}

	/*
	|--------------------------------------------------------------------------
	| Validation
	|--------------------------------------------------------------------------
	*/

	/**
	 * Sanitise one field by its declared type.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	private static function sanitize_value( $key, $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( sanitize_text_field( (string) $value ) );

		// Deliberately not `sanitize_email()` here. That strips whatever it
		// considers invalid, so "not-an-email" arrives as an empty string and
		// the customer is told the field is empty when it plainly is not.
		// Validation needs to see what was typed; `sanitize_email()` runs once
		// the value has been confirmed to be an address.
		return $value;
	}

	/**
	 * Email format and uniqueness.
	 *
	 * @param string|null $email Sanitised email, or null when not submitted.
	 * @param WP_User     $user  Current user.
	 * @return string Error message, or an empty string.
	 */
	private static function validate_email( $email, WP_User $user ) {
		if ( null === $email || '' === $email ) {
			return '';
		}

		if ( ! is_email( $email ) ) {
			return __( 'That email address does not look valid.', 'woocommerce-customer-portal' );
		}

		// Safe to normalise now that it is known to be an address.
		$email = sanitize_email( $email );

		// Unchanged (case-insensitively) is always fine.
		if ( strtolower( $email ) === strtolower( $user->user_email ) ) {
			return '';
		}

		$existing = email_exists( $email );

		if ( $existing && (int) $existing !== (int) $user->ID ) {
			// Deliberately does not say "that account is registered". This
			// message is enough for the person who owns the address and tells
			// an attacker nothing they could not already learn from signup.
			return __( 'That email address is already in use.', 'woocommerce-customer-portal' );
		}

		return '';
	}

	/**
	 * Validate an optional password change.
	 *
	 * Returns the new password to apply, or null when the customer is not
	 * changing it. Errors are appended to $errors by reference.
	 *
	 * @param array   $input  Raw submitted values.
	 * @param WP_User $user   Current user.
	 * @param array   $errors Error map, by reference.
	 * @return string|WP_Error|null New password, a rate-limit error, or null when not changing.
	 */
	private static function validate_password_change( array $input, WP_User $user, array &$errors ) {
		$current = isset( $input['current_password'] ) && is_scalar( $input['current_password'] )
			? (string) $input['current_password']
			: '';
		$new     = isset( $input['new_password'] ) && is_scalar( $input['new_password'] )
			? (string) $input['new_password']
			: '';
		$confirm = isset( $input['confirm_password'] ) && is_scalar( $input['confirm_password'] )
			? (string) $input['confirm_password']
			: '';

		// Not attempting a change.
		if ( '' === $current && '' === $new && '' === $confirm ) {
			return null;
		}

		// The tight bucket, checked only once a password change is actually
		// being attempted -- an exhausted counter must not block someone
		// editing their display name.
		$limited = WCP_Rate_Limit::check( 'password' );

		if ( is_wp_error( $limited ) ) {
			// Handed back as the error itself rather than as a field message,
			// so the caller can answer with the right status code.
			return $limited;
		}

		if ( '' === $current ) {
			$errors['current_password'] = __( 'Enter your current password to change it.', 'woocommerce-customer-portal' );
		} elseif ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			// Re-authentication. Knowing the session is not enough to change
			// the password on it.
			$errors['current_password'] = __( 'That is not your current password.', 'woocommerce-customer-portal' );
		}

		if ( '' === $new ) {
			$errors['new_password'] = __( 'Enter a new password.', 'woocommerce-customer-portal' );
		} elseif ( strlen( $new ) < self::MIN_PASSWORD_LENGTH ) {
			$errors['new_password'] = sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'Use at least %d characters.', 'woocommerce-customer-portal' ),
				self::MIN_PASSWORD_LENGTH
			);
		} elseif ( false !== strpos( $new, '\\' ) ) {
			// WordPress strips backslashes from passwords on save, so one that
			// contains them would not be the password the customer typed.
			$errors['new_password'] = __( 'Passwords cannot contain a backslash.', 'woocommerce-customer-portal' );
		}

		if ( $new !== $confirm ) {
			$errors['confirm_password'] = __( 'The two passwords do not match.', 'woocommerce-customer-portal' );
		}

		$failed = isset( $errors['current_password'] ) || isset( $errors['new_password'] ) || isset( $errors['confirm_password'] );

		if ( $failed ) {
			// Only failures are counted, so a customer legitimately changing
			// their password twice is never throttled, while someone guessing
			// the current one runs out of attempts quickly.
			WCP_Rate_Limit::record( 'password' );

			return null;
		}

		// A correct current password proves there was nothing to guess.
		WCP_Rate_Limit::clear( 'password' );

		return $new;
	}

	/**
	 * Build a typed validation error.
	 *
	 * @param array  $fields  Field key => message.
	 * @param string $message Optional summary message.
	 * @return WP_Error
	 */
	private static function field_error( array $fields, $message = '' ) {
		if ( '' === $message ) {
			$message = _n(
				'Please correct the highlighted field.',
				'Please correct the highlighted fields.',
				max( 1, count( $fields ) ),
				'woocommerce-customer-portal'
			);
		}

		return new WP_Error(
			'wcp_invalid_profile',
			$message,
			array(
				'status' => 400,
				'fields' => $fields,
			)
		);
	}
}
