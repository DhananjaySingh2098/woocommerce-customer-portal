<?php
/**
 * Plugin settings: storage, defaults, validation.
 *
 * One option row holds every setting, and this class is the only thing that
 * reads or writes it. That matters for more than tidiness: the sanitiser here
 * is the *only* path into the option, so a setting can never hold a value the
 * rest of the plugin is not prepared for. Anything unrecognised collapses to a
 * safe default rather than being stored and trusted later.
 *
 * The front end reads settings through the same accessors the admin screen
 * writes them with, so an enabled/disabled section means the same thing to the
 * navigation, to the URL router and to the REST routes -- there is no second
 * interpretation to drift out of sync.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and validates the plugin's settings.
 */
class WCP_Settings {

	/**
	 * Option name holding every setting.
	 */
	const OPTION = 'wcp_settings';

	/**
	 * Settings group for the WordPress Settings API.
	 */
	const GROUP = 'wcp_settings_group';

	/**
	 * Capability required to read or write settings.
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Appearance choices an administrator may set as the default.
	 */
	const THEMES = array( 'system', 'light', 'dark' );

	/**
	 * Visual theme presets. Each is a token set in the stylesheet; the slug is
	 * placed on the document as `data-wcp-visual` and nowhere else.
	 */
	const VISUAL_THEMES = array( 'aurora', 'obsidian', 'pearl', 'midnight', 'emerald' );

	/**
	 * Memoised settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/*
	|--------------------------------------------------------------------------
	| Defaults
	|--------------------------------------------------------------------------
	*/

	/**
	 * Safe defaults.
	 *
	 * These are what a fresh install behaves like, and what any invalid stored
	 * value falls back to. Everything is on: a plugin that silently disables
	 * parts of itself is harder to diagnose than one that starts complete.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'         => true,
			'portal_page'     => 0,
			'sections'        => self::all_section_slugs(),
			'default_section' => 'dashboard',
			'accent'          => '#5b4ce0',
			'theme'           => 'system',
			'visual_theme'    => 'aurora',
			'motion'          => true,
			'effects_3d'      => true,
		);
	}

	/**
	 * Every section slug the portal knows about.
	 *
	 * Read from the navigation model rather than duplicated, so adding a
	 * section there makes it configurable here automatically.
	 *
	 * @return string[]
	 */
	public static function all_section_slugs() {
		if ( ! class_exists( 'WCP_Navigation' ) ) {
			return array( 'dashboard', 'orders', 'addresses', 'profile' );
		}

		return WCP_Navigation::all_slugs();
	}

	/*
	|--------------------------------------------------------------------------
	| Reading
	|--------------------------------------------------------------------------
	*/

	/**
	 * All settings, merged over defaults and validated.
	 *
	 * Validation runs on read as well as on write. A row edited directly in the
	 * database, or left behind by an older version, cannot put an unexpected
	 * value into circulation.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		/*
		 * Merged over defaults *before* sanitising. The sanitiser treats an
		 * absent `enabled` key as an unchecked box -- correct for a form
		 * submission, where every other field is present, but on a fresh
		 * install the whole row is absent and that reading would switch the
		 * portal off for everyone. A key the admin has actually saved (including
		 * `enabled => false`) still wins over the default.
		 */
		self::$cache = self::sanitize( array_merge( self::defaults(), $stored ) );

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Optional fallback; defaults to the declared default.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		if ( null !== $fallback ) {
			return $fallback;
		}

		$defaults = self::defaults();

		return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : null;
	}

	/**
	 * One setting exactly as stored, before validation.
	 *
	 * Almost everything should use `get()`, which repairs bad values. This
	 * exists for diagnostics that need to know a value *was* bad -- the admin
	 * notice for a portal page that has since been deleted cannot fire if the
	 * only view of the setting has already replaced the dead ID with zero.
	 *
	 * @param string $key Setting key.
	 * @return mixed Null when absent.
	 */
	public static function raw( $key ) {
		$stored = get_option( self::OPTION, array() );

		return ( is_array( $stored ) && array_key_exists( $key, $stored ) ) ? $stored[ $key ] : null;
	}

	/**
	 * Drop the memoised copy.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/*
	|--------------------------------------------------------------------------
	| Derived state
	|--------------------------------------------------------------------------
	*/

	/**
	 * Whether the portal is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) self::get( 'enabled' );
	}

	/**
	 * Enabled section slugs, in the navigation's own order.
	 *
	 * @return string[]
	 */
	public static function enabled_sections() {
		$enabled = (array) self::get( 'sections' );
		$order   = self::all_section_slugs();

		return array_values( array_intersect( $order, $enabled ) );
	}

	/**
	 * Whether one section is enabled.
	 *
	 * The single question the navigation, the URL router and the REST routes
	 * all ask, so "disabled" cannot mean three different things.
	 *
	 * @param string $slug Section slug.
	 * @return bool
	 */
	public static function section_enabled( $slug ) {
		return in_array( sanitize_key( (string) $slug ), self::enabled_sections(), true );
	}

	/**
	 * The section a customer lands on.
	 *
	 * Falls back through the configured choice, then Dashboard, then whatever
	 * is enabled first. It cannot return a disabled section, which is what
	 * stops a stale setting producing a redirect loop.
	 *
	 * @return string Empty string only when every section is disabled.
	 */
	public static function default_section() {
		$enabled = self::enabled_sections();

		if ( empty( $enabled ) ) {
			return '';
		}

		$configured = (string) self::get( 'default_section' );

		if ( in_array( $configured, $enabled, true ) ) {
			return $configured;
		}

		if ( in_array( 'dashboard', $enabled, true ) ) {
			return 'dashboard';
		}

		return $enabled[0];
	}

	/**
	 * The configured portal page, if it is usable.
	 *
	 * @return WP_Post|null
	 */
	public static function portal_page() {
		$id = (int) self::get( 'portal_page' );

		if ( $id <= 0 ) {
			return null;
		}

		$page = get_post( $id );

		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return null;
		}

		return $page;
	}

	/*
	|--------------------------------------------------------------------------
	| Sanitisation
	|--------------------------------------------------------------------------
	*/

	/**
	 * Validate a full settings array.
	 *
	 * Registered as the Settings API sanitise callback and also used on read.
	 * Every key is handled explicitly; anything not listed is discarded rather
	 * than stored, so a crafted POST cannot smuggle extra keys into the option.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$clean = array();

		$clean['enabled'] = ! empty( $input['enabled'] );

		$clean['portal_page'] = isset( $input['portal_page'] ) ? absint( $input['portal_page'] ) : $defaults['portal_page'];

		if ( $clean['portal_page'] > 0 ) {
			$page = get_post( $clean['portal_page'] );

			// A page that does not exist, is not a page, or is not published
			// would produce a setting the front end cannot honour.
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
				$clean['portal_page'] = 0;
			}
		}

		$clean['sections'] = self::sanitize_sections( isset( $input['sections'] ) ? $input['sections'] : null );

		$clean['default_section'] = self::sanitize_default_section(
			isset( $input['default_section'] ) ? $input['default_section'] : '',
			$clean['sections']
		);

		$clean['accent'] = self::sanitize_accent( isset( $input['accent'] ) ? $input['accent'] : '' );

		$theme          = ( isset( $input['theme'] ) && is_scalar( $input['theme'] ) ) ? sanitize_key( (string) $input['theme'] ) : '';
		$clean['theme'] = in_array( $theme, self::THEMES, true ) ? $theme : $defaults['theme'];

		$visual                = ( isset( $input['visual_theme'] ) && is_scalar( $input['visual_theme'] ) ) ? sanitize_key( (string) $input['visual_theme'] ) : '';
		$clean['visual_theme'] = in_array( $visual, self::VISUAL_THEMES, true ) ? $visual : $defaults['visual_theme'];

		// Checkboxes: absent means unchecked. The merge-before-sanitise on read
		// (see `all()`) is what keeps a never-saved install at the default.
		$clean['motion']     = ! empty( $input['motion'] );
		$clean['effects_3d'] = ! empty( $input['effects_3d'] );

		return $clean;
	}

	/**
	 * Validate the enabled-section list.
	 *
	 * Only known slugs survive. An empty selection is rejected rather than
	 * stored: a portal with no sections is a broken portal, and silently
	 * accepting it would leave an administrator staring at a blank screen with
	 * no way back except the database.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	private static function sanitize_sections( $value ) {
		$known = self::all_section_slugs();

		if ( ! is_array( $value ) ) {
			return $known;
		}

		$clean = array();

		foreach ( $value as $slug ) {
			if ( ! is_scalar( $slug ) ) {
				continue;
			}

			$slug = sanitize_key( (string) $slug );

			if ( in_array( $slug, $known, true ) && ! in_array( $slug, $clean, true ) ) {
				$clean[] = $slug;
			}
		}

		if ( empty( $clean ) ) {
			return $known;
		}

		return array_values( array_intersect( $known, $clean ) );
	}

	/**
	 * Validate the landing section against what is actually enabled.
	 *
	 * @param mixed    $value    Raw value.
	 * @param string[] $sections Enabled sections.
	 * @return string
	 */
	private static function sanitize_default_section( $value, array $sections ) {
		$slug = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		if ( in_array( $slug, $sections, true ) ) {
			return $slug;
		}

		if ( in_array( 'dashboard', $sections, true ) ) {
			return 'dashboard';
		}

		return $sections ? $sections[0] : 'dashboard';
	}

	/**
	 * Validate an accent colour.
	 *
	 * `sanitize_hex_color()` returns null for anything that is not a literal
	 * `#rgb` or `#rrggbb`, which is the whole defence: the value reaches CSS as
	 * a custom property, and a string like `red; } body { display:none` must
	 * never survive to get there.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_accent( $value ) {
		$defaults = self::defaults();

		if ( ! is_scalar( $value ) ) {
			return $defaults['accent'];
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return $defaults['accent'];
		}

		if ( '#' !== substr( $value, 0, 1 ) ) {
			$value = '#' . $value;
		}

		$hex = sanitize_hex_color( $value );

		return $hex ? $hex : $defaults['accent'];
	}

	/*
	|--------------------------------------------------------------------------
	| Accent derivation
	|--------------------------------------------------------------------------
	*/

	/**
	 * The full accent palette derived from the configured colour.
	 *
	 * The design system needs more than one value -- a hover, an active, a soft
	 * background, an rgb triplet and a readable text colour to sit on top. All
	 * are computed from the single sanitised hex, so an administrator picks one
	 * colour and the component layer stays coherent.
	 *
	 * @param bool $dark Whether to derive the dark-surface variant.
	 * @return array<string,string>
	 */
	public static function accent_palette( $dark = false ) {
		$hex = self::sanitize_accent( self::get( 'accent' ) );
		$rgb = self::hex_to_rgb( $hex );

		if ( $dark ) {
			// Lift towards white so the colour still reads on a dark surface,
			// then derive the rest from the lifted colour. Soft backgrounds
			// blend towards the dark canvas rather than towards white.
			$rgb = self::hex_to_rgb( self::mix( $rgb, array( 255, 255, 255 ), 0.28 ) );
			$hex = self::rgb_to_hex( $rgb );

			return array(
				'--wcp-primary'            => $hex,
				'--wcp-primary-hover'      => self::shift( $rgb, 0.1 ),
				'--wcp-primary-active'     => self::shift( $rgb, -0.08 ),
				'--wcp-primary-soft'       => self::mix( $rgb, array( 22, 27, 36 ), 0.82 ),
				'--wcp-primary-soft-hover' => self::mix( $rgb, array( 22, 27, 36 ), 0.74 ),
				'--wcp-primary-contrast'   => self::readable_on( $rgb ),
				'--wcp-primary-rgb'        => implode( ', ', $rgb ),
			);
		}

		return array(
			'--wcp-primary'            => $hex,
			'--wcp-primary-hover'      => self::shift( $rgb, -0.09 ),
			'--wcp-primary-active'     => self::shift( $rgb, -0.18 ),
			'--wcp-primary-soft'       => self::mix( $rgb, array( 255, 255, 255 ), 0.92 ),
			'--wcp-primary-soft-hover' => self::mix( $rgb, array( 255, 255, 255 ), 0.86 ),
			'--wcp-primary-contrast'   => self::readable_on( $rgb ),
			'--wcp-primary-rgb'        => implode( ', ', $rgb ),
		);
	}

	/**
	 * The administrator's default visual theme, validated.
	 *
	 * @return string One of `VISUAL_THEMES`.
	 */
	public static function visual_theme() {
		$value    = (string) self::get( 'visual_theme' );
		$defaults = self::defaults();

		return in_array( $value, self::VISUAL_THEMES, true ) ? $value : $defaults['visual_theme'];
	}

	/**
	 * Whether the accent differs from the shipped default.
	 *
	 * @return bool
	 */
	public static function accent_is_custom() {
		$defaults = self::defaults();

		return strtolower( self::sanitize_accent( self::get( 'accent' ) ) ) !== strtolower( $defaults['accent'] );
	}

	/**
	 * Convert a sanitised hex colour to an rgb triplet.
	 *
	 * @param string $hex Hex colour, `#rgb` or `#rrggbb`.
	 * @return int[]
	 */
	public static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) ) {
			return array( 91, 76, 224 );
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Lighten or darken an rgb triplet.
	 *
	 * @param int[] $rgb    Triplet.
	 * @param float $amount Negative darkens, positive lightens.
	 * @return string Hex colour.
	 */
	private static function shift( array $rgb, $amount ) {
		$out = array();

		foreach ( $rgb as $channel ) {
			$target = $amount < 0 ? 0 : 255;
			$out[]  = (int) round( $channel + ( ( $target - $channel ) * abs( $amount ) ) );
		}

		return self::rgb_to_hex( $out );
	}

	/**
	 * Blend an rgb triplet towards another colour.
	 *
	 * @param int[] $rgb    Triplet.
	 * @param int[] $towards Target triplet.
	 * @param float $weight Proportion of the target, 0..1.
	 * @return string Hex colour.
	 */
	private static function mix( array $rgb, array $towards, $weight ) {
		$out = array();

		foreach ( $rgb as $i => $channel ) {
			$out[] = (int) round( ( $channel * ( 1 - $weight ) ) + ( $towards[ $i ] * $weight ) );
		}

		return self::rgb_to_hex( $out );
	}

	/**
	 * Pick black or white text for a background, whichever is readable.
	 *
	 * A store that picks a pale accent would otherwise get white button labels
	 * on a pale button. Comparing WCAG contrast against both candidates and
	 * taking the better one keeps the choice legible whatever is configured.
	 *
	 * @param int[] $rgb Background triplet.
	 * @return string Hex colour.
	 */
	public static function readable_on( array $rgb ) {
		$light = self::contrast( $rgb, array( 255, 255, 255 ) );
		$dark  = self::contrast( $rgb, array( 16, 17, 28 ) );

		return $light >= $dark ? '#ffffff' : '#10111c';
	}

	/**
	 * WCAG contrast ratio between two rgb triplets.
	 *
	 * @param int[] $a First colour.
	 * @param int[] $b Second colour.
	 * @return float
	 */
	public static function contrast( array $a, array $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );

		$hi = max( $la, $lb );
		$lo = min( $la, $lb );

		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	/**
	 * Relative luminance of an rgb triplet.
	 *
	 * @param int[] $rgb Triplet.
	 * @return float
	 */
	private static function luminance( array $rgb ) {
		$parts = array();

		foreach ( $rgb as $channel ) {
			$c       = $channel / 255;
			$parts[] = ( $c <= 0.03928 ) ? ( $c / 12.92 ) : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $parts[0] ) + ( 0.7152 * $parts[1] ) + ( 0.0722 * $parts[2] );
	}

	/**
	 * Convert an rgb triplet to a hex colour.
	 *
	 * @param int[] $rgb Triplet.
	 * @return string
	 */
	private static function rgb_to_hex( array $rgb ) {
		$out = '#';

		foreach ( $rgb as $channel ) {
			$channel = max( 0, min( 255, (int) $channel ) );
			$out    .= str_pad( dechex( $channel ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}
}
