<?php
/**
 * Portal sidebar: brand, section navigation, account footer.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

/** The authenticated account.
 *
 * @var WCP_Account $account
 */
$account    = $wcp['account'];
$sidebar_id = (string) $wcp['sidebar_id'];
?>
<aside
	class="wcp-sidebar"
	id="<?php echo esc_attr( $sidebar_id ); ?>"
	data-wcp-sidebar
	aria-label="<?php esc_attr_e( 'Customer portal', 'woocommerce-customer-portal' ); ?>"
>
	<div class="wcp-sidebar__inner">

		<div class="wcp-brand">
			<span class="wcp-brand__mark wcp-depth-1" aria-hidden="true">
				<span class="wcp-brand__mark-glow"></span>
				<?php echo wcp_icon( 'compass', array( 'size' => 20 ) ); ?>
			</span>
			<span class="wcp-brand__text">
				<span class="wcp-brand__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
				<span class="wcp-brand__eyebrow"><?php esc_html_e( 'Customer portal', 'woocommerce-customer-portal' ); ?></span>
			</span>
			<button
				type="button"
				class="wcp-icon-button wcp-sidebar__close"
				data-wcp-drawer-close
			>
				<span class="wcp-sr-only"><?php esc_html_e( 'Close navigation menu', 'woocommerce-customer-portal' ); ?></span>
				<?php echo wcp_icon( 'close', array( 'size' => 20 ) ); ?>
			</button>
		</div>

		<nav class="wcp-nav" data-wcp-nav aria-label="<?php esc_attr_e( 'Portal sections', 'woocommerce-customer-portal' ); ?>">
			<p class="wcp-nav__heading"><?php esc_html_e( 'Menu', 'woocommerce-customer-portal' ); ?></p>
			<span class="wcp-nav__indicator" data-wcp-nav-indicator aria-hidden="true"></span>
			<ul class="wcp-nav__list">
				<?php foreach ( $wcp['items'] as $item ) : ?>
					<li class="wcp-nav__item">
						<a
							class="
							<?php
							echo esc_attr(
								WCP_Helper::class_names(
									array(
										'wcp-nav__link' => true,
										'is-active'     => ! empty( $item['is_current'] ),
									)
								)
							);
							?>
									"
							href="<?php echo esc_url( $item['url'] ); ?>"
							data-wcp-nav-link
							data-wcp-link
							data-wcp-section-link="<?php echo esc_attr( $item['slug'] ); ?>"
							<?php echo ! empty( $item['is_current'] ) ? 'aria-current="page"' : ''; ?>
						>
							<span class="wcp-nav__icon" aria-hidden="true">
								<?php echo wcp_icon( $item['icon'], array( 'size' => 19 ) ); ?>
							</span>
							<span class="wcp-nav__label"><?php echo esc_html( $item['label'] ); ?></span>
							<span class="wcp-nav__chevron" aria-hidden="true">
								<?php echo wcp_icon( 'chevron', array( 'size' => 14 ) ); ?>
							</span>
							<?php if ( 'upcoming' === $item['status'] ) : ?>
								<span class="wcp-nav__dot" aria-hidden="true"></span>
								<span class="wcp-sr-only"><?php esc_html_e( '(preview)', 'woocommerce-customer-portal' ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="wcp-sidebar__footer">
			<div class="wcp-user-card">
				<span class="wcp-user-card__glow" aria-hidden="true"></span>
				<a class="wcp-user" href="<?php echo esc_url( $wcp['account_url'] ); ?>">
					<span class="wcp-avatar" aria-hidden="true"><?php echo esc_html( $account->get_initials() ); ?></span>
					<span class="wcp-user__meta">
						<span class="wcp-user__name"><?php echo esc_html( $account->get_display_name() ); ?></span>
						<span class="wcp-user__email"><?php echo esc_html( $account->get_email() ); ?></span>
					</span>
					<span class="wcp-user__chevron" aria-hidden="true">
						<?php echo wcp_icon( 'chevron', array( 'size' => 16 ) ); ?>
					</span>
				</a>

				<a class="wcp-button wcp-button--ghost wcp-button--block wcp-user-card__signout" href="<?php echo esc_url( $wcp['logout_url'] ); ?>">
					<?php echo wcp_icon( 'logout', array( 'size' => 17 ) ); ?>
					<span><?php esc_html_e( 'Sign out', 'woocommerce-customer-portal' ); ?></span>
				</a>
			</div>
		</div>
	</div>
</aside>
