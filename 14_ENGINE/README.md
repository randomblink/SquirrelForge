# SquirrelForge Engine Layer

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `11_OVERVIEW`, `01_RULES`, `21_CONFIGURATION`, `22_INTERFACES`
Used By: Agent Layer, Workflows, Agents, Coordination, Execution
Last Updated: 2026-07-04

## Purpose

The Engine Layer converts a validated request into structured project context, goals, tasks, dependencies, plans, workflow selections, routes, state transitions, and validation expectations.

The Engine coordinates planning and routing. It does not directly execute project-changing actions.

---

## Layer Boundary

`14_ENGINE` owns:

- project loading,
- goal interpretation and planning,
- task decomposition,
- dependency analysis,
- execution planning,
- workflow selection,
- task routing,
- context assembly,
- engine lifecycle state,
- validation coordination,
- and output rules.

`14_ENGINE` does not own:

- mandatory rules,
- specialist agent identity,
- action dispatch,
- tool execution,
- security policy,
- test implementation,
- persistent storage,
- domain-specific knowledge,
- or AI-provider integration.

Those responsibilities remain in their respective layers.

---

## Components

The Engine layer may include components for:

- Project Loader,
- Goal Planner,
- Task Decomposer,
- Dependency Analyzer,
- Execution Planner,
- Workflow Selector,
- Task Router,
- Context Manager,
- State Manager,
- Validation coordination,
- and Output Rules.

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
Project Load
   ↓
Goal Interpretation
   ↓
Task Decomposition
   ↓
Dependency Analysis
   ↓
Reasoning and Risk Review
   ↓
Execution Planning
   ↓
Workflow Selection
   ↓
Task Routing
   ↓
Execution Handoff
   ↓
Validation Coordination
   ↓
Output Rules
```

---

## Dependencies

The Engine depends on:

- `01_RULES` for mandatory constraints,
- `02_WORKFLOWS` for workflow definitions,
- `12_AGENT` for bootstrap and capability routing context,
- `18_MEMORY` for relevant project and execution context,
- `19_REASONING` for decisions, risks, strategy, and explanation,
- `21_CONFIGURATION` and `28_RUNTIME-CONFIG` for settings and environment policy,
- `22_INTERFACES` for documented contracts,
- and domain layers such as `38_WORDPRESS` only when the active request requires them.

---

## State Rule

The Engine owns planning and routing lifecycle state for the active request.

Components must exchange documented records rather than relying on hidden shared state.

Execution state remains owned by the Execution layer. Persistent state remains owned by Memory and Storage according to their contracts.

---

## Domain Rule

The Engine must not hard-code one domain into general routing behavior.

For WordPress work, the Engine may route to `38_WORDPRESS` and WordPress-specific workflows, agents, rules, and tests.

For unrelated work, WordPress context must not be loaded automatically.

---

## Validation Rule

The Engine may coordinate validation requirements and route validation work, but it must not claim that tests or checks passed unless the responsible validation or testing component produced evidence.

---

## Diagram

```text
Load → Understand → Decompose → Analyze → Reason → Plan → Select → Route → Handoff → Validate → Output
```

---

## Rule

> The Engine plans, selects, routes, and tracks planning state; project-changing actions are performed through controlled Execution-layer workflows and interfaces.
