# SquirrelForge Secrets Manager

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `24_SECURITY`, `24_SECURITY/ENCRYPTION-MANAGER.md`, `27_OBSERVABILITY/AUDIT-TRAIL.md`, `37_STORAGE`
Used By: Security, Integrations, Runtime Configuration, Execution, WordPress
Last Updated: 2026-07-08

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

## Rules

1. Secret values must never appear in logs, metadata, configuration bundles, or audit records.
2. Secrets Manager must consume security authorization references; it must not decide authorization itself.
3. Cryptographic operations belong to Security's Encryption Manager.
4. Secret lifecycle changes must preserve configuration-domain and audit evidence references.
