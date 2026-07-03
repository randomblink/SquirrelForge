# SquirrelForge Project Loader

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Project Loader initializes a SquirrelForge project by loading the core engine, project rules, workflows, and supporting documentation required for the current task.

## Loading Process

1. Verify the project root.
2. Load global project rules.
3. Load the Workflow Selector.
4. Load the active workflow.
5. Load supporting workflows, if required.
6. Load validation rules.
7. Initialize the State Manager.
8. Initialize the Context Manager.
9. Confirm the project is ready.

## Core Components

| Component | Location |
|---|---|
| Workflow Selector | 14_ENGINE/WORKFLOW-SELECTOR.md |
| Task Router | 14_ENGINE/TASK-ROUTER.md |
| State Manager | 14_ENGINE/STATE-MANAGER.md |
| Context Manager | 14_ENGINE/CONTEXT-MANAGER.md |
| Validation | 14_ENGINE/VALIDATION.md |
| Output Rules | 14_ENGINE/OUTPUT-RULES.md |

## Startup Checklist

- [ ] Project root verified
- [ ] Core engine loaded
- [ ] Active workflow selected
- [ ] Supporting workflows loaded (if needed)
- [ ] Validation ready
- [ ] State initialized
- [ ] Context initialized
- [ ] Project ready

## Rule

A project is considered ready only after all startup checklist items have been completed successfully.