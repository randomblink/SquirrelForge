# SquirrelForge Execution Layer

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `19_REASONING/STRATEGY-PLANNER.md`, `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/TASK-ROUTER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, `14_ENGINE/VALIDATION.md`, `19_REASONING/RULE-EVALUATOR.md`, `17_COORDINATION/FAILURE-RECOVERY.md`, `37_STORAGE/STORAGE-MANAGER.md`
Used By: Validation, Testing, Reporting, Governance
Last Updated: 2026-07-06

## Purpose

The Execution Layer carries out an already-approved strategy and execution plan through controlled workflow execution, action dispatch, checkpoint gating, monitoring, failure intake, rollback, result collection, logging, and reporting.

Execution carries out already-decided work. It does not decide the strategy, the plan, task ownership, dependency status, validation outcomes, rule compliance, or recovery strategy — those are supplied by Reasoning, Engine, and Coordination, and Execution consumes them.

---

## Layer Boundary

`20_EXECUTION` owns:

- top-level coordination of an execution run (`EXECUTION-ENGINE.md`),
- sequencing and carrying out approved workflow steps (`WORKFLOW-EXECUTOR.md`),
- dispatching individual actions to their already-selected target (`ACTION-DISPATCHER.md`),
- gating workflow continuation on confirmed validation and rule-compliance evidence (`CHECKPOINT-MANAGER.md`),
- executing an authorized rollback (`ROLLBACK-MANAGER.md`),
- observing execution health and reporting failures (`EXECUTION-MONITOR.md`),
- normalizing execution failures and routing authorized recovery operations (`FAILURE-HANDLER.md`),
- collecting and assembling execution-result references (`RESULT-COLLECTOR.md`),
- recording the execution event log (`EXECUTION-LOGGER.md`),
- and assembling the final execution report from existing authoritative records (`EXECUTION-REPORTER.md`).

`20_EXECUTION` does not own:

- selecting the strategy (owned by `19_REASONING/STRATEGY-PLANNER.md`),
- building the execution plan or scheduling checkpoints within it (owned by `14_ENGINE/EXECUTION-PLANNER.md`),
- selecting a task's owner (owned by `14_ENGINE/TASK-ROUTER.md`),
- resolving dependency status (owned by `14_ENGINE/DEPENDENCY-ANALYZER.md`),
- validating outputs (owned by `14_ENGINE/VALIDATION.md`),
- evaluating rule compliance (owned by `19_REASONING/RULE-EVALUATOR.md`),
- deciding a recovery strategy (owned by `17_COORDINATION/FAILURE-RECOVERY.md`),
- or artifact storage (owned by `37_STORAGE/STORAGE-MANAGER.md`).

Those responsibilities remain in their respective layers; Execution consumes their decisions rather than remaking them.

---

## Components

| Component | Responsibility |
|---|---|
| `EXECUTION-ENGINE.md` | Entry point and top-level coordinator for an execution run. |
| `WORKFLOW-EXECUTOR.md` | Sequences and hands off approved workflow steps in order. |
| `ACTION-DISPATCHER.md` | Dispatches each action to its already-selected execution target. |
| `CHECKPOINT-MANAGER.md` | Gates workflow continuation on confirmed validation and rule-compliance evidence. |
| `ROLLBACK-MANAGER.md` | Executes an authorized rollback to a target checkpoint. |
| `EXECUTION-MONITOR.md` | Observes execution health and reports failures. |
| `FAILURE-HANDLER.md` | Normalizes execution failures and routes authorized recovery operations. |
| `RESULT-COLLECTOR.md` | Collects, correlates, and assembles execution-result references. |
| `EXECUTION-LOGGER.md` | Records the execution event log. |
| `EXECUTION-REPORTER.md` | Assembles the final execution report from existing authoritative records. |

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
Execution Engine (receive approved strategy + plan)
   ↓
Workflow Executor (sequence approved steps)
   ↓
Action Dispatcher (dispatch to the already-selected target)
   ↓
Checkpoint Manager (gate on confirmed validation / rule-compliance)
   ↓
Result Collector (collect and assemble result references)
   ↓
Execution Reporter (assemble the final report)
```

Execution Monitor observes health throughout this path. Execution Logger records events throughout, independent of the main sequence. Failure Handler and Rollback Manager operate off this path, triggered whenever a failure is reported:

```text
Any component detects a failure
   ↓
Execution Monitor / Workflow Executor / Action Dispatcher / Checkpoint Manager
   ↓
Failure Handler (normalize and forward)
   ↓
17_COORDINATION/FAILURE-RECOVERY.md (recovery decision)
   ↓
Failure Handler (route the authorized operation)
   ↓
Rollback Manager, or another owning Execution component
```

---

## Dependencies

Execution depends on:

- `19_REASONING/STRATEGY-PLANNER.md` for the approved strategy,
- `14_ENGINE/EXECUTION-PLANNER.md` for the ordered plan and scheduled checkpoints,
- `14_ENGINE/TASK-ROUTER.md` for the already-selected execution target of each action,
- `14_ENGINE/DEPENDENCY-ANALYZER.md` for dependency status,
- `14_ENGINE/VALIDATION.md` for the validation results Checkpoint Manager and Result Collector reference,
- `19_REASONING/RULE-EVALUATOR.md` for the rule-compliance results Checkpoint Manager references,
- `17_COORDINATION/FAILURE-RECOVERY.md` for the recovery decision Failure Handler forwards failures to and receives authorization from,
- and `37_STORAGE/STORAGE-MANAGER.md` for artifact storage.

---

## State Rule

Execution does not persist authoritative task or lifecycle state on its own authority — that remains `14_ENGINE/STATE-MANAGER.md`'s responsibility. Execution components reference and report against that state; they do not maintain a competing copy of it.

---

## Domain Rule

Execution mechanics apply identically regardless of domain; domain-specific content is carried in the dispatched actions and their results, not interpreted by Execution itself.

---

## Diagram

```text
Plan → Execution Engine → Workflow Executor → Action Dispatcher → Checkpoint Manager → Result Collector → Execution Reporter
                                    ↕                    ↕                  ↕
                            Execution Monitor ───────────┴──── Failure Handler ──→ 17_COORDINATION/FAILURE-RECOVERY.md
                                                                     ↓
                                                          Rollback Manager (if authorized)

                            Execution Logger records events throughout
```

---

## Rule

> Only authorized planned actions may run, and only an authorized recovery decision may trigger a retry, rollback, skip, escalation, or termination. Execution carries out already-decided strategy and plan; it does not decide the strategy, ownership, validation outcome, rule compliance, or recovery strategy itself. Every action emits an execution event.
