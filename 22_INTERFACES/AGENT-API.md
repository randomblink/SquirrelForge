# SquirrelForge Agent API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `16_AGENTS/AGENT-MANAGER.md`, `14_ENGINE/TASK-ROUTER.md`, `17_COORDINATION/HANDOFF-PROTOCOL.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `16_AGENTS`, `14_ENGINE`, `17_COORDINATION`
Last Updated: 2026-07-07

## Purpose

The Agent API owns the versioned interface contract for agent-facing operations: the request and response schemas, typed errors, receipts, and correlation references used to submit assignment, handoff, and cancellation requests and to query assignment state.

The Agent API exposes requests and responses. It does not own the decisions or state changes behind them. It does not select the agent or task owner (owned by `14_ENGINE/TASK-ROUTER.md`, reached via `16_AGENTS/AGENT-MANAGER.md`'s eligibility checks), perform capability matching (owned by `16_AGENTS/AGENT-MANAGER.md` and `14_ENGINE/TASK-ROUTER.md`), authorize an assignment, handoff, or cancellation (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md` — every call must already carry a valid permission reference from that authority), own assignment lifecycle state (owned by `14_ENGINE/TASK-ROUTER.md`'s routing states and `14_ENGINE/STATE-MANAGER.md`), execute handoff mechanics (owned by `17_COORDINATION/HANDOFF-PROTOCOL.md`), mutate authoritative task state directly (owned by `14_ENGINE/STATE-MANAGER.md`), define permission policy (owned by `21_CONFIGURATION/PERMISSIONS.md`), or perform task execution (owned by `20_EXECUTION`).

---

## Responsibilities

- Define the request and response schema for submitting an already-authorized assignment request.
- Define the request and response schema for querying assignment state by reference.
- Define the request and response schema for submitting an authorized handoff request or handoff package.
- Define the request and response schema for submitting a cancellation request.
- Return typed responses, typed errors, receipts, and correlation references for every call.
- Require identity, a permission reference, and a correlation ID on every call.
- Version the contract; breaking changes require a major version and migration path.

---

## Contract Operations

| Operation | Signature | Semantics |
|---|---|---|
| Submit assignment request | `assign(task, contextRef, acceptanceCriteria, identity, permissionRef, correlationId) → assignmentRequestId` | Submits an already-authorized request that a task be assigned. Does not select the owner or perform capability matching — the request is routed to `16_AGENTS/AGENT-MANAGER.md`, which hands the routing decision to `14_ENGINE/TASK-ROUTER.md`. `permissionRef` must reference a valid authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`; the API does not itself decide authorization. |
| Query assignment state | `status(assignmentRequestId) → state` | Returns the current assignment state as tracked by `14_ENGINE/TASK-ROUTER.md`'s routing states and `14_ENGINE/STATE-MANAGER.md`. Read-only; never mutates state. |
| Submit handoff request | `handoff(assignmentRequestId, target, package, identity, permissionRef, correlationId) → receipt` | Submits an already-authorized handoff request and package for a given assignment. Does not execute handoff mechanics — the request is routed to `17_COORDINATION/HANDOFF-PROTOCOL.md`. Returns a receipt correlating to the handoff record `17_COORDINATION/HANDOFF-PROTOCOL.md` produces. |
| Submit cancellation request | `cancel(assignmentRequestId, reason, identity, permissionRef, correlationId) → result` | Submits a request that an assignment be cancelled. Does not decide whether cancellation is permitted or perform it — that determination and the resulting `CANCELLED` state transition belong to `14_ENGINE/TASK-ROUTER.md` under lifecycle or governance control. Returns a result acknowledging the request was received and correlated, not that cancellation occurred. |

---

## Request and Response Requirements

Every request must include:

- Actor identity
- A permission reference to a valid authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`
- A correlation ID

Every response must include:

- A typed result or typed error
- The correlation ID from the originating request
- A receipt or reference identifier when the operation produced one

Typed errors must distinguish, at minimum: invalid schema, missing or invalid authorization, unknown assignment reference, and downstream rejection (surfaced from the owning component, not reinterpreted by the API).

---

## Permission Boundary

The Agent API may define and validate request/response schemas, accept already-authorized requests, and return typed responses, errors, receipts, and correlation references.

It must not select agents or task owners, perform capability matching, authorize requests, own assignment lifecycle state, execute handoff mechanics, mutate authoritative task state, define permission policy, or execute tasks — those remain owned by `16_AGENTS/AGENT-MANAGER.md`, `14_ENGINE/TASK-ROUTER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`, `14_ENGINE/STATE-MANAGER.md`, `17_COORDINATION/HANDOFF-PROTOCOL.md`, `21_CONFIGURATION/PERMISSIONS.md`, and `20_EXECUTION` respectively.

---

## Domain Rule

The Agent API contract applies identically regardless of domain; domain-specific content is carried in the task, context, and package payloads it transports, not interpreted by the API itself.

---

## Rule

> Every Agent API call must carry a valid permission reference and correlation ID and must be treated as a request, not a decision; the API returns what the owning component decided, it does not decide.
