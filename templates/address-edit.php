<?php
/**
 * Address edit form.
 *
 * Every field, its label, its order and whether it is required come from
 * `WC()->countries->get_address_fields()`. Nothing about the shape of an
 * address is written into this template, which is why it is correct for a
 * German or Japanese customer without a single conditional.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/address-edit.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$type     = isset( $wcp['address_type'] ) ? (string) $wcp['address_type'] : '';
$label    = isset( $wcp['address_label'] ) ? (string) $wcp['address_label'] : '';
$fields   = isset( $wcp['address_fields'] ) ? (array) $wcp['address_fields'] : array();
$values   = isset( $wcp['address_values'] ) ? (array) $wcp['address_values'] : array();
$flash    = isset( $wcp['flash'] ) ? (array) $wcp['flash'] : array();
$back_url = isset( $wcp['addresses_url'] ) ? (string) $wcp['addresses_url'] : '';
$instance = (int) $wcp['instance'];

if ( '' === $type || empty( $fields ) ) {
	return;
}

$errors = ( ! empty( $flash['fields'] ) && 'address' === $flash['form'] && ( ! isset( $flash['address'] ) || $flash['address'] === $type ) )
	? (array) $flash['fields']
	: array();
?>

<div class="wcp-account">

	<a class="wcp-backlink wcp-animate" style="--wcp-stagger: 0;" href="<?php echo esc_url( $back_url ); ?>" data-wcp-pending data-wcp-link>
		<?php
		echo wcp_icon(
			'arrow-left',
			array(
				'size'  => 15,
				'class' => 'wcp-backlink__arrow',
			)
		);
		?>
		<span><?php esc_html_e( 'Back to addresses', 'woocommerce-customer-portal' ); ?></span>
	</a>

	<form
		class="wcp-panel wcp-panel--elevated wcp-form wcp-animate"
		style="--wcp-stagger: 1;"
		method="post"
		action="<?php echo esc_url( $wcp['base_url'] ); ?>"
		data-wcp-form="address"
		data-wcp-address-type="<?php echo esc_attr( $type ); ?>"
		aria-labelledby="wcp-address-title"
		novalidate
	>
		<input type="hidden" name="<?php echo esc_attr( WCP_Form_Handler::FORM_FIELD ); ?>" value="address" />
		<input type="hidden" name="wcp_address_type" value="<?php echo esc_attr( $type ); ?>" />
		<input type="hidden" name="wcp_return_section" value="addresses" />
		<input type="hidden" name="<?php echo esc_attr( WCP_Security::NONCE_NAME ); ?>" value="<?php echo esc_attr( $wcp['nonce'] ); ?>" />

		<div class="wcp-panel__head wcp-panel__head--icon">
			<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'shipping' === $type ? 'truck' : 'card', array( 'size' => 16 ) ); ?></span>
			<div>
				<h2 class="wcp-panel__title wcp-panel__title--lg" id="wcp-address-title"><?php echo esc_html( $label ); ?></h2>
				<p class="wcp-panel__text">
					<?php esc_html_e( 'Fields follow the conventions of the country you choose.', 'woocommerce-customer-portal' ); ?>
				</p>
			</div>
		</div>

		<div class="wcp-form__body">
			<?php
			echo wcp_render_template(
				'partials/form-feedback.php',
				array(
					'flash' => $flash,
					'form'  => 'address',
				)
			);
			?>

			<div class="wcp-form__grid" data-wcp-address-fields>
				<?php foreach ( $fields as $key => $field ) : ?>
					<?php
					echo wcp_render_template(
						'partials/field.php',
						array(
							'instance' => $instance,
							'field'    => array(
								'key'          => $key,
								'label'        => $field['label'],
								'type'         => $field['type'],
								'required'     => $field['required'],
								'options'      => $field['options'],
								'placeholder'  => $field['placeholder'],
								'autocomplete' => $field['autocomplete'],
								'half'         => $field['half'],
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
			<a class="wcp-button wcp-button--ghost" href="<?php echo esc_url( $back_url ); ?>">
				<span><?php esc_html_e( 'Cancel', 'woocommerce-customer-portal' ); ?></span>
			</a>
			<button type="submit" class="wcp-button wcp-button--primary" data-wcp-submit>
				<span class="wcp-button__label"><?php esc_html_e( 'Save address', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-button__spinner" aria-hidden="true"></span>
				<span class="wcp-button__check" aria-hidden="true"><?php echo wcp_icon( 'check', array( 'size' => 15 ) ); ?></span>
			</button>
		</div>
	</form>
</div>
