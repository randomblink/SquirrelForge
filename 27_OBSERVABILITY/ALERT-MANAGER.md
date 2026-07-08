# SquirrelForge Alert Manager

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/LOG-MANAGER.md`, `27_OBSERVABILITY/METRICS-MANAGER.md`, `27_OBSERVABILITY/TRACE-MANAGER.md`, `27_OBSERVABILITY/DIAGNOSTICS-ENGINE.md`, `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`
Used By: `27_OBSERVABILITY/HEALTH-REPORTER.md`, `27_OBSERVABILITY/DASHBOARD-MANAGER.md`, Execution, Integrations, Security, Governance
Last Updated: 2026-07-08

## Purpose

The Alert Manager owns alert records, alert rule evaluation, alert status, duplicate suppression, notification requests, escalation references, and alert evidence references.

It detects operational alert conditions from observability evidence. It does not own incident response, security threat classification, recovery execution, workflow state, notification delivery infrastructure, or business validation.

---

## Responsibilities

- Evaluate observability evidence against alert rules and thresholds.
- Create alert records with source, category, severity, status, evidence, and timestamp references.
- Correlate related alerts and suppress duplicates according to approved rules.
- Produce notification and escalation requests for owning communication or response components.
- Provide alert references to dashboards, health reporting, diagnostics, and owning domain components.

---

## Alert States

| State | Meaning |
|---|---|
| `Open` | Alert condition is active. |
| `Acknowledged` | Responsible owner has acknowledged the alert. |
| `Suppressed` | Alert is hidden by approved suppression rules. |
| `Escalation Requested` | Escalation request was sent to the owning response path. |
| `Resolved` | Owning component supplied resolution evidence. |

These are alert lifecycle states only. They are not incident, workflow, validation, recovery, or security-classification states.

---

## Rules

1. Alert Manager may request escalation or notification but must not execute response actions.
2. Alert severity is operational alert severity only unless a security or incident owner consumes it.
3. Alert Manager must not close alerts without supplied resolution evidence from the responsible owner.
