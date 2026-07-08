# SquirrelForge API Gateway

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/AUTHENTICATION.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, Integration components that require approved API handoff
Last Updated: 2026-07-08

## Purpose

The API Gateway owns API ingress and egress protocol handling for approved API handoffs.

It normalizes API request shape, maps protocol fields such as headers, query parameters, body payloads, methods, and endpoint references, applies configured API transport throttling, performs transport-level schema and protocol checks, normalizes API responses, and returns API transport status and evidence references.

It does not own integration routing, connector registry records, authentication or authorization decisions, credential lifecycle, governance decisions, rate-limit policy definition, business validation, security validation, retry or recovery execution, monitoring, logging, audit, storage, observability infrastructure, or authoritative workflow state.

---

## Responsibilities

- Receive approved API handoff requests from `INTEGRATION-MANAGER.md` or approved Integration components.
- Normalize outbound API method, header, query, body, timeout, and endpoint-reference fields.
- Confirm required API transport references are present.
- Consume credential/authentication/authorization status references from owning components.
- Apply configured API transport throttling using provided limit references.
- Perform transport-level request shape and schema conformance checks.
- Send approved API requests to approved endpoint references.
- Normalize transport responses, response headers, response bodies, status codes, and API transport errors.
- Return API response, error, status, rate-limit, and evidence references to the caller.
- Emit API transport event references through observability owners.

---

## Boundary

`API-GATEWAY.md` owns:

- API ingress and egress protocol handling,
- API transport request normalization,
- header, query, method, body, timeout, and endpoint-reference mapping,
- configured API transport throttling,
- transport-level schema and protocol conformance checks,
- API response transport normalization,
- API transport status and error references,
- and API transport evidence references.

`API-GATEWAY.md` does not own:

- integration routing or business routing decisions (`INTEGRATION-MANAGER.md`),
- connector registry records or connector lifecycle state (`CONNECTOR-MANAGER.md`),
- authentication, authorization, or credential lifecycle (`24_SECURITY`, `26_INTEGRATIONS/AUTHENTICATION.md`, and `28_RUNTIME-CONFIG`),
- integration governance decisions (`INTEGRATION-GOVERNANCE.md`),
- rate-limit policy definition or quota policy ownership (`21_CONFIGURATION` and governance owners),
- business validation, output validation, or task-completion validation (`14_ENGINE/VALIDATION.md` and domain owners),
- security validation or security policy enforcement (`24_SECURITY`),
- retry, recovery, rollback, or workflow failure handling (`17_COORDINATION` and `20_EXECUTION`),
- logs, metrics, traces, dashboards, alerts, audit infrastructure, or observability pipelines (`27_OBSERVABILITY`),
- platform storage or persistence infrastructure (`37_STORAGE`),
- or authoritative workflow/task lifecycle state (`14_ENGINE/STATE-MANAGER.md`).

---

## API Handoff Flow

```text
Integration Manager or approved Integration component supplies an approved API handoff
   ↓
API Gateway checks required transport references and normalizes request shape
   ↓
API Gateway consumes credential/auth status and configured rate-limit references
   ↓
API Gateway sends the API request to the approved endpoint reference
   ↓
External API returns response, error, rate-limit, timeout, or availability status
   ↓
API Gateway normalizes transport result and emits observability event references
   ↓
Caller receives API transport result references and remains responsible for next decisions
```

---

## API Transport Statuses

| Status | Meaning |
|---|---|
| `Ready` | Required API transport references are present. |
| `Request Invalid` | Required request shape or transport fields are missing. |
| `Credential Blocked` | Required credential/auth status reference is missing or rejected by the owner. |
| `Rate Limited` | Configured API transport throttling blocked or delayed the request. |
| `Sent` | API request was sent to the approved endpoint reference. |
| `Response Received` | External API returned a response. |
| `Transport Failed` | API transport failed before a usable response was returned. |
| `Normalized` | API response or error was normalized and returned to the caller. |

These are API transport statuses only. They are not workflow state, validation state, recovery state, incident state, or storage state.

---

## Rules

1. API Gateway may process only approved API handoffs.
2. API Gateway must use references for endpoints, credentials, configuration, governance, and rate limits.
3. API Gateway must not store raw secrets in requests, responses, logs, or metadata.
4. API Gateway may perform transport-level protocol and schema checks only.
5. API Gateway may return retryable transport status, but retry and recovery decisions belong to execution and coordination owners.
6. API Gateway must emit observability event references through `27_OBSERVABILITY`; it must not maintain separate logging or audit infrastructure.
7. API Gateway must not mark business outcomes valid or integration tasks complete.
