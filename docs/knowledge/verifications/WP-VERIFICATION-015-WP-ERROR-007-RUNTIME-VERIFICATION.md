# WP-VERIFICATION-015 — Database Connection-Limit Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and `SF-SPEC-015`.

## 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-015`  
**Status:** Complete and closed after independent review

## 2. Associated Artifact

`WP-ERROR-007` — WordPress Database Connection Limit Exceeded.

## 3. Objective

Determine whether controlled MariaDB connection-limit exhaustion produces the documented WordPress database-connection failure boundary, and distinguish it from credential rejection, missing databases, query permission failures, and unrelated HTTP failures.

## 4. Runtime Methodology

- A disposable WordPress 7.0.1 runtime was used under `/private/tmp/sf-verification-015` with PHP 8.5.7, WP-CLI 2.12.0, and MariaDB 12.3.2 on `127.0.0.1:3307`.
- The immutable canonical database baseline was `/private/tmp/sf-verification-015/baseline/database.canonical.sql` with SHA-256 `cdf33596f4c543dea362729d66d257a3867cfe7f82bb656d730122ccf596f699`.
- Each case began with healthy site, REST, WP-CLI, and direct database controls. Runtime-only changes were isolated under `/private/tmp`; the repository and the Atheist site were not modified.
- This campaign's accepted cases restored the database from the canonical baseline, removed runtime sidecars, repeated healthy controls, and verified exact canonical equality before freezing evidence.

## 5. Setup Diagnostics Excluded from Verification Evidence

The first nine-holder attempt did not exhaust the configured limit and returned HTTP 200. It is retained as setup evidence and excluded from the accepted Case 04 result. A failed auxiliary WP-CLI invocation used an incorrect temporary path and is not used as fault evidence.

## 6. Case Summary

| Case | Scenario | Runtime result | Disposition |
|---|---|---|---|
| 01 | Invalid database credentials | MariaDB authentication rejection; WordPress HTTP 500 database error | Approved with limitations |
| 02 | Database does not exist | MariaDB unknown-database path after valid authentication; WordPress HTTP 500 | Approved with limitations |
| 03 | Database permission denied | UPDATE operation denied with MariaDB permission error; recovery passed | Approved with limitations |
| 04 | Connection-limit exhaustion | Ten disposable sessions held connections at `max_connections=10`; site and REST returned HTTP 500 database-error pages; recovery passed | Approved with limitations |

Frozen evidence packages and reviews are preserved under `/private/tmp/sf-verification-015/evidence/` and `/private/tmp/sf-verification-015/reviews/`.

## 7. Findings and Ownership

- Cases 01–04 demonstrate distinct database failure and diagnostic boundaries under the selected MariaDB runtime.
- Case 04 specifically supports `WP-ERROR-007` because the connection-limit cause was deliberately isolated and the WordPress response occurred during database connection establishment.
- The cases do not attribute query permission failures to connection-limit exhaustion, and they do not concern outbound HTTP, object caching, or PHP-extension availability.

## 8. Validation

**Differences from documentation:** None requiring correction. Exact MariaDB error wording, HTTP 500 presentation, timing, and PHP/WP-CLI behavior remain implementation- and environment-specific observations.

**Required repository changes:** None to `WP-ERROR-007`, the Database taxonomy, or the certified knowledge baseline.

## 9. Recovery, Negative Validation, and Cleanup

Healthy controls preceded each fault. Database configuration, credentials, and grants were restored after each case. Case 04 additionally released all holder sessions and restored `max_connections=151`; final site and REST controls returned HTTP 200, WP-CLI database check passed, and direct database control returned `1`. Canonical database restoration matched the immutable SHA-256 exactly, listeners were absent, sidecars were removed, and the repository remained clean.

## 10. Independent Review Outcomes and Evidence Limits

Cases 01–04 were independently reviewed and approved with documented limitations. The evidence is specific to MariaDB 12.3.2, PHP 8.5.7, WordPress 7.0.1, WP-CLI 2.12.0, and the disposable macOS runtime. The Case 04 review notes that no standalone MariaDB error-1040 log line was retained and that an auxiliary fault-time WP-CLI invocation used an invalid path; these do not invalidate the accepted HTTP and process-capacity evidence.

## 11. Final Disposition

`WP-VERIFICATION-015` is complete and closed. The accepted evidence supports the documented database connection-limit boundary of `WP-ERROR-007`. No documentation correction, taxonomy change, or knowledge correction was required.

## 12. Traceability

- Case 01: `/private/tmp/sf-verification-015/evidence/case-01-authentication-rejection/`, `/private/tmp/sf-verification-015/reviews/case-01-independent-review.md`.
- Case 02: `/private/tmp/sf-verification-015/evidence/case-02-database-does-not-exist/`, `/private/tmp/sf-verification-015/reviews/case-02-independent-review.md`.
- Case 03: `/private/tmp/sf-verification-015/evidence/case-03-database-permission-denied/`, `/private/tmp/sf-verification-015/reviews/case-03-independent-review.md`.
- Case 04: `/private/tmp/sf-verification-015/evidence/case-04-connection-limit/`, `/private/tmp/sf-verification-015/reviews/case-04-independent-review.md`.
- Campaign author review: `SF-REVIEW-228`.
- Campaign independent review: `SF-REVIEW-229`.
