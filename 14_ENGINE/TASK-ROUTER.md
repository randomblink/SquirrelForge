# SquirrelForge Task Router

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: Execution Plan, Workflow Selector, `16_AGENTS`, `17_COORDINATION`, `22_INTERFACES`
Used By: Coordination, Execution Planner, Execution Layer
Last Updated: 2026-07-04

## Purpose

The Task Router assigns each ready task to the workflow, agent, capability, and execution path able to satisfy its requirements within active rules, dependencies, permissions, capacity, and priority.

The Task Router creates routing decisions. It does not execute the task itself.

---

## Routing Inputs

The router should evaluate:

- task identifier,
- parent goal and workflow,
- task type,
- required capability,
- active domain,
- dependencies,
- dependency status,
- priority,
- risk level,
- required permissions,
- required tools and interfaces,
- candidate agents,
- agent capability and availability,
- coordination constraints,
- validation requirements,
- and recovery requirements.

Unknown availability or capability must not be treated as confirmed.

---

## Routing Process

1. Receive a task from the execution plan.
2. Confirm that required predecessor dependencies are satisfied.
3. Confirm that the task belongs to the selected workflow or an approved supporting workflow.
4. Identify required skills, domain knowledge, tools, permissions, and validation.
5. Find candidate agents or execution owners.
6. Reject candidates that lack required capability, permission, interface access, or availability.
7. Apply ownership, priority, capacity, and coordination rules.
8. Select one task owner.
9. Record the route and handoff context.
10. Send the routed task to Coordination or the approved execution boundary.
11. Track routing state until accepted, rerouted, blocked, cancelled, or completed.

---

## Routing Record

Each material routing decision should record:

- task ID,
- workflow ID or workflow instance ID,
- selected owner,
- required capabilities,
- required domain context,
- required tools or interfaces,
- required permissions,
- priority,
- dependency status,
- validation requirements,
- routing rationale,
- routing timestamp or sequence,
- and routing status.

---

## Routing States

| State | Meaning |
|---|---|
| `PENDING` | Task exists but is not ready for routing. |
| `READY` | Dependencies are satisfied and routing may begin. |
| `ROUTED` | One owner and execution path have been selected. |
| `ACCEPTED` | The selected owner accepted the task. |
| `BLOCKED` | A dependency, permission, capability, tool, or context requirement prevents routing. |
| `REROUTE_REQUIRED` | The selected route is no longer valid. |
| `CANCELLED` | The task was cancelled by lifecycle or governance control. |
| `COMPLETED` | The routed task completed and produced required completion evidence. |

---

## Ownership Rule

One agent or execution owner owns a task at a time.

Parallel work must be decomposed into separate tasks with explicit dependencies or coordination rules.

A handoff must include enough context for the receiving owner to understand:

- the goal,
- task scope,
- relevant project context,
- active rules,
- expected artifact,
- acceptance criteria,
- dependencies,
- risks,
- and required validation.

---

## Domain Rule

The Task Router must route domain-specific tasks to owners with the required domain context.

For WordPress tasks, relevant `38_WORDPRESS` references and WordPress rules must be available to the selected owner.

WordPress capability must not be assumed for unrelated tasks or for agents that have not been assigned that capability.

---

## Rerouting Rule

Rerouting is required when:

- the assigned owner becomes unavailable,
- a required tool or interface is unavailable,
- a permission boundary changes,
- a dependency fails,
- new evidence changes the task classification,
- validation reveals that the selected capability is insufficient,
- or recovery changes the safe execution path.

Rerouting must preserve task history and must not erase the failed or superseded route.

---

## Completion Rule

The Task Router may mark a task `COMPLETED` only after the responsible execution and validation components provide the required completion evidence.

A route being accepted or executed is not itself proof of completion.

---

## Rule

> Route each ready task to one capable, permitted, available owner through a traceable execution path. Preserve routing history and reroute when the original path is no longer valid.
