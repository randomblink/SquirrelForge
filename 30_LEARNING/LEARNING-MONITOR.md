# SquirrelForge Learning Monitor

Version: 1.0.0
Status: Stable
Owner: Learning Maintainers
Depends On: Learning Layer status references, 27_OBSERVABILITY
Used By: LEARNING-MANAGER.md, Learning maintainers, observability consumers
Last Updated: 2026-07-08

## Purpose

The Learning Monitor consumes authoritative observability and Learning-domain status references, correlates Learning-domain health and effectiveness indicators, and produces Learning-domain findings and reports.

## Responsibilities

- Consume telemetry, metric, log, alert, health, and event references from Observability owners.
- Correlate Learning Layer processing, evaluation, pattern, governance, adaptation, and experience-status signals.
- Interpret Learning-domain thresholds and effectiveness indicators.
- Detect Learning-domain stalled progress, repeated failures, unusual trends, and unauthorized-adaptation signals from authoritative inputs.
- Produce Learning-domain health findings, effectiveness summaries, and status reports.
- Route findings to the Learning Manager, owning Learning component, Security, Resilience, or Observability owner as appropriate.

## Boundary

The Learning Monitor does not:

- collect general telemetry;
- own logs, metrics, traces, dashboards, alerts, diagnostics, health reporting, or audit-trail infrastructure;
- independently verify governance compliance as an authority;
- make adaptation approval decisions;
- own authoritative workflow or task state;
- execute retries, recovery, rollback, remediation, or adaptation;
- create a parallel monitoring history or storage infrastructure.

## Rule

The Learning Monitor specializes authoritative signals into Learning-domain findings. General observability collection and infrastructure remain with `27_OBSERVABILITY`, while operational action remains with the relevant owning component.