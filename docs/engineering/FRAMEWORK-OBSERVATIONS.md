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

---

## 2026-07-14 — Revision History section missing from eleven of thirteen specifications

Observed across `SF-REVIEW-055` (`SF-SPEC-005` independent review, IF-1): a repository-wide sweep (`grep -n "^#.*Revision History" docs/standards/SF-SPEC-0*.md`) found that only `SF-SPEC-013` and, as of this same review cycle, `SF-SPEC-005` currently carry a top-level Revision History section as `SF-TEMPLATE-001` Section 11 requires. The other eleven specifications (`SF-SPEC-001` through `004`, `006` through `012`) do not — including `SF-SPEC-008`, the Versioning Specification itself, whose own Section 5.3 ("Revision History") is a normative requirement about what a revision history must contain, not that document's own revision history.

This gap was found while independently re-verifying `SF-REVIEW-054`'s F-1 (which fixed the same gap in `SF-SPEC-005` alone, without performing the cross-specification sweep its own Recommendations section flagged as unperformed). It is disclosed here rather than fixed in that review, consistent with `SF-SPEC-005` Section 5.4's prohibition on a review recommendation introducing an unrelated architectural change.

Not acted on now. Worth considering, when time permits: a dedicated pass adding a Revision History section (with an honest, undated-review-disclosing Version 1.0 row, matching the pattern `SF-SPEC-005`'s own Version 1.0 row now sets) to each of the eleven affected specifications — likely low-risk, mechanical work suited to the validation-script direction already under consideration for the `SF-SPEC-013` Section 5.7 observation above.

---

No further observations recorded.
