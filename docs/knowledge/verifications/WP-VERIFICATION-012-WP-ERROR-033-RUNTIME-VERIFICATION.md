# WP-VERIFICATION-012 — Persistent Object Cache Backend Availability Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and `SF-SPEC-015`.

## 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-012`
**Status:** Complete and closed after independent review

## 2. Associated Artifact

`WP-ERROR-033` — Persistent Object Cache Backend Unavailable.

## 3. Objective

Determine whether WordPress 7.0.1 with the selected Redis Object Cache drop-in surfaces distinct persistent-object-cache failures when the Redis backend is unavailable, when a connected backend rejects cache operations, and when the drop-in cannot initialize.

**Expected behavior:** backend and drop-in failures should remain distinguishable from SQLite/database failures and from unrelated HTTP transport errors; a healthy Redis-backed cache should recover after each isolated fault.

## 4. Runtime Methodology

- A disposable WordPress 7.0.1 runtime was created under `/private/tmp/sf-verification-012` with PHP 8.5.7, WP-CLI 2.12.0, SQLite integration, Redis Open Source 8.8.0, Redis Object Cache 2.8.0, and Predis 2.4.0.
- Redis ran on `127.0.0.1:6381`. The selected drop-in and configuration were preserved and hashed before and after each case.
- The immutable SQLite baseline was `/private/tmp/sf-verification-012/baseline/database.sqlite` with certified SHA-256 `24f9aad4093be387c25a54f59bed924b27a7c4b3d141ae10af7d5a29b97a8f0f`.
- Each case began with healthy site/cache controls, used one isolated fault, restored Redis/drop-in/database state, repeated healthy controls, removed sidecars, and rechecked the exact database hash.
- Runtime-only modifications remained under `/private/tmp`; the SquirrelForge repository and the Atheist site were not modified.

## 5. Setup Diagnostics Excluded from Verification Evidence

The runtime bootstrap and Redis provenance work qualified the disposable environment before Case 01. PHP 8.5/WP-CLI deprecation notices were retained as tooling observations and were not treated as WordPress runtime failures. No protocol mock or alternate backend was used.

## 6. Case Summary

| Case | Scenario | Runtime result | Disposition |
|---|---|---|---|
| 01 | Redis backend connection failure | Redis stopped; Predis reported connection refusal during drop-in bootstrap; site returned HTTP 500; recovery controls passed | Approved with limitations |
| 02 | Redis backend operation failure | Redis remained reachable (`PING`), ACL denied writes, Predis reported `NOPERM` for `SETEX`; site returned HTTP 500; ACL and cache recovery passed | Approved with limitations |
| 03 | Drop-in initialization failure | Controlled syntax fault in `object-cache.php`; PHP/WordPress parse failure and HTTP 500 while Redis remained healthy; original drop-in and cache recovery passed | Approved with limitations |

Frozen evidence packages:

- `/private/tmp/sf-verification-012/evidence/case-01-backend-connection-failure/`
- `/private/tmp/sf-verification-012/evidence/case-02-operation-failure/`
- `/private/tmp/sf-verification-012/evidence/case-03-dropin-initialization-failure/`

## 7. Findings and Ownership

- Case 01 demonstrates backend unavailability during Redis Object Cache initialization.
- Case 02 demonstrates an operation-level failure after Redis connectivity has been established.
- Case 03 demonstrates a drop-in initialization failure independent of Redis availability.
- The three cases exercise the documented WP-ERROR-033 ownership boundary without attributing database, PHP-extension, page-cache, or inbound HTTP failures to the entry.

## 8. Validation

**Differences from documentation:** None requiring correction. Exact Predis exception text, ACL wording, parse-error wording, HTTP status presentation, timing, and PHP/WP-CLI behavior remain implementation- and environment-specific observations.

**Required repository changes:** None to `WP-ERROR-033`, the Caching/Performance taxonomy, or the certified knowledge baseline.

## 9. Recovery, Negative Validation, and Cleanup

Healthy Redis cache operations and site HTTP controls preceded each fault. Redis recovery, drop-in restoration, cache set/get, site HTTP 200, exact SQLite snapshot restoration, sidecar removal, and clean repository status were recorded after each case. Case 02's `PING` control distinguished operation denial from connection loss; Case 03's healthy Redis `PING` distinguished drop-in syntax failure from backend unavailability.

## 10. Independent Review Outcomes and Evidence Limits

All three cases were independently reviewed and approved with documented limitations. The evidence is specific to Redis 8.8.0, Redis Object Cache 2.8.0, Predis 2.4.0, PHP 8.5.7, and the macOS disposable runtime. The exact exception wording, stack traces, HTTP 500 presentation, and deprecation output are not portable guarantees. No documentation contradiction was identified.

## 11. Final Disposition

`WP-VERIFICATION-012` is complete and closed. The accepted evidence supports the WP-ERROR-033 backend-availability, operation-failure, and drop-in-initialization boundaries. No documentation correction, taxonomy change, or knowledge correction was required.

## 12. Traceability

- Case 01 evidence and review: `/private/tmp/sf-verification-012/evidence/case-01-backend-connection-failure/`, `/private/tmp/sf-verification-012/reviews/case-01-independent-review.md`.
- Case 02 evidence and review: `/private/tmp/sf-verification-012/evidence/case-02-operation-failure/`, `/private/tmp/sf-verification-012/reviews/case-02-independent-review.md`.
- Case 03 evidence and review: `/private/tmp/sf-verification-012/evidence/case-03-dropin-initialization-failure/`, `/private/tmp/sf-verification-012/reviews/case-03-independent-review.md`.
- Campaign author review: `SF-REVIEW-222`.
- Campaign independent review: `SF-REVIEW-223`.
