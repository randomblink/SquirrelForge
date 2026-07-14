# WP-ERROR-022 — WordPress REST API Access Denied

---

# 1. Knowledge Entry

WordPress REST API Access Denied

---

# 2. Metadata

* **Error ID:** `WP-ERROR-022`
* **Title:** WordPress REST API Access Denied
* **Category:** REST API
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A WordPress REST API request matches a registered route and a callback has been identified (per `WP-ERROR-021`, route resolution has already succeeded), but the request is rejected before that callback's own business logic runs — because authentication fails, the route's own `permission_callback` denies the request, or the request's own arguments fail WordPress's built-in schema validation.

---

# 4. Primary Failure Mode

A matched REST route's request-acceptance stage rejects the request before the callback registered for that route begins executing its own business logic. This entry covers three sequential gates a request passes through before reaching that callback, per `SF-TAXONOMY-002` Section 3 and Section 4:

- **Authentication** — identity verification via cookie authentication and its accompanying nonce (`X-WP-Nonce`), Application Passwords, or a custom mechanism (a JWT or OAuth plugin) fails, is invalid, or is insufficient for the request to proceed. Each authentication handler participates through the `determine_current_user` filter chain and the `rest_authentication_errors` filter, checked in priority order (cookie authentication first, then Application Passwords, then any custom handler); each should defer (return `null`) if it does not apply to the current request, or return a `WP_Error` to explicitly deny it.
- **Authorization** — the route's own registered `permission_callback` evaluates the (possibly anonymous) request and returns `false` or a `WP_Error`, denying an operation the authenticated (or unauthenticated) requester is not permitted to perform. This gate is architecturally distinct from the main callback: a `permission_callback` that itself errors or crashes while evaluating is still within this entry's own "request acceptance" stage, not `WP-ERROR-023`'s, since the main callback's own business logic has not yet begun regardless of how the permission check itself failed.
- **Argument and schema validation** — the request's own parameters fail WordPress's built-in `validate_callback`/`sanitize_callback` checks, evaluated via `WP_REST_Request::has_valid_params()`. Per `SF-TAXONOMY-002` Section 4's explicit decision, this gate is owned here rather than by `WP-ERROR-023`, even though WordPress technically performs this validation before invoking the callback function in most cases: the deciding factor is observable engineering state (the callback's own business logic has not yet begun) rather than the precise internal order of operations.

A request rejected at any of these three gates — or by another pre-execution mechanism, such as a custom `rest_pre_dispatch` short-circuit implementing rate limiting or an additional access rule — is this entry's condition, regardless of which specific gate or mechanism produced the rejection.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on scope:

- Where a widely-used integration's own authentication mechanism fails entirely (for example, Application Passwords disabled site-wide, or a broken nonce affecting the Block Editor for every user), the impact can approach a full-site outage for REST-dependent functionality.
- Where a single user, role, or specific endpoint's own `permission_callback` denies a request appropriately or as a narrow misconfiguration, the impact is typically much narrower — that specific action fails for that specific requester while other REST functionality and ordinary browsing continue to work normally.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (for example, `WP-ERROR-004`, `005`, `006`, `019`, `020`, and `021`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a route was matched and a callback identified, and that the request was rejected before that callback's own business logic began running — not that no route was ever matched, that the callback executed and failed afterward, or that the rejection occurred for a reason unrelated to a REST request specifically.

**Internal distinctions this entry specifically requires:**

- **A permission_callback denial versus a permission_callback crash:** both are this entry's condition. A `permission_callback` that returns `false` or a `WP_Error` cleanly, and one that itself throws an exception or fatal error while evaluating, both occur before the main callback's own business logic runs; neither belongs to `WP-ERROR-023`.
- **Authentication failure versus authorization denial:** WordPress core's own endpoints surface both under the shared `rest_forbidden` error code, distinguished by HTTP status — 401 when the requester was never authenticated at all, versus 403 when the requester was authenticated but lacks the specific required capability — using the `rest_authorization_required_code()` helper function to select the correct status. This distinction is opt-in, not automatic: a custom endpoint's own `permission_callback` is not guaranteed to call this helper, and may return a single hardcoded status regardless of authentication state. Diagnosis (Section 11) treats this as a single entry-level condition but requires determining which of the two actually applies for the specific endpoint under review, since the corrective action differs materially.
- **Argument/schema validation versus authentication or authorization:** a request can be fully authenticated and authorized and still be rejected because its own parameters (a missing required field, a value failing type or enum validation) do not satisfy the endpoint's registered schema. This is a distinct gate from the other two, but shares this entry's own boundary — occurring before the callback's own business logic runs — per the explicit placement decision `SF-TAXONOMY-002` Section 4 records.

**Distinct from the following related entries and categories:**

- **`WP-ERROR-021` — WordPress REST API Route Not Found**: presumes no route was ever matched at all, so no callback — and therefore no request-acceptance stage — was ever reached. This entry presumes the opposite: a route was found and a callback identified.
- **`WP-ERROR-023` — WordPress REST API Response Error**: presumes the request survived all three gates this entry owns, and the callback's own business logic began executing and then failed. This entry ends, and `WP-ERROR-023` begins, exactly at that boundary.
- **Generic `wp-admin` cookie authentication** (Authentication category — **`WP-ERROR-024` — WordPress Login Authentication Failure** and **`WP-ERROR-025` — WordPress Authentication Cookie Invalid or Expired**): an ordinary `wp-admin` page load's own session/login handling is a distinct condition from REST's own cookie-authentication requirement, which additionally requires a valid `X-WP-Nonce` header or `_wpnonce` parameter beyond the session cookie alone — a REST-specific manifestation, per `SF-TAXONOMY-002` Section 2. Non-REST capability/authorization denial after a valid session is established belongs to **`WP-ERROR-026` — WordPress Capability or Role Authorization Denied**, distinguished from this entry's own `permission_callback` denial by request context (REST versus non-REST) rather than by mechanism.
- **A specific third-party authentication plugin's own implementation defect** (Plugin category): this entry owns the observable condition "the request was denied," regardless of which authentication mechanism was attempted. It does not own a specific JWT or OAuth plugin's own defective token-validation logic incorrectly denying a technically valid credential — that is the plugin's own defect, per `SF-TAXONOMY-002` Section 5.

---

# 7. Scope

**Covered:** A verified condition in which a REST route has been matched and a callback identified, but the request is rejected before that callback's own business logic runs — because authentication fails or is insufficient, the route's `permission_callback` denies the request (whether by a clean denial or by itself erroring during evaluation), the request's arguments fail WordPress's built-in schema validation, or another pre-execution mechanism (for example, a custom rate-limiting or access-control hook on `rest_pre_dispatch`) rejects the request — regardless of which specific gate or mechanism produced the rejection.

**Excluded:**

- No route matched at all (see `WP-ERROR-021`).
- A matched, accepted request whose callback began executing and then failed (see [WP-ERROR-023](WP-ERROR-023-REST-API-RESPONSE-ERROR.md)).
- Generic `wp-admin` cookie/session authentication unrelated to a REST request specifically.
- A specific third-party authentication plugin's own implementation defect, as distinct from the observable "the request was denied" condition this entry owns.
- Browser-enforced cross-origin (CORS) policy failures, excluded from this category entirely per `SF-TAXONOMY-002` Section 5.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every incident exercises every one of them identically:

- Cookie authentication and its accompanying nonce requirement: `rest_cookie_check_errors()` (`wp-includes/rest-api.php`), the `X-WP-Nonce` HTTP header or `_wpnonce` request parameter, and `wp_verify_nonce()`, producing the `rest_cookie_invalid_nonce` error (HTTP 403) when a nonce is missing, stale, or invalid.
- Application Passwords (`wp-includes/class-wp-application-passwords.php`), WordPress's own built-in credential mechanism for external REST clients, authenticated via HTTP Basic Auth using a WordPress username and a generated application-password string. Requires the site to be served over HTTPS, and can be disabled entirely via the `wp_is_application_passwords_available` filter (some hosting environments disable it by default).
- The `determine_current_user` filter chain and the `rest_authentication_errors` filter, through which every authentication handler (cookie, Application Passwords, or a custom third-party mechanism) is given the opportunity, in priority order, to authenticate the request or defer to the next handler.
- The `permission_callback` parameter of `register_rest_route()`, and `rest_authorization_required_code()` — a helper function WordPress core's own endpoints use to select HTTP 401 (the requester was never authenticated) versus HTTP 403 (the requester was authenticated but lacks the specific required capability), both commonly under the shared `rest_forbidden` error code and its "Sorry, you are not allowed to do that." message. Use of this helper is opt-in; a custom endpoint's own `permission_callback` may not distinguish the two statuses at all.
- `WP_REST_Request::has_valid_params()` and the `validate_callback`/`sanitize_callback` schema arguments a route registers for its own parameters, producing `rest_invalid_param` or `rest_missing_callback_param` (both HTTP 400) when a request's own arguments fail validation.
- The `doing_it_wrong` notice WordPress core emits when a route is registered without any `permission_callback` at all — relevant context, though the omission itself defaults toward permissive access rather than causing this entry's own denial condition.
- `rest_pre_dispatch` and related short-circuit filters, through which custom code (a rate limiter, an additional access-control rule) can reject a request before the main callback runs, independent of the three named gates above.

---

# 9. Typical Symptoms

- An HTTP 401 response with the `rest_forbidden` error code and "Sorry, you are not allowed to do that." message, where the requester was not authenticated at all.
- An HTTP 403 response with the same `rest_forbidden` code and message, where the requester was authenticated but the route's `permission_callback` denies the specific action.
- An HTTP 403 response with the `rest_cookie_invalid_nonce` error code, for a cookie-authenticated (browser or admin-side) request missing a valid `X-WP-Nonce`.
- An HTTP 401 response for an Application-Passwords-authenticated request, where the credentials are invalid, revoked, or the feature is disabled for that site or environment.
- An HTTP 400 response with `rest_invalid_param` or `rest_missing_callback_param`, naming the specific parameter(s) that failed validation — distinctly worded from an authentication or authorization rejection despite occurring at the same overall stage.
- The identical request succeeding for an administrator or other fully-privileged user while failing for a lower-privileged or unauthenticated one, indicating a capability-based (`permission_callback`) denial rather than a mechanism-wide authentication failure.
- A previously-working integration failing suddenly after a nonce's lifetime expired, a user's session ended, an Application Password was revoked or regenerated, or a proxy/CDN configuration change began stripping the `Authorization` header before it reaches WordPress.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- A missing or expired nonce on a cookie-authenticated request — for example, a single-page application or admin-side script holding a stale nonce across a long-lived page load.
- Invalid or revoked Application Password credentials, or the request's own `Authorization` header being stripped by an intermediate proxy, CDN, or web-server configuration before reaching PHP — a well-documented cause on some hosting environments.
- The `wp_is_application_passwords_available` filter (or an equivalent mechanism) disabled by a plugin, theme, or hosting default, preventing Application Passwords from being usable at all regardless of otherwise-correct credentials.
- A `permission_callback` correctly denying a request because the authenticated user genuinely lacks the required capability for that specific action.
- A `permission_callback` implemented incorrectly — checking the wrong capability, or failing closed for a condition its own author did not anticipate.
- A required request parameter omitted, or a parameter's value failing its registered type, format, or enumerated-value validation.
- A custom `rest_pre_dispatch` hook, rate-limiting plugin, or security plugin rejecting the request for a reason independent of the three named gates.
- A third-party authentication plugin (a JWT or OAuth implementation) failing to authenticate a technically valid credential, due to that plugin's own implementation defect.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a request-acceptance denial — an HTTP 400, 401, or 403 response carrying `rest_forbidden`, `rest_cookie_invalid_nonce`, an Application-Passwords-specific authentication error, `rest_invalid_param`, or `rest_missing_callback_param` — rather than a route-not-found condition (`WP-ERROR-021`) or a failure occurring after the callback's own business logic began running (`WP-ERROR-023`).
2. As the broadest, least invasive check, re-confirm that route resolution itself is not the actual cause: verify the same endpoint resolves (reusing `WP-ERROR-021`'s own least-invasive checks — the bare API root, or the endpoint under a differing credential context) before narrowing into which specific gate denied the request under review. A response already carrying an access-denial code ordinarily implies a route was matched, but re-confirming this directly rules out an intermittent or credential-dependent route-resolution issue being mistaken for this entry's own condition.
3. Capture the exact error code, HTTP status, and message, since the specific code indicates which gate (authentication, authorization, or argument validation) actually produced the rejection, rather than assuming from the HTTP status alone.
4. Confirm whether the identical request succeeds for a known-good, fully-privileged user (for example, an administrator) using the same authentication mechanism, to isolate whether the cause is capability-based (`permission_callback`) or mechanism-wide (authentication itself failing regardless of who is requesting).
5. Where cookie authentication is in use, confirm a valid `X-WP-Nonce` (or `_wpnonce`) value is actually being sent with the request and corresponds to the currently active session, since nonces have a limited lifetime and a stale value produces `rest_cookie_invalid_nonce` even for an otherwise-valid, logged-in user.
6. Where Application Passwords are in use, confirm the specific application password has not been revoked or regenerated, that the request reaches WordPress over HTTPS, and that the `Authorization` header actually arrives intact at PHP rather than being stripped by an intermediate proxy, CDN, or web-server configuration.
7. Where a `permission_callback` denial (`rest_forbidden`) is confirmed, determine whether the HTTP status is 401 (the requester was not authenticated at all) or 403 (the requester was authenticated but lacks the specific required capability). WordPress core's own endpoints use `rest_authorization_required_code()` to make this distinction correctly; a custom endpoint's `permission_callback` is not guaranteed to, and may return a single status regardless of authentication state, so confirm which is actually the case for the specific endpoint under review rather than assuming the distinction is automatic.
8. Where an argument-validation error (`rest_invalid_param` or `rest_missing_callback_param`) is confirmed, review the specific parameter(s) named in the response body against the endpoint's own registered schema, rather than assuming the entire request is malformed.
9. Where a third-party authentication plugin (JWT, OAuth) is in use, confirm whether the failure originates from that plugin's own token-validation logic specifically, as opposed to WordPress's own core mechanisms, since a defect in the plugin's own implementation belongs to the Plugin category, not this entry.
10. Where a security plugin, rate limiter, or custom `rest_pre_dispatch` hook is present, confirm whether it is responsible for the rejection by temporarily disabling it and retesting, rather than assuming core WordPress mechanisms are at fault.
11. Preserve relevant evidence — the exact request (with credentials redacted), the full response, and timestamps — before making any change.
12. Where the engineer performing diagnosis does not control the specific plugin, permission logic, or authentication configuration responsible, escalate to whoever does rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall grant or correct only the specific, minimum access the legitimate request actually requires, rather than weakening authentication or authorization broadly as a shortcut.

Permitted recovery categories, depending on the verified cause, include:

- Where a stale or missing nonce is confirmed, obtaining a fresh nonce (for example, by reloading the page that generates it) rather than only retrying the same request.
- Where invalid or revoked Application Password credentials are confirmed, generating a new Application Password through the user's own profile and updating the calling application's stored credentials accordingly.
- Where a proxy, CDN, or web-server configuration is confirmed to be stripping the `Authorization` header, correcting that configuration to pass the header through, in coordination with whoever controls that infrastructure.
- Where the `wp_is_application_passwords_available` filter or an equivalent mechanism is confirmed to be disabling the feature, and Application Passwords are the intended authentication method, correcting or removing that filter.
- Where a `permission_callback` is confirmed to be correctly denying an appropriately-scoped request, granting the specific capability required through the appropriate role or user-capability change, scoped to the minimum necessary, rather than weakening the `permission_callback` itself.
- Where a `permission_callback` is confirmed to be implemented incorrectly, correcting its own logic, rather than working around it by granting broader privileges than the action actually requires.
- Where a required or malformed argument is confirmed as the cause, correcting the calling application's own request to match the endpoint's registered schema, or correcting the schema itself if it does not accurately reflect the endpoint's actual requirements.
- Where a rate limiter, security plugin, or custom `rest_pre_dispatch` hook is confirmed as the cause, adjusting its specific rule to permit the legitimate request, in coordination with whoever administers it, rather than disabling it entirely.
- Escalating to whoever controls the specific plugin, permission logic, or authentication configuration responsible, where the engineer performing recovery does not have that access.

Recovery shall not grant broader capabilities than the specific action requires, disable nonce verification, or bypass a `permission_callback` wholesale as a shortcut to resolving a denial.

---

# 13. Validation

Recovery is successful when:

- The previously denied request now succeeds for every user or client that legitimately should have access, confirmed by reproducing the exact request that previously failed.
- The previously denied request continues to correctly fail for a user or client that should not have access, confirming the fix did not inadvertently widen access beyond what was intended.
- No equivalent `rest_forbidden`, `rest_cookie_invalid_nonce`, Application-Passwords authentication error, `rest_invalid_param`, or `rest_missing_callback_param` recurs across repeated, fresh requests.
- Any capability or permission granted was scoped to the minimum necessary for the legitimate request, confirmed by reviewing the actual grant made.

---

# 14. Prevention

- Document which authentication mechanism (cookie and nonce, Application Passwords, a specific third-party method) each REST-dependent integration is expected to use, so a future denial can be diagnosed against the correct expected mechanism.
- Monitor nonce and session lifetimes relative to how long-lived client-side sessions or single-page applications actually are, to avoid routine, expected nonce expiration being mistaken for a genuine defect.
- Review `permission_callback` logic during development and code review for correctness — checking the intended capability, failing closed only where genuinely appropriate — rather than only after a denial is reported.
- Document any intentional rate-limiting or custom pre-dispatch access rules clearly, so a future investigation does not need to rediscover why a specific request was rejected.
- Test REST-dependent integrations against realistic credential-rotation and revocation scenarios (an expired nonce, a revoked Application Password) as part of routine testing, not only the happy path.

---

# 15. Security Considerations

- Do not weaken authentication or bypass a `permission_callback` as a shortcut to resolving a denial; grant only the specific, minimum access the legitimate request actually requires.
- Do not disable nonce verification for cookie-authenticated requests as a workaround; doing so removes CSRF protection for the affected endpoint.
- Treat a sudden, unexplained spike in access-denial errors as a potential signal of a credential-stuffing or brute-force attempt against authentication, or a probing attempt against permission boundaries, rather than assuming it is always routine misconfiguration.
- Avoid exposing internal capability names, user-enumeration detail, or other implementation specifics in a denial response beyond what WordPress's own standard error messages already provide.
- Coordinate any change to a shared authentication mechanism (disabling Application Passwords, adjusting nonce lifetimes) through a controlled process, since such changes can affect every integration relying on it, not only the one being diagnosed.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-021 — WordPress REST API Route Not Found](WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-023 — WordPress REST API Response Error](WP-ERROR-023-REST-API-RESPONSE-ERROR.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the second of three entries `SF-TAXONOMY-002` declares for the REST API category, owning the request-acceptance stage of the REST request lifecycle — authentication, authorization, and argument/schema validation, unified as one cohesive condition because all three occur before the matched route's own callback begins its business logic, per **SF-SPEC-001** Section 4.3's single-responsibility principle. It does not restate `WP-ERROR-021`'s own boundary.

This entry's governing direction was `SF-TAXONOMY-002` Version 1.2, whose Section 3 declaration and Section 4 argument-validation placement decision are both directly applicable here and are applied without narrowing or widening either: the taxonomy explicitly assigns argument/schema validation to this entry rather than `WP-ERROR-023`, on the basis of observable engineering state (the callback's own business logic has not yet begun) rather than WordPress's internal invocation order, and that decision is reflected throughout this entry's Sections 4, 6, 8, and 9. The specific technical grounding (the `rest_forbidden`/`rest_cookie_invalid_nonce`/`rest_invalid_param`/`rest_missing_callback_param` error codes, `rest_authorization_required_code()`'s 401-versus-403 distinction, Application Passwords' own requirements and disabling mechanism, and the `determine_current_user`/`rest_authentication_errors` filter chain) was independently verified against current WordPress documentation before inclusion, following this catalog's established practice.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-048-WP-ERROR-022-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-049-WP-ERROR-022-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions** — adding an explicit route-resolution-elimination step to Diagnosis (carrying forward the diagnostic-philosophy lesson `SF-REVIEW-047` established for `WP-ERROR-021`) and qualifying `rest_authorization_required_code()`'s scope as opt-in rather than universal — and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
