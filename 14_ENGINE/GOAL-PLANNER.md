# SquirrelForge Goal Planner

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `12_AGENT/BOOTSTRAP.md`, `14_ENGINE/PROJECT-LOADER.md`, `14_ENGINE/CONTEXT-MANAGER.md`
Used By: Engine, Task Decomposer, Workflow Selector, Capability Router
Last Updated: 2026-07-04

## Purpose

The Goal Planner converts a user request into a clear, bounded, evidence-aware goal that can be decomposed, routed, validated, and reported.

It separates the primary outcome from supporting tasks, assumptions, risks, missing information, and validation needs before execution planning begins.

---

## Responsibilities

The Goal Planner must:

- identify the primary goal,
- identify the expected output,
- identify acceptance criteria,
- separate required work from optional supporting work,
- identify the active project or domain when known,
- identify whether the request is read-only, planning-only, documentation-only, project-changing, destructive, external, recovery-related, automation-related, or deployment-related,
- identify missing information and assumptions,
- identify dependencies,
- identify validation needs,
- identify initial risk level,
- identify likely workflow needs,
- and prepare the goal for task decomposition and workflow selection.

---

## Planning Process

1. Read the current user request and available conversation context.
2. Identify the primary requested outcome.
3. Identify expected output or artifact.
4. Identify explicit constraints, permissions, and acceptance criteria.
5. Identify relevant project context from the Project Loader.
6. Identify active domain only when evidence supports it.
7. Separate main work from supporting work.
8. Identify dependencies, blockers, unknowns, and assumptions.
9. Classify request type and initial risk.
10. Identify validation and reporting requirements.
11. Produce a structured goal record.
12. Pass the goal to Task Decomposer, Workflow Selector, or Output Rules as appropriate.

---

## Goal Model

| Field | Description |
|---|---|
| Request | Original or normalized user request. |
| Primary Goal | Main outcome to accomplish. |
| Expected Output | Final deliverable, answer, artifact, or state change. |
| Acceptance Criteria | Conditions that must be satisfied before completion. |
| Scope | Work that is included and excluded. |
| Request Type | Read-only, plan-only, documentation, implementation, review, cleanup, recovery, automation, release, or other. |
| Active Domain | Domain context required, such as WordPress, when supported by evidence. |
| Supporting Tasks | Secondary tasks required to satisfy the primary goal. |
| Dependencies | Required files, workflows, tools, permissions, context, or decisions. |
| Assumptions | Explicit assumptions being used to proceed. |
| Missing Information | Information that is unknown and may affect the outcome. |
| Initial Risk | Low, moderate, high, or critical risk estimate. |
| Validation Needs | Checks required before completion can be claimed. |
| Status | Planned, ready, blocked, clarification-required, unsupported, or failed. |

---

## Clarification Rule

The Goal Planner should not ask for clarification when a safe, reversible, useful path can be chosen from available context.

Clarification is required when missing information changes:

- the likely output,
- the target project,
- the permission boundary,
- destructive or irreversible impact,
- security posture,
- production impact,
- or user-data risk.

When proceeding with assumptions, the assumptions must be recorded and reported if material.

---

## Domain Rule

The Goal Planner must not assume a domain merely because SquirrelForge supports it.

For WordPress work, the goal record may activate WordPress domain context and route later stages toward `38_WORDPRESS`.

For non-WordPress work, WordPress must remain inactive.

---

## Scope Rule

The primary goal should stay as narrow as possible while still satisfying the user request.

Scope may expand only when required by:

- safety,
- validation,
- architecture consistency,
- dependency resolution,
- recovery,
- or explicit user instruction.

Scope expansion must be recorded.

---

## Rule

> Every non-trivial request must have a clear primary goal, expected output, acceptance criteria, scope, assumptions, dependencies, risk, and validation needs before decomposition or execution planning begins.
