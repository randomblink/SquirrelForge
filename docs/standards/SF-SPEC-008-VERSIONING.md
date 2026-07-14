# SF-SPEC-008 — Versioning Specification

## Document Information

**Document ID:** SF-SPEC-008

**Title:** Versioning Specification

**Classification:** Engineering Specification

**Status:** Draft

**Version:** 1.1

**Owner:** SquirrelForge

---

# 1. Purpose

## 1.1 Objective

This specification defines the engineering requirements for versioning, revision control, and lifecycle progression of engineering artifacts within the SquirrelForge Engineering Framework.

Its purpose is to ensure that every engineering artifact has a unique, traceable, and consistently managed version history throughout its lifecycle.

---

# 2. Scope

## 2.1 Applies To

This specification applies to:

* Engineering specifications (`SF-SPEC-XXX`)
* Engineering reviews (`SF-REVIEW-XXX`)
* WordPress knowledge entries (`WP-ERROR-XXX`)
* Runtime scenarios (`WP-SCENARIO-XXX`)
* Engineering plans
* Readiness reports
* Engineering documentation
* Reference implementations

## 2.2 Exclusions

This specification does not define:

* Documentation standards
* Engineering review procedures
* Repository validation
* Runtime evidence requirements
* Source code version control workflows

These subjects are defined by their respective SquirrelForge specifications.

---

# 3. Specification Boundaries

## 3.1 Owns

- Artifact identity across revisions.
- Version numbering.
- Revision history.
- Version statuses.
- Supersession.

## 3.2 Depends On

- **SF-SPEC-004 — Documentation Specification**, for documentation.
- **SF-SPEC-005 — Engineering Review Specification**, for approval.

## 3.3 Does Not Define

- Git workflow.
- Release readiness.
- Lifecycle of runtime scenarios.

---

# 4. Engineering Principles

## 4.1 Unique Identity

Every engineering artifact shall possess a unique and permanent identifier.

---

## 4.2 Traceability

Every revision shall be traceable to its immediately preceding version.

---

## 4.3 Integrity

Version history shall accurately represent the evolution of the engineering artifact.

---

## 4.4 Controlled Revision

Engineering artifacts shall be revised only through documented engineering changes.

---

## 4.5 Backward Reference

Where practical, revisions shall preserve references to earlier versions to support historical engineering review.

---

## 4.6 Consistency

Version numbering shall be applied consistently throughout the SquirrelForge Engineering Framework.

---

# 5. Normative Requirements

The following requirements are mandatory for versioning engineering artifacts.

---

## 5.1 Version Identifier

Every versioned artifact shall include:

* Version number
* Revision status
* Document identifier
* Revision history

---

## 5.2 Version Progression

Version numbers shall increase sequentially.

Published version numbers shall not be reused.

---

## 5.3 Revision History

Every revision shall document:

* Version identifier
* Revision date
* Summary of changes
* Approval status

Approval status shall be established through engineering review in accordance with **SF-SPEC-005 — Engineering Review Specification**. Revision-history document structure shall follow **SF-SPEC-004 — Documentation Specification**.

---

## 5.4 Artifact Identity

Revision shall not change the permanent identity of an engineering artifact.

Document identifiers shall remain stable across revisions.

---

## 5.5 Breaking Changes

Breaking changes shall be clearly identified and documented.

Where applicable, affected engineering artifacts shall be identified.

---

## 5.6 Cross-Reference Maintenance

Version updates shall preserve or appropriately update cross-references to related engineering artifacts.

---

## 5.7 Superseded Versions

Superseded versions shall remain identifiable for engineering traceability unless formally retired under an approved archival process.

---

# 6. Version Status

Engineering artifacts may use statuses including:

* Draft
* Under Engineering Review
* Approved
* Production Ready
* Superseded
* Archived
* Retired

Additional statuses shall not be introduced without revision of this specification.

Version Status describes the state of an artifact's version identity and document record; it applies uniformly across every versioned artifact type listed in Section 2.1. It is distinct from runtime scenario lifecycle state, which is governed exclusively by **SF-SPEC-007 — Scenario Lifecycle Specification**. For a runtime scenario, the two are related but not interchangeable: a scenario's lifecycle state (e.g., "Production Ready," "Archived," "Retired" under SF-SPEC-007) describes its engineering progress, while its Version Status under this specification describes the document/version record of that scenario's specification artifact. A scenario may be lifecycle-state "Archived" while its underlying document is Version Status "Superseded," and the two statuses shall not be conflated or treated as a single field.

---

# 7. Version Numbering

Version numbering shall:

* Be deterministic
* Increase sequentially
* Avoid ambiguity
* Remain consistent across revisions

Individual artifact types may define additional versioning conventions provided they remain compatible with this specification.

---

# 8. Revision Records

Revision records shall contain sufficient information to allow an independent reviewer to understand:

* What changed
* Why it changed
* Which version it replaced
* Which related artifacts may be affected

---

# 9. Version Quality

Version management shall be:

* Traceable
* Repeatable
* Consistent
* Auditable
* Maintainable
* Technically accurate

---

# 10. Production Ready Version

An engineering artifact shall not be designated **Production Ready** until:

* Its version information is complete.
* Revision history has been documented.
* Required engineering review has been completed.
* Cross-references have been validated.

---

# 11. Versioning Review Checklist

Every versioned engineering artifact shall satisfy the following checklist.

* ☐ Permanent identifier verified
* ☐ Version number assigned
* ☐ Version progression verified
* ☐ Revision history updated
* ☐ Cross-references validated
* ☐ Approval status recorded
* ☐ Production Ready requirements satisfied where applicable

---

# 12. Change Control

This specification shall not be modified to accommodate an individual engineering artifact.

The specification shall be revised only when an engineering improvement benefits the SquirrelForge Engineering Framework as a whole.

All revisions shall:

* Be versioned.
* Be reviewed.
* Be documented.
* Preserve backward compatibility where practical.
* Identify affected engineering artifacts.

---

# 13. Reference Implementations

Reference Implementations may be designated following successful engineering review.

The authoritative version of every Reference Implementation remains the individual engineering artifact.

Reference Implementations are informative and do not supersede the normative requirements defined by this specification.

---

# 14. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial specification, established as part of the framework's initial authoring pass (commit `7f29178`), then reviewed and corrected by `SF-REVIEW-002` (Specification Library Review) as part of a unified pass across `SF-SPEC-001` through `011` (Phases 1–6, 2026-07-12): Entry 2 (Phase 2) added an explicit clarifying paragraph at then-§5 (Version Status) distinguishing it from scenario lifecycle state (**SF-SPEC-007**), and added citations at then-§4.3 to **SF-SPEC-005** (approval status) and **SF-SPEC-004** (revision-history document structure). `SF-REVIEW-002` predates, and does not use, the Class A/Class B/Class C reviewer-classification system **SF-SPEC-012** later introduced. | Draft — reviewed via `SF-REVIEW-002` (predates Class A/B system) |
| 1.1 | 2026-07-14 | Added this Revision History section, required by this specification's own Section 5.3 in content and by **SF-SPEC-004** Section 5.9 in structural placement, but absent until now. Migrated as part of a repository-wide pass verified by `SF-REVIEW-060` (Specification Library Revision-History Migration Review). No normative content changed. | Draft — migration verified, see `SF-REVIEW-060` |
