# SquirrelForge Workflow Executor

## Purpose

The Workflow Executor executes the active workflow by carrying out each approved step in the defined execution plan while maintaining consistency, validation, and traceability.

## Responsibilities

- Load the active workflow.
- Execute workflow steps in order.
- Respect task dependencies.
- Trigger validation checkpoints.
- Record execution progress.
- Report workflow completion.
- Escalate failures to the Rollback Manager when required.

## Workflow Execution Process

1. Receive the execution plan.
2. Load the active workflow.
3. Verify prerequisites.
4. Execute the current step.
5. Update progress.
6. Run checkpoint validation.
7. Continue until the workflow completes.
8. Return execution status to the Execution Engine.

## Workflow Step Model

| Field | Description |
|---|---|
| Step ID | Unique workflow step identifier |
| Name | Step name |
| Description | Action to perform |
| Dependencies | Required previous steps |
| Validation | Validation required after completion |
| Status | Pending / Running / Passed / Failed / Skipped |

## Execution Principles

- Execute one validated step at a time.
- Never skip mandatory workflow steps.
- Stop immediately on critical failures.
- Resume from the last validated checkpoint when recovering.
- Maintain a complete execution history.

## Rule

Every workflow step must either complete successfully, fail with a documented reason, or be explicitly skipped with an approved justification. No step may be silently ignored.
