# REST namespace — `wcp/v1`

Reserved for Phase 2 onward. No routes are registered in Phase 1.

Every route added here must, without exception:

1. Resolve the customer from `get_current_user_id()` — never from a request
   parameter, header or cookie.
2. Declare a `permission_callback` that returns `false` for logged-out
   requests. `__return_true` is never acceptable.
3. Verify ownership of the requested resource against the authenticated user
   via `WCP_Security::owns_resource()` before returning anything.
4. Validate and sanitise every argument through the route's `args` schema.
5. Return typed, whitelisted fields — never a raw `WC_Order` or `WP_User`
   object.

See [`docs/SECURITY.md`](../docs/SECURITY.md) and [`docs/API.md`](../docs/API.md).
