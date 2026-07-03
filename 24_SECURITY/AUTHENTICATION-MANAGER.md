# SquirrelForge Authentication Manager

## Purpose

The Authentication Manager acts as the primary identity gatekeeper for the entire SquirrelForge platform. It is responsible for securely managing and validating the credentials of all internal actors (users, agents, and system components) to verify their identity before they are granted any access.

The Authentication Manager answers the fundamental question: "Is this actor who they claim to be?"

---

# Responsibilities

- Verify actor credentials against a secure identity store.
- Manage user and agent sessions and their lifecycle.
- Enforce multi-factor authentication (MFA) policies.
- Issue, validate, and refresh internal access tokens (e.g., JWTs).
- Enforce credential policies (complexity, rotation) from `SECURITY-GOVERNANCE`.
- Log every authentication attempt (successful and failed) to the `SECURITY-AUDITOR`.

---

# Authentication Workflow

1. An actor presents credentials to access the system.
2. The Authentication Manager receives the credentials.
3. It securely hashes and compares the provided credentials against the authoritative identity store.
4. If MFA is required, it initiates and validates the second-factor challenge.
5. Upon successful verification, it creates a session and issues a short-lived access token.
6. The authentication attempt, including its outcome, source IP, and actor ID, is logged to the `SECURITY-AUDITOR`.
7. The access token is returned to the actor for use in subsequent requests.

---

# Supported Credential Types

| Type | Actor | Description |
|---|---|---|
| Password | User | Hashed and salted passwords for human users. |
| API Key | Agent / System | Long-lived, revocable keys for programmatic access. |
| Client Certificate | System | mTLS certificates for service-to-service authentication. |
| Access Token | All | Short-lived tokens (e.g., JWT) issued after initial authentication. |

---

# Authentication Principles

- **Secure Credential Handling**: Passwords and secrets are never stored in plaintext and are never logged.
- **Centralized Identity**: This component is the single source of truth for actor identity verification.
- **Defense Against Brute Force**: The manager must implement mechanisms like rate limiting and account lockout for repeated failed attempts.
- **Session Management**: All sessions must have a defined expiration and be securely managed.

---

# Safety Rules

The Authentication Manager must never:

- Log passwords or other sensitive credential information.
- Return detailed error messages that could reveal whether a username exists.
- Fail to enforce MFA policies when required.
- Issue an access token for a failed authentication attempt.

---

# Audit Requirements

Every authentication attempt records:

- Authentication Attempt ID
- Timestamp
- Actor ID (if known)
- Source IP Address
- Credential Type Used
- MFA Status (Required, Succeeded, Failed)
- Final Outcome (Success / Failure)
- Failure Reason (e.g., Invalid Password, Invalid MFA)

---

# Success Criteria

The Authentication Manager succeeds when:

- Only actors with valid credentials can gain access.
- All authentication attempts are securely logged.
- MFA and other security policies are correctly enforced.
- Compromised or invalid credentials are reliably rejected.

---

# Rule

No actor may be considered authenticated or be issued an access token for internal operations without successfully passing verification by the Authentication Manager.