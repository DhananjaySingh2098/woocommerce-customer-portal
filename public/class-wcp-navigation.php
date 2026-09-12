<?php
/**
 * Portal navigation model.
 *
 * Owns the section list, the current-section resolution and section URLs.
 * Templates read from here rather than hard-coding links, so adding a section
 * in a later phase is a one-entry change.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Navigation items and routing state.
 */
class WCP_Navigation {

	/**
	 * Query argument carrying the active section.
	 */
	const QUERY_VAR = 'wcp_section';

	/**
	 * Section rendered when none is requested.
	 */
	const DEFAULT_SECTION = 'dashboard';

	/**
	 * Query argument carrying the order being viewed.
	 */
	const ORDER_VAR = 'wcp_order';

	/**
	 * Query argument carrying the orders-list page number.
	 */
	const PAGE_VAR = 'wcp_page';

	/**
	 * Query argument carrying the address being edited.
	 */
	const ADDRESS_VAR = 'wcp_address';

	/**
	 * Memoised item list.
	 *
	 * @var array|null
	 */
	private $items = null;

	/**
	 * The enabled-section set the memoised list was built for.
	 *
	 * @var string|null
	 */
	private $items_key = null;

	/**
	 * The portal's section definitions.
	 *
	 * `benefit_title` / `benefit_text` describe the section as a customer
	 * benefit, used by the logged-out screen. `status` is either `ready`
	 * (rendered by this plugin) or `upcoming`
	 * (shell exists, native experience ships in a later phase). Upcoming
	 * sections link out to the equivalent WooCommerce My Account endpoint so
	 * the portal stays genuinely useful in the meantime.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_items() {
		// Memoised per enabled-section set rather than per instance, so a
		// settings change within the same request (or the same test process)
		// is never served a stale list.
		$key = class_exists( 'WCP_Settings' ) ? implode( ',', WCP_Settings::enabled_sections() ) : '*';

		if ( null !== $this->items && $key === $this->items_key ) {
			return $this->items;
		}

		$this->items_key = $key;

		$items = self::definitions();

		/**
		 * Filter the portal's navigation items.
		 *
		 * @param array $items Section definitions.
		 */
		$items = (array) apply_filters( 'wcp_navigation_items', $items );

		// Sections an administrator has switched off are removed here, which is
		// what makes them disappear from the sidebar. It is not what makes them
		// inaccessible -- `get_slugs()` reads from this list, and every route
		// whitelists against it, so a disabled section cannot be reached by
		// typing its URL either.
		//
		// A section added by the filter above is not in the settings screen's
		// known list, so it is left alone rather than being switched off by a
		// setting that could never have been used to enable it.
		if ( class_exists( 'WCP_Settings' ) ) {
			$known = self::all_slugs();

			$items = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $known ) {
						$slug = isset( $item['slug'] ) ? (string) $item['slug'] : '';

						if ( ! in_array( $slug, $known, true ) ) {
							return true;
						}

						return WCP_Settings::section_enabled( $slug );
					}
				)
			);
		}

		$this->items = $items;

		return $this->items;
	}

	/**
	 * Every section slug the plugin ships, regardless of configuration.
	 *
	 * Deliberately static and settings-unaware: `WCP_Settings` calls this to
	 * build its own allowlist, so consulting settings here would be circular.
	 *
	 * @return string[]
	 */
	public static function all_slugs() {
		return wp_list_pluck( self::definitions(), 'slug' );
	}

	/**
	 * The shipped section definitions.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function definitions() {
		return array(
			array(
				'slug'          => 'dashboard',
				'label'         => __( 'Dashboard', 'woocommerce-customer-portal' ),
				'title'         => __( 'Dashboard', 'woocommerce-customer-portal' ),
				'subtitle'      => __( 'An overview of your account.', 'woocommerce-customer-portal' ),
				'icon'          => 'dashboard',
				'status'        => 'ready',
				'endpoint'      => '',
				'description'   => __( 'Your account at a glance.', 'woocommerce-customer-portal' ),
				'benefit_title' => '',
				'benefit_text'  => '',
			),
			array(
				'slug'          => 'orders',
				'label'         => __( 'Orders', 'woocommerce-customer-portal' ),
				'title'         => __( 'Orders', 'woocommerce-customer-portal' ),
				'subtitle'      => __( 'Track purchases and review order history.', 'woocommerce-customer-portal' ),
				'icon'          => 'orders',
				'status'        => 'ready',
				'endpoint'      => 'orders',
				'description'   => __( 'Review past purchases and open any order for full details.', 'woocommerce-customer-portal' ),
				'benefit_title' => __( 'Track every order', 'woocommerce-customer-portal' ),
				'benefit_text'  => __( 'Follow each purchase from checkout to your doorstep.', 'woocommerce-customer-portal' ),
			),
			array(
				'slug'          => 'addresses',
				'label'         => __( 'Addresses', 'woocommerce-customer-portal' ),
				'title'         => __( 'Addresses', 'woocommerce-customer-portal' ),
				'subtitle'      => __( 'Manage billing and shipping details.', 'woocommerce-customer-portal' ),
				'icon'          => 'addresses',
				'status'        => 'ready',
				'endpoint'      => 'edit-address',
				'description'   => __( 'Keep billing and shipping addresses current for faster checkout.', 'woocommerce-customer-portal' ),
				'benefit_title' => __( 'Manage billing & shipping', 'woocommerce-customer-portal' ),
				'benefit_text'  => __( 'Save your addresses once and check out faster every time.', 'woocommerce-customer-portal' ),
			),
			array(
				'slug'          => 'profile',
				'label'         => __( 'Profile', 'woocommerce-customer-portal' ),
				'title'         => __( 'Profile', 'woocommerce-customer-portal' ),
				'subtitle'      => __( 'Update your personal and sign-in details.', 'woocommerce-customer-portal' ),
				'icon'          => 'profile',
				'status'        => 'ready',
				'endpoint'      => 'edit-account',
				'description'   => __( 'Change your name, email address and account password.', 'woocommerce-customer-portal' ),
				'benefit_title' => __( 'Keep your profile secure', 'woocommerce-customer-portal' ),
				'benefit_text'  => __( 'Update your details and password whenever you need to.', 'woocommerce-customer-portal' ),
			),
		);
	}

	/**
	 * All known section slugs.
	 *
	 * @return string[]
	 */
	public function get_slugs() {
		return wp_list_pluck( $this->get_items(), 'slug' );
	}

	/**
	 * Look up a single section definition.
	 *
	 * @param string $slug Section slug.
	 * @return array|null
	 */
	public function get_item( $slug ) {
		foreach ( $this->get_items() as $item ) {
			if ( $item['slug'] === $slug ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Resolve the active section from the request.
	 *
	 * The raw query value is whitelisted against known slugs, so an arbitrary
	 * value can never reach template resolution.
	 *
	 * @param string $requested Optional. Pre-read value; falls back to the query string.
	 * @return string
	 */
	public function get_current_section( $requested = null ) {
		$slugs   = $this->get_slugs();
		$default = $this->get_default_section();

		if ( null === $requested ) {
			$requested = WCP_Security::get_query_arg( self::QUERY_VAR, $default );
		}

		// `$slugs` contains only enabled sections, so a request for a disabled
		// one collapses to the default exactly as an invented slug would. The
		// URL is not a way in.
		return WCP_Security::sanitize_section( $requested, $slugs, $default );
	}

	/**
	 * The section a customer lands on when none is requested.
	 *
	 * Always one that is actually enabled, so a stale setting cannot send the
	 * portal somewhere it will immediately bounce away from.
	 *
	 * @return string
	 */
	public function get_default_section() {
		if ( class_exists( 'WCP_Settings' ) ) {
			$configured = WCP_Settings::default_section();

			if ( '' !== $configured && in_array( $configured, $this->get_slugs(), true ) ) {
				return $configured;
			}
		}

		$slugs = $this->get_slugs();

		if ( in_array( self::DEFAULT_SECTION, $slugs, true ) ) {
			return self::DEFAULT_SECTION;
		}

		return $slugs ? $slugs[0] : self::DEFAULT_SECTION;
	}

	/**
	 * Build the URL for a section on the page hosting the portal.
	 *
	 * @param string $slug     Section slug.
	 * @param string $base_url Page URL hosting the shortcode.
	 * @return string
	 */
	public function get_section_url( $slug, $base_url ) {
		$slug = sanitize_key( $slug );

		// The landing section owns the bare URL. When Dashboard is disabled
		// that is some other section, and linking it to `?wcp_section=` would
		// be a link to nowhere.
		if ( $this->get_default_section() === $slug ) {
			return $base_url;
		}

		return add_query_arg( self::QUERY_VAR, $slug, $base_url );
	}

	/**
	 * URL for a single order's detail view.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $base_url Page URL hosting the shortcode.
	 * @return string
	 */
	public function get_order_url( $order_id, $base_url ) {
		// `absint()` would turn -1 into 1 -- a real order ID -- so the sign is
		// checked before the cast.
		$order_id = ( is_numeric( $order_id ) && (float) $order_id > 0 ) ? absint( $order_id ) : 0;

		if ( $order_id <= 0 ) {
			return $this->get_section_url( 'orders', $base_url );
		}

		return add_query_arg(
			array(
				self::QUERY_VAR => 'orders',
				self::ORDER_VAR => $order_id,
			),
			$base_url
		);
	}

	/**
	 * URL for a page of the orders list.
	 *
	 * @param int    $page     Page number.
	 * @param string $base_url Page URL hosting the shortcode.
	 * @return string
	 */
	public function get_orders_page_url( $page, $base_url ) {
		$page = max( 1, absint( $page ) );
		$url  = $this->get_section_url( 'orders', $base_url );

		if ( 1 === $page ) {
			return $url;
		}

		return add_query_arg( self::PAGE_VAR, $page, $url );
	}

	/**
	 * URL for editing one address.
	 *
	 * @param string $type     `billing` or `shipping`.
	 * @param string $base_url Page URL hosting the shortcode.
	 * @return string
	 */
	public function get_address_url( $type, $base_url ) {
		$type = WCP_Addresses::sanitize_type( $type );

		if ( '' === $type ) {
			return $this->get_section_url( 'addresses', $base_url );
		}

		return add_query_arg(
			array(
				self::QUERY_VAR   => 'addresses',
				self::ADDRESS_VAR => $type,
			),
			$base_url
		);
	}

	/**
	 * The WooCommerce My Account URL backing an upcoming section.
	 *
	 * @param array $item Section definition.
	 * @return string Empty string when the section has no endpoint.
	 */
	public function get_endpoint_url( array $item ) {
		if ( empty( $item['endpoint'] ) ) {
			return '';
		}

		return WCP_Auth::get_account_endpoint_url( $item['endpoint'] );
	}
}
