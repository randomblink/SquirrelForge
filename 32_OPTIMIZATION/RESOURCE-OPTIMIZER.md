# SquirrelForge Resource Optimizer

Version: 1.0.0
Status: Stable
Owner: Optimization Maintainers
Depends On: 27_OBSERVABILITY, 37_STORAGE, OPTIMIZATION-MANAGER.md
Used By: OPTIMIZATION-VALIDATOR.md, OPTIMIZATION-GOVERNANCE.md, CAPACITY-PLANNER.md, COST-OPTIMIZER.md
Last Updated: 2026-07-08

## Purpose

The Resource Optimizer owns analysis and proposals concerning efficient use of compute, memory, storage capacity, network, database, cache, queue, and infrastructure resources.

## Responsibilities

- Consume authoritative utilization and diagnostic references.
- Identify resource inefficiencies, constraints, and bottlenecks.
- Compare utilization baselines with candidate efficiency targets.
- Produce resource findings, right-sizing proposals, and efficiency recommendations.
- Provide evidence references for capacity, cost, validation, and governance consumers.

## Boundary

The Resource Optimizer does not provision infrastructure, delete stored data, manage storage lifecycle, alter runtime configuration, perform scaling, change scheduling, execute recovery, collect general telemetry, perform platform validation, enforce governance, or own audit infrastructure.

## Rule

Resource techniques such as right-sizing, cleanup, caching, scaling, or scheduling are proposal categories; operational execution remains with authoritative owners.