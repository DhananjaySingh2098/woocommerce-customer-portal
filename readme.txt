=== WooCommerce Customer Portal ===
Contributors: dhananjaysingh
Tags: woocommerce, customer portal, my account, dashboard, orders
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 11.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A premium customer portal for WooCommerce. Orders, addresses and account
management in one polished, responsive interface.

== Description ==

WooCommerce Customer Portal gives your customers an app-like account area in
place of the default My Account tabs. Add it to any page with the
`[wcp_customer_portal]` shortcode and it takes care of the rest.

= What customers get =

* **Dashboard** — status cards, recent orders, account health, quick actions,
  the store's newest products and an activity feed. Every figure comes from
  WooCommerce or WordPress; nothing is estimated. A new customer gets a
  Getting started checklist built from their real account state instead of a
  screen of zeroes.
* **Orders** — paginated order history with a full order detail view: line
  items, totals, addresses, payment and shipping details, and downloadable
  files.
* **Profile** — name, display name and email, plus a password change that
  asks for the current password first.
* **Addresses** — billing and shipping editors driven by WooCommerce's own
  country-aware address fields, with country → state switching and
  server-side validation.
* **Five visual themes** — Aurora, Obsidian, Pearl, Midnight and Emerald, each
  in light and dark. The switcher follows the system preference, remembers an
  explicit choice, and never flashes the wrong theme on load.
* **A portal footer** — every screen closes with account links, store links,
  quick actions and your real account status, so short pages feel finished
  rather than empty. On a full-width portal page that footer is the end of the
  page: your theme's footer steps aside there, and stays exactly as your theme
  wrote it everywhere else on your site.
* **Works everywhere** — a real off-canvas drawer on phones, keyboard-operable
  throughout, and every form works with JavaScript disabled.

= What store owners get =

* A settings screen under **WooCommerce → Customer Portal**: choose the
  portal page, switch sections on or off, pick the landing section, the
  accent colour, the default visual theme and colour scheme, and whether
  premium motion and 3D card effects are on, with a live preview.
* A disabled section is blocked at every layer — URL, REST API and form
  handler — not merely hidden.
* Custom order statuses registered by other plugins are labelled and
  coloured automatically.
* Compatible with High-Performance Order Storage (HPOS).

= Built for security =

* The customer is always the authenticated WordPress user. No user or
  customer ID is ever read from the browser, so there is no way to request
  another customer's data.
* An order that is not yours returns the same `404` as one that does not
  exist.
* Every write requires authentication, two nonces, allowlisted fields and
  server-side validation. Writes are rate-limited per customer.
* Passwords go through WordPress's own APIs and are never returned or logged.
* Deactivation keeps your settings. Uninstall removes only the plugin's own
  option and transients — never WooCommerce orders, customers or accounts.

= Built for performance =

* No frontend framework. One hand-written stylesheet, one dependency-free
  script, no build step.
* Assets load only on pages that actually render the portal.
* No external requests: no webfonts, no CDN, no avatar service, no analytics.

= Built for developers =

* Every template is overridable from your theme
  (`your-theme/woocommerce-customer-portal/`).
* Filters for navigation, layout, access control, order queries, status tones,
  profile fields, rate limits and asset loading.
* A REST API under `wcp/v1` that shares its models — and therefore its
  ownership checks — with the templates.
* Design tokens are CSS custom properties: retheme without touching the
  stylesheet.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/woocommerce-customer-portal/`, or
   install it through the Plugins screen.
2. Activate the plugin. WooCommerce must be active.
3. Create a page and add the shortcode `[wcp_customer_portal]`.
4. Optionally, go to **WooCommerce → Customer Portal** to choose the portal
   page, sections, accent colour, visual theme and default appearance.

The portal is full-width by default. Use `[wcp_customer_portal
layout="contained"]` to keep it inside the theme's content column.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. Without an active WooCommerce installation the portal disables itself
cleanly — your site keeps working and an admin notice explains why.

= Can customers see each other's data? =

No. The portal resolves the customer from the authenticated WordPress session
and never from a URL parameter, cookie or request body. There is no code path
that loads another customer's account.

= Does it replace the WooCommerce My Account page? =

No. It lives on a page of your choosing and links to WooCommerce for login,
registration and anything it does not cover. You can keep both, or point your
account links at the portal page.

= Can I change the colours? =

Pick an accent colour in the settings screen, or override the CSS custom
properties on `.wcp-portal` in your theme's stylesheet for full control.

= Does it work with my theme? =

The portal is fully scoped and adapts to its container, so it works in a
narrow theme column as well as a full-width template. If your theme has a
fixed header, set `--wcp-sticky-offset` to its height.

= Is it accessible? =

Semantic HTML, keyboard-accessible navigation, visible focus states on every
interactive element, a focus-trapped mobile drawer with Escape support, ARIA
live regions for form feedback, WCAG AA contrast in both themes, and full
`prefers-reduced-motion` support.

= Will uninstalling delete my customers' data? =

No. Uninstall removes the plugin's own settings and temporary counters only.
Orders, customers, addresses and user accounts belong to WooCommerce and are
left untouched.

== Screenshots ==

1. The customer dashboard in the Aurora theme.
2. The dashboard in the Midnight theme, dark.
3. Order history, paginated.
4. Order detail in the Obsidian theme, dark.
5. The mobile navigation drawer.
6. Order history on a phone in the Emerald theme, dark.
7. Settings under WooCommerce → Customer Portal.

== Changelog ==

= 1.0.0 =
* Initial release.
* Dashboard, orders, order detail, profile and address management.
* Admin settings: portal page, sections, landing section, accent, visual
  theme, appearance, motion and 3D effects.
* Five visual themes in light and dark, with a persisted appearance switcher
  and no flash on load.
* Tasteful CSS 3D card depth and a pointer spotlight, both disabled on touch
  and under prefers-reduced-motion.
* A portal application footer on every section, with a compact variant on the
  sign-in screen.
* REST API under `wcp/v1` with per-customer rate limiting.
* Theme-overridable templates and a filter surface for developers.
* HPOS compatible. Translation-ready.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
