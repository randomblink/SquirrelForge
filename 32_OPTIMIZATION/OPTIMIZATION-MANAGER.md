# SquirrelForge Optimization Manager

Version: 1.0.0
Status: Stable
Owner: Optimization Maintainers
Depends On: OPTIMIZATION-ENGINE.md, PERFORMANCE-OPTIMIZER.md, RESOURCE-OPTIMIZER.md, WORKFLOW-OPTIMIZER.md, AGENT-OPTIMIZER.md, CAPACITY-PLANNER.md, COST-OPTIMIZER.md, OPTIMIZATION-VALIDATOR.md, OPTIMIZATION-GOVERNANCE.md
Used By: optimization-domain callers and downstream consumers
Last Updated: 2026-07-08

## Purpose

The Optimization Manager coordinates Optimization Layer intake, specialist routing, proposal progression, validation and governance handoffs, and optimization-domain status aggregation.

## Responsibilities

- Receive optimization requests and trigger references.
- Check request structure and prerequisite evidence references.
- Route work to the appropriate optimization specialist or Optimization Engine.
- Coordinate proposal handoffs to Optimization Validator and Optimization Governance.
- Aggregate specialist findings, proposal, validation, governance, and implementation-status references.
- Publish optimization-domain status to callers and observability consumers.

## Boundary

The Optimization Manager coordinates the layer. It does not independently perform specialist optimization analysis, general risk assessment, platform validation, governance-policy evaluation, Optimization Governance decisions, production execution, deployment, retry, recovery, rollback, remediation, storage persistence, general monitoring, or audit-trail infrastructure.

Priority recommendations produced by the manager are optimization-domain coordination outputs and do not replace business, governance, risk, release, or execution priorities owned elsewhere.

## Failure Handling

Coordination failures produce failure status and evidence references for owning failure, resilience, and observability paths. The manager does not create a parallel retry or recovery mechanism.

## Rule

Coordination does not transfer specialist or cross-layer authority to the Optimization Manager.