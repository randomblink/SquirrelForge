# SF-TEMPLATE-003 — Runtime Scenario Template

## Document Information

**Document ID:** SF-TEMPLATE-003

**Title:** Runtime Scenario Template

**Classification:** Engineering Template

**Status:** Draft

**Version:** 1.0

**Owner:** SquirrelForge

---

## Instructions for Use

This document is a template governed by **SF-SPEC-003 — Scenario Engineering Specification**. It is not itself a runtime scenario record and shall not be cited as one. Copy this structure into a new scenario record and replace every angle-bracket placeholder with content specific to the scenario.

This template distinguishes **planning claims** (what is intended, before execution) from **executed evidence** (what was actually observed during execution). Sections 1–7 are planning claims. Sections 8 onward are executed evidence and shall not be completed until the corresponding activity has actually occurred.

Completing every section of this template does not, by itself, establish Production Ready status. Production Ready status is a governed determination made under Section 19, following completed engineering review — it is not automatic upon reaching the end of this document.

This template does not define new lifecycle states. Scenario lifecycle state is governed exclusively by **SF-SPEC-007 — Scenario Lifecycle Specification**.

---

# 1. Scenario Identity

**Scenario ID:** `<WP-SCENARIO-XXX>`

**Title:** `<Scenario title>`

**Status:** `<Current lifecycle state per SF-SPEC-007 — this template does not define or restate the lifecycle states themselves; see that specification for the authoritative list.>`

---

# 2. Objective

`<Planning claim: state the specific engineering capability this scenario is intended to demonstrate.>`

---

# 3. Scope

`<Planning claim: state what this scenario will prove and what it will not prove, per SF-SPEC-003 Section 9, Scenario Boundaries.>`

---

# 4. Distinct Contribution

`<Planning claim: state what new engineering evidence this scenario will contribute that does not already exist elsewhere in the SquirrelForge portfolio, per SF-SPEC-003 Section 5.3, Distinct Contribution Review. This section must be completed before implementation begins.>`

---

# 5. Dependencies

`<Planning claim: list prior scenarios, specifications, or artifacts this scenario's engineering plan depends on.>`

---

# 6. Risks

`<Planning claim: list known risks to successful execution.>`

---

# 7. Engineering Plan

`<Planning claim: the approved engineering plan, per SF-SPEC-003 Section 5.1 — objective, scope, expected outcome, dependencies, risks, validation strategy, cleanup strategy.>`

---

# 8. Runtime Baseline

`<Executed evidence: the documented environment and repository state captured before implementation, execution, or validation began, per SF-SPEC-002 Section 5.1.>`

---

# 9. Implementation

`<Executed evidence: what was actually implemented, within the approved scope from Section 3.>`

---

# 10. Runtime Evidence

`<Executed evidence: the runtime evidence collected during execution, satisfying the requirements of SF-SPEC-002 — Runtime Evidence Specification. This section owns evidence collection, validation, sufficiency, and traceability during execution; it does not define evidence retention, classification, or disposal policy, which are governed by SF-SPEC-011.>`

---

# 11. Validation

`<Executed evidence: confirmation that the intended engineering objective from Section 2 was achieved, based on the runtime evidence in Section 10.>`

---

# 12. Negative Validation

`<Executed evidence: confirmation that no unintended regressions, side effects, or unrelated failures were introduced.>`

---

# 13. Cleanup

`<Executed evidence: confirmation that the execution environment was restored to a documented clean baseline, and that temporary artifacts were removed unless preservation was explicitly required.>`

---

# 14. Repository Validation

`<Executed evidence: the repository validation results for this scenario, satisfying the requirements of SF-SPEC-006 — Repository Validation Specification. This section defers entirely to SF-SPEC-006 for what repository validation must confirm; it does not restate that specification's criteria.>`

---

# 15. Documentation

`<Executed evidence: confirmation that engineering documentation was updated to accurately describe the observed behavior, only after successful runtime validation.>`

---

# 16. Lessons Learned

`<Record any engineering insights gained during this scenario that may inform future work.>`

---

# 17. Portfolio Contribution

`<State the engineering capability this scenario contributes to the SquirrelForge portfolio, per SF-SPEC-003 Section 10.>`

---

# 18. Engineering Review

`<This section defers to SF-SPEC-005 — Engineering Review Specification for the review process, findings structure, and outcomes. Record the review outcome and reference the full review record (see SF-TEMPLATE-002) rather than restating SF-SPEC-005's requirements here.>`

---

# 19. Production Ready Status

`<State whether this scenario has been designated Production Ready. A scenario shall not be designated Production Ready until every applicable requirement of SF-SPEC-002 and SF-SPEC-003 has been satisfied and engineering review (Section 18) has completed successfully. Do not treat completion of this template as sufficient by itself.>`

---

# 20. Revision History

Append new entries below; do not overwrite or remove prior entries.

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| `<1.0>` | `<YYYY-MM-DD>` | `<Initial draft.>` | `<Draft — not yet reviewed>` |
