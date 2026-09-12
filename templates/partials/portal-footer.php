<?php
/**
 * Portal application footer.
 *
 * One component, rendered by the shell for every authenticated section and in
 * a compact form on the signed-out screen -- never copied into a section
 * template. It is the application's own footer and has nothing to do with the
 * theme's: it is scoped inside `.wcp-portal` and the site's footer is left
 * exactly as the theme wrote it.
 *
 * Everything here is real. Links are only rendered when the destination
 * actually exists -- a store with no Cart page simply does not get a Cart
 * link -- and the account state is read from WooCommerce, never invented. No
 * figure on this footer costs a query the page was not already making: the
 * order count appears only on screens that already loaded it.
 *
 * Override by copying to:
 * {your-theme}/woocommerce-customer-portal/partials/portal-footer.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$compact  = ! empty( $wcp['footer_compact'] );
$store    = get_bloginfo( 'name' );
$year     = wp_date( 'Y' );
$shop_url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';

/*
 * Legal and account links. Each one is asked for and dropped when the site
 * does not have it, so the bar never shows a link to a page that is not there.
 */
$account_url = class_exists( 'WCP_Auth' ) ? WCP_Auth::get_my_account_url() : '';
$privacy_url = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
$terms_url   = '';

if ( function_exists( 'wc_get_page_id' ) && function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'terms' ) > 0 ) {
	$terms_url = (string) wc_get_page_permalink( 'terms' );
}

$legal = array();

// On the signed-out screen the bar is the whole footer, so it carries the
// store link too.
if ( $compact && '' !== $shop_url ) {
	$legal[] = array(
		'label' => __( 'Shop', 'woocommerce-customer-portal' ),
		'url'   => $shop_url,
	);
}

if ( '' !== $privacy_url ) {
	$legal[] = array(
		'label' => __( 'Privacy', 'woocommerce-customer-portal' ),
		'url'   => $privacy_url,
	);
}

if ( '' !== $terms_url ) {
	$legal[] = array(
		'label' => __( 'Terms', 'woocommerce-customer-portal' ),
		'url'   => $terms_url,
	);
}

if ( '' !== $account_url ) {
	$legal[] = array(
		'label' => __( 'My account', 'woocommerce-customer-portal' ),
		'url'   => $account_url,
	);
}

/* translators: 1: year, 2: store name. */
$copyright = sprintf( _x( '© %1$s %2$s', 'portal footer copyright', 'woocommerce-customer-portal' ), $year, $store );
?>
<footer class="wcp-footer<?php echo $compact ? ' wcp-footer--compact' : ''; ?>" data-wcp-footer data-wcp-reveal>
	<span class="wcp-footer__glow" aria-hidden="true"></span>

	<?php if ( ! $compact ) : ?>
		<?php
		/** The authenticated account.
		 *
		 * @var WCP_Account $account
		 */
		$account  = isset( $wcp['account'] ) ? $wcp['account'] : null;
		$items    = isset( $wcp['items'] ) ? (array) $wcp['items'] : array();
		$summary  = isset( $wcp['summary'] ) ? (array) $wcp['summary'] : array();
		$base_url = isset( $wcp['base_url'] ) ? (string) $wcp['base_url'] : '';

		/** The navigation model.
		 *
		 * @var WCP_Navigation $navigation
		 */
		$navigation = isset( $wcp['navigation'] ) ? $wcp['navigation'] : null;

		$addresses_on = ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'addresses' );
		$orders_url   = isset( $wcp['orders_url'] ) ? (string) $wcp['orders_url'] : '';

		$addresses_url = isset( $wcp['addresses_url'] ) ? (string) $wcp['addresses_url'] : '';
		$profile_url   = ( $navigation && ( ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'profile' ) ) )
			? $navigation->get_section_url( 'profile', $base_url )
			: '';

		// Store pages, in the order a customer would meet them. Anything the
		// store has not published is dropped rather than linked into nothing.
		$store_links = array();

		if ( function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_page_id' ) ) {
			$pages = array(
				'shop'      => __( 'Shop', 'woocommerce-customer-portal' ),
				'cart'      => __( 'Cart', 'woocommerce-customer-portal' ),
				'checkout'  => __( 'Checkout', 'woocommerce-customer-portal' ),
				'myaccount' => __( 'My account', 'woocommerce-customer-portal' ),
			);

			foreach ( $pages as $page => $label ) {
				if ( wc_get_page_id( $page ) <= 0 ) {
					continue;
				}

				$url = (string) wc_get_page_permalink( $page );

				if ( '' === $url ) {
					continue;
				}

				$store_links[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
		}

		// Compact actions. Only destinations that exist, never duplicated as
		// full-size cards -- the dashboard already has those.
		$actions = array();

		if ( '' !== $shop_url ) {
			$actions[] = array(
				'label'    => __( 'Browse store', 'woocommerce-customer-portal' ),
				'icon'     => 'bag',
				'url'      => $shop_url,
				'external' => true,
			);
		}

		if ( '' !== $orders_url && ( ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'orders' ) ) ) {
			$actions[] = array(
				'label'    => __( 'View orders', 'woocommerce-customer-portal' ),
				'icon'     => 'orders',
				'url'      => $orders_url,
				'external' => false,
			);
		}

		if ( $addresses_on && '' !== $addresses_url ) {
			$actions[] = array(
				'label'    => __( 'Manage addresses', 'woocommerce-customer-portal' ),
				'icon'     => 'addresses',
				'url'      => $addresses_url,
				'external' => false,
			);
		}

		if ( '' !== $profile_url ) {
			$actions[] = array(
				'label'    => __( 'Edit profile', 'woocommerce-customer-portal' ),
				'icon'     => 'profile',
				'url'      => $profile_url,
				'external' => false,
			);
		}

		/*
		 * Account state. Address state is two reads of the customer record the
		 * page has already loaded; the order count is used only when the
		 * current screen happens to have it, because a footer is not worth a
		 * second order query.
		 */
		$state = array();

		if ( $addresses_on && class_exists( 'WCP_Addresses' ) ) {
			foreach ( array(
				'billing'  => __( 'Billing address', 'woocommerce-customer-portal' ),
				'shipping' => __( 'Shipping address', 'woocommerce-customer-portal' ),
			) as $type => $label ) {
				$saved = WCP_Addresses::has_address( $type );

				$state[] = array(
					'label' => $label,
					'value' => $saved
						? __( 'Added', 'woocommerce-customer-portal' )
						: __( 'Missing', 'woocommerce-customer-portal' ),
					'tone'  => $saved ? 'ok' : 'todo',
					'icon'  => ( 'billing' === $type ) ? 'card' : 'truck',
				);
			}
		}

		if ( isset( $summary['has_orders'] ) && ! empty( $summary['has_orders'] ) ) {
			$state[] = array(
				'label' => __( 'Orders', 'woocommerce-customer-portal' ),
				'value' => number_format_i18n( (int) $summary['order_count'] ),
				'tone'  => 'ok',
				'icon'  => 'orders',
			);
		}

		$visual_labels = WCP_Theme::visual_labels();
		$visual_slug   = WCP_Theme::admin_visual_default();

		$state[] = array(
			'label' => __( 'Theme', 'woocommerce-customer-portal' ),
			'value' => isset( $visual_labels[ $visual_slug ] ) ? $visual_labels[ $visual_slug ] : $visual_slug,
			'tone'  => 'accent',
			'icon'  => 'palette',
			'live'  => true,
		);
		?>

		<div class="wcp-footer__inner">

			<div class="wcp-footer__brand">
				<span class="wcp-footer__mark" aria-hidden="true">
					<?php echo wcp_icon( 'compass', array( 'size' => 18 ) ); ?>
				</span>
				<span class="wcp-footer__identity">
					<span class="wcp-footer__store"><?php echo esc_html( $store ); ?></span>
					<span class="wcp-footer__eyebrow"><?php esc_html_e( 'Customer portal', 'woocommerce-customer-portal' ); ?></span>
				</span>
				<p class="wcp-footer__text">
					<?php esc_html_e( 'Manage your orders, addresses and account details securely, in one place.', 'woocommerce-customer-portal' ); ?>
				</p>

				<?php if ( $actions ) : ?>
					<ul class="wcp-footer__actions">
						<?php foreach ( $actions as $action ) : ?>
							<li>
								<a
									class="wcp-footer__action"
									href="<?php echo esc_url( $action['url'] ); ?>"
									<?php if ( ! $action['external'] ) : ?>
										data-wcp-link
									<?php endif; ?>
								>
									<span class="wcp-footer__action-icon" aria-hidden="true">
										<?php echo wcp_icon( $action['icon'], array( 'size' => 14 ) ); ?>
									</span>
									<span><?php echo esc_html( $action['label'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<nav class="wcp-footer__nav" aria-label="<?php esc_attr_e( 'Customer portal footer', 'woocommerce-customer-portal' ); ?>">
				<?php if ( $items ) : ?>
					<div class="wcp-footer__group">
						<h2 class="wcp-footer__title"><?php esc_html_e( 'Account', 'woocommerce-customer-portal' ); ?></h2>
						<ul class="wcp-footer__links">
							<?php foreach ( $items as $item ) : ?>
								<li>
									<a
										class="wcp-footer__link<?php echo ! empty( $item['is_current'] ) ? ' is-current' : ''; ?>"
										href="<?php echo esc_url( $item['url'] ); ?>"
										data-wcp-link
										<?php if ( ! empty( $item['is_current'] ) ) : ?>
											aria-current="page"
										<?php endif; ?>
									>
										<span><?php echo esc_html( $item['label'] ); ?></span>
										<?php
										echo wcp_icon(
											'arrow',
											array(
												'size'  => 13,
												'class' => 'wcp-footer__link-arrow',
											)
										);
										?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $store_links ) : ?>
					<div class="wcp-footer__group">
						<h2 class="wcp-footer__title"><?php esc_html_e( 'Store', 'woocommerce-customer-portal' ); ?></h2>
						<ul class="wcp-footer__links">
							<?php foreach ( $store_links as $link ) : ?>
								<li>
									<a class="wcp-footer__link" href="<?php echo esc_url( $link['url'] ); ?>">
										<span><?php echo esc_html( $link['label'] ); ?></span>
										<?php
										echo wcp_icon(
											'arrow-out',
											array(
												'size'  => 13,
												'class' => 'wcp-footer__link-arrow',
											)
										);
										?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</nav>

			<div class="wcp-footer__status">
				<h2 class="wcp-footer__title"><?php esc_html_e( 'Account status', 'woocommerce-customer-portal' ); ?></h2>
				<ul class="wcp-footer__state">
					<?php foreach ( $state as $row ) : ?>
						<li class="wcp-footer__state-row">
							<span class="wcp-footer__state-icon wcp-footer__state-icon--<?php echo esc_attr( $row['tone'] ); ?>" aria-hidden="true">
								<?php echo wcp_icon( $row['icon'], array( 'size' => 13 ) ); ?>
							</span>
							<span class="wcp-footer__state-label"><?php echo esc_html( $row['label'] ); ?></span>
							<span
								class="wcp-footer__state-value wcp-footer__state-value--<?php echo esc_attr( $row['tone'] ); ?>"
								<?php if ( ! empty( $row['live'] ) ) : ?>
									data-wcp-visual-label
								<?php endif; ?>
							><?php echo esc_html( $row['value'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>

	<div class="wcp-footer__bar">
		<p class="wcp-footer__copyright"><?php echo esc_html( $copyright ); ?></p>

		<?php if ( $legal ) : ?>
			<ul class="wcp-footer__legal">
				<?php foreach ( $legal as $link ) : ?>
					<li>
						<a class="wcp-footer__legal-link" href="<?php echo esc_url( $link['url'] ); ?>">
							<?php echo esc_html( $link['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</footer>
