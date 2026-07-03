# SquirrelForge Agent API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: Agent Roles, Task Model
Used By: Engine and Coordination
Last Updated: 2026-07-01

`assign(task, contextRef, acceptanceCriteria) → assignmentId`; `status(assignmentId) → state`; `handoff(assignmentId, target, package) → receipt`; `cancel(assignmentId, reason) → result`. Every call requires identity, permission, correlation ID, and typed errors.
