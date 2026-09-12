<?php
/**
 * Portal application shell.
 *
 * Override by copying to: {your-theme}/woocommerce-customer-portal/portal.php
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context supplied by WCP_Shortcodes.
 */

defined( 'ABSPATH' ) || exit;

/** The authenticated account.
 *
 * @var WCP_Account $account
 */
$account    = $wcp['account'];
$instance   = (int) $wcp['instance'];
$portal_id  = 'wcp-portal-' . $instance;
$sidebar_id = 'wcp-sidebar-' . $instance;
$main_id    = 'wcp-main-' . $instance;

$appearance = isset( $wcp['appearance'] ) ? (array) $wcp['appearance'] : array();

$classes = WCP_Helper::class_names(
	array(
		'wcp-portal'       => true,
		'wcp-portal--app'  => true,
		'wcp-portal--full' => ( 'full' === $wcp['layout'] ),
	)
);

if ( 'full' === $wcp['layout'] ) {
	$classes = trim( $classes . ' ' . WCP_Helper::full_width_classes() );
}
?>
<div
	class="<?php echo esc_attr( $classes ); ?>"
	id="<?php echo esc_attr( $portal_id ); ?>"
	data-wcp-portal
	data-wcp-layout="<?php echo esc_attr( $wcp['layout'] ); ?>"
	data-wcp-section="<?php echo esc_attr( $wcp['current'] ); ?>"
	data-wcp-motion="<?php echo empty( $appearance['motion'] ) ? 'off' : 'on'; ?>"
	data-wcp-3d="<?php echo empty( $appearance['effects_3d'] ) ? 'off' : 'on'; ?>"
	style="--wcp-avatar-shift: <?php echo esc_attr( (string) $account->get_avatar_shift() ); ?>;"
>
	<a class="wcp-skip-link" href="#<?php echo esc_attr( $main_id ); ?>">
		<?php esc_html_e( 'Skip to portal content', 'woocommerce-customer-portal' ); ?>
	</a>

	<div class="wcp-portal__frame">

		<?php
		echo wcp_render_template(
			'partials/sidebar.php',
			array_merge( $wcp, array( 'sidebar_id' => $sidebar_id ) )
		);
		?>

		<div class="wcp-scrim" data-wcp-scrim aria-hidden="true"></div>

		<div class="wcp-portal__body">

			<div class="wcp-canvas wcp-canvas--app" aria-hidden="true">
				<span class="wcp-canvas__wash"></span>
				<span class="wcp-canvas__grid"></span>
				<span class="wcp-canvas__noise"></span>
				<span class="wcp-canvas__glow wcp-canvas__glow--a"></span>
				<span class="wcp-canvas__glow wcp-canvas__glow--b"></span>
			</div>

			<span class="wcp-header-sentinel" data-wcp-header-sentinel aria-hidden="true"></span>

			<?php
			echo wcp_render_template(
				'partials/header.php',
				array_merge( $wcp, array( 'sidebar_id' => $sidebar_id ) )
			);
			?>

			<main class="wcp-main" id="<?php echo esc_attr( $main_id ); ?>" tabindex="-1">
				<?php
				echo wcp_render_template(
					'partials/dashboard-skeleton.php',
					$wcp
				);
				?>

				<div class="wcp-main__inner" data-wcp-content>
					<?php
					// Chosen by WCP_Shortcodes from a fixed set of templates; never
					// assembled from request input.
					$section_template = isset( $wcp['section_template'] )
						? $wcp['section_template']
						: 'partials/section-upcoming.php';

					echo wcp_render_template(
						$section_template,
						$wcp
					);
					?>
				</div>
			</main>

			<?php
			// One footer for every section: the shell renders it, not the
			// section templates, so it can never drift between screens.
			echo wcp_render_template( 'partials/portal-footer.php', $wcp );
			?>
		</div>
	</div>
</div>
