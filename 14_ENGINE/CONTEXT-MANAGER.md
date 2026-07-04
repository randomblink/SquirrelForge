# SquirrelForge Context Manager

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/PROJECT-LOADER.md`, `14_ENGINE/STATE-MANAGER.md`, `12_AGENT/COLLECTION-MANIFEST.md`
Used By: Engine, Workflow Selector, Task Router, Reasoning, Execution Planning, Reporting
Last Updated: 2026-07-04

## Purpose

The Context Manager loads, maintains, prunes, and passes the information required for the active request, workflow, task, and validation path.

It keeps the agent focused on relevant evidence while preserving enough continuity to avoid losing state, overwriting work, or making decisions from stale assumptions.

---

## Responsibilities

The Context Manager must:

- maintain verified project context from the Project Loader,
- maintain active lifecycle and task state from the State Manager,
- load mandatory general rules,
- load project-specific instructions,
- load selected workflow documents,
- load relevant checklists and validation references,
- load domain-specific context only when the active request requires that domain,
- track user clarifications and acceptance criteria,
- distinguish verified evidence from memory and assumptions,
- pass relevant context to supporting skills, workflows, or agents,
- prune inactive context when it is no longer needed,
- and preserve the state required for recovery and accurate reporting.

---

## Context Loading Order

At the start of a task, the Context Manager loads information in this priority:

1. **Current User Request** — goal, constraints, acceptance criteria, and explicit permissions.
2. **System and Agent Context** — active bootstrap state, agent profile, and collection manifest.
3. **Mandatory General Rules** — general behavior, safety, permissions, and completion rules.
4. **Verified Project Context** — Project Loader output, repository state, configuration, runtime profile, and known limitations.
5. **Active State** — lifecycle phase, selected workflow, task state, routing state, blockers, and validation state.
6. **Applicable Workflow and Checklists** — primary workflow, supporting workflows, quality gates, and completion checks.
7. **Applicable Domain Context** — domain rules and references only when the request touches that domain.
8. **Relevant Memory and Knowledge** — prior decisions, reusable knowledge, and project history, treated as supporting context.
9. **Task-Specific Supporting Context** — selected skills, tools, interfaces, templates, and output requirements.

---

## Domain Loading Rule

Domain context is loaded by need, not habit.

For WordPress work, load:

- `01_RULES/WORDPRESS-RULES.md`,
- relevant `38_WORDPRESS` references,
- and applicable WordPress validation expectations.

For non-WordPress work, do not automatically load WordPress rules or WordPress handbooks.

---

## Evidence Priority

When context sources disagree, evaluate them in this order:

1. Current user request and explicit constraints
2. Mandatory system, safety, and permission rules
3. Current repository or runtime evidence
4. Project configuration and local instructions
5. Active workflow and validation requirements
6. Authoritative domain references
7. Project memory and prior decisions
8. General reusable knowledge
9. Assumptions

Assumptions must be labeled or resolved before they become execution dependencies.

---

## Context Record

The Context Manager should maintain a record containing:

- request summary,
- active goal,
- acceptance criteria,
- verified project context,
- active domain context,
- loaded rules,
- loaded workflow references,
- loaded checklists,
- active state summary,
- relevant memory,
- assumptions,
- unknowns,
- limitations,
- and context expiry or pruning notes.

---

## Context Pruning

To maintain focus, context that is no longer relevant should be pruned after:

- a major lifecycle phase completes,
- a workflow route changes,
- a task is validated and finished,
- a domain is no longer active,
- a supporting workflow completes,
- or the project is marked complete.

The State Manager record, decision record, validation evidence, and recovery-relevant context must remain available until the lifecycle is safely completed or retained according to policy.

---

## Staleness Rule

Memory, documentation, and knowledge can become stale.

The Context Manager must prefer current repository state, runtime evidence, and verified user requirements over older memory or earlier plans.

If stale context is detected, record the conflict and route it to reasoning, validation, or cleanup instead of silently trusting it.

---

## Rule

> Load the minimum sufficient context for the active step, preserve evidence needed for continuity and recovery, and load domain-specific references only when the active request requires that domain.
