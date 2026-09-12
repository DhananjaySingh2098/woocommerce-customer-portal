<?php
/**
 * Plugin orchestrator and hook registry.
 *
 * Collects every action/filter the plugin registers into one place, then
 * commits them in a single pass. Components stay free of `add_action()` calls,
 * which makes the plugin's full hook surface readable from this one file.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads dependencies, wires components together and registers hooks.
 */
final class WCP_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var WCP_Loader|null
	 */
	private static $instance = null;

	/**
	 * Queued actions.
	 *
	 * @var array<int,array>
	 */
	private $actions = array();

	/**
	 * Queued filters.
	 *
	 * @var array<int,array>
	 */
	private $filters = array();

	/**
	 * Instantiated components, keyed by short name.
	 *
	 * @var array<string,object>
	 */
	private $components = array();

	/**
	 * Guard against a double boot.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Retrieve the singleton.
	 *
	 * @return WCP_Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use {@see WCP_Loader::instance()}.
	 */
	private function __construct() {}

	/*
	|----------------------------------------------------------------------
	| Boot paths
	|----------------------------------------------------------------------
	*/

	/**
	 * Full boot: admin surface plus the customer portal.
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->commit();

		/**
		 * Fires once the plugin has fully booted with WooCommerce available.
		 *
		 * @param WCP_Loader $loader Plugin orchestrator.
		 */
		do_action( 'wcp_loaded', $this );
	}

	/**
	 * Degraded boot used when WooCommerce is missing or unsupported.
	 *
	 * The portal is disabled, no front-end assets are enqueued, and the
	 * shortcode is still claimed so pages never render a raw `[wcp_...]`
	 * string to visitors.
	 *
	 * @return void
	 */
	public function run_without_woocommerce() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		require_once WCP_PLUGIN_DIR . 'includes/class-wcp-security.php';
		require_once WCP_PLUGIN_DIR . 'admin/class-wcp-admin.php';

		$this->components['admin'] = new WCP_Admin();

		$this->add_action( 'admin_enqueue_scripts', $this->components['admin'], 'enqueue_assets' );
		$this->add_filter( 'plugin_action_links_' . WCP_PLUGIN_BASENAME, $this->components['admin'], 'plugin_action_links' );
		$this->add_filter( 'plugin_row_meta', $this->components['admin'], 'plugin_row_meta', 10, 2 );

		add_shortcode( WCP_SHORTCODE_TAG, array( $this, 'render_disabled_shortcode' ) );

		$this->commit();
	}

	/**
	 * Shortcode output while the portal is disabled.
	 *
	 * Visitors see nothing. Users who can fix the problem see why.
	 *
	 * @return string
	 */
	public function render_disabled_shortcode() {
		if ( ! WCP_Security::current_user_can_manage() ) {
			return '';
		}

		return sprintf(
			'<div class="wcp-inline-notice" role="status" style="padding:14px 16px;border:1px solid #e6e8ee;border-left:3px solid #d97706;border-radius:10px;background:#fffaf2;color:#4a3a1f;font-size:14px;line-height:1.5;"><strong>%1$s</strong> %2$s</div>',
			esc_html__( 'WooCommerce Customer Portal is inactive.', 'woocommerce-customer-portal' ),
			esc_html__( 'WooCommerce must be installed and active for the portal to render. Only site administrators can see this message.', 'woocommerce-customer-portal' )
		);
	}

	/*
	|----------------------------------------------------------------------
	| Wiring
	|----------------------------------------------------------------------
	*/

	/**
	 * Require every class the plugin needs and instantiate components.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once WCP_PLUGIN_DIR . 'includes/class-wcp-security.php';
		require_once WCP_PLUGIN_DIR . 'includes/wcp-template-functions.php';
		require_once WCP_PLUGIN_DIR . 'includes/class-wcp-auth.php';
		require_once WCP_PLUGIN_DIR . 'includes/class-wcp-settings.php';
		require_once WCP_PLUGIN_DIR . 'includes/class-wcp-rate-limit.php';

		require_once WCP_PLUGIN_DIR . 'admin/class-wcp-admin.php';
		require_once WCP_PLUGIN_DIR . 'admin/class-wcp-settings-page.php';

		require_once WCP_PLUGIN_DIR . 'public/class-wcp-account.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-navigation.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-addresses.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-dashboard.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-form-handler.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-order-status.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-orders.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-page-layout.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-profile.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-theme.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-public.php';
		require_once WCP_PLUGIN_DIR . 'public/class-wcp-shortcodes.php';

		require_once WCP_PLUGIN_DIR . 'rest/class-wcp-rest-account.php';
		require_once WCP_PLUGIN_DIR . 'rest/class-wcp-rest-orders.php';

		$this->components['admin']         = new WCP_Admin();
		$this->components['navigation']    = new WCP_Navigation();
		$this->components['page_layout']   = new WCP_Page_Layout();
		$this->components['public']        = new WCP_Public( $this->components['page_layout'] );
		$this->components['shortcodes']    = new WCP_Shortcodes( $this->components['public'], $this->components['navigation'] );
		$this->components['rest_orders']   = new WCP_REST_Orders();
		$this->components['rest_account']  = new WCP_REST_Account();
		$this->components['forms']         = new WCP_Form_Handler();
		$this->components['theme']         = new WCP_Theme( $this->components['page_layout'] );
		$this->components['settings_page'] = new WCP_Settings_Page();
	}

	/**
	 * Register admin-side hooks.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$admin    = $this->components['admin'];
		$settings = $this->components['settings_page'];

		$this->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
		$this->add_filter( 'plugin_action_links_' . WCP_PLUGIN_BASENAME, $admin, 'plugin_action_links' );
		$this->add_filter( 'plugin_row_meta', $admin, 'plugin_row_meta', 10, 2 );

		// `admin_init` is where the Settings API expects registration; the
		// menu goes late enough to sit under WooCommerce's own entry.
		$this->add_action( 'admin_init', $settings, 'register_setting' );
		$this->add_action( 'admin_menu', $settings, 'register_menu', 60 );
		$this->add_action( 'admin_notices', $admin, 'portal_page_notice' );

		// Saving settings must invalidate the per-request cache. Both hooks:
		// the very first save creates the row and fires `add_option_`, not
		// `update_option_`.
		$this->add_action( 'update_option_' . WCP_Settings::OPTION, 'WCP_Settings', 'flush' );
		$this->add_action( 'add_option_' . WCP_Settings::OPTION, 'WCP_Settings', 'flush' );
	}

	/**
	 * Register front-end hooks.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$public     = $this->components['public'];
		$shortcodes = $this->components['shortcodes'];
		$layout     = $this->components['page_layout'];

		$this->add_action( 'wp_enqueue_scripts', $public, 'register_assets', 5 );
		$this->add_action( 'wp_enqueue_scripts', $public, 'maybe_enqueue_assets', 20 );

		// After the stylesheet is enqueued, so the chrome fallback has a handle
		// to attach to.
		$this->add_action( 'wp_enqueue_scripts', $layout, 'print_chrome_fallback', 30 );
		$this->add_action( 'wp_enqueue_scripts', $public, 'print_accent_styles', 31 );

		// Priority 1: the attribute must be on <html> before anything paints.
		$this->add_action( 'wp_head', $this->components['theme'], 'print_head_script', 1 );

		$this->add_filter( 'body_class', $layout, 'body_class' );
		$this->add_filter( 'render_block', $layout, 'filter_title_block', 10, 3 );

		// Short-circuits the footer template part before it renders, so the
		// navigation inside it never costs a query on the portal's page.
		$this->add_filter( 'pre_render_block', $layout, 'filter_footer_block', 10, 2 );

		$this->add_action( 'init', $shortcodes, 'register' );

		$this->add_action( 'rest_api_init', $this->components['rest_orders'], 'register_routes' );
		$this->add_action( 'rest_api_init', $this->components['rest_account'], 'register_routes' );

		// Early enough to redirect before any output is sent.
		$this->add_action( 'template_redirect', $this->components['forms'], 'handle', 5 );
	}

	/*
	|----------------------------------------------------------------------
	| Hook registry
	|----------------------------------------------------------------------
	*/

	/**
	 * Queue an action.
	 *
	 * @param string        $hook          Hook name.
	 * @param object|string $component     Object holding the callback, or a class name for a static method.
	 * @param string        $callback      Method name.
	 * @param int           $priority      Optional. Hook priority.
	 * @param int           $accepted_args Optional. Argument count.
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Queue a filter.
	 *
	 * @param string        $hook          Hook name.
	 * @param object|string $component     Object holding the callback, or a class name for a static method.
	 * @param string        $callback      Method name.
	 * @param int           $priority      Optional. Hook priority.
	 * @param int           $accepted_args Optional. Argument count.
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Commit every queued hook to WordPress.
	 *
	 * @return void
	 */
	private function commit() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		$this->actions = array();
		$this->filters = array();
	}

	/*
	|----------------------------------------------------------------------
	| Accessors
	|----------------------------------------------------------------------
	*/

	/**
	 * Retrieve a booted component.
	 *
	 * @param string $name Component key, e.g. `navigation`.
	 * @return object|null
	 */
	public function get( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}
}
