# SquirrelForge Agent Specialization

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/README.md`, `16_AGENTS/AGENT-COLLABORATION.md`, `12_AGENT/CAPABILITY-ROUTER.md`
Used By: Task Router, Delegation, Governance
Last Updated: 2026-07-05

## Purpose

Agent Specialization confirms that requested work is matched to a role whose documented specialization actually covers it, using `16_AGENTS/README.md`'s Role Categories as the single authoritative roster.

A role's specialization is fully defined by its own `AGENT-*.md` specification — its Purpose, Responsibilities, and Permission Boundary. This document does not maintain a separate qualification-level or capability-profile system (a prior draft described "Generalist / Specialist / Senior Specialist / Expert" tiers and capability inheritance); no other part of the architecture or its runtime implementation defines, checks, or references tiered specialist levels, so that system is not carried forward here. Specialization is a match between required work and a role's documented boundary, not a graded qualification score.

---

## Responsibilities

Agent Specialization must:

- identify the domain or subject matter the requested work actually requires,
- match that requirement against `16_AGENTS/README.md`'s Role Categories,
- confirm the matched role's own `AGENT-*.md` Responsibilities and Permission Boundary actually cover the work, not just its name,
- identify when work spans multiple specializations and route it to `16_AGENTS/AGENT-COLLABORATION.md` instead of forcing a single mismatched owner,
- escalate to the Agent Maintainers or Governance when no existing role's specialization covers the work, rather than assigning it to the nearest approximate role,
- and record the specialization match or escalation.

---

## Inputs

Specialization should receive:

- the work to be assigned and the domain or subject matter it requires,
- the current `16_AGENTS/README.md` Role Categories and the `AGENT-*.md` files that actually exist,
- routing context from `12_AGENT/CAPABILITY-ROUTER.md`, when available,
- and any indication the work spans more than one specialization.

A match must not be assumed from a role's name alone; the role's own Responsibilities and Permission Boundary must actually cover the requested work.

---

## Outputs

Specialization should produce:

- the matched role, or roles, for the work,
- confirmation that the match was verified against the role's actual documented boundary,
- a Collaboration referral when the work spans multiple specializations,
- an escalation record when no existing role matches,
- and a record of the decision.

---

## Specialization Process

1. Identify the domain or subject matter the work actually requires.
2. Match the requirement against `16_AGENTS/README.md`'s Role Categories.
3. Read the matched role's own `AGENT-*.md` Responsibilities and Permission Boundary and confirm they actually cover the work.
4. If the work spans multiple specializations, route it to `16_AGENTS/AGENT-COLLABORATION.md` to define a collaboration structure instead of forcing one owner.
5. If no existing role's documented boundary covers the work, escalate to the Agent Maintainers or Governance to define a new role rather than approximating with a mismatched one.
6. Record the match, referral, or escalation.

---

## Specialization Record

| Field | Description |
|---|---|
| Specialization Match ID | Unique identifier. |
| Work Reference | The task or request being matched. |
| Required Domain | Domain or subject matter identified. |
| Matched Role | Role Category from `16_AGENTS/README.md`. |
| Boundary Verified | Whether the role's actual Responsibilities/Permission Boundary were confirmed to cover the work. |
| Outcome | `Matched`, `Collaboration Required`, or `Escalated — No Matching Role`. |
| Timestamp | Decision time. |

---

## Specialization Principles

- A role's specialization is defined by its own `AGENT-*.md` file, not by a separate registry.
- Matching by role name alone is insufficient; the role's documented Responsibilities and Permission Boundary must actually cover the work.
- Work spanning multiple specializations is a Collaboration case, not a forced single assignment.
- Work matching no existing role is an escalation, not an approximation.
- The authoritative roster is whatever `AGENT-*.md` files actually exist in `16_AGENTS/`, per `16_AGENTS/README.md`.

---

## Permission Boundary

Specialization may match work to an existing role, refer multi-specialization work to Collaboration, and escalate unmatched work.

It must not define a role's Responsibilities or Permission Boundary itself (owned by that role's own `AGENT-*.md`), and must not add or remove roles from the roster — that is a `16_AGENTS/README.md` maintenance change owned by the Agent Maintainers.

---

## Domain Rule

For WordPress work, the required domain may include `38_WORDPRESS`-specific subject matter; the matched role must have documented access to the relevant WordPress context.

For non-WordPress work, WordPress-specific specialization must not be assumed.

---

## Rule

> Work may be assigned only to a role whose actual documented Responsibilities and Permission Boundary cover it. Work spanning multiple specializations goes through Collaboration; work matching no existing specialization is escalated, never forced onto the nearest approximate role.
