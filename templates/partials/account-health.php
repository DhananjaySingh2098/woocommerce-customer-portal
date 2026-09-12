<?php
/**
 * Account health: what checkout will use, one dense row per item.
 *
 * Every row is a real state read from WooCommerce or WordPress -- an address
 * is saved or it is not, a name is on file or it is not. Rows for sections an
 * administrator has switched off are simply absent, and no row states a
 * condition the store cannot actually check.
 *
 * The theme row is the one exception to "server knows best": the customer's
 * choice lives in their browser, so the server renders the configured default
 * and the portal script corrects it on init.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$addresses    = isset( $wcp['dash_addresses'] ) ? (array) $wcp['dash_addresses'] : array();
$setup        = isset( $wcp['dash_setup'] ) ? (array) $wcp['dash_setup'] : array();
$profile_url  = isset( $wcp['dash_profile'] ) ? (string) $wcp['dash_profile'] : '';
$billing_url  = isset( $wcp['dash_billing'] ) ? (string) $wcp['dash_billing'] : '';
$shipping_url = isset( $wcp['dash_shipping'] ) ? (string) $wcp['dash_shipping'] : '';
$shop_url     = isset( $wcp['shop_url'] ) ? (string) $wcp['shop_url'] : '';
$stagger      = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;

$addresses_enabled = ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'addresses' );

// Whether the profile step was offered, and whether it is satisfied.
$profile_done = null;

foreach ( (array) ( isset( $setup['steps'] ) ? $setup['steps'] : array() ) as $step ) {
	if ( isset( $step['key'] ) && 'profile' === $step['key'] ) {
		$profile_done = ! empty( $step['done'] );
	}
}

$rows = array();

if ( $addresses_enabled && ! empty( $addresses['total'] ) ) {
	$rows[] = array(
		'icon'  => 'card',
		'label' => __( 'Billing address', 'woocommerce-customer-portal' ),
		'state' => ! empty( $addresses['billing'] )
			? __( 'Added', 'woocommerce-customer-portal' )
			: __( 'Missing', 'woocommerce-customer-portal' ),
		'tone'  => ! empty( $addresses['billing'] ) ? 'ok' : 'todo',
		'url'   => $billing_url,
	);

	$rows[] = array(
		'icon'  => 'truck',
		'label' => __( 'Shipping address', 'woocommerce-customer-portal' ),
		'state' => ! empty( $addresses['shipping'] )
			? __( 'Added', 'woocommerce-customer-portal' )
			: __( 'Missing', 'woocommerce-customer-portal' ),
		'tone'  => ! empty( $addresses['shipping'] ) ? 'ok' : 'todo',
		'url'   => $shipping_url,
	);
}

if ( '' !== $profile_url ) {
	$rows[] = array(
		'icon'  => 'profile',
		'label' => __( 'Profile', 'woocommerce-customer-portal' ),
		'state' => ( false === $profile_done )
			? __( 'Review', 'woocommerce-customer-portal' )
			: __( 'Complete', 'woocommerce-customer-portal' ),
		'tone'  => ( false === $profile_done ) ? 'todo' : 'ok',
		'url'   => $profile_url,
	);

	$rows[] = array(
		'icon'  => 'key',
		'label' => __( 'Password', 'woocommerce-customer-portal' ),
		'state' => __( 'Manage', 'woocommerce-customer-portal' ),
		'tone'  => 'ok',
		'url'   => $profile_url,
	);
}

$visual_labels = WCP_Theme::visual_labels();
$visual_slug   = WCP_Theme::admin_visual_default();
$visual_label  = isset( $visual_labels[ $visual_slug ] ) ? $visual_labels[ $visual_slug ] : $visual_slug;
?>
<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-health-title">
	<div class="wcp-panel__head">
		<h3 class="wcp-panel__title" id="wcp-health-title"><?php esc_html_e( 'Account health', 'woocommerce-customer-portal' ); ?></h3>
		<p class="wcp-panel__text"><?php esc_html_e( 'What checkout will use, and where to change it.', 'woocommerce-customer-portal' ); ?></p>
	</div>

	<ul class="wcp-health">
		<?php foreach ( $rows as $row ) : ?>
			<li class="wcp-health__row">
				<?php if ( '' !== $row['url'] ) : ?>
					<a class="wcp-health__link" href="<?php echo esc_url( $row['url'] ); ?>" data-wcp-link>
				<?php else : ?>
					<div class="wcp-health__link wcp-health__link--static">
				<?php endif; ?>

					<span class="wcp-health__icon wcp-health__icon--<?php echo esc_attr( $row['tone'] ); ?>" aria-hidden="true">
						<?php echo wcp_icon( $row['icon'], array( 'size' => 14 ) ); ?>
					</span>
					<span class="wcp-health__body">
						<span class="wcp-health__label"><?php echo esc_html( $row['label'] ); ?></span>
					</span>
					<span class="wcp-health__state wcp-health__state--<?php echo esc_attr( $row['tone'] ); ?>">
						<?php echo esc_html( $row['state'] ); ?>
					</span>

					<?php if ( '' !== $row['url'] ) : ?>
						<span class="wcp-health__action" aria-hidden="true">
							<?php echo wcp_icon( 'chevron', array( 'size' => 14 ) ); ?>
						</span>
					<?php endif; ?>

				<?php if ( '' !== $row['url'] ) : ?>
					</a>
				<?php else : ?>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>

		<li class="wcp-health__row">
			<button type="button" class="wcp-health__link" data-wcp-appearance-open>
				<span class="wcp-health__icon wcp-health__icon--accent" aria-hidden="true">
					<?php echo wcp_icon( 'palette', array( 'size' => 14 ) ); ?>
				</span>
				<span class="wcp-health__body">
					<span class="wcp-health__label"><?php esc_html_e( 'Theme', 'woocommerce-customer-portal' ); ?></span>
				</span>
				<span class="wcp-health__state" data-wcp-visual-label><?php echo esc_html( $visual_label ); ?></span>
				<span class="wcp-health__action" aria-hidden="true">
					<?php echo wcp_icon( 'chevron', array( 'size' => 14 ) ); ?>
				</span>
			</button>
		</li>
	</ul>

	<?php if ( '' !== $shop_url ) : ?>
		<a class="wcp-health__shop" href="<?php echo esc_url( $shop_url ); ?>">
			<span class="wcp-health__shop-icon" aria-hidden="true"><?php echo wcp_icon( 'bag', array( 'size' => 16 ) ); ?></span>
			<span><?php esc_html_e( 'Continue shopping', 'woocommerce-customer-portal' ); ?></span>
			<?php echo wcp_icon( 'arrow-out', array( 'size' => 14 ) ); ?>
		</a>
	<?php endif; ?>
</section>
