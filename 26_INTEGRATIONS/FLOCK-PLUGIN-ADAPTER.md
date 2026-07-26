# SquirrelForge Flock Plugin Adapter

Version: 1.0.0
Status: Draft
Owner: Integrations Maintainers
Depends On: `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/VALIDATION.md`, `22_INTERFACES/ENGINE-API.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: Flock plugin host, Integration Manager
Last Updated: 2026-07-26

## Purpose

The Flock Plugin Adapter defines how a SquirrelForge plugin hosted by Flock translates Flock-facing requests into the versioned Engine API contract and translates Engine state, result envelopes, validation decisions, reports, and typed errors back into Flock-facing responses.

The adapter owns host-protocol translation only. It does not interpret user intent, select workflows, execute tasks, validate results, authorize requests, own state, or change an Engine decision.

---

## Boundary

The Flock Plugin Adapter owns:

- Flock plugin registration metadata,
- Flock request and response schema normalization,
- Flock operation-to-Engine API mapping,
- propagation of identity, permission, correlation, conversation, trace, and idempotency references,
- conversion of Engine states and validation decisions into Flock response states,
- transport-safe error normalization,
- cancellation and external-input request forwarding,
- and Flock-facing progress and terminal-response assembly from authoritative references.

It does not own:

- user-intent interpretation or goal creation,
- workflow selection, task routing, or execution planning,
- execution, retry, repair, rollback, or recovery decisions,
- authentication, authorization, or credential lifecycle,
- Engine, workflow, task, or validation state,
- result collection, validation, or reporting decisions,
- storage, memory, audit, or observability infrastructure,
- or Flock platform behavior outside the documented plugin contract.

Those responsibilities remain with their authoritative SquirrelForge or Flock owners.

---

## Connector Registration

The adapter must be registered through `26_INTEGRATIONS/CONNECTOR-MANAGER.md` before activation.

The connector record should declare:

| Field | Requirement |
|---|---|
| Connector ID | Stable identifier for the SquirrelForge-for-Flock adapter. |
| Connector Version | Adapter contract version. |
| Host | Flock plugin runtime and supported host-version range. |
| Protocol Reference | Supported Flock plugin protocol or SDK reference. |
| Engine API Version | Compatible `22_INTERFACES/ENGINE-API.md` version range. |
| Capabilities | Submit, inspect, provide input, cancel, and retrieve result. |
| Configuration Reference | Runtime configuration reference; never embedded secrets. |
| Credential Reference | Approved credential reference when the host connection requires one. |
| Governance Reference | Approval and restriction reference from integration governance. |
| Availability Reference | Current host and Engine availability evidence. |
| Lifecycle Status | Registered, ready, active, degraded, suspended, or retired. |

Registration proves only that the adapter definition and required references exist. It does not prove Engine readiness or authorize a request.

---

## Flock Request Envelope

Every accepted Flock request must be normalized to this logical shape before calling the Engine API:

```json
{
  "adapter_version": "1.0.0",
  "operation": "submit|inspect|provide_input|cancel|result",
  "request_id": "flock_request_ref",
  "conversation_id": "flock_conversation_ref",
  "actor": {
    "identity_ref": "authorized_actor_ref",
    "tenant_ref": "tenant_or_workspace_ref"
  },
  "permission_ref": "authorization_ref",
  "correlation_id": "correlation_ref",
  "trace_id": "trace_ref",
  "idempotency_key": "required_for_mutating_operations",
  "execution_ref": "required_after_submission",
  "project_ref": "target_project_ref",
  "payload": {},
  "client_context": {
    "locale": "optional_locale",
    "timezone": "optional_timezone",
    "capability_refs": []
  }
}
```

### Request Invariants

- `adapter_version`, `operation`, `request_id`, `actor.identity_ref`, `permission_ref`, and `correlation_id` are required.
- `submit` requires `project_ref`, request content in `payload`, and an idempotency key.
- `provide_input` and `cancel` require `execution_ref`, a payload, and an idempotency key.
- `inspect` and `result` require `execution_ref` and are read-only.
- Parent correlation, trace, conversation, and execution identifiers must be propagated, not silently regenerated.
- Raw credentials, provider secrets, private keys, and unrestricted host session data must not enter the payload.
- Host context may be forwarded only when it is required, permitted, and classified for the target Engine request.
- Missing or invalid authorization references must fail closed before Engine submission.

---

## Operation Mapping

| Flock Operation | Engine API Operation | Adapter Behavior |
|---|---|---|
| `submit` | `submit(request, projectRef, identity, permissionRef, idempotencyKey, correlationId)` | Normalize the Flock request, preserve permitted host context, submit once, and return the execution reference. |
| `inspect` | `inspect(executionRef)` | Return the current authoritative state and validation reference when available; do not infer progress. |
| `provide_input` | `provideInput(executionRef, input, identity, permissionRef, correlationId)` | Forward only input requested for that execution and return the correlated receipt. |
| `cancel` | `cancel(executionRef, reason, identity, permissionRef, correlationId)` | Forward the cancellation request and report receipt separately from confirmed cancellation state. |
| `result` | `result(executionRef)` | Translate the Engine result envelope into a Flock response without changing its validation decision, limitations, or next action. |

The adapter must not combine mutating operations in a way that bypasses individual authorization, idempotency, or audit requirements.

---

## HTTP Engine Binding

The reference runtime implements `EngineClientInterface` through `HttpEngineClient` and `NativeHttpTransport`.

Before the first Engine call, `HttpAuthenticationClient` exchanges an approved API key for a short-lived bearer session through `POST /v1/authentication/sessions`. Flock transport configuration supplies the API key; the adapter request envelope does not carry it.

| Engine Operation | Method and Path |
|---|---|
| Submit | `POST /v1/engine/requests` |
| Inspect | `GET /v1/engine/executions/{executionRef}` |
| Provide input | `POST /v1/engine/executions/{executionRef}/input` |
| Cancel | `POST /v1/engine/executions/{executionRef}/cancellation` |
| Result | `GET /v1/engine/executions/{executionRef}/result` |

Mutating requests carry the applicable:

- `X-SquirrelForge-Identity-Ref`,
- `X-SquirrelForge-Permission-Ref`,
- `X-Correlation-ID`,
- and `Idempotency-Key` headers.

Payloads and successful responses use JSON. Non-success responses preserve typed Engine errors when supplied; otherwise the client normalizes status codes into transport-safe error categories. Redirect following is disabled by the reference transport so identity and permission references are not silently forwarded to another endpoint.

The reference runtime includes a framework-neutral `EngineApiServer` request handler, a PHP HTTP entry point, an `InProcessHttpTransport` for deterministic tests, and a SQLite runtime for persistent local execution. A clustered production deployment must replace SQLite with its governed shared execution and state owners.

---

## Flock Response Envelope

The adapter returns this logical shape:

```json
{
  "adapter_version": "1.0.0",
  "request_id": "flock_request_ref",
  "correlation_id": "correlation_ref",
  "trace_id": "trace_ref",
  "execution_ref": "execution_ref_or_null",
  "status": "ACCEPTED|RUNNING|WAITING_FOR_INPUT|REPAIRING|BLOCKED|RECOVERY_REQUIRED|COMPLETE|COMPLETE_WITH_LIMITATIONS|REJECTED|FAILED|CANCELLED",
  "message": "concise_host_safe_status",
  "state_ref": "authoritative_state_ref_or_null",
  "authentication_ref": "authentication_attempt_ref_or_null",
  "authorization_ref": "authorization_decision_ref_or_null",
  "result_refs": [],
  "validation_record_ref": "validation_ref_or_null",
  "validation_decision": "engine_decision_or_null",
  "report_refs": [],
  "limitations": [],
  "next_action": "authoritative_next_action_or_null",
  "error": null
}
```

The response may include user-facing content only when its source is an authoritative result or report reference and the content is permitted for the requesting actor and host.

---

## State and Decision Mapping

| Engine State or Decision | Flock Status | Rule |
|---|---|---|
| Request accepted; execution reference created | `ACCEPTED` | Indicates intake only, not successful completion. |
| Pending, running, or validation pending | `RUNNING` | Preserve authoritative state reference and avoid invented percentages. |
| Clarification required or task waiting on external input | `WAITING_FOR_INPUT` | Include only the authoritative input request and resume condition. |
| `REPAIR_REQUIRED` | `REPAIRING` | Report the repair state and next action; do not expose internal-only reasoning. |
| `BLOCKED` | `BLOCKED` | Preserve blocker ownership and safe next action. |
| `RECOVERY_REQUIRED` | `RECOVERY_REQUIRED` | Do not permit dependent mutating operations until recovery resolves. |
| `ACCEPTED` | `COMPLETE` | Successful completion may be reported. |
| `ACCEPTED_WITH_LIMITATIONS` | `COMPLETE_WITH_LIMITATIONS` | Surface all externally reportable limitations and residual risks. |
| `REJECTED` | `REJECTED` | Do not relabel as a transport or generic execution success. |
| Failed terminal state | `FAILED` | Include typed error or report references when permitted. |
| Confirmed cancelled state | `CANCELLED` | A cancellation receipt alone is insufficient. |

The adapter must not report `COMPLETE` or `COMPLETE_WITH_LIMITATIONS` without the corresponding authoritative validation decision.

---

## Error Contract

The adapter must normalize errors without hiding their source category.

| Error Type | Meaning | Retry Rule |
|---|---|---|
| `INVALID_FLOCK_REQUEST` | Flock request envelope or required operation fields are invalid. | Correct the request; do not retry unchanged. |
| `UNAUTHORIZED` | Required identity or permission reference is missing, invalid, or denied. | Do not retry automatically. |
| `UNSUPPORTED_VERSION` | Flock adapter, host protocol, or Engine API version is incompatible. | Upgrade or use a compatible version. |
| `UNKNOWN_EXECUTION` | Engine API does not recognize the execution reference. | Verify the reference; do not create a replacement silently. |
| `ENGINE_REJECTED` | An authoritative downstream owner rejected the request or result. | Preserve the rejection and next action. |
| `TRANSPORT_UNAVAILABLE` | The adapter could not reach the registered Engine endpoint. | Report retryable status; execution owner decides retry. |
| `RATE_LIMITED` | Host or Engine transport limit prevented the call. | Honor the authoritative retry-after reference; adapter does not set policy. |
| `RESPONSE_SCHEMA_INVALID` | Engine response does not conform to the negotiated contract. | Stop and report contract evidence; do not guess missing fields. |
| `INTERNAL_ADAPTER_ERROR` | Adapter translation failed without a valid downstream response. | Preserve correlation evidence and route through failure handling. |

Every error response must include the request and correlation references, a typed error, a safe message, retryability as reported by the authoritative owner, and evidence or support references when available. Secrets and internal-only payloads must be redacted.

---

## Progress and Polling

The initial adapter version supports authoritative state retrieval through `inspect` and result retrieval through `result`.

Polling must:

- use configured host and Engine limits,
- stop at terminal state,
- preserve the same execution and correlation references,
- avoid converting elapsed time into invented progress,
- and expose `WAITING_FOR_INPUT`, `BLOCKED`, or `RECOVERY_REQUIRED` immediately.

If a future Flock host supports callbacks, events, or streaming, that transport may be added in a compatible contract version. It must carry the same identifiers and authoritative states and must not create a second lifecycle authority.

---

## Security and Data Handling

- Every Engine API call must carry an approved identity and permission reference.
- The HTTP binding must carry a short-lived bearer session; the claimed identity must match its verified identity.
- Access tokens must remain in transport configuration and must never enter Flock payloads, result envelopes, logs, or evidence records.
- The authentication client may refresh a valid session, but must replace the old token immediately because refresh revokes it.
- Human session creation must pass the host's MFA proof to the configured verifier; the adapter must not validate or persist that proof itself.
- Every evaluated response must preserve the Authentication Manager attempt reference when supplied.
- Every evaluated response must preserve the Authorization Manager decision reference when supplied.
- Host authentication is translated into references; the adapter does not make the authorization decision.
- Credentials and secrets remain in their owning security or runtime-configuration systems.
- Flock tenant or workspace boundaries must be preserved in request scope and result access.
- Result and report references must be checked for actor access before content is returned.
- Logs and events must use observability owners and must not contain raw prompts, secrets, credentials, or unapproved private content.
- Cancellation, external input, and other mutating operations require idempotency and audit correlation.
- The adapter must fail closed when identity, permission, tenant, version, or response integrity cannot be verified.
- Bootstrap provisioning is a local/test initialization facility and must never be enabled by a production Flock deployment.
- Credential rotation and revocation are administration-plane operations, not ordinary Flock workflow operations. A host integration may call them only with an active administrator session and a resource-scoped security permission reference.
- Production deployments inject implementations of `SecretsManagerInterface`, `MfaVerifierInterface`, and `SecurityEventSinkInterface`; the adapter must not depend on SQLite or a specific external provider.
- Each production provider must pass the shared conformance suite and report readiness through `ProviderReadinessInterface`; runtime startup fails closed when a local, test, placeholder, null, or unclassified provider is composed.
- The bundled production provider boundary uses an explicitly configured HTTPS JSON gateway. Its bearer token belongs to runtime configuration and must never enter a Flock request, response, trace, event, or error.
- Provider retries remain below the Flock adapter boundary. Flock receives one safe authentication failure after the bounded provider policy completes; it must not add a second credential retry loop or reinterpret an open circuit as successful authentication.

---

## Readiness and Compatibility

The adapter may become active only when:

- the connector is registered and active,
- the configured Flock host version is supported,
- the configured Engine API version is compatible,
- endpoint, configuration, permission, governance, and observability references are available,
- required request and response schema checks pass,
- and a round-trip smoke test confirms identifier and decision preservation.

Breaking request or response changes require a major adapter version and a migration path. Additive optional fields require a minor version. Clarifications with no contract effect require a patch version.

---

## Minimum End-to-End Contract Test

The first end-to-end workflow must demonstrate:

1. Flock submits one authorized request with a stable idempotency key.
2. The adapter produces one Engine execution reference.
3. Flock inspects the same execution without creating a duplicate run.
4. The Engine produces an Execution Result Set.
5. Validation emits a standardized validation record.
6. The State Manager applies the authoritative decision.
7. The adapter retrieves the result envelope.
8. The Flock response preserves correlation, execution, state, validation, result, and report references.
9. The Flock terminal status exactly matches the validation decision.
10. A repeated submit with the same idempotency key returns the original execution reference.

Tests must also cover invalid schema, denied permission, unknown execution, cancellation receipt versus confirmed cancellation, unavailable transport, rejected validation, limited acceptance, and response-schema mismatch.

---

## Rule

> The Flock Plugin Adapter translates protocols and preserves authoritative references and decisions. It must never become a second Engine, authorization owner, validation owner, state manager, retry authority, or source of completion claims.
