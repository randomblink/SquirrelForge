# SquirrelForge Automation Manager

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: RULE-ENGINE.md, EVENT-LISTENER.md, SCHEDULER.md, TRIGGER-MANAGER.md, WORKFLOW-AUTOMATOR.md, TASK-ORCHESTRATOR.md, APPROVAL-GATE.md, AUTOMATION-VALIDATOR.md, AUTOMATION-GOVERNANCE.md
Used By: automation-domain callers and downstream consumers
Last Updated: 2026-07-08

## Purpose

The Automation Manager coordinates Automation Layer intake, specialist routing, checkpoint progression, and automation-domain status aggregation.

## Responsibilities

- Receive automation requests and authoritative event references.
- Check request structure and prerequisite-reference availability.
- Route work to the appropriate Automation specialist.
- Coordinate rule, trigger, schedule, readiness, approval, and governance handoffs.
- Submit approved workflow and task handoffs to the appropriate automation coordination components.
- Aggregate automation-domain decisions, status, and evidence references.
- Report status to callers and observability consumers.

## Boundary

The Automation Manager does not evaluate specialist rules, make trigger decisions, own schedules, make approval or governance decisions, perform platform-wide validation, make Security decisions, execute actions, own authoritative workflow/task state, perform retries or recovery, collect telemetry, or own storage and audit infrastructure.

## Failure Handling

Coordination failures produce failure status and evidence references for authoritative failure, resilience, and observability paths. The manager does not create a parallel retry or recovery mechanism.

## Rule

Coordination does not transfer specialist or cross-layer authority to the Automation Manager.