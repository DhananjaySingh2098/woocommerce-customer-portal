<?php
/**
 * Order error state.
 *
 * Shown when an order cannot be returned -- it does not exist, it belongs to
 * someone else, or WooCommerce is unavailable. All of those render the same
 * message on purpose: distinguishing "not yours" from "not found" tells an
 * attacker which order IDs are real.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$orders_url = isset( $wcp['orders_url'] ) ? $wcp['orders_url'] : '';
$error      = isset( $wcp['order_error'] ) ? $wcp['order_error'] : array();
$message    = isset( $error['message'] ) ? $error['message'] : __( 'That order could not be found.', 'woocommerce-customer-portal' );
?>

<section class="wcp-section" aria-labelledby="wcp-order-error-title">
	<div class="wcp-panel wcp-panel--centered wcp-animate" style="--wcp-stagger: 0;" role="alert">
		<div class="wcp-empty wcp-empty--lg">
			<span class="wcp-empty__art wcp-empty__art--muted" aria-hidden="true">
				<span class="wcp-empty__ring"></span>
				<span class="wcp-empty__glyph">
					<?php echo wcp_icon( 'alert', array( 'size' => 28 ) ); ?>
				</span>
			</span>

			<h2 class="wcp-empty__title wcp-empty__title--lg" id="wcp-order-error-title">
				<?php esc_html_e( 'Order unavailable', 'woocommerce-customer-portal' ); ?>
			</h2>

			<p class="wcp-empty__text"><?php echo esc_html( $message ); ?></p>

			<div class="wcp-empty__actions">
				<a class="wcp-button wcp-button--primary" href="<?php echo esc_url( $orders_url ); ?>" data-wcp-pending>
					<span><?php esc_html_e( 'Back to orders', 'woocommerce-customer-portal' ); ?></span>
					<?php
					echo wcp_icon(
						'arrow',
						array(
							'size'  => 16,
							'class' => 'wcp-button__arrow',
						)
					);
					?>
				</a>
			</div>
		</div>
	</div>
</section>
