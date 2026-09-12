<?php
/**
 * Orders list.
 *
 * A row is one semantic unit, so each renders as a single link containing the
 * whole order rather than a table row with a "View" link stranded in the last
 * column. That gives a large, obvious target, one tab stop per order, and a
 * layout that can restack into a card on a phone without changing meaning.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/orders.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$result   = isset( $wcp['orders'] ) ? $wcp['orders'] : array( 'orders' => array() );
$orders   = isset( $result['orders'] ) ? $result['orders'] : array();
$shop_url = isset( $wcp['shop_url'] ) ? $wcp['shop_url'] : '';

/** The navigation model.
 *
 * @var WCP_Navigation $navigation
 */
$navigation = $wcp['navigation'];
$base_url   = $wcp['base_url'];

$total       = isset( $result['total'] ) ? (int) $result['total'] : 0;
$page        = isset( $result['page'] ) ? (int) $result['page'] : 1;
$total_pages = isset( $result['total_pages'] ) ? (int) $result['total_pages'] : 0;
?>

<?php if ( empty( $orders ) ) : ?>

	<section class="wcp-section" aria-labelledby="wcp-orders-empty-title">
		<div class="wcp-panel wcp-panel--centered wcp-animate" style="--wcp-stagger: 0;">
			<div class="wcp-empty wcp-empty--lg">
				<span class="wcp-empty__art" aria-hidden="true">
					<span class="wcp-empty__ring"></span>
					<span class="wcp-empty__glyph">
						<?php echo wcp_icon( 'orders', array( 'size' => 28 ) ); ?>
					</span>
				</span>

				<h2 class="wcp-empty__title wcp-empty__title--lg" id="wcp-orders-empty-title">
					<?php esc_html_e( 'No orders yet', 'woocommerce-customer-portal' ); ?>
				</h2>

				<p class="wcp-empty__text">
					<?php esc_html_e( 'Your purchases will appear here once you place your first order.', 'woocommerce-customer-portal' ); ?>
				</p>

				<?php if ( $shop_url ) : ?>
					<div class="wcp-empty__actions">
						<a class="wcp-button wcp-button--primary" href="<?php echo esc_url( $shop_url ); ?>">
							<span><?php esc_html_e( 'Browse store', 'woocommerce-customer-portal' ); ?></span>
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
				<?php endif; ?>
			</div>
		</div>
	</section>

<?php else : ?>

	<section class="wcp-section" aria-labelledby="wcp-orders-title">

		<div class="wcp-section__head wcp-section__head--row wcp-animate" style="--wcp-stagger: 0;">
			<div>
				<h2 class="wcp-section__title" id="wcp-orders-title"><?php esc_html_e( 'Order history', 'woocommerce-customer-portal' ); ?></h2>
				<p class="wcp-section__text">
					<?php
					printf(
						/* translators: %s: total number of orders. */
						esc_html( _n( '%s order in your account.', '%s orders in your account.', $total, 'woocommerce-customer-portal' ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</p>
			</div>
			<span class="wcp-count-badge" aria-hidden="true">
				<?php echo wcp_icon( 'orders', array( 'size' => 14 ) ); ?>
				<span><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
			</span>
		</div>

		<div class="wcp-orders wcp-animate" style="--wcp-stagger: 1;" data-wcp-orders>

			<div class="wcp-orders__head" aria-hidden="true">
				<span class="wcp-orders__col wcp-orders__col--order"><?php esc_html_e( 'Order', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-orders__col wcp-orders__col--date"><?php esc_html_e( 'Date', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-orders__col wcp-orders__col--status"><?php esc_html_e( 'Status', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-orders__col wcp-orders__col--items"><?php esc_html_e( 'Items', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-orders__col wcp-orders__col--total"><?php esc_html_e( 'Total', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-orders__col wcp-orders__col--action"></span>
			</div>

			<ul class="wcp-orders__list">
				<?php foreach ( $orders as $index => $order ) : ?>
					<li class="wcp-orders__item wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) ( $index + 2 ) ); ?>;">
						<a
							class="wcp-order-row"
							href="<?php echo esc_url( $navigation->get_order_url( $order['id'], $base_url ) ); ?>"
							data-wcp-pending
							data-wcp-link
						>
							<span class="wcp-orders__col wcp-orders__col--order">
								<span class="wcp-order-row__label"><?php esc_html_e( 'Order', 'woocommerce-customer-portal' ); ?></span>
								<span class="wcp-order-row__glyph" aria-hidden="true"><?php echo wcp_icon( 'bag', array( 'size' => 15 ) ); ?></span>
								<span class="wcp-order-row__number">
									<?php
									printf(
										/* translators: %s: order number. */
										esc_html__( '#%s', 'woocommerce-customer-portal' ),
										esc_html( $order['number'] )
									);
									?>
								</span>
							</span>

							<span class="wcp-orders__col wcp-orders__col--date">
								<span class="wcp-order-row__label"><?php esc_html_e( 'Date', 'woocommerce-customer-portal' ); ?></span>
								<?php if ( $order['date_iso'] ) : ?>
									<time class="wcp-order-row__date" datetime="<?php echo esc_attr( $order['date_iso'] ); ?>">
										<?php echo esc_html( $order['date_label'] ); ?>
									</time>
								<?php else : ?>
									<span class="wcp-order-row__date"><?php echo esc_html( $order['date_label'] ); ?></span>
								<?php endif; ?>
							</span>

							<span class="wcp-orders__col wcp-orders__col--status">
								<span class="wcp-order-row__label"><?php esc_html_e( 'Status', 'woocommerce-customer-portal' ); ?></span>
								<?php
								echo wcp_render_template(
									'partials/order-status.php',
									array( 'status' => $order['status'] )
								);
								?>
							</span>

							<span class="wcp-orders__col wcp-orders__col--items">
								<span class="wcp-order-row__label"><?php esc_html_e( 'Items', 'woocommerce-customer-portal' ); ?></span>
								<span class="wcp-order-row__items"><?php echo esc_html( $order['items_label'] ); ?></span>
								<?php if ( '' !== $order['items_teaser'] ) : ?>
									<span class="wcp-order-row__teaser"><?php echo esc_html( $order['items_teaser'] ); ?></span>
								<?php endif; ?>
							</span>

							<span class="wcp-orders__col wcp-orders__col--total">
								<span class="wcp-order-row__label"><?php esc_html_e( 'Total', 'woocommerce-customer-portal' ); ?></span>
								<span class="wcp-order-row__total">
									<?php echo wp_kses( $order['total_html'], WCP_Security::allowed_price_html() ); ?>
								</span>
							</span>

							<span class="wcp-orders__col wcp-orders__col--action">
								<span class="wcp-order-row__cta">
									<span><?php esc_html_e( 'View order', 'woocommerce-customer-portal' ); ?></span>
									<?php
									echo wcp_icon(
										'arrow',
										array(
											'size'  => 15,
											'class' => 'wcp-order-row__arrow',
										)
									);
									?>
								</span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php
			echo wcp_render_template(
				'partials/orders-skeleton.php',
				array( 'rows' => min( 5, max( 3, count( $orders ) ) ) )
			);
			?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="wcp-pagination wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) ( count( $orders ) + 2 ) ); ?>;" aria-label="<?php esc_attr_e( 'Orders pages', 'woocommerce-customer-portal' ); ?>">
				<?php if ( ! empty( $result['has_prev'] ) ) : ?>
					<a class="wcp-button wcp-button--ghost wcp-pagination__step" href="<?php echo esc_url( $navigation->get_orders_page_url( $page - 1, $base_url ) ); ?>" rel="prev" data-wcp-pending data-wcp-link>
						<?php echo wcp_icon( 'arrow-left', array( 'size' => 15 ) ); ?>
						<span><?php esc_html_e( 'Previous', 'woocommerce-customer-portal' ); ?></span>
					</a>
				<?php else : ?>
					<span class="wcp-button wcp-button--ghost wcp-pagination__step is-disabled" aria-hidden="true">
						<?php echo wcp_icon( 'arrow-left', array( 'size' => 15 ) ); ?>
						<span><?php esc_html_e( 'Previous', 'woocommerce-customer-portal' ); ?></span>
					</span>
				<?php endif; ?>

				<p class="wcp-pagination__status" aria-live="polite">
					<?php
					printf(
						/* translators: 1: current page number, 2: total number of pages. */
						esc_html__( 'Page %1$s of %2$s', 'woocommerce-customer-portal' ),
						esc_html( number_format_i18n( $page ) ),
						esc_html( number_format_i18n( $total_pages ) )
					);
					?>
				</p>

				<?php if ( ! empty( $result['has_next'] ) ) : ?>
					<a class="wcp-button wcp-button--ghost wcp-pagination__step" href="<?php echo esc_url( $navigation->get_orders_page_url( $page + 1, $base_url ) ); ?>" rel="next" data-wcp-pending data-wcp-link>
						<span><?php esc_html_e( 'Next', 'woocommerce-customer-portal' ); ?></span>
						<?php echo wcp_icon( 'arrow', array( 'size' => 15 ) ); ?>
					</a>
				<?php else : ?>
					<span class="wcp-button wcp-button--ghost wcp-pagination__step is-disabled" aria-hidden="true">
						<span><?php esc_html_e( 'Next', 'woocommerce-customer-portal' ); ?></span>
						<?php echo wcp_icon( 'arrow', array( 'size' => 15 ) ); ?>
					</span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</section>

<?php endif; ?>
