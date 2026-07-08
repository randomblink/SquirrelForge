# SquirrelForge Capacity Planner

Version: 1.0.0
Status: Stable
Owner: Optimization Maintainers
Depends On: 27_OBSERVABILITY, RESOURCE-OPTIMIZER.md, COST-OPTIMIZER.md
Used By: OPTIMIZATION-MANAGER.md, OPTIMIZATION-VALIDATOR.md, OPTIMIZATION-GOVERNANCE.md
Last Updated: 2026-07-08

## Purpose

The Capacity Planner owns capacity forecasting, constraint analysis, scenario planning, and scaling recommendations based on authoritative utilization evidence and demand projections.

## Responsibilities

- Consume historical utilization, workload, demand, growth, cost, and health references.
- Forecast capacity demand and confidence ranges.
- Identify projected constraints and planning horizons.
- Compare scaling scenarios and tradeoffs.
- Produce capacity forecasts, constraint findings, contingency references, and scaling recommendations.

## Boundary

The Capacity Planner does not provision or scale infrastructure, allocate production resources, change runtime configuration, perform general risk assessment, execute contingency or recovery actions, collect telemetry, enforce governance, approve budgets, or own audit infrastructure.

Capacity risk observations are planning inputs and do not replace `19_REASONING/RISK-ASSESSOR.md` when general risk assessment is required.

## Rule

Capacity plans inform operational and governance decisions; they do not authorize infrastructure changes.