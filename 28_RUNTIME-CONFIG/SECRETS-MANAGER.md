# SquirrelForge Secrets Manager

Version: 1.7.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `24_SECURITY`, `24_SECURITY/ENCRYPTION-MANAGER.md`, `27_OBSERVABILITY/AUDIT-TRAIL.md`, `37_STORAGE`
Used By: Security, Integrations, Runtime Configuration, Execution, WordPress
Last Updated: 2026-07-26

## Purpose

The Secrets Manager owns secret lifecycle records, secret metadata, secret references, rotation status, revocation status, expiration status, and secure retrieval handoff references for confidential runtime material.

It manages secret records and references. It does not authenticate identities, authorize access, define security policy, perform cryptographic operations, own encryption standards, own key-generation decisions, execute incident response, or expose raw secrets in logs, metadata, or audit records.

---

## Responsibilities

- Maintain secret metadata and lifecycle records.
- Store or reference secret material through approved storage and security controls.
- Record secret type, owner, scope, expiration, rotation, revocation, and status metadata.
- Coordinate secret retrieval handoff only after receiving required security/authorization references.
- Record rotation and revocation status.
- Preserve secret-operation evidence references without exposing secret values.

---

## Boundary

`SECRETS-MANAGER.md` owns:

- secret lifecycle records,
- secret metadata,
- secret references,
- rotation and revocation status,
- expiration status,
- and secure retrieval handoff references.

`SECRETS-MANAGER.md` does not own:

- identity authentication (`24_SECURITY/AUTHENTICATION-MANAGER.md`),
- runtime authorization (`24_SECURITY/AUTHORIZATION-MANAGER.md`),
- security-domain policy (`24_SECURITY/SECURITY-GOVERNANCE.md`),
- cryptographic operations or encryption standards (`24_SECURITY/ENCRYPTION-MANAGER.md`),
- incident response or threat classification,
- general audit infrastructure (`27_OBSERVABILITY/AUDIT-TRAIL.md`),
- or storage infrastructure (`37_STORAGE`).

---

## Secret States

| State | Meaning |
|---|---|
| `Registered` | Secret metadata exists. |
| `Active` | Secret reference is available for approved retrieval handoff. |
| `Rotation Pending` | Secret is scheduled for rotation. |
| `Rotated` | Replacement secret reference is active. |
| `Suspended` | Secret reference is temporarily unavailable. |
| `Revoked` | Secret reference must not be used. |
| `Archived` | Secret metadata is retained for history only. |

---

## Reference Runtime

The PHP reference implementation defines `SecretsManagerInterface` as the injectable credential-provider boundary and `SqliteSecretsManager` as its local reference adapter. A production secret platform implements the same registration, verification, rotation, and revocation operations without changing Authentication or the administration API.

Every provider used in runtime composition must also expose `ProviderReadinessInterface`. `ProviderStartupValidator` refuses production startup unless Secrets Manager, MFA verification, and security-event persistence each identify themselves as production-ready. Missing readiness metadata fails closed. SQLite, static-proof, deny-only, and null providers identify themselves as local, test, or unconfigured and are never accepted in production.

For local API-key authentication it:

- registers secret metadata and an owner identity reference,
- stores only a one-way `password_hash` digest of the API key,
- enforces active, expiry, and revocation state,
- returns only secret and credential-verification references to Authentication,
- rotates an active API key transactionally by activating a new secret reference and marking the old reference `ROTATED`,
- and never returns or persists the submitted plaintext API key.

After rotation, verification with the prior key fails immediately. Rotation records link only the old and replacement secret references; neither plaintext value is retained.

Credential lifecycle HTTP operations require a separately authenticated and authorized administrator. Secrets Manager performs the requested mutation after that decision; it does not issue its own permission. Successful administration returns a security-event reference, while raw credential values remain confined to the protected request and provider call.

This SQLite implementation is a local reference store. Production deployment must use approved storage, encryption, key-management, authorization, rotation, and audit owners appropriate to its environment.

Reusable provider conformance test cases define the minimum behavioral contract for secret rotation and revocation, MFA decision shape and proof redaction, and security-event evidence and correlation preservation. External provider adapters must run these same contract suites before they may claim production readiness.

### Provider Selection

`CredentialProviderLoader` selects one explicit provider set at startup:

| Configuration | Behavior |
|---|---|
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER=local` | Loads SQLite secrets and security events plus deny-only MFA. Allowed only in `local` or `test`. |
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json` | Loads one external HTTP adapter for secrets, MFA, and security events. |
| Missing or unknown value | Defaults to `local` only in `local` or `test`; production startup is refused. |

The HTTP provider requires `SQUIRRELFORGE_CREDENTIAL_PROVIDER_URL` and `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN`. Production readiness additionally requires an HTTPS URL and a provider token of at least 32 characters. The token is carried only in the provider request's Authorization header and must never enter payloads, errors, events, or application logs.

The external gateway contract exposes protected JSON operations for secret registration, verification, rotation, and revocation; MFA verification; and security-event persistence. Non-success responses and invalid response schemas fail closed through safe local errors. The full request/response contract a provider must implement is specified in `deploy/CREDENTIAL-PROVIDER-CONTRACT.md`.

### Provider Resilience

External provider transport uses bounded attempts and an in-process circuit breaker. Configuration is:

| Variable | Default | Rule |
|---|---:|---|
| `SQUIRRELFORGE_PROVIDER_MAX_ATTEMPTS` | `3` | Maximum attempts for retryable transport failures. |
| `SQUIRRELFORGE_PROVIDER_CIRCUIT_FAILURES` | `3` | Exhausted logical operations before opening the circuit. |
| `SQUIRRELFORGE_PROVIDER_CIRCUIT_COOLDOWN_SECONDS` | `30` | Minimum interval before a new provider attempt is permitted. |

Only transport exceptions and HTTP `408`, `429`, `502`, `503`, and `504` are retried. Client and policy failures are returned immediately and do not classify the provider as unavailable.

Registration, rotation, revocation, and security-event writes carry one random idempotency key that remains stable across all attempts for that logical operation. Verification operations are safe reads and do not carry mutation idempotency.

`ProviderHealthInterface` exposes a redacted health decision. The HTTPS adapter probes `GET /v1/provider/health` and returns only provider reference, boolean health, and safe rationale. An outage, exhausted retry budget, or open circuit always fails credential verification closed.

`ProviderTelemetryInterface` records redacted operational events for attempts, retries, successes, failures, and circuit transitions. The SQLite reference telemetry store permits only attempt number, HTTP status, circuit state, and operation class metadata. Credential values, provider tokens, URLs, bodies, identity references, and remote error details are discarded before persistence.

`ProviderReadinessApiServer` combines provider health with the local telemetry snapshot at `GET /v1/health/providers`. An open circuit or unhealthy provider is not ready. This operational route reports aggregate evidence only; it is not an authentication, authorization, incident, or recovery decision.

---

## Rules

1. Secret values must never appear in logs, metadata, configuration bundles, or audit records.
2. Secrets Manager must consume security authorization references; it must not decide authorization itself.
3. Cryptographic operations belong to Security's Encryption Manager.
4. Secret lifecycle changes must preserve configuration-domain and audit evidence references.
