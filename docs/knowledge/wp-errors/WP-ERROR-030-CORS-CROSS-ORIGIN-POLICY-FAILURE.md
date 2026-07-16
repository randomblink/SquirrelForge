# WP-ERROR-030 — WordPress CORS (Cross-Origin) Policy Failure

---

# 1. Knowledge Entry

WordPress CORS (Cross-Origin) Policy Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-030`
* **Title:** WordPress CORS (Cross-Origin) Policy Failure
* **Category:** Networking
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A browser refuses to expose a WordPress-served response to the calling script because the response's own CORS (Cross-Origin Resource Sharing) headers do not permit the requesting origin, HTTP method, or request headers. **CORS is enforced entirely by the browser, not by WordPress.** WordPress's own role is limited to emitting — or failing to emit — the specific `Access-Control-*` response headers a browser's own cross-origin policy engine evaluates; WordPress itself never rejects or blocks a request on the basis of its origin — the request's own handler executes identically regardless — though WordPress's own header-emission logic does read the `Origin` request header as an input to deciding what, if anything, to send back. The observable failure occurs client-side, after WordPress has already done whatever it was going to do.

---

# 4. Primary Failure Mode

A script running on one origin (a browser tab loaded from `https://a.example`) issues a cross-origin request to a WordPress site on a different origin (`https://b.example`). WordPress's own server-side code processes the request — completing successfully or not, on its own terms, entirely independent of this entry's own condition — and returns a response. The browser then independently evaluates that response's own CORS headers (or a preceding preflight `OPTIONS` request's own headers, where one was required) against its own cross-origin policy rules, and refuses to make the response body available to the calling script when the headers do not explicitly permit the requesting origin, method, or headers.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on what the blocked cross-origin access was for:

- Where a single, non-essential cross-origin integration is affected (an occasional external script reading site data), the impact is typically narrow.
- Where a headless or decoupled front end depends entirely on cross-origin access to WordPress's own REST API — the front end *is* a separate origin from WordPress by design — a CORS misconfiguration can mean the entire front end fails to load any data at all, a full-site outage for that architecture even though WordPress's own server-side processing is functioning perfectly.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`029`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that the browser itself refused to expose an otherwise-obtained response, due to its own cross-origin policy evaluation — not that WordPress's own request processing failed, was denied, or never completed for a reason unrelated to cross-origin headers.

**WordPress is in the server role for this entry — a reversal from `WP-ERROR-028`/`029`'s own client role.** Those two entries own WordPress's own outbound requests, as a client, to other hosts. This entry owns a browser's own evaluation of a response *WordPress itself served*, as a server, to a cross-origin request from that browser. The direction of communication is opposite; the underlying network connection and TLS negotiation (if any) between the browser and WordPress are not this entry's own concern at all — they are the browser's own connection to WordPress, not WordPress's own connection to anything, and are outside this category's scope in either direction once established.

**The relationship with `WP-ERROR-022` (REST API Access Denied) runs in both directions, and both directions matter:**

- A REST endpoint can return `200 OK`, complete its own business logic correctly, and still be entirely unusable to the calling script — because the browser blocks access to that successful response due to a missing or incorrect CORS header. `WP-ERROR-022`'s own condition did not occur; this entry's did.
- Conversely, a perfectly configured CORS policy does not override, bypass, or excuse an authentication or authorization failure. A cross-origin request that is correctly permitted by CORS can still be rejected by WordPress's own `permission_callback` or authentication check — `WP-ERROR-022`'s own condition, entirely independent of whether CORS headers were correct.

CORS and REST authentication/authorization are two entirely independent gates, evaluated by two entirely different parties (the browser, and WordPress's own server-side code, respectively), and neither one's outcome implies anything about the other's.

It is distinct from:

- **`WP-ERROR-022` — WordPress REST API Access Denied**: owns authentication and authorization failures within REST request handling, evaluated server-side by WordPress. See the two-directional relationship above.
- **`WP-ERROR-028` — WordPress Outbound HTTP Request Failure** and **`WP-ERROR-029` — WordPress Outbound TLS Negotiation Failure**: own WordPress's own outbound requests as a client. This entry presumes the browser's own connection to WordPress (as the server) already succeeded — if the browser could never reach WordPress at all, or TLS to WordPress itself failed, the browser would never receive any response to evaluate CORS headers on in the first place, and the condition would not be diagnosable as a CORS failure by the browser's own tooling.
- **A server-side HTTP client failure** — WordPress, as a client, failing to reach some *other* service — is `WP-ERROR-028`/`029`'s own territory, not this entry's; this entry concerns only WordPress being reached, as a server, by a browser.
- **A generic JavaScript error unrelated to cross-origin enforcement** (a syntax error, an unhandled promise rejection, a logic defect in the calling script itself) — not a condition of WordPress's own CORS header emission at all, and outside this catalog's scope entirely.

---

# 7. Scope

**Covered:** A verified condition in which a browser refuses to expose a WordPress-served response (most commonly, though not exclusively, a REST API response — this condition can arise for any WordPress-served endpoint a cross-origin script requests) to the requesting script, because the response's own `Access-Control-*` headers — or a preceding preflight `OPTIONS` response's own headers — do not permit the requesting origin, HTTP method, or request headers.

**Excluded:**

- REST API authentication failures (`WP-ERROR-022`).
- REST API authorization/capability-based denial (`WP-ERROR-022`).
- Network connectivity failures between the browser and WordPress, or in any outbound request WordPress itself separately makes as a client (`WP-ERROR-028`).
- TLS negotiation failures, in either direction (`WP-ERROR-029`).
- A server-side HTTP client failure — WordPress itself failing to reach a different, third-party service as a client (`WP-ERROR-028`/`029`).
- A generic JavaScript error in the calling script, unrelated to cross-origin header enforcement.
- An HTTP-level error status WordPress's own endpoint genuinely returns (a `404`, `500`) — if the browser's own CORS evaluation would have permitted access, the calling script receives and can inspect that error response normally; this entry's own condition is specifically that the script *cannot see the response at all*, regardless of what status code it carried.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `rest_send_cors_headers()` (`wp-includes/rest-api.php`), WordPress core's own default CORS handling for the REST API, hooked to the `rest_pre_serve_request` filter, which sends `Access-Control-Allow-Origin` reflecting the requesting origin only where `is_allowed_http_origin()` permits it — core's own default behavior does not send a wildcard `Access-Control-Allow-Origin: *` for authenticated contexts, and does not permit arbitrary origins by default.
- `get_http_origin()` and `is_allowed_http_origin()`, the functions determining which origin, if any, a given request is permitted to receive `Access-Control-Allow-Origin` for, and the `allowed_http_origins`/`http_origin` filters plugins commonly use to extend or override this list.
- The `rest_pre_serve_request` filter more broadly, through which a plugin or theme may add, remove, or replace CORS-related headers for REST responses.
- Preflight `OPTIONS` request handling within `WP_REST_Server`, which WordPress's own REST routing answers directly for a preflighted cross-origin request (one using a non-simple method, a custom header such as `X-WP-Nonce`, or certain content types), independent of whatever the corresponding real request's own route or callback would do.
- `Access-Control-Allow-Origin`, `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers`, and `Access-Control-Allow-Credentials`, the specific response headers a browser's own CORS policy engine evaluates.
- Non-REST cross-origin scenarios (a custom `admin-ajax.php` endpoint, or a non-REST custom endpoint a theme or plugin implements) rely entirely on that specific code's own, independent header-emission logic — WordPress core's own `rest_send_cors_headers()` default handling does not apply outside the REST API.

---

# 9. Typical Symptoms

- A browser console error reading approximately "has been blocked by CORS policy: No 'Access-Control-Allow-Origin' header is present on the requested resource," or "...header contains the invalid value," or "...contains multiple values."
- A cross-origin `fetch()` or `XMLHttpRequest` call's own promise rejects, or its error handler fires, with no meaningful response body available to inspect — as distinct from the request completing and returning an HTTP error status the calling script *can* read.
- A preflight `OPTIONS` request visibly succeeds (or fails) in the browser's own network inspector, while the intended real request is never sent at all, or is sent and then has its response blocked — the two are distinguishable in browser developer tools and diagnosis should not conflate them.
- The identical request succeeds when made from the same origin as WordPress itself (no cross-origin boundary crossed), isolating the condition specifically to cross-origin access rather than to the endpoint's own general functioning.
- A decoupled or headless front end fails to load any data from WordPress's own REST API at all, while WordPress's own `wp-admin` and direct REST API browser testing (same-origin, or via a tool that does not enforce CORS) continue to work normally.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a cause as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- The requesting origin is not included in WordPress's own allowed-origins logic at all — no `Access-Control-Allow-Origin` header is sent for that origin, whether because no CORS customization exists beyond WordPress core's own restrictive default, or because a plugin's own allowed-origins list is incomplete.
- The request uses an HTTP method (`PUT`, `DELETE`, `PATCH`) not included in the `Access-Control-Allow-Methods` header of a preflight response, causing the preflight itself to fail and the real request to never be sent.
- The request includes a custom header (commonly `X-WP-Nonce`, or a custom `Content-Type` such as `application/json`) not included in `Access-Control-Allow-Headers`, with the same preflight-failure effect.
- WordPress's own OPTIONS preflight handling is not correctly reached at all — a server-level rewrite rule, a security plugin, or a caching layer intercepts and mishandles the `OPTIONS` method before WordPress's own REST routing ever processes it.
- The request is made with credentials (`fetch(..., { credentials: 'include' })`, or an equivalent cookie-carrying cross-origin request), but the response's own `Access-Control-Allow-Origin` is a wildcard (`*`) rather than the specific requesting origin, or `Access-Control-Allow-Credentials: true` is missing — browsers reject the credentialed-wildcard combination categorically, regardless of any other header being otherwise correct.
- More than one mechanism emits CORS headers for the same response (WordPress core's own default handling and a plugin's own additional logic both firing), producing duplicate or conflicting `Access-Control-Allow-Origin` values a browser rejects as invalid, even where either header alone would have been acceptable.
- A caching layer or CDN caches a response's CORS headers as computed for one origin and serves that same cached response — headers included — to a request from a different origin, for which those headers are no longer correct.
- The requesting origin genuinely is not intended to have cross-origin access — a correctly-functioning security boundary, not a defect, easily mistaken for one during initial integration development.

---

# 11. Diagnosis

Verify the following, distinguishing a preflight failure from a same-origin-versus-cross-origin distinction from an actual-response blockage before assuming a specific cause:

1. Confirm, using the browser's own developer tools (Network tab), the exact CORS-related error message and which specific request it applies to — the preflight `OPTIONS` request, or the actual `GET`/`POST`/etc. request that followed it.
2. Confirm whether the request required a preflight at all (a non-simple method, a custom header, or certain content types trigger one; an ordinary `GET` with no custom headers typically does not) — a preflight failure and a same-request-response failure have different specific header requirements to check.
3. Where a preflight occurred, inspect its own response headers directly (`Access-Control-Allow-Origin`, `-Methods`, `-Headers`) against what the real request actually needs, rather than assuming the preflight's own success or failure from the real request's own outcome alone.
4. Where no preflight was involved, or the preflight succeeded, inspect the actual response's own `Access-Control-Allow-Origin` (and `-Credentials`, if the request carries credentials) header directly, confirming it matches the exact requesting origin or is otherwise correctly configured for the request's own credential mode.
5. Confirm whether the request is genuinely cross-origin in the first place — test the identical request from the same origin as WordPress itself to isolate whether the condition is specific to the cross-origin boundary or a broader endpoint problem unrelated to CORS entirely (which would indicate `WP-ERROR-022` or a different condition, not this entry's own).
6. Where multiple CORS-related mechanisms might be active (WordPress core's own default handling, a plugin, a server-level configuration), identify all of them and check for duplicate or conflicting header emission, rather than assuming only one mechanism is in effect.
7. Where a caching layer or CDN is in use, confirm it is not caching and replaying CORS headers computed for a different origin than the one actually making the current request.
8. Confirm the requesting origin is genuinely intended to have access, ruling out the correctly-functioning-security-boundary case (Section 10's own final bullet) before treating the condition as a defect to fix.
9. Preserve relevant evidence — the exact requesting origin, the exact headers observed on both the preflight (if any) and the actual response, and whether the condition is specific to one origin or general — before making any header-emission change.

```text
# Example only — illustrates directly inspecting CORS headers for a specific
# origin, independent of a browser; exact syntax and interpretation still
# require comparing against what the browser itself actually enforces.
curl -I -H "Origin: https://example.com" -X OPTIONS https://your-site.example/wp-json/
```

---

# 12. Recovery Procedure

Recovery shall target the specific, verified header gap or misconfiguration identified in Diagnosis (Section 11), scoped to the specific origins genuinely intended to have access.

Permitted recovery categories, depending on the verified cause, include:

- Adding a genuinely intended, legitimate origin to WordPress's own allowed-origins configuration (via the `allowed_http_origins`/`http_origin` filters, or a plugin's own equivalent setting), rather than broadening access beyond what is actually required.
- Correcting `Access-Control-Allow-Methods`/`-Headers` to include a genuinely required method or header the preflight response was missing.
- Correcting a server-level rule or security-plugin configuration that was intercepting or mishandling `OPTIONS` preflight requests before WordPress's own routing could process them.
- Correcting a credentialed-request configuration to send the specific requesting origin (not a wildcard) alongside `Access-Control-Allow-Credentials: true`, where the request genuinely requires credentials.
- Removing a duplicate or conflicting CORS-header-emitting mechanism, retaining only the one intended to be authoritative.
- Correcting a caching layer's own configuration to vary cached responses by requesting origin (commonly via a `Vary: Origin` header) rather than serving one origin's own computed headers to every requester.

This entry does not prescribe a blanket wildcard `Access-Control-Allow-Origin: *` as a general-purpose fix, particularly for any endpoint that also handles authenticated or credentialed requests, where a wildcard origin is both functionally rejected by browsers for credentialed requests and a broader access grant than most legitimate integrations actually require.

---

# 13. Validation

Recovery is successful when:

- The specific, previously-blocked cross-origin request completes and its response is successfully read by the calling script, confirmed via the browser's own developer tools showing no CORS-related console error for that request.
- Both the preflight (where one occurs) and the actual request's own response headers are confirmed correct, not only one or the other.
- Access was granted only to the specific, genuinely intended origin(s), not broadened to every origin as an unscoped side effect of the fix.
- Where credentials are involved, `Access-Control-Allow-Origin` reflects the specific requesting origin (never a wildcard) alongside `Access-Control-Allow-Credentials: true`.
- No previously-working same-origin or already-permitted cross-origin access was disturbed by the correction.
- Where a caching-layer fix was applied, confirmed effective for multiple distinct requesting origins in sequence, not only the one origin used during testing.

---

# 14. Prevention

- Document every legitimate cross-origin integration WordPress is expected to serve, so allowed-origins configuration can be maintained deliberately rather than reactively.
- Avoid a blanket wildcard `Access-Control-Allow-Origin` for any endpoint capable of handling authenticated or credentialed requests.
- Test cross-origin access explicitly, from an actual different origin, as part of any headless/decoupled front-end deployment's own validation — same-origin testing during development does not exercise CORS enforcement at all and can mask a defect until production.
- Ensure any caching layer serving REST or other WordPress-generated responses varies its cache by requesting origin where CORS headers are in play.
- Audit for duplicate CORS-header-emission sources (core defaults plus a plugin, or plus server-level configuration) when introducing a new CORS-related plugin or server rule.

---

# 15. Security Considerations

- A wildcard `Access-Control-Allow-Origin` combined with any form of credentialed access is both browser-rejected and, where it could otherwise take effect, a genuine security exposure; recovery shall never combine the two.
- Overly broad allowed-origin configuration (accepting more origins than genuinely required) increases the attack surface for any endpoint that returns sensitive data, even where authentication is also required — CORS misconfiguration is a defense-in-depth concern, not solely a functional one.
- Do not disable a security plugin's own OPTIONS-request handling broadly as a troubleshooting shortcut; identify and correct the specific interception or misconfiguration instead.
- A duplicate or conflicting CORS header, while primarily a functional defect, can in some configurations indicate an unintended or unreviewed plugin/server change; treat an unexplained new CORS-header source as worth confirming the legitimacy of, not only fixing mechanically.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-022 — WordPress REST API Access Denied](WP-ERROR-022-REST-API-ACCESS-DENIED.md) — exists in this repository; see Section 6 (Distinction) above for the two-directional relationship between CORS and REST authentication/authorization.
2. [WP-ERROR-028 — WordPress Outbound HTTP Request Failure](WP-ERROR-028-WORDPRESS-OUTBOUND-HTTP-REQUEST-FAILURE.md) and [WP-ERROR-029 — WordPress Outbound TLS Negotiation Failure](WP-ERROR-029-OUTBOUND-TLS-NEGOTIATION-FAILURE.md) — exist in this repository; see Section 6 (Distinction) above for how this entry reverses WordPress's own role from client (in those two entries) to server (in this one).
3. [WP-ERROR-021 — WordPress REST API Route Not Found](WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md) — exists in this repository; its own Section 6 already excludes CORS from its scope, anticipating this entry.

---

# 17. Notes

This entry documents the general, verified observable condition of a browser refusing to expose an otherwise WordPress-served response due to the browser's own cross-origin policy evaluation. It resolves the forward-reference `SF-TAXONOMY-002` made when the REST API category was built (`WP-ERROR-021`/`022` both already exclude CORS from their own scope, anticipating this entry by name).

This entry is deliberately framed around the fact that CORS enforcement is a browser behavior, not a WordPress behavior — WordPress's only role is emitting or omitting specific response headers, which is why this entry, uniquely among the three entries this category plans, concerns WordPress in the *server* role rather than the *client* role `WP-ERROR-028`/`029` both occupy.

This is the third and final entry `SF-TAXONOMY-004` plans for the Networking category's initial baseline.

This entry reached `Production Ready` via `SF-REVIEW-085` (Class A author review; no findings) and `SF-REVIEW-086` (Class B independent review; one Minor finding — IF-1, a Section 3 precision gap against Section 8's own documented origin-aware header-emission mechanism — corrected within that same review), per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
