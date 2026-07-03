# SquirrelForge Failure Handler

## Purpose

The Failure Handler evaluates execution failures, determines whether recovery is possible, and routes failures to retry, rollback, escalation, or termination paths.

---

## Responsibilities

- Receive failed execution results.
- Classify failure type.
- Determine failure severity.
- Select recovery action.
- Trigger retries when allowed.
- Trigger rollback when required.
- Escalate unresolved failures.
- Record all failure handling decisions.

---

## Failure Types

| Failure | Meaning |
|---|---|
| Prerequisite Failure | Required condition was not met |
| Dispatch Failure | Action could not be routed |
| Execution Failure | Action ran but did not complete successfully |
| Validation Failure | Output failed validation |
| Timeout Failure | Action exceeded allowed time |
| Dependency Failure | Required upstream result failed |
| Rule Failure | Action violated a required rule |

---

## Recovery Paths

| Path | Use When |
|---|---|
| Retry | Failure may be temporary |
| Rollback | Work created unsafe or invalid state |
| Escalate | Human or higher-level decision is needed |
| Skip | Only allowed by explicit authorization |
| Terminate | Workflow cannot safely continue |

---

## Failure Record

| Field | Description |
|---|---|
| Failure ID | Unique identifier |
| Workflow | Parent workflow |
| Action ID | Failed action |
| Failure Type | Classification |
| Severity | Low / Medium / High / Critical |
| Recovery Path | Retry / Rollback / Escalate / Skip / Terminate |
| Timestamp | Failure handling time |
| Notes | Explanation of decision |

---

## Rule

A failed action may not be ignored. Every failure must be classified, recorded, and assigned a recovery path.
