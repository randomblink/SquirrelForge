# SquirrelForge Workflow API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: Workflow Definitions
Used By: Engine and Execution
Last Updated: 2026-07-01

`select(request) → workflowRef`; `initialize(workflowRef, goal) → runId`; `next(runId, state) → actions`; `completePhase(runId, evidence) → phaseResult`; `terminate(runId, reason) → result`. Phase order and exit criteria are immutable within a run version.
