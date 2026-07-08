# SquirrelForge Workflow Optimizer

Version: 1.0.0
Status: Stable
Owner: Optimization Maintainers
Depends On: 20_EXECUTION, 27_OBSERVABILITY, OPTIMIZATION-MANAGER.md
Used By: OPTIMIZATION-VALIDATOR.md, OPTIMIZATION-GOVERNANCE.md
Last Updated: 2026-07-08

## Purpose

The Workflow Optimizer owns analysis and proposals for workflow structure, sequencing, dependency efficiency, safe parallelization opportunities, and redundant-step reduction.

## Responsibilities

- Consume authoritative workflow definitions, execution evidence, trace references, metrics, and diagnostics.
- Analyze dependency graphs and execution patterns.
- Identify sequencing, parallelization, consolidation, and conditional-flow opportunities.
- Produce workflow optimization proposals with expected impact, risks, evidence, and validation strategy.
- Request appropriate testing and validation through owning components.

## Boundary

The Workflow Optimizer does not own workflow definitions as an authority, modify workflow logic directly, dispatch actions, change retry or error-handling policy directly, own authoritative workflow state, perform A/B testing as Testing authority, execute recovery, enforce governance, collect telemetry, or own audit infrastructure.

## Rule

Workflow optimization proposals are advisory until accepted and implemented through authoritative workflow, validation, governance, execution, and release processes.