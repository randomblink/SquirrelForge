# SquirrelForge Episodic Memory

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/VALIDATION.md`, `19_REASONING/DECISION-ENGINE.md`, `18_MEMORY/MEMORY-INDEX.md`
Used By: Memory Manager, Reflection Engine, Reasoning
Last Updated: 2026-07-05

## Purpose

Episodic Memory records the historical experience of completed tasks — what was done, by whom, and with what outcome — as a durable reference record.

Episodic Memory records; it does not evaluate. Validation status is read from and owned by `14_ENGINE/VALIDATION.md`; the reasoning behind decisions is read from and owned by `19_REASONING/DECISION-ENGINE.md`; identifying lessons learned and recommending improvements is owned by `19_REASONING/REFLECTION-ENGINE.md`; promoting material into platform-wide reusable knowledge is owned by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`. Episodic Memory's record preserves references to each of these once they exist — it does not decide validation, produce lessons learned, or promote knowledge itself.

---

## Responsibilities

Episodic Memory must:

- record a historical reference entry for each completed task,
- reference the original goal, execution plan, and agents involved rather than re-describing them,
- record a concise outcome summary,
- reference the validation result rather than independently declaring it passed or failed,
- reference the decision record behind major choices rather than re-deriving it,
- record the completion timestamp,
- tag the record for retrieval,
- and hand the record to `18_MEMORY/MEMORY-INDEX.md` for indexing.

---

## Episodic Memory Contents

| Item | Description | Authoritative Owner |
|---|---|---|
| Task Reference | Reference to the completed task. | `14_ENGINE/STATE-MANAGER.md` |
| Goal Reference | Reference to the original objective. | `14_ENGINE/STATE-MANAGER.md` |
| Execution Plan Reference | Reference to the plan used. | `14_ENGINE/EXECUTION-PLANNER.md` |
| Agents Involved | Agents that participated. | `14_ENGINE/STATE-MANAGER.md` (routing history) |
| Outcome Summary | Concise description of the final result. | Episodic Memory |
| Validation Result Reference | Reference to the recorded validation outcome. | `14_ENGINE/VALIDATION.md` |
| Decision References | References to the reasoning behind major choices. | `19_REASONING/DECISION-ENGINE.md` |
| Completion Timestamp | Date and time the task completed. | Episodic Memory |
| Retrieval Tags | Keywords supporting future lookup. | Episodic Memory |

Only the Outcome Summary, Completion Timestamp, and Retrieval Tags are Episodic Memory's own authoritative content; every other field is a reference to a value owned elsewhere.

---

## Recording Process

1. Receive a completed task from `18_MEMORY/WORKING-MEMORY.md` once validation has produced a result.
2. Reference the goal, execution plan, agents involved, and validation result rather than re-deriving them.
3. Record a concise outcome summary and completion timestamp.
4. Reference the decision record behind major choices, if one exists.
5. Tag the record for retrieval.
6. Hand the record to `18_MEMORY/MEMORY-INDEX.md` for indexing.

---

## Retrieval Uses

- Similar task lookup
- Historical review
- Regression analysis
- Process improvement
- Audit support

These are downstream consumer uses of the recorded reference; they are not additional responsibilities Episodic Memory performs itself.

---

## Permission Boundary

Episodic Memory may record, reference, tag, and hand off historical entries.

It must not declare validation passed or failed itself (owned by `14_ENGINE/VALIDATION.md`), identify lessons learned or recommend improvements (owned by `19_REASONING/REFLECTION-ENGINE.md`), or promote material into reusable knowledge (owned by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`). It supplies the historical record those processes read from.

---

## Domain Rule

Episodic recording applies identically regardless of domain; domain-specific content is carried in the referenced records, not interpreted by Episodic Memory itself.

---

## Rule

> Episodic Memory records completed experience only. Validation, lessons-learned analysis, and knowledge promotion remain owned by Validation, the Reflection Engine, and the Knowledge Manager respectively — Episodic Memory references their outcomes, it does not produce them.
