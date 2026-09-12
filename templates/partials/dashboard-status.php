<?php
/**
 * Compact status cards: real account state, four across.
 *
 * The set adapts to what the store actually knows. A returning customer gets
 * order figures; a new one gets the state of their account. Neither gets a
 * zero dressed up as a metric, and no card is a placeholder -- a card with
 * nothing behind it is simply not built.
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
$account = $wcp['account'];

/** The navigation model.
 *
 * @var WCP_Navigation $navigation
 */
$navigation    = $wcp['navigation'];
$base_url      = $wcp['base_url'];
$summary       = isset( $wcp['summary'] ) ? (array) $wcp['summary'] : array();
$addresses     = isset( $wcp['dash_addresses'] ) ? (array) $wcp['dash_addresses'] : array();
$setup         = isset( $wcp['dash_setup'] ) ? (array) $wcp['dash_setup'] : array();
$profile_url   = isset( $wcp['dash_profile'] ) ? (string) $wcp['dash_profile'] : '';
$orders_url    = isset( $wcp['orders_url'] ) ? (string) $wcp['orders_url'] : '';
$addresses_url = isset( $wcp['addresses_url'] ) ? (string) $wcp['addresses_url'] : '';
$stagger       = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;
$has_orders    = ! empty( $wcp['dash_has_orders'] );
$latest        = isset( $summary['latest'] ) ? $summary['latest'] : null;
$orders_on     = ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'orders' );

// Was the profile step offered, and is it done?
$profile_done   = null;
$setup_steps    = isset( $setup['steps'] ) ? (array) $setup['steps'] : array();
$member_since   = $account->get_member_since_short();
$member_iso     = $account->get_member_since_iso();
$member_elapsed = '';

foreach ( $setup_steps as $step ) {
	if ( isset( $step['key'] ) && 'profile' === $step['key'] ) {
		$profile_done = ! empty( $step['done'] );
	}
}

if ( '' !== $member_iso ) {
	$registered = strtotime( $member_iso . ' 00:00:00 UTC' );

	if ( $registered && $registered <= time() ) {
		$member_elapsed = sprintf(
			/* translators: %s: human-readable duration, e.g. "3 months". */
			__( 'Active for %s', 'woocommerce-customer-portal' ),
			human_time_diff( $registered, time() )
		);
	}
}

$cards = array();

if ( $orders_on ) {
	$cards[] = array(
		'icon'  => 'orders',
		'label' => __( 'Total orders', 'woocommerce-customer-portal' ),
		'value' => esc_html( number_format_i18n( (int) ( isset( $summary['order_count'] ) ? $summary['order_count'] : 0 ) ) ),
		'text'  => $has_orders
			? __( 'Across the lifetime of your account.', 'woocommerce-customer-portal' )
			: __( 'No orders yet. Your purchases will appear here.', 'woocommerce-customer-portal' ),
		'url'   => $has_orders ? $orders_url : '',
	);
}

if ( $has_orders && ! empty( $summary['total_spent'] ) ) {
	$cards[] = array(
		'icon'  => 'wallet',
		'label' => __( 'Lifetime value', 'woocommerce-customer-portal' ),
		'value' => wp_kses( $summary['total_spent'], WCP_Security::allowed_price_html() ),
		'text'  => __( 'Total of your paid orders.', 'woocommerce-customer-portal' ),
		'url'   => '',
	);
}

if ( $has_orders && $latest ) {
	$cards[] = array(
		'icon'   => 'clock',
		'label'  => __( 'Latest order', 'woocommerce-customer-portal' ),
		'value'  => esc_html( sprintf( '#%s', $latest['number'] ) ),
		'text'   => $latest['date_label'],
		'status' => $latest['status'],
		'url'    => $navigation->get_order_url( $latest['id'], $base_url ),
	);
}

if ( ! empty( $addresses['total'] ) ) {
	$saved = (int) ( isset( $addresses['saved'] ) ? $addresses['saved'] : 0 );
	$total = (int) $addresses['total'];

	$cards[] = array(
		'icon'  => 'addresses',
		'label' => __( 'Saved addresses', 'woocommerce-customer-portal' ),
		'value' => esc_html(
			sprintf(
				/* translators: 1: number of saved addresses, 2: total number of address types. */
				_x( '%1$s of %2$s', 'saved addresses count', 'woocommerce-customer-portal' ),
				number_format_i18n( $saved ),
				number_format_i18n( $total )
			)
		),
		'text'  => ( $saved === $total )
			? __( 'Billing and shipping are ready for checkout.', 'woocommerce-customer-portal' )
			: __( 'Save them once to speed up checkout.', 'woocommerce-customer-portal' ),
		'url'   => $addresses_url,
	);
}

if ( ! $has_orders && null !== $profile_done ) {
	$cards[] = array(
		'icon'  => 'profile',
		'label' => __( 'Profile', 'woocommerce-customer-portal' ),
		'value' => esc_html(
			$profile_done
				? __( 'Complete', 'woocommerce-customer-portal' )
				: __( 'Needs attention', 'woocommerce-customer-portal' )
		),
		'text'  => $profile_done
			? $account->get_email()
			: __( 'Add your name to finish it.', 'woocommerce-customer-portal' ),
		'url'   => $profile_url,
		'word'  => true,
	);
}

if ( '' !== $member_since ) {
	$cards[] = array(
		'icon'  => 'calendar',
		'label' => __( 'Member since', 'woocommerce-customer-portal' ),
		'value' => esc_html( $member_since ),
		'text'  => '' !== $member_elapsed ? $member_elapsed : $account->get_member_since(),
		'url'   => '',
		'word'  => true,
	);
}

// Four is the row; anything further would wrap into a ragged second line.
$cards = array_slice( $cards, 0, 4 );

if ( empty( $cards ) ) {
	return;
}
?>
<section class="wcp-section" aria-labelledby="wcp-status-title">
	<h3 class="wcp-sr-only" id="wcp-status-title"><?php esc_html_e( 'Account overview', 'woocommerce-customer-portal' ); ?></h3>

	<div class="wcp-metrics">
		<?php foreach ( $cards as $index => $card ) : ?>
			<?php $tag = $card['url'] ? 'a' : 'div'; ?>
			<<?php echo esc_attr( $tag ); ?>
				class="wcp-metric wcp-card-3d wcp-animate<?php echo $card['url'] ? ' wcp-metric--link' : ''; ?>"
				style="--wcp-stagger: <?php echo esc_attr( (string) ( $stagger + $index ) ); ?>;"
				data-wcp-tilt
				data-wcp-spotlight
				<?php if ( $card['url'] ) : ?>
					href="<?php echo esc_url( $card['url'] ); ?>" data-wcp-link
				<?php endif; ?>
			>
				<span class="wcp-card-3d__spot" aria-hidden="true"></span>
				<span class="wcp-metric__icon wcp-depth-2" aria-hidden="true">
					<?php echo wcp_icon( $card['icon'], array( 'size' => 17 ) ); ?>
				</span>

				<span class="wcp-metric__label"><?php echo esc_html( $card['label'] ); ?></span>

				<span class="wcp-metric__value wcp-depth-1<?php echo empty( $card['word'] ) ? '' : ' wcp-metric__value--text'; ?>">
					<?php echo $card['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped or wp_kses'd where each value is built. ?>
				</span>

				<span class="wcp-metric__foot">
					<?php if ( ! empty( $card['status'] ) ) : ?>
						<?php
						echo wcp_render_template(
							'partials/order-status.php',
							array( 'status' => $card['status'] )
						);
						?>
					<?php else : ?>
						<span class="wcp-metric__text"><?php echo esc_html( $card['text'] ); ?></span>
					<?php endif; ?>

					<?php if ( $card['url'] ) : ?>
						<span class="wcp-metric__arrow" aria-hidden="true">
							<?php echo wcp_icon( 'arrow', array( 'size' => 15 ) ); ?>
						</span>
					<?php endif; ?>
				</span>
			</<?php echo esc_attr( $tag ); ?>>
		<?php endforeach; ?>
	</div>
</section>
