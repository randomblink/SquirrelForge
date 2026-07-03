# SquirrelForge Working Memory

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

Working Memory stores the active context required to complete the current task.

## Responsibilities

- Store the current user request.
- Store the active goal.
- Store the current workflow.
- Store current task state.
- Track active dependencies.
- Preserve temporary decisions.
- Provide context to active agents.

## Working Memory Contents

| Item | Description |
|---|---|
| Current Request | Original user request |
| Active Goal | Goal currently being executed |
| Active Workflow | Workflow currently in use |
| Active Agent | Agent currently responsible |
| Current Task | Task currently being worked |
| Dependencies | Required files, workflows, or tools |
| Temporary Decisions | Short-term decisions for this task |
| Validation State | Current validation status |

## Lifecycle

1. Initialize when a new task begins.
2. Load required context.
3. Update as the task progresses.
4. Preserve during handoffs.
5. Submit important findings to Reflection Engine.
6. Clear when the task is complete and archived.

## Rule

Working Memory should contain only the information needed for the current task and should be cleared after completion unless the information is promoted to long-term memory.