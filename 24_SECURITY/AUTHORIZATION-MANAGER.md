# SquirrelForge Authorization Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/AUTHENTICATION-MANAGER.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`, `21_CONFIGURATION/PERMISSIONS.md`, `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `24_SECURITY/SECURITY-MANAGER.md`, `24_SECURITY/SECURITY-MONITOR.md`
Last Updated: 2026-07-07

## Purpose

The Authorization Manager determines whether an already-authenticated identity is permitted to perform a requested operation within SquirrelForge. It issues runtime grant/deny decisions using identity status, token claims, role assignments, permission declarations, policy results, resource context, and governance references.

The Authorization Manager grants or denies access only. It does not authenticate identities (owned by `24_SECURITY/AUTHENTICATION-MANAGER.md`), treat token or role claims as automatic authorization, define security policy (owned by `24_SECURITY/SECURITY-GOVERNANCE.md`), evaluate general policy independently (owned by `23_GOVERNANCE/POLICY-ENGINE.md`), or own declarative tool/configuration permission policy (owned by `21_CONFIGURATION/PERMISSIONS.md`, a configuration-scoped model distinct from this component's runtime access decisions).

---

## Responsibilities

- Evaluate authorization requests.
- Issue runtime authorization decisions.
- Enforce the resulting authorization decision.
- Read assigned roles and token claims as inputs.
- Evaluate permission declarations as runtime inputs.
- Apply authorized attribute/context checks.
- Support least-privilege access.
- Deny unauthorized operations.
- Record authorization decisions.
- Support permission audits.
- Maintain authorization integrity.

---

## Authorization Inputs

The Authorization Manager uses:

- Authenticated identities (verified by `24_SECURITY/AUTHENTICATION-MANAGER.md`)
- Token claims as authentication assertions only
- Requested operations
- Target resources
- Assigned roles
- Permission assignments (from `21_CONFIGURATION/PERMISSIONS.md`)
- Security policies (from `24_SECURITY/SECURITY-GOVERNANCE.md`)
- Policy evaluation results or governance rules (from `23_GOVERNANCE/POLICY-ENGINE.md`)
- Operational context
- Resource classifications
- Environmental conditions

---

## Authorization Workflow

1. Receive authorization request.
2. Verify authenticated identity via `24_SECURITY/AUTHENTICATION-MANAGER.md`.
3. Identify requested operation.
4. Determine target resource.
5. Read applicable policy evaluation results, security policy references, and permission declarations.
6. Verify assigned permissions.
7. Apply least-privilege and authorized attribute/context checks.
8. Issue authorization decision.
9. Record authorization decision evidence.
10. Notify `24_SECURITY/SECURITY-MONITOR.md`.

---

## Authorization Models

The Authorization Manager supports:

- Role-Based Access Control (RBAC)
- Attribute-Based Access Control (ABAC)
- Policy-Based Access Control (PBAC)
- Resource-based permissions
- Context-aware authorization
- Time-based restrictions
- Delegated authorization (when approved)

---

## Authorization Decisions

Each request results in one of:

- Authorized
- Authorized with Restrictions
- Temporarily Deferred
- Additional Verification Required
- Denied
- Permanently Prohibited

Every decision must include supporting rationale.

---

## Evaluation Criteria

Authorization evaluates:

- Identity status
- Assigned roles
- Granted permissions
- Resource sensitivity
- Data classification
- Governance requirements
- Operational context
- Security policies

---

## Safety Rules

The Authorization Manager must never:

- Grant permissions to unauthenticated identities.
- Bypass governance requirements.
- Ignore least-privilege principles.
- Escalate privileges without approval.
- Override security policies.
- Remove authorization audit records.

---

## Failure Handling

If authorization fails:

- Deny the operation.
- Preserve request details.
- Record the failure.
- Notify `24_SECURITY/SECURITY-MONITOR.md`.
- Escalate repeated failures.
- Maintain audit continuity.

---

## Audit Requirements

Every authorization operation records:

- Authorization ID
- Timestamp
- Identity ID
- Requested operation
- Target resource
- Authorization decision
- Applied policies
- Governance status
- Final outcome

---

## Success Criteria

The Authorization Manager succeeds when:

- Every protected operation is evaluated.
- Least-privilege access is consistently enforced.
- Unauthorized actions are prevented.
- Authorization decisions are traceable.
- Audit history is complete.
- Governance requirements are respected.
- Platform resources remain securely protected.

---

## Permission Boundary

The Authorization Manager may evaluate authorization requests and issue runtime grant/deny decisions for authenticated identities against existing identity status, token claims, roles, permission declarations, policy results, resource context, and governance references.

It must not authenticate identities, treat token or role claims as automatic authorization, define security policy, independently own general policy evaluation, or own declarative configuration-scoped permission policy — those remain owned by `24_SECURITY/AUTHENTICATION-MANAGER.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`, `23_GOVERNANCE/POLICY-ENGINE.md`, and `21_CONFIGURATION/PERMISSIONS.md` respectively.

---

## Domain Rule

Authorization decisions apply identically regardless of domain; domain-specific access rules — for example WordPress capability checks — are owned by the relevant domain layer (`38_WORDPRESS/SECURITY-VALIDATOR.md`), not reimplemented here.

---

## Rule

No operation on a protected resource may proceed without an explicit "Authorized" or "Authorized with Restrictions" decision from the Authorization Manager.
