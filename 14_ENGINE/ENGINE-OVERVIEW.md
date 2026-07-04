# SquirrelForge Engine Overview

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The SquirrelForge Engine is the orchestration layer that coordinates workflows, routes tasks, manages context, tracks state, validates results, and standardizes output across every project.

## Core Components

| Component | Responsibility |
|---|--- |
| Project Loader | Initializes the project environment |
| Workflow Selector | Chooses the correct workflow |
| Task Router | Routes requests to the proper workflow |
| Context Manager | Loads and preserves relevant context |
| State Manager | Tracks task progress and status |
| Validation | Confirms work is correct and complete |
| Output Rules | Standardizes completed responses |

## Execution Flow
This flow describes the lifecycle of a single user request after the engine has been initialized.

```text
User Request
      │
      ▼
Goal Planner
      │
      ▼
Task Decomposer & Dependency Analyzer
      │
      ▼
Execution Planner
      │
      ▼
Workflow Selector & Task Router
      │
      ▼
Execution (Active Workflow)
      │
      ▼
Validation
      │
      ▼
Output Rules
```

## Design Principles

- Keep workflows modular.
- Use one primary workflow per task.
- Load only the context required.
- Validate every completed task.
- Produce consistent outputs.
- Make components reusable across projects.

## Engine Startup Sequence

1. Load the Project Loader.
2. Initialize the State Manager.
3. Initialize the Context Manager.
4. Select the active workflow.
5. Load supporting workflows as needed.
6. Execute the task.
7. Validate the result.
8. Produce the standardized output.

## Rule

Every task executed by SquirrelForge must follow the Engine execution flow from project initialization through validation and standardized output.