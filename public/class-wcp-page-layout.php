<?php
/**
 * Page-level layout negotiation for the portal.
 *
 * The portal is an application surface hosted inside a normal WordPress page,
 * and those two ideas disagree about chrome: the theme wants to print a page
 * title and frame the content in editorial whitespace, while the portal wants
 * to start just below the site header and own the viewport.
 *
 * This class resolves that disagreement for the one page that hosts the portal
 * and leaves every other page on the site untouched. It answers four
 * questions, all before any output happens:
 *
 *   1. Does the queried page host the portal?
 *   2. Which layout mode did that shortcode ask for?
 *   3. Should the theme's own page title be suppressed as a duplicate?
 *   4. Should the theme's own footer be suppressed as a duplicate?
 *
 * Questions 3 and 4 are the same disagreement at the two ends of the page. The
 * portal prints its own application footer, so a theme footer underneath it is
 * a second ending: two sets of links, two copyright lines, and a tall band of
 * editorial whitespace after the application has already closed.
 *
 * Detection reads the shortcode out of the post content rather than waiting for
 * it to render, because the title block is emitted *before* post content in a
 * block template. By the time the shortcode runs it is already too late to
 * influence the title.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides how the hosting page should frame the portal.
 */
class WCP_Page_Layout {

	/**
	 * Body class marking any page that hosts the portal.
	 */
	const BODY_CLASS = 'wcp-has-portal';

	/**
	 * Body class marking a portal page running in full-width mode.
	 */
	const BODY_CLASS_FULL = 'wcp-portal-full';

	/**
	 * Body class marking a portal page whose theme chrome is suppressed.
	 *
	 * Every fallback style ships behind this class, so nothing the plugin adds
	 * can reach a page that is not the portal.
	 */
	const BODY_CLASS_CHROMELESS = 'wcp-portal-chromeless';

	/**
	 * Body class marking a portal page that ends with the portal's own footer.
	 *
	 * Scopes the footer fallback styles the same way `BODY_CLASS_CHROMELESS`
	 * scopes the title ones: present on the portal's page and nowhere else.
	 */
	const BODY_CLASS_STANDALONE = 'wcp-portal-standalone';

	/**
	 * Resolved page state, or null before the first resolution.
	 *
	 * @var array|null
	 */
	private $state = null;

	/**
	 * Whether the current page hosts the portal.
	 *
	 * @return bool
	 */
	public function has_portal() {
		$state = $this->resolve();

		return $state['has_portal'];
	}

	/**
	 * Layout mode requested by the hosting page.
	 *
	 * @return string `full` or `contained`.
	 */
	public function get_layout() {
		$state = $this->resolve();

		return $state['layout'];
	}

	/**
	 * Whether the portal on this page runs edge to edge.
	 *
	 * @return bool
	 */
	public function is_full_width() {
		return 'full' === $this->get_layout();
	}

	/**
	 * Whether the theme's page title should be suppressed as a duplicate.
	 *
	 * Only ever true on the portal's own page, and only in full-width mode --
	 * a contained portal sits in the theme's content flow like any other block
	 * and the page title still belongs to it.
	 *
	 * @return bool
	 */
	public function should_suppress_title() {
		$state = $this->resolve();

		return $state['chromeless'];
	}

	/**
	 * Whether the theme's own footer should be suppressed as a duplicate.
	 *
	 * Tracks the chromeless decision by default, for the same reason: a portal
	 * that is still framed as ordinary page content is a block in the theme's
	 * flow, and the theme's footer is that page's real ending. Only a portal
	 * that has taken over the page gets to end it.
	 *
	 * @return bool
	 */
	public function should_suppress_footer() {
		$state = $this->resolve();

		return $state['standalone'];
	}

	/*
	|--------------------------------------------------------------------------
	| Hook callbacks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Drop the theme's page-title block on the portal page.
	 *
	 * This is the primary mechanism for block themes and it is deliberately
	 * boring: `render_block` is WordPress core API, so it works with every
	 * block theme -- past, present and not yet written -- without the plugin
	 * knowing a single thing about the active theme's markup. Returning an
	 * empty string removes the element entirely, so its height disappears with
	 * it and nothing has to be hidden after the fact.
	 *
	 * @param string        $block_content Rendered block HTML.
	 * @param array         $block         Parsed block.
	 * @param WP_Block|null $instance      Block instance, when available.
	 * @return string
	 */
	public function filter_title_block( $block_content, $block, $instance = null ) {
		if ( ! isset( $block['blockName'] ) || 'core/post-title' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->should_suppress_title() ) {
			return $block_content;
		}

		// A query loop renders other posts' titles through the same block.
		// Only the title belonging to the queried page is a duplicate of the
		// portal's own heading; everything else stays exactly as authored.
		if ( $instance instanceof WP_Block && isset( $instance->context['postId'] ) ) {
			if ( get_queried_object_id() !== (int) $instance->context['postId'] ) {
				return $block_content;
			}
		}

		return '';
	}

	/**
	 * Drop the theme's footer template part on the portal page.
	 *
	 * The block-theme mechanism, and the mirror image of the title one: a
	 * footer in a block theme is a template part whose `area` is `footer`, and
	 * that is core vocabulary rather than anything theme-specific. Removing the
	 * part removes its height with it, so there is nothing left to hide and no
	 * empty band where it used to be.
	 *
	 * This runs on `pre_render_block` rather than `render_block` because the
	 * part is being thrown away either way -- and a footer usually holds a
	 * navigation block, which costs queries to render. Short-circuiting skips
	 * the whole subtree instead of building it and discarding it.
	 *
	 * @param string|null $pre_render   Short-circuited render, if any.
	 * @param array       $parsed_block Parsed block.
	 * @return string|null Empty string to drop the block, otherwise untouched.
	 */
	public function filter_footer_block( $pre_render, $parsed_block ) {
		// Another filter already decided what this block renders as.
		if ( null !== $pre_render ) {
			return $pre_render;
		}

		// Cheapest tests first, in order. This callback runs for every block on
		// every page of the site, so the string compare rejects almost all of
		// them, the memoised page state rejects the rest, and only the portal's
		// own page ever reaches the area lookup below.
		if ( ! is_array( $parsed_block ) || ! isset( $parsed_block['blockName'] ) ) {
			return $pre_render;
		}

		if ( 'core/template-part' !== $parsed_block['blockName'] ) {
			return $pre_render;
		}

		if ( ! $this->should_suppress_footer() ) {
			return $pre_render;
		}

		if ( ! $this->is_footer_template_part( $parsed_block ) ) {
			return $pre_render;
		}

		return '';
	}

	/**
	 * Whether a template-part block belongs to the footer area.
	 *
	 * The area is usually stated on the block itself. When it is not, the
	 * template part's own registration is asked instead, which is how a part
	 * inserted without an explicit area still resolves correctly. The slug is
	 * the last resort and only as an exact match, so a theme's
	 * `footer-newsletter` part is never mistaken for the footer itself.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return bool
	 */
	private function is_footer_template_part( $parsed_block ) {
		$attrs = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] )
			? $parsed_block['attrs']
			: array();

		if ( isset( $attrs['area'] ) ) {
			return 'footer' === $attrs['area'];
		}

		$slug = isset( $attrs['slug'] ) ? (string) $attrs['slug'] : '';

		if ( '' === $slug ) {
			return false;
		}

		if ( function_exists( 'get_block_template' ) ) {
			$theme = isset( $attrs['theme'] ) ? (string) $attrs['theme'] : get_stylesheet();
			$part  = get_block_template( $theme . '//' . $slug, 'wp_template_part' );

			if ( $part && ! empty( $part->area ) ) {
				return 'footer' === $part->area;
			}
		}

		return 'footer' === $slug;
	}

	/**
	 * Add portal-page body classes.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$classes = (array) $classes;

		if ( ! $this->has_portal() ) {
			return $classes;
		}

		$classes[] = self::BODY_CLASS;

		if ( $this->is_full_width() ) {
			$classes[] = self::BODY_CLASS_FULL;
		}

		if ( $this->should_suppress_title() ) {
			$classes[] = self::BODY_CLASS_CHROMELESS;
		}

		if ( $this->should_suppress_footer() ) {
			$classes[] = self::BODY_CLASS_STANDALONE;
		}

		return $classes;
	}

	/**
	 * CSS fallback for the theme chrome that no hook can reach.
	 *
	 * Both halves of this are a last resort behind a hook, and both are kept
	 * safe by the same three constraints:
	 *
	 *   - Nothing is printed unless the portal has taken over this page. Every
	 *     other page on the site receives zero extra bytes.
	 *   - Every selector is prefixed with a page-specific body class, so it
	 *     cannot match anywhere else even if it somehow loaded.
	 *   - Every selector names the page's own chrome, so a sidebar widget or a
	 *     related-posts list on the same page is left alone.
	 *
	 * A theme that names things differently simply keeps its own chrome, which
	 * is untidy but never broken -- the failure mode is "no change", not damage.
	 *
	 * @return void
	 */
	public function print_chrome_fallback() {
		$rules = array_merge(
			$this->title_fallback_rules(),
			$this->footer_fallback_rules()
		);

		if ( empty( $rules ) ) {
			return;
		}

		wp_add_inline_style( WCP_Public::STYLE_HANDLE, implode( "\n", $rules ) . "\n" );
	}

	/**
	 * Classic-theme fallback rules for hiding the duplicate page title.
	 *
	 * Classic themes print the title from PHP with no equivalent of
	 * `render_block` to intercept, and there is no hook that every classic
	 * theme honours -- `the_title` is out because it also feeds menus, feeds
	 * and the document title.
	 *
	 * @return array CSS rule strings.
	 */
	private function title_fallback_rules() {
		if ( ! $this->should_suppress_title() ) {
			return array();
		}

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return array();
		}

		$body = '.' . self::BODY_CLASS_CHROMELESS;

		// The wrapper, not just the heading inside it: a classic theme's entry
		// header carries its own margins, so hiding only the <h1> leaves an
		// empty band exactly where the title used to be. Themes that print the
		// heading with no wrapper are covered by the child selectors.
		$selectors = array(
			$body . ' .entry-header',
			$body . ' .page-header',
			$body . ' .hentry > .entry-title',
			$body . ' .hentry > .page-title',
		);

		/**
		 * Filter the classic-theme selectors used to hide the duplicate title.
		 *
		 * Every selector is expected to stay scoped to the portal body class.
		 *
		 * @param array $selectors Selector list.
		 */
		$selectors = (array) apply_filters( 'wcp_classic_title_selectors', $selectors );

		return $this->hide_rule( $selectors );
	}

	/**
	 * Fallback rules for hiding a duplicate theme footer.
	 *
	 * Unlike the title, this half is printed for block themes too. Removing the
	 * footer template part is the real mechanism and it covers Twenty
	 * Twenty-Five and every other standard block theme, but a theme is free to
	 * build its ending out of ordinary blocks instead of a part, and a classic
	 * theme prints `get_footer()` with no hook to intercept at all. Both end up
	 * here.
	 *
	 * The selectors deliberately never say bare `footer`: the portal's own
	 * ending is a `<footer>` too, and hiding it would be a far worse bug than
	 * the one being fixed. Each one names a theme footer specifically, and the
	 * portal's class is excluded on top of that.
	 *
	 * @return array CSS rule strings.
	 */
	private function footer_fallback_rules() {
		if ( ! $this->should_suppress_footer() ) {
			return array();
		}

		$body = '.' . self::BODY_CLASS_STANDALONE;
		$not  = ':not(.wcp-footer)';

		$selectors = array(
			// Block themes: the footer area of the block template.
			$body . ' .wp-site-blocks > footer' . $not,
			$body . ' > footer.wp-block-template-part' . $not,
			// Classic themes: the two names essentially all of them use.
			$body . ' .site-footer' . $not,
			$body . ' #colophon' . $not,
			// The entry's own footer -- the exact mirror of the entry header
			// hidden at the top of the page, and on a page that is nothing but
			// the portal it holds only the theme's edit link.
			$body . ' .entry-footer',
			// A footer widget area, which classic themes often print as a
			// sibling of the footer rather than inside it. Anchored to
			// `#content` so it can only match a widget area that comes *after*
			// the content: a real sidebar lives inside `#content`, beside the
			// content rather than below it, and is never matched here.
			$body . ' #content ~ .widget-area',
			$body . ' .footer-widgets',
		);

		/**
		 * Filter the selectors used to hide a duplicate theme footer.
		 *
		 * Every selector is expected to stay scoped to the portal body class,
		 * and to exclude the portal's own `.wcp-footer`.
		 *
		 * @param array $selectors Selector list.
		 */
		$selectors = (array) apply_filters( 'wcp_theme_footer_selectors', $selectors );

		return $this->hide_rule( $selectors );
	}

	/**
	 * Build a single `display: none` rule from a selector list.
	 *
	 * @param array $selectors Selector list.
	 * @return array One rule, or nothing when the list is empty.
	 */
	private function hide_rule( $selectors ) {
		$selectors = array_filter( array_map( 'trim', (array) $selectors ) );

		if ( empty( $selectors ) ) {
			return array();
		}

		return array( implode( ",\n", $selectors ) . " {\n\tdisplay: none;\n}" );
	}

	/*
	|--------------------------------------------------------------------------
	| Resolution
	|--------------------------------------------------------------------------
	*/

	/**
	 * Resolve and memoise the current page's portal state.
	 *
	 * @return array
	 */
	private function resolve() {
		if ( null !== $this->state ) {
			return $this->state;
		}

		$state = array(
			'has_portal' => false,
			'layout'     => 'contained',
			'chromeless' => false,
			'standalone' => false,
		);

		$post = $this->queried_post();

		if ( null === $post ) {
			$this->state = $state;

			return $this->state;
		}

		$content    = (string) $post->post_content;
		$has_portal = has_shortcode( $content, WCP_SHORTCODE_TAG );

		/**
		 * Filter whether the current page hosts the portal.
		 *
		 * Shared with asset loading so detection can never disagree with
		 * itself: whatever answers this filter decides both what loads and how
		 * the page is framed.
		 *
		 * @param bool    $has_portal Detection result.
		 * @param WP_Post $post       Queried post.
		 */
		$has_portal = (bool) apply_filters( 'wcp_page_has_portal', $has_portal, $post );

		if ( ! $has_portal ) {
			$this->state = $state;

			return $this->state;
		}

		$state['has_portal'] = true;
		$state['layout']     = WCP_Shortcodes::normalize_layout( $this->parse_layout_attribute( $content ) );

		$chromeless = ( 'full' === $state['layout'] );

		/**
		 * Filter whether the theme's page title and framing are suppressed.
		 *
		 * Return false to keep the theme's title on a page that mixes the
		 * portal with editorial content of its own.
		 *
		 * @param bool    $chromeless Whether to suppress theme chrome.
		 * @param WP_Post $post       Queried post.
		 * @param string  $layout     Resolved layout mode.
		 */
		$state['chromeless'] = (bool) apply_filters( 'wcp_suppress_page_title', $chromeless, $post, $state['layout'] );

		/**
		 * Filter whether the theme's own footer is suppressed on this page.
		 *
		 * Return false to keep the theme footer under a full-width portal --
		 * for a site whose footer carries something the portal does not, such
		 * as a cookie notice or a legally required disclosure.
		 *
		 * @param bool    $chromeless Whether to suppress the theme footer.
		 * @param WP_Post $post       Queried post.
		 * @param string  $layout     Resolved layout mode.
		 */
		$state['standalone'] = (bool) apply_filters( 'wcp_suppress_theme_footer', $state['chromeless'], $post, $state['layout'] );

		$this->state = $state;

		return $this->state;
	}

	/**
	 * The post being viewed, when this is a front-end singular request.
	 *
	 * @return WP_Post|null
	 */
	private function queried_post() {
		if ( is_admin() || ! is_singular() ) {
			return null;
		}

		$post = get_post();

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Read the `layout` attribute off the first portal shortcode in content.
	 *
	 * @param string $content Post content.
	 * @return string Raw attribute value, or an empty string.
	 */
	private function parse_layout_attribute( $content ) {
		$pattern = get_shortcode_regex( array( WCP_SHORTCODE_TAG ) );

		if ( ! preg_match( '/' . $pattern . '/s', $content, $match ) ) {
			return '';
		}

		// Group 3 holds the raw attribute string for the matched shortcode.
		$atts = isset( $match[3] ) ? shortcode_parse_atts( $match[3] ) : array();

		if ( ! is_array( $atts ) || ! isset( $atts['layout'] ) ) {
			return '';
		}

		return (string) $atts['layout'];
	}
}
