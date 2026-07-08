# SquirrelForge Automation Connector

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/AUTHENTICATION.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, Execution and workflow components requiring external automation handoff
Last Updated: 2026-07-08

## Purpose

The Automation Connector adapts external automation platforms, schedulers, workflow engines, job queues, and CI/CD systems into standardized Integration-layer handoff interfaces.

It owns external automation request translation, trigger handoff, external execution-status normalization, automation-provider metadata references, and automation transport status references.

It does not own SquirrelForge workflow execution, task scheduling, business routing, validation, recovery execution, platform CI policy, credential storage, logging, audit, observability infrastructure, or authoritative workflow state.

---

## Responsibilities

- Adapt approved automation-platform handoff requests into provider-specific trigger requests.
- Normalize external automation trigger, schedule, job, workflow, and pipeline references.
- Consume connector, credential, governance, and configuration references from owning components.
- Submit approved automation handoffs to external automation platforms.
- Normalize external automation status, error, cancellation, timeout, and result references.
- Return automation transport status and evidence references to the caller.
- Emit automation event references through observability owners.

---

## Boundary

`AUTOMATION-CONNECTOR.md` owns:

- external automation provider protocol adaptation,
- automation trigger request translation,
- external automation status normalization,
- automation provider metadata references,
- automation transport error references,
- and automation handoff evidence references.

`AUTOMATION-CONNECTOR.md` does not own:

- SquirrelForge workflow execution, scheduling, or orchestration,
- task routing or integration routing (`INTEGRATION-MANAGER.md`),
- connector registry ownership (`CONNECTOR-MANAGER.md`),
- CI/CD policy, release approval, or deployment governance,
- authentication, authorization, or credential storage,
- business validation or quality gates,
- retry, recovery, rollback, or failure handling,
- storage, logging, audit, or observability infrastructure,
- or authoritative workflow/task lifecycle state.

---

## Automation Statuses

| Status | Meaning |
|---|---|
| `Submitted` | External automation trigger was submitted. |
| `Accepted` | External platform accepted the trigger. |
| `Running` | External platform reports active execution. |
| `Completed` | External platform reports completion. |
| `Failed` | External platform reports failure. |
| `Cancelled` | External platform reports cancellation. |
| `Timed Out` | External automation did not finish within the expected transport window. |

These are external automation status references only. They do not validate business outcome or mark SquirrelForge workflow completion.

---

## Rules

1. Automation Connector may process only approved automation handoffs.
2. Automation Connector must use credential, connector, governance, and configuration references from owning components.
3. Automation Connector must return normalized status and evidence references to the caller.
4. Automation Connector may report retryable or failed status, but retry/recovery decisions belong to execution and coordination owners.
5. Automation Connector must emit event references through observability owners.
