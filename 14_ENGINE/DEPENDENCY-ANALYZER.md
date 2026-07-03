# SquirrelForge Dependency Analyzer

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Dependency Analyzer identifies everything required before a task can begin, ensuring execution starts with the correct context, files, workflows, and prerequisites.

## Responsibilities

- Detect required files.
- Detect required workflows.
- Detect required templates.
- Detect project dependencies.
- Detect external dependencies.
- Identify missing prerequisites.
- Report dependency conflicts before execution.

## Analysis Process

1. Receive the task from the Task Decomposer.
2. Identify required project files.
3. Identify required workflows.
4. Identify required templates.
5. Identify external tools or libraries.
6. Detect missing or conflicting dependencies.
7. Pass validated dependencies to the Execution Planner.

## Dependency Model

| Field | Description |
|---|---|
| Dependency | Name of the dependency |
| Type | File / Workflow / Template / Library / Tool / External |
| Required | Yes / No |
| Status | Available / Missing / Conflict |
| Notes | Additional information |

## Dependency Priority

1. Project Rules
2. Engine Components
3. Primary Workflow
4. Supporting Workflows
5. Templates
6. Project Files
7. External Libraries
8. External Services

## Rule

No execution may begin until all required dependencies have been verified or any missing dependencies have been explicitly acknowledged.