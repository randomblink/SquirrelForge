Status: Stable

---
# SquirrelForge WP-SCENARIO Planning Template

## Purpose

This is a generic, reusable planning template for WP-SCENARIO plans. It defines structure only. It contains no scenario-specific capability, technology, or mechanism content, and must not be edited to add any.

A scenario-specific plan (e.g. `38_WORDPRESS/WP-SCENARIO-008-PLAN.md`) is produced by copying this structure and completing every section with content specific to that scenario. This template file itself is never a completed plan and must never carry a scenario ID, a frozen PASS/FAIL criteria set, or any other scenario-specific conclusion.

This template exists to satisfy `38_WORDPRESS/EVIDENCE-GOVERNANCE.md`'s Standard Scenario Structure (Section 3) and its supporting principles. It does not replace that document; where the two conflict, `EVIDENCE-GOVERNANCE.md` governs.

---

## Governing Rules

These rules are binding on every scenario plan produced from this template:

- A scenario must contribute a distinct category of engineering evidence. A scenario that would only reproduce evidence the portfolio already has is not a valid scenario.
- PASS and FAIL criteria are defined during planning and frozen before implementation begins. They are not weakened, loosened, or reinterpreted once implementation starts.
- Validation asks whether the predefined requirements were satisfied — not whether the implementation seems reasonable in hindsight.
- Evidence identifies what proves the validation conclusion. A validation conclusion without a cited, inspectable artifact is not evidence.
- Planning, implementation, execution, validation, cleanup, and documentation remain distinct phases. A plan does not implement; an implementation does not self-validate; documentation does not substitute for evidence.
- Conclusions must follow the evidence, not precede it. A plan states what will be checked and how; it does not state that the scenario passed.
- Scenario-specific content must not be placed in this reusable template. Scenario-specific content belongs only in the scenario's own plan file.
- Expected artifacts must be declared before execution, in the Expected Evidence Inventory (Section 14), so that execution can be checked against a predefined list rather than an after-the-fact accounting.
- Placeholder metadata (Section 1) must be resolved from repository evidence before a scenario plan is approved. A plan with unresolved placeholders is not ready for approval.

---

## 1. Scenario Information

| Field | Value |
|---|---|
| Scenario ID | |
| Title | |
| Status | |
| Capability Category | |
| Primary Evidence Category | |
| Planning Version | |
| Applicable Governance Version or Governance Reference | |
| Prerequisite Scenarios | |
| Related Capabilities | |
| Supersedes | |
| Author | |
| Last Updated | |

Do not insert invented values into this table in the template itself. Every field is resolved when a specific scenario plan is written from this template, using repository evidence — not placeholder text carried forward unresolved.

---

## 2. Readiness Claim

State, in one precise paragraph, the specific readiness claim this scenario is intended to support. The claim must be concrete enough that it can later be judged true or false against recorded evidence — not a general aspiration.

---

## 3. Distinct Contribution Statement

State plainly what new capability, or what materially stronger evidence for an existing capability, this scenario will produce. Name the specific gap in current evidence that this scenario closes.

---

## 4. Evidence Uniqueness Review

Answer these three questions before planning proceeds:

- What capability or stronger evidence is new?
- What objective evidence will exist afterward that does not already exist?
- If this scenario were removed, which readiness claim would lose direct support?

This review must be checked against the portfolio's actual completed scenarios, not against an assumed or aspirational list. A scenario must not proceed merely because it is the next number in sequence.

---

## 5. Prerequisites

List the conditions required to execute the scenario (environment, tooling, versions, known starting state). Prerequisites are checked, not assumed. State plainly which prerequisites are already confirmed by repository evidence and which remain to be confirmed before execution.

---

## 6. Dependencies

List the earlier evidence, Skills, Knowledge documents, Standards, testing facilities, or governance documents this scenario intentionally relies on. Cite dependencies by their actual file path or scenario ID. Do not invent a dependency that does not exist in the repository.

---

## 7. Risk Assessment

For each identified risk, record:

| Risk | Detection Method | Planned Mitigation | Evidence Expected |
|---|---|---|---|

Risks are specific to the capability under test and must be enumerated in the scenario-specific plan, not assumed generically from this template.

---

## 8. Evidence Traceability Matrix

Map each element of the readiness claim to its planned evidence:

| Claim Element | Planned Evidence | Validation Method | Expected Artifact or Record | PASS Condition | Relevant FAIL Condition |
|---|---|---|---|---|---|

Every row must be traceable in both directions: the readiness claim element points to the evidence that will support it, and the evidence, once produced, points back to the claim element it supports.

---

## 9. PASS Criteria

Define objective, checkable PASS conditions before implementation begins. Each condition must be phrased so that a reviewer with no prior context on the scenario can determine, from recorded evidence alone, whether it was met. PASS criteria are frozen once defined; they are not weakened after execution begins.

---

## 10. FAIL Criteria

Define objective FAIL conditions before implementation begins, phrased with the same precision as the PASS criteria. Include the statement that PASS and FAIL criteria are frozen before implementation and cannot be weakened after execution begins; any later revision to them must be an explicit, versioned, justified planning revision completed before execution resumes.

---

## 11. Execution Strategy

Describe the ordered phases the scenario will move through, at planning level only. This section describes what will happen; it does not itself perform any phase. Each phase must be distinct from the phases named in the Governing Rules above (planning, implementation, execution, validation, cleanup, documentation) and must not blur those boundaries.

---

## 12. Validation Requirements

Define what must be checked to determine whether the implementation satisfies the frozen PASS/FAIL criteria (Sections 9–10). Validation requirements describe what is checked and against what standard; they are independent of the specific evidence artifact names used to satisfy them (those names belong in Section 14).

---

## 13. Evidence Requirements

Define the deterministic records needed to support each validation conclusion in Section 12. Prefer machine-readable, independently inspectable evidence (command output, structured data, hashes, direct object inspection) over subjective or manually-asserted evidence wherever a stronger form of evidence is available.

---

## 14. Expected Evidence Inventory

Declare, before execution, the complete list of evidence artifacts this scenario is expected to produce. This is a declaration, not a creation step — the artifacts themselves do not exist yet when this section is written. Artifact filenames and storage locations may remain "to be finalized during implementation planning" where no existing repository convention already determines them.

---

## 15. Portfolio Contribution

State what this scenario would add to the portfolio's evidence inventory if completed successfully, and which readiness claim(s) it would newly support or materially strengthen. Do not state that this evidence already exists; this section describes an intended contribution, not a completed one.

---

## 16. Governance Compliance

Explain how this plan complies with each applicable principle in `38_WORDPRESS/EVIDENCE-GOVERNANCE.md`. Cite the specific governance section being satisfied. Do not assert a governance designation (such as a reference-implementation status) that the governance document does not itself grant.

---

## 17. Scenario Classification

| Field | Value |
|---|---|
| Primary Discipline | |
| Secondary Disciplines | |

---

## 18. Reference Documents

List the specific repository files this plan relies on or cites, by exact path. Do not list a file that was not actually consulted.
