# Integrations: Authentication Manager

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION/README.md`, `24_SECURITY/README.md`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`
Last Updated: 2026-07-04

## Purpose

The Authentication Manager is the central service within the `Integrations` layer responsible for managing credentials and authenticating with external systems. It provides a secure, standardized way for components like `LLM Providers` and `Connectors` to obtain the authorization needed for their operations, without ever handling secrets directly.

---

## Responsibilities

-   Retrieve credential references from a secure configuration or secrets manager.
-   Orchestrate authentication flows (e.g., OAuth 2.0 token exchange).
-   Provide valid, short-lived tokens or API keys to authorized internal clients upon request.
-   Manage the lifecycle of credentials, including refreshing expiring tokens.
-   Log all authentication events (requests, successes, failures) for security auditing.
-   Report the status of authentication providers (e.g., `Valid`, `Expired`, `Failed`).

---

## Interaction with Integration Clients

Integration clients (like an API client in the `LLM Providers` component) do not manage their own credentials. Instead, they request them from the Authentication Manager.

1.  An integration client (e.g., `Anthropic Client`) needs to make an authenticated API call.
2.  It requests the necessary credential (e.g., an API key) from the Authentication Manager for the `anthropic` service.
3.  The Authentication Manager retrieves the secret reference from the `Configuration Manager`, fetches the secret from the secure vault, and returns it to the client.
4.  The client uses the credential for the single transaction and then discards it.

This ensures that secrets are not stored, logged, or managed by the individual clients.

```text
Integration Client (e.g., Connector)
       │
       ▼ (1. "I need a token for 'github'")
Authentication Manager
       │
       ▼ (2. Retrieves secret reference from Config)
Secrets Vault
       │
       ▼ (3. Returns secret to Auth Manager)
Authentication Manager
       │
       ▼ (4. Returns credential to Client)
Integration Client
       │
       ▼ (5. Uses credential for external call)
External API
```

---

## Authentication States

| Status | Meaning |
|---|---|
| `Valid` | The credential is active and ready for use. |
| `Expired` | The credential has expired and must be refreshed. |
| `Invalid` | The credential was rejected by the external service. |
| `Revoked` | The credential has been manually revoked and is no longer valid. |
| `Pending` | An authentication flow (e.g., OAuth) is in progress. |

---

## Rule

1.  **No Direct Secret Access:** Integration clients **must not** access secret storage directly. All credential requests must go through the Authentication Manager.
2.  **Ephemeral Credentials:** Clients should treat credentials as ephemeral and request them as needed. They **must not** store or cache credentials themselves.
3.  **Centralized Logic:** All logic for handling specific authentication methods (e.g., OAuth 2.0 grant types) **must** reside within the Authentication Manager.
4.  **Complete Audit Trail:** Every request for a credential, and its outcome, must be logged for a complete audit trail.
