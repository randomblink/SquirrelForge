# WP-VERIFICATION-018 — WordPress Database Connection Failure Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and `SF-SPEC-015`.

## 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-018`  
**Status:** Complete and closed after independent review

## 2. Associated Artifact

`WP-ERROR-018` — WordPress Database Connection Failure, Version 1.1.

## 3. Objective

Verify WordPress's general initial database-connection failure presentation and optional `wp-content/db-error.php` override without treating the underlying cause as new evidence for any narrower Database entry.

**Expected behavior:** A failed initial `wpdb::db_connect()` attempt loads `db-error.php` when present; otherwise it constructs WordPress's database-connection error and passes it through `wpdb::bail()` to `wp_die()`. The handled path is distinct from an uncaught PHP fatal.

## 4. Runtime Methodology

- A fresh disposable WordPress 7.0.1 runtime was created under `/private/tmp/sf-verification-018` with PHP 8.5.7, WP-CLI 2.12.0, mysqli/mysqlnd, and isolated MariaDB 12.3.2 on `127.0.0.1:33180`.
- WordPress Core and WP-CLI were instantiated from reverified trusted caches. Core checksums and all database-table checks passed before and after fault injection.
- The healthy database snapshot SHA-256 was `5d17e0a315a26d30dbbc63d3cee33d3f9eb625f6abba4766992c29a7e27ec451`; the WordPress snapshot SHA-256 was `f7cf2e2ecbb4e3c2183962283bcf0cc132c0d513ad0bb6f5aa24eea1a4916b54`.
- An unused loopback port supplied a deterministic connection-refusal trigger. This trigger qualified presentation only; the network cause remains owned and previously verified by WP-ERROR-008.
- Existing Local sites, including the Atheist site, were not accessed or modified.

## 5. Source-Gate Correction

The source gate found that WP-ERROR-018 Version 1.0 incorrectly said initial `db_connect()` failure called `dead_db()`. WordPress 7.0.1 Core directly checks `db-error.php`, otherwise constructs the message and calls `wpdb::bail()`; `dead_db()` serves other database-unavailable call sites.

The correction completed separately as WP-ERROR-018 Version 1.1 through `SF-REVIEW-242`–`245`, establishing Database Knowledge Baseline v4 before runtime fault injection resumed.

## 6. Case Summary

| Case | Scenario | Result | Disposition |
|---|---|---|---|
| 01 | Default initial connection-error presentation | Web request returned HTTP 500 with title `Database Error` and `Error establishing a database connection`; WP-CLI returned its handled connection error | Accepted |
| 02 | Optional `wp-content/db-error.php` override | Two web requests returned the fixture's HTTP 503, plain-text marker, and no-store header; WP-CLI loaded the same marker | Accepted |

The frozen runtime evidence package SHA-256 is `7fd93b23ff03ea7760e602f52a71a6a4f76f511921b1f36fe374d7a6691e62aa`.

## 7. Findings and Ownership

- The default initial failure is handled by WordPress and does not present as an uncaught PHP fatal.
- The optional drop-in replaces the default presentation on web and WP-CLI paths.
- The drop-in controls its own status, headers, and body; Core's source comment requires custom database messages to set an appropriate status themselves.
- The refusal trigger is not new WP-ERROR-008 evidence. WP-VERIFICATION-018 verifies the general WP-ERROR-018 presentation after the connection attempt fails, not the narrower cause.

## 8. Validation

**Differences from documentation:** WP-ERROR-018 Version 1.0's asserted initial `dead_db()` call was incorrect and was corrected before runtime execution. No discrepancy was found against Version 1.1.

**Required repository changes:** WP-ERROR-018 Version 1.0 → 1.1 and Database Knowledge Baseline v4, completed separately through `SF-REVIEW-242`–`245`. No further correction is required.

## 9. Negative Validation

- No authentication, database-selection, permission, schema, corruption, capacity, or query-timeout claim was derived from these cases.
- No uncaught PHP fatal occurred in the default or valid-drop-in paths.
- No defective-drop-in case was needed; WP-ERROR-013 already owns a fatal error in custom drop-in code.

## 10. Recovery and Cleanup

The healthy `DB_HOST` value was restored and the temporary drop-in removed. WordPress Core checksums passed, all database tables checked cleanly, `SELECT 1` and the ordinary WordPress option query succeeded, and `wpdb->last_error` was empty.

## 11. Evidence Limits

Runtime coverage is WordPress 7.0.1, PHP 8.5.7 built-in server and CLI, mysqli/mysqlnd, MariaDB 12.3.2, and Darwin. The same-agent review limitation is disclosed. Exact presentation can differ with localization, WordPress version, SAPI, and custom drop-in implementation.

## 12. Final Disposition

`WP-VERIFICATION-018` is complete and closed. The evidence supports corrected WP-ERROR-018 Version 1.1's default initial connection-error presentation and optional drop-in override. The Database verification tranche and the systematic entry-by-entry Reference Implementation campaign are closed; future verification requires a production incident, relevant code change, or explicit risk signal.

## 13. Traceability

- Runtime package: `/private/tmp/sf-verification-018/evidence/WP-VERIFICATION-018-RUNTIME-EVIDENCE.tar.gz`.
- Case review: `/private/tmp/sf-verification-018/evidence/independent-case-review.md`.
- Correction reviews: `SF-REVIEW-242`–`245`.
- Campaign author review: `SF-REVIEW-246`.
- Campaign independent review: `SF-REVIEW-247`.
