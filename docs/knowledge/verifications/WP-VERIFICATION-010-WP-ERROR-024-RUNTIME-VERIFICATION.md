# WP-VERIFICATION-010 — WP-ERROR-024 Runtime Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and `SF-SPEC-015`.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-010`  
**Date:** 2026-07-19

# 2. Associated Artifact

`WP-ERROR-024` — WordPress Login Authentication Failure, Version 1.1.

# 3. Objective

Determine whether the documented WordPress authentication-pipeline boundary is consistent with runtime behavior across ordinary login and XML-RPC authentication, using browser-session, logout, and REST Application Password observations only as adjacent ownership controls.

# 4. Runtime Methodology

- WordPress 7.0.1 ran only in `/private/tmp/sf-verification-010` using an admitted trusted runtime cache.
- PHP 8.2.29, the PHP built-in server, WP-CLI 2.12.0, and SQLite Database Integration 2.2.23 were used.
- Each accepted case began from the immutable SQLite snapshot SHA-256 `9230ef159ef42dadd53e72f9bfdd64e126a0f46e7f1286468fc194f5a69eeef5` and was restored to that value after execution, with SQLite sidecars removed.
- Hospital, Thematic, Atheist, every existing Local site, and repository artifacts were excluded.
- Runtime-only observers were removed after their cases. The repository was not edited during runtime collection.

# 5. Setup Diagnostics Excluded from Verification Evidence

- The first REST attempt was rejected because Application Password availability was `no` under plain HTTP with `WP_ENVIRONMENT_TYPE=production`; it returned `rest_not_logged_in` HTTP 401. This was an environment diagnostic, not Case 08 evidence.
- A separate readiness check temporarily set only the disposable runtime to `local`. WordPress then reported Application Password availability `yes`, a temporary credential was created, and an authenticated REST request returned HTTP 200. The runtime was restored before Case 08 began.
- The original logout package was not accepted because it did not prove post-logout protected-resource denial. It was replaced by the independently reviewed repeat in Case 06.

# 6. Case Summary

| Case | Scenario | Runtime result | Review disposition |
|---|---|---|---|
| 01 | Existing user, incorrect password | HTTP 200 login error; `wp_login_failed` observed with `incorrect_password`; no authenticated cookie | Accepted with trace limits |
| 02 | Empty password | HTTP 200 empty-password error; no `wp_login_failed` observed; no authenticated cookie | Accepted with trace limits |
| 03 | Empty username | HTTP 200 empty-username error; no `wp_login_failed` observed; no authenticated cookie | Accepted with trace limits |
| 04 | Unknown username | HTTP 200 unknown-user error; `wp_login_failed` observed with `invalid_username`; no authenticated cookie | Accepted with trace limits |
| 05 | Valid browser login boundary control | Credential acceptance HTTP 302; `wp_login` observed; subsequent cookie issuance is `WP-ERROR-025` territory | Accepted with limits |
| 06 | Logout boundary control | Logout HTTP 302; authentication cookies expired; `/wp-admin/index.php` redirected to login; `WP-ERROR-025` territory | Accepted with limits |
| 07 | Valid XML-RPC `wp.getUsersBlogs` | POST HTTP 200, authenticated `isAdmin=1` and expected blog name | Accepted as narrow control with limits |
| 08 | Valid REST Application Password boundary control | `GET /wp-json/wp/v2/users/me` HTTP 200 for `sfadmin`; `application_password_did_authenticate` observed; `WP-ERROR-022` territory | Accepted with limits |

# 7. Runtime Findings

1. Empty username and empty password are distinct pre-credential validation outcomes: both displayed their own login errors without the recorded `wp_login_failed` action.
2. Incorrect-password and unknown-username attempts reached the credential-rejection path and recorded `wp_login_failed` with `incorrect_password` and `invalid_username` respectively.
3. Successful browser credential acceptance reached `wp_login`. Cookie issuance, logout, cookie invalidation, and subsequent protected-resource access are retained only as `WP-ERROR-025` boundary controls.
4. Valid XML-RPC authentication succeeded without browser-session cookies. XML-RPC Application Password failure behavior was not tested.
5. Valid REST Application Password authentication succeeded without browser cookies or XML-RPC. REST Application Password failures remain within the documented `WP-ERROR-022` boundary and were not treated as `WP-ERROR-024` evidence.

# 8. Independent Review Outcomes and Limits

Cases 01–08 received read-only independent assessment. No review identified a contradiction with `WP-ERROR-024` Version 1.1.

The recurring limits are retained:

- Several runtime hook and PHP-observation records are concise observer/server-trace summaries rather than complete raw application logs.
- Cases 05–08 have varying package-completeness limits, including absent standalone cleanup or readiness artifacts; their accepted conclusions remain limited to the preserved facts.
- Case 07's XML request contains a disposable credential and requires controlled archival handling.
- Cases cover WordPress 7.0.1, the named PHP built-in-server and SQLite configuration only; they are not generalized to other web servers, database engines, security plugins, or authentication extensions.

# 9. Validation and Disposition

The evidence supports the current distinction in `WP-ERROR-024` between empty-field validation and credential rejection in `wp_authenticate()`. Browser-session persistence and logout remain adjacent `WP-ERROR-025` controls; REST Application Password authentication remains an adjacent `WP-ERROR-022` control.

**No documentation contradiction was demonstrated.** No taxonomy, knowledge-entry, baseline, or correction change was required by this verification.

# 10. Cleanup and Retention

The disposable runtime was restored after every accepted case. The final reset snapshot matched the certified SHA-256. Runtime evidence remains under `/private/tmp/sf-verification-010`; sensitive ephemeral credentials in preserved XML-RPC material require controlled access or redaction before long-term archival.

# 11. Traceability

- Subject: `WP-ERROR-024` Version 1.1.
- Authentication baseline: `SF-REVIEW-213`.
- Runtime admission: `SF-SPEC-015`.
- Case evidence: `/private/tmp/sf-verification-010/evidence/`.
- Independent case reviews: Cases 01–08, including the accepted Case 06 replacement.

# 12. Engineering Review Status

Reviewed via `SF-REVIEW-214` (Class A) and `SF-REVIEW-215` (Class B). Both approved with no knowledge-correction finding.

# 13. Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-19 | Initial campaign closeout record. | Reviewed via SF-REVIEW-214/215 |
