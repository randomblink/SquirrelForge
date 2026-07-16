# SF-REVIEW-045 — SF-TAXONOMY-002 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-045

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted as a review of a planning artifact, mirroring `SF-REVIEW-034`'s approach for `SF-TAXONOMY-001`.

**Status:** Complete

This is the first taxonomy document reviewed since `SF-SPEC-013` reached Production Ready. Unlike `SF-TAXONOMY-001`, `SF-TAXONOMY-002` was drafted with the benefit of the specific category-boundary and terminology lessons `SF-REVIEW-034` already surfaced — this review verifies whether those lessons were actually applied, rather than assuming they were because the author process is the same one that learned them.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-002` — REST API Error Taxonomy, at `docs/standards/SF-TAXONOMY-002-REST-API-ERROR-TAXONOMY.md`. Reviewed at Version 1.0 as committed (`4b7b8d9`); corrected to Version 1.1 within this review.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification** (Section 7 Category Standard — confirming `REST API` is an approved category value)
- **SF-SPEC-008 — Versioning Specification** (Section 6 Version Status closed list — checking the artifact's own status-terminology disclaimer, already present at authoring this time)
- **SF-SPEC-012 — Engineering Review Independence Specification** (this review's own classification)
- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1 — the normative requirement this document exists to satisfy)

---

# 4. Review Scope

Mirroring `SF-REVIEW-034`'s six areas, applied to this artifact: (1) artifact classification — planning artifact, no normative overreach, no conflict with the (now Production Ready) `SF-SPEC-013`; (2) category boundary — clear technical boundary, distinguished from every adjacent category value that could plausibly be confused with it, not only the ones discussed during drafting; (3) entry separation — the three entries represent genuinely separate, mutually exclusive stages; (4) rejected candidates — technically justified; (5) completeness-claim framing — "frozen at three" understood as revisable; (6) cross-references and repository validation.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-002`, read in full, both pre- and post-correction.
- `grep -n '\bmust\b'` (zero matches).
- `git diff --check` (clean, both before and after correction).
- `ls docs/knowledge/wp-errors/ | grep -E "WP-ERROR-021|WP-ERROR-022|WP-ERROR-023"` (empty, confirming none of the three planned entries exists yet — this document remains a plan, not a claim about drafted entries).
- **SF-SPEC-001** Section 7's category-value list, confirming `REST API` is an approved value.
- **SF-SPEC-013** Section 5.1, re-read directly, confirming this document's Section 3 (boundary, planned entries, rejected candidates) satisfies its three explicit sub-requirements.
- A deliberate technical walk-through of the WordPress REST request lifecycle (`WP_REST_Server::dispatch()`) to independently assess where argument/schema validation actually occurs relative to the "callback has been selected and begun executing" dividing line the taxonomy draws between `WP-ERROR-022` and `WP-ERROR-023`.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| C-1 | Minor | Category Boundary completeness, per the standard `SF-REVIEW-034` already established for this class of document | Section 2's exclusion list addressed Database, PHP Runtime, Plugin, Authentication, CORS, and REST-specific web-server/permalink concerns, but did not address three other plausible points of confusion: (a) a general Bootstrap/PHP-Runtime/Filesystem-level failure that happens to also prevent REST routing from running, as a downstream consequence rather than a REST-specific condition; (b) a route's own registration code never loading because the defining file is unreadable (a Filesystem-category permission condition, not a "route not found" condition in the sense `WP-ERROR-021` owns); (c) a request blocked before ever reaching WordPress at all by a WAF, security plugin, or hosting rule — which presents identically to `WP-ERROR-021`'s own symptoms (a failed request to `/wp-json/`) but is categorically distinct, since WordPress's own routing logic is never reached. | Direct re-read of Section 2 against the three additional adjacent-category scenarios. | Add all three exclusions to Section 2, following the same pattern (name the category, explain the distinguishing fact) already used for the other five. | Resolved |
| — | Informational | Entry separation precision (`WP-ERROR-022`/`023` boundary) | WordPress's own built-in argument and schema validation for a REST route (`validate_callback`/`sanitize_callback`, invoked via `$request->has_valid_params()`) executes during `WP_REST_Server::dispatch()`, technically *before* the route's main callback function is invoked in most cases — yet the taxonomy's own Section 3 (following the user's explicit direction) assigns "schema validation fails" to `WP-ERROR-023` ("callback execution and response generation"), on the conceptual side of the boundary that begins only once the callback executes. This is not a taxonomy-level defect: the taxonomy operates at a conceptual level (has a callback been identified and is the request now proceeding toward it, versus is it now actually running), and that framing is sound. It is flagged here as a precision question the two entries' own eventual authoring (not this taxonomy) will need to resolve explicitly — likely by defining `WP-ERROR-022`'s boundary as ending, and `WP-ERROR-023`'s as beginning, at "the point WordPress commits to invoking the specific route's callback and begins preparing to do so," rather than the literal moment the callback function is called, so that built-in argument validation is consistently placed on one side. | `WP_REST_Server::dispatch()`'s documented request-handling order. | None required of this taxonomy document. Recorded as a note for `WP-ERROR-022`/`023`'s own drafting. | Deferred to entry authoring |
| — | Conforming | Artifact classification | No `shall`/`must` normative-authority language found. The Document Information block includes the `SF-SPEC-008` non-versioned-artifact disclaimer proactively, applying `SF-REVIEW-034`'s own correction from the outset rather than requiring this review to reintroduce it. | `grep -n '\bmust\b'` (zero matches); direct read of Document Information. | None. |
| — | Conforming | Entry separation (Criterion 3) | The three-stage progression (route resolution → authentication/authorization → callback execution) is mutually exclusive by construction: a given request occupies exactly one stage at the moment of failure. The diagram in Section 4 makes this explicit rather than leaving it implicit. | Section 3 and Section 4, cross-read. | None. |
| — | Conforming | Rejected candidates (Criterion 4) | The CORS rejection is technically sound: a CORS failure can occur after the WordPress REST pipeline itself has already completed successfully (route resolved, auth succeeded, callback executed, response returned), meaning it does not share any of the three entries' own failure condition, correctly justifying exclusion from the category entirely rather than folding it into any entry. The third-party-plugin rejection correctly identifies a defect that belongs to a different category (Plugin) than the observable condition (`WP-ERROR-022`) this taxonomy owns. | Section 5. | None. |
| — | Conforming | Completeness-claim framing (Criterion 5) | Section 3's "nothing else is currently planned... per SF-SPEC-013 Section 5.7" correctly frames the three-entry set as the current plan, revisable through the process SF-SPEC-013 now formally requires, not a permanent ceiling. | Section 3. | None. |
| — | Conforming | Cross-references and repository validation (Criterion 6) | `REST API` is confirmed a valid SF-SPEC-001 §7 category value. No `WP-ERROR-021`/`022`/`023` file exists yet, consistent with their `Planned` status. `SF-SPEC-013` is cited accurately (Production Ready, Section 5.1/5.7 as described). | Section 5 evidence above. | None. |

No Major or Critical findings.

---

# 7. Recommendations

- When `WP-ERROR-022` and `WP-ERROR-023` are drafted, resolve the argument/schema-validation timing question (the Informational finding above) explicitly in each entry's own Distinction section, rather than leaving it to be inferred from this taxonomy's conceptual framing alone.

This recommendation is not a condition of this review's outcome.

---

# 8. Outcome

**Approved with Minor Revisions.**

**Basis:** One Minor finding (C-1, a category-boundary completeness gap) was identified and corrected within this review. One Informational finding was recorded for future entry-authoring attention, requiring no correction to this document itself. The three-entry decision (`021`/`022`/`023`), the deterministic-progression framing, and both rejected-candidate determinations are all confirmed sound and are not reversed.

---

# 9. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of SF-TAXONOMY-002. Found and corrected one Minor category-boundary completeness gap (missing Bootstrap/Filesystem-downstream and Security/pre-WordPress exclusions). Recorded one Informational note on the WP-ERROR-022/023 argument-validation timing boundary, deferred to those entries' own future authoring. No entry decision reversed. | Approved with Minor Revisions |
