<?php
/**
 * Placeholder body for sections whose native portal view ships in a later phase.
 *
 * Rather than a dead end, each section links to the equivalent WooCommerce My
 * Account endpoint so the portal is useful today and honest about what it does
 * not yet render itself.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$item = null;
foreach ( $wcp['items'] as $candidate ) {
	if ( ! empty( $candidate['is_current'] ) ) {
		$item = $candidate;
		break;
	}
}

if ( null === $item ) {
	return;
}
?>

<section class="wcp-section" aria-labelledby="wcp-upcoming-title">
	<div class="wcp-panel wcp-panel--centered wcp-animate" style="--wcp-stagger: 0;">
		<div class="wcp-empty wcp-empty--lg">
			<span class="wcp-empty__art" aria-hidden="true">
				<span class="wcp-empty__ring"></span>
				<span class="wcp-empty__glyph">
					<?php echo wcp_icon( $item['icon'], array( 'size' => 28 ) ); ?>
				</span>
			</span>

			<span class="wcp-pill wcp-pill--accent"><?php esc_html_e( 'In development', 'woocommerce-customer-portal' ); ?></span>

			<h2 class="wcp-empty__title wcp-empty__title--lg" id="wcp-upcoming-title">
				<?php
				printf(
					/* translators: %s: section name, e.g. Orders. */
					esc_html__( '%s is coming to the portal', 'woocommerce-customer-portal' ),
					esc_html( $item['label'] )
				);
				?>
			</h2>

			<p class="wcp-empty__text"><?php echo esc_html( $item['description'] ); ?></p>

			<?php if ( ! empty( $item['endpoint_url'] ) ) : ?>
				<div class="wcp-empty__actions">
					<a class="wcp-button wcp-button--primary" href="<?php echo esc_url( $item['endpoint_url'] ); ?>">
						<span><?php esc_html_e( 'Open in My Account', 'woocommerce-customer-portal' ); ?></span>
						<?php echo wcp_icon( 'arrow-out', array( 'size' => 16 ) ); ?>
					</a>
					<a class="wcp-button wcp-button--ghost" href="<?php echo esc_url( $wcp['base_url'] ); ?>">
						<span><?php esc_html_e( 'Back to dashboard', 'woocommerce-customer-portal' ); ?></span>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
