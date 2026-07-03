# SquirrelForge Execution Monitor

## Purpose

The Execution Monitor observes active workflow execution, tracks action progress, detects failures or stalls, and reports execution health to the Workflow Executor.

---

## Responsibilities

- Monitor running actions.
- Track action status changes.
- Detect stalled or failed actions.
- Record execution timing.
- Report workflow health.
- Trigger retry or escalation paths.
- Maintain execution history.
- Confirm completion signals.

---

## Monitoring Process

1. Receive active execution state.
2. Track each running action.
3. Compare progress against expected status.
4. Detect timeout, stall, or failure.
5. Record status updates.
6. Report health to the Workflow Executor.
7. Trigger retry, checkpoint, or escalation handling when required.

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
| Retrying | Action is being attempted again |
| Escalated | Action requires higher-level handling |

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

## Rule

Every running action must be monitored until it reaches Complete, Failed, or Escalated status.
