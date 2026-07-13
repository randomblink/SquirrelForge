# SF-GLOSSARY-001 — Engineering Terminology

## 1. Document Information

**Document ID:** SF-GLOSSARY-001

**Title:** Engineering Terminology

**Classification:** Engineering Glossary

**Status:** Draft

**Version:** 1.0

**Owner:** SquirrelForge

---

## 2. Purpose

This glossary defines the canonical engineering terminology used by the SquirrelForge specification library (SF-SPEC-001 through SF-SPEC-011) and its governed artifacts.

This glossary clarifies terminology. It does not create engineering requirements. Where a definition in this glossary appears to conflict with a governing specification, the specification is authoritative.

---

## 3. Scope

This glossary applies to every term used normatively within SF-SPEC-001 through SF-SPEC-011 and within the artifacts, evidence, and records those specifications govern.

This glossary does not define WordPress-specific terminology, implementation-level technical terms, or vocabulary outside the SquirrelForge Engineering Framework.

---

## 4. Terminology Principles

### 4.1 Consistency

Every term shall carry the same meaning wherever it is used across the specification library.

### 4.2 Non-Circularity

A definition shall not depend on another undefined term, and no two terms shall be defined only in reference to each other.

### 4.3 Traceable Authority

Every definition shall be consistent with, and traceable to, its governing specification.

### 4.4 Minimal Scope

A definition shall describe only what the governing specifications actually establish. It shall not extend a term's meaning beyond what those specifications support.

---

## 5. Canonical Terms

Terms are ordered alphabetically.

### Artifact

Any engineering document, specification, scenario, review record, evidence record, or other work product governed by the SquirrelForge Engineering Framework.

### Baseline

A documented reference state of an environment, repository, or artifact, established before an engineering activity begins, sufficient to permit independent reproduction and to identify subsequent changes. (State/property. Governed for runtime scenarios by **SF-SPEC-002**; for repository state by **SF-SPEC-006**.)

### Deterministic

Producing equivalent observable results when executed, or evaluated, under equivalent conditions. (Property.)

### Engineering Framework

The complete set of SquirrelForge engineering specifications (SF-SPEC-001 through SF-SPEC-011) together with the artifacts, evidence, and records they govern.

### Engineering Review

The independent evaluation of an engineering artifact against its governing specifications, conducted in accordance with **SF-SPEC-005**, concluding in one of that specification's defined review outcomes. (Process.)

### Evidence

Information that supports an engineering conclusion. Evidence may originate from runtime execution, engineering review, repository validation, or any other governed engineering activity. This is the broader category; see Section 6, "Evidence and Runtime Evidence."

### Evidence Artifact

A specific, individually identifiable unit of evidence — such as a log file, screenshot, or validation record — sufficient to support independent engineering review. (Artifact.)

### Evidence Governance

The classification, retention, archival, disposal, and long-term integrity of engineering evidence, governed by **SF-SPEC-011**. (Process/state.)

### Fixture

A controlled supporting artifact or environment arrangement used to support engineering work. See Section 6, "Fixture and Test Fixture."

### Lifecycle

The ordered progression of states an artifact passes through, and the governance of permitted transitions between those states. For runtime scenarios specifically, this is governed by **SF-SPEC-007**.

### Normative Requirement

A mandatory engineering requirement, expressed using unambiguous mandatory language such as "shall," that must be satisfied for compliance with a specification. (Property of a requirement statement.)

### Production Ready

A governed compliance state indicating that an artifact has satisfied every applicable normative requirement of its governing specification(s), including completed engineering review. See Section 6, "Production Ready and Ready for Release." (State.)

### Reference Implementation

An artifact designated, following successful engineering review, as a verified example of full compliance with a specification. A Reference Implementation is informative; it does not supersede or modify the normative requirements it demonstrates. (Artifact designation.)

### Release Readiness

The evaluation, governed by **SF-SPEC-010**, of whether an engineering artifact or framework release satisfies the applicable release gate, concluding in one of that specification's defined release outcomes. (Process.)

### Repository Integrity

The state in which repository contents match the approved, expected state, with every permanent change intentional and documented. See Section 6, "Repository Integrity and Repository Validation." (State.)

### Repository Validation

The process, governed by **SF-SPEC-006**, used to determine and record whether repository integrity has been preserved before and after an engineering activity. (Process.)

### Revision

A controlled, documented change to an engineering artifact that preserves its permanent identity. A revision may or may not require a version change, as determined by **SF-SPEC-008**. See Section 6, "Version and Revision." (Event/process.)

### Runtime Evidence

Evidence generated or observed through execution in a defined environment, governed by **SF-SPEC-002**, encompassing its collection, validation, sufficiency, and traceability during execution. See Section 6, "Evidence and Runtime Evidence" and "Runtime Evidence and Evidence Governance."

### Runtime Scenario

An engineered exercise, identified by a unique `WP-SCENARIO-XXX` identifier, designed to demonstrate one specific engineering capability through deterministic execution supported by runtime evidence. Governed methodologically by **SF-SPEC-003** and, for lifecycle state, by **SF-SPEC-007**. (Artifact.)

### Scenario

Shorthand, within this framework, for Runtime Scenario. This glossary does not define a broader meaning of "scenario" beyond the Runtime Scenario governed by SF-SPEC-003 and SF-SPEC-007.

### Specification

A governing engineering document, identified by a unique `SF-SPEC-XXX` identifier, that defines normative requirements, ownership boundaries, and quality criteria for a single engineering responsibility within the SquirrelForge Engineering Framework. (Artifact.)

### Test Fixture

A Fixture specifically designed to support deterministic testing, governed by **SF-SPEC-009**. See Section 6, "Fixture and Test Fixture." (Artifact.)

### Traceability

The property by which an engineering conclusion, evidence artifact, or lifecycle transition can be linked back to the specific activity, requirement, or artifact that produced or justifies it. (Property.)

### Validation

The confirmation that an intended engineering objective has been achieved, based on observable evidence rather than assumption. (Process.)

### Version

The declared identifier assigned to a defined state of a versioned engineering artifact, governed by **SF-SPEC-008**. See Section 6, "Version and Revision." (Identifier/property.)

---

## 6. Required Distinctions

### Evidence and Runtime Evidence

Evidence is the broader category of information supporting an engineering conclusion.

Runtime Evidence is evidence generated or observed through execution in a defined environment.

### Repository Integrity and Repository Validation

Repository Integrity is the state in which repository contents match the approved expected state.

Repository Validation is the process used to determine and record whether repository integrity is preserved.

### Version and Revision

Version is the declared identifier assigned to a defined artifact state.

Revision is a controlled change to an artifact that may or may not cause a version change according to SF-SPEC-008.

### Production Ready and Ready for Release

Production Ready is a governed artifact-compliance state.

Ready for Release is the outcome of a release-readiness evaluation under SF-SPEC-010.

These are not synonyms. An artifact may be Production Ready without a release-readiness evaluation having occurred, and a release-readiness evaluation depends on, but is distinct from, the Production Ready status of its constituent artifacts.

### Scenario Engineering and Scenario Lifecycle

Scenario Engineering is the methodology used to plan, implement, validate, document, and review a scenario, governed by SF-SPEC-003.

Scenario Lifecycle is the governance of scenario states and permitted transitions, governed by SF-SPEC-007.

### Runtime Evidence and Evidence Governance

Runtime Evidence concerns evidence collection, validation, sufficiency, and execution traceability, governed by SF-SPEC-002.

Evidence Governance concerns classification, retention, archival, disposal, and long-term integrity, governed by SF-SPEC-011.

### Engineering Review and Release Readiness

Engineering Review evaluates an artifact against its governing requirements, governed by SF-SPEC-005.

Release Readiness determines whether an artifact or release satisfies the applicable release gate, governed by SF-SPEC-010.

### Fixture and Test Fixture

Fixture is a controlled supporting artifact or environment arrangement.

Test Fixture is a fixture specifically designed to support deterministic testing, governed by SF-SPEC-009.

---

## 7. Usage and Authority

This glossary is descriptive of the terminology established by SF-SPEC-001 through SF-SPEC-011. It does not itself impose normative requirements, define ownership, or establish architecture.

Where any apparent conflict exists between a definition in this glossary and a governing specification, the specification is authoritative and this glossary shall be corrected to match it.

This glossary shall be consulted, but not cited as a substitute, when a specification's own terminology is unclear.

---

## 8. Change Control

This glossary shall not be modified to accommodate an individual document or artifact.

This glossary shall be revised only when a change to a governing specification changes the meaning of a term, or when a genuine ambiguity is identified across multiple specifications.

All revisions shall:

* Be versioned.
* Be reviewed.
* Be documented.
* Identify affected specifications and templates.

---

## 9. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-12 | Initial glossary, defining 25 canonical terms and 8 required distinctions per the Phase 5 work order. | Draft — not yet reviewed |
