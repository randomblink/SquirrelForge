# WP-VERIFICATION-009 — WP-ERROR-023 Runtime Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` series convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and the runtime-acquisition gate in `SF-SPEC-015`.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-009`

**Date:** 2026-07-17

---

# 2. Associated Scenario or Artifact

`WP-ERROR-023` — WordPress REST API Response Error, Version 1.2.

---

# 3. Objective

Determine whether an accepted WordPress REST route that reaches its own callback exhibits the documented response-failure classes and remains distinct from route-resolution and request-acceptance failures.

**Expected behavior, per the current entry:**

- A callback can deliberately return a `WP_Error`, producing its own structured REST error response.
- An uncaught exception or fatal condition during callback execution can disrupt the REST response; the visible response depends on WordPress and PHP/server error-display behavior.
- A non-serializable callback value is handled by current Core as structured `rest_encode_error` with HTTP 500.
- PHP output emitted during otherwise-successful callback execution can corrupt the complete REST body even when the callback returns serializable data; this manifestation is configuration-dependent.
- A pre-dispatch or authorization rejection belongs to `WP-ERROR-022`; an unmatched route belongs to `WP-ERROR-021`; a normal callback success remains a control, not an error.

---

# 4. Baseline

- Repository at runtime-start: clean `agent/wp-verification-009-research` at `e4701fd`; the corrected knowledge was then Version 1.1 and REST API Knowledge Baseline v4.
- Final documentation baseline: `WP-ERROR-023` Version 1.2 and REST API Knowledge Baseline v5, merged through `82da424` before this record was drafted.
- WordPress: 7.0.1, `en_US`, acquired from the admitted trusted cache. Cache SHA-256: `f171740cf45b1f5a1bf52194ca914787cd9d8ea078599b430eca951b62b2d000`; Core checksum verification passed.
- PHP: 8.2.29; web server: PHP built-in development server; origin: `http://127.0.0.1:8099`.
- WP-CLI: 2.12.0.
- Database: SQLite Database Integration 2.2.23 using SQLite 3.50.2.
- Host: macOS 26.5.2, Darwin 25.5.0, x86_64.
- Healthy controls: installation, login, dashboard, Site Health interface, REST root, permalink, cron spawn, media import/cleanup, plugin installation from a validated local ZIP, active-theme check, and filesystem writability all passed before target injection.

Hospital, Thematic, every existing Local site, and every certified repository artifact were excluded.

---

# 5. Environment

- The disposable WordPress installation, SQLite database, router, must-use fixture, logs, and case evidence were confined to `/private/tmp/sf-verification-009`.
- The trusted WordPress archive was revalidated before extraction under `SF-SPEC-015`; no unverified download or pre-existing site supplied runtime evidence.
- A disposable must-use fixture registered one route per case under `sf/v1`. Each target callback wrote a marker before returning, throwing, or emitting output, allowing callback execution to be distinguished from earlier REST stages.
- The initial frozen baseline was preserved unchanged. After ordinary SQLite mutation made exact live-database recovery unprovable for the first encoding case, a separate immutable reset snapshot was created. It had SHA-256 `7fa3e8c0ae747022aeb52ed34e9ff1ec9e990b88debc0f0b9d6470e8f339f431`.
- For later cases, automatic WP-Cron was disabled; the PHP server was stopped; the runtime database and `-wal`, `-shm`, and journal sidecars were removed; the immutable snapshot was copied and hash-checked; then the server and healthy REST control were restarted. A repeated callback-`WP_Error` case proved exact restoration before later cases continued.
- HTTP evidence captured status, declared content type, raw body class, callback markers, and PHP error-source information. Each completed fixture was removed and its former route then returned `rest_no_route` HTTP 404.

---

# 6. Execution Procedure

1. Acquired and verified WordPress 7.0.1 from the trusted cache, installed the disposable site, and passed the healthy-control gate.
2. Froze `baseline.json`, then sent one target fault request at a time after a REST HTTP 200 readiness check.
3. Registered a callback that returned a deliberate `WP_Error`; captured its marker, HTTP response, cleanup, and successful recovery.
4. Registered a callback that threw a controlled uncaught `RuntimeException`; captured the marker, raw response, and PHP fatal log.
5. Registered a callback that returned a PHP resource; captured Core's response-generation result. The behavior reproduced successfully, but its first cleanup exposed ordinary SQLite mutation, so no further cases were run until stronger reset mechanics existed.
6. Preserved the original baseline, created and hash-verified the immutable SQLite reset snapshot, disabled automatic WP-Cron, and repeated the callback-`WP_Error` case to prove exact restoration.
7. Registered a callback that triggered `E_USER_WARNING` and returned a normal array; captured the raw response and configuration-dependent output contamination.
8. Ran `rest_pre_dispatch` and anonymous permission denials as `WP-ERROR-022` controls, an unmatched route as a `WP-ERROR-021` control, and a successful callback as the final healthy control.
9. After every snapshot-governed case, stopped the server, restored the exact snapshot with all sidecars removed, restarted the server, and re-ran the REST HTTP 200 readiness control. The final successful callback and REST readiness controls passed.
10. Runtime evidence exposed response-body corruption absent from Version 1.1. Verification paused while the separate `WP-ERROR-023` Version 1.2 correction and REST API Baseline v5 re-certification completed. No further runtime injection was needed to evaluate the corrected claim.

---

# 7. Evidence Artifacts

- **Healthy control:** REST root and the final successful callback each returned HTTP 200 with `application/json; charset=UTF-8`; the final callback marker was present and its body was `{"ok":true,"case":"case-08-successful-callback"}`.
- **Callback returns `WP_Error`:** the callback marker was present. The response was HTTP 422, `application/json; charset=UTF-8`, code `sf_verification_callback_error`, message `Controlled callback WP_Error for WP-VERIFICATION-009.` This is a structured callback-supplied REST error.
- **Uncaught exception:** the callback marker was present. The controlled `RuntimeException` produced PHP fatal HTML rather than JSON, HTTP 200, while the declared content type remained `application/json; charset=UTF-8`. PHP logged the fatal exception and stack trace. This is a configuration-specific runtime result, not a universal REST status claim.
- **Non-serializable resource:** the callback marker was present. Returning a PHP resource produced HTTP 500, `application/json; charset=UTF-8`, code `rest_encode_error`, and message `Type is not supported`. This confirms the Version 1.1 encoding correction and remains unchanged in Version 1.2.
- **Displayed PHP warning after successful return:** the callback marker was present. A controlled `E_USER_WARNING` followed by a normal array return produced HTTP 200 with declared `application/json; charset=UTF-8`, but the body contained an HTML PHP warning and a headers-already-sent warning before the otherwise-valid JSON payload. The complete body was not valid JSON. This is runtime verified only in the recorded PHP built-in-server configuration.
- **Pre-dispatch control:** `rest_pre_dispatch` returned `sf_verification_pre_dispatch_denied`, HTTP 403. Its marker was present and the endpoint callback marker was absent, placing it under `WP-ERROR-022`.
- **Authorization control:** anonymous permission denial returned `sf_verification_permission_denied`, HTTP 401. The permission callback marker was present, current user ID was 0, and the endpoint callback marker was absent, placing it under `WP-ERROR-022`.
- **Missing-route control:** a nonexistent route returned `rest_no_route`, HTTP 404, proving no endpoint callback began and placing it under `WP-ERROR-021`.
- **Recovery and reset:** every fixture was removed; its former route then returned `rest_no_route` HTTP 404; every post-case readiness control returned HTTP 200. The snapshot-governed cases restored the exact saved SQLite SHA-256 with sidecars removed and server stopped before restoration.

---

# 8. Validation

The matrix confirms `WP-ERROR-023` Version 1.2 and its boundary with the adjacent REST entries. Every target case reached the endpoint callback, as shown by its marker. `WP_Error`, uncaught exception, Core-detected JSON encoding failure, and PHP-output response corruption produced materially different client-visible results. The two rejection controls prevented callback execution, and the missing-route control never selected a callback.

**Claim classification:**

| Current documented claim | Classification |
|---|---|
| Callback-supplied `WP_Error` becomes a structured REST error | Runtime verified |
| Non-serializable callback value produces `rest_encode_error` HTTP 500 in current Core | Runtime verified |
| Callback exception/fatal output can disrupt a REST response rather than provide structured JSON | Runtime verified for the recorded PHP built-in-server configuration |
| PHP output can corrupt a serializable callback response body | Runtime verified for the recorded PHP built-in-server configuration |
| `wp_debug_mode()`/`display_errors` timing explains why output behavior varies by PHP/server configuration | Source-supported; not executed across additional SAPIs |
| Underlying warning/output cause remains owned by PHP Runtime, Plugin, Theme, or custom code | Architectural ownership, consistent with `SF-TAXONOMY-002` |
| `rest_pre_dispatch` and permission denial are earlier-stage controls | Runtime verified |
| Unmatched route is an earlier-stage control | Runtime verified |

**Differences from documentation:**

1. Runtime evidence against Version 1.1 revealed an omitted response-corruption class: displayed PHP output can make an otherwise-successful REST body invalid JSON. That defect was corrected separately as `WP-ERROR-023` Version 1.2, reviewed via `SF-REVIEW-204`/`205`, and re-certified through REST API Knowledge Baseline v5 (`SF-REVIEW-206`/`207`) before this record was drafted.
2. No discrepancy remains against Version 1.2. The uncaught-exception and displayed-warning response shapes are explicitly configuration-dependent rather than universal claims.
3. The resource-return case reproduced the target behavior before the immutable snapshot reset procedure existed. Its functional recovery passed, but byte-identical database restoration was not established for that first run. The later snapshot procedure was separately proven before subsequent cases; this limitation is retained rather than retroactively claiming stronger reset evidence for Case 03.
4. Apache, Nginx, PHP-FPM, and production display-error configurations were not executed. Their response-output behavior remains source-supported or unverified, not runtime-generalized.

**Required repository changes:** The Version 1.2 correction and REST API Baseline v5 re-certification were completed separately before this record. No further change to certified knowledge or taxonomy is required. This verification adds only its record, reviews, and verification-status navigation.

---

# 9. Negative Validation

- HTTP status alone did not establish ownership: an uncaught exception and displayed warning each returned HTTP 200 in this configuration but produced invalid REST bodies, whereas a valid successful callback also returned HTTP 200 with parseable JSON.
- `rest_encode_error` HTTP 500 remained distinct from emitted-output corruption: the resource callback returned a non-serializable value and Core supplied a structured error; the warning callback returned serializable data and the body was corrupted externally to JSON encoding.
- `rest_pre_dispatch` and permission controls had no endpoint callback marker, distinguishing `WP-ERROR-022` from target cases.
- `rest_no_route` showed route resolution failed before a callback could run, distinguishing `WP-ERROR-021`.
- No MySQL observation was accepted: two Local MySQL initializations crashed before WordPress installation, so they are environment limitations only, not WP-ERROR evidence.
- No Hospital, Thematic, pre-existing local site, production plugin, or production database participated.

---

# 10. Cleanup Evidence

- The original `baseline.json` remained unchanged.
- The immutable reset snapshot and its SHA-256 were preserved; snapshot-governed cases stopped the server, removed SQLite sidecars, restored the snapshot exactly, and passed healthy REST readiness before the next injection.
- All fixture routes were removed; their former paths returned `rest_no_route` HTTP 404.
- The final successful callback and REST root controls returned HTTP 200.
- The disposable runtime was removed after evidence collection; no repository artifact changed during runtime execution.

---

# 11. Repository Validation Evidence

After the correction, re-certification, complete verification record, review records, and navigation updates existed, `scripts/validate-repo.sh .` passed; Markdown links passed; every PHP file under `src/` and `tests/` passed `php -l`; the complete PHPUnit suite passed (146 tests, 338 assertions); and `git diff --check` passed. PHPUnit's optional result-cache write was denied by the workspace mount; this tooling warning did not affect test execution or results.

---

# 12. Classification

**Permanent.** This record preserves the WordPress 7.0.1 runtime evidence for `WP-ERROR-023` Version 1.2, including the evidence-driven correction triggered during this verification.

---

# 13. Retention Decision

Retain permanently. Future Apache, Nginx, PHP-FPM, or alternative display-error execution shall be recorded separately rather than being inferred from the PHP built-in-server result.

---

# 14. Traceability Map

- `WP-ERROR-023` Version 1.2: direct subject.
- `SF-TAXONOMY-002` Version 1.5: REST API ownership boundary.
- `SF-SPEC-015`: admitted trusted runtime input and start gate.
- Frozen runtime baseline: `/private/tmp/sf-verification-009/evidence/baseline.json`.
- Immutable reset procedure: `/private/tmp/sf-verification-009/baseline/reset-baseline.json`.
- `64bd901`, merged through `82da424`: Version 1.2 response-corruption correction completed before this record was drafted.
- `SF-REVIEW-204`–`207`: correction reviews and REST API Knowledge Baseline v5.
- Verification reviews: `SF-REVIEW-208` and `SF-REVIEW-209`.

---

# 15. Engineering Review Status

Reviewed via `SF-REVIEW-208` (Class A) and `SF-REVIEW-209` (Class B). Both approved with no open findings.

---

# 16. Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-17 | Initial record. Confirms callback errors, exception/fatal disruption, `rest_encode_error`, configuration-dependent PHP-output corruption, adjacent-stage controls, snapshot recovery, and cleanup. | Reviewed via SF-REVIEW-208/209 |
