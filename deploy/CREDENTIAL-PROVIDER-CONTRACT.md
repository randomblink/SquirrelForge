# Credential Provider HTTP Contract

This document specifies the exact HTTP contract an external credential provider service must implement to be usable as SquirrelForge's production secrets, MFA, and security-event backend via `SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json`.

It documents the *client's* real, already-implemented, already-tested behavior — `src/RuntimeConfig/HttpCredentialProvider.php`, wrapped by `src/RuntimeConfig/ResilientHttpCredentialProvider.php` — so a server can be built to match it exactly. Nothing in this document describes aspirational behavior; every request/response shape below is read directly from that class's real request-building and response-parsing code, and every field name is cross-checked against the actual consumers that read it (`src/Security/ApiKeySessionIssuer.php`).

Despite its name, `ResilientHttpCredentialProvider` does **not** retry any of the six operational calls below — it delegates each one 1:1 to `HttpCredentialProvider` with no retry loop. What it adds is a seventh, separate capability: a health probe (see below). There is no retry-driven idempotency requirement on this contract; each call happens exactly once per SquirrelForge-side operation.

---

## Scope

This contract covers three roles SquirrelForge composes together when `SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json`:

| Role | SquirrelForge interface | Endpoints |
|---|---|---|
| Secrets Manager | `SecretsManagerInterface` | `secrets/register`, `secrets/verify`, `secrets/rotate`, `secrets/revoke` |
| MFA Verifier | `MfaVerifierInterface` | `mfa/verify` |
| Security Event Sink | `SecurityEventSinkInterface` | `security-events` |

A provider must implement all six operational endpoints below, plus the health probe; SquirrelForge's `CredentialProviderLoader` wires the same base URL and token to all three roles as one unit — there is no way to configure a different provider per role today.

This is **not** SquirrelForge's own health endpoint (`GET /v1/health/providers`, called by container orchestration against SquirrelForge itself) — that is a separate, unrelated surface and out of scope here.

---

## Transport requirements

- **Scheme**: the base URL must be `https://`. `HttpCredentialProvider::isProductionReady()` checks `str_starts_with(strtolower($baseUrl), 'https://')` literally — an `http://` URL is treated as not production-ready and `ProviderStartupValidator` will refuse to start the application in any non-local/non-test environment.
- **Token**: must be at least 32 characters. Also checked by `isProductionReady()`.
- **Method**: every call is `POST`, including `secrets/verify` and `mfa/verify` (not `GET`) — read-shaped operations still POST their input.
- **Request headers**:
  ```
  Accept: application/json
  Authorization: Bearer {SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN}
  Content-Type: application/json
  ```
- **Request body**: a JSON object, always present (even `revoke`, which only needs `secret_ref`).
- **Timeout**: the client applies a per-call timeout (`10.0` seconds by default; configurable by whatever constructs `HttpCredentialProvider`). The provider should respond well within this window or the call is treated as a failure.
- **Success**: any HTTP status `200 <= status < 300`.
- **Failure**: any status outside `[200, 300)` is treated as a hard failure. The client does **not** discriminate between 4xx and 5xx, and — deliberately, for security — **never surfaces the response body in its own exception message or logs** on failure. Use your own server-side logging/status codes to distinguish failure causes; SquirrelForge's own logs will only ever show a generic "Credential provider rejected the operation."
- **Response body**: must be a JSON object (not a bare scalar, not a JSON array at the top level) on every successful call. A response that fails to parse as JSON, or that decodes to a non-object, is treated as a failure the same way a bad status code is.

---

## Endpoints

### `POST /v1/provider/secrets/register`

Registers a new API-key-shaped secret for an identity.

**Request:**
```json
{
  "identity_ref": "string, required",
  "api_key": "string, required — the raw key material being registered",
  "expires_at": "string|null — RFC 3339 timestamp, or null for no expiry"
}
```

**Response (required on success):**
```json
{
  "secret_ref": "string, required, non-empty"
}
```
A response missing `secret_ref`, or where it is empty/non-string, is treated as a failure (`HttpCredentialProvider::requiredString()`). Additional fields are ignored by the client but may be included.

---

### `POST /v1/provider/secrets/verify`

Verifies an identity's presented API key. This is on the critical authentication path — `ApiKeySessionIssuer::issue()` reads `verified` and `verification_ref` directly from this response and uses `verification_ref` as the session's own `authentication_ref`.

**Request:**
```json
{
  "identity_ref": "string, required",
  "api_key": "string, required — the key material to verify"
}
```

**Response (the full object is returned to the caller verbatim):**
```json
{
  "verified": true,
  "secret_ref": "string|null",
  "verification_ref": "string|null — required (non-null) when verified is true; used as the authentication reference downstream",
  "rationale": "string — a short, non-sensitive reason, e.g. \"The active API-key secret matched.\""
}
```
- `verified` is read as `($response['verified'] ?? false) === true` — omitting it or sending a non-boolean-`true` value is treated as **not verified** (fail closed).
- When `verified` is `true`, `verification_ref` must be a non-empty string; the caller casts it directly to a string for use as `authentication_ref` in the issued session.
- Never include the raw key material, its hash, or any other secret value in this response.

---

### `POST /v1/provider/secrets/rotate`

Issues a new secret for an existing `secret_ref` and retires the old one.

**Request:**
```json
{
  "secret_ref": "string, required — the existing secret being rotated",
  "new_api_key": "string, required — the new key material",
  "expires_at": "string|null — RFC 3339 timestamp for the new secret, or null"
}
```

**Response (required on success):**
```json
{
  "secret_ref": "string, required, non-empty — the NEW secret's reference"
}
```
Same `requiredString()` validation as `register`. The old `secret_ref` should be marked non-active server-side; SquirrelForge does not separately call `revoke` after a rotation.

---

### `POST /v1/provider/secrets/revoke`

Revokes a secret. The client discards the response body entirely (return type is `void`); it only needs a `200 <= status < 300` and a valid JSON object body (an empty `{}` is sufficient).

**Request:**
```json
{
  "secret_ref": "string, required"
}
```

**Response:** any JSON object; content is ignored. Recommended: `{"revoked": true}` for the server's own audit/debug clarity, though the client does not read it.

---

### `POST /v1/provider/mfa/verify`

Verifies a human identity's MFA proof. Called only for identities SquirrelForge's own Identity Manager reports as `identity_type === "USER"` (service/agent identities skip MFA entirely).

**Request:**
```json
{
  "identity_ref": "string, required",
  "proof": "string|null — the MFA proof/code the caller presented; null if none was supplied",
  "correlation_id": "string, required"
}
```

**Response (the full object is returned to the caller verbatim):**
```json
{
  "verified": true,
  "verification_ref": "string|null",
  "rationale": "string — a short, non-sensitive reason"
}
```
- `verified` is read the same fail-closed way as the secrets-verify endpoint: `($response['verified'] ?? false) === true`.
- `verification_ref` (when present) is recorded as evidence in a `MFA_VERIFICATION` security event, not otherwise used structurally.
- A `proof` of `null` should be treated as an explicit "no proof supplied" and verified `false`, not an error — the caller is allowed to ask "is this identity currently verified" without presenting a fresh proof (e.g. checking an existing session), and the provider decides what that means for its own MFA state model.

---

### `POST /v1/provider/security-events`

Records a security-relevant event. Called for lockouts, credential-verification failures, MFA failures/successes, and session issuance.

**Request:**
```json
{
  "event_type": "string, required — e.g. \"AUTHENTICATION\", \"CREDENTIAL_VERIFICATION\", \"MFA_VERIFICATION\", \"SESSION_ISSUED\"",
  "outcome": "string, required — e.g. \"SUCCESS\", \"FAILURE\", \"LOCKED\"",
  "identity_ref": "string|null",
  "correlation_id": "string, required",
  "metadata": "object — free-form, caller-supplied; may include source_ref, verification_ref, session_ref depending on event_type"
}
```
`event_type` and `outcome` are not drawn from a fixed enum on the client side — the provider should accept any string SquirrelForge sends rather than rejecting unrecognized values, since the caller's own vocabulary may grow over time.

**Response (required on success):**
```json
{
  "security_event_ref": "string, required, non-empty"
}
```
Same `requiredString()` validation as `register`/`rotate`.

---

### `GET /v1/provider/health`

Only called when the provider is wrapped as `ResilientHttpCredentialProvider` (the standard `http-json` wiring). Used for the provider's own health signal, independent of any specific operation. Not part of the four-role interface set above — this is `ProviderHealthInterface::health()`.

**Request:** no body.
```
Accept: application/json
Authorization: Bearer {token}
```

**Response:**
```json
{
  "healthy": true
}
```
- Timeout is fixed at `5.0` seconds regardless of the configured operational timeout.
- `healthy` is read as `($body['healthy'] ?? false) === true` — omitting it, or any non-`true` value, is treated as unhealthy (fail closed).
- Any non-2xx status, a non-JSON body, or a transport-level exception (connection refused, timeout, TLS failure) is also treated as unhealthy — the client catches `Throwable` broadly around this specific call and never lets a health-check failure propagate as an exception.
- This endpoint should reflect the provider's own actual operational state (its datastore reachable, its own dependencies healthy) — the same "fail closed, not open" requirement as the operational endpoints applies here too.

---

## Security requirements for the provider implementation

These are requirements on the *server* you build, inferred from how the client is deliberately designed:

1. **Never return raw secret material** in any response — `secrets/register`, `secrets/rotate`, and `secrets/verify` responses must never echo back the `api_key`/`new_api_key` value or an equivalent derivable form.
2. **Constant-time comparison** for any credential-matching logic (`secrets/verify`, `mfa/verify`), the same discipline the local reference implementations (`SqliteSecretsManager`, `StaticMfaVerifier`) already apply via `password_verify()`/`hash_equals()`.
3. **Do not put a token or secret value in a log line, error body, or URL.** The client already avoids leaking response bodies into its own error path; the same care is expected server-side, since `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN` grants this provider's full authority over secrets, MFA, and security events.
4. **Fail closed, not open.** If the provider itself is degraded (its own datastore unavailable, etc.), it must return a non-2xx status rather than a `200` with `"verified": true` or a fabricated `secret_ref` — the client has no independent way to detect a provider that lies about success.

---

## Reference: the real, standalone server implementation of this contract

`src/CredentialProvider/CredentialProviderRouter.php` implements every endpoint above and is meant to be run as its own deployed service (a different process/host than the SquirrelForge instance configured with `SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json` that calls it) — `deploy/mock-provider/` is a throwaway smoke-test double for CI; this is the genuine implementation.

It composes already-real, already-tested pieces rather than reimplementing them: `SqliteSecretsManager` for `secrets/*` (the same class used as the `local` in-process provider), `SqliteSecurityEventSink` for `security-events`, and a new `MfaSecretStore`/`TotpVerifier` pair for `mfa/verify` — real RFC 6238 TOTP, since that role has no prior implementation to reuse (API-key material only ever needs one-way `password_hash()`; TOTP verification genuinely needs the secret back, so it is encrypted at rest via `SqliteEncryptionManager` rather than hashed).

`CredentialProviderRouter` itself is transport-agnostic (`handle()` takes plain PHP values, returns `[httpStatus, responseBody]`); `public/credential-provider.php` is the HTTP entry point, following the same superglobals-only-at-the-edge pattern as `public/engine-api.php`. It is configured entirely by environment variables:

| Variable | Required | Notes |
|---|---|---|
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN` | yes | The Bearer token this server expects — the same value the calling SquirrelForge instance sets as its own `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN`. Compared with `hash_equals()`. |
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER_MFA_MASTER_KEY` | yes | Base64-encoded 32-byte AES-256-GCM key used to encrypt TOTP secrets at rest. Missing, non-base64, or wrong-length values make the server refuse every request with `500 {"error": "server_misconfigured"}` (fail closed, never fall back to a default key). |
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER_DB` | no | SQLite database path for secrets, security events, and encryption audit records. Defaults to `var/credential-provider.sqlite`. |

Run it the same way `deploy/entrypoint.sh` runs the main API — as the PHP built-in server's router script, not a docroot, so `REQUEST_URI` reaches the router unmodified: `php -S 0.0.0.0:8080 public/credential-provider.php`. Behind a real webserver instead, rewrite every path to this one script rather than serving it as a static docroot target.

---

## Reference: the local (non-production) implementations this contract replaces

`SqliteSecretsManager`, `StaticMfaVerifier`, `DenyHumanMfaVerifier`, and `SqliteSecurityEventSink` (`src/RuntimeConfig/` and `src/Security/`) are the in-process reference implementations of the same interfaces, used only in `local`/`test` environments (`RuntimeEnvironmentPolicy::allowsLocalProviders()`). Their behavior is the de facto specification for response *shape* used throughout this document; this contract is what an external service needs to implement to be a drop-in, production-ready replacement for all four at once.
