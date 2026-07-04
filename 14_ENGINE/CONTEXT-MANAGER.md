# Agent Engine: Context Manager

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-04

## Purpose

The Context Manager is responsible for loading, maintaining, and unloading the information required to complete the current task. It ensures the agent has precisely the right context while minimizing cognitive load and preventing context contamination.

---

## Responsibilities

-   Maintain the active project context (from the `Project Loader`).
-   Track the active workflow and task state.
-   Preserve task-specific information (e.g., user clarifications).
-   Load domain-specific knowledge and rules only when required by the task.
-   Prune stale or irrelevant context to maintain focus.
-   Provide a traceable record of the context used for a decision.

---

## Context Loading Priority

Context is assembled with the following priority order, where higher-priority items override lower-priority ones in case of conflict:

1.  **Current Task Evidence:** Facts, files, and outputs directly related to the immediate step being executed.
2.  **Current Task Definition:** The specific request, its goals, and any user clarifications.
3.  **Active Workflow State:** The state of the primary workflow executing the task.
4.  **Domain-Specific Knowledge:** Rules and documents loaded by a domain-specific manager (e.g., `38_WORDPRESS/KNOWLEDGE-MANAGER.md`) relevant to the task's domain.
5.  **Project Context:** The broader project state provided by the `Project Loader`.
6.  **Core System Rules:** Universal behaviors and constraints, such as `01_RULES/AGENT-BEHAVIOR.md`.

---

## Context States

| State | Description |
|---|---|
| `Loading` | The Context Manager is assembling the required information for a new task. |
| `Active` | The context is loaded and is being used by an agent for execution. |
| `Stale` | The context is no longer relevant to the current step and is pending pruning. |
| `Pruning` | The Context Manager is actively removing stale information. |
| `Unloaded` | The context for a completed task has been archived and removed from active memory. |

---

## Context Pruning

To maintain focus and manage token limits, context that is no longer relevant to the current step must be unloaded. Pruning is triggered when:

- A major workflow phase is completed.
- A task is fully validated and finished.
- The agent's focus shifts to a different domain or skill.
- The project itself is marked as complete.

---

## Rules

1.  **Evidence Overrides Memory:** Information derived from direct observation of the current task's state (e.g., file contents, command output) must always take precedence over historical memory or general knowledge.
2.  **Domain-Specific Loading:** Domain-specific rules (e.g., WordPress standards) must not be loaded into the global context. They must be loaded on-demand by a relevant domain manager (like `38_WORDPRESS/KNOWLEDGE-MANAGER.md`) when a task requires that specific specialization.
3.  **Minimum Necessary Context:** Load only the minimum context necessary to complete the current step while preserving enough information to maintain continuity throughout the entire task.
4.  **Staleness Invalidation:** Context from a previous task or workflow step is considered stale and must not be used for a new task unless explicitly re-validated and loaded.
5.  **Traceability:** The set of active context documents and sources used for any significant agent decision must be recorded for audit and traceability.
