# SF-SPEC-013 — Knowledge Category Lifecycle Specification

## Document Information

**Document ID:** SF-SPEC-013

**Title:** Knowledge Category Lifecycle Specification

**Classification:** Engineering Specification

**Status:** Production Ready

**Version:** 1.0

**Owner:** SquirrelForge

---

# 1. Purpose

## 1.1 Objective

This specification defines the engineering requirements governing how a `WP-ERROR` knowledge category — the set of entries sharing one **SF-SPEC-001** Section 7 category value (for example, `Database` or `Filesystem`) — progresses from an undefined, unplanned state to a certified, stable baseline, and how it is subsequently maintained. Its purpose is to make that progression a documented, repeatable engineering process rather than a practice that exists only in review records and conversation history.

---

# 2. Scope

## 2.1 Applies To

This specification applies to:

* Every `WP-ERROR` knowledge category, as defined by the category values enumerated in **SF-SPEC-001** Section 7.
* Every `SF-TAXONOMY-XXX` taxonomy document.
* Every category-level consistency review and every category-level baseline certification review.
* Changes made to a category's entries or taxonomy after that category has been certified.

## 2.2 Exclusions

This specification does not define:

* The technical content requirements of an individual `WP-ERROR` entry — owned by **SF-SPEC-001**.
* Engineering review process, findings structure, or review outcomes in general — owned by **SF-SPEC-005**.
* Reviewer classes, reviewer eligibility, or independence requirements — owned by **SF-SPEC-012**.
* Repository validation methodology — owned by **SF-SPEC-006**; this specification requires that validation be performed and its outcome recorded, without redefining how it is performed.
* Version numbering, revision-history document structure, or version-status values for an individual artifact — owned by **SF-SPEC-008**.
* Evidence retention, classification, archival, or disposal — owned by **SF-SPEC-011**.
* The approved list of category values themselves (`Database`, `Filesystem`, and so on) — owned by **SF-SPEC-001** Section 7; this specification governs how a category, once named there, is built out and certified, not which category values are approved to exist.
* The internal document structure of an `SF-TAXONOMY-XXX` document — no governing template currently exists for this artifact type; see Section 16 (Specification Evolution).

---

# 3. Specification Boundaries

## 3.1 Owns

* Category lifecycle states and the conditions for transitioning between them.
* Category entry criteria — the conditions that shall be satisfied before individual knowledge entries in a category may be authored.
* The required sequence of engineering artifacts a category's lifecycle shall produce.
* Category-level consistency review requirements (distinct from, and in addition to, per-entry review requirements owned by **SF-SPEC-001**/**SF-SPEC-005**).
* Category-level baseline certification requirements.
* Conditions under which a category is designated **Baseline Certified**, and what that designation does and does not mean.
* The change-control process governing a category's entries and taxonomy after Baseline Certified status is reached.
* The relationship between a category's lifecycle state and its governing `SF-TAXONOMY-XXX` document's own status record.

## 3.2 Depends On

* **SF-SPEC-001 — Error Knowledge Specification**, for individual `WP-ERROR` entry requirements, the approved category-value list, and the Production Ready definition each individual entry shall satisfy.
* **SF-SPEC-005 — Engineering Review Specification**, for review process, findings, and outcomes, which every review this specification requires (category consistency, baseline certification) shall itself conform to.
* **SF-SPEC-006 — Repository Validation Specification**, for the repository validation criteria and outcomes a baseline certification shall apply.
* **SF-SPEC-008 — Versioning Specification**, for revision-history requirements this specification's own Section 5.8 relies on.
* **SF-SPEC-011 — Evidence Governance Specification**, for retention of the review records this specification requires.
* **SF-SPEC-012 — Engineering Review Independence Specification**, for the reviewer-class framework the required review sequence (Section 5.3) invokes.

## 3.3 Does Not Define

* Individual `WP-ERROR` entry content, structure, or the six-required-distinctions-style internal completeness a single entry shall satisfy — **SF-SPEC-001**'s territory.
* Review record document structure — **SF-TEMPLATE-002**'s and **SF-SPEC-004**'s territory.
* Who may perform a review, or what independence means — **SF-SPEC-012**'s territory.
* How repository validation is technically performed — **SF-SPEC-006**'s territory.

---

# 4. Engineering Principles

## 4.1 Deliberate Sequencing

A category shall progress through its lifecycle in a defined order. Individual knowledge entries shall not be authored before a taxonomy document exists to bound the category they belong to.

---

## 4.2 Evidence Over Assertion

A category's completeness, consistency, and status claims shall be independently re-verified against current repository state at each review stage, not accepted from an artifact's own prior claim about itself — including a review record's or a revision-history entry's own summary of what it accomplished.

---

## 4.3 Layered Review

Per-entry review, category-level consistency review, and category-level baseline certification serve distinct purposes and evaluate distinct scopes. None substitutes for another, and a finding appropriate to one layer is not evidence that a different layer's own review was deficient for not having found it.

---

## 4.4 Preservation Over Correction-in-Place

Where a certification process determines that an earlier artifact's own revision history contains an inaccurate claim about repository state, the inaccuracy shall be disclosed in a new revision-history entry rather than corrected by silently editing or removing the earlier one.

---

## 4.5 Controlled Post-Certification Change

A category's certified baseline shall be extended or altered only through the same lifecycle discipline that produced it, not through an ad hoc edit to an individual entry or the taxonomy document.

---

# 5. Normative Requirements

The following requirements are mandatory for the lifecycle of a `WP-ERROR` knowledge category within the SquirrelForge Engineering Framework. Each requirement below is followed by a citation to the specific evidence, drawn from the Database and Filesystem categories' own completed lifecycles, that this requirement was extracted from.

---

## 5.1 Category Entry Criteria

A category shall not begin producing individual `WP-ERROR` knowledge entries until a dedicated `SF-TAXONOMY-XXX` document exists that:

* Declares the category's boundary, including what it explicitly excludes from every other category value it could plausibly be confused with.
* Enumerates every entry currently planned for the category, with a one-line ownership statement for each.
* Documents any candidate entry that was considered and rejected during taxonomy definition, together with the specific technical reasoning for its rejection.

**Evidentiary basis:** `SF-TAXONOMY-001` Section 2 (Category Boundary), Section 3 (Planned Entries), and Section 5 (Candidates Considered and Rejected) all existed, and were independently reviewed (`SF-REVIEW-034`), before `WP-ERROR-019` or `WP-ERROR-020` was authored. The Database category's own taxonomy was reconstructed only implicitly, after the fact, from conceptual placeholders scattered across `WP-ERROR-005` and `WP-ERROR-018` — a gap `SF-REVIEW-033` explicitly disclosed as a remaining risk, and the reason this requirement exists.

---

## 5.2 Required Engineering Artifacts

A category's lifecycle shall produce, at minimum:

* One `SF-TAXONOMY-XXX` document.
* One `WP-ERROR-XXX` knowledge entry per entry the taxonomy declares.
* One author (Class A) and one independent (Class B) review record per knowledge entry, per **SF-SPEC-001** Section 19 and **SF-SPEC-012**.
* One category-level consistency review record covering the complete set of entries together.
* One category-level baseline certification review record.

**Evidentiary basis:** the Filesystem category produced this exact artifact set (three entries, six per-entry review records, `SF-REVIEW-039`, `SF-REVIEW-040`). The Database category produced the same artifact types with one exception: it has nine entries (`WP-ERROR-002` through `009` and `018`) and eighteen per-entry review records, `SF-REVIEW-032`, and `SF-REVIEW-033`, but never produced a formal `SF-TAXONOMY-XXX` document — its own planned-entry set existed only informally, reconstructed after the fact from conceptual placeholders scattered across `WP-ERROR-005` and `WP-ERROR-018`, which is precisely the gap Section 5.1 exists to close prospectively rather than evidence that this artifact fully conforms to it.

---

## 5.3 Required Review Sequence

Individual entries shall not be authored before the governing taxonomy document exists (Section 5.1). The category-level consistency review shall not be performed until every entry the taxonomy declares has reached Production Ready. The category-level baseline certification shall not be performed until the category-level consistency review has reached an outcome of Approved or Approved with Minor Revisions, with any required revisions completed and re-validated.

**Evidentiary basis:** `SF-REVIEW-039` (Filesystem consistency review) was performed only after `WP-ERROR-016`, `019`, and `020` were all independently confirmed Production Ready; `SF-REVIEW-040` (Filesystem baseline certification) was performed only after `SF-REVIEW-039` reached Approved with Minor Revisions and its two findings were resolved. The Database category followed the identical order (`SF-REVIEW-032` before `SF-REVIEW-033`).

---

## 5.4 Baseline Certification Requirements

A category-level baseline certification review shall independently re-verify, against current repository state rather than any prior review record's own report:

* Every entry the taxonomy declares as planned actually exists.
* Every such entry carries `Status: Production Ready`.
* The category's entries retain mutually exclusive boundaries.
* Every cross-reference among the category's entries resolves to an existing file.
* The taxonomy document's own status record accurately reflects the entries' actual current status.
* No unresolved entry in `FRAMEWORK-OBSERVATIONS.md` describes a defect or open question specific to this category that would block certification.
* Repository validation (Section 5.9) has been applied and its outcome recorded.
* The working tree is clean, verified both before and after any correction the certification review itself applies.

**Evidentiary basis:** this list is the union of `SF-REVIEW-033`'s and `SF-REVIEW-040`'s own Baseline Criteria sections, generalized. `SF-REVIEW-040` specifically demonstrates why the taxonomy-status re-verification criterion shall be independent rather than inherited: it found `SF-TAXONOMY-001`'s own table inaccurately described as complete, a defect that survived both a prior correction commit and the category consistency review (`SF-REVIEW-039`) that preceded it.

---

## 5.5 Conditions for Category Freeze

A category reaches **Baseline Certified** status when its baseline certification review (Section 5.4) reaches an outcome of Approved. `Baseline Certified` is a category-level designation, distinct from, and not to be conflated with:

* **Production Ready**, which is an individual `WP-ERROR` entry's own status field, governed by **SF-SPEC-001** Section 19.
* **Version Frozen**, the individual-entry lifecycle stage named in **SF-SPEC-001** Section 18's diagram (Draft → Engineering Review → Production Ready → Version Frozen → Revision), governed by **SF-SPEC-008**.
* A taxonomy document's own informal `Status: Frozen` self-description (see, for example, `SF-TAXONOMY-001`), which describes only that document's own planned-entry-set stability, not the category's overall certification state.

A `Baseline Certified` category is not thereby placed in a `Version Frozen` state itself — each entry within it independently progresses through its own lifecycle under **SF-SPEC-008**. `Baseline Certified` describes the category as a whole; it does not alter, and is not altered by, any individual entry's own version-lifecycle state.

**Evidentiary basis:** the terminology risk this requirement exists to prevent is not hypothetical — `SF-REVIEW-034` found that `SF-TAXONOMY-001`'s own informal "Frozen" self-description already required an explicit disclaimer distinguishing it from **SF-SPEC-001**'s `Version Frozen` and **SF-SPEC-008**'s closed Version Status list, before a third, category-level use of similar terminology could be introduced without repeating that confusion.

---

## 5.6 Post-Certification Change

Once a category is `Baseline Certified`, a change to any of its entries or to its governing taxonomy document shall proceed only through:

1. A documented taxonomy revision (a new, dated, reasoned version of the `SF-TAXONOMY-XXX` document), where the change affects category scope or the planned-entry set.
2. The standard entry authoring and review sequence (Section 5.2, Section 5.3) for any new or materially revised entry.
3. A new category-level consistency review.
4. A new category-level baseline certification review, citing the specific change.

A category shall not be represented as still `Baseline Certified` following an uncertified change to any of its entries or its taxonomy document.

**Evidentiary basis:** no post-certification change has yet occurred for either the Database or Filesystem category; this requirement is derived from Section 5.3's already-demonstrated sequence and Section 4.5's principle, applied prospectively, rather than from a change episode that has actually occurred. This is disclosed as a limitation in Section 15.

---

## 5.7 Relationship to Taxonomy Maintenance

The `SF-TAXONOMY-XXX` document remains the authoritative record of a category's planned entry set and each entry's status throughout the category's lifecycle, including after Baseline Certified status is reached. Every entry status change (for example, `Draft` to `Production Ready`) shall be reflected in the taxonomy document's own status record within the same body of work that produces the change, not deferred to a later, separate correction.

**Evidentiary basis:** this requirement exists directly because of a violation of it. `WP-ERROR-020` was promoted to Production Ready in commit `c88ec9e`; the dedicated follow-up commit updating cross-references and the taxonomy table (`e8a70b5`) updated only `WP-ERROR-020`'s own status row, leaving `WP-ERROR-019`'s row inaccurately showing `Planned` despite that entry having reached Production Ready in an earlier commit (`a7910a8`) — a gap that persisted through `SF-REVIEW-039` and was only caught by `SF-REVIEW-040`.

---

## 5.8 Revision History Preservation

Where a category-level consistency review or baseline certification review determines that an earlier artifact's own revision history contains an inaccurate claim about repository state, that inaccuracy shall be disclosed as part of a new revision-history entry in the affected artifact, rather than corrected by editing or removing the earlier entry.

**Evidentiary basis:** `SF-TAXONOMY-001`'s Version 1.2 revision-history row, which inaccurately claimed the three-entry Filesystem baseline was complete, was preserved unmodified; `SF-REVIEW-040`'s correction was recorded as a new Version 1.3 row that explicitly names the 1.2 row's error rather than rewriting it.

---

## 5.9 Repository Validation Before Certification

A baseline certification review shall apply **SF-SPEC-006**'s repository validation criteria explicitly — repository identity, repository status, approved versus unexpected modifications, temporary-artifact removal, document consistency, and cross-reference integrity — and record the resulting outcome as one of **SF-SPEC-006** Section 9's four defined outcomes, rather than asserting repository cleanliness without applying the governing specification's own criteria.

**Evidentiary basis:** `SF-REVIEW-040` Section 5 applies each of **SF-SPEC-006** Section 6's criteria individually and records an explicit Section 9 outcome (`Repository Valid with Approved Changes`) as part of its own Findings table; `SF-REVIEW-033`, performed before this specification existed, asserted a clean working tree via `git status` without separately citing **SF-SPEC-006**'s own criteria by name — a gap this requirement corrects prospectively.

---

# 6. Quality Criteria

Category lifecycle governance under this specification shall be:

* Deterministic
* Traceable
* Repeatable
* Evidence-based
* Auditable

---

# 7. Baseline Certified Definition

This specification's generic template section is retitled here, per **SF-TEMPLATE-001**'s own instruction that a section addressed by different content than its generic name suggests be handled deliberately rather than silently: the governed subject of this specification is a *category*, not an individual artifact with its own Production Ready state, so this section defines **Baseline Certified** rather than reusing "Production Ready" verbatim for a different concept (see Section 5.5).

A category shall not be designated **Baseline Certified** until:

* Every requirement in Section 5.1 through 5.4 has been satisfied.
* A baseline certification review record exists, documenting independent re-verification of every criterion in Section 5.4.
* That review's outcome is Approved, with any findings it raised corrected and re-validated within the same review.
* Repository validation (Section 5.9) has been applied and recorded.

---

# 8. Engineering Review Checklist

Every category-level baseline certification shall satisfy the following checklist before the category may be designated **Baseline Certified**.

* ☐ Governing taxonomy document exists and declares the category boundary
* ☐ Every planned entry is enumerated in the taxonomy
* ☐ Any rejected candidate entries are documented with reasoning
* ☐ Every planned entry exists as a file
* ☐ Every planned entry is independently confirmed Production Ready
* ☐ Category-level consistency review completed with an Approved or Approved with Minor Revisions outcome, findings resolved
* ☐ Cross-references among the category's entries independently re-verified to resolve
* ☐ Taxonomy document's status record independently re-verified against actual entry status
* ☐ Relevant framework observations reviewed for anything blocking this category
* ☐ Repository validation applied per SF-SPEC-006, outcome recorded
* ☐ Working tree confirmed clean before and after any correction applied during certification
* ☐ Baseline certification review outcome is Approved

---

# 9. Change Control

This specification shall not be modified to accommodate an individual category's lifecycle.

The specification shall be revised only when an engineering improvement benefits the SquirrelForge Engineering Framework as a whole.

All revisions shall:

* Be versioned.
* Be reviewed.
* Be documented.
* Preserve backward compatibility where practical.
* Identify affected engineering artifacts.

---

# 10. Reference Implementations

The Database category (`WP-ERROR-002` through `009` and `018`, certified by `SF-REVIEW-033`) and the Filesystem category (`WP-ERROR-016`, `019`, `020`, certified by `SF-REVIEW-040`) are the evidentiary basis this specification's Section 5 requirements were extracted from — each requirement above cites the specific review record or commit its own wording is grounded in, rather than describing a process invented theoretically before any category had completed it.

Neither is designated a Reference Implementation under **SF-SPEC-001** Section 22 by this specification alone. That designation requires a dedicated verification pass explicitly confirming each category's completed lifecycle against every requirement in Section 5 of this document specifically, which has not yet been performed. This specification does not assert or assume that designation until such verification actually exists, consistent with **SF-SPEC-001** Section 22.3's own precedent of removing an unverifiable Reference Implementation citation rather than leaving it asserted without support.

---

# 11. Glossary Dependencies

This specification uses the following terms as defined by **SF-GLOSSARY-001 — Engineering Terminology**: Artifact, Engineering Review, Evidence, Production Ready, Specification, Traceability, Version, Revision.

This specification additionally introduces the following terms, which **SF-GLOSSARY-001** does not currently define: Category (as a lifecycle-bearing unit, distinct from the category *value* SF-SPEC-001 Section 7 governs), Category Lifecycle, Baseline Certified, Category-Level Consistency Review, Category-Level Baseline Certification. These are defined locally within this specification (Sections 1, 3.1, 5.4, 5.5, 7) rather than in the shared glossary. Extending **SF-GLOSSARY-001** to include them is outside the scope of this specification's own creation; this gap is disclosed here consistent with the precedent established in `SF-REVIEW-005`'s own handling of the same situation for `SF-SPEC-012`.

---

# 12. Cross-Reference Requirements

Every cross-reference this specification makes to another specification, taxonomy document, or review record shall identify it by its full document ID and title on first use within a section, and shall remain valid following revision of the cited document, per **SF-SPEC-004 — Documentation Specification**.

---

# 13. Validation

An application of this specification is complete when:

* The category's lifecycle state (per Section 5) has been determined from current repository evidence, not assumed from a prior status claim.
* Every artifact Section 5.2 requires has been confirmed to exist.
* The review sequence in Section 5.3 has been confirmed to have been followed in order.
* Where Baseline Certified status is being determined, every item in the Section 8 checklist has been satisfied.

---

# 14. Boundary Validation

Verify that this specification:

* Owns category lifecycle governance and no other engineering responsibility.
* Does not define individual entry content requirements, owned by **SF-SPEC-001**.
* Does not define review record structure or process, owned by **SF-SPEC-005**.
* Does not define reviewer independence, owned by **SF-SPEC-012**.
* Does not define repository validation methodology, owned by **SF-SPEC-006**.
* Does not duplicate requirements owned by any specification named in Section 3.2.
* References governing specifications instead of restating their responsibilities.

---

# 15. Remaining Limitations

* Section 5.6 (Post-Certification Change) is derived prospectively from principle rather than from an observed change episode, since neither the Database nor Filesystem category has yet undergone a post-certification change. This requirement should be re-examined once a real instance occurs, to confirm the prescribed sequence is actually sufficient in practice.
* No `SF-TAXONOMY-XXX` template currently exists (see Section 2.2); `SF-TAXONOMY-001` was authored without one. A future, separately-scoped template (analogous to **SF-TEMPLATE-004** for `WP-ERROR` entries) would let this specification's Section 5.1 be checked mechanically rather than by prose comparison.
* This specification's own creation was not preceded by an independent (Class B) review; per the precedent established by `SF-REVIEW-005` for `SF-SPEC-012`, it was initially reviewed only by its own authoring process (`SF-REVIEW-041`), consistent with how every specification in this library (`SF-SPEC-001` through `SF-SPEC-012`) was originally established.

---

# 16. Specification Evolution

This specification shall evolve only within its defined engineering responsibility.

Proposed additions shall be evaluated against Section 3 (Specification Boundaries) and Section 14 (Boundary Validation) before acceptance.

Requirements outside the owned responsibility shall be assigned to the specification that owns that responsibility, or to a new specification if no appropriate owner exists.

A future `SF-TEMPLATE-XXX` governing `SF-TAXONOMY-XXX` document structure, if created, is anticipated by this specification's Section 2.2 exclusion but is not itself defined here.

---

# 17. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial specification, authored after the Database and Filesystem categories independently completed the full lifecycle this document formalizes. Every Section 5 requirement cites the specific review record or commit it was extracted from. | Draft — author-reviewed, see `SF-REVIEW-041` |
| 1.0 | 2026-07-14 | Independent (Class B) review (`SF-REVIEW-042`) found and corrected one Minor finding: Section 5.2 undercounted Database's review records (16 stated vs. 18 actual) and overstated its conformance to the taxonomy-document requirement Section 5.1 itself discloses Database lacks. Corrected. Status changed to Production Ready — the first specification in this library to reach that designation. | Production Ready — Approved with Minor Revisions |
