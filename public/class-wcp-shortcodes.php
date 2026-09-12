<?php
/**
 * Shortcode registration and portal rendering.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the portal via [wcp_customer_portal].
 */
class WCP_Shortcodes {

	/**
	 * Shortcode tag.
	 */
	const TAG = WCP_SHORTCODE_TAG;

	/**
	 * Asset controller.
	 *
	 * @var WCP_Public
	 */
	private $assets;

	/**
	 * Navigation model.
	 *
	 * @var WCP_Navigation
	 */
	private $navigation;

	/**
	 * Incremented per render so multiple instances keep unique element IDs.
	 *
	 * @var int
	 */
	private $instance = 0;

	/**
	 * Constructor.
	 *
	 * @param WCP_Public     $assets     Asset controller.
	 * @param WCP_Navigation $navigation Navigation model.
	 */
	public function __construct( WCP_Public $assets, WCP_Navigation $navigation ) {
		$this->assets     = $assets;
		$this->navigation = $navigation;
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Resolve the layout mode for a render.
	 *
	 * `full` lets the portal bleed to the viewport edges out of a theme's
	 * content column; `contained` keeps it inside. Full is the default because
	 * the portal is an application surface, not an article — but the opt-out
	 * exists for themes and placements where bleeding is wrong.
	 *
	 * Static so that page-level framing can resolve the same layout from post
	 * content before the shortcode ever runs, and never disagree with it.
	 *
	 * @param string $requested Raw `layout` attribute.
	 * @return string `full` or `contained`.
	 */
	public static function normalize_layout( $requested ) {
		$requested = sanitize_key( (string) $requested );

		if ( ! in_array( $requested, array( 'full', 'contained' ), true ) ) {
			/**
			 * Filter the default portal layout mode.
			 *
			 * @param string $layout `full` or `contained`.
			 */
			$requested = (string) apply_filters( 'wcp_default_layout', 'full' );
			$requested = in_array( $requested, array( 'full', 'contained' ), true ) ? $requested : 'full';
		}

		return $requested;
	}

	/**
	 * Render the portal.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'section' => '',
				'layout'  => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		// Switched off by an administrator: nothing for customers, and a note
		// for someone who could switch it back on, so the page is not mistaken
		// for empty. Checked before the enqueue so a disabled portal loads no
		// assets either.
		if ( class_exists( 'WCP_Settings' ) && ! WCP_Settings::is_enabled() ) {
			return $this->render_switched_off();
		}

		// Late enqueue covers placements a content scan cannot detect.
		$this->assets->enqueue_assets();

		++$this->instance;

		$layout = self::normalize_layout( $atts['layout'] );

		if ( ! WCP_Auth::can_view_portal() ) {
			return WCP_Helper::render_template( 'login-required.php', $this->get_logged_out_context( $layout ) );
		}

		return WCP_Helper::render_template( 'portal.php', $this->get_portal_context( $atts, $layout ) );
	}

	/**
	 * Output when an administrator has disabled the portal.
	 *
	 * @return string
	 */
	private function render_switched_off() {
		if ( ! WCP_Security::current_user_can_manage() ) {
			return '';
		}

		return sprintf(
			'<div class="wcp-inline-notice" role="status" style="padding:14px 16px;border:1px solid #e6e8ee;border-left:3px solid #6b7280;border-radius:10px;background:#f8f9fb;color:#374151;font-size:14px;line-height:1.5;"><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></div>',
			esc_html__( 'The customer portal is switched off.', 'woocommerce-customer-portal' ),
			esc_html__( 'Customers see nothing here. Only store managers can see this message.', 'woocommerce-customer-portal' ),
			esc_url( WCP_Admin::settings_url() ),
			esc_html__( 'Portal settings', 'woocommerce-customer-portal' )
		);
	}

	/*
	|----------------------------------------------------------------------
	| Template context
	|----------------------------------------------------------------------
	*/

	/**
	 * Context for the logged-out state.
	 *
	 * @param string $layout Layout mode.
	 * @return array
	 */
	private function get_logged_out_context( $layout = 'full' ) {
		return array(
			'instance'     => $this->instance,
			'layout'       => $layout,
			'login_url'    => WCP_Auth::get_login_url(),
			'register_url' => WCP_Auth::get_register_url(),
			'account_url'  => WCP_Auth::get_my_account_url(),
			'home_url'     => home_url( '/' ),
			'features'     => $this->navigation->get_items(),
			'appearance'   => self::appearance_flags(),
		);
	}

	/**
	 * Context for the authenticated portal shell.
	 *
	 * @param array  $atts   Parsed shortcode attributes.
	 * @param string $layout Layout mode.
	 * @return array
	 */
	private function get_portal_context( array $atts, $layout = 'full' ) {
		$account = WCP_Account::for_current_user();

		if ( null === $account ) {
			// Defensive: can_view_portal() already guarantees a user, but a
			// filter could disagree. Fail closed rather than render a shell
			// with no identity behind it.
			return $this->get_logged_out_context( $layout );
		}

		$requested = '' !== $atts['section'] ? $atts['section'] : null;
		$current   = $this->navigation->get_current_section( $requested );
		$base_url  = WCP_Auth::current_url();
		$items     = $this->navigation->get_items();

		$prepared = array();

		foreach ( $items as $item ) {
			$item['url']          = $this->navigation->get_section_url( $item['slug'], $base_url );
			$item['endpoint_url'] = $this->navigation->get_endpoint_url( $item );
			$item['is_current']   = ( $item['slug'] === $current );
			$prepared[]           = $item;
		}

		$current_item = $this->navigation->get_item( $current );

		$context = array(
			'instance'      => $this->instance,
			'layout'        => $layout,
			'account'       => $account,
			'navigation'    => $this->navigation,
			'items'         => $prepared,
			'current'       => $current,
			'current_item'  => $current_item ? $current_item : $prepared[0],
			'base_url'      => $base_url,
			'account_url'   => WCP_Auth::get_my_account_url(),
			'logout_url'    => WCP_Auth::get_logout_url(),
			'shop_url'      => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
			'orders_url'    => $this->navigation->get_section_url( 'orders', $base_url ),
			'addresses_url' => $this->navigation->get_section_url( 'addresses', $base_url ),
			'nonce'         => WCP_Security::create_nonce(),
			'appearance'    => self::appearance_flags(),
		);

		return array_merge( $context, $this->get_section_context( $current, $context ) );
	}

	/**
	 * Administrator switches that shape motion and depth, for the root element.
	 *
	 * Rendered as data attributes so the stylesheet can turn effects off
	 * without waiting for script -- and so a page with script disabled still
	 * honours the setting.
	 *
	 * @return array{motion:bool,effects_3d:bool,visual:string}
	 */
	public static function appearance_flags() {
		$settings = class_exists( 'WCP_Settings' );

		return array(
			'motion'     => ! $settings || (bool) WCP_Settings::get( 'motion' ),
			'effects_3d' => ! $settings || (bool) WCP_Settings::get( 'effects_3d' ),
			'visual'     => $settings ? WCP_Settings::visual_theme() : 'aurora',
		);
	}

	/*
	|----------------------------------------------------------------------
	| Section routing
	|----------------------------------------------------------------------
	*/

	/**
	 * Resolve which template renders the active section, and load its data.
	 *
	 * Routing lives here rather than in the template so that a view never has
	 * to decide what it is. `section_template` is chosen from a fixed set --
	 * it is never assembled from request input.
	 *
	 * @param string $current Active section slug (already whitelisted).
	 * @param array  $context Shared portal context.
	 * @return array Extra context for the section.
	 */
	private function get_section_context( $current, array $context ) {
		if ( 'orders' === $current ) {
			return $this->get_orders_context( $context );
		}

		if ( 'addresses' === $current ) {
			return $this->get_addresses_context( $context );
		}

		if ( 'profile' === $current ) {
			return $this->get_profile_context();
		}

		if ( 'dashboard' === $current ) {
			return array(
				'section_template' => 'dashboard.php',
				'summary'          => WCP_Dashboard::get_summary(),
			);
		}

		return array( 'section_template' => 'partials/section-upcoming.php' );
	}

	/**
	 * Data for the addresses section: the overview, or one editable address.
	 *
	 * @param array $context Shared portal context.
	 * @return array
	 */
	private function get_addresses_context( array $context ) {
		$requested = WCP_Security::get_query_arg( WCP_Navigation::ADDRESS_VAR );
		$type      = WCP_Addresses::sanitize_type( $requested );
		$flash     = WCP_Form_Handler::consume_flash();

		if ( '' !== $type ) {
			// A rejected submission repopulates the form with what was typed,
			// so nothing is lost to a single missing field.
			$values = WCP_Addresses::get_form_values( $type );

			if ( ! empty( $flash['values'] ) && 'address' === $flash['form'] ) {
				$values = array_merge( $values, (array) $flash['values'] );
			}

			$country = isset( $values[ $type . '_country' ] )
				? WCP_Addresses::sanitize_country( $values[ $type . '_country' ], $type )
				: '';

			// An empty string here would build the field set with *no* country,
			// which is not the same as "use the default" -- null is.
			$country = ( '' === $country ) ? null : $country;

			return array(
				'section_template' => 'address-edit.php',
				'address_type'     => $type,
				'address_label'    => WCP_Addresses::get_label( $type ),
				'address_fields'   => WCP_Addresses::get_fields( $type, $country ),
				'address_values'   => $values,
				'addresses_url'    => $this->navigation->get_section_url( 'addresses', $context['base_url'] ),
				'flash'            => $flash,
			);
		}

		$addresses = array();

		foreach ( WCP_Addresses::get_types() as $candidate ) {
			$addresses[] = array(
				'type'      => $candidate,
				'label'     => WCP_Addresses::get_label( $candidate ),
				'formatted' => WCP_Addresses::get_formatted( $candidate ),
				'is_set'    => WCP_Addresses::has_address( $candidate ),
				'edit_url'  => $this->navigation->get_address_url( $candidate, $context['base_url'] ),
			);
		}

		return array(
			'section_template' => 'addresses.php',
			'addresses'        => $addresses,
			'flash'            => $flash,
		);
	}

	/**
	 * Data for the profile section.
	 *
	 * @return array
	 */
	private function get_profile_context() {
		$flash  = WCP_Form_Handler::consume_flash();
		$values = WCP_Profile::get_values();

		if ( ! empty( $flash['values'] ) && 'profile' === $flash['form'] ) {
			$values = array_merge( $values, (array) $flash['values'] );
		}

		return array(
			'section_template' => 'profile.php',
			'profile_fields'   => WCP_Profile::get_fields(),
			'profile_values'   => $values,
			'flash'            => $flash,
		);
	}

	/**
	 * Data for the orders section: either one order or a page of them.
	 *
	 * @param array $context Shared portal context.
	 * @return array
	 */
	private function get_orders_context( array $context ) {
		$order_id = WCP_Security::get_query_int( WCP_Navigation::ORDER_VAR );

		if ( $order_id > 0 ) {
			$order = WCP_Orders::get_order( $order_id );

			if ( is_wp_error( $order ) ) {
				return array(
					'section_template' => 'partials/order-error.php',
					'order_error'      => array(
						'code'    => $order->get_error_code(),
						'message' => $order->get_error_message(),
					),
					'orders_url'       => $this->navigation->get_section_url( 'orders', $context['base_url'] ),
				);
			}

			return array(
				'section_template' => 'order-detail.php',
				'order'            => $order,
				'orders_url'       => $this->navigation->get_section_url( 'orders', $context['base_url'] ),
			);
		}

		$page   = WCP_Security::get_query_int( WCP_Navigation::PAGE_VAR, 1 );
		$result = WCP_Orders::get_orders( array( 'page' => $page ) );

		return array(
			'section_template' => 'orders.php',
			'orders'           => $result,
		);
	}
}
