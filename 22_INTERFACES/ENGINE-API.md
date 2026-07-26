# SquirrelForge Engine API

Version: 1.5.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `00_CORE/SYSTEM-ORCHESTRATOR.md`, `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/VALIDATION.md`, `20_EXECUTION/EXECUTION-ENGINE.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `26_INTEGRATIONS`, `22_INTERFACES`
Last Updated: 2026-07-26

## Purpose

The Engine API owns the versioned external contract for requests entering or querying the Engine: submitting a request, querying execution or run information by reference, providing requested external input, submitting cancellation requests, and retrieving available result or report references.

The Engine API exposes requests and responses. It does not own the decisions or state behind them. It does not create execution state authoritatively (execution and correlation identifiers are created by `00_CORE/SYSTEM-ORCHESTRATOR.md` as it routes the validated request into `14_ENGINE`), own run state (owned by `14_ENGINE/STATE-MANAGER.md` and `20_EXECUTION/EXECUTION-ENGINE.md`'s execution status), decide cancellation (owned by `20_EXECUTION/EXECUTION-ENGINE.md` under lifecycle or governance control), execute workflows (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md`), produce execution reports (owned by `20_EXECUTION/EXECUTION-REPORTER.md`), validate submitted work (owned by `14_ENGINE/VALIDATION.md`), or authorize requests (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md` — every call must already carry a valid permission reference). It must not bypass `00_CORE/SYSTEM-ORCHESTRATOR.md`'s routing, `20_EXECUTION/EXECUTION-ENGINE.md`'s execution authority, `14_ENGINE/STATE-MANAGER.md`'s state authority, or `24_SECURITY`'s security controls.

---

## Responsibilities

- Define the request and response schema for submitting an engine request.
- Define the request and response schema for querying execution or run information by reference.
- Define the request and response schema for providing requested external input.
- Define the request and response schema for submitting a cancellation request.
- Define the request and response schema for retrieving available result, validation-record, or report references.
- Support idempotency keys on requests that create or mutate state.
- Return typed responses, typed errors, and correlation references for every call.
- Version the contract; breaking changes require a major version and migration path.

---

## Contract Operations

| Operation | Signature | Semantics |
|---|---|---|
| Submit engine request | `submit(request, projectRef, identity, permissionRef, idempotencyKey, correlationId) → executionRef` | Submits a request for engine processing. Does not create execution state itself — `00_CORE/SYSTEM-ORCHESTRATOR.md` creates the execution identifier and routes the validated request into `14_ENGINE`. `permissionRef` must reference a valid authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`. Supplying the same `idempotencyKey` twice returns the original `executionRef` rather than creating a duplicate execution. |
| Query execution information | `inspect(executionRef) → state` | Returns the current run state, including the validation-record reference and validation decision when available, as tracked by `14_ENGINE/STATE-MANAGER.md` and `20_EXECUTION/EXECUTION-ENGINE.md`'s execution status. Read-only; never mutates state. |
| Provide external input | `provideInput(executionRef, input, identity, permissionRef, correlationId) → receipt` | Submits externally requested input (for example, a clarification) for a pending execution. Does not decide whether input is required or acceptable — that determination belongs to whichever component requested it, recorded through `14_ENGINE/STATE-MANAGER.md`. Returns a receipt correlating to that record. |
| Submit cancellation request | `cancel(executionRef, reason, identity, permissionRef, correlationId) → result` | Submits a request that an execution be cancelled. Does not decide whether cancellation is permitted or perform it — that determination belongs to `20_EXECUTION/EXECUTION-ENGINE.md` under lifecycle or governance control. Returns a result acknowledging the request was received and correlated, not that cancellation occurred. |
| Retrieve result references | `result(executionRef) → resultEnvelope` | Returns available result-set, validation-record, and execution-report references plus the authoritative validation decision when available. Does not produce or reinterpret those records. Returns an empty or partial envelope while execution or validation remains non-terminal. |

---

## Result Envelope

The result response must preserve authoritative references rather than copying full underlying records.

| Field | Requirement |
|---|---|
| `execution_ref` | Required execution reference. |
| `state_ref` | Reference to the authoritative State Manager record. |
| `result_set_refs` | Zero or more references from `20_EXECUTION/RESULT-COLLECTOR.md`. |
| `validation_record_ref` | Standardized validation-object reference from `14_ENGINE/VALIDATION.md`, when available. |
| `validation_decision` | Decision copied from the validation record without reinterpretation, when available. |
| `report_refs` | Zero or more report references from `20_EXECUTION/EXECUTION-REPORTER.md`. |
| `terminal` | Whether the authoritative state is terminal. |
| `limitations` | Externally reportable limitations when the validation decision permits output. |
| `next_action` | Next action from the authoritative state or validation record. |

The envelope must not report successful completion unless the validation decision is `ACCEPTED` or policy-permitted `ACCEPTED_WITH_LIMITATIONS`.

---

## Reference HTTP Binding

The reference runtime exposes the contract through these method and path mappings:

| Operation | Method and Path |
|---|---|
| Submit engine request | `POST /v1/engine/requests` |
| Query execution information | `GET /v1/engine/executions/{executionRef}` |
| Provide external input | `POST /v1/engine/executions/{executionRef}/input` |
| Submit cancellation request | `POST /v1/engine/executions/{executionRef}/cancellation` |
| Retrieve result references | `GET /v1/engine/executions/{executionRef}/result` |

Authentication sessions are issued separately through:

| Operation | Method and Path |
|---|---|
| Verify API key and issue session | `POST /v1/authentication/sessions` |
| Rotate an active session token | `POST /v1/authentication/sessions/refresh` |

The session request carries identity and API-key credentials in its protected JSON body plus correlation and source references in headers. Human identities also carry an MFA proof for verification by the configured MFA owner. The API key and MFA proof must not appear in logs, errors, response bodies, or downstream Engine requests. A successful response returns one opaque, short-lived access token.

The refresh request carries the current bearer token and a correlation reference. Success returns a replacement token and revokes the presented session before responding. Refresh is denied for missing, revoked, expired, or inactive-identity sessions.

Credential lifecycle administration is exposed separately from session issuance:

| Operation | Method and Path | Required Permission |
|---|---|---|
| Rotate API key | `POST /v1/security/api-keys/{secretRef}/rotation` | `security.credentials.rotate` for `secret:{secretRef}` |
| Revoke API key | `POST /v1/security/api-keys/{secretRef}/revocation` | `security.credentials.revoke` for `secret:{secretRef}` |
| Revoke session | `POST /v1/security/sessions/{sessionRef}/revocation` | `security.sessions.revoke` for `session:{sessionRef}` |

Every administration call requires an active bearer session, matching claimed identity, permission reference, and correlation ID. Success returns authentication, authorization, and security-event references. Responses never return an API key or token value; rotation accepts the replacement key only in the protected request body.

Operational dependency readiness is exposed through `GET /v1/health/providers`. It returns only a provider reference, boolean health and readiness, circuit state, aggregate counters, last-event timestamp, and safe rationale. It uses `Cache-Control: no-store`, returns `200` when ready and `503` otherwise, and never includes endpoint URLs, credentials, request payloads, remote response bodies, or internal exception text.

Every operation carries:

- `Authorization: Bearer {accessToken}`,
- `X-SquirrelForge-Identity-Ref`,
- `X-SquirrelForge-Permission-Ref`,
- and `X-Correlation-ID`.

State-creating requests additionally carry `Idempotency-Key`. Request and response bodies use JSON. Error responses retain typed error fields and an HTTP status without allowing transport status to replace the authoritative Engine decision.

Every authentication evaluation produces `X-SquirrelForge-Authentication-Ref`. The claimed identity header must match the verified session identity and is never accepted as identity proof on its own.

Every authorization-evaluated protected response carries `X-SquirrelForge-Authorization-Ref`, including denied responses when a decision record was produced. Authorized-with-restrictions responses also carry the externally transportable restriction summary. These headers reference persisted decisions; they are not themselves credentials or grants.

`src/Integration/Http/EngineApiServer.php` is the framework-neutral request handler. `public/engine-api.php` mounts it as a PHP HTTP entry point.

`src/Engine/InMemoryEngineRuntime.php` remains a deterministic test runtime. `src/Engine/SqliteEngineRuntime.php` provides the first persistent reference runtime with schema migration, durable execution/input/validation/result records, WAL mode, and a unique idempotency constraint. SQLite is appropriate for local and single-node reference deployment; clustered production execution still requires a governed shared state owner and its recovery model.

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

Responses that expose terminal results must additionally include the authoritative state reference and, when validation was required, the validation-record reference and decision.

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
