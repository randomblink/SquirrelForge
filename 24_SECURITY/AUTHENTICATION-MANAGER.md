# SquirrelForge Authentication Manager

Version: 1.6.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/IDENTITY-MANAGER.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`
Used By: `24_SECURITY/AUTHORIZATION-MANAGER.md`, `24_SECURITY/ENCRYPTION-MANAGER.md`, `24_SECURITY/SECURITY-MANAGER.md`
Last Updated: 2026-07-26

## Purpose

The Authentication Manager acts as the primary credential-verification gatekeeper for the entire SquirrelForge platform. It verifies the credentials of actors identified in `24_SECURITY/IDENTITY-MANAGER.md`'s identity records, enforces MFA, and issues the sessions and access tokens other components rely on as proof of a verified identity.

The Authentication Manager answers the fundamental question: "Is this actor who its identity record claims it is?"

The Authentication Manager performs credential verification and session/token issuance only. It does not provision, update, suspend, or deactivate identities (owned by `24_SECURITY/IDENTITY-MANAGER.md`), make authorization decisions (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md`), or store credentials directly (owned by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`).

---

## Responsibilities

- Verify actor credentials against the credential references held in `24_SECURITY/IDENTITY-MANAGER.md`'s identity record.
- Manage authentication sessions and their lifecycle.
- Enforce multi-factor authentication (MFA) policies.
- Issue, validate, and refresh internal access tokens (e.g., JWTs).
- Enforce credential policies (complexity, rotation) from `24_SECURITY/SECURITY-GOVERNANCE.md`.
- Log every authentication attempt (successful and failed) to `24_SECURITY/SECURITY-MONITOR.md`.

---

## Authentication Workflow

1. An actor presents credentials to access the system.
2. The Authentication Manager retrieves the actor's identity record from `24_SECURITY/IDENTITY-MANAGER.md` and confirms it is Active.
3. It securely hashes and compares the provided credentials against the authoritative credential store (`28_RUNTIME-CONFIG/SECRETS-MANAGER.md`).
4. If MFA is required by `24_SECURITY/SECURITY-GOVERNANCE.md` policy, it initiates and validates the second-factor challenge.
5. Upon successful verification, it creates a session and issues a short-lived access token containing authentication claims such as identity and role claims for downstream authorization evaluation.
6. The authentication attempt, including its outcome, source IP, and identity ID, is logged to `24_SECURITY/SECURITY-MONITOR.md`.
7. The access token is returned to the actor for use in subsequent requests.

---

## Supported Credential Types

| Type | Actor | Description |
|---|---|---|
| Password | User | Hashed and salted passwords for human users. |
| API Key | Agent / System | Long-lived, revocable keys for programmatic access. |
| Client Certificate | System | mTLS certificates for service-to-service authentication. |

Which credential types apply to a given actor is determined by that actor's identity record in `24_SECURITY/IDENTITY-MANAGER.md`.

Access tokens are issued authentication artifacts, not automatic authorization. Token and role claims are inputs to `24_SECURITY/AUTHORIZATION-MANAGER.md`.

---

## Authentication Principles

- **Secure Credential Handling**: Passwords and secrets are never stored in plaintext and are never logged; they are held in `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`.
- **Single Verification Authority**: This component is the single source of truth for credential verification; `24_SECURITY/IDENTITY-MANAGER.md` remains the single source of truth for identity records.
- **Defense Against Brute Force**: The manager must implement mechanisms like rate limiting and account lockout for repeated failed attempts.
- **Session Management**: All sessions must have a defined expiration and be securely managed.

---

## Safety Rules

The Authentication Manager must never:

- Log passwords or other sensitive credential information.
- Return detailed error messages that could reveal whether a username exists.
- Fail to enforce MFA policies when required.
- Issue an access token for a failed authentication attempt.
- Provision, update, or deactivate an identity record — that is `24_SECURITY/IDENTITY-MANAGER.md`'s responsibility.

---

## Audit Requirements

Every authentication attempt records:

- Authentication Attempt ID
- Timestamp
- Identity ID (from `24_SECURITY/IDENTITY-MANAGER.md`, if known)
- Source IP Address
- Credential Type Used
- MFA Status (Required, Succeeded, Failed)
- Final Outcome (Success / Failure)
- Failure Reason (e.g., Invalid Password, Invalid MFA)

---

## Success Criteria

The Authentication Manager succeeds when:

- Only actors with valid credentials and an Active identity record can gain access.
- All authentication attempts are securely logged.
- MFA and other security policies are correctly enforced.
- Compromised or invalid credentials are reliably rejected.

---

## Reference Runtime

The PHP reference implementation defines:

- `AuthenticationManagerInterface` for access-token validation,
- `SqliteAuthenticationManager` for persistent local sessions and attempt records,
- and `StaticHeaderAuthenticationManager` only for deterministic contract tests.

The SQLite implementation:

- verifies API keys through `SecretsManagerInterface`,
- issues a random opaque token only after receiving a credential-verification reference,
- stores only the token's SHA-256 digest,
- binds the session to one active Identity Manager record,
- enforces session expiry and revocation,
- rejects claimed-identity mismatches,
- and persists every successful or failed authentication attempt with a rationale and correlation reference.

`ApiKeySessionIssuer` applies configurable source-and-identity failure windows and temporary lockout before issuing a session. Human `USER` identities additionally require a successful `MfaVerifierInterface` decision; the production default fails closed when no verifier is configured. Service and agent identities retain the API-key flow.

`AuthenticationApiServer` exposes session issue and refresh operations. Refresh atomically revokes the presented session and returns a new opaque token; a revoked, expired, missing, or inactive-identity session cannot be refreshed. Credential failures, lockouts, MFA decisions, session issuance, and refresh decisions are written through `SecurityEventSinkInterface`; the SQLite sink persists correlation-safe metadata without credentials or token values.

`CredentialAdministrationApiServer` protects session revocation behind Authentication and a resource-scoped `security.sessions.revoke` authorization decision. It returns authentication, authorization, and security-event references so an administrative transport response can be traced to each owning decision without exposing a token.

`public/engine-api.php` requires a bearer session before authorization. Its optional bootstrap identity, permission, and API-key environment values are honored only when `SQUIRRELFORGE_ENVIRONMENT` is explicitly `local` or `test`. The default environment is production and refuses bootstrap provisioning. Production provisioning must use governed identity, secret, credential-verification, and permission workflows.

The same environment boundary governs provider composition. Local and test environments may use the documented SQLite, static, and deny-only reference providers. Production startup validates all credential-path providers before accepting requests and terminates if Secrets Manager, MFA verification, or security-event persistence is missing production-readiness evidence.

The first production-capable adapter is `HttpCredentialProvider`. It delegates API-key verification and human MFA verification to a configured external HTTPS gateway, while preserving the existing Authentication Manager decisions, throttling, session issuance, and correlation model. Provider transport failure never becomes successful authentication and remote response bodies are not copied into public authentication errors.

The resilient provider wrapper applies bounded retry and circuit-breaking before Authentication receives a provider result. Retry exhaustion and open-circuit decisions are failures, never cached authentication successes. The circuit breaker suppresses repeated calls during a bounded outage window while preserving fail-closed behavior.

---

## Permission Boundary

The Authentication Manager may verify credentials against identity records it does not own, enforce MFA, and issue and manage sessions and access tokens.

It must not create, modify, suspend, or deactivate identity records, make authorization decisions, or store credentials directly — those remain owned by `24_SECURITY/IDENTITY-MANAGER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`, and `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` respectively.

---

## Domain Rule

Authentication mechanics apply identically regardless of domain; no domain layer may implement its own credential verification.

---

## Rule

No actor may be considered authenticated or be issued an access token for internal operations without successfully passing verification by the Authentication Manager against an Active identity record maintained by `24_SECURITY/IDENTITY-MANAGER.md`.
