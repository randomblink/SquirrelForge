# SquirrelForge Identity Manager

## Purpose

The Identity Manager is responsible for the complete lifecycle of all identities within SquirrelForge, including users, agents, and system services. It serves as the authoritative source for "who is who" and manages the credentials used to prove that identity.

The Identity Manager answers the fundamental question: "Is this a valid identity, and can it prove who it is?"

---

# Responsibilities

- Manage the lifecycle of all identities (provision, update, deactivate).
- Verify actor credentials against a secure identity store.
- Enforce multi-factor authentication (MFA) policies.
- Issue, validate, and refresh internal access tokens upon successful authentication.
- Enforce credential policies (e.g., complexity, rotation) from `SECURITY-GOVERNANCE`.
- Record all identity lifecycle and authentication events for auditing.

---

# Identity Types

| Type | Description |
|---|---|
| User | A human actor interacting with the system. |
| Agent | An autonomous AI or software agent performing tasks. |
| Service | An internal system component or microservice. |

---

# Authentication Workflow

1. An actor presents credentials to access the system.
2. The Identity Manager receives the credentials.
3. It securely verifies the credentials against the authoritative identity store.
4. If MFA is required by policy, it initiates and validates the second-factor challenge.
5. Upon successful verification, it creates a session and issues a short-lived access token containing the actor's identity and roles.
6. The authentication attempt (success or failure) is logged for auditing.
7. The access token is returned to the actor for use in subsequent requests.

---

# Identity Record

Each identity has a record containing:

- **Identity ID**: A unique, immutable identifier (e.g., UUID).
- **Identity Type**: User, Agent, or Service.
- **Status**: Active, Suspended, Deactivated.
- **Attributes**: Human-readable name, email, etc.
- **Credential References**: Pointers to the types of credentials associated with the identity (e.g., has password, has API key).
- **Assigned Roles**: A list of roles used by the `AUTHORIZATION-MANAGER`.

---

# Supported Credential Types

| Type | Used By | Description |
|---|---|---|
| Password | User | Hashed and salted passwords for interactive logins. |
| API Key | Agent, Service | Long-lived, revocable keys for programmatic access. |
| Client Certificate | Service | mTLS certificates for secure service-to-service authentication. |
| Access Token | All | Short-lived tokens (e.g., JWT) issued after initial authentication. |

---

# Safety Rules

The Identity Manager must never:

- Log passwords or other raw secrets.
- Return detailed error messages that could confirm the existence of an identity (to prevent enumeration attacks).
- Fail to enforce MFA policies when required by `SECURITY-GOVERNANCE`.
- Issue an access token for a failed or incomplete authentication attempt.
- Store credentials directly; it must use the `SECRETS-MANAGER`.

---

# Failure Handling

If authentication fails:

- Deny the request immediately.
- Log the failure event with relevant non-sensitive metadata (e.g., source IP, timestamp).
- Increment a failure counter for the identity or source IP to detect brute-force attempts.
- Do not provide specific reasons for the failure to the end-user.

---

# Audit Requirements

Every identity and authentication event is recorded, including:

- **Event ID**: A unique identifier for the event.
- **Timestamp**: The time of the event.
- **Event Type**: IdentityCreated, IdentitySuspended, AuthSuccess, AuthFailure, etc.
- **Identity ID**: The affected identity.
- **Source IP Address**: The origin of the request.
- **MFA Status**: Required, Succeeded, Failed.
- **Final Outcome**: Success or Failure.

---

# Success Criteria

The Identity Manager succeeds when:

- Only valid, active identities can attempt authentication.
- Only actors with valid credentials can successfully authenticate.
- All identity lifecycle events and authentication attempts are securely logged.
- MFA and other credential policies are correctly and consistently enforced.
- Compromised or invalid credentials are reliably rejected.

---

# Rule

No actor may be considered authenticated or be issued an access token for internal operations without successfully passing verification by the Identity Manager.