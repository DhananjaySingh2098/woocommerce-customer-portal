# Changelog

All notable changes to WooCommerce Customer Portal are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-09-10

First stable release.

### Added

- `[wcp_customer_portal]` shortcode rendering a full-width, self-contained
  portal shell (sidebar, sticky header, off-canvas mobile drawer) on block and
  classic themes without overriding theme styles.
- Sign-in screen for logged-out visitors, linking to WooCommerce's own login
  and registration; no custom authentication.
- **Dashboard** composed from modules that each carry real data or do not
  render: compact status cards (orders, lifetime spend from WooCommerce's own
  `wc_get_customer_total_spent()`, latest order, saved addresses, profile
  state, member since), recent orders, account health, quick actions, a store
  discovery panel listing the shop's newest catalog-visible products, and an
  activity feed built from the order timestamps WooCommerce actually records
  (placed, paid, completed). A customer with no orders sees the same
  composition with a **Getting started** checklist in place of order history —
  five real conditions (account, name, billing, shipping, first order) with a
  percentage that is simply completed over offered, and never invented
  statistics.
- **Orders**: paginated history and an order detail view with line items,
  totals, addresses, payment and shipping details and downloadable files.
  Custom order statuses registered by other plugins are labelled and coloured
  automatically.
- **Profile**: edit display name, first/last name and email; change password
  through WordPress's own password APIs with current-password verification.
- **Addresses**: billing and shipping editors driven by WooCommerce's
  country-aware address fields, with server-side validation (postcode, phone,
  email, state) and country → state switching without a page load.
- **Admin settings** (WooCommerce → Customer Portal, `manage_woocommerce`):
  portal page, enabled sections, default section, accent colour, default
  appearance, default visual theme, premium motion and 3D effect switches, and
  a live preview. Every value is validated against a fixed list or pattern.
- **Five visual themes** — Aurora, Obsidian, Pearl, Midnight and Emerald —
  built entirely from CSS custom properties in one stylesheet, each with a
  complete light and dark palette.
- **Appearance switcher** in the portal header: colour scheme (System, Light,
  Dark) and visual theme, with swatch previews, keyboard-operable radio
  groups, local persistence and a pre-paint script that prevents a flash of
  the wrong theme. Precedence: preset accent → administrator's custom accent →
  the customer's appearance choice.
- **Tasteful CSS 3D**: selected cards tilt up to 5° toward a fine pointer with
  layered `translateZ` depth, and a soft pointer-tracked spotlight moves across
  premium surfaces. No 3D library, transform and opacity only, one
  `requestAnimationFrame` per pointer move and no continuous loop. Disabled on
  touch, below 1025px, under `prefers-reduced-motion`, while a field inside a
  card has focus, and whenever an administrator switches the effects off.
- Client-side section navigation using `fetch` + the History API on top of
  fully server-rendered pages; every section works with JavaScript disabled.
- REST API under `wcp/v1` for orders, profile and addresses, secured by
  cookie authentication plus two nonces (`wp_rest` and `wcp_portal_action`).
- Per-customer rate limiting on profile, address and password writes, with
  `429` responses carrying `Retry-After`.
- **Portal footer**: one shared component closing every authenticated section
  with brand, quick actions, account and store navigation, real account status
  and a bottom bar carrying only the legal pages the site actually has. A
  compact variant closes the signed-out screen. It is the application's footer,
  scoped inside the portal.
- A full-width portal page ends with that footer: the theme's own footer is
  suppressed on that one page, so the portal reads as a complete application
  surface rather than a panel inside an article. Block themes lose the footer
  template part through `pre_render_block`; classic themes are covered by a
  short list of selectors scoped to the `wcp-portal-standalone` body class.
  Every other page on the site — including a `layout="contained"` portal — keeps
  its theme footer exactly as the theme wrote it, and a site can keep it under a
  full-width portal too by returning false from `wcp_suppress_theme_footer`.
- Theme-overridable templates under `woocommerce-customer-portal/` in the
  active theme.
- Graceful degradation when WooCommerce is missing, outdated or deactivated:
  an admin notice and a plain message on the front end, never a fatal error.
- WooCommerce HPOS (High-Performance Order Storage) compatibility declaration.
- Accessibility: keyboard-operable navigation, drawer and appearance switcher,
  focus management, ARIA live regions for feedback, `role="progressbar"` with a
  spoken value on account setup, states carried in words as well as colour,
  WCAG AA contrast in every theme in both schemes, and full
  `prefers-reduced-motion` support — which disables tilt, spotlight, ambient
  motion, stagger and shimmer while leaving focus rings untouched.
- Translation-ready (`woocommerce-customer-portal` text domain, bundled POT).

### Security

- Customer identity comes exclusively from `get_current_user_id()`; browser
  supplied user or customer identifiers are never read.
- Every order is ownership-checked before any field is returned, and unknown,
  foreign and malformed order IDs produce the same `404` response.
- All writes require authentication, both nonces, allowlisted fields,
  server-side validation and sanitisation; settings writes additionally
  require `manage_woocommerce`.
- Sections disabled in settings are blocked at the URL, REST and form-handler
  layers, not merely hidden.
- Passwords are never returned, logged or echoed; failed attempts are
  throttled separately from other profile writes.
- Accent colours are validated with `sanitize_hex_color()` and emitted as CSS
  custom properties only; no arbitrary CSS can reach the page. The visual theme
  is an allowlisted slug, re-validated before it is interpolated into the
  pre-paint script and re-checked in the browser against that same list.
- The store discovery panel queries only published, catalog-visible products,
  so the portal cannot surface something the store has hidden.
- Deactivation keeps all settings; uninstall removes only the plugin's own
  option and transients and never touches WooCommerce orders, customers or
  user accounts.

[1.0.0]: https://github.com/dhananjaysingh/woocommerce-customer-portal/releases/tag/v1.0.0
