# SquirrelForge Dashboard Manager

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/LOG-MANAGER.md`, `27_OBSERVABILITY/METRICS-MANAGER.md`, `27_OBSERVABILITY/TRACE-MANAGER.md`, `27_OBSERVABILITY/ALERT-MANAGER.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`, `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`
Used By: Operators, Agents, Governance, Security, Execution, Integrations
Last Updated: 2026-07-08

## Purpose

The Dashboard Manager owns dashboard definitions, dashboard views, visualization references, display freshness, and dashboard evidence references derived from observability records.

It presents operational visibility. It does not collect telemetry, decide health, create alerts, diagnose issues, execute actions, approve governance decisions, or own storage infrastructure.

---

## Responsibilities

- Build dashboard views from log, metric, trace, alert, diagnostic, and health references.
- Maintain dashboard definitions, widget references, filters, time windows, and freshness references.
- Present platform, workflow, integration, security, governance, performance, and release observability views.
- Provide dashboard evidence references to operators and owning components.

---

## Rules

1. Dashboard Manager must display source and freshness references for displayed evidence.
2. Dashboard Manager must not become the source of truth for health, alert, diagnostic, or workflow state.
3. Dashboard Manager must not expose raw secrets or prohibited sensitive data.
