# WP-VERIFICATION-017 — Database Query Timeout Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and `SF-SPEC-015`.

## 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-017`  
**Status:** Complete and closed after independent review

## 2. Associated Artifact

`WP-ERROR-009` — WordPress Database Query Timeout, Version 1.2.

## 3. Objective

Determine whether a specific database query can exceed an independently enforced limit after WordPress has established a healthy, authenticated, selected, and privileged database connection, and distinguish server statement timeout, lock-wait timeout, PHP execution-time, driver read-timeout, and gateway timeout behavior.

**Expected behavior:** WP-ERROR-009 assigns a timeout to this entry only after the database connection is usable and a specific query is executing. The enforcing layer must be identified because database-server, driver, PHP, and gateway limits have different signals and connection consequences.

## 4. Runtime Methodology

- A fresh disposable WordPress 7.0.1 runtime was created under `/private/tmp/sf-verification-017` with PHP 8.5.7, WP-CLI 2.12.0, mysqli/mysqlnd, and an isolated MariaDB 12.3.2 server on `127.0.0.1:33179`.
- WordPress Core was instantiated from the preserved Tier-1 cache after SHA-1 and SHA-256 re-verification. WP-CLI was reused from its signed cached artifact after SHA-256 re-verification.
- The healthy database snapshot SHA-256 was `c73009fb2309af39460e7d3f84604a71d6fec1ebef80fbdf697946daf2633945`; the healthy WordPress snapshot SHA-256 was `e17ccbcb2b9b3ebd214802cfcea635424d60784af8a6885eb1fbdde60ba88b3e`.
- Runtime execution remained under `/private/tmp`. Documentation corrections were performed separately on `agent/wp-verification-017-docs`; evidence was never committed with those corrections.
- Existing Local site stacks, including the Atheist site, were excluded from inspection, configuration, and execution.

## 5. Case Summary

| Case | Scenario | Runtime result | Disposition |
|---|---|---|---|
| 01 | MariaDB server statement timeout | `SELECT SLEEP(2)` stopped at approximately 0.20 seconds with `ER_STATEMENT_TIMEOUT`, error 1969, SQLSTATE `70100`; `SELECT 1` succeeded on the same connection | Accepted |
| 02 | InnoDB lock-wait timeout | A confirmed blocker transaction caused the WordPress contender to fail at approximately one second with error 1205, SQLSTATE `HY000`; same-connection and rollback controls passed | Accepted |
| 03 | PHP `max_execution_time` caused by database wait | On Darwin, two three-second database waits completed despite a one-second PHP limit, while CPU controls timed out | Platform-deferred |
| 04 | `mysqli` client read timeout | Stock WordPress does not configure the pre-connect option; applying it after connection had no effect. Lower-level pre-connect controls timed out and closed the client connection | Implementation-deferred |
| 05 | PHP-FPM or reverse-proxy wall-clock timeout | No governed disposable gateway stack was available; existing Local site stacks were excluded | Platform-deferred |

## 6. Accepted Evidence

### Case 01 — Server statement timeout

Two fresh WordPress processes produced MariaDB error 1969 and SQLSTATE `70100` after approximately 0.20 seconds. Each process captured the failed query, then ran `SELECT 1` and a normal WordPress options query on the unchanged connection ID. The frozen package SHA-256 is `b1dc4c8ab42fb16da56cab96979a966341cf9e4f15aa29b9400450381d210631`.

### Case 02 — Lock-wait timeout

The accepted executions used separate blocker and contender connections. The repeated blocker record confirmed `@@in_transaction=1` while holding the row lock. The contender failed after approximately one second with error 1205 and SQLSTATE `HY000`, then passed same-connection and ordinary WordPress controls. Blocker rollback restored the fixture to `healthy`. The frozen package SHA-256 is `ce436396338d0b598be673a234db2400c3027c2e2459dd8cc9b61be597660ba2`.

The first Case 02 setup attempt failed to hold a concurrent lock and was explicitly excluded. It contributes no evidence to the accepted result.

## 7. Deferred and Feasibility Outcomes

### Case 03 — PHP execution-time path

PHP's one-second limit was active, demonstrated by two CPU-bound HTTP 500 controls at approximately one second. The database-wait requests nevertheless completed successfully after approximately three seconds. This matches PHP's documented non-Windows behavior: database-query time is excluded from `max_execution_time`. Windows and other wall-clock-enforcing paths were not executed. The feasibility package SHA-256 is `9f0eaa6990a884f3d5b6a75e5f57af7eac870b1396ea56f0ef1db33cdae16ff7`.

### Case 04 — Driver read-timeout path

Two stock WordPress processes accepted an after-connect `MYSQLI_OPT_READ_TIMEOUT` call but completed the slow query normally. WordPress 7.0.1 Core initializes and connects `mysqli` without configuring that option. Three lower-level pre-connect controls demonstrated the driver capability, failing after approximately one second with error 2006 and SQLSTATE `HY000`; their client connections were unusable afterward and no server-side query remained. These controls qualify the environment but are not stock WordPress target evidence. The feasibility package SHA-256 is `509b26560025d542764d74ad532d5d255ce4017d4b5f9aa82684103ee1f1521b`.

### Case 05 — Gateway path

No standalone governed PHP-FPM, Nginx, or equivalent disposable request-timeout stack was available. Existing Local site processes were excluded rather than reused. The feasibility record SHA-256 is `7bc36321db51dec151c94ed016b163103330458729cac296699ec27e6ff697e4`.

## 8. Validation

**Differences from documentation:**

1. WP-ERROR-009 Version 1.0 presented one MariaDB error-1969 message as universal. MariaDB 12.3.2 emitted a different version-specific message while retaining stable `ER_STATEMENT_TIMEOUT`, error 1969, and SQLSTATE `70100` identifiers.
2. WP-ERROR-009 Version 1.1 named PHP `max_execution_time` as a possible query-timeout layer without stating that non-Windows PHP excludes database-query time while Windows measures real elapsed time.

**Required repository changes:**

1. WP-ERROR-009 Version 1.1 corrected MariaDB identification; reviews `SF-REVIEW-232`–`235` established Database Knowledge Baseline v2.
2. WP-ERROR-009 Version 1.2 corrected PHP execution-time portability; reviews `SF-REVIEW-236`–`239` established Database Knowledge Baseline v3.
3. No further correction is required for the accepted Case 02 result or the deferred Case 04/05 paths.

## 9. Negative Validation and Boundaries

- Case 01's server statement timeout and Case 02's lock-wait timeout retained usable WordPress database connections.
- Case 04's pre-connect driver timeout closed its lower-level client connection, distinguishing it from the two accepted server-side cases.
- Case 03's CPU control is PHP Runtime behavior when no database query is responsible and is not WP-ERROR-009 target evidence.
- Case 05 establishes no gateway behavior; it records only the absence of a governed disposable stack.
- No accepted case involved authentication failure, missing database, privilege denial, connection capacity, network unreachability, or table corruption.

## 10. Recovery and Cleanup

Healthy controls preceded fault injection. After each accepted or feasibility case, temporary transactions were rolled back, fixtures were removed or the canonical database snapshot was restored, WordPress Core checksums passed, database tables checked cleanly, and ordinary WordPress queries returned without `wpdb` error. The disposable PHP server was stopped after Case 03. All runtime-only scripts were removed after their evidence copies were frozen.

## 11. Independent Review Outcomes and Evidence Limits

Every accepted and deferred case received a separate review pass with the same-agent limitation disclosed. Runtime coverage is specific to WordPress 7.0.1, PHP 8.5.7, mysqli/mysqlnd, MariaDB 12.3.2, InnoDB, and Darwin. Windows, PHP-FPM, reverse-proxy wall-clock timeouts, alternate drivers, custom database drop-ins, and hosting-specific wrappers remain unexecuted.

## 12. Final Disposition

`WP-VERIFICATION-017` is complete and closed. The accepted evidence supports WP-ERROR-009's database-server statement-timeout and lock-contention boundaries on an otherwise usable connection. PHP execution-time, stock-WordPress driver read-timeout, and gateway paths are explicitly deferred with documented reasons. Two runtime-discovered documentation defects were corrected separately before closure; WP-ERROR-009 Version 1.2 is the evaluated artifact and Database Knowledge Baseline v3 is current.

## 13. Traceability

- Case 01 package: `/private/tmp/sf-verification-017/evidence/WP-VERIFICATION-017-CASE-01.tar.gz`.
- Case 02 package: `/private/tmp/sf-verification-017/evidence/WP-VERIFICATION-017-CASE-02.tar.gz`.
- Case 03 feasibility package: `/private/tmp/sf-verification-017/evidence/WP-VERIFICATION-017-CASE-03-FEASIBILITY.tar.gz`.
- Case 04 feasibility package: `/private/tmp/sf-verification-017/evidence/WP-VERIFICATION-017-CASE-04-FEASIBILITY.tar.gz`.
- Case 05 feasibility record and review: `/private/tmp/sf-verification-017/evidence/case-05-gateway-feasibility.txt`, `/private/tmp/sf-verification-017/evidence/case-05-independent-review.md`.
- Correction reviews: `SF-REVIEW-232`–`239`.
- Campaign author review: `SF-REVIEW-240`.
- Campaign independent review: `SF-REVIEW-241`.
