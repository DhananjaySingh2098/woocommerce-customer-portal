# Security

Phase 1 ships no data endpoints, no forms and no state changes. That is exactly
why the security model is written now: the rules below are the constraints every
later phase has to satisfy, and they are cheaper to establish before there is
data to leak than after.

## Threat model

The portal renders a customer's own account data on a public-facing page. The
risks that actually matter for that shape of feature are:

1. **Cross-customer data exposure** — customer A viewing customer B's orders.
2. **Unauthenticated access** — anonymous visitors reaching account data.
3. **Cross-site scripting** — untrusted values reaching the page unescaped.
4. **Cross-site request forgery** — a third-party page triggering a state change.
5. **Direct file execution** — a PHP file called outside WordPress.

## 1. Identity comes from the session, never the request

The single most important rule in the codebase:

> The customer is always `get_current_user_id()`. There is no code path that
> accepts a customer, user or order ID from the request.

[`WCP_Auth`](../includes/class-wcp-auth.php) is the only place identity is
resolved, and it reads exclusively from the WordPress session:

```php
public static function current_customer_id() {
    return (int) get_current_user_id();
}
```

[`WCP_Account`](../public/class-wcp-account.php) has a **private constructor**
and one factory, `for_current_user()`. It is not possible to instantiate an
account view for an arbitrary user, because no such constructor exists — the
guarantee is structural, not a check someone has to remember to write.

The plugin defines no passwords, no sessions, no tokens and no "remember me"
of its own. Authentication is WordPress's, and WooCommerce's My Account flow
handles login, registration and logout.

## 2. Authorisation

`WCP_Auth::can_view_portal()` gates every render. A logged-out request receives
`templates/login-required.php`, which contains no customer data of any kind —
safe to serve from a full-page cache.

For resources that arrive in later phases,
[`WCP_Security::owns_resource()`](../includes/class-wcp-security.php) is the
ownership check:

```php
public static function owns_resource( $owner_id ) {
    $owner_id   = absint( $owner_id );
    $current_id = get_current_user_id();
    return $owner_id > 0 && $current_id > 0 && $owner_id === $current_id;
}
```

Both operands must be non-zero, so a resource with no owner (`0`) can never
match a logged-out session (`0`) through a loose comparison.

Administrative capability is separate: `WCP_Security::current_user_can_manage()`
requires `manage_woocommerce` or `manage_options`, and gates the admin-only
notice rendered where the portal is disabled.

## 3. Input handling

The portal reads exactly one request value in Phase 1: the active section.

```php
$value = sanitize_key( (string) $value );
return in_array( $value, $allowed, true ) ? $value : $default_section;
```

It is **whitelisted against known section slugs**, not merely sanitised.
Anything unrecognised collapses to `dashboard`. Because the template chosen
downstream is keyed off that whitelisted value, a crafted URL cannot influence
template resolution.

Verified: `?wcp_section=<script>alert(1)</script>` resolves to `dashboard`,
renders the dashboard, and injects nothing.

`WCP_Helper::locate_template()` independently rejects any path containing `..`,
so even a future caller that passed unsanitised input could not traverse out of
the templates directory.

## 4. Output escaping

Every dynamic value is escaped at the point of output, with the escaper matched
to the context — `esc_html()` for text, `esc_attr()` for attributes,
`esc_url()` for links, and `printf()` with `esc_html__()` for interpolated
translations.

Inline SVG is the one case where escaping cannot be contextual, so icons are
passed through `wp_kses()` against an explicit allowed-element map
(`WCP_Helper::svg_allowed_html()`) that permits geometry attributes only — no
`onload`, no `script`, no `foreignObject`. Icon markup is plugin-authored and
never derived from input; `wp_kses()` is defence in depth, not the primary
control.

`WCP_Security::esc_url()` restricts protocols to `http`, `https` and `mailto`,
which blocks `javascript:` and `data:` URLs from reaching an `href`.

## 5. CSRF

Phase 1 performs no state changes, so there is nothing to forge. The primitives
are in place so that mutating code cannot be written without them:

- `WCP_Security::create_nonce()` / `verify_nonce()` wrap a single action
  constant, so there is no ambiguity about which nonce protects what.
- A nonce is exposed to the front end **only for authenticated sessions**.
  A logged-out response carries an empty string, so a cached anonymous page can
  never bake in a nonce for the next visitor to reuse.

## 6. Direct file access

Every PHP file begins with `defined( 'ABSPATH' ) || exit;`. Directories without
executable content (`rest/`, `admin/views/`, `tests/*`) carry a
silence-is-golden `index.php`.

`uninstall.php` additionally guards on `WP_UNINSTALL_PLUGIN`, so it cannot be
executed outside a genuine uninstall.

## 7. Failing safe

Where behaviour is ambiguous, the plugin chooses the closed state:

- **WooCommerce missing** — the portal does not boot. The shortcode is still
  claimed so visitors never see raw `[wcp_customer_portal]` text, but it renders
  an empty string for everyone except users who hold `manage_woocommerce`.
- **Unsupported PHP/WordPress** — activation is refused outright; runtime boot
  registers an admin notice and nothing else.
- **Account resolution fails after the auth check passes** — for instance
  because a site filtered `wcp_current_user_can_view_portal` — the shortcode
  falls through to the logged-out template rather than rendering a shell with no
  identity behind it.

## 8. Data handling

- No secrets, API keys, tokens or credentials exist anywhere in the codebase.
- No external HTTP requests are made. No analytics, no phone-home, no CDN, no
  webfont, no Gravatar — every asset is served from the plugin directory.
- No customer data is written to `localStorage`, `sessionStorage` or cookies.
  The only client-side state is the colour-scheme preference (`light`/`dark`).
- The plugin persists three options (`wcp_settings`, `wcp_version`,
  `wcp_installed_at`) and short-lived transients (`wcp_rl_{bucket}_{user}`
  rate-limit counters, `wcp_flash_{user}` form feedback,
  `wcp_environment_check`). It stores no user meta and no tables of its own.
- **Deactivation keeps everything**, so settings survive a temporary switch-off.
  **Uninstall** (`uninstall.php`, multisite-aware) removes exactly the items
  above and nothing else: WooCommerce orders, customers, addresses and user
  accounts are never touched. Verified on a fresh install in Phase 6: 13 plugin
  option rows → 0, with order, customer and product counts unchanged.
- No personal data is logged.

## 9. Caching guidance

The logged-out template is cache-safe. The authenticated portal renders account
data and a nonce, so **full-page caching must exclude logged-in users** — the
default behaviour of WP Rocket, LiteSpeed Cache, W3 Total Cache and Batcache,
but worth confirming on any custom edge configuration.

## Rules for later phases

Non-negotiable for every endpoint added from Phase 2 onward:

1. Resolve the customer from `get_current_user_id()`. Never from a parameter.
2. Every REST route declares a `permission_callback` that returns `false` when
   logged out. `__return_true` is never acceptable.
3. Verify ownership with `WCP_Security::owns_resource()` before returning any
   record.
4. Validate and sanitise every argument through the route's `args` schema.
5. Return typed, whitelisted fields — never a raw `WC_Order` or `WP_User`.
6. Every mutating request verifies a nonce, and re-checks the capability
   server-side. A hidden UI control is not an authorisation boundary.
7. Escape at output, in the correct context, without exception.
8. A record belonging to another customer returns the *same* response as one
   that does not exist — same status, same code, same message. Distinguishing
   the two confirms that an ID is real, which is how enumeration starts.

### How Phase 2 satisfies them

| Rule | Where |
| --- | --- |
| 1 | `WCP_Orders::current_customer_id()` → `WCP_Auth::current_customer_id()` → `get_current_user_id()`. The query's `customer_id` is assigned *after* `wcp_orders_query_args` runs, so no filter can widen the scope. |
| 2 | `WCP_REST_Orders::check_permission()` — `401` logged out, `403` without portal access. |
| 3 | `WCP_Orders::is_readable_by_current_user()`, called on the single-order path and on every row of the list. Guest orders have `customer_id` 0, which `owns_resource()` rejects for every session. |
| 4 | Route `args` schemas: `page >= 1`, `per_page` 1–50, `id` a positive integer. `WCP_Orders` clamps again, independently. |
| 5 | `WCP_Orders::to_summary()` / `to_detail()` return flat arrays. No `WC_Order` leaves the repository. |
| 6 | No mutating routes in Phase 2. Nonce helpers remain for Phase 3. |
| 7 | `esc_html`/`esc_url` throughout; WooCommerce's formatted markup passes `wp_kses` with the narrow maps in `WCP_Security` (`allowed_price_html`, `allowed_address_html`, `allowed_meta_html`). |
| 8 | Not-owned and not-found both return `404 wcp_order_not_found` with an identical body. |

**Cross-customer test.** Customer A (`jordan`) authenticated, attempting
Customer B (`taylor`)'s order:

| Attempt | Result |
| --- | --- |
| Portal UI, `?wcp_order=<B's id>` | "Order unavailable" panel; no order markup; zero occurrences of B's name, surname or email in the response |
| `GET /wcp/v1/orders/<B's id>` | `404 wcp_order_not_found` |
| `GET /wcp/v1/orders` | 12 orders, none of them B's |
| Logged out, either route | `401` |
| Logged out, portal UI | Sign-in gate, no order data |
| Cookie request with no `X-WP-Nonce` | `401` |
| Cookie request with a forged nonce | `403 rest_cookie_invalid_nonce` (WordPress) |

### How Phase 3 satisfies them

The first mutating endpoints, so every rule is now load-bearing.

| Rule | Where |
| --- | --- |
| 1 | `WCP_Profile` and `WCP_Addresses` take no user or customer ID in any method. The subject is resolved internally from `WCP_Auth::current_customer_id()`, so there is no parameter to aim at another account. |
| 2 | `WCP_REST_Account::check_read()` / `check_write()` — `401` logged out, `403` without portal access. |
| 3 | Ownership is structural rather than checked: because no method accepts an ID, a write can only ever land on the session's own record. |
| 4 | Route `args` schemas, then a second allowlist inside each model. Profile reads only `WCP_Profile::get_fields()`; addresses read only the keys WooCommerce declared for that type. |
| 5 | Responses are built field by field. No `WP_User` or `WC_Customer` is returned, and no user meta beyond the declared address fields. |
| 6 | Write routes require the portal-scoped `wcp_portal_action` nonce in addition to WordPress's `wp_rest`; the no-JavaScript path verifies the same nonce in `WCP_Form_Handler`. Capability is re-checked server-side in both. |
| 7 | `esc_html` / `esc_attr` / `esc_url` throughout; the formatted address passes `wp_kses` with `allowed_address_html()`. Passwords are never re-rendered into markup and never written to the flash transient. |
| 8 | Unchanged from Phase 2. |

**Mass assignment and privilege escalation.** A single authenticated request
carrying `ID: 1`, `user_id: 3`, `customer_id: 3`, `user_login: admin`,
`role: administrator`, `wp_capabilities: {administrator: true}`,
`user_pass: pwned` and `user_url` alongside a legitimate `first_name`:

| Target | Result |
| --- | --- |
| Customer A's own `first_name` | Changed — the only thing that was |
| Customer A's role | Still `customer` |
| Customer A's password | Unchanged |
| Customer A's `user_url` | Still empty |
| Customer B (user 3) | Untouched: name, email, both addresses |
| Administrator (user 1) | Untouched: login, email, role |
| `shipping_*` sent to the billing route | Not written |
| `POST /addresses/admin` | `404` — never matches a route |

Confirmed both from the database and from Customer B's own authenticated view
of their profile and addresses, which contained no marker from the attempt.

**Nonce enforcement on writes.**

| Request | Result |
| --- | --- |
| Logged out | `401` |
| Valid `wp_rest`, no `X-WCP-Nonce` | `403` |
| Valid `wp_rest`, forged `X-WCP-Nonce` | `403` |
| No `wp_rest` nonce | `401` (WordPress, before the callback) |

### How Phase 4 satisfies them

The dashboard is read-only and adds no endpoint, so the surface is rule 1 and
rule 5.

| Rule | Where |
| --- | --- |
| 1 | `WCP_Dashboard::get_summary()` resolves the customer from `WCP_Auth::current_customer_id()` and takes no parameter. Order data is read through `WCP_Orders`, which already owns the ownership gate, so Phase 4 adds no second path to order records. |
| 5 | Metrics are built field by field from DTOs. The order DTO gained `paid_*` and `completed_*` timestamps — facts about the customer's own order that WooCommerce records — and still carries no `WC_Order`, no internal meta and no private keys. |

**Per-customer scoping, verified end to end.** Three customers' dashboards
rendered and compared:

| | Customer A | Customer B | Customer C |
| --- | --- | --- | --- |
| Total orders | 12 | 1 | *(card not rendered)* |
| Lifetime value | $3,973.65 | $439.45 | *(card not rendered)* |
| Latest order | #34 | #47 | — |
| Recent orders | 4 | 1 | 0 |

A's order numbers appear nowhere in B's markup; C's dashboard contains no
order markup at all; lifetime value differs per customer, so it is not a shared
or global figure. A scan of the rendered dashboard for `_wcp_seed`,
`customer_id`, `user_pass`, `wp_capabilities`, `_edit_lock` and `wc_last_active`
returns nothing.

**The theme preference is not a security boundary and is not treated as one.**
It lives in `localStorage`, is read only to set a presentational attribute, and
every read and write is wrapped in try/catch — `localStorage` throws outright in
some privacy modes, and a colour scheme is never worth breaking a page over.

### How Phase 5 satisfies them

Phase 5 adds no customer-facing endpoint. It adds an administrator surface,
throttling on the existing writes, and a hardening pass over everything before
it.

**Settings.** One option row, one sanitiser, three capability checks.

| Layer | Check |
| --- | --- |
| Menu | `add_submenu_page()` with `manage_woocommerce` — decides who sees the entry |
| Screen | `current_user_can( 'manage_woocommerce' )` before rendering — the URL is reachable without the menu, and a hidden control is not a permission |
| Write | `register_setting()` — `options.php` enforces the capability, the nonce and the referer, then runs `WCP_Settings::sanitize()` |

`WCP_Settings::sanitize()` is the *only* path into the option and handles every
key explicitly. Unknown keys are discarded, not stored. It runs on **read as
well as write**, so a row edited directly in the database cannot put an
unexpected value into circulation. Tested with a crafted POST carrying a CSS
injection as the accent, a `<script>` as the theme, `../../etc/passwd` as the
landing section, a nonexistent page ID and an extra key: the stored row held the
default for each, and the extra key was absent.

`show_in_rest` is `false`. Settings are read only through the plugin's own
accessors, never through `/wp/v2/settings`.

**Disabled sections are enforced, not hidden.** `WCP_Settings::section_enabled()`
is the single question asked by the sidebar, the URL router
(`WCP_Navigation::get_slugs()` returns only enabled sections, so a disabled one
in the URL collapses to the landing section exactly as an invented slug would),
the no-JavaScript form handler, both REST controllers, and the dashboard. The
landing section can never resolve to a disabled one, which is what rules out a
redirect loop.

**Accent colour.** `sanitize_hex_color()` on write; the derived palette is
re-checked against `^#[0-9a-f]{6}$` / `^\d+, \d+, \d+$` at output. Nothing an
administrator types reaches CSS as a string — only colours the plugin derived
from a colour it validated. Button text is chosen by WCAG contrast against both
white and near-black, and the screen reports the ratio.

## Rate limiting

Two buckets on the mutating routes, keyed on the **authenticated user ID**, not
the IP — the endpoints are already authenticated, so the session is the actor,
and an IP key would punish everyone behind one NAT while doing nothing about an
attacker with a session and a proxy pool.

| Bucket | Counts | Limit | Window |
| --- | --- | --- | --- |
| `profile` | every profile write | 30 | 5 min |
| `address` | every address write | 30 | 5 min |
| `password` | **failed** password changes only | 5 | 15 min |

Counting only failures on the password bucket means a customer who legitimately
changes their password twice is never throttled, while someone guessing the
current password runs out of attempts quickly. A successful change clears the
bucket. While the bucket is exhausted **the correct password is refused too** —
there is no finish line to reach by guessing.

The limit lives in the models (`WCP_Profile::update()`,
`WCP_Addresses::update()`), so both transports inherit it. Limiting only the
REST route would leave the no-JavaScript form as a way around it. Responses are
`429` with a `Retry-After` header and a body that names no limit, count or
bucket. Page rendering is never rate limited.

Buckets are filterable (`wcp_rate_limit_buckets`) but clamped to a minimum of
one attempt and ten seconds, so a filter cannot switch limiting off by returning
zero.

**Verified:** 5 wrong passwords → `400` with a field error; the 6th → `429`
with `Retry-After`; a plain name edit still works while the password bucket is
exhausted; the correct password is refused at `429` while throttled; the no-JS
form shows the same message; 31 address writes → `429`, `Retry-After: 299`.

## Endpoint security matrix

Every route from Phases 2–5, audited against the rules above.

| Route | Auth | Permission callback | Ownership | Nonce | Input | Output | Section gate | Rate limit |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /orders` | logged in | `check_permission` | `customer_id` assigned *after* the query filter — cannot be widened | `wp_rest` (WordPress, pre-callback) | `page ≥ 1`, `per_page` 1–50 via schema, clamped again in `WCP_Orders` | DTO; no `WC_Order`; HTML stripped | `orders` | — |
| `GET /orders/{id}` | logged in | `check_permission` | `is_readable_by_current_user()`; not-owned ≡ not-found (`404`) | `wp_rest` | `id` positive integer | DTO | `orders` | — |
| `GET /profile` | logged in | `check_read` | structural: no user ID accepted anywhere | `wp_rest` | — | field list + values, no `WP_User` | `profile` | — |
| `POST /profile` | logged in | `check_write` | structural | `wp_rest` **and** `wcp_portal_action` | allowlisted twice (route args, then model); passwords unsanitised, compared and hashed only | values; never a password | `profile` | `profile` + `password` |
| `GET /addresses` | logged in | `check_read` | structural | `wp_rest` | — | shaped per type | `addresses` | — |
| `GET /addresses/{type}` | logged in | `check_read` | structural | `wp_rest` | `type` by route regex; `country` sanitised and checked against the store's list | WooCommerce field definitions + values | `addresses` | — |
| `POST /addresses/{type}` | logged in | `check_write` | structural | `wp_rest` **and** `wcp_portal_action` | only keys WooCommerce declared for *that* type — billing cannot write `shipping_*` | values + formatted address | `addresses` | `address` |
| `POST options.php` (settings) | logged in | Settings API: `manage_woocommerce` | n/a | `_wpnonce` + referer | `WCP_Settings::sanitize()`, every key explicit | n/a | — | — |

Private WooCommerce metadata: `wc_display_item_meta()` has already removed
hidden (`_`-prefixed) keys before the plugin sees item meta; totals come from
`get_order_item_totals()`; addresses from `get_formatted_*_address()`. A scan of
every rendered screen for `_wcp_seed`, `customer_id`, `user_pass`,
`wp_capabilities`, `_edit_lock` and `wc_last_active` returns nothing.

## Password change

| Requirement | Where |
| --- | --- |
| Current password required and verified | `wp_check_password()` in `WCP_Profile::validate_password_change()` |
| Nonce | `X-WCP-Nonce` on REST; `WCP_Security::verify_nonce()` on the form |
| Authenticated current user, no browser-supplied ID | `WCP_Auth::current_user()`; no method takes an ID |
| Written through WordPress | `wp_update_user()` hashes it and destroys other sessions; the cookie is re-issued for this device only |
| Never logged | No `error_log`, `var_dump`, `print_r` or `console.log` anywhere in plugin source (grep-verified) |
| Never returned | REST responses shaped field by field; seven wrong-password responses scanned for the submitted values: none present |
| Never in the flash | `WCP_Form_Handler::strip_secrets()` removes all three fields before the transient is written |
| Never re-rendered | Password inputs render with an empty `value` on every path |
| Throttled | `password` bucket, failures only, correct password refused while exhausted |

## Content-Security-Policy guidance

The plugin does not set a CSP header and should not: a policy is a property of
the site, and a plugin that sets one globally breaks every other plugin. What
follows is what a site operator needs to know to run the portal under a strict
policy of their own.

**Inline script.** There is exactly one: `#wcp-theme-boot`, printed by
`WCP_Theme::print_head_script()` on portal pages only. It resolves the colour
scheme before first paint, which is the one job a stylesheet cannot do because
the preference lives in `localStorage`. It is static apart from three
interpolations, each a class constant or a value validated against a fixed
list, so its hash is stable per plugin version. `wp_localize_script()` also
prints one `<script>` carrying `wcpPortalConfig` — this is WordPress core
behaviour, shared with most of the ecosystem.

Under `script-src` without `'unsafe-inline'`, either:

- **Nonce.** Hook `wp_head` before priority 1 and filter the tag. The plugin
  prints the script with a fixed `id`; a site's CSP integration can add a
  `nonce` attribute by buffering `wp_head`, or the plugin can be given one via
  the `wcp_theme_script_attributes` filter (see below).
- **Hash.** Compute the SHA-256 of the script body for the installed version and
  add it to `script-src`. The body changes only when the plugin does.

```php
// Give the pre-paint script a CSP nonce.
add_filter( 'wcp_theme_script_attributes', function ( $attrs ) {
    $attrs['nonce'] = my_csp_nonce();
    return $attrs;
} );
```

**Inline style.** A custom accent colour is printed with `wp_add_inline_style()`
as a `<style>` block, and `partials/theme-toggle.php` / the metric cards set
`--wcp-stagger` in `style` attributes for entrance timing. Under `style-src`
without `'unsafe-inline'` these need `'unsafe-inline'` for `style-src-attr` or
a hashed/nonced `<style>`; the stagger attributes are cosmetic and the portal
is fully usable without them.

**No other inline code.** The plugin adds no `onclick`, no `javascript:` URLs,
no `eval`, no `new Function`, and no external hosts — every byte is served from
the plugin directory. `connect-src 'self'` is sufficient for the REST calls.

## Verification performed

| Check | Result |
| --- | --- |
| PHP 8.2 syntax, all files | Pass |
| PHP 7.4 syntax (declared minimum), all files | Pass |
| Front end with WooCommerce deactivated | HTTP 200, no fatal, no shortcode leak, no assets loaded |
| `WP_DEBUG` + `WP_DEBUG_LOG` across all states | No notices, warnings or deprecations |
| `?wcp_section=<script>alert(1)</script>` | Collapses to `dashboard`; no injected node |
| Logged-out render | No customer data in markup |
| Browser console and network, all viewports | No errors, no failed requests |
| Secret scan (keys, tokens, passwords, external hosts) | None found |
