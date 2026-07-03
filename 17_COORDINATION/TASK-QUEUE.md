# SquirrelForge Task Queue

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Task Queue manages pending, active, blocked, and completed tasks across the multi-agent system.

## Responsibilities

- Store all active tasks.
- Track task priority.
- Track task ownership.
- Track task status.
- Prevent duplicate work.
- Preserve task history.
- Send completed tasks to the next agent through the Handoff Protocol.

## Queue States

| State | Meaning |
|---|---|
| Pending | Task is waiting to be assigned |
| Active | Task is currently assigned to an agent |
| Blocked | Task cannot continue |
| Review | Task is awaiting review or validation |
| Complete | Task is finished and validated |
| Archived | Task is stored for history |

## Task Record

| Field | Description |
|---|---|
| Task ID | Unique task identifier |
| Title | Short task name |
| Description | Task details |
| Owner | Current responsible agent |
| Priority | Low / Medium / High / Critical |
| Status | Pending / Active / Blocked / Review / Complete / Archived |
| Dependencies | Required prior tasks |
| Created | Creation date |
| Updated | Last update date |

## Queue Process

1. Add task to the queue.
2. Assign priority.
3. Check dependencies.
4. Assign task to the correct agent.
5. Mark task Active.
6. Update status as work progresses.
7. Move completed task to Review or Complete.
8. Archive completed task after final validation.

## Rule

A task may not become Active until its required dependencies are complete or explicitly acknowledged.