# SquirrelForge Performance Optimizer

Version: 1.0.0
Status: Stable
Owner: Optimization Maintainers
Depends On: 27_OBSERVABILITY, OPTIMIZATION-MANAGER.md
Used By: OPTIMIZATION-VALIDATOR.md, OPTIMIZATION-GOVERNANCE.md
Last Updated: 2026-07-08

## Purpose

The Performance Optimizer owns performance-domain analysis and proposals for measurable latency, throughput, responsiveness, and execution-efficiency improvement.

## Responsibilities

- Consume authoritative metric, trace, diagnostic, health, and timing references.
- Identify performance bottlenecks and affected-component references.
- Compare baselines and candidate improvement hypotheses.
- Produce performance findings, measurable targets, test requests, and improvement proposals.
- Provide evidence references for validation and governance review.

## Boundary

The Performance Optimizer does not collect general telemetry, own Observability infrastructure, change production behavior, tune retry policy directly, perform tests as Testing authority, perform platform validation, enforce governance, execute rollback or recovery, own authoritative execution state, or own audit infrastructure.

## Rule

Performance recommendations remain proposals until required validation, governance, execution, release, deployment, and recovery authorities act.