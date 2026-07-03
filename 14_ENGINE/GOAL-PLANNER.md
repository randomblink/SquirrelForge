# SquirrelForge Goal Planner

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Goal Planner converts a user request into a clear execution goal and a high-level plan.

## Responsibilities

- Identify the main goal.
- Separate the goal from supporting tasks.
- Detect the expected output.
- Identify missing information.
- Determine whether the request requires one workflow or multiple workflows.
- Prepare the task for decomposition and execution.

## Planning Process

1. Read the user request.
2. Identify the primary goal.
3. Identify the expected output.
4. Identify supporting tasks.
5. Identify dependencies.
6. Identify validation needs.
7. Pass the goal to the Task Decomposer.

## Goal Model

| Field | Description |
|---|---|
| Request | Original user request |
| Primary Goal | Main thing to accomplish |
| Expected Output | Final deliverable |
| Supporting Tasks | Secondary tasks required |
| Dependencies | Required files, workflows, tools, or context |
| Validation Needs | Checks required before completion |
| Status | Not Started / Planned / Blocked / Ready |

## Rule

Every non-trivial task must begin with a clear primary goal before execution begins.