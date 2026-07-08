# SquirrelForge Learning Manager

Version: 1.0.0
Status: Stable
Owner: Learning Maintainers
Depends On: FEEDBACK-COLLECTOR.md, EVALUATION-ENGINE.md, PATTERN-DETECTOR.md, EXPERIENCE-STORE.md, ADAPTATION-MANAGER.md, LEARNING-GOVERNANCE.md, LEARNING-MONITOR.md
Used By: Learning-domain callers and downstream consumers
Last Updated: 2026-07-08

## Purpose

The Learning Manager coordinates Learning Layer intake, specialist handoffs, lifecycle progression, and learning-domain status aggregation.

## Responsibilities

- Receive learning requests and event references.
- Check request structure and prerequisite references.
- Route feedback normalization to the Feedback Collector.
- Route learning-value assessment to the Evaluation Engine.
- Route qualified evaluation results to the Pattern Detector.
- Coordinate experience-record references with the Experience Store.
- Coordinate adaptation proposals with Learning Governance and the Adaptation Manager.
- Aggregate learning-domain status, findings, decisions, and evidence references.
- Report Learning Layer outcomes to callers and observability consumers.

## Boundary

The Learning Manager coordinates the layer. It does not:

- independently collect or rewrite source feedback;
- perform Learning-domain evaluation or pattern detection;
- own persistence infrastructure;
- make Learning Governance decisions;
- execute adaptations, retries, recovery, rollback, or remediation;
- perform platform-wide validation, governance-policy evaluation, or general risk assessment;
- own general observability or audit infrastructure;
- own authoritative workflow or task state.

## Failure Handling

Coordinator failures produce Learning-domain failure status and evidence references for the owning failure, resilience, and observability paths. The Learning Manager does not create a parallel retry or recovery mechanism.

## Rule

The Learning Manager may coordinate and aggregate specialist work, but specialist decisions and records remain owned by their canonical components.