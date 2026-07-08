# SquirrelForge Trace Manager

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`, `27_OBSERVABILITY/TELEMETRY-COLLECTOR.md`, `37_STORAGE`
Used By: `27_OBSERVABILITY/DIAGNOSTICS-ENGINE.md`, `27_OBSERVABILITY/DASHBOARD-MANAGER.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`
Last Updated: 2026-07-08

## Purpose

The Trace Manager owns trace records, span records, correlation identifiers, parent-child relationships, timing references, and execution-path evidence references.

It links related operations for visibility. It does not own workflow state, execute work, validate completion, perform recovery, or decide fault ownership.

---

## Responsibilities

- Create or update trace and span records from normalized telemetry.
- Preserve correlation, parent, child, component, operation, timing, and status references.
- Produce execution-path references for diagnostics, dashboards, and health reporting.
- Preserve trace history through owning storage infrastructure.

---

## Rules

1. Trace Manager must represent observed execution paths only.
2. Trace Manager must not change workflow state or infer task completion.
3. Trace Manager may expose latency and dependency evidence, but diagnostics owns diagnostic interpretation.
