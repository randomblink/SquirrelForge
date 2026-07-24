# WP-VERIFICATION-016 — Database Server Unreachability Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and `SF-SPEC-015`.

## 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-016`  
**Status:** Complete and closed after independent review

## 2. Associated Artifact

`WP-ERROR-008` — WordPress Database Server Unreachable.

## 3. Objective

Determine whether WordPress's database connection path exposes distinct network-level unreachability boundaries before authentication, database selection, or query execution.

## 4. Runtime Methodology

- A fresh disposable WordPress 7.0.1 runtime was created under `/private/tmp/sf-verification-016` with PHP 8.5.7, WP-CLI 2.12.0, mysqli/mysqlnd, and MariaDB 12.3.2 on `127.0.0.1:3308`.
- The immutable canonical database baseline was `/private/tmp/sf-verification-016/baseline/database.canonical.sql` with SHA-256 `395d5231888553ada13fc31b506fe2f4d99911ac644c56e3cd701bdaea9f150c`.
- Each case began with healthy site, REST, WP-CLI, and direct database controls. Runtime-only configuration changes remained under `/private/tmp`; the SquirrelForge repository and the Atheist site were not modified.
- After each case, configuration and database state were restored, healthy controls were repeated, listeners and sidecars were removed, and the exact canonical baseline was verified.

## 5. Feasibility Diagnostic Excluded from Verification Evidence

The separate Case 03 feasibility diagnostic established that `192.0.2.1:3308` could remain in `SYN_SENT` and time out before TCP establishment. It is cited as trigger qualification only and is not counted as Case 03 evidence.

## 6. Case Summary

| Case | Scenario | Runtime result | Disposition |
|---|---|---|---|
| 01 | Database connection refusal | Unused loopback port returned raw connection refusal; WordPress site and REST returned HTTP 500; WP-CLI reported MariaDB error 2002 | Approved with limitations |
| 02 | Database-host DNS failure | Reserved `.invalid` hostname returned NXDOMAIN; WordPress site and REST returned HTTP 500; WP-CLI reported MariaDB error 2005 | Approved with limitations |
| 03 | Connection-establishment timeout | Target remained `SYN_SENT` with no established TCP state; WordPress returned HTTP 500; recovery passed | Approved with limitations |

The proposed cases were independently reviewed. Frozen evidence packages and reviews are preserved under `/private/tmp/sf-verification-016/evidence/` and `/private/tmp/sf-verification-016/reviews/`.

## 7. Findings and Ownership

- Case 01 demonstrates an immediate unreachable-port boundary before authentication.
- Case 02 demonstrates DNS resolution failure before TCP connection.
- Case 03 demonstrates an incomplete TCP handshake and connection-establishment timeout.
- These cases support WP-ERROR-008's network-unreachability boundary and remain distinct from WP-ERROR-002, WP-ERROR-003, WP-ERROR-004, WP-ERROR-007, and WP-ERROR-009.

## 8. Validation

**Differences from documentation:** None requiring correction. Error codes, wording, timing, resolver behavior, socket-state sampling, and HTTP presentation are implementation- and environment-specific observations.

**Required repository changes:** None to `WP-ERROR-008`, the Database category, or the certified knowledge baseline.

## 9. Recovery, Negative Validation, and Cleanup

Healthy controls preceded each case. Original database configuration and MariaDB service state were restored after each fault. Site and REST controls returned HTTP 200, WP-CLI database checks passed, direct database queries returned `1`, canonical dumps matched the immutable hash, listeners were absent, sidecars were removed, and the repository remained clean.

## 10. Independent Review Outcomes and Evidence Limits

Cases 01–03 were independently reviewed and approved with documented limitations. No packet captures were retained. Case 02's resolver tools returned completed NXDOMAIN responses with zero exit status, and Case 03's auxiliary WP-CLI timeout attempt was interrupted and excluded. The evidence is specific to this macOS runtime, PHP/mysqli stack, MariaDB configuration, and WordPress version; it does not generalize exact driver messages or timing to other platforms.

## 11. Final Disposition

`WP-VERIFICATION-016` is complete and closed. The accepted evidence supports the documented WP-ERROR-008 network-unreachability boundaries. No documentation correction, taxonomy change, or knowledge correction was required.

## 12. Traceability

- Case 01 evidence and review: `/private/tmp/sf-verification-016/evidence/case-01-connection-refusal/`, `/private/tmp/sf-verification-016/reviews/case-01-independent-review.md`.
- Case 02 evidence and review: `/private/tmp/sf-verification-016/evidence/case-02-dns-resolution/`, `/private/tmp/sf-verification-016/reviews/case-02-independent-review.md`.
- Case 03 evidence and review: `/private/tmp/sf-verification-016/evidence/case-03-connection-timeout/`, `/private/tmp/sf-verification-016/reviews/case-03-independent-review.md`.
- Case 03 feasibility diagnostic: `/private/tmp/sf-verification-016/evidence/case-03-timeout-feasibility/diagnostic-report.md`.
- Campaign author review: `SF-REVIEW-230`.
- Campaign independent review: `SF-REVIEW-231`.
