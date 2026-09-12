<?php
/**
 * One form field.
 *
 * Every input in the portal goes through here, so the label association, the
 * `aria-describedby` wiring and the error markup are written once and are
 * correct everywhere rather than re-derived per form.
 *
 * `aria-describedby` lists the hint and the error together when both exist, in
 * that order, which is the order a screen reader should read them.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context: `field` describes one control.
 */

defined( 'ABSPATH' ) || exit;

$field = isset( $wcp['field'] ) ? (array) $wcp['field'] : array();

$key = isset( $field['key'] ) ? (string) $field['key'] : '';

if ( '' === $key ) {
	return;
}

$type         = isset( $field['type'] ) ? (string) $field['type'] : 'text';
$label        = isset( $field['label'] ) ? (string) $field['label'] : $key;
$value        = isset( $field['value'] ) ? (string) $field['value'] : '';
$hint         = isset( $field['hint'] ) ? (string) $field['hint'] : '';
$error        = isset( $field['error'] ) ? (string) $field['error'] : '';
$required     = ! empty( $field['required'] );
$options      = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
$autocomplete = isset( $field['autocomplete'] ) ? (string) $field['autocomplete'] : '';
$placeholder  = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
$instance     = isset( $wcp['instance'] ) ? (int) $wcp['instance'] : 1;

$id       = 'wcp-' . $instance . '-' . str_replace( '_', '-', sanitize_key( $key ) );
$hint_id  = $id . '-hint';
$error_id = $id . '-error';

$described = array();

if ( '' !== $hint ) {
	$described[] = $hint_id;
}

/*
 * The error element is always referenced, even while it is empty and hidden.
 * A `display: none` element is outside the accessibility tree, so nothing is
 * announced until it has something to say -- and when it does, it is announced
 * without anyone having to rewrite `aria-describedby` at that moment. Wiring it
 * up front is more reliable than wiring it during the failure it describes.
 */
$described[] = $error_id;

$classes = WCP_Helper::class_names(
	array(
		'wcp-field'        => true,
		'wcp-field--half'  => ! empty( $field['half'] ),
		'wcp-field--error' => ( '' !== $error ),
	)
);
?>
<div class="<?php echo esc_attr( $classes ); ?>" data-wcp-field="<?php echo esc_attr( $key ); ?>">
	<label class="wcp-field__label" for="<?php echo esc_attr( $id ); ?>">
		<span><?php echo esc_html( $label ); ?></span>
		<?php if ( $required ) : ?>
			<span class="wcp-field__required" aria-hidden="true">*</span>
			<span class="wcp-sr-only"><?php esc_html_e( '(required)', 'woocommerce-customer-portal' ); ?></span>
		<?php else : ?>
			<span class="wcp-field__optional"><?php esc_html_e( 'Optional', 'woocommerce-customer-portal' ); ?></span>
		<?php endif; ?>
	</label>

	<?php if ( 'select' === $type ) : ?>
		<div class="wcp-select-wrap">
			<select
				class="wcp-input wcp-select"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				<?php echo $required ? 'required' : ''; ?>
				<?php echo $autocomplete ? 'autocomplete="' . esc_attr( $autocomplete ) . '"' : ''; ?>
				<?php echo $described ? 'aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"' : ''; ?>
				<?php echo ( '' !== $error ) ? 'aria-invalid="true"' : ''; ?>
			>
				<option value=""><?php esc_html_e( 'Select…', 'woocommerce-customer-portal' ); ?></option>
				<?php foreach ( $options as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $option_value, $value ); ?>>
						<?php echo esc_html( $option_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="wcp-select-wrap__chevron" aria-hidden="true">
				<?php echo wcp_icon( 'chevron', array( 'size' => 15 ) ); ?>
			</span>
		</div>
	<?php else : ?>
		<input
			class="wcp-input"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $key ); ?>"
			type="<?php echo esc_attr( $type ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php echo $required ? 'required' : ''; ?>
			<?php echo $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
			<?php echo $autocomplete ? 'autocomplete="' . esc_attr( $autocomplete ) . '"' : ''; ?>
			<?php echo $described ? 'aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"' : ''; ?>
			<?php echo ( '' !== $error ) ? 'aria-invalid="true"' : ''; ?>
		/>
	<?php endif; ?>

	<?php if ( '' !== $hint ) : ?>
		<p class="wcp-field__hint" id="<?php echo esc_attr( $hint_id ); ?>"><?php echo esc_html( $hint ); ?></p>
	<?php endif; ?>

	<p class="wcp-field__error" id="<?php echo esc_attr( $error_id ); ?>" data-wcp-field-error <?php echo ( '' === $error ) ? 'hidden' : ''; ?>>
		<?php echo wcp_icon( 'alert', array( 'size' => 14 ) ); ?>
		<span><?php echo esc_html( $error ); ?></span>
	</p>
</div>
