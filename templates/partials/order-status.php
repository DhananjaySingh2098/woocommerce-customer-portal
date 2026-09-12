<?php
/**
 * Order status pill.
 *
 * The tone class is chosen from a fixed list in `WCP_Order_Status`, so a custom
 * or unknown status renders with the neutral tone rather than no tone at all.
 * Every pill carries a glyph as well as a colour, so a status is never
 * conveyed by colour alone.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context: `status` describes one status.
 */

defined( 'ABSPATH' ) || exit;

$status = isset( $wcp['status'] ) ? $wcp['status'] : array();

if ( empty( $status['label'] ) ) {
	return;
}

$icon = ! empty( $status['icon'] ) ? (string) $status['icon'] : 'minus';
?>
<span class="wcp-order-status <?php echo esc_attr( $status['class'] ); ?>">
	<span class="wcp-order-status__dot" aria-hidden="true">
		<?php echo wcp_icon( $icon, array( 'size' => 11 ) ); ?>
	</span>
	<span class="wcp-order-status__label"><?php echo esc_html( $status['label'] ); ?></span>
</span>
