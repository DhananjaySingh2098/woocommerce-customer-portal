# API Reference

Two audiences: developers extending the plugin (hooks, templates, shortcode,
PHP surface) and clients of the REST API under `wcp/v1`.

---

## Shortcode

### `[wcp_customer_portal]`

Renders the portal. Logged out, it renders the sign-in state; logged in, the
application shell.

| Attribute | Default | Description |
| --- | --- | --- |
| `section` | `''` | Force a section (`dashboard`, `orders`, `addresses`, `profile`), overriding the `wcp_section` query argument. Unknown values fall back to `dashboard`. |
| `layout` | `full` | `full` lets the portal span the viewport, escaping the theme's content column. `contained` keeps it inside. Unknown values fall back to the `wcp_default_layout` filter. |

```
[wcp_customer_portal]
[wcp_customer_portal section="orders"]
[wcp_customer_portal layout="contained"]
```

Multiple instances on one page are safe: element IDs are suffixed per instance.

**Placement.** No wrapper block or special page template is needed: in `full`
layout the portal spans the viewport by itself, on block and classic themes
alike. Drop the bare shortcode on any page.

If the theme prints a page title above the portal, consider a template that
omits it — the portal supplies its own heading, and two competing titles read
as duplication.

---

## Navigation

Sections are read from `wcp_section`, whitelisted against registered slugs. The
default section uses a clean URL:

```
/customer-portal/                     → dashboard
/customer-portal/?wcp_section=orders  → orders
```

---

## Filters

### `wcp_navigation_items`

Add, remove or reorder sidebar sections.

```php
add_filter( 'wcp_navigation_items', function ( $items ) {
    $items[] = array(
        'slug'        => 'support',
        'label'       => __( 'Support', 'my-plugin' ),
        'title'       => __( 'Support', 'my-plugin' ),
        'subtitle'    => __( 'Get help with your order.', 'my-plugin' ),
        'icon'        => 'mail',           // A bundled icon name.
        'status'      => 'upcoming',       // 'ready' | 'upcoming'
        'endpoint'    => '',               // WooCommerce My Account endpoint.
        'description' => __( 'Talk to our team.', 'my-plugin' ),
        // Benefit framing, shown on the logged-out screen. Leave the title
        // empty to omit the section from that list.
        'benefit_title' => __( 'Get help fast', 'my-plugin' ),
        'benefit_text'  => __( 'Reach a human whenever you need one.', 'my-plugin' ),
    );
    return $items;
} );
```

Every key is required. `status` of `upcoming` renders the placeholder body and
links to `endpoint` when one is given.

### `wcp_default_layout`

Change the default layout mode for every portal instance.

```php
add_filter( 'wcp_default_layout', function () {
    return 'contained';
} );
```

### `wcp_full_width_classes`

Theme-level classes added to the portal root in `full` layout. Defaults to
`alignfull` for any theme that understands wide alignment -- every block theme,
plus classic themes that call `add_theme_support( 'align-wide' )`. For themes
that support neither, it returns an empty string and the script's measured
fallback takes over instead.

```php
add_filter( 'wcp_full_width_classes', function ( $classes ) {
    return 'full-bleed';   // A classic theme's own full-width class.
} );
```

### `wcp_suppress_page_title`

Whether the theme's own page title and its surrounding framing are suppressed
on the page hosting the portal. True by default in `full` layout only; a
`contained` portal always keeps the theme's title.

Return `false` on a page that mixes the portal with editorial content of its
own, where the page title still earns its place.

```php
add_filter( 'wcp_suppress_page_title', function ( $suppress, $post ) {
    return 'account' === $post->post_name ? $suppress : false;
}, 10, 2 );
```

### `wcp_classic_title_selectors`

The CSS selectors used to hide a classic theme's page title. Block themes never
reach this filter -- their title block is removed in PHP via `render_block`, so
no CSS is involved.

The stylesheet is printed only on the portal page itself, and every selector is
prefixed with the `wcp-portal-chromeless` body class. Keep that prefix on
anything you add, or the rule will escape onto other pages.

```php
add_filter( 'wcp_classic_title_selectors', function ( $selectors ) {
    $selectors[] = '.wcp-portal-chromeless .my-theme__page-heading';
    return $selectors;
} );
```

### `wcp_suppress_theme_footer`

Whether the theme's own footer is suppressed on the page hosting the portal.
Tracks `wcp_suppress_page_title` by default, so it is true in `full` layout only
and a `contained` portal always keeps the theme footer.

The portal prints an application footer of its own, so a theme footer under a
full-width portal is a second ending. Return `false` when the theme footer
carries something the portal does not -- a cookie notice, a legally required
disclosure.

```php
add_filter( 'wcp_suppress_theme_footer', function ( $suppress, $post ) {
    return false;   // Keep the theme footer under the portal.
}, 10, 2 );
```

### `wcp_theme_footer_selectors`

The CSS selectors used to hide a theme footer that survived `pre_render_block` --
a classic theme's `get_footer()`, or a block theme that builds its ending out of
ordinary blocks rather than a footer template part.

The stylesheet is printed only on the portal page itself, and every selector is
prefixed with the `wcp-portal-standalone` body class. Keep that prefix on
anything you add, or the rule will escape onto other pages. The portal's own
ending is a `<footer>` as well, so never add a bare `footer` selector -- name the
theme's footer specifically, and keep `:not(.wcp-footer)` on anything generic.

```php
add_filter( 'wcp_theme_footer_selectors', function ( $selectors ) {
    $selectors[] = '.wcp-portal-standalone .my-theme__site-end';
    return $selectors;
} );
```

### `wcp_orders_query_args`

Query arguments for the orders list, before customer scoping is applied.

Customer scoping is applied *after* this filter and cannot be overridden, so a
filter may narrow the result set but never widen it.

```php
add_filter( 'wcp_orders_query_args', function ( $args, $customer_id ) {
    $args['status'] = array( 'wc-completed' );   // Completed orders only.
    return $args;
}, 10, 2 );
```

### `wcp_order_status_tone`

Visual tone for an order status: `success`, `info`, `warning`, `danger` or
`neutral`. Anything else falls back to `neutral`, so a typo cannot inject a
class name.

Use it to colour a status your store registered itself.

```php
add_filter( 'wcp_order_status_tone', function ( $tone, $status ) {
    return 'awaiting-stock' === $status ? 'warning' : $tone;
}, 10, 2 );
```

### `wcp_profile_fields`

The editable profile fields. This list is the allowlist: removing a field also
removes the ability to write it.

Adding one does not grant write access on its own — `WCP_Profile` only knows how
to persist the built-in fields.

```php
add_filter( 'wcp_profile_fields', function ( $fields ) {
    unset( $fields['display_name'] );   // Not editable in the portal.
    return $fields;
} );
```

### `wcp_address_updated`

Action fired after a customer saves one of their own addresses.

```php
add_action( 'wcp_address_updated', function ( $type, $values, $customer ) {
    // $customer is always the authenticated one.
}, 10, 3 );
```

### `wcp_rate_limit_buckets`

The throttle buckets on mutating routes. Values are clamped to a minimum of one
attempt and ten seconds, so a filter can loosen or tighten limiting but cannot
switch it off.

```php
add_filter( 'wcp_rate_limit_buckets', function ( $buckets ) {
    $buckets['password']['limit'] = 3;   // Stricter password guessing limit.
    return $buckets;
} );
```

### `wcp_theme_script_attributes`

Attributes on the pre-paint theme `<script>` tag, for sites running a strict
Content-Security-Policy. See [`SECURITY.md`](SECURITY.md#content-security-policy-guidance).

```php
add_filter( 'wcp_theme_script_attributes', function ( $attrs ) {
    $attrs['nonce'] = my_csp_nonce();
    return $attrs;
} );
```

### `wcp_current_user_can_view_portal`

Gate portal access. Defaults to `is_user_logged_in()`.

```php
add_filter( 'wcp_current_user_can_view_portal', function ( $can_view ) {
    return $can_view && current_user_can( 'read' );
} );
```

Returning `true` for a logged-out visitor does **not** expose data: the
shortcode independently requires a resolvable account and falls back to the
logged-out template.

### `wcp_page_has_portal`

Force asset loading where a content scan cannot detect the shortcode — page
builders, widgets, template parts.

```php
add_filter( 'wcp_page_has_portal', function ( $has_portal, $post ) {
    return $has_portal || is_page( 'my-account-hub' );
}, 10, 2 );
```

### `wcp_admin_asset_screens`

Admin screens that load plugin assets. Defaults to `[ 'plugins.php' ]`.

---

## Actions

### `wcp_loaded`

Fires once the plugin has booted with WooCommerce available. Receives the
`WCP_Loader` instance.

```php
add_action( 'wcp_loaded', function ( $loader ) {
    $navigation = $loader->get( 'navigation' );
} );
```

Never fires when WooCommerce is missing — a safe hook for dependent code.

---

## Template overrides

Copy any template into your theme under `woocommerce-customer-portal/`:

```
your-theme/woocommerce-customer-portal/portal.php
your-theme/woocommerce-customer-portal/dashboard.php
your-theme/woocommerce-customer-portal/login-required.php
your-theme/woocommerce-customer-portal/orders.php
your-theme/woocommerce-customer-portal/order-detail.php
your-theme/woocommerce-customer-portal/partials/sidebar.php
your-theme/woocommerce-customer-portal/partials/header.php
your-theme/woocommerce-customer-portal/partials/section-upcoming.php
your-theme/woocommerce-customer-portal/partials/order-status.php
your-theme/woocommerce-customer-portal/partials/orders-skeleton.php
your-theme/woocommerce-customer-portal/partials/order-error.php
your-theme/woocommerce-customer-portal/profile.php
your-theme/woocommerce-customer-portal/addresses.php
your-theme/woocommerce-customer-portal/address-edit.php
your-theme/woocommerce-customer-portal/partials/field.php
your-theme/woocommerce-customer-portal/partials/form-feedback.php
your-theme/woocommerce-customer-portal/partials/theme-toggle.php
your-theme/woocommerce-customer-portal/partials/dashboard-skeleton.php
```

Child themes take precedence over parent themes, which take precedence over the
plugin. Templates receive a single `$wcp` array; see each file's docblock.

---

## Styling

Override design tokens without touching the stylesheet:

```css
.wcp-portal {
    --wcp-primary: #0f766e;
    --wcp-primary-hover: #0d6259;
    --wcp-primary-soft: #e6f4f1;
    --wcp-primary-rgb: 15, 118, 110;
    --wcp-radius-lg: 12px;
    --wcp-content-max: 1240px;
    --wcp-gate-max: 1280px;      /* Width of the logged-out hero. */
    --wcp-grid-line: transparent; /* Drop the background grid entirely. */
    --wcp-sticky-offset: 64px;   /* Clear a theme's fixed header. */
}
```

`--wcp-sticky-offset` is the one most sites will want: it pushes the portal's
sticky header and sidebar below a theme's own fixed header.

See [`DESIGN_SYSTEM.md`](DESIGN_SYSTEM.md) for the full token list.

---

## PHP surface

Public methods that later phases and integrations may rely on.

| Class | Method | Returns |
| --- | --- | --- |
| `WCP_Auth` | `is_logged_in()` | `bool` |
| | `current_customer_id()` | `int` — the only source of customer identity |
| | `current_user()` | `WP_User\|null` |
| | `can_view_portal()` | `bool` |
| | `get_my_account_url()` | `string` |
| | `get_account_endpoint_url( $endpoint )` | `string` |
| | `get_login_url( $redirect_to = '' )` | `string` |
| | `get_logout_url( $redirect_to = '' )` | `string` |
| `WCP_Account` | `for_current_user()` | `WCP_Account\|null` — the only constructor |
| | `get_id()` / `get_email()` / `get_display_name()` | `int` / `string` / `string` |
| | `get_greeting_name()` / `get_initials()` | `string` |
| | `get_member_since()` | `string` — formatted, or `''` |
| `WCP_Security` | `sanitize_section( $value, $allowed, $default )` | `string` |
| | `create_nonce()` / `verify_nonce( $nonce = null )` | `string` / `bool` |
| | `owns_resource( $owner_id )` | `bool` |
| | `current_user_can_view_portal()` / `current_user_can_manage()` | `bool` |
| `WCP_Navigation` | `get_items()` / `get_slugs()` | `array` / `string[]` |
| | `get_item( $slug )` | `array\|null` |
| | `get_current_section( $requested = null )` | `string` |
| | `get_section_url( $slug, $base_url )` | `string` |
| `WCP_Orders` | `get_orders( $args )` | `array` — page of the current customer's orders |
| | `get_order( $id )` | `array\|WP_Error` — ownership-checked |
| | `is_readable_by_current_user( $order )` | `bool` — the Phase 2 ownership gate |
| `WCP_Order_Status` | `describe( $status )` | `array` — slug, label, tone, class |
| | `timeline( $status )` | `array` — derivable progress steps, or empty |
| `WCP_Settings` | `get( $key )` / `all()` | `mixed` / `array` — validated on read |
| | `is_enabled()` / `section_enabled( $slug )` | `bool` — the single source of truth |
| | `enabled_sections()` / `default_section()` | `string[]` / `string` — never a disabled section |
| | `accent_palette( $dark )` | `array` — derived CSS custom properties |
| `WCP_Rate_Limit` | `check( $action )` | `true\|WP_Error` — 429 with `retry_after` |
| | `record( $action )` / `clear( $action )` | `void` |
| `WCP_Dashboard` | `get_summary()` | `array` — metrics, recent orders, activity |
| `WCP_Theme` | `get_config()` | `array` — storage key and attribute name |
| `WCP_Profile` | `get_fields()` / `get_values()` | `array` — the allowlist, and current values |
| | `update( $input )` | `array\|WP_Error` — typed field errors |
| `WCP_Addresses` | `get_types()` / `sanitize_type( $t )` | `string[]` / `string` |
| | `get_fields( $type, $country )` | `array` — from WooCommerce, in priority order |
| | `get_values( $type )` / `get_form_values( $type )` | `array` |
| | `get_formatted( $type )` | `string` — WooCommerce's country-aware rendering |
| | `has_address( $type )` | `bool` |
| | `update( $type, $input )` | `array\|WP_Error` |
| `WCP_Page_Layout` | `has_portal()` | `bool` — does the queried page host the portal |
| | `get_layout()` / `is_full_width()` | `string` / `bool` |
| | `should_suppress_title()` | `bool` — is the theme's title being hidden |
| `WCP_Shortcodes` | `normalize_layout( $requested )` | `string` — `full` or `contained` |
| `WCP_Helper` | `render_template( $template, $data )` | `string` |
| | `locate_template( $template )` | `string` |
| | `icon( $name, $args )` | `string` — `wp_kses`-sanitised SVG |
| | `initials( $name )` | `string` |
| | `full_width_classes()` | `string` — theme classes for full-bleed |
| | `class_names( $map )` | `string` |

---

## Settings

Stored as one option, `wcp_settings`, read only through `WCP_Settings`. Never
exposed via `/wp/v2/settings`.

| Key | Type | Default | Validation |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | — |
| `portal_page` | int | `0` (detect) | must be a published page, else `0` |
| `sections` | string[] | all four | known slugs only; empty selection restores all |
| `default_section` | string | `dashboard` | must be enabled, else Dashboard, else first enabled |
| `accent` | string | `#5b4ce0` | `sanitize_hex_color()`, else default |
| `theme` | string | `system` | `system` \| `light` \| `dark` |

**Theme precedence**, highest first: the customer's stored choice → the
administrator's default (when `light` or `dark`) → the operating system.

## REST surface

Namespace `wcp/v1`. Registered on `rest_api_init`.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/orders` | Current customer's orders, paginated |
| `GET` | `/orders/{id}` | Single order, ownership-checked |
| `GET` | `/profile` | Editable profile fields and values |
| `POST` | `/profile` | Update profile, including password |
| `GET` | `/addresses` | Both addresses |
| `GET` | `/addresses/{type}` | One address; `?country=` returns that country's field set |
| `POST` | `/addresses/{type}` | Update one address |

The dashboard has no REST route of its own: its figures are rendered
server-side from the same models, and there is no client that needs them as
JSON. Every route satisfies the rules in
[`SECURITY.md`](SECURITY.md#rules-for-later-phases) and
[`rest/README.md`](../rest/README.md).

### Authentication

For a browser these routes are cookie-authenticated, and WordPress will not
resolve a cookie into a logged-in user unless the request carries a valid
`wp_rest` nonce in the `X-WP-Nonce` header. That happens in
`rest_cookie_check_errors()`, *before* any permission callback runs, so a
nonce-less request arrives as a logged-out visitor and is rejected with `401`.

The nonce and the route base are published to the page for exactly this
purpose, and only to authenticated sessions:

```js
const { restUrl, restNonce } = window.wcpPortalConfig;

const res = await fetch( restUrl + 'orders?page=2', {
    credentials: 'same-origin',
    headers: { 'X-WP-Nonce': restNonce },
} );
```

For **read** routes the permission callback deliberately does *not* re-check the
nonce. It would add nothing over what WordPress already enforces, and it would
break the auth schemes that legitimately carry no nonce, such as application
passwords.

**Write routes are different.** `wp_rest` is a single nonce covering the entire
REST API, so anything able to obtain one can reach every route on the site. The
mutating routes therefore require a *second*, portal-scoped nonce
(`wcp_portal_action`) in `X-WCP-Nonce`, checked explicitly in
`WCP_REST_Account::check_write()`. Holding a general REST nonce is not enough to
change someone's email address.

```js
const { restUrl, restNonce, nonce } = window.wcpPortalConfig;

await fetch( restUrl + 'addresses/billing', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': restNonce,   // WordPress: authenticates the cookie
        'X-WCP-Nonce': nonce,      // Portal: authorises the mutation
    },
    body: JSON.stringify( { billing_city: 'Austin' } ),
} );
```

Missing `X-WP-Nonce` gives `401` (WordPress never resolves the cookie); missing
or forged `X-WCP-Nonce` gives `403`.

### `GET /wcp/v1/orders`

| Parameter | Type | Default | Bounds |
| --- | --- | --- | --- |
| `page` | integer | `1` | `>= 1` |
| `per_page` | integer | `10` | `1`–`50` |

Out-of-range values are rejected with `400` by the route schema rather than
silently clamped, so a caller always knows what it got. `WCP_Orders` clamps
again independently — the schema is the courtesy, the repository is the
protection.

```json
{
  "orders": [
    {
      "id": 34,
      "number": "34",
      "date": "2026-09-07T10:14:22+00:00",
      "date_label": "September 7, 2026",
      "status": { "slug": "processing", "label": "Processing", "tone": "info" },
      "item_count": 3,
      "items_label": "3 items",
      "items_teaser": "Aster Walnut Desk, Halden Task Lamp",
      "total": "$1,013.95",
      "currency": "USD"
    }
  ],
  "pagination": {
    "page": 1, "per_page": 10, "total": 12, "total_pages": 2,
    "has_prev": false, "has_next": true
  }
}
```

`X-WP-Total` and `X-WP-TotalPages` carry the same counts in headers.

### `GET /wcp/v1/orders/{id}`

Adds `timeline`, `items`, `totals`, `billing`, `shipping`, `payment_method`,
`shipping_method`, `customer_note` and `downloads`.

Values are plain text here, while the templates receive WooCommerce's formatted
markup. Both come from the same DTO in `WCP_Orders`, so the two consumers can
differ in presentation but never in content.

### `POST /wcp/v1/profile`

Accepts `first_name`, `last_name`, `display_name`, `user_email`, and the
password trio `current_password` / `new_password` / `confirm_password`.

**Nothing else is read.** The accepted set is derived from
`WCP_Profile::get_fields()`, and `WCP_Profile::update()` allowlists against it
again — so `ID`, `role`, `wp_capabilities`, `user_login` and `user_pass` in a
payload are discarded rather than applied. Verified: a request carrying all five
returns `200` and changes only the declared fields on the session's own account.

A password change requires the current password, verified with
`wp_check_password()`. The new one is written by `wp_update_user()`, which
hashes it and destroys every session for the user; the portal then re-issues the
cookie for the device that made the change, so that one stays signed in and the
rest are signed out.

```json
{ "saved": true, "values": { "first_name": "Jordan", "…": "…" },
  "message": "Profile updated." }
```

### `GET|POST /wcp/v1/addresses/{type}`

`type` is `billing` or `shipping`, enforced by the route regex — anything else
never matches a route.

The field set comes from `WC()->countries->get_address_fields()`, which is also
the allowlist: only keys WooCommerce declared for *that* type are read, so the
billing route cannot write `shipping_*` and vice versa. `GET` accepts
`?country=XX` and returns the field set for that country, which is how the form
asks what changes when the customer picks a different one.

```json
{ "type": "billing", "label": "Billing address",
  "fields": [ { "key": "billing_state", "type": "select", "required": true,
                "options": { "TX": "Texas" } } ],
  "values": { "billing_city": "Austin" } }
```

### Validation errors

Both write routes return `400` with a `fields` map, which is what lets the
browser and the no-JavaScript form path render identical per-field messages:

```json
{ "code": "wcp_invalid_address",
  "message": "Please correct the highlighted fields.",
  "data": { "status": 400,
            "fields": { "billing_postcode": "That postcode is not valid for the selected country." } } }
```

### Errors

`WP_Error` with WordPress's standard status mapping: `401` unauthenticated,
`400` validation failure, `403` section disabled or portal-nonce failure, `404`
not found, `429` rate limited (with a `Retry-After` header).

**An order belonging to another customer returns `404`, not `403`** — the same
code, message and body as an ID that does not exist. This reverses the Phase 1
plan, deliberately: `403` confirms that an ID is real, which is precisely the
signal an attacker needs to enumerate orders. The customer-facing cost is nil,
because a customer has no legitimate way to reach another customer's order in
the first place.

```json
{ "code": "wcp_order_not_found",
  "message": "That order could not be found.",
  "data": { "status": 404 } }
```
