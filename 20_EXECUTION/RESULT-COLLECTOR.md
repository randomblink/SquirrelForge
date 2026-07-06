# SquirrelForge Result Collector

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/ACTION-DISPATCHER.md`, `14_ENGINE/VALIDATION.md`, `20_EXECUTION/EXECUTION-ENGINE.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `20_EXECUTION/EXECUTION-LOGGER.md`, `37_STORAGE/STORAGE-MANAGER.md`
Used By: Execution Engine, Workflow Executor, Execution Reporter
Last Updated: 2026-07-06

## Purpose

The Result Collector collects, correlates, and assembles references to execution outputs from completed actions into an Execution Result Set, without deciding whether those outputs are correct, acceptable, or sufficient for the workflow to advance.

The Result Collector collects references; it does not validate. Output correctness and artifact acceptability are owned by `14_ENGINE/VALIDATION.md` and the relevant reviewing agent. Whether a workflow stage is complete or execution may advance is owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md` and `20_EXECUTION/EXECUTION-ENGINE.md`. Authoritative workflow records are owned by `14_ENGINE/STATE-MANAGER.md`. Artifact storage is owned by `37_STORAGE/STORAGE-MANAGER.md`. The execution event log is owned by `20_EXECUTION/EXECUTION-LOGGER.md`. When the Result Collector detects a duplicate result reference, it flags it for the owning authority to reconcile rather than deleting or reconciling the underlying artifact itself.

---

## Responsibilities

The Result Collector must:

- receive action-result references from execution targets,
- correlate results with execution, workflow-step, action, and dispatch references,
- register received result references,
- detect missing expected result references,
- detect duplicate collection entries or duplicate result references, flagging them without deleting or reconciling the underlying authoritative record,
- assemble related result references into an Execution Result Set,
- attach external validation-result references when available, without independently validating,
- report collection findings to `20_EXECUTION/EXECUTION-ENGINE.md` and `20_EXECUTION/WORKFLOW-EXECUTOR.md`,
- provide assembled result sets to `20_EXECUTION/EXECUTION-REPORTER.md`,
- and preserve collection traceability.

It must not:

- validate output correctness,
- decide that an artifact is acceptable,
- determine workflow-stage completion,
- mutate authoritative workflow records,
- own artifact storage,
- delete or reconcile authoritative duplicate outputs,
- replace `20_EXECUTION/EXECUTION-LOGGER.md`,
- or decide whether execution may advance.

---

## Collection Process

1. Receive an action-result reference from the execution target.
2. Correlate the result with its execution, workflow-step, action, and dispatch references.
3. Register the result reference.
4. Detect missing expected result references.
5. Detect duplicate collection entries or duplicate result references, and flag them without deleting or reconciling the underlying record.
6. Attach an external validation-result reference when one is available.
7. Assemble related result references into an Execution Result Set.
8. Report collection findings to `20_EXECUTION/EXECUTION-ENGINE.md` and `20_EXECUTION/WORKFLOW-EXECUTOR.md`.
9. Provide the assembled result set to `20_EXECUTION/EXECUTION-REPORTER.md`.

---

## Collection Findings

| Finding | Meaning |
|---|---|
| Pending | Awaiting an action's result reference |
| Received | A result reference has been registered |
| Missing | An expected result reference has not been received |
| Duplicate | More than one result reference was collected for the same expected output; flagged, not deleted |
| Referenced | An external validation-result reference has been attached |
| Assembled | Included in an Execution Result Set |

---

## Result Reference Record

| Field | Description |
|---|---|
| Result Reference ID | Unique identifier |
| Execution Reference | Related execution |
| Workflow Step Reference | Related workflow step |
| Action Reference | Source action |
| Dispatch Reference | Related dispatch record |
| Output Type | Artifact or result category |
| Validation Result Reference | Reference to `14_ENGINE/VALIDATION.md`'s result, when available |
| Collection Finding | Current finding from the table above |
| Timestamp | Collection time |

---

## Execution Result Set

| Field | Description |
|---|---|
| Result Set ID | Unique identifier |
| Workflow Step Reference | Related workflow step |
| Included Result References | Result Reference Records assembled into this set |
| Missing References | Expected result references not yet received |
| Duplicate References | Flagged duplicate result references |
| Timestamp | Assembly time |

---

## Permission Boundary

The Result Collector may collect, correlate, register, and assemble result references, and attach external validation references.

It must not validate output correctness or decide artifact acceptability (owned by `14_ENGINE/VALIDATION.md` and the relevant reviewing agent), determine workflow-stage completion or whether execution may advance (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md` and `20_EXECUTION/EXECUTION-ENGINE.md`), mutate authoritative workflow records (owned by `14_ENGINE/STATE-MANAGER.md`), own artifact storage (owned by `37_STORAGE/STORAGE-MANAGER.md`), delete or reconcile authoritative duplicate outputs, or replace the execution event log (owned by `20_EXECUTION/EXECUTION-LOGGER.md`).

---

## Domain Rule

Result collection applies identically regardless of domain; domain-specific content is carried in the referenced output, not interpreted by the Result Collector itself.

---

## Rule

> The Result Collector reports whether all expected result references have been received and assembled; it does not decide that a workflow stage is complete or that execution may advance — that determination belongs to the owning workflow and execution authority.
