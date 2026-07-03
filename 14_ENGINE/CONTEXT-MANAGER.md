# Agent Engine: Context Manager

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Context Manager is responsible for loading, maintaining, and unloading the information required to complete the current task. It ensures the agent has precisely the right context while minimizing cognitive load.

## Responsibilities

-   Maintain the active project context (from `CONTEXT-LOADER`).
-   Track the active workflow and task state.
-   Preserve task-specific information (e.g., user clarifications).
-   Load only the documents needed for the current step.
-   Remove inactive context when it is no longer required.
-   Pass relevant context to supporting skills or workflows.

## Context Loading Order

At the start of any task, the Context Manager loads information in this priority:

1.  **Core Rules:** `01_RULES/AGENT-BEHAVIOR.md` and `01_RULES/WORDPRESS-RULES.md`.
2.  **Project Context:** The output from the `CONTEXT-LOADER`.
3.  **Active Workflow:** The primary workflow selected for the task.
4.  **Current Task:** The user's specific request and any clarifications.
5.  **Supporting Workflows/Skills:** Any additional capabilities needed.

## Context Unloading (Pruning)

To maintain focus, context that is no longer relevant to the current step should be unloaded. This typically occurs after:

-   A major workflow phase is completed.
-   A task is fully validated and finished.
-   The project itself is marked as complete.

**Note:** The core `TASK-STATE` should always be preserved until the session ends.

## Rule

Load the minimum context necessary to complete the current step while preserving enough information to maintain continuity throughout the entire task.
