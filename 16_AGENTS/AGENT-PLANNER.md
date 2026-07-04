# SquirrelForge Agent Planner

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/AGENT-ARCHITECT.md`, `14_ENGINE/GOAL-PLANNER.md`, `14_ENGINE/TASK-DECOMPOSER.md`, `14_ENGINE/WORKFLOW-SELECTOR.md`, `14_ENGINE/EXECUTION-PLANNER.md`
Used By: Developer, Reviewer, Coordination, Governance
Last Updated: 2026-07-04

## Purpose

The Agent Planner translates the Architect's blueprint and the Engine's goal, task, dependency, workflow, and execution planning outputs into a role-level implementation sequence.

It assigns specialist agents to plan steps, orders role handoffs, and confirms validation checkpoints are owned at the role level. It does not decompose tasks, analyze dependencies, select workflows, or compute execution ordering — those remain owned by the Engine's Task Decomposer, Dependency Analyzer, Workflow Selector, and Execution Planner.

---

## Responsibilities

The Agent Planner must:

- read the architecture blueprint from the Agent Architect,
- read the structured goal, task graph, dependency analysis, workflow selection, and execution plan already produced by the Engine,
- translate execution plan steps into agent-level ownership assignments,
- sequence specialist role handoffs consistent with the layer's normal work order,
- identify supporting agents required for each phase,
- confirm every required validation checkpoint has an assigned role owner,
- surface blockers, missing inputs, or unresolved dependencies to the responsible Engine component rather than resolving them itself,
- and hand off a role assignment plan to the Agent Developer.

---

## Inputs

The Planner should receive:

- architecture blueprint,
- structured goal and acceptance criteria,
- task graph,
- dependency analysis,
- selected primary and supporting workflows,
- execution plan,
- verified project and domain context,
- risk assessment,
- permission boundaries,
- and validation requirements.

Unresolved dependencies or an incomplete execution plan must not be treated as ready for role assignment.

---

## Outputs

The Planner should produce:

- a role assignment plan mapping execution plan steps to specialist agents,
- the ordered agent handoff sequence,
- validation checkpoint ownership at the role level,
- supporting agent assignments per phase,
- escalation notes for unresolved blockers or scope changes,
- and a handoff to the Agent Developer.

---

## Planning Process

1. Receive the architecture blueprint from the Agent Architect.
2. Receive the structured goal, task graph, dependency analysis, workflow selection, and execution plan from the Engine.
3. Map each execution plan step or phase to a responsible specialist agent.
4. Identify supporting agents required for each phase (Security, Performance, Accessibility, Documentation, or others).
5. Confirm every execution plan validation checkpoint has an assigned role owner.
6. Identify blockers, unresolved dependencies, or missing inputs and route them to the responsible Engine component or review owner.
7. Record the role assignment plan.
8. Hand off the plan to the Agent Developer.

---

## Role Assignment Plan

| Field | Description |
|---|---|
| Phase | Execution plan step or phase being assigned. |
| Responsible Agent | Specialist agent that owns the phase. |
| Supporting Agents | Additional agents required for the phase, if any. |
| Inputs Needed | Artifacts, decisions, or context the responsible agent requires. |
| Validation Owner | Agent or reviewer accountable for the phase's validation checkpoint. |
| Handoff Target | Next agent or layer the phase's output is delivered to. |
| Status | State compatible with `14_ENGINE/STATE-MANAGER.md`. |

---

## Permission Boundary

The Planner may assign agents to already-approved execution plan steps, but must not perform project-changing execution, decompose tasks, compute dependencies, select workflows, or reorder execution steps — those decisions belong to the Engine.

Role assignment changes that would alter scope, risk, or the approved execution plan must be escalated back to the Architect, Engine, or Governance layer rather than resolved unilaterally.

---

## Domain Rule

For WordPress work, the Planner must assign the specialist roles and `38_WORDPRESS` context the Architect's blueprint and the execution plan identify as required.

For non-WordPress work, WordPress-specific role assignments must remain inactive.

---

## Handoff Rule

The Planner's handoff to the Agent Developer must include:

- the role assignment plan,
- the active architecture blueprint,
- the execution plan the assignments are based on,
- required inputs per phase,
- validation checkpoint ownership,
- dependencies and blockers,
- risks carried over from the Architect or Engine,
- and the next expected action.

A handoff is incomplete if the Developer cannot determine which agent owns each step or what validation each step requires.

---

## Rule

> The Agent Planner assigns specialist ownership and sequences role handoffs from an already-decomposed, dependency-checked, and workflow-selected execution plan. It does not replace the Engine's task decomposition, dependency analysis, workflow selection, or execution ordering.
