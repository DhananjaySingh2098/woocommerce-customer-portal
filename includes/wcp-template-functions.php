<?php
/**
 * Template-facing functions.
 *
 * Thin functions over the static helpers in `WCP_Helper`, for use inside
 * templates. They exist for one reason: PHP_CodeSniffer's escaping sniff can
 * be told that a *function* returns escaped output, but treats any static
 * method call as opaque and reports every variable in its arguments. With
 * these, templates read more simply and the sniff verifies everything else on
 * the page instead of drowning in false positives. The escaping contract is
 * unchanged: template output is escaped at source, icons are wp_kses'd.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wcp_render_template' ) ) {
	/**
	 * Render a portal template to a string.
	 *
	 * @see WCP_Helper::render_template()
	 *
	 * @param string $template Template file name relative to templates/.
	 * @param array  $data     Data exposed to the template as `$wcp`.
	 * @return string Escaped-at-source markup.
	 */
	function wcp_render_template( $template, array $data = array() ) {
		return WCP_Helper::render_template( $template, $data );
	}
}

if ( ! function_exists( 'wcp_icon' ) ) {
	/**
	 * Render a bundled icon.
	 *
	 * @see WCP_Helper::icon()
	 *
	 * @param string $name Icon name.
	 * @param array  $args Optional. `size` and `class`.
	 * @return string wp_kses-sanitised SVG.
	 */
	function wcp_icon( $name, $args = array() ) {
		return WCP_Helper::icon( $name, $args );
	}
}
