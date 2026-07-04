# SquirrelForge Agents Layer

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `12_AGENT`, `14_ENGINE`, `17_COORDINATION`, `19_REASONING`, `20_EXECUTION`, `22_INTERFACES`
Used By: Coordination, Execution, Review, Governance, Release
Last Updated: 2026-07-04

## Purpose

The Agents Layer defines specialized roles that can own, review, or support bounded work inside the SquirrelForge architecture.

Specialist agents execute role-specific responsibilities through the Engine, Coordination, Execution, Testing, Security, Governance, and domain layers. They do not replace the general Agent Layer or bypass lifecycle controls.

---

## Layer Boundary

`16_AGENTS` owns:

- specialist role definitions,
- role responsibilities,
- role permissions,
- role boundaries,
- role handoff expectations,
- role validation obligations,
- and role-specific completion expectations.

`16_AGENTS` does not own:

- agent bootstrap,
- workflow selection,
- task decomposition,
- action dispatch,
- tool execution,
- security policy,
- governance policy,
- persistent memory,
- or domain knowledge.

Those responsibilities remain in their source layers.

---

## Role Categories

Specialist roles may include:

- Architect,
- Planner,
- Developer,
- Reviewer,
- Security Reviewer,
- Performance Reviewer,
- Accessibility Reviewer,
- Documentation Agent,
- Release Agent,
- Recovery Agent,
- Integration Agent,
- Automation Agent,
- AI Driver Agent,
- and domain-specific support roles.

The authoritative role roster must match the `AGENT-*.md` files that actually exist in this directory.

---

## Normal Work Order

```text
Goal and Context
   ↓
Architect / Planner
   ↓
Developer / Implementer
   ↓
Reviewer
   ↓
Security / Performance / Accessibility Review as needed
   ↓
Documentation
   ↓
Release or Completion Report
```

This order may change for read-only work, recovery work, documentation-only work, or governed workflow requirements.

---

## Ownership Rule

One agent owns a task at a time.

An agent may support another agent, but support does not transfer ownership unless a handoff is recorded.

A task owner is responsible for:

- understanding the active goal,
- staying within scope,
- preserving user work,
- respecting permissions,
- producing the expected output,
- recording material decisions,
- and ensuring required validation evidence is produced or routed.

---

## Handoff Rule

Every handoff must include:

- active goal,
- task scope,
- current state,
- changed artifacts,
- relevant context,
- active rules,
- dependencies,
- blockers,
- risks,
- acceptance criteria,
- validation requirements,
- and next expected action.

A handoff is invalid if the receiving agent cannot determine what has changed and what remains to be done.

---

## Permission Rule

Agents may not exceed their role permissions, active execution boundary, or user-granted authority.

High-risk, destructive, external, production, deployment, secret-handling, or user-data actions require the applicable Security, Governance, Execution, and Permission controls before work proceeds.

---

## Routing Rule

The Engine and Coordination layers route tasks to specialist agents based on:

- requested outcome,
- task type,
- active domain,
- risk level,
- required permissions,
- required tools and interfaces,
- required validation,
- agent capability,
- availability,
- and ownership constraints.

Agents must not self-assign work that has not been routed or authorized when routing controls are active.

---

## Domain Rule

Domain-specific work requires the relevant domain context.

For WordPress work, the selected agent must have access to applicable `38_WORDPRESS` references and WordPress rules.

For non-WordPress work, WordPress-specific responsibilities must not be assumed.

---

## Validation Rule

Agents must not mark work complete merely because they performed their assigned action.

Completion requires applicable validation evidence or an explicit report that validation failed, was unavailable, was waived, or was not applicable.

---

## Role Specification Standard

Each `AGENT-*.md` role specification should define:

- purpose,
- responsibilities,
- inputs,
- outputs,
- permissions,
- prohibited actions,
- handoff requirements,
- validation obligations,
- escalation conditions,
- and completion criteria.

---

## Rule

> Specialist agents own bounded role-specific work, but they operate inside the SquirrelForge lifecycle. No specialist role may bypass rules, permissions, validation, coordination, or governance controls.
