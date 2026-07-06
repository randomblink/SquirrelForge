# SquirrelForge Execution Reporter

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/RESULT-COLLECTOR.md`, `20_EXECUTION/EXECUTION-LOGGER.md`, `20_EXECUTION/ROLLBACK-MANAGER.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `14_ENGINE/VALIDATION.md`, `14_ENGINE/STATE-MANAGER.md`
Used By: `23_GOVERNANCE`, Reporting
Last Updated: 2026-07-06

## Purpose

The Execution Reporter assembles a concise execution report from existing authoritative records and references. It does not decide, validate, assess, or recommend on its own authority.

Deciding whether execution passed validation is owned by `14_ENGINE/VALIDATION.md`. Authoritative task or workflow status is owned by `14_ENGINE/STATE-MANAGER.md`. Rollback decisions are owned by `20_EXECUTION/ROLLBACK-MANAGER.md`. Risk assessment is owned by `19_REASONING/RISK-ASSESSOR.md`. Recommended next actions are owned by whichever planning, reasoning, recovery, or governance component supplies them. Execution results themselves remain owned by `20_EXECUTION/RESULT-COLLECTOR.md`, and the execution event log remains owned by `20_EXECUTION/EXECUTION-LOGGER.md`. The Reporter assembles and summarizes these existing records; it does not replace, mutate, or duplicate their authority.

---

## Responsibilities

The Execution Reporter must:

- receive the Execution Result Set from `20_EXECUTION/RESULT-COLLECTOR.md`,
- receive execution-event references from `20_EXECUTION/EXECUTION-LOGGER.md`,
- include external validation and test-result references when supplied by `14_ENGINE/VALIDATION.md`,
- include rollback-result references from `20_EXECUTION/ROLLBACK-MANAGER.md`, when applicable,
- include failure and recovery references from `20_EXECUTION/FAILURE-HANDLER.md` and `17_COORDINATION/FAILURE-RECOVERY.md`, when applicable,
- summarize completed execution activity, blocked or unresolved execution conditions, changed-artifact references, and outstanding execution concerns,
- preserve provenance for every reported conclusion,
- and provide the assembled report to downstream consumers without changing the underlying records.

It must not:

- decide whether execution passed validation,
- determine authoritative task or workflow status,
- create rollback decisions,
- assess risk independently,
- invent recommended next actions as a reasoning authority,
- mutate execution results,
- or replace audit or observability records.

---

## Reporting Process

1. Receive the Execution Result Set from `20_EXECUTION/RESULT-COLLECTOR.md`.
2. Receive relevant execution-event references from `20_EXECUTION/EXECUTION-LOGGER.md`.
3. Include external validation and test-result references when `14_ENGINE/VALIDATION.md` has supplied them.
4. Include rollback-result references from `20_EXECUTION/ROLLBACK-MANAGER.md`, when applicable.
5. Include failure and recovery references from `20_EXECUTION/FAILURE-HANDLER.md` and `17_COORDINATION/FAILURE-RECOVERY.md`, when applicable.
6. Summarize completed activity, blocked or unresolved conditions, changed artifacts, and outstanding concerns from these references.
7. Preserve provenance for every reported conclusion.
8. Provide the assembled Execution Report to downstream consumers.

---

## Execution Report Model

| Field | Description | Source |
|---|---|---|
| Report ID | Unique identifier. | Execution Reporter |
| Execution Reference | Related execution. | Execution Reporter |
| Status Reference | Reference to authoritative task/workflow status. | `14_ENGINE/STATE-MANAGER.md` |
| Completed Activity | Summary of completed execution steps. | `20_EXECUTION/RESULT-COLLECTOR.md`, `20_EXECUTION/EXECUTION-LOGGER.md` |
| Blocked / Unresolved Conditions | Summary of blocked or unresolved execution conditions. | `20_EXECUTION/EXECUTION-LOGGER.md`, `20_EXECUTION/FAILURE-HANDLER.md` |
| Changed Artifact References | References to artifacts changed during execution. | `20_EXECUTION/RESULT-COLLECTOR.md` |
| Validation / Test Evidence References | References to validation and test results. | `14_ENGINE/VALIDATION.md` |
| Rollback References | References to rollback results, when applicable. | `20_EXECUTION/ROLLBACK-MANAGER.md` |
| Failure / Recovery References | References to failure and recovery records, when applicable. | `20_EXECUTION/FAILURE-HANDLER.md`, `17_COORDINATION/FAILURE-RECOVERY.md` |
| Unresolved Risk References | References to risk the owning risk authority has recorded. | `19_REASONING/RISK-ASSESSOR.md` |
| Recommended Next Actions | Reported only when supplied by an authoritative planning, reasoning, recovery, or governance component. | `19_REASONING/STRATEGY-PLANNER.md`, `17_COORDINATION/FAILURE-RECOVERY.md`, `23_GOVERNANCE` |
| Timestamp | Report assembly time. | Execution Reporter |

---

## Permission Boundary

The Execution Reporter may assemble, summarize, and provide an Execution Report from existing authoritative records and references, preserving their provenance.

It must not decide whether execution passed validation (owned by `14_ENGINE/VALIDATION.md`), determine authoritative task or workflow status (owned by `14_ENGINE/STATE-MANAGER.md`), create rollback decisions (owned by `20_EXECUTION/ROLLBACK-MANAGER.md`), assess risk independently (owned by `19_REASONING/RISK-ASSESSOR.md`), invent recommended next actions on its own authority, mutate execution results (owned by `20_EXECUTION/RESULT-COLLECTOR.md`), or replace audit or observability records (owned by `20_EXECUTION/EXECUTION-LOGGER.md` and `27_OBSERVABILITY`).

---

## Domain Rule

Report assembly applies identically regardless of domain; domain-specific content is carried in the referenced records, not interpreted by the Execution Reporter itself.

---

## Rule

> Every reported conclusion must trace to an existing authoritative record or reference. The Execution Reporter assembles and summarizes; it does not decide validation outcomes, workflow status, rollback, risk, or recommended next actions on its own authority.
