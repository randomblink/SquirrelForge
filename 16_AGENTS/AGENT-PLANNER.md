# SquirrelForge Agent Planner

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Agent Planner converts the architecture blueprint into an ordered execution plan.

## Responsibilities

- Read the architecture blueprint.
- Break the work into phases.
- Define task order.
- Identify dependencies.
- Assign supporting agents.
- Insert validation checkpoints.
- Prepare the plan for implementation.

## Planning Process

1. Receive the architecture blueprint from the Agent Architect.
2. Break the solution into phases.
3. Define the task order.
4. Identify dependencies and blockers.
5. Assign the correct agent for each phase.
6. Add validation checkpoints.
7. Send the plan to the Agent Developer.

## Execution Plan Model

| Field | Description |
|---|---|
| Phase | Major work section |
| Task | Specific task to complete |
| Agent | Responsible agent |
| Dependencies | Required prior work |
| Validation | Required check |
| Status | Pending / In Progress / Blocked / Complete |

## Rule

Every implementation must have an ordered plan before development begins.