# SquirrelForge Permissions

Version: 1.0.0
Status: Stable
Owner: Configuration Maintainers
Depends On: `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `16_AGENTS`, `20_EXECUTION/ACTION-DISPATCHER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Last Updated: 2026-07-07

## Purpose

Permissions defines the declarative, configuration-scoped policy describing which actors may perform which actions on which resources — the static rulebook, not the runtime decision. Permissions follow least privilege and are scoped by actor, action, resource, and duration; denial is the default.

Permissions is a policy document, not a decision engine. It does not evaluate a specific request or grant or deny access at runtime — that is `24_SECURITY/AUTHORIZATION-MANAGER.md`'s responsibility, which reads this configuration as one of its inputs alongside identity, role, and governance context.

---

## Responsibilities

- Declare which actors may perform which actions on which resources.
- Scope every permission by actor, action, resource, and duration.
- Evaluate read, write, execute, network, secret, and external-side-effect capabilities separately.
- Default to denial when no matching permission is declared.
- Keep permission declarations consistent with `23_GOVERNANCE/POLICY-ENGINE.md`.
- Version and record changes to permission declarations.

---

## Permission Model

| Dimension | Description |
|---|---|
| Actor | The user, agent, or service the permission applies to. |
| Action | The operation being permitted (read, write, execute, network, secret access, external side effect). |
| Resource | The target the action applies to. |
| Duration | How long the permission remains valid (persistent, session-scoped, time-limited). |

---

## Capability Types

| Capability | Description |
|---|---|
| Read | Permission to read a resource. |
| Write | Permission to modify a resource. |
| Execute | Permission to run a tool, workflow, or action. |
| Network | Permission to make outbound network calls. |
| Secret | Permission to reference a secret held by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`. |
| External Side Effect | Permission to take an action with effects outside SquirrelForge. |

Each capability is declared and evaluated independently; holding one does not imply another.

---

## Process

1. Register a permission declaration for an actor, action, resource, and duration.
2. Validate the declaration against `23_GOVERNANCE/POLICY-ENGINE.md`.
3. Store the declaration in the active permission set.
4. Make the declaration available for `24_SECURITY/AUTHORIZATION-MANAGER.md` to evaluate at runtime.
5. Expire or revoke declarations when duration lapses or governance requires it.
6. Record every change to the permission set for audit.

---

## Permission Boundary

Permissions may declare, scope, version, and expire the static actor/action/resource/duration policy that governs what is allowed.

It must not evaluate a specific runtime request or issue a grant/deny decision — that remains owned by `24_SECURITY/AUTHORIZATION-MANAGER.md`, which treats this file as configuration input rather than a decision authority.

---

## Domain Rule

Permission declarations apply identically regardless of domain; domain-specific access rules — for example WordPress capability checks — are owned by the relevant domain layer (`38_WORDPRESS/SECURITY-VALIDATOR.md`), not declared here.

---

## Rule

No actor may be granted an action on a resource that is not explicitly declared in this configuration; absence of a matching declaration is treated as denial.
