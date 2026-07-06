# SquirrelForge Memory Retention

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, `18_MEMORY/PROJECT-MEMORY.md`, `18_MEMORY/MEMORY-INDEX.md`, `23_GOVERNANCE`
Used By: Memory Manager
Last Updated: 2026-07-05

## Purpose

Memory Retention defines retention periods, archival eligibility, pruning eligibility, and preservation rules for records held in Working, Episodic, Semantic, and Project Memory, and evaluates records against those rules to produce retention actions or recommendations.

Memory Retention executes the mechanical archive/prune lifecycle transition on a record once eligibility is confirmed; it does not alter a record's substantive content, decide what counts as validated or reusable knowledge (owned by `25_KNOWLEDGE`), or decide project lifecycle phase transitions (owned by `14_ENGINE/STATE-MANAGER.md` and `11_OVERVIEW/LIFECYCLE.md`). Memory is a store of records, not a knowledge base — platform-wide reusable knowledge remains `25_KNOWLEDGE`'s domain regardless of how long a Memory record has been retained.

---

## Responsibilities

Memory Retention must:

- define retention periods, archival eligibility, pruning eligibility, and preservation rules for each memory type,
- distinguish temporary Working Memory, which holds no independent retention beyond the active task, from durable Episodic, Semantic, and Project Memory,
- evaluate records against those rules,
- execute the archive or prune lifecycle transition once eligibility is confirmed, without altering the record's substantive content,
- produce a retention recommendation instead of acting directly when automatic action is not authorized,
- preserve provenance and required historical references through archival and pruning,
- and request `18_MEMORY/MEMORY-INDEX.md` update or remove the corresponding index entry when a record's lifecycle state changes.

---

## Retention Policies

| Memory Type | Retention Period | Archival Trigger | Pruning Eligibility | Preservation Requirement |
|---|---|---|---|---|
| Working Memory | Duration of the active task only. | Task completion, when content is handed to `18_MEMORY/EPISODIC-MEMORY.md`. | Immediately after handoff; Working Memory holds no long-term copy of its own. | None — durable content is already recorded in Episodic Memory before Working Memory is cleared. |
| Episodic Memory | Retained as the durable historical record; no fixed expiration by default. | Not applicable — Episodic Memory is the archival destination for completed tasks, not itself an archival source. | Only under an explicit, governance-approved retention exception. | Task, goal, execution-plan, validation, and decision references (per `18_MEMORY/EPISODIC-MEMORY.md`) must be preserved even if the record is later archived. |
| Semantic Memory | Retained while validated and in use; reviewed periodically. | Superseded by a newer validated pattern, or no longer referenced by active workflows. | After archival, if no retrieval or reference occurs within the governance-defined inactivity window. | Original source reference and validation status must be preserved on archive; superseded entries are archived, not deleted, so history remains traceable. |
| Project Memory | Retained for the life of the project. | Project reaches a terminal lifecycle phase (`11_OVERVIEW/LIFECYCLE.md`'s `RETENTION` phase). | Only after governance-approved project closeout. | Architecture, decisions, and milestone history must be preserved in full; Project Memory is not summarized or pruned before governance approval. |

---

## Retention Actions

| Action | Description |
|---|---|
| Retain | No change; the record remains `Active`. |
| Archive | Move the record to `Archived`; preserve required references. |
| Prune | Move the record to `Removed`, only once past its retention and archival requirements. |
| Recommend | Produce a retention recommendation for governance or Memory Manager approval when automatic action is not authorized. |

These reuse the lifecycle stage names `18_MEMORY/MEMORY-MANAGER.md` defines (`Created` / `Active` / `Archived` / `Expired` / `Removed`).

---

## Retention Process

1. Select or receive a record for evaluation.
2. Determine its memory type and current lifecycle state.
3. Evaluate it against the Retention Policies table.
4. If eligible for archival, move it to `Archived` and preserve required references.
5. If eligible for pruning, move it to `Removed` only after archival and preservation requirements are satisfied.
6. Request `18_MEMORY/MEMORY-INDEX.md` update or remove the corresponding index entry.
7. Record the retention action taken.

---

## Permission Boundary

Memory Retention may evaluate records, execute the archive or prune lifecycle transition, and produce retention recommendations.

It must not alter a record's substantive content (owned by each memory type's respective component), decide what counts as validated or reusable platform-wide knowledge (owned by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`), or decide project lifecycle phase transitions (owned by `14_ENGINE/STATE-MANAGER.md`).

---

## Domain Rule

Retention rules apply identically regardless of domain; domain-specific content is carried in the retained record, not interpreted by Memory Retention itself.

---

## Rule

> No record may be pruned before its archival and preservation requirements are satisfied. Memory Retention executes the lifecycle transition; it does not alter what a record says, and it does not decide whether content is reusable, platform-wide knowledge.
