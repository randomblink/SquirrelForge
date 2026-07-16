# SF-REVIEW-087 — Networking Category Consistency Review

# 1. Review Information

**Review ID:** SF-REVIEW-087

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted as a category-level consistency pass, analogous to `SF-REVIEW-032` (Database), `SF-REVIEW-039` (Filesystem), `SF-REVIEW-052` (REST API), `SF-REVIEW-056` (PHP Runtime), and `SF-REVIEW-078` (Authentication). This is the third category, after REST API and Authentication, whose entire lifecycle — taxonomy through Production Ready — was executed entirely under `SF-SPEC-013`'s completed governance baseline and entirely after `SF-BASELINE-001` (Framework Baseline v2).

**Status:** Complete

Per the pattern `SF-REVIEW-078` established, this review treats the three entries as a system rather than re-reviewing each individually: their own author/independent reviews (`SF-REVIEW-081`/`082`, `083`/`084`, `085`/`086`) already established each entry's own internal soundness. This review's own scope is the relationships *between* them, and cross-document staleness their sequential authoring may have left behind.

---

# 2. Artifacts Reviewed

1. `WP-ERROR-028` — WordPress Outbound HTTP Request Failure
2. `WP-ERROR-029` — WordPress Outbound TLS Negotiation Failure
3. `WP-ERROR-030` — WordPress CORS (Cross-Origin) Policy Failure
4. `SF-TAXONOMY-004` — Networking Error Taxonomy, Version 1.4 (the governing plan these three entries were drafted against)

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-004 — Documentation Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification**
- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.4's Baseline Certification Requirements, applied here as the criteria for this consistency pass, per the same union with category-consistency criteria this project has applied since `SF-REVIEW-032`)
- `SF-TAXONOMY-004` — Networking Error Taxonomy, Version 1.4

---

# 4. Review Scope

Per the established pattern and this category's own two-axis structure (Section 4 of `SF-TAXONOMY-004`), this review verifies:

1. The two-axis ownership model actually holds: `WP-ERROR-028`/`029` are mutually exclusive sequential stages of the same outbound request; `WP-ERROR-030` is conceptually independent of both, sharing no failure condition with either.
2. Terminology is used consistently across all three — "connection," "transport," "negotiation," "outbound," "cross-origin" each mean the same thing in every entry that uses them.
3. The `WP-ERROR-014` boundary is drawn consistently in both `WP-ERROR-028` and `WP-ERROR-029`, and that `WP-ERROR-029`'s more carefully refined "categorical versus request-specific" framing (added by `SF-REVIEW-084` IF-1) does not silently imply `WP-ERROR-028`'s own, simpler framing is now inconsistent or incomplete.
4. Cross-reference symmetry, taxonomy status accuracy, review-record citation accuracy, and metadata consistency across all three entries.
5. Diagnosis (Section 11) breadth: whether each entry rules out its most easily-confused sibling condition early enough, and whether `WP-ERROR-030`'s later (step 5, not step 1–2) ruling-out of `WP-ERROR-022` is a genuine gap or a reasonable difference in diagnostic entry point.
6. Whether the stale-generic-category-hedge defect class `SF-REVIEW-075`/`078` already found and checked for recurs within this category's own artifacts.
7. Whether sequential authoring — `WP-ERROR-029` widening the taxonomy and refining its own `WP-ERROR-014` boundary after `WP-ERROR-028` was already Production Ready, and `WP-ERROR-030` being authored last — left any stale title, status, or citation behind in an earlier sibling, the same class of defect `SF-REVIEW-052`/`056` each found in their own categories.

---

# 5. Evidence Examined

- Full re-read of `WP-ERROR-028`, `029`, `030` in full, post all prior corrections.
- `grep -H "Category:|Severity:|Recovery Priority:|Status:|Version:"` against all three, building a metadata-consistency table.
- `grep -c '^# [0-9]\+\.'` and a bare-`must` sweep (excluding `must-use`) against all three: 17 sections each, zero bare `must` matches.
- `grep -oE '\]\([A-Za-z0-9_.-]+\.md\)'` against all three, cross-checked against the actual file listing, building a complete citation matrix.
- `grep -n "The following are cited"` against all three, comparing the Related Errors Section 16 intro sentence: identical wording in all three.
- `grep -n "WP-ERROR-030"` against `WP-ERROR-021`/`022`, confirming both now cite this entry by real link rather than the pre-existing `SF-TAXONOMY-002` forward-reference-only wording.
- `grep -n "WP-ERROR-028\|WP-ERROR-029\|WP-ERROR-030"` against `WP-ERROR-014`, confirming zero matches — `WP-ERROR-014` does not cite any of the three back, independently assessed against the `SF-REVIEW-052`/`078` established convention (no reciprocal citation owed where the cited entry carries no forward-reference placeholder for the citing one).
- Full contents of `SF-TAXONOMY-004` at its current (pre-review) state, cross-checked against all three entries' actual `Status` fields.
- Full text of each entry's own Section 6 (Distinction), independently re-read side by side to trace the two-axis ownership model and the `WP-ERROR-014` boundary language in both `WP-ERROR-028` and `WP-ERROR-029`.
- Full text of each entry's own Section 5 (Severity), confirming the incrementally-chained "range-based Critical classification used elsewhere in this catalog" citation is accurate and consistent (`028` cites `021`, `024`–`027`; `029` cites `021`, `024`–`028`; `030` cites `021`, `024`–`029`).
- Each entry's own Section 11 (Diagnosis) opening steps, compared for breadth and upstream-exclusion coverage.
- `grep -rn "once a taxonomy exists\|Networking category)"` across `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`, to test for recurrence of the `SF-REVIEW-075`/`078` stale-hedge defect class.
- `docs/engineering/FRAMEWORK-OBSERVATIONS.md`, read in full, assessing whether any open item is Networking-specific and blocking.
- `scripts/validate-repo.sh .`, run before and after this review's own corrections.
- `git status --short`, confirming a clean working tree before this review's evidence-gathering began.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| C-1 | Minor | Criterion 7 (sequential-authoring staleness) | `WP-ERROR-028` Section 6's own citation of `WP-ERROR-029` still used that entry's pre-widening title ("WordPress Outbound SSL/TLS Certificate Verification Failure"), even though `WP-ERROR-029` was retitled to "WordPress Outbound TLS Negotiation Failure" during its own taxonomy-widening phase (`SF-TAXONOMY-004` v1.0→v1.2), before `WP-ERROR-029` was even authored. `WP-ERROR-028`'s own Section 16 citation of `WP-ERROR-029` had already been corrected to the new title (per `SF-REVIEW-083`'s own account), but the inline Section 6 mention was missed by that same correction pass. `scripts/validate-repo.sh` Check A did not catch this, since it is not a "conceptual reference" citation (the target entry already existed) — it is a stale *title string* attached to a real, resolving link, a class of staleness the validator does not check. | `WP-ERROR-028` Section 6 (pre-correction), Section 5 above. | Update `WP-ERROR-028` Section 6's citation to the current title, and align the accompanying description with `WP-ERROR-029`'s actual (widened) scope, not only its certificate-specific original scope. | Resolved |
| C-2 | Minor | Criterion 7 (sequential-authoring staleness) | `WP-ERROR-028` Section 16's own citation of `WP-ERROR-029` carried the parenthetical "(currently `Draft`)", accurate only at the moment `WP-ERROR-028` was authored (before `WP-ERROR-029` existed at all). `WP-ERROR-029` has since reached `Production Ready`. This is the same status-staleness failure mode `SF-SPEC-013` Section 5.7 names for taxonomy status tables, occurring instead in a sibling entry's own prose rather than a table `scripts/validate-repo.sh` Check B is scoped to check. | `WP-ERROR-028` Section 16 (pre-correction), Section 5 above. | Update the parenthetical to reflect `WP-ERROR-029`'s actual current status. | Resolved |
| — | Conforming | Criterion 1 (two-axis ownership model) | Independently re-traced all three entries' own Section 6 text: `028` (no connection established) and `029` (connection established, secure channel cannot be established) are confirmed mutually exclusive by construction — `029`'s own Section 6 states this explicitly and `028`'s corrected Section 6 (C-1) now agrees. `030` (browser refuses to expose an already-completed response) shares no precondition with either — it presumes the opposite of `028`'s condition (a connection, and in `030`'s case a full request/response cycle, already succeeded) and is independent of `029`'s TLS-negotiation condition entirely, since `030`'s own concern is the browser's connection to WordPress, not WordPress's own outbound connection to anything. No overlap, no gap, across all three. | Section 5 above. | None. |
| — | Conforming | Criterion 2 (terminology) | "Connection" (TCP-layer establishment), "transport" (the mechanism — `curl`/streams — used to attempt a connection), "negotiation" (the TLS handshake specifically, never used for connection establishment or CORS), and "cross-origin"/"CORS" (browser-side policy evaluation, never used to describe WordPress's own outbound behavior) are each used consistently across all three entries with no cross-entry drift or conflation found. | Section 5 above. | None. |
| — | Conforming | Criterion 3 (WP-ERROR-014 boundary) | `WP-ERROR-029`'s own `WP-ERROR-014` distinction bullet uses the refined "categorical gap versus observable, request-specific negotiation failure" framing `SF-REVIEW-084` (IF-1) added, because `WP-ERROR-014`'s own Diagnosis (step 10) names a `curl`-build-without-a-specific-SSL-backend example that is genuinely adjacent to `WP-ERROR-029`'s own protocol/cipher causes. `WP-ERROR-028`'s own `WP-ERROR-014` distinction bullet does not use this same "categorical" language, but independent re-reading of `WP-ERROR-014`'s complete text found no equivalent DNS- or connection-establishment-specific example that would create the same adjacency risk for `WP-ERROR-028`'s own causes — `WP-ERROR-014`'s only granular example concerns an SSL/TLS-specific build gap, not a DNS/TCP-specific one. `WP-ERROR-028`'s simpler "no working transport available at all" framing is therefore not an inconsistency; it is sufficient because the ambiguity that motivated `WP-ERROR-029`'s more careful wording does not exist for `WP-ERROR-028`'s own boundary. This is the same class of "no comparable confusion risk" conclusion `SF-REVIEW-078` reached for `WP-ERROR-024` not needing an equivalent Diagnosis step. | `WP-ERROR-014` full text, `WP-ERROR-028`/`029` Section 6, Section 5 above. | None. |
| — | Conforming | Criterion 4 (symmetry, taxonomy, citations, metadata) | Citation matrix confirms `028`↔`029`↔`030` fully symmetrical (each cites, and is cited by, both others with a real link). `021`/`022` correctly cite `030` by real link (updated in the same work order that created `030`); `014` correctly carries no reciprocal citation to `028`/`029`, consistent with the established `SF-REVIEW-052`/`078` convention. `SF-TAXONOMY-004`'s pre-review status table matched all three entries' actual `Status: Production Ready`. Metadata (`Category: Networking`, `Severity: Critical`, `Recovery Priority: Immediate`, `Version: 1.0`) identical across all three. Structural compliance (17 `SF-TEMPLATE-004` sections, zero bare `must`) confirmed for all three. Related Errors intro sentence identical across all three. | Section 5 above. | None. |
| — | Conforming | Criterion 5 (diagnosis breadth) | `WP-ERROR-029` rules out `WP-ERROR-028` at Diagnosis step 2 (immediately), because a TLS-specific diagnostic step is meaningless before connection establishment is confirmed — an ambiguity that exists from the first symptom onward. `WP-ERROR-030` rules out `WP-ERROR-022` only at step 5, after first isolating the CORS-specific browser signal (console error, preflight vs. real request) at steps 1–4. Independently assessed as *not* a comparable gap: a CORS failure and a `WP-ERROR-022` authentication/authorization failure present with recognizably different browser-console signatures from the very first symptom (a CORS block produces a network-level "blocked by CORS policy" console error with no readable response body at all; a `WP-ERROR-022` denial produces a normal, readable 401/403 response) — unlike `028`/`029`, where the ambiguity is real and immediate. `WP-ERROR-030`'s own diagnostic ordering (inspect the CORS-specific evidence first, confirm genuine cross-origin-ness once that evidence is in hand) is the more natural sequence for its own condition, not a gap. | `WP-ERROR-029`/`030` Section 11, Section 5 above. | None. |
| — | Conforming | Criterion 6 (stale-hedge recurrence) | The generic-category-hedge pattern `SF-REVIEW-075`/`078` checked for does not recur within this category's own artifacts. No `WP-ERROR-028`/`029`/`030` text, and no `SF-TAXONOMY-004` text, contains a "(once a taxonomy exists for it)"-style hedge naming any category at all — this category's own artifacts make no such forward-reference to begin with, so there is nothing to have gone stale. | `grep -rn "once a taxonomy exists\|Networking category)"` sweep, Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Recommendations

None beyond the corrections already applied.

---

# 8. Outcome

**Approved with Minor Revisions.**

**Basis:** Two Minor findings were identified, both cross-document staleness artifacts of this category's own sequential authoring order (a title predating a sibling's own widening, and a status predating that same sibling's own promotion) rather than technical defects in any entry's own individual content. Both were corrected and re-validated within this review. No overlap was found across the three entries' own boundaries; the two-axis ownership model holds exactly as `SF-TAXONOMY-004` Section 4 describes it; terminology is used consistently; the `WP-ERROR-014` boundary is drawn with the appropriate degree of precision in each entry given what `WP-ERROR-014`'s own text actually requires; cross-references are fully symmetrical; and diagnostic ordering differences between entries reflect genuine differences in each condition's own earliest-available evidence, not inconsistent rigor.

---

# 9. Gate Decision

This review does not itself grant or withhold any individual artifact's Production Ready status; each of the three entries already satisfied that gate independently. This review instead establishes that the three-entry Networking category, together with its governing taxonomy, is internally consistent as of this review's completion, per **SF-SPEC-013** Section 5.4. No individual artifact's Status changes as a result of this review; the corrections applied (a stale title, a stale status parenthetical) are consistency/completeness fixes, not reopened technical findings.

---

# 10. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as every authoring and review pass for all three entries.
- C-1/C-2's underlying failure mode (a sibling entry's own prose going stale the moment a later entry is retitled or promoted, invisible to `scripts/validate-repo.sh` because it targets a resolving link rather than a placeholder) is now the second time this general class of defect has surfaced (the first being the `SF-SPEC-013` Section 5.7 observation already on record in `FRAMEWORK-OBSERVATIONS.md`) — this instance is disclosed there as a second data point rather than acted on as a new tooling change, per the same evidentiary-threshold discipline `SF-REVIEW-078`'s own stale-hedge finding applied.
- No runtime scenario or evidence record under `SF-SPEC-002`/`SF-SPEC-003` exists for any of the three entries.
- The two disclosed, unowned gaps `WP-ERROR-028`/`029` each carry (read/response timeout; an HTTP-level error status received after a successful TLS handshake) remain genuinely unowned by any entry in this catalog, unchanged by this review.

---

# 11. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial category-level consistency review across WP-ERROR-028, 029, and 030. Found and corrected: WP-ERROR-028's Section 6 citation of WP-ERROR-029 used that entry's pre-widening title, missed by the earlier Section-16-only correction pass (C-1); WP-ERROR-028's Section 16 citation of WP-ERROR-029 carried a stale "(currently Draft)" parenthetical predating WP-ERROR-029's own promotion (C-2). Confirmed the two-axis ownership model holds exactly as SF-TAXONOMY-004 Section 4 describes, consistent terminology across all three entries, appropriately-scoped WP-ERROR-014 boundary treatment in both WP-ERROR-028 and WP-ERROR-029, full cross-reference symmetry, accurate taxonomy/metadata state, and diagnostic-ordering differences reflecting genuine per-condition evidence availability rather than inconsistent rigor. Confirmed the SF-REVIEW-075/078 stale-hedge defect class does not recur within this category. | Approved with Minor Revisions |
