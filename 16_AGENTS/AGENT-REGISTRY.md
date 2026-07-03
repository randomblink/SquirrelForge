# SquirrelForge Agent Registry

## Purpose

The Agent Registry serves as the authoritative catalog of every AI agent operating within SquirrelForge. It maintains a unique identity, capability profile, and operational metadata for each agent, serving as the master inventory for the entire multi-agent system.

---

## Responsibilities

- Register new agents.
- Assign unique agent identifiers.
- Maintain agent capability profiles.
- Track agent lifecycle status.
- Record agent ownership and version.
- Provide a central lookup for agent discovery.
- Record registry activity.

---

## Registration Process

1. Receive agent registration request.
2. Validate required agent metadata.
3. Assign a unique Agent ID.
4. Record agent capabilities and specialization.
5. Set initial lifecycle status to `Pending`.
6. Record registration event.
7. Return registration confirmation.

---

## Registry Record

| Field | Description |
|---|---|
| Agent ID | Unique, immutable identifier |
| Name | Human-readable agent name |
| Specialization | Primary role (e.g., Developer, Security) |
| Capabilities | List of approved skills and functions |
| Owner | The component or team responsible for the agent |
| Trust Level | Reliability of the agent's output (e.g., Experimental, Stable) |
| Lifecycle Status | Draft / Pending / Active / Suspended / Retired |
| Version | The current version of the agent's definition |
| Supported Tools | Approved tools the agent can use |

---

## Lifecycle Status

| Status | Meaning |
|---|---|
| Draft | Under development; not available for use |
| Pending | Awaiting final approval and activation |
| Active | Registered and available for assignment |
| Suspended | Temporarily unavailable for assignment |
| Retired | Decommissioned and no longer in use |

---

## Registry Principles

- Every agent must have a single, unique entry.
- Agent IDs are immutable.
- Capabilities must be explicitly defined and approved.
- An agent's status must accurately reflect its availability.
- All changes to the registry must be auditable.
- The registry is the single source of truth for agent identity.

---

## Rule

No agent may be assigned work by the Agent Manager unless it is listed in the Agent Registry with an `Active` lifecycle status and has the required capabilities for the task.