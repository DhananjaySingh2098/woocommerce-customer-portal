<?php
/**
 * Account setup, compact: the same real state as Getting started, in the rail.
 *
 * Shown to a customer who has already ordered but still has something
 * outstanding. It states the count in words as well as drawing the bar, and
 * links straight to the one step that is next.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$steps   = isset( $wcp['dash_steps'] ) ? (array) $wcp['dash_steps'] : array();
$setup   = isset( $wcp['dash_setup'] ) ? (array) $wcp['dash_setup'] : array();
$stagger = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;
$next    = isset( $setup['next'] ) ? (string) $setup['next'] : '';

if ( empty( $steps ) || '' === $next ) {
	return;
}

$done    = (int) ( isset( $setup['done'] ) ? $setup['done'] : 0 );
$total   = (int) ( isset( $setup['total'] ) ? $setup['total'] : count( $steps ) );
$percent = (int) ( isset( $setup['percent'] ) ? $setup['percent'] : 0 );

$progress_text = sprintf(
	/* translators: 1: completed steps, 2: total steps. */
	__( '%1$s of %2$s complete', 'woocommerce-customer-portal' ),
	number_format_i18n( $done ),
	number_format_i18n( $total )
);

$next_step = null;

foreach ( $steps as $step ) {
	if ( $step['key'] === $next ) {
		$next_step = $step;

		break;
	}
}
?>
<section class="wcp-panel wcp-panel--elevated wcp-panel--accent wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-progress-title">
	<div class="wcp-panel__head wcp-panel__head--row">
		<div>
			<h3 class="wcp-panel__title" id="wcp-progress-title"><?php esc_html_e( 'Account setup', 'woocommerce-customer-portal' ); ?></h3>
			<p class="wcp-panel__text"><?php echo esc_html( $progress_text ); ?></p>
		</div>
		<span class="wcp-progress__badge"><?php echo esc_html( sprintf( '%d%%', $percent ) ); ?></span>
	</div>

	<div class="wcp-setup wcp-setup--compact">
		<div class="wcp-progress">
			<div
				class="wcp-progress__track"
				role="progressbar"
				aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuetext="<?php echo esc_attr( $progress_text ); ?>"
				aria-label="<?php esc_attr_e( 'Account setup', 'woocommerce-customer-portal' ); ?>"
			>
				<span class="wcp-progress__fill" style="--wcp-progress: <?php echo esc_attr( (string) $percent ); ?>%;"></span>
			</div>
		</div>

		<?php if ( $next_step && '' !== $next_step['url'] ) : ?>
			<a
				class="wcp-next"
				href="<?php echo esc_url( $next_step['url'] ); ?>"
				<?php if ( 'order' !== $next_step['key'] ) : ?>
					data-wcp-link
				<?php endif; ?>
			>
				<span class="wcp-next__icon" aria-hidden="true">
					<?php echo wcp_icon( $next_step['icon'], array( 'size' => 15 ) ); ?>
				</span>
				<span class="wcp-next__body">
					<span class="wcp-next__label"><?php echo esc_html( $next_step['label'] ); ?></span>
					<span class="wcp-next__text"><?php echo esc_html( $next_step['text'] ); ?></span>
				</span>
				<span class="wcp-next__arrow" aria-hidden="true">
					<?php echo wcp_icon( 'arrow', array( 'size' => 15 ) ); ?>
				</span>
			</a>
		<?php endif; ?>
	</div>
</section>
