<?php
/**
 * Logged-out state — the portal's front door.
 *
 * A split composition: a benefit-led introduction on the left, the sign-in
 * card on the right, stacking to a single column on narrow screens.
 *
 * Renders no customer data of any kind — this template is reachable by
 * anonymous visitors and may be served from a full-page cache.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/login-required.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$instance     = (int) $wcp['instance'];
$login_url    = (string) $wcp['login_url'];
$register_url = (string) $wcp['register_url'];
$headline_id  = 'wcp-gate-headline-' . $instance;
$auth_id      = 'wcp-gate-auth-' . $instance;

$benefits = array();
foreach ( $wcp['features'] as $feature ) {
	if ( 'dashboard' === $feature['slug'] || empty( $feature['benefit_title'] ) ) {
		continue;
	}
	$benefits[] = $feature;
}

$appearance = isset( $wcp['appearance'] ) ? (array) $wcp['appearance'] : array();

$classes = WCP_Helper::class_names(
	array(
		'wcp-portal'       => true,
		'wcp-portal--gate' => true,
		'wcp-portal--full' => ( 'full' === $wcp['layout'] ),
	)
);

if ( 'full' === $wcp['layout'] ) {
	$classes = trim( $classes . ' ' . WCP_Helper::full_width_classes() );
}
?>
<div
	class="<?php echo esc_attr( $classes ); ?>"
	data-wcp-portal
	data-wcp-layout="<?php echo esc_attr( $wcp['layout'] ); ?>"
	data-wcp-motion="<?php echo empty( $appearance['motion'] ) ? 'off' : 'on'; ?>"
	data-wcp-3d="<?php echo empty( $appearance['effects_3d'] ) ? 'off' : 'on'; ?>"
>

	<div class="wcp-canvas wcp-canvas--gate" aria-hidden="true">
		<span class="wcp-canvas__wash"></span>
		<span class="wcp-canvas__grid"></span>
		<span class="wcp-canvas__noise"></span>
		<span class="wcp-canvas__glow wcp-canvas__glow--a"></span>
		<span class="wcp-canvas__glow wcp-canvas__glow--b"></span>
		<span class="wcp-canvas__glow wcp-canvas__glow--c"></span>
	</div>

	<div class="wcp-gate__controls">
		<?php
		echo wcp_render_template(
			'partials/theme-toggle.php',
			array( 'instance' => isset( $wcp['instance'] ) ? $wcp['instance'] : 1 )
		);
		?>
	</div>

	<div class="wcp-gate">

		<section class="wcp-gate__intro" aria-labelledby="<?php echo esc_attr( $headline_id ); ?>">

			<span class="wcp-brandmark wcp-animate" style="--wcp-stagger: 0;">
				<span class="wcp-brandmark__icon" aria-hidden="true">
					<?php echo wcp_icon( 'compass', array( 'size' => 18 ) ); ?>
				</span>
				<span class="wcp-brandmark__text">
					<span class="wcp-brandmark__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
					<span class="wcp-brandmark__label"><?php esc_html_e( 'Customer portal', 'woocommerce-customer-portal' ); ?></span>
				</span>
			</span>

			<h2 class="wcp-gate__headline wcp-animate" id="<?php echo esc_attr( $headline_id ); ?>" style="--wcp-stagger: 1;">
				<span class="wcp-gate__headline-line"><?php esc_html_e( 'Your account,', 'woocommerce-customer-portal' ); ?></span>
				<span class="wcp-gate__headline-line wcp-gate__headline-line--soft"><?php esc_html_e( 'beautifully organised.', 'woocommerce-customer-portal' ); ?></span>
			</h2>

			<p class="wcp-gate__lead wcp-animate" style="--wcp-stagger: 2;">
				<?php esc_html_e( 'Track orders, manage addresses and keep your account details up to date — without hunting through email receipts.', 'woocommerce-customer-portal' ); ?>
			</p>

			<?php if ( $benefits ) : ?>
				<ul class="wcp-benefits">
					<?php foreach ( $benefits as $index => $benefit ) : ?>
						<li class="wcp-benefit wcp-animate" style="--wcp-stagger: <?php echo esc_attr( (string) ( $index + 3 ) ); ?>;">
							<span class="wcp-benefit__icon" aria-hidden="true">
								<?php echo wcp_icon( $benefit['icon'], array( 'size' => 19 ) ); ?>
							</span>
							<span class="wcp-benefit__body">
								<span class="wcp-benefit__title"><?php echo esc_html( $benefit['benefit_title'] ); ?></span>
								<span class="wcp-benefit__text"><?php echo esc_html( $benefit['benefit_text'] ); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<div class="wcp-authcard__stage wcp-animate" style="--wcp-stagger: 2;" data-wcp-tilt data-wcp-tilt-max="5">
		<section
			class="wcp-authcard"
			aria-labelledby="<?php echo esc_attr( $auth_id ); ?>"
			data-wcp-spotlight
		>
			<span class="wcp-authcard__spot" aria-hidden="true"></span>
			<span class="wcp-authcard__sheen" aria-hidden="true"></span>

			<div class="wcp-authcard__inner">
				<span class="wcp-authcard__mark wcp-depth-2" aria-hidden="true">
					<span class="wcp-authcard__mark-ring"></span>
					<?php echo wcp_icon( 'lock', array( 'size' => 22 ) ); ?>
				</span>

				<h2 class="wcp-authcard__title" id="<?php echo esc_attr( $auth_id ); ?>">
					<?php esc_html_e( 'Welcome back', 'woocommerce-customer-portal' ); ?>
				</h2>

				<p class="wcp-authcard__text">
					<?php esc_html_e( 'Sign in to access your customer portal.', 'woocommerce-customer-portal' ); ?>
				</p>

				<?php if ( '' !== $login_url ) : ?>
					<a class="wcp-button wcp-button--primary wcp-button--lg wcp-button--block wcp-button--arrow" href="<?php echo esc_url( $login_url ); ?>">
						<span><?php esc_html_e( 'Sign in', 'woocommerce-customer-portal' ); ?></span>
						<span class="wcp-button__arrow" aria-hidden="true">
							<?php echo wcp_icon( 'arrow', array( 'size' => 17 ) ); ?>
						</span>
					</a>
				<?php endif; ?>

				<?php if ( '' !== $register_url ) : ?>
					<p class="wcp-authcard__alt">
						<span><?php esc_html_e( 'New here?', 'woocommerce-customer-portal' ); ?></span>
						<a class="wcp-textlink" href="<?php echo esc_url( $register_url ); ?>">
							<span><?php esc_html_e( 'Create an account', 'woocommerce-customer-portal' ); ?></span>
						</a>
					</p>
				<?php endif; ?>

				<p class="wcp-authcard__trust">
					<?php echo wcp_icon( 'shield', array( 'size' => 15 ) ); ?>
					<span><?php esc_html_e( 'Protected by your store’s secure WooCommerce sign-in. We never see your password.', 'woocommerce-customer-portal' ); ?></span>
				</p>
			</div>
		</section>

		<span class="wcp-authcard__orbit wcp-authcard__orbit--a" aria-hidden="true">
			<?php echo wcp_icon( 'orders', array( 'size' => 18 ) ); ?>
		</span>
		<span class="wcp-authcard__orbit wcp-authcard__orbit--b" aria-hidden="true">
			<?php echo wcp_icon( 'addresses', array( 'size' => 16 ) ); ?>
		</span>
		</div>
	</div>

	<?php
	// The same footer component in its compact form: enough to close the
	// screen without competing with the sign-in card.
	echo wcp_render_template(
		'partials/portal-footer.php',
		array_merge( $wcp, array( 'footer_compact' => true ) )
	);
	?>
</div>
