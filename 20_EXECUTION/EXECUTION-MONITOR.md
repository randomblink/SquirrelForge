# SquirrelForge Execution Monitor

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `20_EXECUTION/EXECUTION-ENGINE.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `20_EXECUTION/EXECUTION-LOGGER.md`
Used By: `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `20_EXECUTION/EXECUTION-ENGINE.md`, `20_EXECUTION/FAILURE-HANDLER.md`
Last Updated: 2026-07-06

## Purpose

The Execution Monitor observes active workflow execution, tracks per-action progress in detail, detects failures or stalls, and reports execution health to `20_EXECUTION/WORKFLOW-EXECUTOR.md` and `20_EXECUTION/EXECUTION-ENGINE.md`.

The Execution Monitor observes and reports; it does not decide recovery. When it detects a timeout, stall, or failure, it reports the finding to `20_EXECUTION/FAILURE-HANDLER.md` rather than triggering retry or escalation itself — retry, rollback, skip, escalation, and termination remain `17_COORDINATION/FAILURE-RECOVERY.md`'s authority. Detecting that an action produced a completion signal is the Monitor's job; collecting, correlating, and registering the actual result is `20_EXECUTION/RESULT-COLLECTOR.md`'s job.

---

## Responsibilities

The Execution Monitor must:

- monitor running actions,
- track action status changes,
- detect stalled or failed actions,
- record execution timing,
- report workflow health to `20_EXECUTION/WORKFLOW-EXECUTOR.md` and overall progress to `20_EXECUTION/EXECUTION-ENGINE.md`,
- report a detected timeout, stall, or failure to `20_EXECUTION/FAILURE-HANDLER.md`, rather than triggering retry or escalation itself,
- maintain execution history,
- and confirm that an action has signaled completion, without collecting or registering its result (owned by `20_EXECUTION/RESULT-COLLECTOR.md`).

---

## Monitoring Process

1. Receive active execution state.
2. Track each running action.
3. Compare progress against expected status.
4. Detect timeout, stall, or failure.
5. Record status updates.
6. Report health to `20_EXECUTION/WORKFLOW-EXECUTOR.md` and `20_EXECUTION/EXECUTION-ENGINE.md`.
7. Report a detected timeout, stall, or failure to `20_EXECUTION/FAILURE-HANDLER.md`.

---

## Execution Status

| Status | Meaning |
|---|---|
| Queued | Action is waiting to run |
| Running | Action is currently executing |
| Waiting | Action is paused for dependency or input |
| Complete | Action finished successfully |
| Failed | Action failed during execution |
| Stalled | Action has stopped progressing |
| Retrying | Action is being attempted again under an authorization received back through `20_EXECUTION/FAILURE-HANDLER.md` |
| Escalated | Action's failure has been reported to `20_EXECUTION/FAILURE-HANDLER.md` for a recovery decision |

---

## Health Signals

| Signal | Meaning |
|---|---|
| Progress | Action is advancing normally |
| Delay | Action is slower than expected |
| Timeout | Action exceeded allowed time |
| Error | Action returned a failure |
| Dependency Block | Action is waiting on another requirement |
| Completion | Action returned a valid result |

---

## Permission Boundary

The Execution Monitor may observe, track, detect, record, and report execution health and failures.

It must not trigger retry, rollback, skip, escalation, or termination itself (owned by `17_COORDINATION/FAILURE-RECOVERY.md`, routed through `20_EXECUTION/FAILURE-HANDLER.md`), and it must not collect or register an action's result (owned by `20_EXECUTION/RESULT-COLLECTOR.md`).

---

## Domain Rule

Monitoring mechanics apply identically regardless of domain; domain-specific content is carried in the observed action, not interpreted by the Execution Monitor itself.

---

## Rule

> Every running action must be monitored until it reaches Complete, Failed, or a state reported to the Failure Handler. The Execution Monitor observes and reports; it does not decide how a failure is resolved.
