# SquirrelForge Workflow Selector

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: Goal record, `02_WORKFLOWS`, `01_RULES`, project context, risk context
Used By: Capability Router, Execution Planner, Task Router
Last Updated: 2026-07-04

## Purpose

The Workflow Selector chooses one primary workflow for an active request and records why that workflow fits the goal, project context, domain, risk, permissions, and expected output.

Supporting workflows may be added only when they represent distinct quality, safety, lifecycle, recovery, or domain needs.

---

## Selection Inputs

The selector should evaluate:

- request type,
- requested outcome,
- expected artifact or response,
- acceptance criteria,
- project type,
- active domain,
- project state,
- risk level,
- reversibility,
- permissions,
- available tools and interfaces,
- validation requirements,
- recovery requirements,
- and project configuration.

Missing inputs must remain explicit. The selector must not invent project state or tool availability.

---

## Selection Process

1. Read the normalized goal and acceptance criteria.
2. Read verified project context from the Project Loader.
3. Identify the active domain or domains.
4. Classify the request type and risk level.
5. Find candidate workflows in `02_WORKFLOWS`.
6. Reject candidates that violate rules, permissions, environment constraints, or missing required capabilities.
7. Rank remaining candidates by outcome fit, domain fit, risk fit, validation support, recoverability, and simplicity.
8. Select one primary workflow.
9. Add supporting workflows only when separately justified.
10. Record rationale, assumptions, limitations, and unresolved blockers.
11. Pass the selection record to planning and routing.

---

## Selection Record

The selector should produce a record containing:

- request or goal identifier,
- selected primary workflow,
- supporting workflows,
- active domain,
- selection rationale,
- rejected candidates when materially relevant,
- required tools,
- required permissions,
- required validation,
- recovery requirements,
- known limitations,
- and selection status.

---

## Selection States

| State | Meaning |
|---|---|
| `SELECTED` | One primary workflow fits the request and required conditions are available. |
| `SELECTED_WITH_LIMITATIONS` | A workflow fits, but missing optional capabilities or context must be disclosed. |
| `CLARIFICATION_REQUIRED` | A material ambiguity prevents safe selection. |
| `BLOCKED` | Rules, permissions, environment, or required capabilities prevent selection. |
| `UNSUPPORTED` | No governed workflow fits the request. |

---

## Domain Rule

Workflow selection must not assume a domain merely because SquirrelForge supports it.

For WordPress work, select applicable WordPress workflows and load relevant `38_WORDPRESS` references.

For non-WordPress work, WordPress workflows and rules must not be selected automatically.

---

## Risk Rule

Higher-risk requests may require supporting workflows for:

- security review,
- testing,
- governance approval,
- backup verification,
- rollback preparation,
- observability,
- or recovery.

Supporting workflows must not replace the primary workflow.

---

## Ambiguity Rule

Ambiguous requests should not automatically trigger a clarification question.

If verified context and a safe reversible path are sufficient, the selector may choose a controlled workflow and record the assumption.

If ambiguity changes the likely outcome, permission boundary, destructive impact, or security posture, return `CLARIFICATION_REQUIRED`.

---

## Rule

> Select one primary workflow from verified goal, project, domain, risk, permission, and validation context. Never silently select an unrelated workflow or claim support for unavailable capabilities.
