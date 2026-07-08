# SquirrelForge Task Orchestrator

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: WORKFLOW-AUTOMATOR.md, 16_AGENTS, 20_EXECUTION, 26_INTEGRATIONS, 35_RESILIENCE
Used By: AUTOMATION-MANAGER.md, WORKFLOW-AUTOMATOR.md
Last Updated: 2026-07-08

## Purpose

The Task Orchestrator owns Automation-domain task dependency coordination, readiness ordering, synchronization planning, and dispatch handoffs for approved automated workflows.

## Responsibilities

- Receive approved automation task graphs and execution-context references.
- Check task-definition and dependency-reference completeness.
- Determine Automation-domain readiness order from declared dependencies.
- Coordinate parallel-group and synchronization handoffs.
- Request agent, service, integration, or execution dispatch through authoritative owners.
- Consume authoritative task execution and failure status references.
- Request retry or recovery handling from owning Execution or Resilience components when policy references permit.
- Report automation-facing task coordination status to Workflow Automator.

## Boundary

The Task Orchestrator does not execute tasks, assign agents as an agent authority, invoke services outside approved integration/execution paths, own authoritative task state, define retry/recovery policy, execute retries or recovery, schedule time-based automation, enforce governance policy, collect telemetry, or own audit/storage infrastructure.

## Rule

Task orchestration coordinates approved handoffs and dependencies; authoritative execution state remains with Execution owners.