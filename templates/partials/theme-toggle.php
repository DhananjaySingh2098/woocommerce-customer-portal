<?php
/**
 * Appearance switcher: colour scheme and visual theme.
 *
 * A trigger button opens a small dialog holding two radio groups: Appearance
 * (System / Light / Dark) and Theme (Aurora, Obsidian, Pearl, Midnight,
 * Emerald). The choice is
 * stored in `localStorage` by the portal script and applied to the document
 * before first paint by the head script -- nothing here depends on the
 * customer being signed in, and nothing here is sent to the server.
 *
 * Rendered unchecked on the server: the server cannot know the preference.
 * The portal script corrects `aria-checked` on init, so the control is never
 * wrong for longer than a frame. Without script the dialog stays hidden and
 * the trigger is inert, which is the honest state.
 *
 * @package WooCommerce_Customer_Portal
 *
 * @var array $wcp Template context.
 */

defined( 'ABSPATH' ) || exit;

$instance = isset( $wcp['instance'] ) ? (int) $wcp['instance'] : 1;
$menu_id  = 'wcp-appearance-' . $instance;
$mode_id  = $menu_id . '-mode';
$theme_id = $menu_id . '-theme';

$modes = array(
	'system' => array(
		'label' => __( 'System', 'woocommerce-customer-portal' ),
		'icon'  => 'monitor',
	),
	'light'  => array(
		'label' => __( 'Light', 'woocommerce-customer-portal' ),
		'icon'  => 'sun',
	),
	'dark'   => array(
		'label' => __( 'Dark', 'woocommerce-customer-portal' ),
		'icon'  => 'moon',
	),
);

$preset_text = array(
	'aurora'   => __( 'Violet and indigo', 'woocommerce-customer-portal' ),
	'obsidian' => __( 'Graphite and charcoal', 'woocommerce-customer-portal' ),
	'pearl'    => __( 'Warm and minimal', 'woocommerce-customer-portal' ),
	'midnight' => __( 'Deep navy and cobalt', 'woocommerce-customer-portal' ),
	'emerald'  => __( 'Charcoal and emerald', 'woocommerce-customer-portal' ),
);

$presets = array();

foreach ( WCP_Theme::visual_labels() as $slug => $label ) {
	$presets[ $slug ] = array(
		'label' => $label,
		'text'  => isset( $preset_text[ $slug ] ) ? $preset_text[ $slug ] : '',
	);
}
?>
<div class="wcp-appearance" data-wcp-appearance>
	<button
		type="button"
		class="wcp-icon-button wcp-appearance__trigger"
		data-wcp-appearance-trigger
		aria-haspopup="dialog"
		aria-expanded="false"
		aria-controls="<?php echo esc_attr( $menu_id ); ?>"
	>
		<span class="wcp-sr-only"><?php esc_html_e( 'Appearance and theme', 'woocommerce-customer-portal' ); ?></span>
		<span class="wcp-appearance__glyph wcp-appearance__glyph--sun" aria-hidden="true">
			<?php echo wcp_icon( 'sun', array( 'size' => 18 ) ); ?>
		</span>
		<span class="wcp-appearance__glyph wcp-appearance__glyph--moon" aria-hidden="true">
			<?php echo wcp_icon( 'moon', array( 'size' => 18 ) ); ?>
		</span>
	</button>

	<div
		class="wcp-appearance__menu"
		id="<?php echo esc_attr( $menu_id ); ?>"
		role="dialog"
		aria-label="<?php esc_attr_e( 'Appearance and theme', 'woocommerce-customer-portal' ); ?>"
		data-wcp-appearance-menu
		hidden
	>
		<div class="wcp-appearance__group" role="radiogroup" aria-labelledby="<?php echo esc_attr( $mode_id ); ?>">
			<p class="wcp-appearance__label" id="<?php echo esc_attr( $mode_id ); ?>"><?php esc_html_e( 'Appearance', 'woocommerce-customer-portal' ); ?></p>
			<div class="wcp-appearance__modes">
				<?php foreach ( $modes as $mode => $meta ) : ?>
					<button
						type="button"
						class="wcp-appearance__mode"
						role="radio"
						aria-checked="false"
						tabindex="-1"
						data-wcp-appearance-mode="<?php echo esc_attr( $mode ); ?>"
					>
						<span class="wcp-appearance__mode-icon" aria-hidden="true">
							<?php echo wcp_icon( $meta['icon'], array( 'size' => 16 ) ); ?>
						</span>
						<span class="wcp-appearance__mode-label"><?php echo esc_html( $meta['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="wcp-appearance__group" role="radiogroup" aria-labelledby="<?php echo esc_attr( $theme_id ); ?>">
			<p class="wcp-appearance__label" id="<?php echo esc_attr( $theme_id ); ?>"><?php esc_html_e( 'Theme', 'woocommerce-customer-portal' ); ?></p>
			<div class="wcp-appearance__presets">
				<?php foreach ( $presets as $preset => $meta ) : ?>
					<button
						type="button"
						class="wcp-appearance__preset"
						role="radio"
						aria-checked="false"
						tabindex="-1"
						data-wcp-visual-option="<?php echo esc_attr( $preset ); ?>"
					>
						<span class="wcp-swatch wcp-swatch--<?php echo esc_attr( $preset ); ?>" aria-hidden="true">
							<span class="wcp-swatch__bg"></span>
							<span class="wcp-swatch__card"></span>
							<span class="wcp-swatch__accent"></span>
						</span>
						<span class="wcp-appearance__preset-body">
							<span class="wcp-appearance__preset-label"><?php echo esc_html( $meta['label'] ); ?></span>
							<span class="wcp-appearance__preset-text"><?php echo esc_html( $meta['text'] ); ?></span>
						</span>
						<span class="wcp-appearance__check" aria-hidden="true">
							<?php echo wcp_icon( 'check', array( 'size' => 13 ) ); ?>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
