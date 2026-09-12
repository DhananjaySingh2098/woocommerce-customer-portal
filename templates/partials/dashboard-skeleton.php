<?php
/**
 * Dashboard loading skeleton.
 *
 * Mirrors the dashboard's own geometry -- hero, metric row, two panels -- so a
 * section swap does not resize the column on arrival. Hidden until script marks
 * a navigation as pending, and only after a delay, for the same reason the
 * orders skeleton is: a placeholder that flashes is worse than none.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcp-skeleton wcp-skeleton--dash" data-wcp-section-skeleton hidden>
	<span class="wcp-sr-only" role="status"><?php esc_html_e( 'Loading…', 'woocommerce-customer-portal' ); ?></span>

	<div class="wcp-skeleton__block wcp-skeleton__block--hero" style="--wcp-stagger: 0;" aria-hidden="true"></div>

	<div class="wcp-skeleton__cards" aria-hidden="true">
		<?php for ( $i = 0; $i < 4; $i++ ) : ?>
			<div class="wcp-skeleton__block wcp-skeleton__block--metric" style="--wcp-stagger: <?php echo esc_attr( (string) ( $i + 1 ) ); ?>;"></div>
		<?php endfor; ?>
	</div>

	<div class="wcp-grid wcp-grid--split" aria-hidden="true">
		<div class="wcp-skeleton__block wcp-skeleton__block--panel" style="--wcp-stagger: 5;"></div>
		<div class="wcp-skeleton__block wcp-skeleton__block--panel" style="--wcp-stagger: 6;"></div>
	</div>
</div>
