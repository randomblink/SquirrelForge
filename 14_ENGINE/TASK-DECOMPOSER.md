# SquirrelForge Task Decomposer

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/GOAL-PLANNER.md`, `14_ENGINE/CONTEXT-MANAGER.md`, `19_REASONING`
Used By: Dependency Analyzer, Execution Planner, Workflow Selector, Task Router
Last Updated: 2026-07-04

## Purpose

The Task Decomposer converts a structured goal into bounded, ordered, independently understandable tasks that can be analyzed, planned, routed, executed, validated, recovered, and reported.

It defines task boundaries. It does not execute tasks or mark them complete.

---

## Responsibilities

The Task Decomposer must:

- convert the primary goal into concrete task units,
- preserve the goal's acceptance criteria and scope,
- separate required tasks from optional tasks,
- identify task inputs and expected outputs,
- identify dependencies between tasks,
- identify tasks that require supporting workflows,
- identify domain context required by each task,
- identify permission-sensitive or high-risk tasks,
- identify validation points,
- identify useful checkpoints and recovery boundaries,
- avoid unnecessary fragmentation,
- and pass the task graph to dependency analysis and execution planning.

---

## Decomposition Process

1. Receive the structured goal record.
2. Read active project and context records.
3. Identify major work areas required by the acceptance criteria.
4. Break each work area into concrete tasks with one clear outcome.
5. Identify inputs, outputs, dependencies, and ordering constraints.
6. Mark optional work separately from required work.
7. Identify active domain context for each task.
8. Identify permission, security, production, or destructive risk.
9. Identify validation requirements and evidence owner.
10. Identify checkpoint or rollback boundaries when needed.
11. Review the task graph for missing work, duplication, and circular dependency.
12. Pass the task graph to Dependency Analyzer and Execution Planner.

---

## Task Model

| Field | Description |
|---|---|
| Task ID | Stable unique task identifier. |
| Task Name | Short task label. |
| Description | Exact outcome the task must produce. |
| Required | Whether the task is required for the primary goal. |
| Inputs | Files, context, decisions, or artifacts required. |
| Expected Output | Artifact, state change, decision, or evidence the task should produce. |
| Dependencies | Prior tasks, decisions, files, or conditions required. |
| Active Domain | Domain context required for the task, if any. |
| Workflow | Primary or supporting workflow associated with the task. |
| Risk | Task-specific risk level and material risk factors. |
| Permissions | Required permission boundary. |
| Validation | Required validation and evidence owner. |
| Checkpoint | Recovery or rollback checkpoint requirement. |
| Status | State compatible with `14_ENGINE/STATE-MANAGER.md`. |

---

## Task Size Rule

A task should normally have:

- one clear outcome,
- one accountable owner at a time,
- understandable inputs,
- a verifiable output,
- and a defined completion condition.

Do not split work into artificial micro-tasks that add coordination overhead without improving ownership, safety, validation, or recovery.

Do not combine unrelated outcomes into one task merely to reduce task count.

---

## Dependency Rule

The Task Decomposer identifies candidate dependencies, but the Dependency Analyzer owns deeper dependency validation and cycle detection.

Dependencies must be explicit when one task requires another task's output, permission, decision, checkpoint, or validation result.

---

## Parallel Work Rule

Tasks may be eligible for parallel work only when:

- they have no unresolved dependency relationship,
- they do not mutate the same state unsafely,
- ownership boundaries are clear,
- coordination requirements are defined,
- and validation can distinguish their outputs.

Parallelism must not be inferred solely because tasks appear different.

---

## Domain Rule

Domain context is assigned per task when required.

For WordPress-specific tasks, relevant `38_WORDPRESS` context and WordPress rules must be available downstream.

General tasks must not be labeled WordPress-specific merely because the repository supports WordPress work.

---

## Completion Boundary Rule

The Task Decomposer must define what evidence would allow each task to be considered complete, but completion state is controlled by execution and validation evidence through the State Manager.

A task definition must not pre-claim successful execution or validation.

---

## Rule

> Decompose goals into the smallest useful tasks that improve ownership, dependency clarity, validation, or recovery. Every task must have a clear outcome, explicit dependencies, and a verifiable completion boundary.
