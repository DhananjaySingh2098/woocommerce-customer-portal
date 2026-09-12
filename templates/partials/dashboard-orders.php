<?php
/**
 * Recent orders: the customer's own latest purchases.
 *
 * Read through `WCP_Orders`, which owns the ownership gate, so this panel
 * adds no second path to order records.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

/** The navigation model.
 *
 * @var WCP_Navigation $navigation
 */
$navigation = $wcp['navigation'];
$base_url   = $wcp['base_url'];
$summary    = isset( $wcp['summary'] ) ? (array) $wcp['summary'] : array();
$orders_url = isset( $wcp['orders_url'] ) ? (string) $wcp['orders_url'] : '';
$stagger    = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;
$recent     = isset( $summary['recent'] ) ? (array) $summary['recent'] : array();

if ( empty( $recent ) ) {
	return;
}
?>
<section class="wcp-panel wcp-panel--elevated wcp-panel--feature wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-recent-title">
	<div class="wcp-panel__head wcp-panel__head--row">
		<div>
			<h3 class="wcp-panel__title" id="wcp-recent-title"><?php esc_html_e( 'Recent orders', 'woocommerce-customer-portal' ); ?></h3>
			<p class="wcp-panel__text"><?php esc_html_e( 'Your latest purchases at a glance.', 'woocommerce-customer-portal' ); ?></p>
		</div>
		<?php if ( '' !== $orders_url ) : ?>
			<a class="wcp-button wcp-button--ghost wcp-button--sm" href="<?php echo esc_url( $orders_url ); ?>" data-wcp-link>
				<span><?php esc_html_e( 'View all', 'woocommerce-customer-portal' ); ?></span>
				<?php
				echo wcp_icon(
					'arrow',
					array(
						'size'  => 14,
						'class' => 'wcp-button__arrow',
					)
				);
				?>
			</a>
		<?php endif; ?>
	</div>

	<ul class="wcp-mini-orders">
		<?php foreach ( $recent as $index => $order ) : ?>
			<li class="wcp-mini-orders__item wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) ( $stagger + $index ) ); ?>;">
				<a class="wcp-mini-order" href="<?php echo esc_url( $navigation->get_order_url( $order['id'], $base_url ) ); ?>" data-wcp-link>
					<span class="wcp-mini-order__glyph" aria-hidden="true">
						<?php echo wcp_icon( 'bag', array( 'size' => 16 ) ); ?>
					</span>
					<span class="wcp-mini-order__main">
						<span class="wcp-mini-order__number">
							<?php
							printf(
								/* translators: %s: order number. */
								esc_html__( 'Order #%s', 'woocommerce-customer-portal' ),
								esc_html( $order['number'] )
							);
							?>
						</span>
						<span class="wcp-mini-order__meta">
							<?php if ( $order['date_iso'] ) : ?>
								<time datetime="<?php echo esc_attr( $order['date_iso'] ); ?>"><?php echo esc_html( $order['date_label'] ); ?></time>
							<?php endif; ?>
							<span aria-hidden="true">&middot;</span>
							<span><?php echo esc_html( $order['items_label'] ); ?></span>
							<?php if ( ! empty( $order['items_teaser'] ) ) : ?>
								<span class="wcp-mini-order__teaser"><?php echo esc_html( $order['items_teaser'] ); ?></span>
							<?php endif; ?>
						</span>
					</span>

					<span class="wcp-mini-order__side">
						<?php
						echo wcp_render_template(
							'partials/order-status.php',
							array( 'status' => $order['status'] )
						);
						?>
						<span class="wcp-mini-order__total">
							<?php echo wp_kses( $order['total_html'], WCP_Security::allowed_price_html() ); ?>
						</span>
						<span class="wcp-mini-order__arrow" aria-hidden="true">
							<?php echo wcp_icon( 'arrow', array( 'size' => 15 ) ); ?>
						</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
