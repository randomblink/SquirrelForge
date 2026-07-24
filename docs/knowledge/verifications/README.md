# WP-VERIFICATION-XXX — Series Convention

## Purpose

This directory holds `WP-VERIFICATION-XXX` records: runtime evidence that a specific `WP-ERROR-XXX` entry's documented mechanism, messages, and diagnostic details hold up against a real, current WordPress installation. This is the artifact series for the **Reference Implementation** track (`docs/engineering/KNOWLEDGE-PRODUCTION-PLAN.md` Section 10).

This is a lightweight, informally-adopted convention, in the same spirit as `FRAMEWORK-OBSERVATIONS.md` and `KNOWLEDGE-PRODUCTION-PLAN.md`: it is not itself a specification, is not governed by **SF-SPEC-005**'s review process as a distinct artifact type, and does not require review to edit. It exists to keep every `WP-VERIFICATION-XXX` record structurally comparable over time, not to add ceremony beyond what already governs runtime evidence.

## Template and governance

Every `WP-VERIFICATION-XXX` record uses **`SF-TEMPLATE-005`** (Runtime Evidence Record Template) directly — unmodified, and without adopting `SF-SPEC-003`/`SF-SPEC-007`'s scenario-lifecycle or portfolio machinery, which governs a different artifact series (`WP-SCENARIO-XXX`, demonstrating the broader SquirrelForge agent's own engineering capabilities). `SF-TEMPLATE-005`'s own Section 2 ("Associated Scenario or Artifact") already generalizes beyond `WP-SCENARIO-XXX`, so no new template was drafted. Substantive governance remains **SF-SPEC-002** (Runtime Evidence), **SF-SPEC-006** (Repository Validation), **SF-SPEC-011** (Evidence Governance) — this document restates none of their criteria.

## Required content, within SF-TEMPLATE-005's existing structure

Every record shall additionally, within the indicated existing section, cover the following — not as new sections, but as required content within sections `SF-TEMPLATE-005` already defines, so every record stays comparable without diverging from the shared template:

| Required content | Lives within SF-TEMPLATE-005 section |
|---|---|
| WordPress version, PHP version, database backend | Section 4 (Baseline) and/or Section 5 (Environment) |
| Trigger procedure (the exact, deterministic steps executed) | Section 6 (Execution Procedure) |
| **Expected behavior** — what the associated `WP-ERROR-XXX` entry itself documents should happen, stated explicitly *before* reporting what was observed | Section 3 (Objective), as its own labeled sub-point |
| Observed behavior (exact command output, exact messages, exact source citations) | Section 7 (Evidence Artifacts) |
| Negative validation | Section 9 (already a dedicated section) |
| Cleanup verification | Section 10 (already a dedicated section) |
| **Differences from documentation** — an explicit, itemized list of every place observed behavior diverged from the entry's documented claims, even if the divergence turned out to be immaterial | Section 8 (Validation), as its own labeled sub-point |
| **Required repository changes** — an explicit statement of what, if anything, must be corrected as a result, and in which artifact(s) — or an explicit "None" if the entry was fully confirmed accurate | Section 8 (Validation), as its own labeled sub-point, immediately after Differences from Documentation |

## Why this shape

`WP-VERIFICATION-001` (`WP-ERROR-037`) established the pattern informally, with these two elements folded into prose rather than labeled as their own sub-points. Formalizing them as explicit, consistently-labeled sub-points — rather than drafting a new template — keeps future records easy to scan and compare ("what did this one find different, and what changed as a result?") without duplicating governance `SF-TEMPLATE-005`/`SF-SPEC-002` already provide.

## Index

| Record | Verifies | Result | Repository changes |
|---|---|---|---|
| `WP-VERIFICATION-001` | `WP-ERROR-037` | Mechanism confirmed; message text and capability-grant documentation were wrong | `WP-ERROR-037` v1.0→1.1, `SF-TAXONOMY-007` v1.3→1.4, Media Knowledge Baseline v2 |
| `WP-VERIFICATION-002` | `WP-ERROR-038` | Cause 1 (corrupt source image) confirmed accurate in every respect tested | None |
| `WP-VERIFICATION-003` | `WP-ERROR-036` | Causes 1/2 confirmed accurate; Cause 3 substantially inaccurate (documented mechanism does not exist; real mechanism is multisite-only and different) | `WP-ERROR-036` v1.0→1.1, `SF-TAXONOMY-007` v1.4→1.5, Media Knowledge Baseline v3 |
| `WP-VERIFICATION-004` | `WP-ERROR-019` | Direct-write denial, ancestor-traversal denial, upload behavior, installer path, recovery, and repeatability confirmed; one literal installer quotation had incorrect capitalization/interface attribution | `WP-ERROR-019` v1.0→1.1, `WP-ERROR-020` v1.0→1.1, `SF-TAXONOMY-005` v1.3→1.4, Filesystem Knowledge Baseline v2, Plugin Knowledge Baseline v2 |
| `WP-VERIFICATION-005` | `WP-ERROR-020` | Genuine byte-capacity exhaustion, Site Health, media upload, plugin staging, permission distinction, recovery, and cleanup confirmed; inode/quota execution explicitly deferred; stale size-limit mechanism corrected before completion | Separate correction commit: `WP-ERROR-020` v1.1→1.2, `SF-TAXONOMY-001` v1.3→1.4, `SF-TAXONOMY-007` v1.5→1.6, Filesystem Baseline v3, Media Baseline v4 |
| `WP-VERIFICATION-006` | `WP-ERROR-016` | Pre-load checksum execution, missing/altered/readability detection, environmental dependency distinctions, exact-release recovery, and cleanup confirmed | None |
| `WP-VERIFICATION-007` | `WP-ERROR-021` | Route/path/method mismatch, `rest_endpoints` removal, downstream controls, pre-dispatch distinction, WP-CLI inspection, recovery, and cleanup confirmed; Apache/Nginx/WAF execution explicitly deferred | None |
| `WP-VERIFICATION-008` | `WP-ERROR-022` | Missing-versus-invalid nonce behavior, permission-status distinction, Application Password states, argument validation, pre-dispatch callback exclusion, adjacent-stage controls, recovery, and cleanup confirmed; third-party authentication explicitly deferred | None |
| `WP-VERIFICATION-009` | `WP-ERROR-023` | Callback `WP_Error`, uncaught-exception disruption, `rest_encode_error`, configuration-dependent PHP-output corruption, ownership controls, snapshot recovery, and cleanup confirmed | `WP-ERROR-023` v1.1→1.2 and REST API Knowledge Baseline v4→v5, completed separately before record drafting |
| `WP-VERIFICATION-010` | `WP-ERROR-024` | Ordinary credential validation/rejection and valid XML-RPC pipeline behavior confirmed; browser-session, logout, and REST observations retained as adjacent ownership controls | None |
| `WP-VERIFICATION-011` | `WP-ERROR-028` (with `WP-ERROR-029` and HTTP-response boundary controls) | Connection refusal, DNS failure, and TCP establishment timeout verified; TLS negotiation classified under `WP-ERROR-029`; HTTP 404 response boundary verified; reset candidate not distinct from refusal | None |
| `WP-VERIFICATION-012` | `WP-ERROR-033` | Redis backend connection failure, connected-backend operation failure, and drop-in initialization failure verified; all cases independently reviewed | None |
| `WP-VERIFICATION-013` | `WP-ERROR-034` | Missing page-cache drop-in, administrative no-op, and cache-write failure verified with a deterministic runtime fixture; all cases independently reviewed | None |
| `WP-VERIFICATION-014` | `WP-ERROR-035` | OPcache stale-bytecode behavior verified across disabled timestamp validation, timed revalidation, and CLI/web execution-context boundaries; all cases independently reviewed | None |
| `WP-VERIFICATION-015` | `WP-ERROR-007` | Database authentication, missing-database, permission-denied, and connection-limit boundaries verified against MariaDB; all cases independently reviewed | None |
| `WP-VERIFICATION-016` | `WP-ERROR-008` | Database connection refusal, DNS resolution failure, and connection-establishment timeout verified; all cases independently reviewed | None |
