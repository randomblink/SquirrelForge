# SquirrelForge Core Layer

## Purpose

This directory holds the control-plane specification for SquirrelForge: the single document that defines how every other layer starts up, hands off work, communicates, recovers, and shuts down as one governed AI agent.

The Core Layer does not implement domain logic itself. Each domain manager (security, storage, workflow, execution, and so on) remains the authoritative owner of its own state; this layer only defines the shared lifecycle, routing boundaries, identifiers, and invariants that let those managers work together safely.

---

## Component Roster

| Component | Responsibility |
|---|---|
| [`SYSTEM-ORCHESTRATOR.md`](SYSTEM-ORCHESTRATOR.md) | Defines startup order, request lifecycle, cross-layer communication, error propagation, recovery flow, extension points, and global rules for the whole platform. |

---

## Core Rule

No other layer may bypass the lifecycle order, routing boundaries, or global invariants defined in `SYSTEM-ORCHESTRATOR.md`. When a layer's own documentation is silent on a cross-cutting concern (identifiers, health states, error propagation, and similar), `SYSTEM-ORCHESTRATOR.md` is the authority.
