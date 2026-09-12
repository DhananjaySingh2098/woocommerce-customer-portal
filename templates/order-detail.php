<?php
/**
 * Single order detail.
 *
 * Every value here comes from `WCP_Orders::to_detail()`, which has already
 * proved the order belongs to the authenticated customer. WooCommerce's
 * formatted values (prices, addresses, item meta) carry markup by design, so
 * they pass through `wp_kses` with the narrow maps in `WCP_Security` rather
 * than `esc_html`, which would print the tags.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/order-detail.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$order      = isset( $wcp['order'] ) ? $wcp['order'] : array();
$orders_url = isset( $wcp['orders_url'] ) ? $wcp['orders_url'] : '';

if ( empty( $order ) ) {
	return;
}

$stagger = 0;
?>

<div class="wcp-order">

	<a class="wcp-backlink wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger++ ); ?>;" href="<?php echo esc_url( $orders_url ); ?>" data-wcp-pending data-wcp-link>
		<?php
		echo wcp_icon(
			'arrow-left',
			array(
				'size'  => 15,
				'class' => 'wcp-backlink__arrow',
			)
		);
		?>
		<span><?php esc_html_e( 'Back to orders', 'woocommerce-customer-portal' ); ?></span>
	</a>

	<!-- Summary ------------------------------------------------------- -->
	<section class="wcp-order__summary wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger++ ); ?>;" aria-labelledby="wcp-order-title" data-wcp-spotlight>
		<span class="wcp-order__summary-spot" aria-hidden="true"></span>
		<div class="wcp-order__summary-glow" aria-hidden="true"></div>
		<div class="wcp-order__summary-grid" aria-hidden="true"></div>

		<div class="wcp-order__heading">
			<span class="wcp-order__eyebrow"><?php esc_html_e( 'Order summary', 'woocommerce-customer-portal' ); ?></span>
			<h2 class="wcp-order__title" id="wcp-order-title">
				<?php
				printf(
					/* translators: %s: order number. */
					esc_html__( 'Order #%s', 'woocommerce-customer-portal' ),
					esc_html( $order['number'] )
				);
				?>
			</h2>
			<?php
			echo wcp_render_template(
				'partials/order-status.php',
				array( 'status' => $order['status'] )
			);
			?>
		</div>

		<dl class="wcp-order__facts">
			<div class="wcp-order__fact">
				<dt><?php echo wcp_icon( 'calendar', array( 'size' => 13 ) ); ?><?php esc_html_e( 'Order date', 'woocommerce-customer-portal' ); ?></dt>
				<dd>
					<?php if ( $order['date_iso'] ) : ?>
						<time datetime="<?php echo esc_attr( $order['date_iso'] ); ?>"><?php echo esc_html( $order['date_label'] ); ?></time>
					<?php else : ?>
						<?php echo esc_html( $order['date_label'] ); ?>
					<?php endif; ?>
				</dd>
			</div>
			<div class="wcp-order__fact">
				<dt><?php echo wcp_icon( 'orders', array( 'size' => 13 ) ); ?><?php esc_html_e( 'Items', 'woocommerce-customer-portal' ); ?></dt>
				<dd><?php echo esc_html( $order['items_label'] ); ?></dd>
			</div>
			<div class="wcp-order__fact wcp-order__fact--total">
				<dt><?php echo wcp_icon( 'receipt', array( 'size' => 13 ) ); ?><?php esc_html_e( 'Order total', 'woocommerce-customer-portal' ); ?></dt>
				<dd><?php echo wp_kses( $order['total_html'], WCP_Security::allowed_price_html() ); ?></dd>
			</div>
		</dl>

		<?php if ( ! empty( $order['timeline'] ) ) : ?>
			<ol class="wcp-timeline">
				<?php foreach ( $order['timeline'] as $step ) : ?>
					<li class="wcp-timeline__step wcp-timeline__step--<?php echo esc_attr( $step['state'] ); ?>">
						<span class="wcp-timeline__marker" aria-hidden="true">
							<?php if ( 'done' === $step['state'] ) : ?>
								<?php echo wcp_icon( 'check', array( 'size' => 12 ) ); ?>
							<?php endif; ?>
						</span>
						<span class="wcp-timeline__label">
							<?php echo esc_html( $step['label'] ); ?>
							<?php if ( 'current' === $step['state'] ) : ?>
								<span class="wcp-sr-only"><?php esc_html_e( '(current status)', 'woocommerce-customer-portal' ); ?></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>

	<!-- Items --------------------------------------------------------- -->
	<?php if ( ! empty( $order['items'] ) ) : ?>
		<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger++ ); ?>;" aria-labelledby="wcp-order-items">
			<div class="wcp-panel__head wcp-panel__head--icon">
				<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'bag', array( 'size' => 16 ) ); ?></span>
				<div>
					<h3 class="wcp-panel__title" id="wcp-order-items"><?php esc_html_e( 'Items', 'woocommerce-customer-portal' ); ?></h3>
					<p class="wcp-panel__text"><?php echo esc_html( $order['items_label'] ); ?></p>
				</div>
			</div>

			<ul class="wcp-line-items">
				<?php foreach ( $order['items'] as $item ) : ?>
					<li class="wcp-line-item">
						<span class="wcp-line-item__media" aria-hidden="true">
							<?php if ( $item['image'] ) : ?>
								<img class="wcp-line-item__image" src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy" decoding="async" width="56" height="56" />
							<?php else : ?>
								<span class="wcp-line-item__placeholder">
									<span class="wcp-line-item__placeholder-glow"></span>
									<?php echo wcp_icon( 'bag', array( 'size' => 20 ) ); ?>
								</span>
							<?php endif; ?>
							<span class="wcp-line-item__qty"><?php echo esc_html( number_format_i18n( $item['quantity'] ) ); ?></span>
						</span>

						<span class="wcp-line-item__body">
							<span class="wcp-line-item__name">
								<?php if ( $item['permalink'] ) : ?>
									<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $item['name'] ); ?>
								<?php endif; ?>
							</span>

							<?php if ( $item['sku'] ) : ?>
								<span class="wcp-line-item__sku">
									<?php
									printf(
										/* translators: %s: product SKU. */
										esc_html__( 'SKU: %s', 'woocommerce-customer-portal' ),
										esc_html( $item['sku'] )
									);
									?>
								</span>
							<?php endif; ?>

							<?php if ( $item['meta_html'] ) : ?>
								<span class="wcp-line-item__meta">
									<?php echo wp_kses( $item['meta_html'], WCP_Security::allowed_meta_html() ); ?>
								</span>
							<?php endif; ?>
						</span>

						<span class="wcp-line-item__price">
							<?php echo wp_kses( $item['total_html'], WCP_Security::allowed_price_html() ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( ! empty( $order['totals'] ) ) : ?>
				<dl class="wcp-totals">
					<?php foreach ( $order['totals'] as $row ) : ?>
						<div class="wcp-totals__row<?php echo $row['is_total'] ? ' wcp-totals__row--grand' : ''; ?>">
							<dt><?php echo wp_kses( $row['label'], WCP_Security::allowed_price_html() ); ?></dt>
							<dd><?php echo wp_kses( $row['value'], WCP_Security::allowed_price_html() ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<!-- Downloads ----------------------------------------------------- -->
	<?php if ( ! empty( $order['downloads'] ) ) : ?>
		<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger++ ); ?>;" aria-labelledby="wcp-order-downloads">
			<div class="wcp-panel__head wcp-panel__head--icon">
				<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'download', array( 'size' => 16 ) ); ?></span>
				<div>
					<h3 class="wcp-panel__title" id="wcp-order-downloads"><?php esc_html_e( 'Downloads', 'woocommerce-customer-portal' ); ?></h3>
					<p class="wcp-panel__text"><?php esc_html_e( 'Files included with this order.', 'woocommerce-customer-portal' ); ?></p>
				</div>
			</div>

			<ul class="wcp-downloads">
				<?php foreach ( $order['downloads'] as $download ) : ?>
					<li class="wcp-download">
						<span class="wcp-download__icon" aria-hidden="true">
							<?php echo wcp_icon( 'download', array( 'size' => 17 ) ); ?>
						</span>
						<span class="wcp-download__body">
							<span class="wcp-download__name"><?php echo esc_html( $download['name'] ); ?></span>
							<?php if ( $download['expires'] || '' !== $download['remaining'] ) : ?>
								<span class="wcp-download__meta">
									<?php if ( '' !== $download['remaining'] ) : ?>
										<?php
										printf(
											/* translators: %s: number of downloads remaining. */
											esc_html__( '%s downloads remaining', 'woocommerce-customer-portal' ),
											esc_html( $download['remaining'] )
										);
										?>
									<?php endif; ?>
									<?php if ( $download['expires'] ) : ?>
										<?php
										printf(
											/* translators: %s: expiry date. */
											esc_html__( 'Expires %s', 'woocommerce-customer-portal' ),
											esc_html( $download['expires'] )
										);
										?>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</span>
						<a class="wcp-button wcp-button--secondary wcp-download__action" href="<?php echo esc_url( $download['url'] ); ?>">
							<span><?php esc_html_e( 'Download', 'woocommerce-customer-portal' ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<!-- Addresses, payment, shipping ---------------------------------- -->
	<div class="wcp-grid wcp-grid--split">

		<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger++ ); ?>;" aria-labelledby="wcp-order-shipping">
			<div class="wcp-panel__head wcp-panel__head--icon">
				<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'truck', array( 'size' => 16 ) ); ?></span>
				<div>
					<h3 class="wcp-panel__title" id="wcp-order-shipping"><?php esc_html_e( 'Shipping', 'woocommerce-customer-portal' ); ?></h3>
					<p class="wcp-panel__text"><?php esc_html_e( 'Where this order was sent.', 'woocommerce-customer-portal' ); ?></p>
				</div>
			</div>

			<div class="wcp-address-block">
				<?php if ( $order['shipping_html'] ) : ?>
					<address class="wcp-address">
						<?php echo wp_kses( $order['shipping_html'], WCP_Security::allowed_address_html() ); ?>
					</address>
				<?php else : ?>
					<p class="wcp-address__none"><?php esc_html_e( 'No shipping address on this order.', 'woocommerce-customer-portal' ); ?></p>
				<?php endif; ?>

				<?php if ( $order['shipping_method'] ) : ?>
					<p class="wcp-address__meta">
						<?php echo wcp_icon( 'truck', array( 'size' => 15 ) ); ?>
						<span><?php echo esc_html( $order['shipping_method'] ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		</section>

		<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger++ ); ?>;" aria-labelledby="wcp-order-billing">
			<div class="wcp-panel__head wcp-panel__head--icon">
				<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'card', array( 'size' => 16 ) ); ?></span>
				<div>
					<h3 class="wcp-panel__title" id="wcp-order-billing"><?php esc_html_e( 'Billing & payment', 'woocommerce-customer-portal' ); ?></h3>
					<p class="wcp-panel__text"><?php esc_html_e( 'Invoice address and payment method.', 'woocommerce-customer-portal' ); ?></p>
				</div>
			</div>

			<div class="wcp-address-block">
				<?php if ( $order['billing_html'] ) : ?>
					<address class="wcp-address">
						<?php echo wp_kses( $order['billing_html'], WCP_Security::allowed_address_html() ); ?>
					</address>
				<?php else : ?>
					<p class="wcp-address__none"><?php esc_html_e( 'No billing address on this order.', 'woocommerce-customer-portal' ); ?></p>
				<?php endif; ?>

				<?php if ( $order['payment_method'] ) : ?>
					<p class="wcp-address__meta">
						<?php echo wcp_icon( 'card', array( 'size' => 15 ) ); ?>
						<span><?php echo esc_html( $order['payment_method'] ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<?php if ( $order['customer_note'] ) : ?>
		<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger++ ); ?>;" aria-labelledby="wcp-order-note">
			<div class="wcp-panel__head wcp-panel__head--icon">
				<span class="wcp-panel__icon" aria-hidden="true"><?php echo wcp_icon( 'receipt', array( 'size' => 16 ) ); ?></span>
				<div>
					<h3 class="wcp-panel__title" id="wcp-order-note"><?php esc_html_e( 'Your note', 'woocommerce-customer-portal' ); ?></h3>
				</div>
			</div>
			<p class="wcp-order__note"><?php echo esc_html( $order['customer_note'] ); ?></p>
		</section>
	<?php endif; ?>
</div>
