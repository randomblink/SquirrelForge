# SquirrelForge Reflection Engine

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `18_MEMORY/EPISODIC-MEMORY.md`, `14_ENGINE/VALIDATION.md`, `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`
Used By: Reasoning, 25_KNOWLEDGE
Last Updated: 2026-07-06

## Purpose

The Reflection Engine reviews completed, validated work recorded in `18_MEMORY/EPISODIC-MEMORY.md` to identify successes, failures, and repeated issues, and produces reflection records, improvement candidates, and learning signals that strengthen future workflows.

The Reflection Engine produces candidates; it does not promote or approve knowledge itself. Registering, validating, and promoting material into platform-wide reusable knowledge is `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`'s authority alone. The Reflection Engine makes improvement candidates and learning signals available to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`; it does not decide that they become approved, reusable knowledge, and it does not write directly into Semantic Memory or any Knowledge Layer store.

---

## Responsibilities

The Reflection Engine must:

- receive a completed, validated task record from `18_MEMORY/EPISODIC-MEMORY.md`,
- compare the result against the original goal and the validation result already referenced there,
- identify strengths, weaknesses, and repeated issues,
- record lessons learned in a Reflection Record,
- recommend workflow or template improvements,
- produce improvement candidates and learning signals for `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` to evaluate,
- and make those candidates available without deciding their promotion.

---

## Reflection Process

1. Receive a completed, validated task record from `18_MEMORY/EPISODIC-MEMORY.md`.
2. Compare the result against the original goal.
3. Review the validation result already referenced in the Episodic record.
4. Identify strengths, weaknesses, and repeated issues.
5. Record lessons learned and recommended improvements in a Reflection Record.
6. Produce improvement candidates and learning signals.
7. Make those candidates available to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` for its own evaluation and promotion decision.

---

## Reflection Model

| Field | Description |
|---|---|
| Task Reference | Reference to the completed task record in `18_MEMORY/EPISODIC-MEMORY.md`. |
| Goal | Original objective. |
| Result | Final outcome. |
| Validation Reference | Reference to the validation result already recorded in Episodic Memory. |
| Successes | What worked well. |
| Issues | Problems encountered. |
| Improvement Candidates | Suggested enhancements, offered for evaluation, not yet approved. |
| Learning Signals | Reusable insights offered as candidates for `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`. |
| Follow-up Actions | Recommended next steps. |

---

## Reflection Questions

- Was the original goal achieved?
- Were all validation checks successful?
- Were unnecessary steps performed?
- Could the workflow be simplified?
- Should a new workflow or template be created?
- Can any part of this work be reused?

---

## Permission Boundary

The Reflection Engine may review completed work, identify patterns, and produce reflection records, improvement candidates, and learning signals.

It must not promote, approve, or register knowledge itself (owned by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`), write directly into Semantic Memory (owned by `18_MEMORY/SEMANTIC-MEMORY.md`, which stores only already-approved records), or re-derive the validation result (owned by `14_ENGINE/VALIDATION.md`).

---

## Domain Rule

Reflection mechanics apply identically regardless of domain; domain-specific content is carried in the reviewed record, not interpreted by the Reflection Engine itself.

---

## Rule

> Every completed and validated task should be reviewed before it is archived. The Reflection Engine produces improvement candidates and learning signals; it does not promote, approve, or register them as reusable knowledge — that authority belongs to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` alone.
