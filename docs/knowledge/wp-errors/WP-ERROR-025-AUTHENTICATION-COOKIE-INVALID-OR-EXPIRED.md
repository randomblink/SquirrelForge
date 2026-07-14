# WP-ERROR-025 — WordPress Authentication Cookie Invalid or Expired

---

# 1. Knowledge Entry

WordPress Authentication Cookie Invalid or Expired

---

# 2. Metadata

* **Error ID:** `WP-ERROR-025`
* **Title:** WordPress Authentication Cookie Invalid or Expired
* **Category:** Authentication
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A user who previously completed a successful login (`WP-ERROR-024`'s own condition, resolved favorably) is later treated as no longer authenticated, because a subsequent request's own validation of WordPress's authentication cookies — `wp_validate_auth_cookie()` — fails: the cookie is missing, malformed, expired, fails its HMAC signature check, or references a session token WordPress no longer considers valid. Identity was established previously; this entry's own condition is that the *persistence* of that established identity across requests has broken down.

---

# 4. Primary Failure Mode

A request arrives carrying (or failing to carry) WordPress's own authentication cookies, and `wp_validate_auth_cookie()` — called by `wp_get_current_user()` on every request via the `determine_current_user` filter — returns `false` for a user who holds evidence of a genuinely prior successful login (their own recollection, a support ticket, or server-side logs from `WP-ERROR-024`'s own pipeline showing an earlier accepted `wp_authenticate()` call for the same account). The user is treated as logged out: `is_user_logged_in()` returns `false`, and any subsequent attempt to access an authenticated area redirects to `wp-login.php` or is otherwise denied, without a new credential ever having been rejected.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on cause and scope:

- Where a single user's own cookie becomes invalid (natural expiration, a cleared browser cache, switching devices), the impact is narrow: that user is logged out and shall authenticate again via `WP-ERROR-024`'s own pipeline, which — if the underlying cause was local to that one session — succeeds normally.
- Where the underlying cause is site-wide (an incorrect `COOKIE_DOMAIN`, a secret-key/salt rotation, a site-URL change that alters the cookie-name hash WordPress derives from it, or a load-balanced environment with inconsistent secret keys across servers), *every* user's session is invalidated simultaneously, and — critically — a fresh login may immediately fail to persist as well, since the same misconfiguration that invalidated existing cookies also prevents new ones from validating correctly on the next request. This is the entry's most severe manifestation: a redirect loop back to the login screen immediately after every successful `WP-ERROR-024` authentication, with no account able to remain logged in at all.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a prior successful authentication genuinely occurred, and that the *current* failure is in cookie validation specifically — not that the original credential check itself failed, that the user was authenticated but lacks a required capability, or that a request-origin token (rather than the session itself) was rejected.

**What this entry means by "authentication cookie" — and what it explicitly does not mean:**

- **WordPress's own authentication cookies** are exactly three, each serving a distinct scope: `AUTH_COOKIE` (used for `wp-admin`/network-admin requests on a site not forcing SSL admin-wide), `SECURE_AUTH_COOKIE` (the SSL-forced equivalent, used when `FORCE_SSL_ADMIN` is set or the request is already over HTTPS), and `LOGGED_IN_COOKIE` (used front-end-wide, for any `is_user_logged_in()` check outside the admin-specific cookie's own scope — for example, comment-form pre-fill or a front-end members-only page). This entry's condition is a validation failure in one or more of these three, and only these three.
- **PHP native sessions (`session_start()`, `$_SESSION`) are explicitly not what this entry covers.** WordPress core does not use PHP sessions to track logged-in state at all — it uses only the cookie-plus-server-side-token scheme described in Section 8. A site where a *plugin* has separately introduced PHP sessions for its own purposes, and where that plugin's own session handling breaks, is a Plugin-category condition, not this entry's, even if the symptom ("I keep getting logged out") sounds identical. Diagnosis (Section 11) requires confirming which mechanism — WordPress's own auth cookies, or a plugin-introduced PHP session — is actually implicated before applying this entry.
- **Arbitrary cookies set by plugins or themes** (a shopping-cart cookie, an analytics cookie, a cookie-consent-banner cookie) are not authentication cookies and are entirely out of this entry's scope, regardless of how a specific plugin's own logic might use their presence or absence.

It is distinct from:

- **`WP-ERROR-024` — WordPress Login Authentication Failure**: presumes no valid, prior authenticated session exists — the *current* credential-verification attempt is what fails. This entry presumes the opposite: a credential-verification attempt *already succeeded*, and the failure is entirely in what happened after that success. A browser with cookies disabled entirely is a boundary case of *this* entry, not of `WP-ERROR-024`: WordPress's own `wp_signon()` still completes successfully in this scenario — credentials are verified and `wp_set_auth_cookie()` issues the cookie server-side — `wp-login.php` only separately detects, via its own `wordpress_test_cookie` check, that the browser never retained anything at all. This is this entry's own condition at its most extreme (zero persistence, rather than expired or otherwise invalid persistence), not a failure of credential verification itself.
- **`WP-ERROR-026` — WordPress Capability or Role Authorization Denied**: presumes the user's session is currently *valid* — `wp_validate_auth_cookie()` succeeds — and the failure is a subsequent decision about what that still-authenticated user is allowed to do. This entry's own condition ends the moment session validity itself is confirmed or denied; a denial made after that point is `WP-ERROR-026`'s territory.
- **`WP-ERROR-027` — WordPress Nonce Verification Failure (Non-REST)**: a nonce failure is independent of session validity — a user can hold a perfectly valid, currently-accepted authentication cookie and still fail nonce verification, because a nonce checks request origin/freshness, not identity persistence. This entry's own condition and `WP-ERROR-027`'s are orthogonal, not sequential.
- **A user's own account being logged out deliberately** (an explicit `wp_logout()` call, an administrator forcing a session termination via the Sessions feature in the user's own profile screen) — this is the *intended*, correctly-functioning result of the same cookie-invalidation mechanism this entry documents, not a failure of it. This entry covers only cases where invalidation occurs *unexpectedly*, without a corresponding deliberate logout action.

---

# 7. Scope

**Covered:** A verified condition in which a user who previously completed a successful WordPress login is later found to hold an invalid, missing, or unaccepted authentication cookie (`AUTH_COOKIE`, `SECURE_AUTH_COOKIE`, or `LOGGED_IN_COOKIE`), causing `wp_validate_auth_cookie()` to reject the session on a subsequent request, without any new credential having been submitted or rejected.

**Excluded:**

- The original login attempt's own credential verification failing (`WP-ERROR-024`) — as distinct from credentials being verified successfully but the resulting cookie never persisting to the browser at all (a browser with cookies disabled entirely), which is this entry's own condition at its most extreme, per Section 6.
- Capability or role checks evaluated after session validity is already confirmed (`WP-ERROR-026`).
- Nonce or request-origin verification, independent of session validity (`WP-ERROR-027`).
- REST API cookie-authentication requirements, which additionally require a valid nonce beyond the cookie alone (`WP-ERROR-022`).
- PHP native sessions (`$_SESSION`) introduced by a plugin, independent of WordPress's own cookie-based session mechanism.
- Arbitrary non-authentication cookies set by plugins, themes, or third-party scripts.
- A deliberate, intended logout (explicit `wp_logout()`, or an administrator explicitly terminating a session).
- A missing PHP extension or unsupported PHP version preventing the cookie-validation code (for example, a hashing function) from executing at all (PHP Runtime category).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp_set_auth_cookie()` (`wp-includes/pluggable.php`), called on successful login, which generates and sets the appropriate cookie(s) and creates a corresponding server-side session token via `WP_Session_Tokens`.
- `wp_validate_auth_cookie()` (`wp-includes/pluggable.php`), called on every request through the `determine_current_user` filter, which parses the cookie, verifies its HMAC signature against the site's secret keys and salts, checks expiration, and confirms the embedded session token still exists and is valid in the user's own stored session-token list (`wp_usermeta`, `session_tokens` key).
- The cookie constants: `AUTH_COOKIE`, `SECURE_AUTH_COOKIE`, `LOGGED_IN_COOKIE` (the cookie *names* WordPress generates, which themselves embed a hash of the site's own URL — `COOKIEHASH` — meaning a site-URL change alone can invalidate every existing cookie by changing the name WordPress looks for).
- The `AUTH_KEY`/`AUTH_SALT`, `SECURE_AUTH_KEY`/`SECURE_AUTH_SALT`, and `LOGGED_IN_KEY`/`LOGGED_IN_SALT` secret values in `wp-config.php`, used to compute each cookie's HMAC signature — rotating any of these (including via the official secret-key-regeneration service) immediately invalidates every existing cookie signed with the old value.
- `COOKIE_DOMAIN` and `COOKIEPATH`/`SITECOOKIEPATH`, which control the domain and path scope the cookie is set and read for; a mismatch (for example, between a `www` and non-`www` hostname, or between HTTP and HTTPS treated as different scopes without `COOKIE_DOMAIN` set explicitly) causes the browser to hold a cookie the server-side request never actually receives.
- `WP_Session_Tokens` and its default implementation `WP_User_Meta_Session_Tokens`, which store the server-side half of each session (allowing per-session revocation, expiration, and the "Log Out Everywhere Else" feature) independently of the cookie itself — a cookie can be cryptographically valid and still be rejected if its corresponding server-side token has been removed or expired.
- `auth_redirect()`, which enforces that an admin-area request holds a currently-valid cookie, redirecting to `wp-login.php` with a `reauth` parameter when it does not.

---

# 9. Typical Symptoms

- A user is unexpectedly redirected to the login screen while attempting to access `wp-admin`, despite having logged in earlier in the same browsing session.
- "You are not currently logged in." displayed on an admin-area page that had previously loaded normally.
- Repeated, intermittent logout — a user is required to log in again multiple times within a short period, rather than remaining logged in for the expected duration.
- A user is logged in on one subdomain or protocol (`https://example.com`) but appears logged out on another (`https://www.example.com`), indicating a `COOKIE_DOMAIN` scope mismatch.
- Every user across an entire site is simultaneously logged out at the same moment, with no individual action by any of them — a strong signal of a site-wide cause (secret-key rotation, site-URL change, or a load-balanced environment with inconsistent secret keys across servers).
- A user remains logged in on the front end (`LOGGED_IN_COOKIE` still valid) but cannot access `wp-admin` (`AUTH_COOKIE`/`SECURE_AUTH_COOKIE` invalid), or the reverse — since the three cookies are validated independently for their own respective scopes.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Ordinary, expected cookie expiration (WordPress's default authentication cookie duration is two days, or fourteen days when "Remember Me" is checked at login) — not itself a defect, but frequently reported as one when a user does not expect the expiration.
- A secret key or salt in `wp-config.php` was rotated (deliberately, via a security response, or accidentally via a configuration-management tool overwriting `wp-config.php` with a template containing placeholder or freshly-generated values) — this invalidates every existing session immediately and simultaneously.
- The site URL (`siteurl`/`home` options, or the `WP_SITEURL`/`WP_HOME` constants) changed — including a protocol change from HTTP to HTTPS — altering the `COOKIEHASH` value embedded in the cookie name WordPress looks for, so previously-set cookies are no longer recognized.
- `COOKIE_DOMAIN` is unset, incorrect, or inconsistent with how users actually reach the site (a mix of `www` and non-`www` access without a canonical redirect in place).
- A load-balanced or multi-server environment has inconsistent `wp-config.php` secret keys across servers, so a cookie validated as correct by one server is rejected by another handling a subsequent request from the same user.
- A caching layer (a full-page cache, a CDN, or a reverse proxy) is caching a response containing a `Set-Cookie` header intended for one user and serving it to another, or is stripping/altering cookie headers in transit.
- The user's own browser cleared cookies (manually, via a privacy extension, or via "clear on exit" settings) between visits.
- A user's own account had its sessions explicitly and correctly terminated (a deliberate password change, which WordPress core invalidates all other sessions for by design, or an administrator's own "Log Out Everywhere Else" action) — this is the excluded, correctly-functioning case from Section 6, not a defect, but is a common source of a false report of this entry's own condition.
- Server clock skew significant enough to affect expiration-timestamp comparison between when a cookie was issued and when it is later validated.

---

# 11. Diagnosis

Verify the following:

1. Confirm a genuine prior successful authentication occurred for the affected account — check for evidence of an earlier accepted `wp_authenticate()` call (server logs, the user's own credible report of having been logged in) rather than assuming it without confirmation.
2. Confirm which of the three authentication cookies is actually implicated (`AUTH_COOKIE`/`SECURE_AUTH_COOKIE` for admin-area access, `LOGGED_IN_COOKIE` for front-end logged-in state) using the browser's own developer tools to inspect the cookies actually being sent, since the three fail independently.
3. Confirm the condition is genuinely a WordPress authentication-cookie issue and not a plugin-introduced PHP session or an unrelated cookie, per Section 6's explicit disambiguation — inspect what cookies the browser is actually holding and confirm their names match WordPress's own generated cookie names, which include the `COOKIEHASH` site-URL-derived value.
4. Determine whether the condition affects one user or every user simultaneously; simultaneous, site-wide loss of every session is strong evidence of a secret-key rotation, a site-URL change, or a `COOKIE_DOMAIN`/multi-server misconfiguration, rather than an individual browser-side cause.
5. Check whether the site's own secret keys and salts in `wp-config.php` were recently changed (via version-control history, deployment logs, or a configuration-management tool's own change history).
6. Check whether the site URL (`siteurl`/`home`, or `WP_SITEURL`/`WP_HOME`) changed recently, including a protocol change, which alters the cookie name via `COOKIEHASH`.
7. In a load-balanced or multi-server environment, confirm every server shares an identical `wp-config.php` secret-key configuration.
8. Where a caching layer or CDN is in use, confirm it is not caching or mishandling `Set-Cookie` response headers — a response containing another user's session cookie being served from cache is both this entry's own condition and a distinct privacy/security concern (see Section 15).
9. Rule out the excluded, correctly-functioning case (Section 6): confirm the logout was not the intended result of a deliberate password change or an administrator's own explicit session termination.
10. Preserve relevant evidence — the exact cookie names and values observed (redacted of their signature portion before sharing outside a trusted diagnostic context), the timing of onset, and whether the condition is single-user or site-wide — before making any change to secret keys, URLs, or cookie configuration.

```text
# Example only — illustrates checking the currently-configured secret keys are
# present and non-default; does not itself diagnose which specific cookie failed.
wp config get AUTH_KEY
wp config get LOGGED_IN_KEY
```

---

# 12. Recovery Procedure

Recovery shall target the verified cause, not merely instruct the user to log in again without addressing a site-wide cause if one exists.

Permitted recovery categories, depending on the verified cause, include:

- For an individual, isolated case with no site-wide cause found: no action beyond confirming normal re-authentication via `WP-ERROR-024`'s own pipeline succeeds and persists correctly on a subsequent request.
- Correcting `COOKIE_DOMAIN` to match the site's own actual, canonical access pattern, and ensuring a consistent canonical URL (a single enforced `www`/non-`www` and HTTP/HTTPS choice) so cookies are set and read under one consistent scope.
- Ensuring identical `wp-config.php` secret-key values across every server in a load-balanced or multi-server environment.
- Reverting an unintended site-URL change, or, where the change is intentional and permanent, accepting that every existing session is correctly invalidated as a one-time consequence and communicating that to users rather than treating it as an ongoing defect.
- Correcting a caching layer's or CDN's configuration to never cache a response containing a `Set-Cookie` header for an authenticated request.
- Where secret-key rotation was unintentional (an overwritten `wp-config.php`), restoring the correct, previously-in-use values if available, or accepting the one-time, site-wide re-authentication requirement if not.

This entry does not prescribe extending the default cookie-expiration duration as a general workaround for an unresolved underlying cause, and does not prescribe disabling secure cookie flags or cookie signature verification as a means of making an invalid cookie appear valid.

Recovery shall not weaken cookie security (for example, by extending `AUTH_KEY`-equivalent secrets to a trivially guessable value, or by disabling the HMAC signature check) as a substitute for correcting the actual configuration cause.

---

# 13. Validation

Recovery is successful when:

- The affected user (or, for a site-wide cause, a representative sample of users) can log in via `WP-ERROR-024`'s own pipeline and remain authenticated across multiple subsequent requests and page loads without unexpected logout.
- Session persistence is confirmed across every relevant scope the site actually uses — both `wp-admin` (`AUTH_COOKIE`/`SECURE_AUTH_COOKIE`) and front-end logged-in state (`LOGGED_IN_COOKIE`), where both are relevant to the original report.
- Where a `COOKIE_DOMAIN` or canonical-URL correction was made, session persistence is confirmed across every hostname variant users actually use to reach the site, not only the canonical one.
- Where a caching-layer correction was made, a fresh authenticated request confirms no cached `Set-Cookie` header is served to a different session.
- No unrelated, previously-working session was disturbed by the correction (for example, a secret-key change made to fix one issue did not need to invalidate sessions a second, unnecessary time).
- Session duration matches the expected, configured value (the two-day default, or fourteen days with "Remember Me"), not an unexpectedly shorter interval that would indicate an unresolved underlying cause still present.

---

# 14. Prevention

- Store `wp-config.php` secret keys under the same change-management discipline as any other production credential; avoid regenerating them as a routine, non-deliberate action, and document any intentional rotation as a known, one-time, site-wide re-authentication event.
- Set `COOKIE_DOMAIN` explicitly rather than relying on WordPress's own default inference, particularly on any site reachable via more than one hostname variant.
- Enforce a single canonical URL (protocol and `www`/non-`www` choice) via a proper redirect, rather than allowing a site to be served identically under multiple URL variants.
- In load-balanced or multi-server environments, manage `wp-config.php` (or at minimum its secret-key values) as shared, synchronized configuration rather than per-server files that can drift.
- Explicitly exclude authenticated responses (any response where a `Set-Cookie` header for an authentication cookie is present) from full-page caching and CDN caching.
- Treat any site-URL change as a planned event with an explicit, communicated expectation that every existing session will be invalidated.
- Monitor for simultaneous, site-wide logout events as a health signal distinct from ordinary, individually-timed session expiration.

---

# 15. Security Considerations

- A cookie's own signature portion (the HMAC) should never be logged or shared in a diagnostic context; capture only the cookie *name* and non-sensitive metadata (presence, approximate expiration) when documenting an investigation.
- A caching layer serving one user's authentication cookie to another user (Section 10) is not only this entry's own functional condition but a session-hijacking-equivalent security exposure, and shall be treated with the corresponding urgency — including determining whether any unauthorized access occurred as a result — not solely as a "users keep getting logged out" inconvenience.
- Secret-key rotation is itself a legitimate, recommended security response to a suspected key compromise; recovery from the resulting site-wide logout (Section 12) shall not be treated as a reason to avoid rotating a genuinely compromised key.
- Do not disable cookie signature verification, secure/HttpOnly cookie flags, or reduce cookie entropy as a troubleshooting shortcut; every such change weakens session security for every user, not only the one being diagnosed.
- Confirm that "Log Out Everywhere Else" and per-session revocation (`WP_Session_Tokens`) continue to function correctly after any recovery action, since these are themselves security features a misconfigured cookie/session mechanism could silently disable.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-024 — WordPress Login Authentication Failure](WP-ERROR-024-WORDPRESS-LOGIN-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for how the identity-verification stage differs from this entry's own session-persistence condition.
2. [WP-ERROR-026 — WordPress Capability or Role Authorization Denied](WP-ERROR-026-CAPABILITY-OR-ROLE-AUTHORIZATION-DENIED.md) — exists in this repository (currently `Draft`); see Section 6 (Distinction) above for how a post-session-validity authorization decision differs from this entry's own condition.
3. [WP-ERROR-027 — WordPress Nonce Verification Failure, Non-REST](WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md) — exists in this repository (currently `Draft`); see Section 6 (Distinction) above for why request-origin verification is orthogonal to session validity.
4. [WP-ERROR-022 — WordPress REST API Access Denied](WP-ERROR-022-REST-API-ACCESS-DENIED.md) — exists in this repository; see Section 7 (Scope) above for how REST's own cookie-authentication requirement additionally requires a nonce beyond the cookie alone.

---

# 17. Notes

This entry documents the general, verified observable condition of a previously-successful WordPress authentication failing to persist across subsequent requests due to the authentication-cookie mechanism specifically. It does not claim that every "I keep getting logged out" report is this entry's own condition; per Section 6 and Section 11, PHP-session-based causes introduced by a plugin, and deliberate/intended logouts, are explicitly excluded and shall be ruled out during diagnosis before this entry is applied.

This is the second of four entries `SF-TAXONOMY-003` plans for the Authentication category, referencing `WP-ERROR-024`'s own "identity established" baseline as the project owner's own recommended layered-progression approach directs.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-072-WP-ERROR-025-AUTHOR-REVIEW.md`, which found and corrected two Minor findings, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-073-WP-ERROR-025-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, corrected a cross-document attribution error regarding the cookies-disabled boundary case, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
