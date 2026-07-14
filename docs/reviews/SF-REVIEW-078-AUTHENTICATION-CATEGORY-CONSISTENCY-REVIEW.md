# SF-REVIEW-078 — Authentication Category Consistency Review

# 1. Review Information

**Review ID:** SF-REVIEW-078

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted as a category-level consistency pass, analogous to `SF-REVIEW-032` (Database), `SF-REVIEW-039` (Filesystem), `SF-REVIEW-052` (REST API), and `SF-REVIEW-056` (PHP Runtime). This is the second category, after REST API, whose entire lifecycle — taxonomy through Production Ready — was executed entirely under `SF-SPEC-013`'s completed governance baseline and entirely after `SF-BASELINE-001` (Framework Baseline v2).

**Status:** Complete

Per explicit project-owner direction, this review treats the four entries as a system rather than re-reviewing each individually: their own author/independent reviews (`SF-REVIEW-070`/`071`, `072`/`073`, `074`/`075`, `076`/`077`) already established each entry's own internal soundness. This review's own scope is the relationships *between* them.

---

# 2. Artifacts Reviewed

1. `WP-ERROR-024` — WordPress Login Authentication Failure
2. `WP-ERROR-025` — WordPress Authentication Cookie Invalid or Expired
3. `WP-ERROR-026` — WordPress Capability or Role Authorization Denied
4. `WP-ERROR-027` — WordPress Nonce Verification Failure, Non-REST
5. `SF-TAXONOMY-003` — Authentication Error Taxonomy, Version 1.4 (the governing plan these four entries were drafted against)

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-004 — Documentation Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification**
- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.4's Baseline Certification Requirements, applied here as the criteria for this consistency pass, per that section's own union with the category-consistency criteria this project has applied since `SF-REVIEW-032`)
- `SF-TAXONOMY-003` — Authentication Error Taxonomy, Version 1.4

---

# 4. Review Scope

Per the established pattern (`SF-REVIEW-032`, `039`, `052`, `056`) and the project owner's own explicit direction, this review verifies:

1. Every failure belongs to exactly one primary boundary: credentials (`024`), session persistence (`025`), authorization (`026`), or request-intent/nonce verification (`027`) — no overlap, no gap.
2. The four entries consistently distinguish authentication, authentication *state* (session persistence), authorization, and request intent as four separate concepts, not conflating any pair.
3. `WP-ERROR-026` remains capability-centered even in passages that discuss roles.
4. `WP-ERROR-027` never implies a nonce authenticates a user, authorizes an action, or prevents replay.
5. Plugin-category and REST-API-category exclusions are drawn consistently across all four entries.
6. Diagnosis (Section 11 of each entry) starts broad enough to rule out upstream/adjacent conditions before narrowing to the entry's own specific cause.
7. Cross-reference symmetry, taxonomy status accuracy, review-record citation accuracy, and metadata consistency.
8. The specific `WP-ERROR-025`/`WP-ERROR-027` overlap the project owner flagged (a nonce generated before a session change) is resolved by an explicit, stated rule rather than left to implicit consistency.
9. Whether the stale-generic-category-hedge defect class `SF-REVIEW-075` (IF-1) found recurs elsewhere in this category's own artifacts, as evidence for or against extending `scripts/validate-repo.sh`.

---

# 5. Evidence Examined

- Full re-read of `WP-ERROR-024`, `025`, `026`, `027` in full, post all prior corrections.
- `grep -H "Category:|Severity:|Recovery Priority:|Status:|Version:"` against all four, building a metadata-consistency table.
- `grep -c '^# [0-9]\+\.'` and a bare-`must` sweep (excluding `must-use`) against all four.
- `grep -oE '\]\([A-Za-z0-9_.-]+\.md\)'` against all four, cross-checked against the actual file listing, building a complete citation matrix.
- `grep -n "The following are cited"` against all four, comparing the Related Errors Section 16 intro sentence.
- Full contents of `SF-TAXONOMY-003` at its pre-review state, cross-checked against all four entries' actual `Status` fields.
- `grep -n "Plugin category"` against all four, comparing exclusion wording and logic.
- The opening three steps of each entry's own Section 11 (Diagnosis), compared for breadth and upstream-exclusion coverage.
- A dedicated sweep for "nonce" combined with "authenticat"/"authoriz" in `WP-ERROR-027`, to independently verify no passage implies a nonce performs either function.
- A dedicated sweep for role-centered language in `WP-ERROR-026` that might contradict its own stated capability-centered framing.
- `grep -rn "once a taxonomy exists"` and `"Authentication category)"` across `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`, to test for recurrence of the `SF-REVIEW-075` IF-1 defect class.
- Each entry's own Section 17 (Notes) review-record citations, cross-checked against the actual `SF-REVIEW-070` through `077` files.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| C-1 | Minor | Criterion 6 (diagnosis breadth) | `WP-ERROR-026` and `WP-ERROR-027` both open Diagnosis by explicitly ruling out a REST-context condition (`WP-ERROR-022`) as their first or second step. `WP-ERROR-025` did not, despite its own Section 7 (Scope) explicitly excluding REST API cookie-authentication requirements — a genuine confusion risk, since both a `wp-admin` cookie failure and a REST `rest_cookie_invalid_nonce` failure can present as "I'm logged out." `WP-ERROR-024` was independently assessed as not needing an equivalent step: its own entry points (`wp-login.php`, XML-RPC, programmatic `wp_signon()`) are structurally distinct from REST request handling in a way `WP-ERROR-025`'s shared "logged out" symptom is not, so no comparable confusion risk exists for it. | Diagnosis Section 11, opening steps, all four entries, Section 5 above. | Add an explicit REST-context ruling-out step to `WP-ERROR-025` Section 11, matching the pattern `WP-ERROR-026`/`027` already established. | Resolved |
| C-2 | Minor | Criterion 8 (the flagged overlap) | The `WP-ERROR-025`/`WP-ERROR-027` classification for a nonce generated before a session change was already correctly *implemented* (neither entry's boundary contradicted the other, and independent tracing of both entries' own Scope/Distinction sections confirmed no double-coverage or gap), but the classification *rule itself* was not stated explicitly anywhere as a standalone principle — a future reader diagnosing this exact overlap would have to re-derive it from two separately-worded passages rather than finding one authoritative statement. | `SF-TAXONOMY-003` Section 4 (pre-review state, Section 5 above); `WP-ERROR-025`/`027` Section 6 (pre-review state). | Add an explicit classification rule to `SF-TAXONOMY-003` Section 4, and cross-reference it from both entries' own Distinction sections rather than duplicating the wording. | Resolved |
| — | Conforming | Criterion 1 (single boundary) | Independently re-traced all four entries' own Scope/Distinction sections: `024` (credential verification, no prior valid session presumed), `025` (session/cookie persistence, presumes prior success), `026` (capability, presumes authentication and session already valid), `027` (request-intent/nonce, presumes authentication, session, and capability may all be valid). No condition is claimed by two entries; no gap identified between them. | Section 5 above. | None. |
| — | Conforming | Criterion 2 (terminology) | "Authentication" (identity verification), "authentication state"/session (persistence of that verification across requests), "authorization" (a permission decision about an already-identified user), and "request intent" (nonce-based origin/freshness, independent of identity/permission) are each used consistently across all four entries with no cross-entry drift or conflation found. | Section 5 above. | None. |
| — | Conforming | Criterion 3 (capability-centered) | Independently re-swept `WP-ERROR-026` for role-centered phrasing that might contradict its own stated framing; no instance found where a role name, rather than a capability, is treated as the actual authorization decision. | Section 5 above. | None. |
| — | Conforming | Criterion 4 (nonce is not auth) | Independently re-swept `WP-ERROR-027` for any passage implying a nonce authenticates, authorizes, or prevents replay in the single-use sense; every match is either the entry's own explicit negation of these properties or a mention of authentication/authorization as separate, independently-confirmed preconditions. | Section 5 above. | None. |
| — | Conforming | Criterion 5 (Plugin/REST exclusions) | All four entries exclude a plugin's own *independent* system (not routed through WordPress's own core mechanism) to Plugin category, using materially consistent reasoning; all four correctly exclude REST-context conditions to `WP-ERROR-022`, with `WP-ERROR-024` (via `WP-ERROR-002`'s own disambiguation) additionally excluding the unrelated "database authentication" concept. | Section 5 above. | None. |
| — | Conforming | Criterion 7 (symmetry, taxonomy, citations, metadata) | Full citation matrix confirms `024`↔`025`↔`026`↔`027` fully symmetrical; all four correctly cite `WP-ERROR-022` one-directionally (no reciprocal citation owed, since `WP-ERROR-022` carries no placeholder for any of the four, per the `SF-REVIEW-052` convention, independently re-confirmed for all four rather than assumed from `SF-REVIEW-075`'s own single check). `SF-TAXONOMY-003`'s pre-review status table matched all four entries' actual `Status: Production Ready`. All four entries' own Section 17 review-record citations independently verified accurate against the actual `SF-REVIEW-070`–`077` files. Metadata (`Category: Authentication`, `Severity: Critical`, `Recovery Priority: Immediate`, `Version: 1.0`) identical across all four. Structural compliance (17 `SF-TEMPLATE-004` sections, zero bare `must`) confirmed for all four. Related Errors intro sentence identical across all four, matching the majority wording this catalog standardized on. | Section 5 above. | None. |
| — | Conforming | Criterion 9 (stale-hedge recurrence) | The generic-category-hedge pattern `SF-REVIEW-075` (IF-1) found does **not** recur within this category's own artifacts. Five other "once a taxonomy exists for it" matches were found (`WP-ERROR-021`, `SF-TAXONOMY-002` ×2, `WP-ERROR-024`, `SF-TAXONOMY-003`), but every one of them references the **Security** category specifically, which genuinely still has no taxonomy or entries — those hedges remain accurate, not stale, and are a different defect class entirely (a correct forward-reference to an unproduced category, not a stale reference to a now-produced one). This is disclosed as a negative result: one occurrence, not a repeated pattern, does not yet meet the evidentiary bar the project owner set for extending `scripts/validate-repo.sh`. | `grep -rn "once a taxonomy exists\|Authentication category)"` sweep, Section 5 above. | Not acted on; disclosed in `FRAMEWORK-OBSERVATIONS.md` as a checked-and-not-recurring result, per Section 10 below. | Disclosed |

No Major or Critical findings.

---

# 7. Recommendations

None beyond the corrections already applied.

---

# 8. Outcome

**Approved with Minor Revisions.**

**Basis:** Two Minor findings were identified, both consistency/completeness gaps rather than technical defects in any entry's own individual content, and both corrected and re-validated within this review. No overlap was found across the four entries' own boundaries, terminology is used consistently, `WP-ERROR-026` remains capability-centered throughout, `WP-ERROR-027` never implies a nonce performs authentication/authorization/replay-prevention, Plugin/REST exclusions are drawn consistently, cross-references are fully symmetrical, and the specific overlap the project owner flagged is now resolved by an explicit, singly-stated rule rather than implicit consistency alone.

---

# 9. Gate Decision

This review does not itself grant or withhold any individual artifact's Production Ready status; each of the four entries already satisfied that gate independently. This review instead establishes that the four-entry Authentication category, together with its governing taxonomy, is internally consistent as of this review's completion, per **SF-SPEC-013** Section 5.4. No individual artifact's Status changes as a result of this review; the corrections applied (a diagnosis-ordering addition, an explicit classification rule) are consistency/completeness fixes, not reopened technical findings.

---

# 10. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as every authoring and review pass for all four entries.
- Finding C-2's new classification rule (`SF-TAXONOMY-003` Section 4) remains untested against a real, ambiguous field case; it is derived from principle and independently re-verified consistency, not from an observed misclassification incident.
- Criterion 9's negative result (no recurrence of the stale-hedge pattern) is disclosed as a new `FRAMEWORK-OBSERVATIONS.md` entry for traceability, per the project owner's own evidentiary threshold — not because it changes anything now, but so a *third* occurrence, if one ever surfaces, has two prior data points already on record rather than requiring this review to be re-discovered.
- No runtime scenario or evidence record under `SF-SPEC-002`/`SF-SPEC-003` exists for any of the four entries.

---

# 11. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial category-level consistency review across WP-ERROR-024, 025, 026, and 027. Found and corrected: WP-ERROR-025's Diagnosis section lacked the REST-context ruling-out step its own three-entry-old siblings (026, 027) had already established (C-1); the WP-ERROR-025/027 nonce-before-session-change overlap was correctly implemented but not stated as an explicit rule, now added to SF-TAXONOMY-003 Section 4 (C-2). Confirmed no boundary overlap, consistent terminology, WP-ERROR-026's capability-centered framing held throughout, WP-ERROR-027 never implies nonce authentication/authorization/replay-prevention, consistent Plugin/REST exclusions, full cross-reference symmetry, and accurate taxonomy/citation/metadata state. Confirmed the SF-REVIEW-075 stale-hedge defect class does not recur within this category (five other matches all correctly reference the still-taxonomy-less Security category). | Approved with Minor Revisions |
