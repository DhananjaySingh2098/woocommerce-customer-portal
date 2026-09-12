<?php
/**
 * Shared, dependency-free helpers.
 *
 * Loaded first during bootstrap so it can report environment problems before
 * any other class exists. Nothing here may assume WooCommerce is present.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static utility helpers used across the plugin.
 */
class WCP_Helper {

	/**
	 * Reason descriptors queued by the bootstrap guards.
	 *
	 * @var array<int,array>
	 */
	private static $notices = array();

	/**
	 * Cached icon markup, keyed by icon name.
	 *
	 * @var array<string,string>
	 */
	private static $icon_cache = array();

	/*
	|----------------------------------------------------------------------
	| Admin notices
	|----------------------------------------------------------------------
	*/

	/**
	 * Queue an admin notice explaining why the portal is disabled.
	 *
	 * Takes a reason descriptor rather than a finished string, because callers
	 * run on `plugins_loaded` — before translations may safely be loaded.
	 *
	 * @param array $reason Reason descriptor with at least a `code` key.
	 * @return void
	 */
	public static function add_blocking_notice( $reason ) {
		if ( ! is_array( $reason ) || empty( $reason['code'] ) ) {
			return;
		}

		self::$notices[] = $reason;

		if ( 1 === count( self::$notices ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_blocking_notices' ) );
		}
	}

	/**
	 * Compose the translated message for a reason descriptor.
	 *
	 * Only ever called from `admin_notices`, long after `init`.
	 *
	 * @param array $reason Reason descriptor.
	 * @return string
	 */
	public static function notice_message( array $reason ) {
		$required = isset( $reason['required'] ) ? (string) $reason['required'] : '';
		$current  = isset( $reason['current'] ) ? (string) $reason['current'] : '';

		switch ( $reason['code'] ) {
			case 'php_version':
				return sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'This plugin requires PHP %1$s or newer. This site is running PHP %2$s.', 'woocommerce-customer-portal' ),
					$required,
					$current
				);

			case 'wp_version':
				return sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version. */
					__( 'This plugin requires WordPress %1$s or newer. This site is running WordPress %2$s.', 'woocommerce-customer-portal' ),
					$required,
					$current
				);

			case 'wc_version':
				return sprintf(
					/* translators: 1: required WooCommerce version, 2: current WooCommerce version. */
					__( 'This plugin requires WooCommerce %1$s or newer. This site is running WooCommerce %2$s.', 'woocommerce-customer-portal' ),
					$required,
					$current
				);

			case 'wc_missing':
			default:
				return __( 'WooCommerce must be installed and active. The customer portal stays disabled until it is available.', 'woocommerce-customer-portal' );
		}
	}

	/**
	 * Render queued notices for users who can act on them.
	 *
	 * @return void
	 */
	public static function render_blocking_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( self::$notices as $reason ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'WooCommerce Customer Portal:', 'woocommerce-customer-portal' ),
				esc_html( self::notice_message( $reason ) )
			);
		}
	}

	/*
	|----------------------------------------------------------------------
	| Templates
	|----------------------------------------------------------------------
	*/

	/**
	 * Resolve a template path, honouring theme overrides.
	 *
	 * Lookup order:
	 *   1. {child-theme}/woocommerce-customer-portal/{template}
	 *   2. {parent-theme}/woocommerce-customer-portal/{template}
	 *   3. {plugin}/templates/{template}
	 *
	 * @param string $template Template file name relative to templates/.
	 * @return string Absolute path, or an empty string when not found.
	 */
	public static function locate_template( $template ) {
		$template = ltrim( (string) $template, '/' );

		// Reject traversal attempts outright.
		if ( '' === $template || false !== strpos( $template, '..' ) ) {
			return '';
		}

		$theme_path = locate_template(
			array(
				'woocommerce-customer-portal/' . $template,
			)
		);

		if ( $theme_path ) {
			return $theme_path;
		}

		$plugin_path = WCP_PLUGIN_DIR . 'templates/' . $template;

		return file_exists( $plugin_path ) ? $plugin_path : '';
	}

	/**
	 * Render a template to a string with a scoped variable bag.
	 *
	 * Data is extracted into a single `$wcp` array rather than loose variables,
	 * which keeps template scope predictable and greppable.
	 *
	 * @param string $template Template file name relative to templates/.
	 * @param array  $data     Data exposed to the template as `$wcp`.
	 * @return string Rendered markup, or an empty string when the template is missing.
	 */
	public static function render_template( $template, array $data = array() ) {
		$path = self::locate_template( $template );

		if ( '' === $path ) {
			return '';
		}

		// Consumed inside the included template.
		$wcp = $data; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Render an admin view directly to output.
	 *
	 * Admin views are never theme-overridable: a theme has no business
	 * replacing a settings screen, and allowing it would let a theme file run
	 * with `manage_woocommerce` context. Lookup is the plugin directory only,
	 * with the same traversal guard as front-end templates.
	 *
	 * @param string $view Template file name relative to admin/views/.
	 * @param array  $data Data exposed to the view as `$wcp`.
	 * @return void
	 */
	public static function render_admin_view( $view, array $data = array() ) {
		$view = ltrim( (string) $view, '/' );

		if ( '' === $view || false !== strpos( $view, '..' ) ) {
			return;
		}

		$path = WCP_PLUGIN_DIR . 'admin/views/' . $view;

		if ( ! file_exists( $path ) ) {
			return;
		}

		$wcp = $data; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

		include $path;
	}

	/*
	|----------------------------------------------------------------------
	| Assets
	|----------------------------------------------------------------------
	*/

	/**
	 * Build a URL to a bundled asset.
	 *
	 * @param string $relative_path Path relative to assets/.
	 * @return string
	 */
	public static function asset_url( $relative_path ) {
		return WCP_PLUGIN_URL . 'assets/' . ltrim( (string) $relative_path, '/' );
	}

	/**
	 * Return a cache-busting version string for bundled assets.
	 *
	 * Uses the file modification time while `SCRIPT_DEBUG` is on so local
	 * development never serves a stale stylesheet.
	 *
	 * @param string $relative_path Path relative to assets/.
	 * @return string
	 */
	public static function asset_version( $relative_path ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$file = WCP_PLUGIN_DIR . 'assets/' . ltrim( (string) $relative_path, '/' );

			if ( file_exists( $file ) ) {
				return (string) filemtime( $file );
			}
		}

		return WCP_VERSION;
	}

	/**
	 * Extra root classes needed for the portal to span the full viewport.
	 *
	 * Block themes constrain content children with `margin-left: auto
	 * !important`, deliberately, and the sanctioned way out is the core
	 * `alignfull` class — the same mechanism the block editor uses. Handing the
	 * theme its own vocabulary is far safer than out-shouting it with
	 * `!important`. Classic themes get nothing here and fall back to the
	 * measured bleed applied by script.
	 *
	 * @return string
	 */
	public static function full_width_classes() {
		// `alignfull` is the vocabulary a theme uses to say "this element is
		// exempt from my content column" -- Twenty Twenty-One states it literally,
		// as `.entry-content > *:not(.alignfull):not(...)`. Any theme that
		// declares wide-alignment support has agreed to honour it, and block
		// themes get that support automatically. Speaking the theme's own language
		// beats out-specifying a rule the theme wrote on purpose.
		// Block themes express wide alignment through theme.json and report
		// false for the `align-wide` feature flag, so both signals are needed:
		// one for block themes, one for classic themes that opted in.
		$is_block = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		$declares = function_exists( 'current_theme_supports' ) && current_theme_supports( 'align-wide' );
		$classes  = ( $is_block || $declares ) ? 'alignfull' : '';

		/**
		 * Filter the theme-level classes used to break the portal out of a
		 * constrained content column.
		 *
		 * @param string $classes Space-separated class names.
		 */
		return (string) apply_filters( 'wcp_full_width_classes', $classes );
	}

	/*
	|----------------------------------------------------------------------
	| Icons
	|----------------------------------------------------------------------
	*/

	/**
	 * Raw path data for the bundled icon set.
	 *
	 * Hand-authored 24x24 stroke icons so the plugin ships no icon font, no
	 * sprite request and no third-party licence obligation.
	 *
	 * @return array<string,string>
	 */
	private static function icon_paths() {
		return array(
			'dashboard'  => '<rect x="3" y="3" width="7.5" height="7.5" rx="2.25"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2.25"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2.25"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2.25"/>',
			'orders'     => '<path d="M20.5 7.5 12 3 3.5 7.5v9L12 21l8.5-4.5v-9Z"/><path d="M3.5 7.5 12 12l8.5-4.5"/><path d="M12 12v9"/><path d="m7.75 5.25 8.5 4.5"/>',
			'addresses'  => '<path d="M19 10.6c0 5.2-7 10.9-7 10.9s-7-5.7-7-10.9a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10.4" r="2.6"/>',
			'profile'    => '<circle cx="12" cy="8" r="4"/><path d="M4.75 20.25a7.25 7.25 0 0 1 14.5 0"/>',
			'logout'     => '<path d="M9.5 21H5.75A2.75 2.75 0 0 1 3 18.25V5.75A2.75 2.75 0 0 1 5.75 3H9.5"/><path d="m16 16.5 4.5-4.5L16 7.5"/><path d="M20.5 12H9.25"/>',
			'chevron'    => '<path d="m9.75 5.75 6.25 6.25-6.25 6.25"/>',
			'arrow'      => '<path d="M4.75 12h14.5"/><path d="m13.25 6 6 6-6 6"/>',
			'arrow-out'  => '<path d="M7.5 16.5 16.5 7.5"/><path d="M9.25 7.5h7.25v7.25"/>',
			'menu'       => '<path d="M4 7.25h16"/><path d="M4 12h16"/><path d="M4 16.75h16"/>',
			'close'      => '<path d="m6.75 6.75 10.5 10.5"/><path d="m17.25 6.75-10.5 10.5"/>',
			'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2.75"/><path d="m3.9 7.2 6.98 4.98a2 2 0 0 0 2.24 0L20.1 7.2"/>',
			'calendar'   => '<rect x="3" y="5" width="18" height="16" rx="2.75"/><path d="M3 10.25h18"/><path d="M8 3v4"/><path d="M16 3v4"/>',
			'shield'     => '<path d="M12 3 5 5.75v5.4c0 4.2 2.85 8.15 7 9.35 4.15-1.2 7-5.15 7-9.35v-5.4L12 3Z"/><path d="m9.1 11.9 2.15 2.15L15 10.3"/>',
			'inbox'      => '<path d="M3.5 13.75h4.25l1.4 2.75h5.7l1.4-2.75h4.25"/><path d="M5.65 5.95 3.5 13.75v3.5a2.25 2.25 0 0 0 2.25 2.25h12.5a2.25 2.25 0 0 0 2.25-2.25v-3.5L18.35 5.95A2 2 0 0 0 16.46 4.5H7.54a2 2 0 0 0-1.89 1.45Z"/>',
			'lock'       => '<rect x="4.5" y="10" width="15" height="10.5" rx="2.75"/><path d="M8.25 10V7.5a3.75 3.75 0 0 1 7.5 0V10"/>',
			'sparkle'    => '<path d="m12 3.25 1.85 4.9 4.9 1.85-4.9 1.85L12 16.75l-1.85-4.9-4.9-1.85 4.9-1.85L12 3.25Z"/><path d="m18.5 15.75.72 1.78 1.78.72-1.78.72-.72 1.78-.72-1.78-1.78-.72 1.78-.72.72-1.78Z"/>',
			'check'      => '<path d="m5.75 12.5 4.25 4.25L18.25 8.25"/>',
			'compass'    => '<circle cx="12" cy="12" r="9"/><path d="m15.25 8.75-1.65 4.85-4.85 1.65 1.65-4.85 4.85-1.65Z"/>',
			'arrow-left' => '<path d="M19.25 12H4.75"/><path d="m10.75 6-6 6 6 6"/>',
			'truck'      => '<path d="M3 6.75h10.5v9.5H3z"/><path d="M13.5 10h3.6l2.9 3.1v3.15h-6.5"/><circle cx="7" cy="17.75" r="1.75"/><circle cx="16.5" cy="17.75" r="1.75"/>',
			'card'       => '<rect x="2.75" y="5.25" width="18.5" height="13.5" rx="2.5"/><path d="M2.75 9.75h18.5"/><path d="M6.5 14.5h3.25"/>',
			'download'   => '<path d="M12 3.75v10.5"/><path d="m7.75 10 4.25 4.25L16.25 10"/><path d="M4.75 19.25h14.5"/>',
			'receipt'    => '<path d="M6 3.5h12v17l-2.5-1.6-2.5 1.6-2.5-1.6-2.5 1.6-2-1.28V3.5Z"/><path d="M9.25 8.25h5.5"/><path d="M9.25 12h5.5"/>',
			'alert'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7.75v5"/><path d="M12 16.1h.01"/>',
			'refresh'    => '<path d="M20 11.5a8 8 0 1 0-.7 4.6"/><path d="M20.25 4.75v5h-5"/>',
			'clock'      => '<circle cx="12" cy="12" r="8.75"/><path d="M12 7v5.25l3.25 1.9"/>',
			'sun'        => '<circle cx="12" cy="12" r="4.25"/><path d="M12 2.75v2.1"/><path d="M12 19.15v2.1"/><path d="m4.95 4.95 1.5 1.5"/><path d="m17.55 17.55 1.5 1.5"/><path d="M2.75 12h2.1"/><path d="M19.15 12h2.1"/><path d="m4.95 19.05 1.5-1.5"/><path d="m17.55 6.45 1.5-1.5"/>',
			'moon'       => '<path d="M20.5 14.6A8.6 8.6 0 0 1 9.4 3.5a8.75 8.75 0 1 0 11.1 11.1Z"/>',
			'trend'      => '<path d="M3.75 16.5 9 11.25l3.5 3.5 7.25-7.25"/><path d="M15.5 7.5h4.25v4.25"/>',
			'wallet'     => '<path d="M3.75 7.75A2 2 0 0 1 5.75 5.75h11.5a2 2 0 0 1 2 2v.75"/><rect x="3.75" y="7.75" width="16.5" height="11.5" rx="2.5"/><path d="M16.25 13.5h1.5"/>',
			'pause'      => '<rect x="6.5" y="4.5" width="3.5" height="15" rx="1.2"/><rect x="14" y="4.5" width="3.5" height="15" rx="1.2"/>',
			'undo'       => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
			'minus'      => '<path d="M6 12h12"/>',
			'monitor'    => '<rect x="3" y="4" width="18" height="12.5" rx="2.5"/><path d="M8.5 20.5h7"/><path d="M12 16.5v4"/>',
			'palette'    => '<path d="M12 3a9 9 0 1 0 0 18c1.4 0 2-.9 2-1.8 0-.5-.2-.9-.5-1.3-.3-.4-.5-.8-.5-1.3 0-.9.7-1.6 1.6-1.6H16a5 5 0 0 0 5-5c0-4.1-4-7-9-7Z"/><circle cx="7.5" cy="11.5" r="1.1"/><circle cx="10.5" cy="7.5" r="1.1"/><circle cx="15" cy="7.5" r="1.1"/>',
			'layers'     => '<path d="m12 3 9 4.5-9 4.5-9-4.5L12 3Z"/><path d="m3 12 9 4.5 9-4.5"/><path d="m3 16.5 9 4.5 9-4.5"/>',
			'star'       => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1.1 5.9L12 16.9l-5.3 2.8 1.1-5.9-4.3-4.1 5.9-.8L12 3.5Z"/>',
			'bag'        => '<path d="M5.5 8.5h13l-1 11.5a1.5 1.5 0 0 1-1.5 1.5H8a1.5 1.5 0 0 1-1.5-1.5l-1-11.5Z"/><path d="M9 8.5V7a3 3 0 0 1 6 0v1.5"/>',
			'key'        => '<circle cx="8" cy="14.5" r="4"/><path d="m11 11.5 8.5-8.5"/><path d="m16.5 6 2.5 2.5"/><path d="m14 8.5 2.5 2.5"/>',
			'globe'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.6 2.8 3.9 5.8 3.9 9s-1.3 6.2-3.9 9c-2.6-2.8-3.9-5.8-3.9-9S9.4 5.8 12 3Z"/>',
		);
	}

	/**
	 * Return sanitised inline SVG markup for a named icon.
	 *
	 * The returned string is already passed through `wp_kses()`, so templates
	 * may echo it directly.
	 *
	 * @param string $name Icon name.
	 * @param array  $args Optional. `class` and `size` overrides.
	 * @return string
	 */
	public static function icon( $name, $args = array() ) {
		$name  = sanitize_key( $name );
		$paths = self::icon_paths();

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'class' => '',
				'size'  => 24,
			)
		);

		$classes  = trim( 'wcp-icon wcp-icon--' . $name . ' ' . (string) $args['class'] );
		$size     = (int) $args['size'];
		$cache_id = $name . '|' . $classes . '|' . $size;

		if ( isset( self::$icon_cache[ $cache_id ] ) ) {
			return self::$icon_cache[ $cache_id ];
		}

		$svg = sprintf(
			'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $classes ),
			$size,
			$paths[ $name ]
		);

		self::$icon_cache[ $cache_id ] = wp_kses( $svg, self::svg_allowed_html() );

		return self::$icon_cache[ $cache_id ];
	}

	/**
	 * Allowed-HTML map for inline SVG output.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function svg_allowed_html() {
		return array(
			'svg'            => array(
				'class'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'focusable'       => true,
				'role'            => true,
			),
			'path'           => array(
				'd'            => true,
				'fill'         => true,
				'stroke'       => true,
				'opacity'      => true,
				'stroke-width' => true,
			),
			'rect'           => array(
				'x'       => true,
				'y'       => true,
				'width'   => true,
				'height'  => true,
				'rx'      => true,
				'ry'      => true,
				'fill'    => true,
				'opacity' => true,
			),
			'circle'         => array(
				'cx'      => true,
				'cy'      => true,
				'r'       => true,
				'fill'    => true,
				'opacity' => true,
			),
			'ellipse'        => array(
				'cx'      => true,
				'cy'      => true,
				'rx'      => true,
				'ry'      => true,
				'fill'    => true,
				'opacity' => true,
			),
			'line'           => array(
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			),
			'g'              => array(
				'fill'      => true,
				'stroke'    => true,
				'opacity'   => true,
				'transform' => true,
			),
			'defs'           => array(),
			'lineargradient' => array(
				'id'            => true,
				'x1'            => true,
				'y1'            => true,
				'x2'            => true,
				'y2'            => true,
				'gradientunits' => true,
			),
			'stop'           => array(
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			),
		);
	}

	/*
	|----------------------------------------------------------------------
	| Formatting
	|----------------------------------------------------------------------
	*/

	/**
	 * Derive up to two uppercase initials from a display name.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	public static function initials( $name ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );

		if ( '' === $name ) {
			return '?';
		}

		$parts    = preg_split( '/\s+/u', $name );
		$initials = '';

		foreach ( $parts as $part ) {
			$first = function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );

			if ( '' === $first || ! preg_match( '/\p{L}|\p{N}/u', $first ) ) {
				continue;
			}

			$initials .= $first;

			if ( self::string_length( $initials ) >= 2 ) {
				break;
			}
		}

		if ( '' === $initials ) {
			return '?';
		}

		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials, 'UTF-8' ) : strtoupper( $initials );
	}

	/**
	 * Multibyte-safe string length.
	 *
	 * @param string $text Input.
	 * @return int
	 */
	private static function string_length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Join a conditional class map into an attribute-ready string.
	 *
	 * @param array $classes Map of class name => bool, or a plain list.
	 * @return string
	 */
	public static function class_names( array $classes ) {
		$out = array();

		foreach ( $classes as $key => $value ) {
			if ( is_int( $key ) ) {
				if ( is_string( $value ) && '' !== $value ) {
					$out[] = $value;
				}
				continue;
			}

			if ( $value ) {
				$out[] = $key;
			}
		}

		return implode( ' ', array_unique( $out ) );
	}
}
