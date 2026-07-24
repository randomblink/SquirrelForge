# WP-VERIFICATION-014 — OPcache Stale-Bytecode Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and `SF-SPEC-015`.

## 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-014`
**Status:** Complete and closed after independent review

## 2. Associated Artifact

`WP-ERROR-035` — OPcache Stale Bytecode.

## 3. Objective

Determine whether stale PHP bytecode can be reproduced in the web runtime and distinguished from source changes, timed timestamp revalidation, and fresh CLI execution.

**Expected behavior:** when timestamp validation is disabled, a running web worker may continue serving cached bytecode after an in-place source change; when validation is enabled, a configured revalidation interval eventually refreshes the bytecode.

## 4. Runtime Methodology

- A disposable WordPress 7.0.1 runtime was operated under Apache with PHP-FPM and SQLite at `/private/tmp/sf-verification-014`.
- The web SAPI was confirmed as `fpm-fcgi`; OPcache was enabled in the PHP-FPM pool.
- The immutable database baseline was `/private/tmp/sf-verification-014/baseline/database.sqlite` with SHA-256 `357f7ad6fa4e90b4d41f745699f4e8fce5c5bebf98c4424efecc5283eaab1359`.
- Each accepted case used a runtime-only probe, healthy HTTP controls, exact snapshot recovery, sidecar cleanup, and repository-status verification.

## 5. Setup Diagnostics Excluded from Verification Evidence

Case 03 included two preliminary file-replacement attempts that refreshed to the new source and therefore did not isolate the intended boundary. They are retained under the case evidence as setup diagnostics only and were excluded from the accepted result. PHP/WP-CLI deprecation observations were tooling noise, not runtime findings.

## 6. Case Summary

| Case | Scenario | Runtime result | Disposition |
|---|---|---|---|
| 01 | Timestamp validation disabled | Web requests continued serving V1 after an in-place source change to V2; restart with validation enabled served V2 | Approved with limitations |
| 02 | Revalidation interval | With validation enabled and `revalidate_freq=5`, immediate request served V1 and a request after six seconds served V2 | Approved with limitations |
| 03 | CLI/web boundary | With web timestamp validation disabled, repeated web requests served V1 after an in-place change while a fresh CLI invocation served V2 | Approved with limitations |

Frozen evidence packages:

- `/private/tmp/sf-verification-014/evidence/case-01-validate-timestamps-disabled/`
- `/private/tmp/sf-verification-014/evidence/case-02-revalidate-freq/`
- `/private/tmp/sf-verification-014/evidence/case-03-cli-web-boundary/`

## 7. Findings and Ownership

- Case 01 demonstrates stale web bytecode when timestamp validation is disabled.
- Case 02 demonstrates delayed refresh under a configured revalidation interval.
- Case 03 demonstrates that a fresh CLI execution can observe updated source while a running web worker retains stale bytecode.
- These results are distinct from incomplete deployment, page/object caching, PHP extension absence, and OPcache unavailability.

## 8. Validation

**Differences from documentation:** None requiring correction. The exact timing, SAPI behavior, probe output, and OPcache status are implementation- and environment-specific observations.

**Required repository changes:** None to `WP-ERROR-035`, the Caching/Performance taxonomy, or the certified knowledge baseline.

## 9. Recovery, Negative Validation, and Cleanup

Healthy site controls preceded and followed each accepted case. Probe restoration, baseline PHP-FPM restoration, exact SQLite snapshot restoration, sidecar removal, listener shutdown, and clean repository status were recorded. Case 03's excluded setup attempts were not used as verification evidence.

## 10. Independent Review Outcomes and Evidence Limits

All three cases were independently reviewed and approved with documented limitations. The evidence is specific to the macOS Apache/PHP-FPM runtime, one tested PHP-FPM pool, the tested OPcache settings, and fresh CLI execution. It does not establish every pool topology, deployment model, or OPcache configuration.

## 11. Final Disposition

`WP-VERIFICATION-014` is complete and closed. The accepted evidence supports the tested WP-ERROR-035 stale-bytecode boundaries. No documentation correction, taxonomy change, or knowledge correction was required.

## 12. Traceability

- Case 01 evidence and review: `/private/tmp/sf-verification-014/evidence/case-01-validate-timestamps-disabled/`, `/private/tmp/sf-verification-014/reviews/case-01-independent-review.md`.
- Case 02 evidence and review: `/private/tmp/sf-verification-014/evidence/case-02-revalidate-freq/`, `/private/tmp/sf-verification-014/reviews/case-02-independent-review.md`.
- Case 03 evidence and review: `/private/tmp/sf-verification-014/evidence/case-03-cli-web-boundary/`, `/private/tmp/sf-verification-014/reviews/case-03-independent-review.md`.
- Campaign author review: `SF-REVIEW-226`.
- Campaign independent review: `SF-REVIEW-227`.
