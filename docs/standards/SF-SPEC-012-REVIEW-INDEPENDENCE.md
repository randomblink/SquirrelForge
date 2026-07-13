# SF-SPEC-012 — Engineering Review Independence Specification

## Document Information

**Document ID:** SF-SPEC-012

**Title:** Engineering Review Independence Specification

**Classification:** Engineering Specification

**Status:** Draft

**Version:** 1.0

**Owner:** SquirrelForge

---

# 1. Purpose

## 1.1 Objective

This specification defines the independence requirements, reviewer classifications, reviewer eligibility, and review authority for engineering reviews performed within the SquirrelForge Engineering Framework.

Its purpose is to define what independence means for a review, so that no governing specification requiring an "independent engineering review" is left to interpret that requirement ad hoc.

---

# 2. Scope

## 2.1 Applies To

This specification applies to:

* Every engineering review conducted under **SF-SPEC-005 — Engineering Review Specification**.
* Every reviewer, whether an authoring process, an independent review session, or a human engineer.
* Every governing specification that conditions a lifecycle state on reviewer independence.

## 2.2 Exclusions

This specification does not define:

* Engineering review document structure.
* Evidence governance.
* Versioning policy.
* Release readiness policy.
* Approval workflow implementation.
* Repository permissions or access control.
* Technical correctness of the reviewed artifact.

These subjects are defined by their respective SquirrelForge specifications, or, for technical correctness, remain the responsibility of the review itself rather than of this specification.

---

# 3. Specification Boundaries

## 3.1 Owns

- Review independence.
- Reviewer classifications.
- Reviewer eligibility.
- Independence requirements.
- Conflict-of-interest requirements.
- Review succession.
- Review authority.
- Independence exceptions.
- Review-succession preservation (the requirement that a later review not delete or overwrite an earlier review record for the same artifact).

## 3.2 Depends On

- **SF-SPEC-004 — Documentation Specification**, for cross-reference validity requirements.
- **SF-SPEC-005 — Engineering Review Specification**, for engineering review process, structure, findings, and outcomes.
- **SF-SPEC-011 — Evidence Governance Specification**, for the classification, retention, archival, and disposal of review records as evidence artifacts.

## 3.3 Does Not Define

- Engineering review document structure.
- Evidence classification, retention, archival, and disposal of review records (including the general question of how long a review record is kept and when it may be disposed of).
- Versioning policy.
- Release readiness policy.
- Approval workflow implementation.
- Repository permissions or access control.
- Technical correctness of the reviewed artifact.

## 3.4 Ownership Rationale

Reviewer independence is governed separately from engineering review methodology to preserve single-responsibility architecture. **SF-SPEC-005** defines how engineering reviews are performed. This specification defines who may perform reviews, the required level of reviewer independence, and the authority associated with each review class. Lifecycle state transitions remain the responsibility of the governing specification for each artifact type; this specification does not itself grant or withhold any lifecycle state.

This specification's ownership of "review-succession preservation" is narrower than general evidence retention. It requires only that a later review not delete or overwrite an earlier review record, so that the independence chain established by Section 10 (Review Succession) remains auditable. It does not define how long a review record is retained, how it is classified, or when it may be disposed of; those questions remain governed by **SF-SPEC-011 — Evidence Governance Specification**.

---

# 4. Engineering Principles

## 4.1 Independence

Engineering reviews shall be conducted with sufficient independence to permit objective technical evaluation.

---

## 4.2 Evidence Before Conclusion

Engineering conclusions shall be derived from observable evidence rather than from previous review conclusions.

---

## 4.3 Preservation

Engineering review history shall be preserved. Earlier review records shall not be deleted or replaced by later reviews.

---

## 4.4 Traceability

Every engineering conclusion reached under this specification shall be traceable to the reviewed artifact, its governing specifications, or its engineering evidence.

---

## 4.5 Separation of Responsibilities

Engineering review governance shall remain independent of artifact engineering, lifecycle management, release governance, repository administration, and implementation technology.

---

## 4.6 Structural Note

This specification governs review authority rather than an artifact type with its own Production Ready lifecycle. Accordingly, it does not include the generic specification template's Quality Criteria, Production Ready Definition, or Engineering Review Checklist sections (per **SF-TEMPLATE-001**). Section 14 (Validation) and Section 15 (Boundary Validation) serve the equivalent function for this specification's own governed process.

---

# 5. Glossary Dependencies

This specification uses the following terms as defined by **SF-GLOSSARY-001 — Engineering Terminology**: Artifact, Engineering Review, Evidence, Production Ready, Reference Implementation, Specification, Traceability, Version.

This specification additionally relies on the following terms, which **SF-GLOSSARY-001** does not currently define: Reviewer (Section 6), Conflict of Interest (Section 9), Review Record and Review Succession (Section 10), Independence (Section 8), Lifecycle State and Review Authority (Section 12). These terms are defined locally within this specification rather than in the shared glossary. Extending **SF-GLOSSARY-001** to include them is outside the scope of this specification's own creation; see this specification's engineering review record for the disclosed observation.

---

# 6. Reviewer Classes

**Reviewer:** the person, authoring process, or independent process performing a review under this specification.

Reviewer classes represent minimum independence levels rather than reviewer capability. A higher class letter does not indicate greater skill; it indicates a different, and in some respects stricter, independence requirement.

## 6.1 Class A — Author Review

Performed by the original author or authoring process.

Class A review may identify and correct defects.

Class A review shall not, by itself, satisfy a reviewer-independence requirement or authorize a lifecycle state that a governing specification conditions on reviewer independence.

---

## 6.2 Class B — Independent Review

Performed independently of the authoring process.

A Class B reviewer shall satisfy every requirement defined by Section 8 (Independence).

Class B review may satisfy a reviewer-independence requirement, including authorization of a lifecycle state such as Production Ready, where the governing specification permits Class B review for that purpose.

---

## 6.3 Class C — Human Engineering Review

Performed by a qualified human reviewer.

Class C review is required only when another governing specification, release policy, customer requirement, or regulatory requirement explicitly requires human review.

---

# 7. Reviewer Eligibility

A reviewer is eligible to perform a Class A review when the reviewer is the artifact's author or authoring process.

A reviewer is eligible to perform a Class B review when the reviewer satisfies every requirement of Section 8 (Independence) with respect to the specific artifact under review.

A reviewer is eligible to perform a Class C review when the reviewer is a qualified human engineer who has no conflict of interest, as defined by Section 9, with respect to the artifact under review.

Eligibility shall be determined per review; a reviewer eligible for a given class on one artifact is not automatically eligible for that class on a different artifact.

---

# 8. Independence

**Independence:** the degree to which a reviewer reaches engineering conclusions without relying upon the conclusions of the reviewed artifact's author or prior reviewers.

A Class B (Independent) review shall:

* Begin from the governing specifications and the artifact itself.
* Reach conclusions independently of the author's findings.
* Record preliminary findings before consulting previous review records.
* Disclose any known limitations affecting reviewer independence.
* Preserve previous review records.
* Document any disagreement with earlier reviews rather than replacing them.

A review that does not satisfy every requirement above shall not be classified as Class B, regardless of the reviewing party's identity.

---

# 9. Conflict of Interest

**Conflict of Interest:** a relationship between a reviewer and the reviewed artifact's authoring process — including being that authoring process — that could reasonably affect the reviewer's independence, as defined by Section 8.

An author review shall always be considered to have an inherent conflict of interest.

That conflict does not invalidate the review. It determines which review authority the review possesses (see Section 12).

A Class B or Class C reviewer shall disclose any known relationship to the artifact's authoring process that could affect independence, even where that relationship does not disqualify the review from its class.

---

# 10. Review Succession

A typical **review succession** is:

1. Author Review (Class A)
2. Independent Review (Class B)
3. Human Engineering Review (Class C), when required

Additional reviews may be performed when appropriate.

No review shall supersede or delete an earlier **review record**. A later review may supersede an earlier review's conclusion for the purpose of a specific lifecycle gate; it shall not overwrite, remove, or replace the earlier review record itself.

---

# 11. Independence Exceptions

An engineering process may temporarily waive a Class B review when:

* explicitly permitted by another governing specification;
* the artifact is classified as exempt by its governing specification; or
* emergency engineering procedures authorize the exception.

Every exception shall be documented, including its basis and the specification or procedure that authorized it.

---

# 12. Review Authority

**Review Authority:** the authority associated with each review class to approve, recommend, or reject engineering artifacts, as permitted by governing specifications.

* **Class A review authority:** may identify and correct defects; shall not, by itself, satisfy a reviewer-independence requirement.
* **Class B review authority:** may satisfy a reviewer-independence requirement, where the governing specification permits Class B review to do so.
* **Class C review authority:** may satisfy a reviewer-independence requirement, where the governing specification requires Class C review.

A governing specification may require a minimum reviewer class before an artifact advances to a specified **lifecycle state** (for example: Draft, Reviewed, Production Ready, Reference Implementation, Archived). This specification does not itself define which lifecycle states require which reviewer class for any given artifact type; that determination belongs to the specification governing that artifact type.

---

# 13. Cross-Reference Requirements

A governing specification that requires reviewer independence shall reference this specification rather than restating reviewer independence requirements.

Cross-references to this specification shall identify it by ID and title (`SF-SPEC-012 — Engineering Review Independence Specification`) and shall remain valid following revision, per **SF-SPEC-004 — Documentation Specification**.

---

# 14. Validation

An application of this specification is complete when:

* The reviewer class (Section 6) has been identified and disclosed.
* Eligibility for that class has been confirmed against Section 7.
* Where Class B, every Independence requirement in Section 8 has been satisfied, and any limitation affecting independence has been disclosed.
* Any conflict of interest has been disclosed per Section 9.
* The review record preserves, rather than replaces, any earlier review record for the same artifact, per Section 10.
* Any independence exception invoked has been documented per Section 11.

---

# 15. Boundary Validation

Verify that this specification:

* Owns reviewer independence and no other engineering responsibility.
* Does not define engineering review methodology.
* Does not define evidence governance.
* Does not define lifecycle transitions.
* Does not define artifact technical correctness.
* Does not duplicate requirements owned by **SF-SPEC-005** or **SF-SPEC-011**.
* References governing specifications instead of restating their responsibilities.

---

# 16. Specification Evolution

This specification shall evolve only within its defined engineering responsibility.

Proposed additions shall be evaluated against Section 3 (Specification Boundaries) and Section 15 (Boundary Validation) before acceptance.

Requirements outside the owned responsibility shall be assigned to the governing specification that owns that responsibility, or to a new specification if no appropriate owner exists.

Changes that alter specification ownership or architectural boundaries shall be treated as architectural changes rather than ordinary specification revisions.

---

# 17. Change Control

This specification shall not be modified to accommodate an individual engineering review.

The specification shall be revised only when an engineering improvement benefits the SquirrelForge Engineering Framework as a whole.

All revisions shall:

* Be versioned.
* Be reviewed.
* Be documented.
* Preserve backward compatibility where practical.
* Identify affected engineering artifacts.

---

# 18. Reference Implementations

Reference Implementations may be designated following successful engineering review.

The authoritative version of every Reference Implementation remains the individual engineering artifact demonstrating this specification's application.

No Reference Implementation is currently designated. A Reference Implementation may be designated once an artifact's review record has been evaluated against every normative requirement of this specification and explicitly verified as compliant.

Reference Implementations are informative and do not supersede the normative requirements defined by this specification.
