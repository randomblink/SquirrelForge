# SquirrelForge Agent Monitor

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/AGENT-MANAGER.md`, `27_OBSERVABILITY`, `14_ENGINE/STATE-MANAGER.md`
Used By: Agent Manager, Governance, Reporting
Last Updated: 2026-07-05

## Purpose

The Agent Monitor interprets agent-specific health, workload, communication, capability-usage, and governance-compliance signals surfaced by `27_OBSERVABILITY`, and keeps each agent's operational health status current in the Agent Manager.

The Monitor interprets and reports. It does not collect telemetry, compute metrics, or fire alerts itself — that instrumentation is owned by `27_OBSERVABILITY`'s Telemetry Collector, Metrics Manager, and Alert Manager. It also does not suspend, reassign, or reconfigure an agent itself; that authority belongs to the Agent Manager.

---

## Responsibilities

The Agent Monitor must:

- read agent-relevant telemetry and metrics already collected by `27_OBSERVABILITY`,
- evaluate that data against thresholds and baselines defined by `27_OBSERVABILITY`, not its own ad hoc limits,
- classify each monitored agent's current health status,
- detect anomalous behavior: unexpected loops, repetitive actions, performance deviation, or unusual communication or capability-usage patterns,
- update the agent's health status in the Agent Manager,
- request an alert from `27_OBSERVABILITY`'s Alert Manager when a breach is detected, rather than notifying directly,
- and record monitoring events for historical trend analysis and audit.

---

## Inputs

The Monitor should receive:

- telemetry and metrics for the monitored agent from `27_OBSERVABILITY`,
- the configured thresholds and baselines those metrics are evaluated against,
- the agent's current registered status from the Agent Manager,
- and prior monitoring history for trend comparison.

Monitoring must not proceed against undefined or missing thresholds by inventing its own.

---

## Outputs

The Monitor should produce:

- a health status classification for the monitored agent,
- flagged anomalies with supporting evidence,
- an alert request to `27_OBSERVABILITY`'s Alert Manager when a threshold is breached,
- an updated health record in the Agent Manager,
- and historical monitoring events for trend analysis.

---

## Monitoring Process

1. Read agent-relevant telemetry and metrics already collected by `27_OBSERVABILITY`.
2. Evaluate that data against `27_OBSERVABILITY`'s configured thresholds and baselines.
3. Classify the agent's current health status.
4. Compare against monitoring history to detect anomalous patterns.
5. Update the agent's health status in the Agent Manager.
6. If a threshold is breached, request an alert from the Alert Manager rather than acting directly.
7. Record the monitoring event for historical trend analysis and audit.

---

## Monitored Categories

| Category | Description | Source |
|---|---|---|
| Health | Operational status and error rates. | `27_OBSERVABILITY/HEALTH-REPORTER.md` |
| Performance | Task latency and throughput. | `27_OBSERVABILITY/METRICS-MANAGER.md` |
| Workload | Active tasks and resource utilization. | `27_OBSERVABILITY/METRICS-MANAGER.md` |
| Communication | Message volume and delivery success. | `27_OBSERVABILITY/TELEMETRY-COLLECTOR.md` |
| Governance | Policy compliance and violations. | `16_AGENTS/AGENT-GOVERNANCE.md` |

---

## Monitor Record

| Field | Description |
|---|---|
| Monitor Event ID | Unique identifier for the monitoring event. |
| Agent ID | The agent being monitored. |
| Metric | The specific metric being evaluated. |
| Value | The recorded value of the metric. |
| Status | Health classification (see Health Status). |
| Timestamp | The time the metric was recorded. |

---

## Health Status

| Status | Meaning |
|---|---|
| `NORMAL` | The agent is operating within configured thresholds. |
| `DEGRADED` | A non-critical threshold is breached or an anomaly is developing; the agent remains eligible for work. |
| `CRITICAL` | A critical threshold is breached; the agent's eligibility for new work must be reassessed by the Agent Manager. |
| `UNKNOWN` | Required telemetry is missing or stale; health cannot be classified. |

---

## Behavioral Analysis

The Agent Monitor analyzes patterns to detect:

- unexpected loops or repetitive actions,
- significant deviations from established performance baselines,
- anomalous communication patterns between agents,
- and unusual sequences of capability usage.

An anomaly is reported as evidence to the Agent Manager and, when it breaches a configured threshold, escalated through the Alert Manager — the Monitor does not independently decide what corrective action follows.

---

## Permission Boundary

The Monitor may read observability data, classify health, detect anomalies, and update the health record in the Agent Manager.

It must not collect telemetry, define thresholds, fire alerts, or take corrective action (suspending, reassigning, or reconfiguring an agent) itself — those remain owned by `27_OBSERVABILITY` and the Agent Manager respectively.

---

## Handoff Rule

The Monitor's update to the Agent Manager must include:

- the agent's classified health status,
- the metrics and thresholds the classification was based on,
- any detected anomalies and their evidence,
- whether an alert was requested,
- and the monitoring event timestamp.

An update is incomplete if the Agent Manager cannot determine why the health status changed.

---

## Rule

> Every active agent must be continuously monitored against `27_OBSERVABILITY`'s thresholds, and its health status must be kept current in the Agent Manager to remain eligible for work assignment. The Monitor classifies and reports — it does not collect telemetry, define thresholds, or act on findings itself.
