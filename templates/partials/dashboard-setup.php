<?php
/**
 * Getting started: the real setup steps, in order, with their real state.
 *
 * Each row is a condition the store can answer right now, and each unfinished
 * row links to the screen that finishes it. The percentage is completed steps
 * over offered steps -- it is never weighted, and it is never the only way to
 * read the state, because every row also says where it stands in words.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$steps   = isset( $wcp['dash_steps'] ) ? (array) $wcp['dash_steps'] : array();
$setup   = isset( $wcp['dash_setup'] ) ? (array) $wcp['dash_setup'] : array();
$stagger = isset( $wcp['dash_stagger'] ) ? (int) $wcp['dash_stagger'] : 0;

if ( empty( $steps ) ) {
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
?>
<section class="wcp-panel wcp-panel--elevated wcp-panel--feature wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) $stagger ); ?>;" aria-labelledby="wcp-setup-title">
	<div class="wcp-panel__head wcp-panel__head--row">
		<div>
			<h3 class="wcp-panel__title" id="wcp-setup-title"><?php esc_html_e( 'Getting started', 'woocommerce-customer-portal' ); ?></h3>
			<p class="wcp-panel__text"><?php esc_html_e( 'A short list, and your account is ready for checkout.', 'woocommerce-customer-portal' ); ?></p>
		</div>
		<span class="wcp-progress__badge"><?php echo esc_html( sprintf( '%d%%', $percent ) ); ?></span>
	</div>

	<div class="wcp-setup">
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
			<p class="wcp-progress__meta"><?php echo esc_html( $progress_text ); ?></p>
		</div>

		<ol class="wcp-steps">
			<?php foreach ( $steps as $index => $step ) : ?>
				<?php
				$state_class = $step['done'] ? ' is-done' : ( $step['is_current'] ? ' is-current' : '' );
				$linkable    = ! $step['done'] && '' !== $step['url'];
				$external    = ( 'order' === $step['key'] );
				$tag         = $linkable ? 'a' : 'div';

				if ( $step['done'] ) {
					$state_label = __( 'Done', 'woocommerce-customer-portal' );
				} elseif ( $step['is_current'] ) {
					$state_label = __( 'Next', 'woocommerce-customer-portal' );
				} else {
					$state_label = __( 'To do', 'woocommerce-customer-portal' );
				}
				?>
				<li class="wcp-steps__item wcp-animate<?php echo esc_attr( $state_class ); ?>" style="--wcp-stagger: <?php echo esc_attr( (string) ( $stagger + $index ) ); ?>;">
					<<?php echo esc_attr( $tag ); ?>
						class="wcp-step"
						<?php if ( $linkable ) : ?>
							href="<?php echo esc_url( $step['url'] ); ?>"
							<?php if ( ! $external ) : ?>
								data-wcp-link
							<?php endif; ?>
						<?php endif; ?>
					>
						<span class="wcp-step__index" aria-hidden="true">
							<?php if ( $step['done'] ) : ?>
								<?php echo wcp_icon( 'check', array( 'size' => 14 ) ); ?>
							<?php else : ?>
								<?php echo esc_html( sprintf( '%02d', $index + 1 ) ); ?>
							<?php endif; ?>
						</span>

						<span class="wcp-step__body">
							<span class="wcp-step__label"><?php echo esc_html( $step['label'] ); ?></span>
							<span class="wcp-step__text"><?php echo esc_html( $step['text'] ); ?></span>
						</span>

						<span class="wcp-step__state"><?php echo esc_html( $state_label ); ?></span>

						<?php if ( $linkable ) : ?>
							<span class="wcp-step__arrow" aria-hidden="true">
								<?php echo wcp_icon( $external ? 'arrow-out' : 'arrow', array( 'size' => 15 ) ); ?>
							</span>
						<?php endif; ?>
					</<?php echo esc_attr( $tag ); ?>>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
