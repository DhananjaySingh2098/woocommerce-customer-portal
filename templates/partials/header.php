<?php
/**
 * Portal header: drawer toggle, page title, appearance switcher, account chip.
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
$account      = $wcp['account'];
$current_item = $wcp['current_item'];
$sidebar_id   = (string) $wcp['sidebar_id'];
?>
<header class="wcp-header">
	<div class="wcp-header__inner">
		<button
			type="button"
			class="wcp-icon-button wcp-header__menu"
			data-wcp-drawer-toggle
			aria-expanded="false"
			aria-controls="<?php echo esc_attr( $sidebar_id ); ?>"
		>
			<span class="wcp-sr-only"><?php esc_html_e( 'Open navigation menu', 'woocommerce-customer-portal' ); ?></span>
			<?php echo wcp_icon( 'menu', array( 'size' => 20 ) ); ?>
		</button>

		<div class="wcp-header__titles" data-wcp-header-titles>
			<p class="wcp-header__crumb" aria-hidden="true">
				<span class="wcp-header__crumb-brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
				<span class="wcp-header__crumb-sep"><?php echo wcp_icon( 'chevron', array( 'size' => 12 ) ); ?></span>
			</p>
			<h1 class="wcp-header__title" data-wcp-page-title><?php echo esc_html( $current_item['title'] ); ?></h1>
			<p class="wcp-header__subtitle" data-wcp-page-subtitle><?php echo esc_html( $current_item['subtitle'] ); ?></p>
		</div>

		<div class="wcp-header__actions">
			<?php
			echo wcp_render_template(
				'partials/theme-toggle.php',
				$wcp
			);
			?>

			<a class="wcp-account-chip" href="<?php echo esc_url( $wcp['account_url'] ); ?>">
				<span class="wcp-avatar wcp-avatar--sm" aria-hidden="true"><?php echo esc_html( $account->get_initials() ); ?></span>
				<span class="wcp-account-chip__meta">
					<span class="wcp-account-chip__name"><?php echo esc_html( $account->get_display_name() ); ?></span>
					<span class="wcp-account-chip__hint"><?php esc_html_e( 'My account', 'woocommerce-customer-portal' ); ?></span>
				</span>
				<span class="wcp-account-chip__icon" aria-hidden="true">
					<?php echo wcp_icon( 'chevron', array( 'size' => 16 ) ); ?>
				</span>
			</a>
		</div>
	</div>
</header>
