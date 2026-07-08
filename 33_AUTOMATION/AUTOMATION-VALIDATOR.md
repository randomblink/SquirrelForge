# SquirrelForge Automation Validator

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: automation definitions, 14_ENGINE/VALIDATION.md, 29_TESTING, 35_RESILIENCE, 27_OBSERVABILITY, authoritative Security and policy decision references
Used By: APPROVAL-GATE.md, AUTOMATION-GOVERNANCE.md, AUTOMATION-MANAGER.md
Last Updated: 2026-07-08

## Purpose

The Automation Validator owns Automation-domain readiness assessment for automation definitions before approval and execution progression.

## Responsibilities

- Check automation-definition completeness and internal consistency.
- Assess trigger, rule, schedule, workflow-reference, and task-dependency coherence.
- Check that required test, platform-validation, Security, policy, observability-coverage, and recovery-plan references are present where applicable.
- Assess measurable success criteria and failure-handling references.
- Produce ready, ready-with-conditions, requires-revision, deferred, or not-ready findings with evidence references.

## Boundary

The Automation Validator does not perform platform-wide validation, execute tests as Testing authority, evaluate governance policy, make Security decisions, certify compliance, approve automation, execute workflows, verify recovery by performing recovery, collect telemetry, or own audit/storage infrastructure.

## Rule

Automation readiness is an Automation-domain assessment and evidence input. It does not replace authoritative Validation, Testing, Security, Governance, approval, release, deployment, Execution, or Resilience decisions.