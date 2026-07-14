# Framework Observations

## Purpose

This document records implementation observations encountered while applying the SquirrelForge engineering framework.

It is intentionally informal.

It is not governed by the engineering specification library.

It is not reviewed.

It is not versioned.

It serves only as implementation feedback that may justify future framework improvements.

---

## 2026-07-13 — Formalize review success criteria (SF-SPEC-005)

Observed across `SF-REVIEW-035` (WP-ERROR-019 author review): the first zero-defect author review in this catalog. Attributable to upstream taxonomy work (`SF-TAXONOMY-001` + its own `SF-REVIEW-034` correction) absorbing the kind of gaps an author review typically exists to catch, not to reduced scrutiny — the Evidence Examined section shows the same depth of independent verification as every prior review.

As entries increasingly draw on a corrected, frozen taxonomy, zero-defect reviews should become more common, not less. Worth considering, when time permits: an explicit addition to **SF-SPEC-005** stating that a review's completeness is measured by whether every criterion in scope was actually evaluated against recorded evidence, not by whether a finding was produced — and that an all-Conforming outcome is a valid, complete result on its own, not a signal the review under-delivered. This would remove any implicit pressure to "find something" to justify a review's existence.

Not acted on now; deferred per explicit direction to proceed with entry authoring (WP-ERROR-020) rather than a framework change at this point.

**Acted on 2026-07-14:** formalized as `SF-SPEC-005` Section 5.7 (Review Completeness), Version 1.1. Author review `SF-REVIEW-054`, independent review `SF-REVIEW-055`; `SF-SPEC-005` reached `Production Ready` — the second specification in this library, after `SF-SPEC-013`, to do so.

---

## 2026-07-14 — SF-SPEC-013 Section 5.7 is correctly identified but not yet self-enforcing

Observed across `SF-REVIEW-052` (REST API category consistency review): even in the first category authored entirely under `SF-SPEC-013`'s completed governance baseline, two defects recurred that are the exact classes Database's and Filesystem's own category reviews already found — a sibling entry citing a now-existing entry as a stale conceptual placeholder, and a taxonomy document's own status table going stale the moment a later sibling was promoted. `SF-SPEC-013` Section 5.7 already names this second failure mode explicitly and requires it not happen, yet it happened anyway, in the very category meant to demonstrate the specification working end to end.

This suggests the requirement is correctly identified but currently depends entirely on a dedicated, later consistency review to catch a violation — it is not self-enforcing at the moment the violating commit is made (an entry's own promotion, or a sibling's own cross-reference, is a point-in-time snapshot that goes stale the instant a later commit changes what it describes). Worth considering, when time permits: whether a lighter-weight, mechanical check (for example, a `grep` step run as part of every entry-promotion commit, checking whether any other file in the repository cites the entry being promoted as `(conceptual reference...)`) could catch this at the moment of the violation rather than requiring a full category consistency review to surface it after the fact.

Not acted on now; disclosed per `SF-REVIEW-052`'s own recommendation to record it as a framework observation rather than act on it mid-review. Does not block REST API's own baseline certification, since it describes a process characteristic of this catalog's authoring workflow generally, not a defect specific to the REST API category's own entries.

**Acted on 2026-07-14:** added `scripts/validate-repo.sh`, a standalone, manually-run mechanical check for both named defect classes. It intentionally scopes its "conceptual reference" sweep to *live* citing documents only (`docs/knowledge/wp-errors/*.md`, `docs/standards/SF-TAXONOMY-*.md`) and excludes `docs/reviews/*.md`, whose point-in-time text is preserved by design per **SF-SPEC-012** Section 4.3 and **SF-SPEC-013** Section 5.8 — a scoping refinement of this entry's own "any other file in the repository" phrasing, made explicit in the script's own header comment. On its first run against the current repository, Check B (taxonomy/entry status drift) was clean, but Check A found a real, previously undetected defect: `WP-ERROR-014` (PHP Runtime category) still cites `WP-ERROR-015` as a conceptual reference at two locations, even though `WP-ERROR-015` has existed and been Production Ready since `SF-REVIEW-009`. Unlike Database, Filesystem, and REST API, the PHP Runtime category (`WP-ERROR-013`/`014`/`015`) has never had a dedicated category consistency review under **SF-SPEC-013** Section 5.4, which is almost certainly why this went uncaught. Not remediated in this pass — remediating a knowledge entry's own content is out of scope for a framework-observation closure and belongs to a dedicated category consistency review, matching the `SF-REVIEW-032`/`039`/`052` precedent.

**Remediated 2026-07-14:** `SF-REVIEW-056` (PHP Runtime category consistency review) corrected the stale citation and a related terminology inconsistency; `SF-REVIEW-057` certified PHP Runtime Knowledge Baseline v1. `scripts/validate-repo.sh` confirmed clean before and after.

**Second data point, 2026-07-14 (`SF-REVIEW-087`, Networking category consistency review):** a related but narrower variant recurred — not a "conceptual reference" placeholder (`scripts/validate-repo.sh` Check A's own target), but a sibling entry's own prose (an inline title in `WP-ERROR-028` Section 6, a `(currently Draft)` status parenthetical in `WP-ERROR-028` Section 16) going stale the moment `WP-ERROR-029` was retitled and later promoted — a *resolving link* whose accompanying text nonetheless drifted, which Check A's own pattern-matching (scoped to unresolved `(conceptual reference...)` citations) does not check and was not designed to. Corrected within `SF-REVIEW-087` itself. Disclosed here as a second, narrower data point in the same general failure family Section 5.7 names (a point-in-time snapshot going stale the instant a later commit changes what it describes), not yet meeting the evidentiary bar for a validator change on its own.

---

## 2026-07-14 — Revision History section missing from eleven of thirteen specifications

Observed across `SF-REVIEW-055` (`SF-SPEC-005` independent review, IF-1): a repository-wide sweep (`grep -n "^#.*Revision History" docs/standards/SF-SPEC-0*.md`) found that only `SF-SPEC-013` and, as of this same review cycle, `SF-SPEC-005` currently carry a top-level Revision History section as `SF-TEMPLATE-001` Section 11 requires. The other eleven specifications (`SF-SPEC-001` through `004`, `006` through `012`) do not — including `SF-SPEC-008`, the Versioning Specification itself, whose own Section 5.3 ("Revision History") is a normative requirement about what a revision history must contain, not that document's own revision history.

This gap was found while independently re-verifying `SF-REVIEW-054`'s F-1 (which fixed the same gap in `SF-SPEC-005` alone, without performing the cross-specification sweep its own Recommendations section flagged as unperformed). It is disclosed here rather than fixed in that review, consistent with `SF-SPEC-005` Section 5.4's prohibition on a review recommendation introducing an unrelated architectural change.

Not acted on now. Worth considering, when time permits: a dedicated pass adding a Revision History section (with an honest, undated-review-disclosing Version 1.0 row, matching the pattern `SF-SPEC-005`'s own Version 1.0 row now sets) to each of the eleven affected specifications — likely low-risk, mechanical work suited to the validation-script direction already under consideration for the `SF-SPEC-013` Section 5.7 observation above.

**Acted on 2026-07-14:** ownership, scope, and disclosure policy defined first (per explicit user direction not to patch quietly): `SF-SPEC-004` Section 5.9, added and reviewed via `SF-REVIEW-058`/`059`, now owns the requirement that a Revision History section exist, and establishes that its earliest row shall accurately state whether a dedicated review record exists rather than either inventing one or wrongly claiming none exists. The eleven-specification gap was then migrated: `SF-SPEC-004` via its own review cycle; the remaining ten (`SF-SPEC-001`, `002`, `003`, `006`–`012`) via a single batch pass verified by `SF-REVIEW-060`. `scripts/validate-repo.sh` gained a third check (Check C) mechanically enforcing this requirement going forward.

---

## 2026-07-14 — "No dedicated review record" is a claim that requires a full-text search, not a filename check

Observed across `SF-REVIEW-059` (`SF-SPEC-004` independent review, IF-1): both `SF-REVIEW-054` (for `SF-SPEC-005`) and the initial draft of `SF-REVIEW-058` (for `SF-SPEC-004`) concluded "no dedicated engineering review record was produced for this version" by checking for a review file named after the artifact (`SF-REVIEW-XXX-SF-SPEC-NNN-*`) and finding none. Both conclusions were wrong: `SF-REVIEW-002` ("Specification Library Review") is a real, six-phase, substantive review record covering `SF-SPEC-001` through `011` — including both files — under a title that does not embed either artifact's ID. A filename-pattern search cannot find a review filed under a different organizing name.

This is the same underlying failure mode `SF-REVIEW-042`'s IF-1 and `SF-REVIEW-055`'s IF-1 each independently found in a different form: a claim of thoroughness ("all citations accurate," "no dedicated review record") that had not actually been tested by the specific search that would falsify it. Worth considering, when time permits: whether `scripts/validate-repo.sh` or a documented review-preparation checklist should require a full-text `grep` for the artifact's own ID across all of `docs/reviews/` before a Revision History row may claim no review record exists, rather than relying on filename convention alone.

Not acted on now; the two known instances (`SF-SPEC-004`, `SF-SPEC-005`) were corrected directly within `SF-REVIEW-059` and a follow-up disclosure row (`SF-SPEC-005` Version 1.2). Assessed as low risk for the ten specifications `SF-REVIEW-060` migrated, since that review's own Section 5/6 independently re-checked each citation against `SF-REVIEW-002`'s and `SF-REVIEW-005`'s actual text rather than repeating the unverified-absence pattern.

**Classified 2026-07-14:** formally assessed as an **accepted limitation**, not a blocking defect, under `SF-SPEC-014` Section 5.5 (`SF-REVIEW-064`) — it has a stated resolution path above and both known instances are already corrected. Not required to be resolved before a Framework Baseline may be declared.

---

## 2026-07-14 — Generic category-hedge staleness: one occurrence, checked for recurrence, not (yet) found

Observed across `SF-REVIEW-075` (`WP-ERROR-026` independent review, IF-1): `WP-ERROR-022` and `SF-TAXONOMY-002` both carried a stale "Authentication category (once a taxonomy exists for it)" hedge, invisible to `scripts/validate-repo.sh` because it matches no `WP-ERROR-XXX` ID and so falls outside Check A's pattern-matching. Corrected in both.

Per explicit project-owner direction — "check whether the category-level review exposes enough repeated stale-hedge cases to justify extending the validator... repetition... would provide stronger evidence for a tooling change" — `SF-REVIEW-078` (Authentication category consistency review) deliberately searched for recurrence of this exact defect class across all four Authentication entries and `SF-TAXONOMY-003`. Result: **not found to recur**. Five other "once a taxonomy exists for it" matches exist in the repository (`WP-ERROR-021`, `SF-TAXONOMY-002` ×2, `WP-ERROR-024`, `SF-TAXONOMY-003`), but every one references the **Security** category specifically, which genuinely has no taxonomy or entries yet — those hedges remain accurate forward-references, not stale ones, and are not the same defect class.

Not acted on now; one occurrence does not meet the evidentiary bar the project owner set for a validator change. Recorded here specifically so that a second real occurrence, if one ever surfaces, has this negative result already on record rather than requiring re-discovery — two data points (one positive, one deliberately-checked negative) will make a third occurrence's evidentiary weight easier to assess than starting from zero.

---

## 2026-07-14 — A taxonomy's independent review verifies claims against the entries it names, not against every category a boundary section might silently encroach on

Observed while drafting `WP-ERROR-032` (Plugin category): `SF-TAXONOMY-005` Version 1.0/1.1's own Section 2 claimed that a plugin update's own file-replacement step failing due to a permission or capacity constraint was this category's own condition. This was wrong — `WP-ERROR-019` (Filesystem Permission Denied) and `WP-ERROR-020` (Disk Space Exhausted), both predating this taxonomy, already explicitly name the `wp-content/upgrade` staging directory and WordPress's own "Installation Failed: Could Not Create Directory." message as their own territory. A second overlap existed for the same reason: `WP-ERROR-028`/`029` (Networking) already explicitly name "plugin/theme/core update checks" and the `api.wordpress.org` outbound request as their own territory — the update package's own download step.

`SF-REVIEW-089`, the taxonomy's own independent review, did not catch either overlap. Its own evidence-gathering (per its Section 5) independently re-verified every claim the taxonomy made about `WP-ERROR-013`, `016`, and `017` — the entries the taxonomy's own text named directly — but the taxonomy's Section 2 never named `WP-ERROR-019`/`020`/`028`/`029` at all; it asserted a boundary claim about "filesystem" and "update" territory in the abstract without citing the specific entries that might already occupy it. A review that verifies every *named* claim can still miss a claim the artifact never thought to name in the first place.

This is a related but distinct failure mode from the `SF-SPEC-013` Section 5.7 staleness family (a citation going stale after the fact) and from the generic-hedge pattern (`SF-REVIEW-075`/`078`/`087`) — this is a taxonomy asserting ownership of territory without first checking whether an *existing, unrelated* category already claimed it, because the relevant entries were never on the reviewer's own checklist to begin with. Worth considering, when time permits: whether a taxonomy's own independent review should include a mandatory sweep — for every category the taxonomy's own boundary section touches even implicitly (not only the categories/entries it explicitly cites) — checking whether an existing entry in that category already makes a conflicting claim, rather than relying on the taxonomy's own text to have already flagged every relevant neighbor.

Not acted on now; corrected directly in `SF-TAXONOMY-005` Version 1.2, before `WP-ERROR-032` was authored, the same pre-authoring correction pattern `SF-TAXONOMY-004`'s own WP-ERROR-014 boundary received. Disclosed here as a first data point on this specific failure mode, distinct enough from the prior three staleness/hedge findings to track separately rather than folding into one of them.

---

No further observations recorded.
