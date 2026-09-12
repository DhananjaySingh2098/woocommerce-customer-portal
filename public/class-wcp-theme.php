<?php
/**
 * Colour-scheme preference.
 *
 * The portal reads `data-wcp-theme` on the document element. Three things have
 * to line up for that to feel right:
 *
 *   1. The attribute must be set before the browser paints, or the customer
 *      sees a white flash and then the dark portal. That means a small
 *      synchronous script in `<head>` -- the one place a stylesheet cannot
 *      help, because the choice lives in `localStorage`.
 *   2. With no stored choice, the operating system decides. A visitor who has
 *      set their machine to dark should not have to set the portal to dark.
 *   3. The attribute goes on `<html>`, but the dark palette is scoped to
 *      `.wcp-portal` descendants. The surrounding theme is never restyled --
 *      turning the portal dark must not turn someone's site dark.
 *
 * Nothing here touches business logic: it swaps token values and nothing else.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the theme attribute to a stored preference.
 */
class WCP_Theme {

	/**
	 * `localStorage` key holding the customer's choice.
	 */
	const STORAGE_KEY = 'wcp-theme';

	/**
	 * Attribute the stylesheet keys off.
	 */
	const ATTRIBUTE = 'data-wcp-theme';

	/**
	 * `localStorage` key holding the customer's visual theme preset.
	 */
	const VISUAL_STORAGE_KEY = 'wcp-visual';

	/**
	 * Attribute carrying the visual theme preset.
	 */
	const VISUAL_ATTRIBUTE = 'data-wcp-visual';

	/**
	 * Page framing controller, used to decide whether this page needs the script.
	 *
	 * @var WCP_Page_Layout
	 */
	private $page_layout;

	/**
	 * Constructor.
	 *
	 * @param WCP_Page_Layout $page_layout Page framing controller.
	 */
	public function __construct( WCP_Page_Layout $page_layout ) {
		$this->page_layout = $page_layout;
	}

	/**
	 * Print the pre-paint theme resolver.
	 *
	 * Deliberately inline and synchronous. An external file would be a second
	 * request between markup and paint, which is exactly the window this exists
	 * to close. It is a few hundred bytes, runs once, and is printed only on
	 * pages that actually host the portal.
	 *
	 * Everything is wrapped in try/catch: `localStorage` throws outright in
	 * some privacy modes, and a theme preference is never worth breaking a page
	 * over. On failure the portal simply stays light.
	 *
	 * @return void
	 */
	public function print_head_script() {
		if ( ! $this->page_layout->has_portal() ) {
			return;
		}

		$key        = self::STORAGE_KEY;
		$attribute  = self::ATTRIBUTE;
		$default    = self::admin_default();
		$visual_key = self::VISUAL_STORAGE_KEY;
		$visual_at  = self::VISUAL_ATTRIBUTE;
		$visual     = self::admin_visual_default();
		$presets    = implode( '|', self::visual_presets() );

		/*
		 * Precedence, highest first.
		 *
		 * Appearance (light / dark):
		 *   1. The customer's explicit choice, stored by the switcher.
		 *   2. The administrator's configured default, when it is light or dark.
		 *   3. The operating system, via prefers-color-scheme.
		 *
		 * Visual theme (aurora / obsidian / pearl / midnight / emerald):
		 *   1. The customer's explicit choice, stored by the switcher.
		 *   2. The administrator's configured default.
		 *
		 * Every interpolated value is one of a handful of constant strings,
		 * validated in PHP against a fixed list, so nothing here can introduce
		 * anything into the script.
		 */
		$script = <<<JS
(function(){try{
var d=document.documentElement,l=window.localStorage;
var s=l?l.getItem('{$key}'):null;
if(s!=='light'&&s!=='dark'){
s='{$default}';
}
if(s!=='light'&&s!=='dark'){
s=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';
}
d.setAttribute('{$attribute}',s);
var v=l?l.getItem('{$visual_key}'):null;
if(!v||!/^({$presets})$/.test(v)){
v='{$visual}';
}
d.setAttribute('{$visual_at}',v);
}catch(e){}})();
JS;

		/**
		 * Filter the attributes on the pre-paint theme script tag.
		 *
		 * Exists so a site running a strict Content-Security-Policy can attach
		 * its `nonce`. Keys are attribute names; the `id` is always set by the
		 * plugin and cannot be overridden.
		 *
		 * @param array $attributes Attribute name => value.
		 */
		$attributes = (array) apply_filters( 'wcp_theme_script_attributes', array() );

		$attributes['id'] = 'wcp-theme-boot';

		$rendered = '';

		foreach ( $attributes as $name => $value ) {
			// Only plain attribute names survive; a filter cannot smuggle in a
			// second attribute or a closing bracket through the key.
			if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9-]*$/', (string) $name ) || ! is_scalar( $value ) ) {
				continue;
			}

			$rendered .= sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
		}

		printf(
			"<script%s>%s</script>\n",
			$rendered, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each attribute name is pattern-checked and each value passed through esc_attr() above.
			$script // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static script; interpolations are class constants and a value validated against a fixed list.
		);
	}

	/**
	 * The administrator's configured default, validated.
	 *
	 * Returns `system`, `light` or `dark` and nothing else, whatever is in the
	 * option row -- `WCP_Settings` already guarantees that, and this re-checks
	 * because the value is about to be placed inside a script.
	 *
	 * @return string
	 */
	public static function admin_default() {
		$value = class_exists( 'WCP_Settings' ) ? (string) WCP_Settings::get( 'theme' ) : 'system';

		return in_array( $value, array( 'system', 'light', 'dark' ), true ) ? $value : 'system';
	}

	/**
	 * The visual theme presets, in display order.
	 *
	 * @return string[]
	 */
	public static function visual_presets() {
		return class_exists( 'WCP_Settings' ) ? WCP_Settings::VISUAL_THEMES : array( 'aurora', 'obsidian', 'pearl', 'midnight', 'emerald' );
	}

	/**
	 * The administrator's default visual theme, validated against the list.
	 *
	 * @return string
	 */
	public static function admin_visual_default() {
		$value = class_exists( 'WCP_Settings' ) ? WCP_Settings::visual_theme() : 'aurora';

		return in_array( $value, self::visual_presets(), true ) ? $value : 'aurora';
	}

	/**
	 * Display names for the visual presets.
	 *
	 * One list, read by the appearance switcher, by the account panel and by
	 * the front-end script, so a preset is never called two different things
	 * on the same screen. Keys outside the validated preset list are dropped.
	 *
	 * @return array<string,string>
	 */
	public static function visual_labels() {
		$labels = array(
			'aurora'   => __( 'Aurora', 'woocommerce-customer-portal' ),
			'obsidian' => __( 'Obsidian', 'woocommerce-customer-portal' ),
			'pearl'    => __( 'Pearl', 'woocommerce-customer-portal' ),
			'midnight' => __( 'Midnight', 'woocommerce-customer-portal' ),
			'emerald'  => __( 'Emerald', 'woocommerce-customer-portal' ),
		);

		$ordered = array();

		foreach ( self::visual_presets() as $preset ) {
			$ordered[ $preset ] = isset( $labels[ $preset ] )
				? $labels[ $preset ]
				: ucfirst( str_replace( '-', ' ', $preset ) );
		}

		return $ordered;
	}

	/**
	 * Configuration handed to the front-end script.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'storageKey'      => self::STORAGE_KEY,
			'attribute'       => self::ATTRIBUTE,
			'adminDefault'    => self::admin_default(),
			'visualKey'       => self::VISUAL_STORAGE_KEY,
			'visualAttribute' => self::VISUAL_ATTRIBUTE,
			'visualDefault'   => self::admin_visual_default(),
			'visualPresets'   => self::visual_presets(),
			'visualLabels'    => self::visual_labels(),
			'motion'          => ! class_exists( 'WCP_Settings' ) || (bool) WCP_Settings::get( 'motion' ),
			'effects3d'       => ! class_exists( 'WCP_Settings' ) || (bool) WCP_Settings::get( 'effects_3d' ),
		);
	}
}
