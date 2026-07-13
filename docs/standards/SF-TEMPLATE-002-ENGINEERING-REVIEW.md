# SF-TEMPLATE-002 — Engineering Review Template

## Document Information

**Document ID:** SF-TEMPLATE-002

**Title:** Engineering Review Template

**Classification:** Engineering Template

**Status:** Draft

**Version:** 1.0

**Owner:** SquirrelForge

---

## Instructions for Use

This document is a template governed by **SF-SPEC-005 — Engineering Review Specification**. It is not itself a review record and shall not be cited as one. Copy this structure into a new review record and replace every angle-bracket placeholder with content specific to the review being conducted.

This template does not introduce review outcomes, criteria, or procedures beyond what SF-SPEC-005 defines. Where this template and SF-SPEC-005 could be read as conflicting, SF-SPEC-005 is authoritative.

---

# 1. Review Information

**Review ID:** `<SF-REVIEW-XXX or another governed review identifier>`

**Review Date:** `<YYYY-MM-DD>`

**Reviewer:** `<Name or role>`

**Status:** `<Draft — not yet reviewed>`

---

# 2. Artifact Reviewed

`<Identify the specific artifact under review: its identifier, title, version, and path.>`

---

# 3. Governing Specifications

`<List every specification this artifact must comply with, by SF-SPEC-XXX ID and title.>`

---

# 4. Review Scope

`<State what this review evaluates and what it explicitly does not evaluate. A requirement outside the stated scope shall not influence the review outcome, per SF-SPEC-005 Section 5.2.>`

---

# 5. Review Criteria

`<List the specific normative requirements, drawn from the governing specifications in Section 3, that this review evaluates compliance against.>`

---

# 6. Evidence Examined

`<List the specific evidence examined to reach each finding below — e.g., file contents, runtime evidence records, repository validation results. Findings not supported by evidence recorded here shall not be considered valid, per SF-SPEC-005 Section 4.3.>`

---

# 7. Findings

Each finding shall record:

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| `<F-1>` | `<Conforming / Minor / Major / Informational>` | `<The specific requirement or criterion this finding relates to>` | `<The observed condition>` | `<Reference to Section 6>` | `<What must change, if anything>` | `<Open / Resolved / Deferred>` |

Severity values shall be drawn from the four categories SF-SPEC-005 Section 8 defines: Conforming observations, Minor deficiencies, Major deficiencies, Informational observations. Do not introduce additional severity categories.

---

# 8. Recommendations

`<State recommendations addressing the findings above. Recommendations shall not introduce unrelated architectural changes, per SF-SPEC-005 Section 5.4.>`

Recommendations are distinct from the findings themselves and from the required revisions identified below: a recommendation may suggest an improvement without that improvement being a condition of the review outcome.

---

# 9. Outcome

`<Select exactly one, per SF-SPEC-005 Section 5.5. Do not invent additional outcomes.>`

* Approved
* Approved with Minor Revisions
* Major Revisions Required
* Rejected

`<State the basis for the assigned outcome.>`

If the outcome is "Approved with Minor Revisions" or "Major Revisions Required," list the specific required revisions here, distinct from Section 8's recommendations:

* `<Required revision 1, referencing the Finding ID it resolves.>`

---

# 10. Gate Decision

`<State explicitly whether this review satisfies any applicable process gate (e.g., a Production Ready gate under the artifact's governing specification, or a Version 1.0 freeze gate under SF-SPEC-008). The gate decision is distinct from the review Outcome in Section 9: an "Approved with Minor Revisions" outcome may or may not satisfy a given gate, depending on that gate's own requirements.>`

---

# 11. Remaining Risks

`<List any risks, gaps, or unresolved concerns that do not rise to the level of a required revision, disclosed here for transparency.>`

---

# 12. Revision History

Append new entries below; do not overwrite or remove prior entries.

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| `<1.0>` | `<YYYY-MM-DD>` | `<Initial review.>` | `<Outcome from Section 9>` |
