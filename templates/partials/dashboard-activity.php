<?php
/**
 * Recent activity: milestones WooCommerce actually recorded.
 *
 * WooCommerce keeps three timestamps per order -- placed, paid, completed --
 * and those are the only events here. Nothing is reconstructed from a status
 * that has no history behind it.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$summary  = isset( $wcp['summary'] ) ? (array) $wcp['summary'] : array();
$activity = isset( $summary['activity'] ) ? (array) $summary['activity'] : array();
$stagger  = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;

if ( empty( $activity ) ) {
	return;
}
?>
<section class="wcp-panel wcp-panel--elevated wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-activity-title">
	<div class="wcp-panel__head">
		<h3 class="wcp-panel__title" id="wcp-activity-title"><?php esc_html_e( 'Recent activity', 'woocommerce-customer-portal' ); ?></h3>
		<p class="wcp-panel__text"><?php esc_html_e( 'Milestones WooCommerce recorded on your orders.', 'woocommerce-customer-portal' ); ?></p>
	</div>

	<ol class="wcp-activity">
		<?php foreach ( $activity as $index => $event ) : ?>
			<li class="wcp-activity__item wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) ( $stagger + $index ) ); ?>;">
				<span class="wcp-activity__marker wcp-activity__marker--<?php echo esc_attr( $event['tone'] ); ?>" aria-hidden="true">
					<?php echo wcp_icon( $event['icon'], array( 'size' => 13 ) ); ?>
				</span>
				<span class="wcp-activity__body">
					<span class="wcp-activity__text"><?php echo esc_html( $event['text'] ); ?></span>
					<time class="wcp-activity__time" datetime="<?php echo esc_attr( $event['date_iso'] ); ?>">
						<?php echo esc_html( '' !== $event['relative'] ? $event['relative'] : $event['date_label'] ); ?>
					</time>
				</span>
			</li>
		<?php endforeach; ?>
	</ol>
</section>
