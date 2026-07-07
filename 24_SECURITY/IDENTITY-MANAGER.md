# SquirrelForge Identity Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/SECURITY-GOVERNANCE.md`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`
Used By: `24_SECURITY/AUTHENTICATION-MANAGER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`, `24_SECURITY/SECURITY-MANAGER.md`
Last Updated: 2026-07-07

## Purpose

The Identity Manager is the authoritative source for "who is who" within SquirrelForge. It owns the complete lifecycle of every identity — users, agents, and system services — and maintains the identity record each is known by, including which roles are assigned and which credential types are associated with it.

The Identity Manager owns identity records and lifecycle only. It does not verify credentials, enforce MFA, or issue access tokens — those are authentication mechanics owned by `24_SECURITY/AUTHENTICATION-MANAGER.md`, which reads identity records from this component rather than maintaining its own identity store. It also does not make authorization decisions (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md`) or store credential secrets directly (owned by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`).

---

## Responsibilities

- Provision new identities.
- Update identity attributes and status.
- Suspend and deactivate identities.
- Maintain the authoritative identity record for every user, agent, and service.
- Maintain each identity's assigned roles for use by `24_SECURITY/AUTHORIZATION-MANAGER.md`.
- Maintain credential-type references (pointers to which credential types an identity has, not the credentials themselves).
- Enforce identity-record policies (naming, attribute requirements, retention) from `24_SECURITY/SECURITY-GOVERNANCE.md`.
- Record all identity lifecycle events for auditing.

---

## Identity Types

| Type | Description |
|---|---|
| User | A human actor interacting with the system. |
| Agent | An autonomous AI or software agent performing tasks. |
| Service | An internal system component or microservice. |

---

## Identity Lifecycle Workflow

1. Receive an identity provisioning, update, or deactivation request.
2. Validate the request against identity-record policy.
3. Apply the change to the identity record.
4. Update assigned roles or credential-type references as needed.
5. Record the lifecycle event for auditing.
6. Notify `24_SECURITY/SECURITY-MONITOR.md`.
7. Publish the updated identity status.

---

## Identity Record

Each identity has a record containing:

- **Identity ID**: A unique, immutable identifier (e.g., UUID).
- **Identity Type**: User, Agent, or Service.
- **Status**: Active, Suspended, Deactivated.
- **Attributes**: Human-readable name, email, etc.
- **Credential References**: Pointers to the credential types associated with the identity (e.g., has password, has API key) — the credentials themselves are held by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` and verified by `24_SECURITY/AUTHENTICATION-MANAGER.md`.
- **Assigned Roles**: A list of roles used by `24_SECURITY/AUTHORIZATION-MANAGER.md`.

---

## Supported Credential Types (Reference)

The identity record tracks which of these credential types are associated with an identity. Verification of these credentials is performed by `24_SECURITY/AUTHENTICATION-MANAGER.md`, not by the Identity Manager.

| Type | Used By | Description |
|---|---|---|
| Password | User | Hashed and salted passwords for interactive logins. |
| API Key | Agent, Service | Long-lived, revocable keys for programmatic access. |
| Client Certificate | Service | mTLS certificates for secure service-to-service authentication. |

Access tokens are authentication artifacts issued by `24_SECURITY/AUTHENTICATION-MANAGER.md`, not identity-record credential-type references.

---

## Safety Rules

The Identity Manager must never:

- Verify credentials or issue access tokens — that is `24_SECURITY/AUTHENTICATION-MANAGER.md`'s responsibility.
- Return detailed error messages that could confirm the existence of an identity (to prevent enumeration attacks).
- Store credentials directly; it must reference `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`.
- Provision or reactivate an identity without a valid request and applicable policy check.
- Delete identity lifecycle audit history.

---

## Failure Handling

If an identity lifecycle operation fails:

- Preserve the existing identity record unchanged.
- Record the failure.
- Notify `24_SECURITY/SECURITY-MONITOR.md`.
- Escalate repeated failures.
- Maintain audit continuity.

---

## Audit Requirements

Every identity lifecycle event is recorded, including:

- **Event ID**: A unique identifier for the event.
- **Timestamp**: The time of the event.
- **Event Type**: IdentityCreated, IdentityUpdated, IdentitySuspended, IdentityDeactivated, etc.
- **Identity ID**: The affected identity.
- **Requested By**: The actor or component requesting the change.
- **Final Outcome**: Success or Failure.

---

## Success Criteria

The Identity Manager succeeds when:

- Every identity within SquirrelForge has exactly one authoritative record.
- Identity status accurately reflects lifecycle state at all times.
- Role assignments remain current for `24_SECURITY/AUTHORIZATION-MANAGER.md` to consume.
- All identity lifecycle events are securely logged.
- No credentials are stored outside `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`.

---

## Permission Boundary

The Identity Manager may provision, update, suspend, and deactivate identity records, and maintain role assignments and credential-type references.

It must not verify credentials, enforce MFA, issue access tokens, make authorization decisions, or store credential secrets directly — those remain owned by `24_SECURITY/AUTHENTICATION-MANAGER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`, and `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` respectively.

---

## Domain Rule

Identity lifecycle management applies identically regardless of domain; domain-specific identity attributes are stored as additional attributes on the existing identity record, not as a separate domain-specific identity store.

---

## Rule

Every user, agent, and service acting within SquirrelForge must have exactly one identity record maintained by the Identity Manager before `24_SECURITY/AUTHENTICATION-MANAGER.md` may verify it or `24_SECURITY/AUTHORIZATION-MANAGER.md` may authorize it.
