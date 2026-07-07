# SquirrelForge Workflow API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `14_ENGINE/WORKFLOW-SELECTOR.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `14_ENGINE/STATE-MANAGER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `14_ENGINE`, `20_EXECUTION`
Last Updated: 2026-07-07

## Purpose

The Workflow API owns the versioned interface contract for workflow-facing operations: selecting a workflow, initializing a run, requesting the next actions, reporting phase completion, and terminating a run.

The Workflow API exposes requests and responses. It does not own the decisions or state behind them. It does not select the workflow (owned by `14_ENGINE/WORKFLOW-SELECTOR.md`), sequence or execute workflow steps (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md`), own run state (owned by `14_ENGINE/STATE-MANAGER.md`), decide phase completion (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md` in coordination with `20_EXECUTION/CHECKPOINT-MANAGER.md`), decide termination (owned by `20_EXECUTION/EXECUTION-ENGINE.md` under lifecycle or governance control), or authorize requests (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md` — every call must already carry a valid permission reference).

---

## Responsibilities

- Define the request and response schema for selecting a workflow for a request.
- Define the request and response schema for initializing a workflow run.
- Define the request and response schema for requesting the next actions in an active run.
- Define the request and response schema for reporting phase completion evidence.
- Define the request and response schema for terminating a run.
- Return typed responses, typed errors, and correlation references for every call.
- Require identity, a permission reference, and a correlation ID on every call.
- Version the contract; breaking changes require a major version and migration path.

---

## Contract Operations

| Operation | Signature | Semantics |
|---|---|---|
| Select workflow | `select(request, identity, permissionRef, correlationId) → workflowRef` | Submits a request for workflow selection. Does not select the workflow itself — the request is routed to `14_ENGINE/WORKFLOW-SELECTOR.md`, which returns the selection record referenced by `workflowRef`. |
| Initialize run | `initialize(workflowRef, goal, identity, permissionRef, idempotencyKey, correlationId) → runRef` | Submits a request to start a run for the selected workflow. Does not create run state itself — `14_ENGINE/STATE-MANAGER.md` owns the resulting run record. Supplying the same `idempotencyKey` twice returns the original `runRef` rather than creating a duplicate run. |
| Request next actions | `next(runRef, state) → actions` | Requests the next actions for an active run at the given reported state. Does not decide or sequence the actions itself — they are supplied by `20_EXECUTION/WORKFLOW-EXECUTOR.md` per the plan it is already executing. |
| Report phase completion | `completePhase(runRef, evidence) → phaseResult` | Submits completion evidence for the current phase. Does not decide whether the phase is complete — that determination belongs to `20_EXECUTION/WORKFLOW-EXECUTOR.md` in coordination with `20_EXECUTION/CHECKPOINT-MANAGER.md`. Phase order and exit criteria are immutable within a run version. |
| Terminate run | `terminate(runRef, reason, identity, permissionRef, correlationId) → result` | Submits a request that a run be terminated. Does not decide whether termination is permitted or perform it — that determination belongs to `20_EXECUTION/EXECUTION-ENGINE.md` under lifecycle or governance control. Returns a result acknowledging the request was received and correlated, not that termination occurred. |

---

## Request and Response Requirements

Every request must include:

- Actor identity
- A permission reference to a valid authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`
- A correlation ID

Requests that create or mutate run state must additionally support an idempotency key.

Every response must include:

- A typed result or typed error
- The correlation ID from the originating request
- A reference identifier (workflow reference, run reference, or phase result) when the operation produced one

Typed errors must distinguish, at minimum: invalid schema, missing or invalid authorization, unknown workflow or run reference, and downstream rejection (surfaced from the owning component, not reinterpreted by the API).

---

## Permission Boundary

The Workflow API may define and validate request/response schemas, accept requests carrying a valid permission reference, and return typed responses, errors, references, and results.

It must not select workflows, sequence or execute steps, own run state, decide phase completion, decide termination, or authorize requests — those remain owned by `14_ENGINE/WORKFLOW-SELECTOR.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `14_ENGINE/STATE-MANAGER.md`, `20_EXECUTION/CHECKPOINT-MANAGER.md`, `20_EXECUTION/EXECUTION-ENGINE.md`, and `24_SECURITY/AUTHORIZATION-MANAGER.md` respectively.

---

## Domain Rule

The Workflow API contract applies identically regardless of domain; domain-specific content is carried in the request, goal, and evidence payloads it transports, not interpreted by the API itself.

---

## Rule

> Phase order and exit criteria are immutable within a run version. Every Workflow API call must carry a valid permission reference and correlation ID and must be treated as a request, not a decision; the API returns what the owning component decided, it does not decide.
