# SF-REVIEW-049 — WP-ERROR-022 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-049

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. Conducted in a fresh review pass, beginning from `SF-TAXONOMY-002` and the artifact itself. Preliminary findings and a preliminary outcome were recorded before `SF-REVIEW-048` was re-opened for comparison, per **SF-SPEC-012** Section 8 (Independence).

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-022` — WordPress REST API Access Denied, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-022-REST-API-ACCESS-DENIED.md`. Reviewed in its post-author-review state.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification**
- **SF-SPEC-013 — Knowledge Category Lifecycle Specification**
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**
- `SF-TAXONOMY-002` — REST API Error Taxonomy, Version 1.2

---

# 4. Review Scope

This review independently determines whether WP-ERROR-022 satisfies `SF-TAXONOMY-002`'s declared boundary and SF-SPEC-001's authoring standards, and is eligible to advance from `Draft` to `Production Ready`. It specifically tests whether the diagnostic-philosophy lesson `SF-REVIEW-047` established for `WP-ERROR-021` (begin diagnosis with the least-invasive, broadest, highest-information check) was actually carried forward into this entry's own Diagnosis section, rather than only referenced in this project's own conversational record.

---

# 5. Independence Requirements Applied

Per **SF-SPEC-012** Section 8: began from `SF-TAXONOMY-002` and the artifact itself; independently re-verified `WP-ERROR-021`'s Production Ready status and `WP-ERROR-023`'s non-existence rather than trusting `SF-REVIEW-048`'s own report; independently re-verified the specific technical claims about `rest_authorization_required_code()`'s actual scope; recorded preliminary findings before opening `SF-REVIEW-048`; reached conclusions independently; preserves `SF-REVIEW-048` unmodified.

---

# 6. Preliminary Independent Findings (recorded before reading SF-REVIEW-048)

A fresh, full read of WP-ERROR-022 was performed against SF-SPEC-001's requirements and `SF-TAXONOMY-002`'s declared boundary. Areas checked with no finding: metadata (correct ID, title matching the taxonomy's own table exactly, `REST API` category, Critical, Immediate, Draft, 1.0); failure boundary (matches the taxonomy's Section 3 declaration exactly, including the Section 4 argument-validation placement decision, correctly explained rather than merely asserted); the permission-callback-denial-versus-crash and authentication-versus-authorization internal distinctions, both explicitly drawn; structure (17 sequential sections, none empty, no drafting language, no bare "must" outside "must-use"); the `rest_forbidden`, `rest_cookie_invalid_nonce`, `rest_invalid_param`, and `rest_missing_callback_param` error codes, and Application Passwords' real requirements, all independently re-verified against current documentation and found accurate.

Two findings were identified independently:

| Finding ID | Severity | Observation |
|---|---|---|
| IF-1 | Minor | Section 11 (Diagnosis) moves directly from "confirm this is a request-acceptance denial" (step 1) into error-code-specific investigation (nonce, Application Passwords, permission callback) without an intervening step that actively re-confirms route resolution itself is not the actual issue, before drilling into a specific gate. `SF-REVIEW-047`, one entry earlier, established exactly this principle for this category — begin with the broadest, least invasive, highest-information check — and this entry's own Diagnosis does not carry it forward as an explicit step, even though the entry's own Distinction section already discusses the WP-ERROR-021/022 boundary in the abstract. |
| IF-2 | Minor | Sections 6, 8, and 9 describe `rest_authorization_required_code()` as what "determines" whether a denial surfaces as 401 or 403, without qualifying that this is a helper function a `permission_callback` must actually choose to call to get that distinction right — WordPress's own core endpoints use it, but a custom, third-party endpoint's `permission_callback` is not guaranteed to, and may return a single hardcoded status regardless of authentication state. As worded, a reader could infer the 401/403 distinction is automatic for every REST endpoint, which overstates the mechanism's actual, opt-in scope. |

**Preliminary Outcome (before reading SF-REVIEW-048): Approved with Minor Revisions.** Two Minor, non-architectural findings; neither changes the owned failure boundary; both correctable without redesign.

---

# 7. Comparison with SF-REVIEW-048

`SF-REVIEW-048` was read only after Section 6 above was finalized.

**Classification of SF-REVIEW-048:** Correctly self-identified as Class A — Author Review. Retained as valid author-review history, not treated as independent verification.

**Findings independently reproduced:** `SF-REVIEW-048`'s F-1 (bare-"must" language in Section 4) was already corrected in the artifact this review read; the correction was independently re-verified as present (fresh sweep: zero bare "must" matches outside "must-use").

**New findings absent from SF-REVIEW-048:** Both IF-1 and IF-2 are new. `SF-REVIEW-048`'s own Evidence Examined section confirms the taxonomy's argument-validation placement decision was applied, but its review scope did not check the Diagnosis section's own step ordering against the specific diagnostic-philosophy lesson `SF-REVIEW-047` had just established for this same category one entry earlier — a scope gap in that review's own depth, not a false claim. Nor did it check whether `rest_authorization_required_code()`'s described behavior was universal versus opt-in.

**Effect on this review's outcome:** None. The preliminary outcome (Approved with Minor Revisions) is carried forward unchanged.

---

# 8. Final Findings and Correction

| Finding ID | Severity | Requirement or Criterion | Observation | Required Action | Resolution Status |
|---|---|---|---|---|---|
| IF-1 | Minor | SF-SPEC-001 §12 (Diagnosis Standard — least invasive to most invasive); the diagnostic-philosophy lesson established by SF-REVIEW-047 | Diagnosis does not include an explicit, early step re-confirming route resolution itself is not the actual cause before investigating a specific denial gate. | Insert a step, early in Section 11, confirming the same endpoint resolves (per WP-ERROR-021's own least-invasive checks) under a different credential context, before narrowing into which specific gate denied the request under review. | Resolved |
| IF-2 | Minor | Technical accuracy of a cited mechanism's actual scope | `rest_authorization_required_code()` described as if its 401/403 distinction applies universally, when it is an opt-in helper function a `permission_callback` must choose to call. | Qualify Sections 6, 8, and 9 to state that WordPress core's own endpoints use this helper, but a custom endpoint's `permission_callback` is not guaranteed to, and may not distinguish 401 from 403 at all. | Resolved |

**Correction applied:** Inserted a new early Diagnosis step confirming route resolution under a differing credential context (reusing `WP-ERROR-021`'s own least-invasive checks) before drilling into the specific denial gate, renumbering subsequent steps. Qualified the `rest_authorization_required_code()` description in Sections 6, 8, and 9 to state explicitly that its use is opt-in, not universal.

Re-validated: drafting-language sweep (no match), bare-`must` sweep (no match outside "must-use"), section-numbering sweep (17 sections, Diagnosis sub-items renumbered sequentially), `git diff --check` (clean), link-target re-verification (`WP-ERROR-021` still resolves and is Production Ready).

No Major or Critical findings. All other areas remain Conforming as recorded in Section 6.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass and `SF-REVIEW-048`, though as a distinct pass, independently re-testing whether a lesson from the immediately preceding review was actually carried forward rather than assuming it was because the same process learned it. A reviewer from a genuinely separate party was not used.
- `WP-ERROR-023` remains undocumented; this entry's own conceptual citation to it will need updating once it is created, per the established pattern.
- This entry's technical grounding was verified against external documentation rather than a live WordPress installation exercising an actual denied request; no runtime scenario or evidence record under SF-SPEC-002/SF-SPEC-003 currently exists to demonstrate its diagnosis or recovery steps against a real failure.

---

# 10. Outcome

**Approved with Minor Revisions.**

**Basis:** WP-ERROR-022 is fundamentally sound. Its failure boundary matches `SF-TAXONOMY-002`'s own declaration exactly, including the argument-validation placement decision, its internal distinctions are technically accurate, and its diagnostic safety, recovery safety, validation sufficiency, prevention guidance, security considerations, structure, and normative language all conform without further correction. The two findings raised were a diagnostic-ordering completeness gap (specifically, failing to carry forward the broad-before-narrow lesson the immediately preceding review established for this same category) and a scope-precision qualification, both corrected and re-validated within this same review.

---

# 11. Production Ready Gate Decision

This review satisfies the Production Ready gate defined by **SF-SPEC-001** Section 19 and **SF-SPEC-005** Section 5.6 for `WP-ERROR-022`, per the Class B review authority defined by **SF-SPEC-012** Section 6.2 and Section 12. `WP-ERROR-022`'s Status may accordingly be changed from `Draft` to `Production Ready`.

This gate decision does not designate `WP-ERROR-022` as a Reference Implementation.

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of WP-ERROR-022. Two new Minor findings identified independently of SF-REVIEW-048: a missing broad-before-narrow diagnostic step (the exact lesson SF-REVIEW-047 established one entry earlier for this category), and an overstated claim about rest_authorization_required_code()'s universality. Both corrected and re-validated. | Approved with Minor Revisions — Production Ready gate satisfied |
