<?php
/**
 * Orders loading skeleton.
 *
 * Hidden until the portal script marks a navigation as pending, which it only
 * does after a short delay -- a skeleton that flashes on a fast connection is
 * worse than none. Mirrors the real row's geometry so the transition into
 * loaded content does not jump.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context: `rows` is how many placeholder rows to draw.
 */

defined( 'ABSPATH' ) || exit;

$rows = isset( $wcp['rows'] ) ? max( 1, min( 8, (int) $wcp['rows'] ) ) : 4;
?>
<div class="wcp-skeleton" data-wcp-skeleton hidden>
	<span class="wcp-sr-only" role="status"><?php esc_html_e( 'Loading orders…', 'woocommerce-customer-portal' ); ?></span>

	<?php for ( $i = 0; $i < $rows; $i++ ) : ?>
		<div class="wcp-skeleton__row" style="--wcp-stagger: <?php echo esc_attr( (string) $i ); ?>;" aria-hidden="true">
			<span class="wcp-skeleton__bar wcp-skeleton__bar--order"></span>
			<span class="wcp-skeleton__bar wcp-skeleton__bar--date"></span>
			<span class="wcp-skeleton__bar wcp-skeleton__bar--status"></span>
			<span class="wcp-skeleton__bar wcp-skeleton__bar--items"></span>
			<span class="wcp-skeleton__bar wcp-skeleton__bar--total"></span>
			<span class="wcp-skeleton__bar wcp-skeleton__bar--action"></span>
		</div>
	<?php endfor; ?>
</div>
