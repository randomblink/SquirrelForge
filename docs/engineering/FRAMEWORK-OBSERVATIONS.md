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

---

## 2026-07-14 — SF-SPEC-013 Section 5.7 is correctly identified but not yet self-enforcing

Observed across `SF-REVIEW-052` (REST API category consistency review): even in the first category authored entirely under `SF-SPEC-013`'s completed governance baseline, two defects recurred that are the exact classes Database's and Filesystem's own category reviews already found — a sibling entry citing a now-existing entry as a stale conceptual placeholder, and a taxonomy document's own status table going stale the moment a later sibling was promoted. `SF-SPEC-013` Section 5.7 already names this second failure mode explicitly and requires it not happen, yet it happened anyway, in the very category meant to demonstrate the specification working end to end.

This suggests the requirement is correctly identified but currently depends entirely on a dedicated, later consistency review to catch a violation — it is not self-enforcing at the moment the violating commit is made (an entry's own promotion, or a sibling's own cross-reference, is a point-in-time snapshot that goes stale the instant a later commit changes what it describes). Worth considering, when time permits: whether a lighter-weight, mechanical check (for example, a `grep` step run as part of every entry-promotion commit, checking whether any other file in the repository cites the entry being promoted as `(conceptual reference...)`) could catch this at the moment of the violation rather than requiring a full category consistency review to surface it after the fact.

Not acted on now; disclosed per `SF-REVIEW-052`'s own recommendation to record it as a framework observation rather than act on it mid-review. Does not block REST API's own baseline certification, since it describes a process characteristic of this catalog's authoring workflow generally, not a defect specific to the REST API category's own entries.

---

No further observations recorded.
