# Tests

The suite runs on the **real WordPress test framework** against a **real
WooCommerce install**. Nothing is mocked: orders are created with
`wc_create_order()`, customers with `wp_insert_user()`, and REST routes are
dispatched through `WP_REST_Server`. 198 tests, 767 assertions.

## Layout

```
tests/
├── bootstrap.php                 Loads WooCommerce, installs its tables, then the plugin
├── wp-tests-config.php           Environment-driven; contains no credentials
├── phpstan-bootstrap.php         Constants for static analysis only
├── includes/
│   ├── class-wcp-test-case.php   Base case: fixtures, login, settings, rendering helpers
│   └── class-wcp-rest-test-case.php  Adds a REST server and a nonce-aware request helper
├── unit/          73 tests   Settings, rate limiting, order status, addresses, profile, navigation
├── integration/   55 tests   Shortcode, sections, every REST route, admin screen, no-JS forms
├── security/      34 tests   Cross-customer isolation and write protection (CI gate)
└── degraded/       7 tests   Boot with WooCommerce absent
```

`phpunit.xml.dist` runs `unit`, `integration` and `security`.
`phpunit-degraded.xml.dist` runs `degraded` in a separate process with
`WCP_TESTS_WITHOUT_WC=1`, because WooCommerce cannot be unloaded once
required.

## What the security suite guarantees

These tests are a **required CI check**. A failure blocks merge.

- Customer A requesting customer B's order — by ID, through the REST API and
  through the page URL — receives the same `404` body as for an order that
  does not exist, with no field of B's order in the response.
- A's dashboard, profile, billing and shipping never contain B's data, and A's
  writes never change B's records.
- Guest (customer ID 0) orders are invisible to every logged-in customer.
- Logged-out writes are rejected (`401`); writes missing either nonce, or
  carrying an invalid one, are rejected (`403`).
- Settings cannot be saved without `manage_woocommerce`.
- Invalid, negative, fractional and non-numeric order IDs, unknown address
  types, path-traversal section names and oversized/malformed field values are
  all rejected without a PHP notice.
- A disabled section is refused by the REST route and the form handler, not
  just hidden from navigation.
- Rate limits are enforced per customer: exhausting A's bucket leaves B
  unaffected; the no-JavaScript form path shares the bucket with REST; `429`
  responses carry `Retry-After` and reveal no limit or counter.
- Password change: wrong current password fails and is counted; a successful
  change is not counted; the password never appears in any response.

## Running the suite

Requirements: PHP 7.4+, Composer, a MySQL/MariaDB server, a WordPress core
directory and a WooCommerce plugin directory. The database named in the
configuration is **destroyed on every run** — never point it at a real site.

```sh
composer install

export WP_CORE_DIR=/path/to/wordpress            # ABSPATH
export WC_PLUGIN_DIR=$WP_CORE_DIR/wp-content/plugins/woocommerce
export WP_TESTS_DB_NAME=wordpress_test
export WP_TESTS_DB_USER=root
export WP_TESTS_DB_PASSWORD=root
export WP_TESTS_DB_HOST=127.0.0.1

composer test              # unit + integration + security
composer test:security     # the gate alone
composer test:degraded     # WooCommerce absent
composer test:all          # everything
```

`WP_TESTS_DIR` can point at a checkout of the WordPress test library that
matches the core version under test (CI does this for the compatibility
matrix); by default the bundled `wp-phpunit/wp-phpunit` package is used.

### Docker

The plugin's own development used a throwaway `wordpress` + `mariadb` pair
with the plugin bind-mounted into `wp-content/plugins/`. Any container that
can see the core directory, the WooCommerce directory and the database works;
pass the paths through the environment variables above.

## Conventions

- Every test asserts behaviour a user or attacker could observe. There are no
  placeholder tests.
- Fixtures are created per test through `WCP_Test_Case` helpers and rolled
  back by the framework's transaction handling; no fixture SQL is committed.
- The base case resets settings, the current user, rate-limit transients and
  the asset-enqueue state before each test, so order of execution cannot leak
  state between tests.
- Fixture passwords (`Test-Pass-1!`, `Original-Pass-1!`) are throwaway values
  for users that exist only inside a rolled-back test transaction. They are
  not credentials for any real system and never appear in the plugin or the
  release ZIP.
