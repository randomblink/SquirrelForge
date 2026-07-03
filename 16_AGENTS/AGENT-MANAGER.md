# SquirrelForge Agent Manager

## Purpose

The Agent Manager serves as the central authority for coordinating AI agents throughout SquirrelForge, ensuring that every agent operates within its approved capabilities, follows governance policies, collaborates safely, and supports workflow execution.

---

## Responsibilities

- Register and manage AI agents.
- Coordinate agent lifecycle events.
- Route work to appropriate agents.
- Verify agent capabilities.
- Coordinate agent communication.
- Supervise delegation and collaboration.
- Record agent activity.
- Report overall agent status.

---

## Agent Management Process

1. Receive agent request.
2. Identify the target agent.
3. Verify agent registration.
4. Confirm agent status.
5. Validate required capabilities.
6. Route work to the selected agent.
7. Record management activity.
8. Return execution status.

---

## Agent Categories

| Category | Description |
|---|---|
| Planner | Strategic planning and decomposition |
| Architect | System and solution design |
| Developer | Code generation and implementation |
| Reviewer | Quality assurance and review |
| Validator | Verification and compliance |
| Security | Security analysis and guidance |
| Knowledge | Retrieval and knowledge management |
| Operations | Deployment, monitoring, and support |

---

## Agent Record

| Field | Description |
|---|---|
| Agent ID | Unique identifier |
| Name | Registered agent name |
| Category | Agent specialization |
| Status | Active / Busy / Idle / Suspended / Retired |
| Capabilities | Approved functions |
| Current Assignment | Active workflow or task |
| Last Activity | Most recent operation |
| Health Status | Operational condition |

---

## Operational Principles

- Every agent has a defined role.
- Capabilities determine assignment eligibility.
- Agent status must be current.
- Work distribution must be traceable.
- Collaboration follows governance policies.
- All agent actions are recorded and auditable.

---

## Rule

Every task assigned within SquirrelForge must be routed through the Agent Manager to a registered, authorized, and capable agent before execution begins.