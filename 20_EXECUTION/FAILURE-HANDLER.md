# SquirrelForge Failure Handler

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/EXECUTION-MONITOR.md`, `20_EXECUTION/EXECUTION-LOGGER.md`, `17_COORDINATION/FAILURE-RECOVERY.md`, `14_ENGINE/VALIDATION.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, `19_REASONING/RULE-EVALUATOR.md`
Used By: Execution Engine, Workflow Executor, Action Dispatcher, Checkpoint Manager
Last Updated: 2026-07-06

## Purpose

The Failure Handler receives execution failure reports from Execution-layer components, correlates each failure with its execution, workflow-step, action, dispatch, and checkpoint references, and normalizes them into an Execution Failure Record. It forwards normalized records to `17_COORDINATION/FAILURE-RECOVERY.md` for a recovery decision, and routes only the recovery operations that come back authorized to the Execution component that owns them.

The Failure Handler normalizes and routes; it does not decide recovery. Classifying a failure's underlying cause when that cause originates outside Execution — a validation failure (`14_ENGINE/VALIDATION.md`), a dependency failure (`14_ENGINE/DEPENDENCY-ANALYZER.md`), or a rule failure (`19_REASONING/RULE-EVALUATOR.md`) — is preserved as a reference to that authoritative source's classification, not independently judged here. Selecting a recovery strategy (retry, rollback, skip, escalate, terminate), determining whether recovery is possible, and owning escalation criteria are `17_COORDINATION/FAILURE-RECOVERY.md`'s authority alone. The Failure Handler does not perform rollback itself (owned by `20_EXECUTION/ROLLBACK-MANAGER.md`) and does not maintain its own Recovery Record — that record belongs to `17_COORDINATION/FAILURE-RECOVERY.md`.

---

## Responsibilities

The Failure Handler must:

- receive execution failure reports from Execution components (Execution Engine, Workflow Executor, Action Dispatcher, Checkpoint Manager, Execution Monitor),
- correlate each failure with its execution, workflow-step, action, dispatch, and checkpoint references,
- normalize execution-specific failure evidence into an Execution Failure Record,
- preserve the reporting source and observed failure condition without inventing recovery policy,
- attach relevant `20_EXECUTION/EXECUTION-MONITOR.md` findings and `20_EXECUTION/EXECUTION-LOGGER.md` references,
- forward the normalized Execution Failure Record to `17_COORDINATION/FAILURE-RECOVERY.md`,
- receive authorized recovery requests back through the defined coordination path,
- route each authorized execution operation to the Execution component that owns it (for example, a rollback operation to `20_EXECUTION/ROLLBACK-MANAGER.md`, a retry to `20_EXECUTION/ACTION-DISPATCHER.md`),
- and preserve failure-handling traceability across the intake, forwarding, and routing steps.

It must not:

- independently select retry, rollback, skip, escalation, or termination,
- define recovery policy,
- determine whether recovery is possible,
- trigger a retry without authorization from `17_COORDINATION/FAILURE-RECOVERY.md`,
- choose rollback scope or checkpoint,
- perform rollback itself,
- own escalation criteria,
- or duplicate the Recovery Record owned by `17_COORDINATION/FAILURE-RECOVERY.md`.

---

## Failure Types

| Failure | Meaning | Source Classification |
|---|---|---|
| Prerequisite Failure | Required condition was not met | Execution (observed directly) |
| Dispatch Failure | Action could not be routed | Execution (observed directly) |
| Execution Failure | Action ran but did not complete successfully | Execution (observed directly) |
| Validation Failure | Output failed validation | `14_ENGINE/VALIDATION.md` |
| Timeout Failure | Action exceeded allowed time | Execution (observed directly) |
| Dependency Failure | Required upstream result failed | `14_ENGINE/DEPENDENCY-ANALYZER.md` |
| Rule Failure | Action violated a required rule | `19_REASONING/RULE-EVALUATOR.md` |

For Validation, Dependency, and Rule Failure, the Failure Handler preserves the classification its authoritative source already assigned. It does not independently judge validation, dependency, or rule compliance itself.

---

## Examples of Authorized Recovery Operations Routed

| Operation | Routed To |
|---|---|
| Retry | `20_EXECUTION/ACTION-DISPATCHER.md` |
| Rollback | `20_EXECUTION/ROLLBACK-MANAGER.md` |
| Skip | The Execution component that owns the skipped step (for example `20_EXECUTION/WORKFLOW-EXECUTOR.md`) |
| Escalate | Routed back through `17_COORDINATION/FAILURE-RECOVERY.md`'s own escalation path |
| Terminate | `20_EXECUTION/EXECUTION-ENGINE.md` |

These are illustrations of where an already-authorized recovery operation is routed once `17_COORDINATION/FAILURE-RECOVERY.md` decides it. The Failure Handler does not select among them.

---

## Failure Handling Process

1. Receive an execution failure report from the originating Execution component.
2. Correlate the failure with its execution, workflow-step, action, dispatch, and checkpoint references.
3. Preserve the reporting source and observed failure condition, and the source classification when the failure originates outside Execution (Validation, Dependency, Rule).
4. Attach relevant Execution Monitor findings and Execution Logger references.
5. Normalize the evidence into an Execution Failure Record.
6. Forward the Execution Failure Record to `17_COORDINATION/FAILURE-RECOVERY.md`.
7. Receive an authorized recovery request back through the defined coordination path.
8. Route the authorized operation to the Execution component that owns it.
9. Record the routing for traceability.

---

## Execution Failure Record

| Field | Description |
|---|---|
| Failure ID | Unique identifier |
| Execution Reference | Related execution |
| Workflow Step Reference | Related workflow step |
| Action Reference | Related dispatched action |
| Checkpoint Reference | Related checkpoint, if applicable |
| Reporting Component | Execution component that reported the failure |
| Observed Condition | What was observed (for example timeout, dispatch error) |
| Evidence References | Supporting evidence (logs, monitor findings) |
| Source Classification Reference | Reference to the classification from Validation, Dependency Analyzer, or Rule Evaluator, when the failure originates outside Execution |
| Monitor Finding Reference | Reference to `20_EXECUTION/EXECUTION-MONITOR.md`'s relevant finding |
| Logger Reference | Reference to `20_EXECUTION/EXECUTION-LOGGER.md`'s relevant entry |
| Timestamp | Failure report time |
| Recovery Record Reference | Reference to `17_COORDINATION/FAILURE-RECOVERY.md`'s Recovery Record, once one exists |

---

## Permission Boundary

The Failure Handler may receive failure reports, correlate references, normalize evidence into an Execution Failure Record, forward it, and route authorized recovery operations to their owning Execution component.

It must not select a recovery strategy, define recovery policy, determine whether recovery is possible, trigger retry or rollback without authorization, choose rollback scope, perform rollback itself, own escalation criteria, or maintain a duplicate Recovery Record — all owned by `17_COORDINATION/FAILURE-RECOVERY.md` (and, for rollback execution, `20_EXECUTION/ROLLBACK-MANAGER.md`).

---

## Domain Rule

Failure intake and normalization apply identically regardless of domain; domain-specific content is carried in the correlated references, not interpreted by the Failure Handler itself.

---

## Rule

> No execution failure may be silently dropped. Every failure must be correlated, normalized into an Execution Failure Record, and forwarded to `17_COORDINATION/FAILURE-RECOVERY.md` for a recovery decision. The Failure Handler normalizes and routes; it does not decide recovery.
