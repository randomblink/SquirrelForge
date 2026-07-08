# SquirrelForge Pattern Detector

Version: 1.0.0
Status: Stable
Owner: Learning Maintainers
Depends On: EVALUATION-ENGINE.md, EXPERIENCE-STORE.md
Used By: LEARNING-MANAGER.md, 32_OPTIMIZATION, LEARNING-GOVERNANCE.md
Last Updated: 2026-07-08

## Purpose

The Pattern Detector identifies recurring Learning-domain patterns, trends, correlations, and anomalies across qualified evaluation and experience references.

## Responsibilities

- Analyze qualified historical experience references.
- Group related observations for pattern analysis.
- Detect recurrence, trends, correlations, and anomalies.
- Measure pattern strength and Learning-domain confidence.
- Produce pattern findings, source references, evidence summaries, and optimization-candidate references.
- Provide qualified pattern findings to downstream Optimization and Learning consumers.

## Boundary

The Pattern Detector does not:

- modify source experiences;
- perform general anomaly monitoring for the platform;
- replace observability diagnostics or alerting;
- perform general risk assessment;
- create optimization policy or execute optimizations;
- recommend or implement behavioral changes as an authority;
- approve adaptations;
- own general historical storage or audit infrastructure;
- execute retries or recovery.

## Rule

Pattern findings are Learning-domain analytical evidence. They remain proposals or evidence inputs until the appropriate Optimization, Learning Governance, Validation, Execution, and other authoritative owners act on them.