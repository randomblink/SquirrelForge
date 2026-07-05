# SquirrelForge Agent Registry

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/README.md`, `16_AGENTS/AGENT-LIFECYCLE.md`, `16_AGENTS/AGENT-MONITOR.md`
Used By: Agent Manager, Specialization, Governance
Last Updated: 2026-07-05

## Purpose

The Agent Registry is the authoritative catalog of agent entity identity: a unique identifier, owning role category, owner, version, trust level, and tool provisioning for every agent operating within SquirrelForge.

The Registry owns identity and inventory. It does not own lifecycle state or transitions (owned by `16_AGENTS/AGENT-LIFECYCLE.md`), health classification (owned by `16_AGENTS/AGENT-MONITOR.md`), or capability/domain matching (owned by `16_AGENTS/AGENT-SPECIALIZATION.md` against each role's own `AGENT-*.md`). Where the Registry displays lifecycle state or health, it reflects the current value from those owners rather than maintaining a separate copy.

---

## Responsibilities

The Registry must:

- assign each agent entity a unique, immutable identifier,
- record identity, owning role category, owner, version, and trust level,
- record which tools and interfaces the agent instance is provisioned with,
- provide a central lookup for agent discovery,
- reflect current lifecycle state and health by reference to their owning components, not by independent tracking,
- and record registry activity for audit.

---

## Inputs

The Registry should receive:

- the agent's required identity metadata (name, role category, owner, version),
- the tools and interfaces to provision,
- confirmation the role category matches an existing entry in `16_AGENTS/README.md`,
- and, for display purposes only, the agent's current lifecycle state and health from their owning components.

A registration request without a matching role category must not be registered as if one existed — it is an escalation case for `16_AGENTS/AGENT-SPECIALIZATION.md`.

---

## Outputs

The Registry should produce:

- the assigned, immutable Agent ID,
- the identity record,
- a lookup result for agent discovery,
- and a recorded registry event.

---

## Registration Process

1. Receive the registration request and required identity metadata.
2. Confirm the requested role category matches an existing entry in `16_AGENTS/README.md`; if not, refer to `16_AGENTS/AGENT-SPECIALIZATION.md`'s escalation path rather than registering an undefined category.
3. Assign a unique, immutable Agent ID.
4. Record owner, version, trust level, and provisioned tools.
5. Request the agent's initial lifecycle state from `16_AGENTS/AGENT-LIFECYCLE.md` rather than setting one independently.
6. Record the registration event.
7. Return the registration confirmation.

---

## Registry Record

| Field | Description |
|---|---|
| Agent ID | Unique, immutable identifier. |
| Name | Human-readable agent name. |
| Role Category | Reference to the matching entry in `16_AGENTS/README.md`; the category's actual capabilities are defined by its own `AGENT-*.md`, not duplicated here. |
| Owner | The component or team responsible for the agent. |
| Trust Level | Reliability of the agent's output (for example, Experimental or Stable). |
| Version | Current version of the agent's definition. |
| Supported Tools | Tools and interfaces this agent instance is provisioned with. |
| Current Lifecycle State | Read from `16_AGENTS/AGENT-LIFECYCLE.md`; not independently set here. |
| Current Health | Read from `16_AGENTS/AGENT-MONITOR.md`; not independently set here. |

---

## Registry Principles

- Every agent has a single, unique entry.
- Agent IDs are immutable.
- A role's capabilities are defined by its own `AGENT-*.md`, not duplicated as a separate registry field.
- Lifecycle state and health displayed here are sourced from their owning components, not independently maintained.
- All changes to the registry are auditable.
- The Registry is the single source of truth for agent identity — not for lifecycle state, health, or specialization matching.

---

## Permission Boundary

The Registry may create and update identity records, assign Agent IDs, and read current lifecycle state and health for display and lookup.

It must not transition lifecycle state, classify health, or define capability or specialization matching itself — those remain owned by the Lifecycle Manager, the Monitor, and Specialization respectively.

---

## Domain Rule

For WordPress-specialized agents, the Registry records the role category as usual; applicable `38_WORDPRESS` context requirements are defined by that role's own `AGENT-*.md`, not by the Registry.

---

## Rule

> Every agent entity must have exactly one immutable identity record in the Registry before it may be assigned work. The Registry is the source of truth for identity; it reflects, and never redefines, the lifecycle state, health, and specialization owned elsewhere.
