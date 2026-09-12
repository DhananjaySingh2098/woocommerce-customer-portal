<?php
/**
 * Form-level feedback.
 *
 * Rendered once per form and always present in the DOM, even when empty, so
 * that script can fill it in place. An `aria-live` region that is created at
 * the moment it gains content is frequently missed by screen readers; one that
 * already exists and changes is announced reliably.
 *
 * `role="status"` rather than `role="alert"`: the message follows an action the
 * customer just took, so it does not need to interrupt them.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$flash   = isset( $wcp['flash'] ) ? (array) $wcp['flash'] : array();
$form    = isset( $wcp['form'] ) ? (string) $wcp['form'] : '';
$status  = '';
$message = '';

// Only show a flash that belongs to this form.
if ( ! empty( $flash['form'] ) && $flash['form'] === $form ) {
	$status  = isset( $flash['status'] ) ? (string) $flash['status'] : '';
	$message = isset( $flash['message'] ) ? (string) $flash['message'] : '';
}

$classes = WCP_Helper::class_names(
	array(
		'wcp-feedback'          => true,
		'wcp-feedback--success' => ( 'success' === $status ),
		'wcp-feedback--error'   => ( 'error' === $status ),
	)
);
?>
<div
	class="<?php echo esc_attr( $classes ); ?>"
	data-wcp-feedback
	role="status"
	aria-live="polite"
	<?php echo ( '' === $message ) ? 'hidden' : ''; ?>
>
	<span class="wcp-feedback__icon" aria-hidden="true" data-wcp-feedback-icon>
		<?php
		echo wcp_icon(
			'error' === $status ? 'alert' : 'check',
			array( 'size' => 15 )
		);
		?>
	</span>
	<span class="wcp-feedback__text" data-wcp-feedback-text><?php echo esc_html( $message ); ?></span>
</div>
