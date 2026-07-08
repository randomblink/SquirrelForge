# SquirrelForge Integration Authentication

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `28_RUNTIME-CONFIG`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `26_INTEGRATIONS/API-GATEWAY.md`, Integration connector and provider components
Last Updated: 2026-07-08

## Purpose

Integration Authentication coordinates authentication handshakes with external systems for approved integration calls.

It consumes credential references, secret-reference status, and security decisions from their owning components, then coordinates provider-specific credential exchange, token refresh handoff, signing material use, or authentication-status reporting for Integration components.

It does not own platform identity, platform authentication, runtime authorization, credential storage, secret storage, raw credential lifecycle, security policy, business routing, integration execution, logging, audit, observability infrastructure, or task validation.

---

## Responsibilities

- Coordinate external-service authentication handshakes for approved integration components.
- Consume credential and secret references from `28_RUNTIME-CONFIG` and `21_CONFIGURATION`.
- Consume security and authorization status references from `24_SECURITY`, when required.
- Exchange approved credential references for external provider tokens or signed requests when the provider protocol requires it.
- Refresh external provider tokens only through approved references and owner-provided rules.
- Return external authentication status references to callers.
- Report authentication failures as integration-domain status references.
- Emit authentication event references through observability owners.

---

## Boundary

`AUTHENTICATION.md` owns:

- external integration authentication flow coordination,
- provider-specific credential-handshake mechanics,
- token exchange and token refresh handoff for external providers,
- request-signing coordination using approved references,
- and integration authentication status references.

`AUTHENTICATION.md` does not own:

- platform identity lifecycle or user/session authentication (`24_SECURITY/IDENTITY-MANAGER.md` and `24_SECURITY/AUTHENTICATION-MANAGER.md`),
- runtime authorization decisions (`24_SECURITY/AUTHORIZATION-MANAGER.md`),
- raw credential storage, secret storage, key storage, or key rotation (`28_RUNTIME-CONFIG` and `24_SECURITY/ENCRYPTION-MANAGER.md`),
- declarative credential configuration (`21_CONFIGURATION`),
- security-domain policy (`24_SECURITY/SECURITY-GOVERNANCE.md`),
- integration routing (`INTEGRATION-MANAGER.md`),
- connector/provider execution internals,
- retry, recovery, or rollback execution,
- logs, metrics, traces, dashboards, alerts, audit infrastructure, or observability pipelines (`27_OBSERVABILITY`),
- or validation of business outcomes and task completion.

---

## Authentication Flow

```text
Integration component requests external auth status or credential handshake
   ↓
Integration Authentication checks required credential and security references
   ↓
Owning config, secrets, and security components provide references or decisions
   ↓
Integration Authentication performs provider-specific handshake or signing coordination
   ↓
Caller receives external authentication status, token reference, or failure reference
```

Raw secrets must remain with the owning secrets/runtime-configuration component. Integration Authentication may use approved references; it must not persist or expose raw secret material.

---

## Authentication States

| Status | Meaning |
|---|---|
| `Ready` | Required references exist and external authentication can proceed. |
| `Pending` | External authentication handshake is in progress. |
| `Valid` | External provider accepted the authentication material or status. |
| `Expired` | External provider credential or token requires approved refresh. |
| `Invalid` | External provider rejected the authentication material. |
| `Revoked` | Credential or token is no longer authorized by its owning component or provider. |
| `Blocked` | Required security, authorization, configuration, or secret reference is missing or denied. |

These are external integration authentication states only. They are not platform identity, session, or authorization states.

---

## Rules

1. Integration components must use approved credential references rather than raw secrets.
2. Integration Authentication must not store, log, or expose raw credential material.
3. Platform authentication and runtime authorization decisions must come from `24_SECURITY`.
4. Credential storage and secret lifecycle decisions must come from runtime-configuration and security owners.
5. Integration Authentication may report authentication status, but it must not approve business access or mark work complete.
6. Authentication event references must be emitted through `27_OBSERVABILITY`.
