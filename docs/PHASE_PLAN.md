# Phase Plan

Six phases. Each one leaves the plugin in a shippable state — never a
half-migrated one — and no phase begins before the previous is approved.

---

## Phase 1 — Foundation + Premium UI Shell ✅ Complete

Plugin foundation, authentication foundation, and the full visual system.

**Delivered**

- Plugin bootstrap with guarded constants, lifecycle hooks, environment guards
  and HPOS / cart-checkout-blocks compatibility declarations.
- `WCP_Loader` orchestrator: one file lists every hook the plugin registers.
- Authentication foundation — identity resolved from the WordPress session
  only; `WCP_Account` has a private constructor and a single
  `for_current_user()` factory.
- Graceful degradation when WooCommerce is absent: no fatal, admin notice,
  portal disabled, shortcode still claimed.
- `[wcp_customer_portal]` shortcode with authenticated and logged-out states.
- Premium application shell: sidebar with sliding active indicator, sticky
  header, centred content column, mobile drawer.
- Dashboard with real account data only — no fabricated statistics.
- Design system: tokens, components, responsive rules, dark-theme readiness.
- Animation system with full `prefers-reduced-motion` support.
- Conditional asset loading, theme-overridable templates, documentation.

**Explicitly out of scope** — order data, address editing, profile editing,
REST routes, admin settings.

---

## Phase 2 — Orders & Order Details ✅ Complete

Replace the Orders placeholder with a native portal experience.

- `WCP_Orders`: query the current customer's orders through the WooCommerce CRUD
  API (HPOS-safe), scoped to `get_current_user_id()`.
- Orders list: status pill, order number, date, item summary, total, action.
  Paginated, with a designed empty state for customers who have not ordered.
- Order detail view: line items, totals breakdown, addresses, downloads,
  tracking where the store provides it.
- First REST routes under `wcp/v1` — `GET /orders`, `GET /orders/{id}` — each
  with a real `permission_callback` and an `owns_resource()` ownership check.
- New components: data table, status pill variants, order timeline, skeleton
  loading states built on the existing `.is-loading` primitive.
- Tests: ownership enforcement (customer A must receive 403 for customer B's
  order), pagination, empty state, guest access.

**Delivered**

- `WCP_Orders` reads through `wc_get_orders()` / `wc_get_order()` only, so the
  plugin is correct under HPOS and the legacy post tables without knowing which
  is active. No SQL.
- Customer scoping is applied *after* the query filter runs, so no caller and no
  filter can widen it beyond `get_current_user_id()`.
- `WCP_Orders::is_readable_by_current_user()` is the single ownership gate; the
  templates and the REST routes both reach order data through it.
- Orders list with status pills, pagination (10/page), and a designed empty
  state. Order detail with timeline, line items, totals, addresses, payment,
  shipping and downloads.
- `WCP_Order_Status`: five tones, custom statuses safe by construction.
- `GET /wcp/v1/orders` and `GET /wcp/v1/orders/{id}`.
- Skeleton loading state on a 180ms delay, reduced-motion aware.

**Deviation from plan:** a non-owned order returns `404`, not the `403` this
document originally specified. `403` confirms an ID is real, which is the signal
needed to enumerate orders. See [`API.md`](API.md#errors).

**Deliberately deferred:** order actions (reorder, cancel, invoice download)
move to Phase 4 — reading orders safely is a complete unit of work on its own.

---

## Phase 3 — Profile & Addresses ✅ Complete

- Billing and shipping address forms driven by
  `WC()->countries->get_address_fields()`, so locale-specific field sets, labels
  and required flags come from WooCommerce rather than being reinvented.
- Profile: display name, first/last name, email, password change — each routed
  through WordPress's own validation and re-authentication requirements.
- First mutating endpoints: nonce verification, server-side capability re-check,
  full field sanitisation, and typed error responses.
- Form component layer: inputs, selects, validation states, inline errors,
  optimistic save with rollback, success toast.
- Accessibility focus: label association, `aria-describedby` error wiring,
  `aria-live` status announcements, and focus management on submit.

**Delivered**

- `WCP_Profile` and `WCP_Addresses`: neither takes a user ID, so a write can
  only land on the session's own record. Both allowlist their input twice.
- Address fields, labels, order and required flags come from
  `WC()->countries->get_address_fields()`; the store's own field customisations
  and every WooCommerce locale come for free.
- `POST /wcp/v1/profile` and `POST /wcp/v1/addresses/{type}`, gated by a
  portal-scoped nonce on top of WordPress's `wp_rest`.
- Progressive enhancement: the forms are real posts handled by
  `WCP_Form_Handler` (Post/Redirect/Get, one-shot flash) and enhanced by script
  into in-place saves. Both call the same validators.
- Form component layer: fields, selects, hints, per-field errors, live status
  region, busy state.

**Deviation from plan:** "optimistic save with rollback" was not built. An
optimistic UI shows success before the server has confirmed it, which the phase
brief separately forbids ("do not claim success before server confirmation").
The forms show a busy state and report only what the server actually returned.

---

## Phase 4 — Advanced Dashboard + UI Enhancements ✅ Complete

Only now does the dashboard show statistics — because only now is there
verified data behind them.

- Real metrics: order count, lifetime value, most recent order status.
- Recent activity timeline replacing the Phase 1 empty state.
- Dark theme activated: wire `data-wcp-theme` to a user preference with a
  `prefers-color-scheme` default. The palette already exists.
- Client-side section switching with the History API, preserving the sliding
  indicator, focus placement and back-button behaviour.
- Progressive polish: view transitions where supported, refined skeletons.

**Delivered**

- `WCP_Dashboard`: order count, lifetime value, latest order and saved-address
  state, all read for the session's own customer through `WCP_Orders` and
  `wc_get_customer_total_spent()`. No fabricated deltas.
- Recent orders (4) and a real activity feed built from the three timestamps
  WooCommerce actually records, with same-minute milestones collapsed.
- Dark theme wired: `WCP_Theme` prints a pre-paint resolver, the toggle persists
  an explicit choice, `prefers-color-scheme` is the default and keeps applying
  until the customer chooses. Both palettes audited against WCAG AA.
- Client-side section switching over the server-rendered portal, preserving deep
  links, Back/Forward, the active indicator and focus placement.
- Section and dashboard skeletons on the same 180ms delay as Phase 2's.

**Deviation from plan:** "view transitions where supported" was not used. The
View Transitions API would animate the swap, but it captures a snapshot of the
whole document — including the surrounding theme, which this plugin does not
own and must not animate. The section swap uses a scoped opacity transition
instead.

**Found and fixed while auditing:** the scoped reset gives `h1`–`h4` typography
as well as spacing, so every heading component's `font-weight` and
`letter-spacing` had been silently overridden since Phase 1. The portal's
intended type scale had never actually rendered.

---

## Phase 5 — Admin Settings + Security Hardening ✅ Complete

- Settings screen under WooCommerce → Customer Portal, populating
  `admin/views/`: enabled sections, accent colour, portal page selection,
  default landing section.
- Settings API integration with sanitisation callbacks and capability checks.
- Rate limiting on mutating REST routes.
- Security hardening pass: full audit against `docs/SECURITY.md`, plus a
  third-party review of every endpoint added in Phases 2–4.
- Optional Content-Security-Policy guidance for the portal page.

**Delivered**

- WooCommerce → Customer Portal: enabled, portal page, sections, landing
  section, accent colour, default colour scheme. Settings API, one option, one
  sanitiser that runs on read as well as write, `manage_woocommerce` checked at
  menu, screen and save.
- Disabled sections enforced at every layer — sidebar, URL router, form
  handler, both REST controllers, dashboard — through one
  `WCP_Settings::section_enabled()`.
- `WCP_Rate_Limit`: per-user buckets in the models, so both transports
  inherit them; `429` + `Retry-After`; failures-only counting on password
  changes.
- Full endpoint matrix, password-change audit, XSS audit with live payloads,
  CSP guidance and a `wcp_theme_script_attributes` filter in `SECURITY.md`.

**Found and fixed:** a fresh install with no option row resolved `enabled` to
`false` — the sanitiser read an absent checkbox as unchecked — which switched
the portal off for everyone until the first save. Defaults are now merged
before sanitising on the read path.

**Deviation from plan:** "third-party review of every endpoint" cannot be
performed by the same author. The matrix in `SECURITY.md` is written to be
handed to a reviewer; it is not a substitute for one.

---

## Phase 6 — Testing, Packaging + Release

- PHPUnit suite on the WordPress test framework: unit tests for helpers,
  security and navigation; integration tests for shortcode rendering,
  authentication states and every REST route.
- Cross-customer access tests promoted to a required CI gate.
- PHPCS against `WordPress-Extra` and `WordPress-Docs`, plus PHPStan.
- GitHub Actions matrix: PHP 7.4–8.3 × WordPress 6.0–latest × WooCommerce
  7.0–latest.
- Translation template (`.pot`), `readme.txt` finalisation, build script that
  ships only runtime files, and a tagged release.

---

## Sequencing rationale

Read before write (Phase 2 before Phase 3): a read-only surface exercises the
authentication and ownership model against real data with no possibility of
corrupting it. Statistics after data (Phase 4 after Phase 2): the alternative is
placeholder numbers that quietly become permanent. Hardening after the
endpoints exist (Phase 5): auditing hypothetical routes is theatre.
