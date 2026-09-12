<?php
/**
 * Addresses overview.
 *
 * Two preview cards. Each shows WooCommerce's own formatted rendering of the
 * stored address -- which means it reads the way an address is written in that
 * country, not in a fixed Anglophone order -- or an empty state that says what
 * the address is for.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/addresses.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$addresses = isset( $wcp['addresses'] ) ? (array) $wcp['addresses'] : array();
$flash     = isset( $wcp['flash'] ) ? (array) $wcp['flash'] : array();

$blurbs = array(
	'billing'  => __( 'Where your invoices and payment receipts are addressed.', 'woocommerce-customer-portal' ),
	'shipping' => __( 'Where your orders are delivered by default.', 'woocommerce-customer-portal' ),
);
?>

<section class="wcp-section" aria-labelledby="wcp-addresses-title">

	<div class="wcp-section__head wcp-animate" style="--wcp-stagger: 0;">
		<h2 class="wcp-section__title" id="wcp-addresses-title"><?php esc_html_e( 'Saved addresses', 'woocommerce-customer-portal' ); ?></h2>
		<p class="wcp-section__text"><?php esc_html_e( 'These are used to prefill checkout. You can change them at any time.', 'woocommerce-customer-portal' ); ?></p>
	</div>

	<?php
	echo wcp_render_template(
		'partials/form-feedback.php',
		array(
			'flash' => $flash,
			'form'  => 'address',
		)
	);
	?>

	<div class="wcp-grid wcp-grid--even">
		<?php foreach ( $addresses as $index => $address ) : ?>
			<article class="wcp-panel wcp-panel--elevated wcp-address-card wcp-card-3d wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) ( $index + 1 ) ); ?>;" data-wcp-tilt data-wcp-tilt-max="4" data-wcp-spotlight>
				<span class="wcp-card-3d__spot" aria-hidden="true"></span>
				<span class="wcp-address-card__pattern" aria-hidden="true"></span>
				<div class="wcp-panel__head wcp-address-card__head">
					<span class="wcp-address-card__icon wcp-depth-2" aria-hidden="true">
						<?php
						echo wcp_icon(
							'shipping' === $address['type'] ? 'truck' : 'card',
							array( 'size' => 18 )
						);
						?>
					</span>
					<div class="wcp-address-card__heading">
						<h3 class="wcp-panel__title"><?php echo esc_html( $address['label'] ); ?></h3>
						<p class="wcp-address-card__blurb">
							<?php echo esc_html( isset( $blurbs[ $address['type'] ] ) ? $blurbs[ $address['type'] ] : '' ); ?>
						</p>
					</div>
					<span class="wcp-address-card__state wcp-address-card__state--<?php echo $address['is_set'] ? 'set' : 'empty'; ?>">
						<?php echo wcp_icon( $address['is_set'] ? 'check' : 'minus', array( 'size' => 11 ) ); ?>
						<span><?php echo $address['is_set'] ? esc_html__( 'Saved', 'woocommerce-customer-portal' ) : esc_html__( 'Empty', 'woocommerce-customer-portal' ); ?></span>
					</span>
				</div>

				<div class="wcp-address-card__body">
					<?php if ( $address['is_set'] && '' !== $address['formatted'] ) : ?>
						<address class="wcp-address">
							<?php echo wp_kses( $address['formatted'], WCP_Security::allowed_address_html() ); ?>
						</address>
					<?php else : ?>
						<div class="wcp-address-card__empty">
							<span class="wcp-address-card__empty-art" aria-hidden="true">
								<?php echo wcp_icon( 'addresses', array( 'size' => 20 ) ); ?>
							</span>
							<p class="wcp-address-card__empty-title"><?php esc_html_e( 'Not set yet', 'woocommerce-customer-portal' ); ?></p>
							<p class="wcp-address-card__empty-text">
								<?php esc_html_e( 'Add this address once and checkout will fill it in for you.', 'woocommerce-customer-portal' ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>

				<a class="wcp-panel__link wcp-address-card__edit" href="<?php echo esc_url( $address['edit_url'] ); ?>" data-wcp-pending data-wcp-link>
					<span>
						<?php
						if ( $address['is_set'] ) {
							printf(
								/* translators: %s: address label, e.g. "Billing address". */
								esc_html__( 'Edit %s', 'woocommerce-customer-portal' ),
								esc_html( strtolower( $address['label'] ) )
							);
						} else {
							printf(
								/* translators: %s: address label, e.g. "Billing address". */
								esc_html__( 'Add %s', 'woocommerce-customer-portal' ),
								esc_html( strtolower( $address['label'] ) )
							);
						}
						?>
					</span>
					<?php echo wcp_icon( 'chevron', array( 'size' => 15 ) ); ?>
				</a>
			</article>
		<?php endforeach; ?>
	</div>

	<ul class="wcp-notes wcp-animate" style="--wcp-stagger: 3;">
		<li class="wcp-note">
			<span class="wcp-note__icon" aria-hidden="true"><?php echo wcp_icon( 'bag', array( 'size' => 16 ) ); ?></span>
			<span class="wcp-note__body">
				<span class="wcp-note__title"><?php esc_html_e( 'Used at checkout', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-note__text"><?php esc_html_e( 'Both addresses are filled in for you on your next order.', 'woocommerce-customer-portal' ); ?></span>
			</span>
		</li>
		<li class="wcp-note">
			<span class="wcp-note__icon" aria-hidden="true"><?php echo wcp_icon( 'globe', array( 'size' => 16 ) ); ?></span>
			<span class="wcp-note__body">
				<span class="wcp-note__title"><?php esc_html_e( 'Formatted per country', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-note__text"><?php esc_html_e( 'Fields and layout follow the conventions of the country you pick.', 'woocommerce-customer-portal' ); ?></span>
			</span>
		</li>
		<li class="wcp-note">
			<span class="wcp-note__icon" aria-hidden="true"><?php echo wcp_icon( 'shield', array( 'size' => 16 ) ); ?></span>
			<span class="wcp-note__body">
				<span class="wcp-note__title"><?php esc_html_e( 'Only you can change them', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-note__text"><?php esc_html_e( 'Edits are saved to your account and never shared with other customers.', 'woocommerce-customer-portal' ); ?></span>
			</span>
		</li>
	</ul>
</section>
