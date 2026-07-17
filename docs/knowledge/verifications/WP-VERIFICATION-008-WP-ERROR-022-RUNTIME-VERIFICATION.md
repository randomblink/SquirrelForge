# WP-VERIFICATION-008 — WP-ERROR-022 Runtime Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` series convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, and `SF-SPEC-011`.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-008`

**Date:** 2026-07-17

---

# 2. Associated Scenario or Artifact

`WP-ERROR-022` — WordPress REST API Access Denied, Version 1.1.

---

# 3. Objective

Determine whether current WordPress runtime behavior agrees with the corrected request-acceptance boundary for cookie/nonce authentication, Application Passwords, permission denial, built-in argument validation, pre-dispatch short-circuiting, and the adjacent WP-ERROR-021/023 stages.

**Expected behavior, per the entry:**

- A cookie supplied without a REST nonce is treated as anonymous and does not produce `rest_cookie_invalid_nonce`; a supplied invalid nonce produces that code with HTTP 403.
- A permission callback using `rest_authorization_required_code()` distinguishes anonymous HTTP 401 from authenticated-but-underprivileged HTTP 403.
- Application Password success, unavailability, invalid credentials, and revoked credentials can be diagnosed by combining request results with the mechanism's configured and credential state.
- Required-argument absence and schema rejection return `rest_missing_callback_param` and `rest_invalid_param` before the main callback runs.
- A `rest_pre_dispatch` denial can prevent the main callback from running after a valid route is requested.
- `rest_no_route` and an error returned by the main callback remain adjacent but separately owned controls.

---

# 4. Baseline

- Repository: clean `agent/wp-verification-008-research` at corrected baseline `30c45d4`; no repository file changed during runtime research.
- WordPress: 7.0.1, newly installed in a disposable directory.
- WP-CLI: current official PHAR.
- PHP: 8.2.29 CLI and built-in HTTP server.
- Database: official WordPress SQLite Database Integration 2.2.23.
- Environment type: `local`; Application Password support and availability both returned true before request-scoped fault injection.
- Healthy controls: REST root HTTP 200; public identity route HTTP 200 as user 0; protected route HTTP 200 as administrator through Application Password and cookie-plus-valid-nonce authentication.

Hospital, Thematic, every pre-existing Local site, and every certified repository artifact were excluded from the environment.

---

# 5. Environment

- macOS 26.5.2, Darwin 25.5.0, x86_64.
- Disposable site, SQLite database, official downloads, router, authentication helper, and fixtures existed only under `/private/tmp/sf-verification-008`.
- A must-use plugin registered public identity, administrator-only, argument-validation, and callback-failure routes under `sf/v1`.
- The protected callback incremented a persistent counter. A request-scoped `rest_pre_dispatch` filter returned a 403 only when explicitly triggered, allowing the counter to prove whether the main callback ran.
- A request-scoped `wp_is_application_passwords_available` filter allowed availability to be disabled without altering certified knowledge or another site.
- Temporary administrator and subscriber cookies/nonces and one temporary Application Password were created only in the disposable database.
- HTTP evidence was collected with an API client (`curl`), including exact method/path, status, and body. WP-CLI recorded environment, availability, callback counters, and credential inventory.

---

# 6. Execution Procedure

1. Installed WordPress, activated SQLite integration, created administrator and subscriber controls, enabled pretty permalinks, and loaded the isolated routes.
2. Confirmed the REST root, route registration, anonymous identity, and authorized administrator request.
3. Sent an administrator login cookie without a REST nonce to the identity route, then repeated with a deliberately invalid nonce and with the valid session nonce.
4. Requested the protected route anonymously and as the authenticated subscriber.
5. Created one Application Password and tested valid credentials, request-scoped feature unavailability, invalid credentials, and the original credentials after revocation.
6. Requested the argument route with the required parameter absent and with a value outside its registered enum.
7. Reset the protected callback counter, triggered `rest_pre_dispatch` denial, inspected the counter, removed the request trigger, repeated the healthy request, and inspected the counter again.
8. Requested a nonexistent route and the callback-failure route as WP-ERROR-021 and WP-ERROR-023 negative controls.
9. Confirmed environment settings and that no Application Password remained, stopped the server, and removed the complete disposable environment before drafting this record.

---

# 7. Evidence Artifacts

- **REST controls:** `GET /wp-json/` returned HTTP 200 and exposed namespace `sf/v1`; `GET /wp-json/sf/v1/identity` returned HTTP 200 with `{"user_id":0,"login":false}`.
- **Authorized controls:** valid Application Password and valid administrator cookie plus `X-WP-Nonce` each returned HTTP 200 with `{"ok":true,"user_id":1,"login":"sfadmin"}`.
- **Missing nonce:** administrator cookie without a nonce returned HTTP 200 from the identity route with user 0 and no error code.
- **Invalid nonce:** the same cookie plus `X-WP-Nonce: deliberately-invalid` returned HTTP 403 with `rest_cookie_invalid_nonce`, message `Cookie check failed`, and status 403.
- **Authorization distinction:** anonymous protected request returned `rest_forbidden` HTTP 401; authenticated subscriber plus valid nonce returned the same code/message with HTTP 403. The callback counter did not increase for either denial.
- **Application Password states:** valid credentials returned authenticated HTTP 200. With availability disabled for that request, valid credentials reached permission logic anonymously and returned HTTP 401. Invalid credentials and the formerly valid credentials after deletion also reached permission logic anonymously and returned HTTP 401. The final credential inventory was zero.
- **Missing parameter:** `GET /wp-json/sf/v1/params` returned HTTP 400, `rest_missing_callback_param`, and named `kind`.
- **Invalid parameter:** `GET /wp-json/sf/v1/params?kind=wrong` returned HTTP 400, `rest_invalid_param`, and nested `rest_not_in_enum` detail for `kind`.
- **Pre-dispatch denial:** valid administrator authentication plus `sf_pre_deny=1` returned HTTP 403 with `sf_pre_dispatch_denied`; the protected callback counter remained 0. Removing the trigger returned HTTP 200 and advanced the counter to 1.
- **Route negative control:** nonexistent `/sf/v1/does-not-exist` returned HTTP 404 with `rest_no_route`.
- **Callback negative control:** `/sf/v1/failure` ran its callback, advanced its separate marker to 1, and returned HTTP 500 with `sf_callback_failed`.
- **Final environment state:** WordPress 7.0.1, `local`, Application Passwords supported and available, zero Application Passwords, and the protected healthy control successful.

---

# 8. Validation

The runtime matrix confirms WP-ERROR-022 Version 1.1. Missing and invalid supplied nonces followed the corrected separate paths. Permission denial, argument validation, and pre-dispatch short-circuiting all prevented protected business logic from running. Route resolution failure and callback-generated failure remained distinct adjacent stages.

Application Password unavailability, invalidity, and revocation produced the same external anonymous 401 on this protected custom route. They remained diagnostically distinguishable only through controlled knowledge of availability and credential inventory; this agrees with the entry's instruction to test the intended mechanism rather than infer its cause from status alone.

**Differences from documentation:**

1. No discrepancy was found against WP-ERROR-022 Version 1.1 or SF-TAXONOMY-002 Version 1.5.
2. No third-party JWT/OAuth mechanism was executed; custom authentication remains source/architecture-verified.
3. The runtime matrix did not emulate natural nonce expiry because an invalid supplied nonce exercises the same `wp_verify_nonce()` failure branch; expiry remains source-verified.

**Required repository changes:** None to WP-ERROR-022, SF-TAXONOMY-002, the REST API baseline, or other certified knowledge. This change adds only this record, its reviews, and applicable verification-status navigation.

---

# 9. Negative Validation

- Anonymous and authenticated-underprivileged requests returned different statuses only because the fixture deliberately used `rest_authorization_required_code()`; no universal custom-endpoint 401/403 claim is made.
- Equal external 401 responses did not collapse disabled, invalid, and revoked Application Password causes; controlled availability and credential inventory identified their different states.
- `rest_no_route` proved route resolution had not reached this entry's acceptance gates.
- The callback-generated HTTP 500 and marker proved business logic ran, placing that result under WP-ERROR-023 rather than this entry.
- Callback counters proved permission, parameter, and pre-dispatch conclusions independently of response wording.
- No pre-existing website, Hospital installation, Thematic installation, authentication plugin, or production credential participated.

---

# 10. Cleanup Evidence

- The final protected request succeeded after the pre-dispatch trigger was absent and its callback counter advanced exactly once.
- The temporary Application Password was revoked and the final inventory count was zero.
- The localhost PHP server was terminated.
- The disposable WordPress site, SQLite database, users, cookies, nonces, Application Password, fixtures, router, WP-CLI PHAR, and downloaded archives were removed from `/private/tmp`.
- No `/private/tmp/sf-verification-008` path remained.
- The repository branch remained clean after runtime research.

---

# 11. Repository Validation Evidence

After the complete verification and review artifact set existed, `scripts/validate-repo.sh .` passed, the complete PHPUnit suite passed (146 tests, 338 assertions), every PHP file under `src/` and `tests/` passed `php -l`, Markdown links passed, and `git diff --check` passed.

---

# 12. Classification

**Permanent.** This record fixes runtime evidence for WP-ERROR-022 Version 1.1's nonce, permission, Application Password, argument-validation, pre-dispatch, adjacent-stage, recovery, and cleanup claims.

---

# 13. Retention Decision

Retain permanently. Future third-party authentication execution shall be recorded separately rather than retroactively broadening this record.

---

# 14. Traceability Map

- `WP-ERROR-022` Version 1.1: direct subject.
- `SF-TAXONOMY-002` Version 1.5: category ownership.
- Correction commit `268cdc3`, merged through `30c45d4`: nonce, Application Password availability, and mechanism-path correction completed before runtime research.
- `SF-REVIEW-189`–`192`: correction reviews and REST API Knowledge Baseline v3.
- Verification reviews: `SF-REVIEW-193` and `SF-REVIEW-194`.

---

# 15. Engineering Review Status

Reviewed via `SF-REVIEW-193` (Class A) and `SF-REVIEW-194` (Class B). Both approved with no open findings.

---

# 16. Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-17 | Initial record. Confirms nonce distinction, permission statuses, Application Password states, argument validation, pre-dispatch callback exclusion, adjacent-stage controls, recovery, and cleanup. | Reviewed via SF-REVIEW-193/194 |
