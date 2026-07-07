# SquirrelForge Engine API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `00_CORE/SYSTEM-ORCHESTRATOR.md`, `14_ENGINE/STATE-MANAGER.md`, `20_EXECUTION/EXECUTION-ENGINE.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `26_INTEGRATIONS`, `22_INTERFACES`
Last Updated: 2026-07-07

## Purpose

The Engine API owns the versioned external contract for requests entering or querying the Engine: submitting a request, querying execution or run information by reference, providing requested external input, submitting cancellation requests, and retrieving available result or report references.

The Engine API exposes requests and responses. It does not own the decisions or state behind them. It does not create execution state authoritatively (execution and correlation identifiers are created by `00_CORE/SYSTEM-ORCHESTRATOR.md` as it routes the validated request into `14_ENGINE`), own run state (owned by `14_ENGINE/STATE-MANAGER.md` and `20_EXECUTION/EXECUTION-ENGINE.md`'s execution status), decide cancellation (owned by `20_EXECUTION/EXECUTION-ENGINE.md` under lifecycle or governance control), execute workflows (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md`), produce execution reports (owned by `20_EXECUTION/EXECUTION-REPORTER.md`), validate submitted work (owned by `14_ENGINE/VALIDATION.md`), or authorize requests (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md` — every call must already carry a valid permission reference). It must not bypass `00_CORE/SYSTEM-ORCHESTRATOR.md`'s routing, `20_EXECUTION/EXECUTION-ENGINE.md`'s execution authority, `14_ENGINE/STATE-MANAGER.md`'s state authority, or `24_SECURITY`'s security controls.

---

## Responsibilities

- Define the request and response schema for submitting an engine request.
- Define the request and response schema for querying execution or run information by reference.
- Define the request and response schema for providing requested external input.
- Define the request and response schema for submitting a cancellation request.
- Define the request and response schema for retrieving available result or report references.
- Support idempotency keys on requests that create or mutate state.
- Return typed responses, typed errors, and correlation references for every call.
- Version the contract; breaking changes require a major version and migration path.

---

## Contract Operations

| Operation | Signature | Semantics |
|---|---|---|
| Submit engine request | `submit(request, projectRef, identity, permissionRef, idempotencyKey, correlationId) → executionRef` | Submits a request for engine processing. Does not create execution state itself — `00_CORE/SYSTEM-ORCHESTRATOR.md` creates the execution identifier and routes the validated request into `14_ENGINE`. `permissionRef` must reference a valid authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`. Supplying the same `idempotencyKey` twice returns the original `executionRef` rather than creating a duplicate execution. |
| Query execution information | `inspect(executionRef) → state` | Returns the current run state as tracked by `14_ENGINE/STATE-MANAGER.md` and `20_EXECUTION/EXECUTION-ENGINE.md`'s execution status. Read-only; never mutates state. |
| Provide external input | `provideInput(executionRef, input, identity, permissionRef, correlationId) → receipt` | Submits externally requested input (for example, a clarification) for a pending execution. Does not decide whether input is required or acceptable — that determination belongs to whichever component requested it, recorded through `14_ENGINE/STATE-MANAGER.md`. Returns a receipt correlating to that record. |
| Submit cancellation request | `cancel(executionRef, reason, identity, permissionRef, correlationId) → result` | Submits a request that an execution be cancelled. Does not decide whether cancellation is permitted or perform it — that determination belongs to `20_EXECUTION/EXECUTION-ENGINE.md` under lifecycle or governance control. Returns a result acknowledging the request was received and correlated, not that cancellation occurred. |
| Retrieve result references | `result(executionRef) → reportRefs` | Returns available result and report references. Does not produce the report itself — reports are produced by `20_EXECUTION/EXECUTION-REPORTER.md` from result sets assembled by `20_EXECUTION/RESULT-COLLECTOR.md`. Returns an empty or partial reference set if the execution has not yet reached a terminal state. |

---

## Request and Response Requirements

Every request must include:

- Actor identity
- A permission reference to a valid authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`
- A correlation ID

State-creating or state-mutating requests must additionally support an idempotency key.

Every response must include:

- A typed result or typed error
- The correlation ID from the originating request
- A reference identifier (execution reference, receipt, or result reference) when the operation produced one

Typed errors must distinguish, at minimum: invalid schema, missing or invalid authorization, unknown execution reference, and downstream rejection (surfaced from the owning component, not reinterpreted by the API).

---

## Permission Boundary

The Engine API may define and validate request/response schemas, accept requests carrying a valid permission reference, and return typed responses, errors, references, and receipts.

It must not create execution state authoritatively, own run state, decide cancellation, execute workflows, produce execution reports, validate submitted work, authorize requests, or bypass `00_CORE/SYSTEM-ORCHESTRATOR.md`, `20_EXECUTION/EXECUTION-ENGINE.md`, `14_ENGINE/STATE-MANAGER.md`, or `24_SECURITY` — those remain owned by their respective components.

---

## Domain Rule

The Engine API contract applies identically regardless of domain; domain-specific content is carried in the request and input payloads it transports, not interpreted by the API itself.

---

## Rule

> Every Engine API call must carry a valid permission reference and correlation ID and must be treated as a request, not a decision; the API returns what the owning component decided, it does not decide, execute, validate, or authorize on its own authority.
