# SF-TAXONOMY-003 — Authentication Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-003

**Title:** Authentication Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair, though an independent review of it is planned per this project's established practice (`SF-REVIEW-034` for `SF-TAXONOMY-001`, `SF-REVIEW-045` for `SF-TAXONOMY-002`), not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` **SF-SPEC-001** Section 18 lifecycle stage, nor of any **SF-SPEC-008** Section 6 Version Status value, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope, the same disclaimer `SF-TAXONOMY-001`/`002` make.

**Version:** 1.5

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the Authentication category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — this is the Category Work Order the project owner requested when selecting Authentication as the first knowledge-production effort under Framework Baseline v2.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**Authentication** owns failures in WordPress's own user-identity verification, session persistence, and non-REST authorization/request-legitimacy checks — the mechanisms `wp-login.php`, `wp_signon()`, `wp_authenticate()`, `wp_verify_nonce()`, and `current_user_can()` implement for ordinary WordPress usage (`wp-admin`, theme/plugin-rendered forms, XML-RPC), independent of any specific REST API request.

**Critical disambiguation — this is not the only "authentication" concept already named in this repository:**

* **`WP-ERROR-002` — WordPress Database Authentication Failure** concerns WordPress's own connection to its MySQL/MariaDB *database server* rejecting the credentials in `wp-config.php` — a completely different identity relationship (WordPress-as-client authenticating to its database) from anything this category covers (a human or programmatic actor authenticating *to* WordPress). The shared word "authentication" does not imply any shared condition, boundary, or entry. No cross-reference between this category and `WP-ERROR-002` is expected, since the two conditions cannot be confused once the actors involved are named explicitly, but this disclaimer is recorded here so a future reader is not left to infer it.

**Explicitly not owned by Authentication:**

* **Authentication or authorization occurring *within* a REST API request** — cookie/nonce validation, Application Passwords, or `permission_callback` denial when the request in question is a REST request — **`WP-ERROR-022`** (REST API category), regardless of which underlying mechanism was attempted. `SF-TAXONOMY-002` Section 2 and `WP-ERROR-022` Section 6 both already reserve "generic `wp-admin` cookie authentication" for this category by name, anticipating this taxonomy; this boundary is the other half of that same, already-agreed line: REST's own cookie-authentication requirement additionally requires a valid `X-WP-Nonce` header or `_wpnonce` parameter beyond the session cookie alone, a REST-specific manifestation this category does not own.
* **A specific third-party authentication or two-factor plugin's own implementation defect** (a JWT plugin incorrectly validating a token, a 2FA plugin's own logic error) — Plugin category. This category owns WordPress core's own authentication mechanisms; it does not own a plugin's own defective reimplementation or extension of them, the same reasoning `SF-TAXONOMY-002` Section 5 and `WP-ERROR-022` Section 6 already apply to REST-specific third-party authentication plugins. See Section 5 for the specific two-factor candidate considered and rejected.
* **Database credential or connection failures underlying an authentication attempt** (for example, `wp_authenticate()` failing because the user table cannot be queried at all) — Database category (`WP-ERROR-002`/`007`/`008`/`009`/`018`, as applicable). This category presumes the database itself is reachable and queryable; it owns only the identity-verification logic operating on the result, not a database-layer failure masquerading as a login failure.
* **A missing PHP extension or unsupported PHP version preventing authentication code from running at all** (for example, a hashing function requiring `sodium` or `openssl` being unavailable) — PHP Runtime category (`WP-ERROR-014`/`015`). This category presumes the PHP runtime itself is fully capable; it owns only a verified, business-logic-level authentication or authorization failure, not a runtime-capability failure that happens to manifest during a login attempt.
* **A request blocked before it ever reaches WordPress's own authentication code** — a web application firewall, a security plugin's own IP-blocking or brute-force-lockout feature, or a hosting-level rule — Security category (once a taxonomy exists for it). This can present identically to a login failure from the requester's perspective (a rejected `wp-login.php` submission) but is categorically different: WordPress's own `wp_authenticate()` pipeline is never reached at all, as opposed to being reached and correctly rejecting invalid credentials.
* **Password-reset / lost-password recovery flow failures** (`retrieve_password()`, reset-key generation or validation) — considered and explicitly deferred, not folded into any entry below; see Section 5.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-024` | WordPress Login Authentication Failure | Credential verification — `wp_authenticate()`/`wp_signon()` correctly reject a login attempt (or accept one that should have been rejected) due to a verified cause in WordPress's own credential-checking logic, across every entry point that ultimately calls this same core pipeline (`wp-login.php`, XML-RPC, a plugin's own programmatic `wp_signon()` call) | Existing, Production Ready |
| `WP-ERROR-025` | WordPress Authentication Cookie Invalid or Expired | Session persistence — a user previously authenticated successfully, but a *subsequent* request's own auth-cookie validation (`wp_validate_auth_cookie()`) fails: cookie expiration, tampering, a scheme/domain mismatch (`COOKIE_DOMAIN`, `SECURE_AUTH_COOKIE`), or invalidation caused by a secret-key/salt rotation or a forced logout | Existing, Production Ready |
| `WP-ERROR-026` | WordPress Capability or Role Authorization Denied | Post-authentication authorization, non-REST — a user is verifiably authenticated (Section 2's boundary with `WP-ERROR-024`/`025` already crossed successfully), but `current_user_can()` or an equivalent role/capability check denies access to a `wp-admin` page, an admin-ajax action, or a plugin/theme-defined capability gate | Existing, Production Ready |
| `WP-ERROR-027` | WordPress Nonce Verification Failure (Non-REST) | Request-origin/freshness verification, non-REST — `wp_verify_nonce()`/`check_admin_referer()`/`check_ajax_referer()` reject a request as stale, forged, or mismatched to the acting user's own session, outside the REST API pipeline `WP-ERROR-022` already owns | Existing, Production Ready |

All four entries now exist and are Production Ready; the Authentication category's initial planned baseline is complete. Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Model

Unlike REST API's three entries (`SF-TAXONOMY-002` Section 4), which form a strictly sequential, server-enforced pipeline, this category's four conditions are **commonly co-occurring but conceptually independent** — closer to how `WP-ERROR-014`/`015` (PHP Runtime) relate than to how `WP-ERROR-021`–`023` relate. WordPress does not enforce a single fixed order across `wp_authenticate()`, cookie validation, nonce verification, and capability checks the way `WP_REST_Server::dispatch()` enforces route → accept → execute; a given plugin or theme's own code may check a nonce before or after a capability check, and a request may never reach a nonce or capability check at all if authentication itself already failed.

The four conditions are nonetheless mutually exclusive by verified cause, not merely by careful wording:

* `WP-ERROR-024` presumes no valid, prior authenticated session exists yet — the *current* credential-verification attempt is what fails.
* `WP-ERROR-025` presumes a valid authenticated session *did* previously exist — the failure is in that session's own subsequent persistence, not in the original credential check.
* `WP-ERROR-026` presumes authentication (current or persisted) has already succeeded — the failure is a capability decision made about an already-identified user.
* `WP-ERROR-027` presumes the request's own origin/freshness token, not the user's identity or capability, is what is being rejected — a genuinely orthogonal axis from the other three, the same way PHP extension availability and PHP version are orthogonal in `WP-ERROR-014`/`015`.

Evidence shall establish which of the four conditions is actually present before an entry is applied; symptoms can overlap (both a cookie failure and a nonce failure can present as "you have been logged out" or "are you sure you want to do this?" style messaging) even though the underlying verified cause is disjoint.

**Classification rule for the `WP-ERROR-025`/`WP-ERROR-027` overlap:** a nonce generated while logged out and submitted after a later login, or generated under one user/session and submitted under another, can superficially look like either a session problem or a nonce problem, since both involve the request's own session context having changed since the markup was rendered. The classification is decided by the *current* authentication session's own fate, not by the nonce's own history:

* If WordPress rejects the current session or cookie itself — `wp_validate_auth_cookie()` fails — the condition is `WP-ERROR-025`, regardless of what the nonce would or would not have validated against.
* If WordPress accepts the current session, but the submitted nonce does not match that session's own user context (because the nonce was generated under a different, earlier user/session state), the condition is `WP-ERROR-027`. The session having *changed* since the nonce was generated is the nonce's own problem to fail on, not evidence the current session itself is invalid.

This rule is stated once, here, rather than duplicated with potentially drifting wording across both entries' own Distinction sections; each entry's own text cross-references this section rather than restating it.

---

## 5. Candidates Considered and Rejected

* **Two-Factor / Multi-Factor Authentication plugin failures:** not given an entry. No 2FA capability exists in WordPress core; every 2FA mechanism in real-world use is a plugin's own addition, with its own implementation-specific failure modes. Per this taxonomy's own Section 2 exclusion, a plugin's own defective logic belongs to the Plugin category, not here. Should 2FA-adjacent conditions prove common enough to warrant dedicated coverage, that is a future Plugin-category entry (or a future dedicated category), not a retroactive addition to this one.
* **Password-reset / lost-password recovery flow:** not given an entry in this initial set. `retrieve_password()` and reset-key validation are conceptually adjacent to `WP-ERROR-024` (an alternate credential-establishment path) but involve their own distinct mechanics (key generation, email delivery — which itself borders the newly-approved `Email` category, per the Knowledge Production Plan) that were judged to deserve dedicated treatment rather than being folded into Login Authentication Failure's scope, or rushed into this initial four-entry set. Deferred, not rejected outright; a future revision to this document may add it once Email category boundaries exist to disambiguate the delivery-failure portion.
* **XML-RPC as a separate entry:** not given its own entry. XML-RPC authentication ultimately calls the same `wp_authenticate()` core pipeline `WP-ERROR-024` already owns; a login failure reached via `xmlrpc.php` is the same verified condition as one reached via `wp-login.php`, differing only in entry point. `WP-ERROR-024`'s own Scope section (once authored) shall explicitly include XML-RPC as a covered entry point rather than treating this as a gap.
* **CAPTCHA / bot-mitigation plugin rejections:** not given an entry. A CAPTCHA challenge failing is a plugin-added gate in front of WordPress's own authentication pipeline, not a condition of `wp_authenticate()`/`wp_signon()`/`current_user_can()` themselves — Plugin category, per the same reasoning as the two-factor exclusion above.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial taxonomy: WP-ERROR-024 (Login Authentication Failure), WP-ERROR-025 (Authentication Cookie Invalid or Expired), WP-ERROR-026 (Capability or Role Authorization Denied), WP-ERROR-027 (Nonce Verification Failure, Non-REST), forming four mutually-exclusive-by-cause conditions rather than a strict pipeline. Explicitly disambiguated from WP-ERROR-002 (Database Authentication Failure, an unrelated concept sharing only a name) and from WP-ERROR-022 (REST API Access Denied, which already reserved this category's territory by name in SF-TAXONOMY-002 Section 2 and WP-ERROR-022 Section 6). Two-factor authentication, password reset, XML-RPC as a separate entry, and CAPTCHA/bot-mitigation considered and rejected or deferred, per Section 5. | Frozen |
| 1.1 | 2026-07-14 | WP-ERROR-024 reached Production Ready (SF-REVIEW-070 author review, SF-REVIEW-071 independent review). Status column updated from Planned to Existing, Production Ready in the same body of work as the promotion, per SF-SPEC-013 Section 5.7. No boundary content changed. | Frozen |
| 1.2 | 2026-07-14 | WP-ERROR-025 reached Production Ready (SF-REVIEW-072 author review, SF-REVIEW-073 independent review, which corrected an attribution error in WP-ERROR-025's own text about a WP-ERROR-024 boundary case rather than in this taxonomy). Status column updated from Planned to Existing, Production Ready in the same body of work as the promotion, per SF-SPEC-013 Section 5.7. No boundary content changed. | Frozen |
| 1.3 | 2026-07-14 | WP-ERROR-026 reached Production Ready (SF-REVIEW-074 author review, SF-REVIEW-075 independent review, which also corrected a stale generic Authentication-category hedge in WP-ERROR-022 and SF-TAXONOMY-002, unrelated to this taxonomy's own content). Status column updated from Planned to Existing, Production Ready in the same body of work as the promotion, per SF-SPEC-013 Section 5.7. No boundary content changed. | Frozen |
| 1.4 | 2026-07-14 | WP-ERROR-027 reached Production Ready (SF-REVIEW-076 author review, SF-REVIEW-077 independent review, all-Conforming). Status column updated from Planned to Existing, Production Ready in the same body of work as the promotion, per SF-SPEC-013 Section 5.7. All four planned entries now exist and are Production Ready; the Authentication category's initial planned baseline is complete. No boundary content changed. | Frozen |
| 1.5 | 2026-07-14 | Corrected per `SF-REVIEW-078` (Authentication category consistency review), Finding C-2: added an explicit classification rule to Section 4 for the WP-ERROR-025/WP-ERROR-027 overlap (a nonce generated before a session change) — the current session's own acceptance or rejection decides classification, not the nonce's own history. No entry boundary changed; the rule formalizes a distinction both entries already implemented consistently. | Frozen |
