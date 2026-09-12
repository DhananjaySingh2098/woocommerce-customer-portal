<?php
/**
 * Dashboard hero: who is signed in, and the one thing worth saying to them.
 *
 * The second sentence is derived from real account state -- the next setup
 * step that is actually outstanding, or a plain statement that nothing is.
 * It is never a statistic.
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
$note    = isset( $wcp['dash_hero_note'] ) ? (string) $wcp['dash_hero_note'] : '';
$stagger = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;
$hour    = (int) current_time( 'G' );

if ( $hour < 12 ) {
	$greeting = __( 'Good morning', 'woocommerce-customer-portal' );
} elseif ( $hour < 18 ) {
	$greeting = __( 'Good afternoon', 'woocommerce-customer-portal' );
} else {
	$greeting = __( 'Good evening', 'woocommerce-customer-portal' );
}
?>
<section class="wcp-hero wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-hero-title" data-wcp-spotlight>
	<span class="wcp-hero__spot" aria-hidden="true"></span>
	<div class="wcp-hero__glow" aria-hidden="true"></div>
	<div class="wcp-hero__grid" aria-hidden="true"></div>

	<div class="wcp-hero__content">
		<p class="wcp-hero__eyebrow">
			<span class="wcp-hero__eyebrow-dot" aria-hidden="true"></span>
			<span><?php echo esc_html( $greeting ); ?></span>
		</p>
		<h2 class="wcp-hero__title" id="wcp-hero-title">
			<?php
			printf(
				/* translators: %s: customer first name. */
				esc_html__( 'Welcome back, %s.', 'woocommerce-customer-portal' ),
				esc_html( $account->get_greeting_name() )
			);
			?>
		</h2>
		<?php if ( '' !== $note ) : ?>
			<p class="wcp-hero__text"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>

		<ul class="wcp-meta-list">
			<li class="wcp-meta-list__item">
				<?php echo wcp_icon( 'mail', array( 'size' => 15 ) ); ?>
				<span><?php echo esc_html( $account->get_email() ); ?></span>
			</li>
			<?php if ( '' !== $account->get_member_since() ) : ?>
				<li class="wcp-meta-list__item">
					<?php echo wcp_icon( 'calendar', array( 'size' => 15 ) ); ?>
					<span>
						<?php
						printf(
							/* translators: %s: registration date. */
							esc_html__( 'Member since %s', 'woocommerce-customer-portal' ),
							esc_html( $account->get_member_since() )
						);
						?>
					</span>
				</li>
			<?php endif; ?>
		</ul>
	</div>

	<div class="wcp-hero__aside" aria-hidden="true">
		<div class="wcp-identity" data-wcp-tilt data-wcp-tilt-max="6">
			<span class="wcp-identity__sheen"></span>
			<span class="wcp-identity__ring wcp-identity__ring--a"></span>
			<span class="wcp-identity__ring wcp-identity__ring--b"></span>
			<span class="wcp-avatar wcp-avatar--xl wcp-depth-3"><?php echo esc_html( $account->get_initials() ); ?></span>
			<span class="wcp-identity__name wcp-depth-1"><?php echo esc_html( $account->get_display_name() ); ?></span>
			<span class="wcp-identity__badge wcp-depth-2">
				<?php echo wcp_icon( 'shield', array( 'size' => 12 ) ); ?>
				<span><?php esc_html_e( 'Customer account', 'woocommerce-customer-portal' ); ?></span>
			</span>
		</div>
	</div>
</section>
