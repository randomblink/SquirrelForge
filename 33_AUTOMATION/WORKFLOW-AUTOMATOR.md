# SquirrelForge Workflow Automator

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: APPROVAL-GATE.md, AUTOMATION-VALIDATOR.md, AUTOMATION-GOVERNANCE.md, 20_EXECUTION, 35_RESILIENCE
Used By: AUTOMATION-MANAGER.md, TASK-ORCHESTRATOR.md
Last Updated: 2026-07-08

## Purpose

The Workflow Automator converts fully qualified Automation-domain workflow requests into execution handoffs and tracks automation-facing workflow status from authoritative execution references.

## Responsibilities

- Receive workflow automation requests with required trigger, rule, readiness, approval, and governance references.
- Check handoff completeness and workflow-definition references.
- Submit workflow execution requests to authoritative Execution components.
- Coordinate Automation-domain task handoffs with Task Orchestrator where applicable.
- Consume execution progress, completion, failure, and recovery-status references.
- Request recovery handling from Resilience owners when authorized conditions require it.
- Publish automation-facing workflow status and evidence references.

## Boundary

The Workflow Automator does not initialize or own authoritative workflow instances, execute actions, allocate resources, own workflow execution state, monitor infrastructure, execute retries, rollback, recovery, or state restoration, perform validation, enforce governance policy, or own logging, audit, and storage infrastructure.

## Rule

Workflow Automator owns the automation-to-execution handoff, not the execution engine.