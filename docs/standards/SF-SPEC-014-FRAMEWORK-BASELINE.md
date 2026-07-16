# SF-SPEC-014 — Framework Baseline Specification

## Document Information

**Document ID:** SF-SPEC-014

**Title:** Framework Baseline Specification

**Classification:** Engineering Specification

**Status:** Production Ready

**Version:** 1.1

**Owner:** SquirrelForge

---

# 1. Purpose

## 1.1 Objective

This specification defines the engineering requirements governing how the SquirrelForge Engineering Framework as a whole — its specification library, knowledge categories, and governance artifacts, taken together — progresses from an undeclared state to a declared **Framework Baseline**, and how a subsequent Framework Baseline is later declared. Its purpose is to make that declaration a documented, evidence-based, repeatable engineering process rather than one inferred by analogy to a narrower specification, or asserted procedurally by convention.

---

# 2. Scope

## 2.1 Applies To

This specification applies to:

* The SquirrelForge specification library as a whole (every `SF-SPEC-XXX`, `SF-GLOSSARY-XXX`, and `SF-TEMPLATE-XXX` document).
* Every `WP-ERROR` knowledge category and its certification state under **SF-SPEC-013**.
* `FRAMEWORK-OBSERVATIONS.md` and the disposition of its entries.
* Any Framework Baseline Readiness Review record.
* The declaration of a Framework Baseline, and its relationship to any Framework Baseline declared previously.

## 2.2 Exclusions

This specification does not define:

* The technical content or Production Ready criteria of an individual specification — owned by **SF-SPEC-004**, **SF-SPEC-005**, and **SF-SPEC-008**.
* Category-level consistency review or baseline certification requirements — owned by **SF-SPEC-013**; this specification requires that a category's certification state be accurately reported, without redefining how a category itself becomes `Baseline Certified`.
* Repository validation methodology — owned by **SF-SPEC-006**; this specification requires that validation be performed and its outcome recorded, without redefining how it is performed.
* Engineering review process, findings structure, or review outcomes in general — owned by **SF-SPEC-005**.
* Reviewer classes, reviewer eligibility, or independence requirements — owned by **SF-SPEC-012**.
* The design, implementation, or ownership of any automated validation tooling (for example, `scripts/validate-repo.sh`) — no specification currently owns this; this specification requires that such tooling, where it exists and is relied upon, be run and its outcome recorded, without asserting its existence as a prerequisite or claiming ownership of it.

---

# 3. Specification Boundaries

## 3.1 Owns

* The definition of a Framework Baseline.
* Framework Baseline declaration requirements and required evidence.
* The distinction between an accepted limitation and a blocking defect, for purposes of a Framework Baseline declaration.
* The Framework Baseline Readiness Review requirement and its relationship to a declaration.
* The Framework Baseline declaration procedure.
* The structure and required content of a Framework Baseline Declaration Record (`SF-BASELINE-XXX`), as an artifact type distinct from a Framework Baseline Readiness Review.
* Framework Baseline numbering and succession from one baseline to the next.
* The relationship between a Framework Baseline and the maturity state of individual specifications and knowledge categories, without redefining either.

## 3.2 Depends On

* **SF-SPEC-004 — Documentation Specification**, for document structure and revision-history requirements this specification's own artifacts follow.
* **SF-SPEC-005 — Engineering Review Specification**, for the review process, findings, and outcomes a Framework Baseline Readiness Review shall itself conform to.
* **SF-SPEC-006 — Repository Validation Specification**, for the repository validation criteria and outcomes a Framework Baseline declaration shall apply.
* **SF-SPEC-008 — Versioning Specification**, for individual specification Version Status, which this specification's own Section 5.4 reports on but does not redefine.
* **SF-SPEC-012 — Engineering Review Independence Specification**, for the reviewer-class framework a Framework Baseline Readiness Review invokes.
* **SF-SPEC-013 — Knowledge Category Lifecycle Specification**, for category-level `Baseline Certified` status, which this specification aggregates and reports on but does not redefine or re-certify.
* **SF-SPEC-001 — Error Knowledge Specification**, for the approved `WP-ERROR` category-value list Section 5.4 relies on to identify "every knowledge category," and for the individual-entry Production Ready definition Section 5.4 reports on but does not redefine.

## 3.3 Does Not Define

* Individual specification content, structure, or Production Ready criteria — **SF-SPEC-004**/**005**/**008**'s territory.
* Category-level consistency or baseline certification requirements — **SF-SPEC-013**'s territory.
* How repository validation is technically performed — **SF-SPEC-006**'s territory.
* Who may perform a review, or what independence means — **SF-SPEC-012**'s territory.

---

# 4. Engineering Principles

## 4.1 Governance Before Declaration

A Framework Baseline shall not be declared before the specification governing its own declaration exists and has itself completed engineering review.

---

## 4.2 Evidence Over Assertion

A Framework Baseline's readiness shall be independently re-verified against current repository state at declaration time, not accepted from a prior review record's own summary of what it accomplished — including this specification's own Reference Implementations, once any exist.

---

## 4.3 Aggregation Without Redefinition

A Framework Baseline reports on the current state of individual specifications and knowledge categories; it does not alter, re-certify, or substitute for either's own governing specification. A specification remaining `Draft`, or a category never having undergone category-level certification, does not by itself prevent a Framework Baseline from being declared — it requires that state to be explicitly and accurately disclosed.

---

## 4.4 Preservation Over Correction-in-Place

A subsequent Framework Baseline declaration shall not edit, remove, or silently supersede a prior declaration's own record. Where a prior declaration's own claim is later found inaccurate, the inaccuracy shall be disclosed in a new record, consistent with the same principle **SF-SPEC-013** Section 4.4 already establishes for category-level artifacts.

---

## 4.5 Distinguish Accepted Limitations From Blocking Defects

A Framework Baseline declaration shall not require every open question about framework state to be resolved. It shall require every open question to be explicitly classified as either a disclosed, non-blocking accepted limitation, or a blocking defect — and shall not proceed while any item remains unclassified or is a blocking defect.

---

# 5. Normative Requirements

The following requirements are mandatory for the declaration of a SquirrelForge Framework Baseline. Because no Framework Baseline has yet been declared under this specification, its requirements are grounded differently than a typical SquirrelForge specification: **SF-SPEC-013** was authored after two knowledge categories independently completed the lifecycle it formalizes, and could cite their completed history directly. This specification has no completed Framework Baseline history to cite. Its requirements are instead grounded in (a) the four category-level baselines already completed under **SF-SPEC-013**, generalized one level up, and (b) `SF-REVIEW-061`'s own direct experience of being forced to borrow **SF-SPEC-013** by analogy for lack of this specification — evidence that the gap this specification closes is real, not hypothetical. This limitation is disclosed, not concealed; see Section 15.

---

## 5.1 Framework Baseline Definition

A **Framework Baseline** is a dated, sequentially numbered declaration that the SquirrelForge specification library, its knowledge categories, and its governance artifacts, taken together, represent a coherent, internally consistent, independently re-verified state suitable for treating as a stable reference point for future work.

A Framework Baseline is distinct from, and shall not be conflated with:

* An individual specification's **Production Ready** status, governed by **SF-SPEC-005** and **SF-SPEC-008**.
* A knowledge category's **Baseline Certified** status, governed by **SF-SPEC-013** Section 5.5.
* The informal "Version 1.0 Freeze Authorized" language `SF-REVIEW-002` (Phase 6, 2026-07-12) used for the specification library, which predates this specification and satisfied no criterion this specification defines — it is a historical precursor, not a Framework Baseline under this specification, in the same way `SF-TAXONOMY-001`'s own informal `Status: Frozen` self-description predates and is distinct from `SF-SPEC-013`'s formal `Baseline Certified` designation.

**Evidentiary basis:** `SF-REVIEW-061` (Framework Baseline v2 Readiness Review) Section 1 explicitly disclosed that no specification defines this term, and that its own review therefore could not declare anything — only assess readiness. That disclosure is the direct cause of this section's existence.

---

## 5.2 Required Evidence

A Framework Baseline declaration shall be supported by:

* A Framework Baseline Readiness Review record (Section 5.3) evaluating every criterion in Section 5.4.
* An explicit disposition of every `FRAMEWORK-OBSERVATIONS.md` entry as either closed or an accepted limitation (Section 5.5) — no entry may remain undispositioned.
* An explicit accounting of every knowledge category's certification state under **SF-SPEC-013**.
* An explicit accounting of every specification's Status and Version under **SF-SPEC-008**.
* A recorded repository validation outcome per **SF-SPEC-006**.

**Evidentiary basis:** `SF-REVIEW-061` produced exactly this evidence set (its Sections 5–7 and 9) without a specification requiring it to. This requirement formalizes what that review already did in practice.

---

## 5.3 Readiness Review Requirement

A Framework Baseline shall not be declared until a dedicated Framework Baseline Readiness Review, citing this specification directly rather than by analogy to **SF-SPEC-013** or any other specification, has reached an outcome of Approved or Approved with Minor Revisions under **SF-SPEC-005**, with any required revisions completed and re-validated.

**Evidentiary basis:** `SF-REVIEW-061` performed this function on 2026-07-14 but was forced to state, in its own Section 1 and Section 4, that it was borrowing **SF-SPEC-013** Section 5.4 "by analogy" for lack of a governing specification of its own. This requirement exists so a future readiness review need not make the same disclosure.

---

## 5.4 Framework Baseline Declaration Requirements

A Framework Baseline Readiness Review shall independently re-verify, against current repository state rather than any prior review record's own report:

* Every specification's Status, Version, and Revision History are internally consistent and accurate.
* Every `WP-ERROR` knowledge category with more than one entry is either `Baseline Certified` under **SF-SPEC-013**, or its non-certified state is explicitly disclosed as an accepted limitation (Section 5.5).
* Every entry in `FRAMEWORK-OBSERVATIONS.md` is classified as either closed or an accepted limitation (Section 5.5); none remains unclassified.
* No item classified as a blocking defect remains outstanding.
* Any automated repository-validation tooling currently relied upon by the framework, where one exists, has been run and its outcome recorded.
* Repository validation per **SF-SPEC-006** has been applied and its outcome recorded.
* The working tree is clean, verified both before and after any correction the readiness review itself applies.

**Evidentiary basis:** this list is `SF-REVIEW-061` Section 4's own adapted criteria and Section 9's accepted-limitations disclosure, generalized from that single review's ad hoc practice into a normative requirement — the same relationship **SF-SPEC-013** Section 5.4 has to `SF-REVIEW-033`/`040`'s own Baseline Criteria sections.

---

## 5.5 Accepted Limitations Distinguished From Blocking Defects

An **accepted limitation** is a disclosed, non-blocking characteristic of current framework state that has either a stated resolution path, a documented precedent for deliberate deferral, or is an inherent scope characteristic rather than a gap.

A **blocking defect** is an inaccuracy, inconsistency, or unresolved finding with no such disclosed disposition.

A Framework Baseline Readiness Review shall classify every open item it identifies, and every open `FRAMEWORK-OBSERVATIONS.md` entry, as one or the other explicitly. An item without an explicit classification shall be treated as a blocking defect for purposes of Section 5.4.

**Evidentiary basis:** this distinction was introduced directly by the user's own instruction ("a baseline should record accepted limitations, but it should not freeze while known structural defects are still actively awaiting decisions") and applied by `SF-REVIEW-061` Section 9 before any specification made it normative. It is formalized here rather than left as an ad hoc judgment call for each future readiness review to reinvent.

---

## 5.6 Declaration Procedure

A Framework Baseline is declared by a dedicated **Framework Baseline Declaration Record** (`SF-BASELINE-XXX`), an artifact type distinct from a Framework Baseline Readiness Review. The Readiness Review answers whether the framework is ready to be frozen; the Declaration Record answers whether the project owner has chosen to freeze it. The two shall not be merged into a single document, and a Readiness Review's own Gate Decision section shall not itself declare a baseline.

A Framework Baseline Declaration Record shall state, at minimum:

* The baseline identifier (its sequential version number, per Section 5.7).
* The declaration date.
* The approving authority.
* The specific Framework Baseline Readiness Review relied upon as evidence, cited rather than restated.
* The scope of the freeze: the specific commit and the specific set of specifications, categories, and artifacts it covers.
* An explicit statement that subsequent framework changes require a new Framework Baseline Readiness Review and Declaration Record (Section 5.7), rather than silent modification.

A Framework Baseline Declaration Record shall not restate the Readiness Review's own findings; it cites the review record rather than reproducing its evidence. It does not itself require a dedicated Class A/Class B review pair beyond the review already completed for the Readiness Review it cites: it records a governance decision rather than asserting new technical or normative content, a deliberate proportionality choice distinct from how an `SF-TAXONOMY-XXX` document — which does assert new technical content — is independently reviewed. This is disclosed, not silently assumed; see Section 15.

A Framework Baseline shall not be asserted informally — in a commit message, a summary, or conversational text — without a Declaration Record satisfying this section existing.

**Evidentiary basis:** this section replaces Version 1.0's approach, which required the declaration to live inside the Readiness Review's own Gate Decision section, mirroring how `SF-REVIEW-053`/`057`'s Section 8 ("Baseline Designation") is itself a category-level declaration. That approach was reconsidered per explicit user direction, given while `SF-REVIEW-064` awaited a declaration decision: readiness evidence and the governance decision to act on it are different questions, and conflating them in one document does not give a clean audit trail distinguishing "the repository was ready" from "the project owner chose to freeze it." Version 1.0's own Section 15 had already disclosed this exact question as unresolved, deferred "before one has proven necessary" (see Section 15's revised third bullet) — this revision is that proof.

---

## 5.7 Baseline Numbering and Succession

Framework Baselines shall be numbered sequentially. The first Framework Baseline declared under this specification shall be numbered **v2**, continuing the number already in use across this session's own records (`SF-REVIEW-060`, `SF-REVIEW-061`, and the user's own direction) rather than restarting at v1 and creating ambiguity against those existing, committed records. This numbering choice is a continuity decision, not a claim that a formal "Framework Baseline v1" was ever declared under this or any specification — see Section 5.1's third bullet.

A subsequent Framework Baseline (v3 and beyond) shall be declared only through a new Framework Baseline Readiness Review independently satisfying Section 5.4 in full against then-current repository state. Its declaration shall explicitly identify which prior Framework Baseline it supersedes, without editing, removing, or silently superseding the prior declaration's own record, per Section 4.4.

**Evidentiary basis:** no Framework Baseline succession has yet occurred; this requirement is derived from Section 4.4's principle and **SF-SPEC-013** Section 5.6's analogous, already-demonstrated post-certification change sequence, applied prospectively rather than from an observed instance. Disclosed as a limitation in Section 15.

---

# 6. Quality Criteria

Framework Baseline governance under this specification shall be:

* Deterministic
* Traceable
* Repeatable
* Evidence-based
* Auditable

---

# 7. Framework Baseline Declared Definition

This specification's generic template section is retitled here, per **SF-TEMPLATE-001**'s own instruction that a section addressed by different content than its generic name suggests be handled deliberately rather than silently: the governed subject of this specification is the framework as a whole, not an individual artifact with its own Production Ready state, so this section defines when a Framework Baseline may be declared rather than reusing "Production Ready" verbatim for a different concept (see Section 5.1).

A Framework Baseline shall not be declared until:

* Every requirement in Section 5.1 through 5.6 has been satisfied.
* A Framework Baseline Readiness Review record exists, documenting independent re-verification of every criterion in Section 5.4.
* That review's outcome is Approved or Approved with Minor Revisions, with any findings it raised corrected and re-validated within the same review.
* Repository validation has been applied and recorded.
* Every open item is explicitly classified per Section 5.5; none remains a blocking defect.
* A Framework Baseline Declaration Record satisfying Section 5.6 has been created, citing the Readiness Review as its evidence.

---

# 8. Engineering Review Checklist

Every Framework Baseline Readiness Review shall satisfy the following checklist before a Framework Baseline Declaration Record may be created.

* ☐ Every specification's Status, Version, and Revision History independently re-verified
* ☐ Every multi-entry knowledge category's certification state independently re-verified
* ☐ Every `FRAMEWORK-OBSERVATIONS.md` entry classified as closed or an accepted limitation
* ☐ No blocking defect remains outstanding
* ☐ Relevant automated validation tooling run and its outcome recorded, where such tooling exists
* ☐ Repository validation applied per **SF-SPEC-006**, outcome recorded
* ☐ Working tree confirmed clean before and after any correction applied during the review
* ☐ Readiness review outcome is Approved or Approved with Minor Revisions
* ☐ Readiness review does not itself declare the baseline; it is recorded as available evidence for a subsequent Declaration Record (Section 5.6)

A Framework Baseline Declaration Record itself shall satisfy the content checklist Section 5.6 states before the baseline it declares may be considered valid.

---

# 9. Change Control

This specification shall not be modified to accommodate an individual Framework Baseline declaration.

The specification shall be revised only when an engineering improvement benefits the SquirrelForge Engineering Framework as a whole.

All revisions shall:

* Be versioned.
* Be reviewed.
* Be documented.
* Preserve backward compatibility where practical.
* Identify affected engineering artifacts.

---

# 10. Reference Implementations

No Reference Implementation is currently designated. No Framework Baseline has yet been declared under this specification; one may be designated once a Framework Baseline Readiness Review has been evaluated against every requirement in Section 5 of this document and explicitly verified compliant.

---

# 11. Glossary Dependencies

This specification uses the following terms as defined by **SF-GLOSSARY-001 — Engineering Terminology**: Artifact, Engineering Review, Evidence, Production Ready, Specification, Traceability, Version, Revision.

This specification additionally introduces the following terms, which **SF-GLOSSARY-001** does not currently define: Framework Baseline, Framework Baseline Readiness Review, Accepted Limitation, Blocking Defect. These are defined locally within this specification (Sections 4.5, 5.1, 5.3, 5.5) rather than in the shared glossary. Extending **SF-GLOSSARY-001** to include them is outside the scope of this specification's own creation, consistent with the precedent `SF-REVIEW-005` and `SF-REVIEW-041` each established for their own new terminology.

---

# 12. Cross-Reference Requirements

Every cross-reference this specification makes to another specification or review record shall identify it by its full document ID and title on first use within a section, and shall remain valid following revision of the cited document, per **SF-SPEC-004 — Documentation Specification**.

---

# 13. Validation

An application of this specification is complete when:

* Every criterion in Section 5.4 has been independently re-verified against current repository state, not assumed from a prior claim.
* Every open item has been explicitly classified per Section 5.5.
* The Section 8 checklist has been fully satisfied by the Readiness Review.
* A Framework Baseline Declaration Record satisfying Section 5.6 has been created, citing the Readiness Review rather than restating it.

---

# 14. Boundary Validation

Verify that this specification:

* Owns Framework Baseline declaration governance and no other engineering responsibility.
* Does not define individual specification Production Ready criteria, owned by **SF-SPEC-005**/**008**.
* Does not define category-level certification criteria, owned by **SF-SPEC-013**.
* Does not define repository validation methodology, owned by **SF-SPEC-006**.
* Does not claim ownership of any automated validation tooling.
* Does not duplicate requirements owned by any specification named in Section 3.2.
* References governing specifications instead of restating their responsibilities.

---

# 15. Remaining Limitations

* This specification's Section 5 requirements are grounded in generalization from category-level precedent and from `SF-REVIEW-061`'s own disclosed gap, not from a completed Framework Baseline lifecycle — because none exists yet. Unlike **SF-SPEC-013**, this specification cannot cite a first real instance until after its own first application. It should be re-examined once a Framework Baseline is actually declared under it, the same way **SF-SPEC-013** Section 15 flags its own Section 5.6 for re-examination once a real post-certification change occurs.
* Section 5.7's numbering choice (starting at v2) is a continuity decision tied to this specific session's history, not a general principle other frameworks adopting a similar pattern would necessarily follow; disclosed rather than presented as a universal rule.
* **Resolved in Version 1.1:** Version 1.0 had deferred whether a Framework Baseline declaration should be its own dedicated artifact type, resolving in favor of embedding it in the Readiness Review's own Gate Decision section rather than introducing a new artifact type before one had proven necessary. Per explicit user direction, that necessity arose immediately upon the first real declaration decision: readiness evidence and the governance decision to act on it were found to be different things that a clean audit trail should keep separate. Section 5.6 now defines a dedicated `SF-BASELINE-XXX` artifact type accordingly.
* No `SF-TEMPLATE-XXX` currently governs `SF-BASELINE-XXX` document structure; Section 5.6 defines required content inline, the same way `SF-TAXONOMY-001`/`002` were authored without a governing template before one existed for that artifact type either (**SF-SPEC-013** Section 15's own second bullet). A future, separately-scoped template would let this requirement be checked mechanically.
* A Framework Baseline Declaration Record does not itself require a dedicated Class A/Class B review pair (Section 5.6); it relies on the review already completed for the Readiness Review it cites. This proportionality choice — appropriate for a document recording a decision rather than asserting new technical content — should be revisited if a future Declaration Record is ever found to assert something beyond what its cited Readiness Review actually established.
* This specification's own creation was not preceded by an independent (Class B) review at the time of the Version 1.0 draft; that review (`SF-REVIEW-063`) followed before Production Ready was sought, consistent with every other specification's own lifecycle in this library. This Version 1.1 revision was independently reviewed in turn (see this document's own Revision History).
* Whether any automated validation tooling (for example, `scripts/validate-repo.sh`) should receive formal normative ownership is explicitly left unresolved by this specification (Section 2.2), consistent with the user's own guidance to treat that as a later evolution rather than a prerequisite for this specification's own creation or for any Framework Baseline declared under it.

---

# 16. Specification Evolution

This specification shall evolve only within its defined engineering responsibility.

Proposed additions shall be evaluated against Section 3 (Specification Boundaries) and Section 14 (Boundary Validation) before acceptance.

Requirements outside the owned responsibility shall be assigned to the specification that owns that responsibility, or to a new specification if no appropriate owner exists.

Should automated validation tooling later receive formal normative ownership (Section 15), this specification's Section 5.4 requirement that such tooling "be run and its outcome recorded, where one exists" is anticipated to remain compatible without requiring revision, since it already does not assume the tooling's existence.

---

# 17. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial specification, authored in direct response to `SF-REVIEW-061`'s own disclosure that no specification defines a framework-level baseline, and to the user's explicit recommendation that this gap be closed by a dedicated specification before any Framework Baseline is declared, rather than inferred by analogy. Every Section 5 requirement cites `SF-REVIEW-061` or the four completed category-level baselines it aggregates from. | Draft — author-reviewed, see `SF-REVIEW-062` |
| 1.0 | 2026-07-14 | Class B independent review (`SF-REVIEW-063`) found and corrected one Minor finding (IF-1): Section 3.2 did not cite `SF-SPEC-001` directly for the category-value concept Section 5.4 relies on, relying on transitive coverage through `SF-SPEC-013` instead. Corrected. Status changed to Production Ready — the fourth specification in this library to reach that designation. | Production Ready — Approved with Minor Revisions |
| 1.1 | 2026-07-14 | Revised Section 5.6 (Declaration Procedure) per explicit user direction, given while `SF-REVIEW-064` awaited a declaration decision: a Framework Baseline is now declared by a dedicated `SF-BASELINE-XXX` Declaration Record, distinct from the Readiness Review, rather than inside the Readiness Review's own Gate Decision section. Updated Section 3.1 (Owns), Section 7 (Declared Definition), Section 8 (checklist), and Section 15 (Remaining Limitations) accordingly; no other section's substance changed. Resolves the design question Version 1.0 Section 15 had explicitly deferred. | Draft — author-reviewed, see `SF-REVIEW-065` |
| 1.1 | 2026-07-14 | Class B independent review (`SF-REVIEW-066`) found no defects. Status changed to Production Ready. | Production Ready — Approved |
