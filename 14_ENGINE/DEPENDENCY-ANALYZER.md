# SquirrelForge Dependency Analyzer

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/TASK-DECOMPOSER.md`, `14_ENGINE/CONTEXT-MANAGER.md`, `22_INTERFACES`, `21_CONFIGURATION`
Used By: Execution Planner, Workflow Selector, Task Router, State Manager
Last Updated: 2026-07-04

## Purpose

The Dependency Analyzer identifies and validates everything required before a task, workflow, or execution plan may proceed.

It verifies ordering, prerequisites, conflicts, missing context, tool requirements, permission requirements, validation dependencies, and recovery dependencies before execution planning begins.

---

## Responsibilities

The Dependency Analyzer must:

- detect required files and artifacts,
- detect required workflows and supporting workflows,
- detect required templates,
- detect required project dependencies,
- detect required tools and interfaces,
- detect required permissions,
- detect required domain knowledge,
- detect required configuration and runtime profile data,
- detect validation dependencies,
- detect checkpoint or rollback dependencies,
- identify missing prerequisites,
- identify conflicts,
- identify circular dependencies,
- identify unsafe parallelism,
- classify dependency severity,
- and report dependency blockers before execution.

---

## Analysis Process

1. Receive the task graph from the Task Decomposer.
2. Read active context from the Context Manager.
3. Identify dependency relationships between tasks.
4. Identify file, artifact, workflow, template, tool, interface, domain, permission, configuration, and validation dependencies.
5. Verify availability where possible.
6. Identify missing, unknown, stale, conflicting, circular, or unsafe dependencies.
7. Determine whether dependencies block work, limit work, or merely inform work.
8. Identify tasks eligible for safe parallel routing.
9. Record dependency results and unresolved blockers.
10. Pass validated dependency data to the Execution Planner and State Manager.

---

## Dependency Model

| Field | Description |
|---|---|
| Dependency ID | Stable identifier for the dependency. |
| Name | Human-readable dependency name. |
| Type | File, artifact, workflow, template, tool, interface, permission, domain, configuration, validation, external, or task. |
| Required | Whether this dependency is required for the task or workflow. |
| Owner | Layer, tool, agent, or task responsible for satisfying it. |
| Status | Available, missing, unknown, stale, conflict, blocked, or waived. |
| Severity | Informational, limiting, blocking, high-risk, or critical. |
| Required By | Task, workflow, or validation item that depends on it. |
| Evidence | Source that proves the dependency status. |
| Notes | Additional context, risks, or constraints. |

---

## Dependency Status Values

| Status | Meaning |
|---|---|
| `AVAILABLE` | Dependency is verified and usable. |
| `MISSING` | Dependency is required but not present. |
| `UNKNOWN` | Dependency status could not be verified. |
| `STALE` | Dependency exists but may not reflect current project state. |
| `CONFLICT` | Dependency conflicts with another requirement or state. |
| `BLOCKED` | Dependency cannot be satisfied under current conditions. |
| `WAIVED` | Dependency was intentionally waived by approved governance or permission decision. |

---

## Dependency Priority

When ordering dependencies, use this priority:

1. Mandatory rules and permission boundaries
2. Security, governance, and safety requirements
3. Project identity and repository state
4. Configuration and runtime profile
5. Active workflow and task graph
6. Domain rules and domain knowledge
7. Required files and artifacts
8. Required tools and interfaces
9. Required templates
10. Required external libraries or services
11. Validation and reporting requirements
12. Optional supporting material

---

## Cycle Detection Rule

The analyzer must detect circular task dependencies.

If a cycle exists, execution planning must not continue until the cycle is resolved, waived by an approved governance decision, or transformed into a controlled iterative workflow.

---

## Conflict Rule

Dependency conflicts must be surfaced before execution.

Examples include:

- two tasks modifying the same artifact without coordination,
- a workflow requiring a tool that is unavailable,
- a domain rule conflicting with project configuration,
- a permission requirement that exceeds the active boundary,
- stale memory contradicting current repository evidence,
- or validation requirements that cannot be satisfied.

---

## Parallelism Rule

Tasks may be marked parallel-safe only when:

- no ordering dependency exists between them,
- they do not require conflicting permissions,
- they do not mutate the same state unsafely,
- they do not rely on the same unavailable tool,
- ownership and handoff boundaries are clear,
- and validation can distinguish the outputs.

---

## Blocker Rule

A missing or conflicting required dependency must produce a blocker record containing:

- blocked task or workflow,
- missing or conflicting dependency,
- responsible layer or owner,
- severity,
- evidence,
- and next safe action.

---

## Rule

> No execution plan may proceed until required dependencies are verified, explicitly marked unavailable, or waived by an approved governance or permission decision. Dependency uncertainty must not be treated as availability.
