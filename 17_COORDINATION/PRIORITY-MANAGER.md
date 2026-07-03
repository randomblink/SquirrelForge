# SquirrelForge Priority Manager

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Priority Manager determines the execution order of tasks by balancing urgency, dependencies, project goals, and available resources.

## Responsibilities

- Assign task priority.
- Recalculate priorities as work changes.
- Respect task dependencies.
- Prevent lower-priority work from blocking critical tasks.
- Support dynamic reprioritization.
- Provide scheduling recommendations to the Agent Orchestrator.

## Priority Levels

| Level | Meaning |
|---|---|
| Critical | Immediate action required |
| High | Important to current milestone |
| Medium | Normal planned work |
| Low | Nice-to-have or future work |

## Priority Factors

- User-requested urgency
- Task dependencies
- Blocking impact
- Security implications
- Release readiness
- Technical risk
- Estimated effort
- Business value

## Prioritization Process

1. Evaluate the task.
2. Check dependencies.
3. Determine blocking impact.
4. Assign an initial priority.
5. Compare with active tasks.
6. Reorder the task queue if needed.
7. Notify the Agent Orchestrator of changes.

## Reprioritization Triggers

- New critical task
- Blocked dependency
- Validation failure
- Security issue
- User-requested change
- Release deadline

## Priority Record

| Field | Description |
|---|---|
| Task ID | Related task |
| Priority | Critical / High / Medium / Low |
| Reason | Why this priority was assigned |
| Assigned By | Agent or system component |
| Last Reviewed | Most recent evaluation |

## Rule

Priority decisions must consider both urgency and dependency order. A high-priority task may not bypass unresolved required dependencies unless explicitly authorized.