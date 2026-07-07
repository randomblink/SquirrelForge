# SquirrelForge Authorization Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/AUTHENTICATION-MANAGER.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`, `21_CONFIGURATION/PERMISSIONS.md`, `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `24_SECURITY/SECURITY-MANAGER.md`, `24_SECURITY/SECURITY-MONITOR.md`
Last Updated: 2026-07-06

## Purpose

The Authorization Manager determines whether an already-authenticated identity is permitted to perform a requested operation within SquirrelForge. It enforces role-based, attribute-based, and policy-driven access controls to ensure that every action complies with security, governance, and operational requirements.

The Authorization Manager grants or denies access only. It does not authenticate identities (owned by `24_SECURITY/AUTHENTICATION-MANAGER.md`), define security policy (owned by `24_SECURITY/SECURITY-GOVERNANCE.md`), or own declarative tool/configuration permission policy (owned by `21_CONFIGURATION/PERMISSIONS.md`, a configuration-scoped model distinct from this component's runtime access decisions).

---

## Responsibilities

- Evaluate authorization requests.
- Enforce access control policies.
- Verify assigned roles.
- Evaluate permissions.
- Apply attribute-based rules.
- Support least-privilege access.
- Deny unauthorized operations.
- Record authorization decisions.
- Support permission audits.
- Maintain authorization integrity.

---

## Authorization Inputs

The Authorization Manager evaluates:

- Authenticated identities (verified by `24_SECURITY/AUTHENTICATION-MANAGER.md`)
- Requested operations
- Target resources
- Assigned roles
- Permission assignments (from `21_CONFIGURATION/PERMISSIONS.md`)
- Security policies (from `24_SECURITY/SECURITY-GOVERNANCE.md`)
- Governance rules (from `23_GOVERNANCE/POLICY-ENGINE.md`)
- Operational context
- Resource classifications
- Environmental conditions

---

## Authorization Workflow

1. Receive authorization request.
2. Verify authenticated identity via `24_SECURITY/AUTHENTICATION-MANAGER.md`.
3. Identify requested operation.
4. Determine target resource.
5. Evaluate applicable policies.
6. Verify assigned permissions.
7. Apply least-privilege rules.
8. Issue authorization decision.
9. Record audit information.
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

The Authorization Manager may evaluate authorization requests and issue grant/deny decisions for authenticated identities against existing roles, permissions, and policy.

It must not authenticate identities, define security policy, or own declarative configuration-scoped permission policy — those remain owned by `24_SECURITY/AUTHENTICATION-MANAGER.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`, and `21_CONFIGURATION/PERMISSIONS.md` respectively.

---

## Domain Rule

Authorization decisions apply identically regardless of domain; domain-specific access rules — for example WordPress capability checks — are owned by the relevant domain layer (`38_WORDPRESS/SECURITY-VALIDATOR.md`), not reimplemented here.

---

## Rule

No operation on a protected resource may proceed without an explicit "Authorized" or "Authorized with Restrictions" decision from the Authorization Manager.
