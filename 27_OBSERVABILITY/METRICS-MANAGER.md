# SquirrelForge Metrics Manager

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`, `27_OBSERVABILITY/TELEMETRY-COLLECTOR.md`, `37_STORAGE`
Used By: `27_OBSERVABILITY/ALERT-MANAGER.md`, `27_OBSERVABILITY/DASHBOARD-MANAGER.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`, `27_OBSERVABILITY/DIAGNOSTICS-ENGINE.md`
Last Updated: 2026-07-08

## Purpose

The Metrics Manager owns metric records, metric definitions, aggregates, time-series references, and metric availability references derived from normalized telemetry.

It does not collect telemetry directly, own storage infrastructure, define business success criteria, execute optimization, create alerts independently, or validate workflow outcomes.

---

## Responsibilities

- Produce metric records from normalized telemetry.
- Maintain metric names, units, dimensions, source references, and timestamps.
- Calculate approved aggregates and derived metric references.
- Preserve time-series references through owning storage infrastructure.
- Provide metric references to alerts, dashboards, health reports, and diagnostics.

---

## Rules

1. Metrics Manager must use approved metric definitions and governance references.
2. Metrics Manager may expose threshold evidence, but alert decisions belong to `ALERT-MANAGER.md`.
3. Metrics Manager must not mark business outcomes successful or failed.
