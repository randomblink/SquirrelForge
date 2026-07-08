# SquirrelForge Diagnostics Engine

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/LOG-MANAGER.md`, `27_OBSERVABILITY/METRICS-MANAGER.md`, `27_OBSERVABILITY/TRACE-MANAGER.md`, `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`
Used By: `27_OBSERVABILITY/ALERT-MANAGER.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`, Execution, Integrations, Security, Governance
Last Updated: 2026-07-08

## Purpose

The Diagnostics Engine interprets observability records to produce diagnostic findings, correlation reports, probable-cause reports, anomaly findings, and troubleshooting evidence references.

It does not collect telemetry, execute remediation, assign incident ownership, decide security severity, validate business outcomes, change workflow state, or perform recovery.

---

## Responsibilities

- Consume log, metric, trace, alert, health, and telemetry references.
- Correlate observability evidence across components.
- Produce diagnostic findings and probable-cause references.
- Identify degraded dependencies, bottlenecks, anomalies, and failure-path evidence.
- Provide diagnostic references to alerting, health, dashboards, and owning domain components.

---

## Rules

1. Diagnostics Engine findings are evidence and recommendations only.
2. Remediation, recovery, incident classification, and workflow state changes belong to their owning components.
3. Diagnostics Engine must distinguish observed evidence from inferred probable cause.
