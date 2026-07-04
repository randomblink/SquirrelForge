# SquirrelForge Task Decomposer

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/GOAL-PLANNER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`
Used By: `14_ENGINE/ENGINE-OVERVIEW.md`
Last Updated: 2026-07-01

## Purpose

The Task Decomposer breaks a structured goal from the `Goal Planner` into a series of small, ordered, and independently verifiable tasks. Each task has clear boundaries, inputs, outputs, and completion criteria.

## Responsibilities

-   Consume the `Goal Definition` from the `Goal Planner`.
-   Break the primary goal into a sequence of concrete, executable tasks.
-   For each task, define its specific inputs, expected outputs, and verifiable completion criteria.
-   Identify task-level dependencies, risks, and required permissions.
-   Assign a validation owner for each task's output.
-   Designate key tasks as checkpoints for recovery.
-   Pass the list of structured `Task Definitions` to the `Dependency Analyzer`.

## Decomposition Process

1.  **Receive Goal:** Ingest the structured `Goal Definition` from the `Goal Planner`.
2.  **Identify Major Steps:** Break the goal into high-level phases or work areas.
3.  **Decompose into Tasks:** For each phase, create a list of small, concrete tasks.
4.  **Define Task Boundaries:** For each task, specify its inputs, outputs, completion criteria, and dependencies.
5.  **Assess Task Attributes:** Assign risk, permissions, and validation ownership for each task.
6.  **Emit Task List:** Produce a list of `Task Definition` records.
7.  **Forward for Analysis:** Pass the task list to the `Dependency Analyzer`.

## Task Definition

| Field | Description |
|---|---|
| **Task ID** | A unique identifier for this specific task. |
| **Goal ID** | The identifier of the parent goal for traceability. |
| **Description** | A clear statement of what must be done. |
| **Inputs** | The specific artifacts or data required to start the task. |
| **Expected Outputs** | The specific artifacts or data the task must produce. |
| **Completion Criteria** | A measurable, verifiable definition of "done" for this task. |
| **Dependencies** | A list of other `Task IDs` that must be completed first. |
| **Domain Context** | The operational domain (e.g., `WordPress`, `Core`) required for execution. |
| **Risk** | The estimated risk associated with this specific task (e.g., `Low`, `Medium`, `High`). |
| **Permissions Required** | The specific permissions needed to execute the task (e.g., `file:write`). |
| **Validation Owner** | The component or role responsible for validating the task's output. |
| **Is Checkpoint** | `true` if the successful completion of this task represents a safe recovery point. |

## Rule

1.  **Verifiable Tasks:** Every task must have explicit, non-ambiguous `Completion Criteria`.
2.  **Small Units of Work:** Tasks should be decomposed to the smallest practical unit of work that can be independently executed and validated.
3.  **No Ambiguity:** The decomposer must not proceed if the parent goal is ambiguous. It should rely on the `Goal Planner` to handle clarification.
4.  **Parallelism Rules:** Tasks that have no dependencies on each other may be marked as eligible for parallel execution by the `Execution Planner`.