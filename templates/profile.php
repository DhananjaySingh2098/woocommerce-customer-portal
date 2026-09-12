<?php
/**
 * Profile section.
 *
 * Two forms in one screen, posting to the same endpoint: the identity fields,
 * and an optional password change. They are separated visually because they
 * carry different risk -- changing a display name is not the same act as
 * changing a password, and the interface should not pretend otherwise.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/profile.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

/** The authenticated account.
 *
 * @var WCP_Account $account
 */
$account  = $wcp['account'];
$fields   = isset( $wcp['profile_fields'] ) ? (array) $wcp['profile_fields'] : array();
$values   = isset( $wcp['profile_values'] ) ? (array) $wcp['profile_values'] : array();
$flash    = isset( $wcp['flash'] ) ? (array) $wcp['flash'] : array();
$instance = (int) $wcp['instance'];

$errors = ( ! empty( $flash['fields'] ) && 'profile' === $flash['form'] )
	? (array) $flash['fields']
	: array();

$half = array(
	'first_name' => true,
	'last_name'  => true,
);

$password_fields = array(
	'current_password' => array(
		'label'        => __( 'Current password', 'woocommerce-customer-portal' ),
		'autocomplete' => 'current-password',
	),
	'new_password'     => array(
		'label'        => __( 'New password', 'woocommerce-customer-portal' ),
		'hint'         => sprintf(
			/* translators: %d: minimum number of characters. */
			__( 'At least %d characters.', 'woocommerce-customer-portal' ),
			WCP_Profile::MIN_PASSWORD_LENGTH
		),
		'autocomplete' => 'new-password',
		'half'         => true,
	),
	'confirm_password' => array(
		'label'        => __( 'Confirm new password', 'woocommerce-customer-portal' ),
		'autocomplete' => 'new-password',
		'half'         => true,
	),
);
?>

<div class="wcp-account">

	<!-- Identity ------------------------------------------------------- -->
	<section class="wcp-profile-head wcp-animate" style="--wcp-stagger: 0;" aria-labelledby="wcp-profile-title" data-wcp-spotlight>
		<span class="wcp-profile-head__spot" aria-hidden="true"></span>
		<div class="wcp-profile-head__glow" aria-hidden="true"></div>
		<div class="wcp-profile-head__grid" aria-hidden="true"></div>

		<div class="wcp-profile-head__avatar" data-wcp-tilt data-wcp-tilt-max="6" aria-hidden="true">
			<span class="wcp-profile-head__halo"></span>
			<span class="wcp-avatar wcp-avatar--xl wcp-depth-3"><?php echo esc_html( $account->get_initials() ); ?></span>
		</div>

		<div class="wcp-profile-head__body">
			<p class="wcp-profile-head__eyebrow"><?php esc_html_e( 'Your profile', 'woocommerce-customer-portal' ); ?></p>
			<h2 class="wcp-profile-head__name" id="wcp-profile-title"><?php echo esc_html( $account->get_display_name() ); ?></h2>
			<p class="wcp-profile-head__email">
				<?php echo wcp_icon( 'mail', array( 'size' => 14 ) ); ?>
				<span><?php echo esc_html( $account->get_email() ); ?></span>
			</p>
			<?php if ( '' !== $account->get_member_since() ) : ?>
				<p class="wcp-profile-head__meta">
					<?php echo wcp_icon( 'calendar', array( 'size' => 14 ) ); ?>
					<span>
						<?php
						printf(
							/* translators: %s: registration date. */
							esc_html__( 'Member since %s', 'woocommerce-customer-portal' ),
							esc_html( $account->get_member_since() )
						);
						?>
					</span>
				</p>
			<?php endif; ?>
		</div>
	</section>

	<!-- Details -------------------------------------------------------- -->
	<form
		class="wcp-panel wcp-panel--elevated wcp-form wcp-animate"
		style="--wcp-stagger: 1;"
		method="post"
		action="<?php echo esc_url( $wcp['base_url'] ); ?>"
		data-wcp-form="profile"
		aria-labelledby="wcp-profile-details"
		novalidate
	>
		<input type="hidden" name="<?php echo esc_attr( WCP_Form_Handler::FORM_FIELD ); ?>" value="profile" />
		<input type="hidden" name="wcp_return_section" value="profile" />
		<input type="hidden" name="<?php echo esc_attr( WCP_Security::NONCE_NAME ); ?>" value="<?php echo esc_attr( $wcp['nonce'] ); ?>" />

		<div class="wcp-panel__head wcp-panel__head--icon">
			<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'profile', array( 'size' => 16 ) ); ?></span>
			<div>
				<h3 class="wcp-panel__title" id="wcp-profile-details"><?php esc_html_e( 'Your details', 'woocommerce-customer-portal' ); ?></h3>
				<p class="wcp-panel__text"><?php esc_html_e( 'How your name and contact details appear across the store.', 'woocommerce-customer-portal' ); ?></p>
			</div>
		</div>

		<div class="wcp-form__body">
			<?php
			echo wcp_render_template(
				'partials/form-feedback.php',
				array(
					'flash' => $flash,
					'form'  => 'profile',
				)
			);
			?>

			<div class="wcp-form__grid">
				<?php foreach ( $fields as $key => $field ) : ?>
					<?php
					echo wcp_render_template(
						'partials/field.php',
						array(
							'instance' => $instance,
							'field'    => array(
								'key'          => $key,
								'label'        => isset( $field['label'] ) ? $field['label'] : $key,
								'type'         => isset( $field['type'] ) ? $field['type'] : 'text',
								'required'     => ! empty( $field['required'] ),
								'hint'         => isset( $field['hint'] ) ? $field['hint'] : '',
								'autocomplete' => isset( $field['autocomplete'] ) ? $field['autocomplete'] : '',
								'half'         => ! empty( $half[ $key ] ),
								'value'        => isset( $values[ $key ] ) ? $values[ $key ] : '',
								'error'        => isset( $errors[ $key ] ) ? $errors[ $key ] : '',
							),
						)
					);
					?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="wcp-form__actions">
			<button type="submit" class="wcp-button wcp-button--primary" data-wcp-submit>
				<span class="wcp-button__label"><?php esc_html_e( 'Save changes', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-button__spinner" aria-hidden="true"></span>
				<span class="wcp-button__check" aria-hidden="true"><?php echo wcp_icon( 'check', array( 'size' => 15 ) ); ?></span>
			</button>
		</div>
	</form>

	<!-- Password ------------------------------------------------------- -->
	<form
		class="wcp-panel wcp-panel--elevated wcp-form wcp-animate"
		style="--wcp-stagger: 2;"
		method="post"
		action="<?php echo esc_url( $wcp['base_url'] ); ?>"
		data-wcp-form="profile"
		aria-labelledby="wcp-profile-password"
		novalidate
	>
		<input type="hidden" name="<?php echo esc_attr( WCP_Form_Handler::FORM_FIELD ); ?>" value="profile" />
		<input type="hidden" name="wcp_return_section" value="profile" />
		<input type="hidden" name="<?php echo esc_attr( WCP_Security::NONCE_NAME ); ?>" value="<?php echo esc_attr( $wcp['nonce'] ); ?>" />

		<div class="wcp-panel__head wcp-panel__head--icon">
			<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'key', array( 'size' => 16 ) ); ?></span>
			<div>
				<h3 class="wcp-panel__title" id="wcp-profile-password"><?php esc_html_e( 'Password', 'woocommerce-customer-portal' ); ?></h3>
				<p class="wcp-panel__text"><?php esc_html_e( 'Changing your password signs you out everywhere else.', 'woocommerce-customer-portal' ); ?></p>
			</div>
		</div>

		<div class="wcp-form__body">
			<?php
			echo wcp_render_template(
				'partials/form-feedback.php',
				array(
					'flash' => $flash,
					'form'  => 'profile',
				)
			);
			?>

			<div class="wcp-form__grid">
				<?php foreach ( $password_fields as $key => $field ) : ?>
					<?php
					echo wcp_render_template(
						'partials/field.php',
						array(
							'instance' => $instance,
							'field'    => array(
								'key'          => $key,
								'label'        => $field['label'],
								'type'         => 'password',
								'required'     => false,
								'hint'         => isset( $field['hint'] ) ? $field['hint'] : '',
								'autocomplete' => $field['autocomplete'],
								'half'         => ! empty( $field['half'] ),
								// Never repopulated: a password is not a value
								// to round-trip through markup.
								'value'        => '',
								'error'        => isset( $errors[ $key ] ) ? $errors[ $key ] : '',
							),
						)
					);
					?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="wcp-form__actions">
			<button type="submit" class="wcp-button wcp-button--secondary" data-wcp-submit>
				<span class="wcp-button__label"><?php esc_html_e( 'Update password', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-button__spinner" aria-hidden="true"></span>
				<span class="wcp-button__check" aria-hidden="true"><?php echo wcp_icon( 'check', array( 'size' => 15 ) ); ?></span>
			</button>
		</div>
	</form>
</div>
