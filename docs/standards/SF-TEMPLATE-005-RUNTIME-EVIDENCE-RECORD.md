# SF-TEMPLATE-005 — Runtime Evidence Record Template

## Document Information

**Document ID:** SF-TEMPLATE-005

**Title:** Runtime Evidence Record Template

**Classification:** Engineering Template

**Status:** Draft

**Version:** 1.0

**Owner:** SquirrelForge

---

## Instructions for Use

This document is a template. It is not itself an evidence record and shall not be cited as one. Copy this structure into a new evidence record and replace every angle-bracket placeholder.

This template preserves the ownership distinction established by the governing specifications:

* **SF-SPEC-002 — Runtime Evidence Specification** owns runtime evidence collection, validation, sufficiency, and execution traceability (Sections 6–9 below).
* **SF-SPEC-006 — Repository Validation Specification** owns repository validation (Section 11 below).
* **SF-SPEC-011 — Evidence Governance Specification** owns evidence classification, retention, archival, disposal, and long-term integrity (Sections 12–13 below).

This template does not restate those specifications' full policies. Where a section references a governing specification, that specification is authoritative for the applicable criteria.

---

# 1. Evidence Record Identity

**Record ID:** `<Identifier for this evidence record>`

**Date:** `<YYYY-MM-DD>`

---

# 2. Associated Scenario or Artifact

`<Identify the specific WP-SCENARIO-XXX or other artifact this evidence record supports.>`

---

# 3. Objective

`<State the specific engineering conclusion this evidence is intended to support.>`

---

# 4. Baseline

`<Document the reference state established before execution, per SF-SPEC-002 Section 5.1 (Baseline) and Section 12 (Runtime Baselines).>`

---

# 5. Environment

`<Document the execution environment sufficient to permit independent reproduction.>`

---

# 6. Execution Procedure

`<Describe the deterministic procedure actually executed, per SF-SPEC-002 Section 5.2 (Deterministic Execution).>`

---

# 7. Evidence Artifacts

`<List the specific evidence artifacts collected — e.g., command output, log files, database snapshots — per SF-SPEC-002 Sections 6 (Evidence Categories) and 7 (Evidence Quality). Each artifact shall be relevant, observable, verifiable, repeatable, sufficient, and traceable.>`

---

# 8. Validation

`<Document what the evidence in Section 7 demonstrates: that the intended objective from Section 3 was achieved, per SF-SPEC-002 Section 9.>`

---

# 9. Negative Validation

`<Document confirmation that no unintended regressions, side effects, or unrelated failures were introduced.>`

---

# 10. Cleanup Evidence

`<Document evidence that required cleanup activities completed successfully, per SF-SPEC-002 Section 13 (Runtime Cleanup).>`

---

# 11. Repository Validation Evidence

`<Document the repository validation results for this evidence record, per SF-SPEC-006 — Repository Validation Specification. This section defers entirely to SF-SPEC-006 for what must be verified; it does not restate that specification's criteria.>`

---

# 12. Classification

`<Classify this evidence record per SF-SPEC-011 Section 5.1 (Evidence Classification): Permanent, Supporting, Temporary, or Disposable. This section defers to SF-SPEC-011 for the classification scheme; it does not define additional classifications.>`

---

# 13. Retention Decision

`<State the retention decision for this evidence record, per SF-SPEC-011 Section 5.3 (Retention). This section defers to SF-SPEC-011 for retention policy; it does not restate that specification's requirements.>`

---

# 14. Traceability Map

`<Map this evidence record to the scenario, implementation, validation, and documentation it supports, per SF-SPEC-002 Section 11 (Evidence Traceability).>`

---

# 15. Engineering Review Status

`<State whether this evidence record has been examined as part of an engineering review under SF-SPEC-005, and reference that review record (see SF-TEMPLATE-002) rather than restating its findings here.>`

---

# 16. Revision History

Append new entries below; do not overwrite or remove prior entries.

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| `<1.0>` | `<YYYY-MM-DD>` | `<Initial record.>` | `<Draft — not yet reviewed>` |
