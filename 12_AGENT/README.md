# SquirrelForge Agent Layer

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: All active SquirrelForge layers
Used By: SquirrelForge Agent hosts and runtime entry points
Last Updated: 2026-07-04

## Purpose

The Agent Layer provides the agent-facing entry point for SquirrelForge.

It assembles the agent profile, bootstrap sequence, collection manifest, and capability routing rules without duplicating the source documents owned by other layers.

This layer answers one question:

> What must an agent load, know, and route before it begins controlled work?

---

## Layer Boundary

`12_AGENT` owns:

- agent identity,
- agent startup sequence,
- agent-facing manifest loading,
- capability routing,
- request-to-workflow routing support,
- and readiness expectations before execution.

`12_AGENT` does not own:

- WordPress domain knowledge,
- general workflow definitions,
- action execution,
- runtime configuration storage,
- security policy,
- governance policy,
- testing infrastructure,
- observability infrastructure,
- persistent memory storage,
- or AI-provider orchestration.

Those responsibilities remain in their respective source layers.

---

## Components

| Component | Responsibility |
|---|---|
| `AGENT-PROFILE.md` | Defines identity, priorities, constraints, operating posture, and success criteria. |
| `BOOTSTRAP.md` | Defines the required loading and initialization sequence before execution. |
| `COLLECTION-MANIFEST.md` | Maps the agent-facing collection to the authoritative source layers. |
| `CAPABILITY-ROUTER.md` | Routes requests to skills, workflows, agents, tools, and validation requirements. |

The component roster must match files that actually exist in this directory.

---

## Execution Order

```text
Agent Profile
   ↓
Bootstrap
   ↓
Rules and Configuration
   ↓
Engine
   ↓
Capability Router
   ↓
Reasoning and Planning
   ↓
Agents and Coordination
   ↓
Execution
   ↓
Validation and Testing
   ↓
Output and Memory
```

---

## Dependencies

The Agent Layer depends on the source documents referenced by `COLLECTION-MANIFEST.md`.

Source-layer documents remain authoritative. The Agent Layer may route to them, summarize their use, or require them to be loaded, but it must not replace them with copied duplicates.

---

## Rules

- Load this README before using the Agent Layer.
- Follow `BOOTSTRAP.md` before project-changing execution.
- Load only the capability documents relevant to the request.
- Never replace a source document with a copied collection version.
- Resolve conflicting guidance by mandatory rules, permissions, project settings, then workflow policy.
- Do not report completion without validation evidence.
- Do not place domain-specific WordPress knowledge here; use `38_WORDPRESS`.

---

## Diagram

```text
Agent Host
    ↓
12_AGENT ──> Identity + Bootstrap + Manifest + Router
    ↓
Rules → Engine → Reasoning → Agents → Execution → Testing → Output
                         ↕                 ↕
                    Coordination        Memory
```

---

## Start

External AI agents enter SquirrelForge through [AI Bootstrap](../AI-BOOTSTRAP.md), which delegates into this layer for the required agent startup sequence.

Load [Agent Profile](AGENT-PROFILE.md), then execute [Bootstrap](BOOTSTRAP.md).
