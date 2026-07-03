# SquirrelForge State Manager

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The State Manager tracks the current progress of a task and preserves context throughout the workflow.

## Responsibilities

- Record the current workflow.
- Track the current milestone.
- Track the current task.
- Record completed steps.
- Record pending steps.
- Store validation status.
- Maintain context between workflow stages.

## State Model

| Field | Description |
|---|---|
| Workflow | Active workflow being executed |
| Milestone | Current milestone |
| Task | Current task |
| Status | Not Started / In Progress / Blocked / Complete |
| Validation | Pending / Passed / Failed |
| Next Step | Recommended action after completion |

## State Transitions

1. Task created → **Not Started**
2. Work begins → **In Progress**
3. Validation required → **Pending**
4. Validation succeeds → **Passed**
5. Task finishes → **Complete**
6. Validation fails → **Failed**
7. External dependency prevents progress → **Blocked**

## Rule

Only one task may be **In Progress** at a time. A task cannot be marked **Complete** until validation has passed.