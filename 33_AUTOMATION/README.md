# SquirrelForge Automation Layer

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: 14_ENGINE/VALIDATION.md, 20_EXECUTION, 23_GOVERNANCE/POLICY-ENGINE.md, 24_SECURITY, 27_OBSERVABILITY, 28_RUNTIME-CONFIG, 35_RESILIENCE, 37_STORAGE
Used By: approved event-driven, scheduled, conditional, and workflow automation flows
Last Updated: 2026-07-08

## Purpose

The Automation Layer converts approved event, schedule, rule, and trigger conditions into controlled execution requests. It owns automation-domain definitions, trigger decisions, schedules, approval-checkpoint status, readiness findings, and automation coordination records.

Automation does not replace Execution, Governance policy evaluation, Security authorization, platform Validation, Resilience, Storage, or Observability authorities.

## Component Roster

| Component | Responsibility |
|---|---|
| `AUTOMATION-MANAGER.md` | Coordinates automation intake, specialist routing, checkpoint handoffs, and status aggregation. |
| `RULE-ENGINE.md` | Evaluates declarative automation conditions only. |
| `EVENT-LISTENER.md` | Normalizes incoming event references for automation use. |
| `SCHEDULER.md` | Owns schedule definitions, due-time evaluation, and scheduled trigger requests. |
| `TRIGGER-MANAGER.md` | Correlates trigger candidates and decides whether automation trigger conditions are satisfied. |
| `WORKFLOW-AUTOMATOR.md` | Converts approved automation requests into workflow execution handoffs and tracks automation-facing status references. |
| `TASK-ORCHESTRATOR.md` | Coordinates automation-domain task dependency and dispatch handoffs without owning execution state. |
| `APPROVAL-GATE.md` | Verifies required approval and decision references before automation progression. |
| `AUTOMATION-VALIDATOR.md` | Performs automation-domain readiness assessment. |
| `AUTOMATION-GOVERNANCE.md` | Owns Automation-domain standards, exceptions, restrictions, and governance decision records. |

## Layer Boundary

The Automation Layer consumes authoritative policy, security, validation, configuration, observability, execution, and resilience references. It does not define general governance policy, authenticate identities, make runtime authorization decisions, execute production actions independently, own workflow/task execution state, perform general retries or recovery, collect general telemetry, or own raw persistence and audit infrastructure.

## Rule

An automation may progress only when its automation conditions are satisfied and all required authoritative validation, governance, security, approval, execution, and recovery references permit progression.