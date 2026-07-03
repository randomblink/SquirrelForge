# SquirrelForge Handoff Protocol

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Handoff Protocol defines how responsibility for a task is transferred from one agent to another while preserving context, state, and validation history.

## Responsibilities

- Transfer task ownership.
- Preserve execution context.
- Preserve validation results.
- Preserve progress state.
- Prevent duplicate work.
- Confirm successful receipt by the next agent.

## Handoff Process

1. Complete the assigned work.
2. Update the task status.
3. Attach all required artifacts.
4. Include validation results.
5. Send a handoff message through the Message Bus.
6. Receive acknowledgment from the next agent.
7. Transfer ownership.
8. Record the completed handoff.

## Required Handoff Information

| Field | Description |
|---|---|
| Task ID | Unique task identifier |
| Current Agent | Agent completing the work |
| Next Agent | Receiving agent |
| Task Status | Current completion state |
| Validation Status | Pending / Passed / Failed |
| Artifacts | Files, documentation, or outputs |
| Notes | Additional context |

## Handoff Validation

Before ownership changes:

- [ ] Task status updated
- [ ] Validation results attached
- [ ] Required artifacts included
- [ ] Context preserved
- [ ] Next agent identified
- [ ] Acknowledgment received

## Failure Handling

If the receiving agent rejects the handoff:

1. Return ownership to the current agent.
2. Record the rejection reason.
3. Correct the identified issues.
4. Repeat the handoff process.

## Rule

Task ownership changes only after the receiving agent acknowledges a complete and validated handoff.