<?php
/**
 * Store discovery: the store's own newest published products.
 *
 * Not a recommendation, and not labelled as one. Nothing here looks at the
 * customer -- these are the most recently published catalog-visible products,
 * which is the same set the shop page would show, so the portal can never
 * surface something the store has hidden. When the catalog is empty the panel
 * falls back to an invitation rather than an empty grid.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$products = isset( $wcp['dash_products'] ) ? (array) $wcp['dash_products'] : array();
$shop_url = isset( $wcp['shop_url'] ) ? (string) $wcp['shop_url'] : '';
$stagger  = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;

if ( empty( $products ) && '' === $shop_url ) {
	return;
}
?>
<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-discovery-title">
	<div class="wcp-panel__head wcp-panel__head--row">
		<div>
			<h3 class="wcp-panel__title" id="wcp-discovery-title"><?php esc_html_e( 'Latest products', 'woocommerce-customer-portal' ); ?></h3>
			<p class="wcp-panel__text"><?php esc_html_e( 'The newest additions to the store.', 'woocommerce-customer-portal' ); ?></p>
		</div>
		<?php if ( '' !== $shop_url ) : ?>
			<a class="wcp-button wcp-button--ghost wcp-button--sm" href="<?php echo esc_url( $shop_url ); ?>">
				<span><?php esc_html_e( 'Shop', 'woocommerce-customer-portal' ); ?></span>
				<?php
				echo wcp_icon(
					'arrow-out',
					array(
						'size'  => 14,
						'class' => 'wcp-button__arrow',
					)
				);
				?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $products ) ) : ?>
		<ul class="wcp-products">
			<?php foreach ( $products as $index => $product ) : ?>
				<li class="wcp-products__item wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) ( $stagger + $index ) ); ?>;">
					<a class="wcp-product" href="<?php echo esc_url( $product['permalink'] ); ?>" data-wcp-tilt data-wcp-tilt-max="4" data-wcp-spotlight>
						<span class="wcp-product__spot" aria-hidden="true"></span>
						<span class="wcp-product__media">
							<?php if ( '' !== $product['image'] ) : ?>
								<img
									class="wcp-product__image"
									src="<?php echo esc_url( $product['image'] ); ?>"
									alt=""
									loading="lazy"
									decoding="async"
								/>
							<?php else : ?>
								<span class="wcp-product__placeholder" aria-hidden="true">
									<?php echo wcp_icon( 'bag', array( 'size' => 20 ) ); ?>
								</span>
							<?php endif; ?>
						</span>
						<span class="wcp-product__body">
							<span class="wcp-product__name"><?php echo esc_html( $product['name'] ); ?></span>
							<?php if ( '' !== $product['price_html'] ) : ?>
								<span class="wcp-product__price">
									<?php echo wp_kses( $product['price_html'], WCP_Security::allowed_price_html() ); ?>
								</span>
							<?php endif; ?>
						</span>
						<span class="wcp-product__arrow" aria-hidden="true">
							<?php echo wcp_icon( 'arrow-out', array( 'size' => 14 ) ); ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<div class="wcp-empty">
			<span class="wcp-empty__art" aria-hidden="true">
				<span class="wcp-empty__ring"></span>
				<span class="wcp-empty__glyph">
					<?php echo wcp_icon( 'compass', array( 'size' => 22 ) ); ?>
				</span>
			</span>
			<h4 class="wcp-empty__title"><?php esc_html_e( 'Nothing published yet', 'woocommerce-customer-portal' ); ?></h4>
			<p class="wcp-empty__text"><?php esc_html_e( 'New products will show up here as the store adds them.', 'woocommerce-customer-portal' ); ?></p>
			<?php if ( '' !== $shop_url ) : ?>
				<div class="wcp-empty__actions">
					<a class="wcp-button wcp-button--secondary wcp-button--sm" href="<?php echo esc_url( $shop_url ); ?>">
						<span><?php esc_html_e( 'Visit the store', 'woocommerce-customer-portal' ); ?></span>
						<?php
						echo wcp_icon(
							'arrow-out',
							array(
								'size'  => 14,
								'class' => 'wcp-button__arrow',
							)
						);
						?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
