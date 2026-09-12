<?php
/**
 * Dashboard.
 *
 * Every figure on this screen is read from WooCommerce or WordPress for the
 * authenticated customer. There are no percentage deltas on the status cards,
 * because nothing here records a prior period to compare against -- a
 * "+12% this month" would be decoration pretending to be data. The one
 * percentage on the page, account setup, counts completed steps out of offered
 * steps and is clickable all the way to the thing it is counting.
 *
 * The screen composes the same modules in both states. A customer with no
 * orders sees Getting started where a returning customer sees Recent orders;
 * everything else -- status, setup, quick actions, account health, the store's
 * newest products -- is real either way, so neither state is a hole.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/dashboard.php
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
$account       = $wcp['account'];
$summary       = isset( $wcp['summary'] ) ? (array) $wcp['summary'] : array();
$shop_url      = isset( $wcp['shop_url'] ) ? (string) $wcp['shop_url'] : '';
$addresses_url = isset( $wcp['addresses_url'] ) ? (string) $wcp['addresses_url'] : '';

/** The navigation model.
 *
 * @var WCP_Navigation $navigation
 */
$navigation = $wcp['navigation'];
$base_url   = $wcp['base_url'];

$has_orders = ! empty( $summary['has_orders'] );
$addresses  = isset( $summary['addresses'] ) ? (array) $summary['addresses'] : array();
$products   = isset( $summary['products'] ) ? (array) $summary['products'] : array();
$setup      = isset( $summary['setup'] ) ? (array) $summary['setup'] : array(
	'steps'    => array(),
	'done'     => 0,
	'total'    => 0,
	'percent'  => 0,
	'next'     => '',
	'complete' => true,
);

$profile_enabled   = ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'profile' );
$addresses_enabled = ! class_exists( 'WCP_Settings' ) || WCP_Settings::section_enabled( 'addresses' );
$profile_url       = $profile_enabled ? $navigation->get_section_url( 'profile', $base_url ) : '';
$billing_url       = ( $addresses_enabled && '' !== $addresses_url ) ? add_query_arg( 'wcp_address', 'billing', $addresses_url ) : '';
$shipping_url      = ( $addresses_enabled && '' !== $addresses_url ) ? add_query_arg( 'wcp_address', 'shipping', $addresses_url ) : '';

/*
 * Presentation for each setup step. `WCP_Dashboard` decides which steps exist
 * and whether each is done; the wording and the destination live here so a
 * theme can reword them without touching the state that drives them.
 */
$step_copy = array(
	'account'  => array(
		'label' => __( 'Account created', 'woocommerce-customer-portal' ),
		'text'  => '' !== $account->get_member_since()
			? sprintf(
				/* translators: %s: registration date. */
				__( 'Joined %s', 'woocommerce-customer-portal' ),
				$account->get_member_since()
			)
			: __( 'Your account is active.', 'woocommerce-customer-portal' ),
		'hero'  => __( 'Your account is ready to use.', 'woocommerce-customer-portal' ),
		'icon'  => 'shield',
		'url'   => '',
	),
	'profile'  => array(
		'label' => __( 'Complete your profile', 'woocommerce-customer-portal' ),
		'text'  => __( 'Add your name so orders are addressed correctly.', 'woocommerce-customer-portal' ),
		'hero'  => __( 'Add your name to finish setting up your profile.', 'woocommerce-customer-portal' ),
		'icon'  => 'profile',
		'url'   => $profile_url,
	),
	'billing'  => array(
		'label' => __( 'Add a billing address', 'woocommerce-customer-portal' ),
		'text'  => __( 'Saved once, filled in at every checkout.', 'woocommerce-customer-portal' ),
		'hero'  => __( 'Add a billing address to make checkout faster.', 'woocommerce-customer-portal' ),
		'icon'  => 'card',
		'url'   => $billing_url,
	),
	'shipping' => array(
		'label' => __( 'Add a shipping address', 'woocommerce-customer-portal' ),
		'text'  => __( 'Where your orders should be delivered.', 'woocommerce-customer-portal' ),
		'hero'  => __( 'Add a shipping address so deliveries reach you.', 'woocommerce-customer-portal' ),
		'icon'  => 'truck',
		'url'   => $shipping_url,
	),
	'order'    => array(
		'label' => __( 'Place your first order', 'woocommerce-customer-portal' ),
		'text'  => __( 'Your purchases will appear in this portal.', 'woocommerce-customer-portal' ),
		'hero'  => __( 'When you place your first order it will appear here.', 'woocommerce-customer-portal' ),
		'icon'  => 'bag',
		'url'   => $shop_url,
	),
);

// Merge real state with the copy, dropping steps whose destination is gone.
$steps = array();

foreach ( (array) $setup['steps'] as $step ) {
	$key = isset( $step['key'] ) ? (string) $step['key'] : '';

	if ( ! isset( $step_copy[ $key ] ) ) {
		continue;
	}

	$steps[] = array_merge(
		$step_copy[ $key ],
		array(
			'key'        => $key,
			'done'       => ! empty( $step['done'] ),
			'is_current' => ( isset( $setup['next'] ) && $key === $setup['next'] ),
		)
	);
}

// The hero's second sentence: the next real thing to do, or nothing left.
$hero_note = ( ! empty( $setup['complete'] ) || '' === (string) $setup['next'] || ! isset( $step_copy[ $setup['next'] ] ) )
	? __( 'Your account details are up to date.', 'woocommerce-customer-portal' )
	: $step_copy[ $setup['next'] ]['hero'];

$shared = array_merge(
	$wcp,
	array(
		'dash_setup'      => $setup,
		'dash_steps'      => $steps,
		'dash_addresses'  => $addresses,
		'dash_profile'    => $profile_url,
		'dash_billing'    => $billing_url,
		'dash_shipping'   => $shipping_url,
		'dash_has_orders' => $has_orders,
		'dash_hero_note'  => $hero_note,
		'dash_products'   => $products,
	)
);

$show_discovery = ( ! empty( $products ) || '' !== $shop_url );
?>

<?php
// Row 1 -- identity.
echo wcp_render_template( 'partials/dashboard-hero.php', array_merge( $shared, array( 'dash_stagger' => 0 ) ) );

// Row 2 -- real account state, four compact cards.
echo wcp_render_template( 'partials/dashboard-status.php', array_merge( $shared, array( 'dash_stagger' => 1 ) ) );
?>

<!-- Row 3: what to do next, and the shortcuts to do it with ------------- -->
<div class="wcp-grid wcp-grid--wide">
	<?php
	if ( $has_orders ) {
		echo wcp_render_template( 'partials/dashboard-orders.php', array_merge( $shared, array( 'dash_stagger' => 5 ) ) );
	} else {
		echo wcp_render_template( 'partials/dashboard-setup.php', array_merge( $shared, array( 'dash_stagger' => 5 ) ) );
	}
	?>

	<div class="wcp-rail">
		<?php
		if ( empty( $setup['complete'] ) && $has_orders ) {
			echo wcp_render_template( 'partials/dashboard-progress.php', array_merge( $shared, array( 'dash_stagger' => 6 ) ) );
		}

		echo wcp_render_template( 'partials/dashboard-actions.php', array_merge( $shared, array( 'dash_stagger' => 6 ) ) );
		?>
	</div>
</div>

<!-- Row 4: saved details, and the store itself --------------------------- -->
<div class="wcp-grid wcp-grid--top<?php echo $show_discovery ? ' wcp-grid--even' : ''; ?>">
	<?php
	echo wcp_render_template( 'partials/account-health.php', array_merge( $shared, array( 'dash_stagger' => 7 ) ) );

	if ( $show_discovery ) {
		echo wcp_render_template( 'partials/dashboard-discovery.php', array_merge( $shared, array( 'dash_stagger' => 8 ) ) );
	}
	?>
</div>

<?php
// Row 5 -- only when WooCommerce actually recorded something.
if ( $has_orders && ! empty( $summary['activity'] ) ) {
	echo wcp_render_template( 'partials/dashboard-activity.php', array_merge( $shared, array( 'dash_stagger' => 9 ) ) );
}
