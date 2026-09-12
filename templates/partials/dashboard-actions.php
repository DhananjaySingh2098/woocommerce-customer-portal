<?php
/**
 * Quick actions: compact tiles for the things a customer does most.
 *
 * Every tile is a real destination in this portal or in the store. Tiles for
 * sections an administrator has switched off are not rendered at all, so the
 * panel never offers a door that does not open.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$shop_url     = isset( $wcp['shop_url'] ) ? (string) $wcp['shop_url'] : '';
$orders_url   = isset( $wcp['orders_url'] ) ? (string) $wcp['orders_url'] : '';
$profile_url  = isset( $wcp['dash_profile'] ) ? (string) $wcp['dash_profile'] : '';
$billing_url  = isset( $wcp['dash_billing'] ) ? (string) $wcp['dash_billing'] : '';
$shipping_url = isset( $wcp['dash_shipping'] ) ? (string) $wcp['dash_shipping'] : '';
$stagger      = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;
$orders_on    = ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'orders' );

$actions = array();

if ( '' !== $shop_url ) {
	$actions[] = array(
		'label'    => __( 'Browse store', 'woocommerce-customer-portal' ),
		'icon'     => 'bag',
		'url'      => $shop_url,
		'external' => true,
	);
}

if ( $orders_on && '' !== $orders_url ) {
	$actions[] = array(
		'label'    => __( 'View orders', 'woocommerce-customer-portal' ),
		'icon'     => 'orders',
		'url'      => $orders_url,
		'external' => false,
	);
}

if ( '' !== $profile_url ) {
	$actions[] = array(
		'label'    => __( 'Manage profile', 'woocommerce-customer-portal' ),
		'icon'     => 'profile',
		'url'      => $profile_url,
		'external' => false,
	);
}

if ( '' !== $billing_url ) {
	$actions[] = array(
		'label'    => __( 'Billing address', 'woocommerce-customer-portal' ),
		'icon'     => 'card',
		'url'      => $billing_url,
		'external' => false,
	);
}

if ( '' !== $shipping_url ) {
	$actions[] = array(
		'label'    => __( 'Shipping address', 'woocommerce-customer-portal' ),
		'icon'     => 'truck',
		'url'      => $shipping_url,
		'external' => false,
	);
}

if ( empty( $actions ) ) {
	return;
}
?>
<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-actions-title">
	<div class="wcp-panel__head">
		<h3 class="wcp-panel__title" id="wcp-actions-title"><?php esc_html_e( 'Quick actions', 'woocommerce-customer-portal' ); ?></h3>
		<p class="wcp-panel__text"><?php esc_html_e( 'Everything you reach for most.', 'woocommerce-customer-portal' ); ?></p>
	</div>

	<ul class="wcp-actions">
		<?php foreach ( $actions as $action ) : ?>
			<li class="wcp-actions__item">
				<a
					class="wcp-action"
					href="<?php echo esc_url( $action['url'] ); ?>"
					data-wcp-spotlight
					<?php if ( ! $action['external'] ) : ?>
						data-wcp-link
					<?php endif; ?>
				>
					<span class="wcp-action__spot" aria-hidden="true"></span>
					<span class="wcp-action__icon" aria-hidden="true">
						<?php echo wcp_icon( $action['icon'], array( 'size' => 16 ) ); ?>
					</span>
					<span class="wcp-action__label"><?php echo esc_html( $action['label'] ); ?></span>
					<span class="wcp-action__arrow" aria-hidden="true">
						<?php echo wcp_icon( $action['external'] ? 'arrow-out' : 'arrow', array( 'size' => 14 ) ); ?>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
