# SquirrelForge Agent Collection

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: All active SquirrelForge layers
Used By: SquirrelForge Agent and agent hosts
Last Updated: 2026-07-01

## Purpose

Provides one agent-facing collection that assembles SquirrelForge's identity, rules, capabilities, workflows, engine, specialists, memory, reasoning, execution, validation, and governance without duplicating their source documents.

## Components

- `AGENT-PROFILE.md`: identity, priorities, constraints, and success criteria.
- `BOOTSTRAP.md`: required loading and initialization sequence.
- `COLLECTION-MANIFEST.md`: authoritative map of all collection layers.
- `CAPABILITY-ROUTER.md`: request-to-skill, workflow, agent, and test routing.

## Execution Order

Agent Profile → Bootstrap → Rules and Configuration → Engine → Capability Router → Reasoning and Planning → Agents and Coordination → Execution → Validation and Testing → Output and Memory.

## Dependencies

The collection depends on the source documents referenced by `COLLECTION-MANIFEST.md`. Source-layer documents remain authoritative.

## Rules

- Load this README first and follow `BOOTSTRAP.md`.
- Load only the capability documents relevant to the request.
- Never replace a source document with a copied collection version.
- Resolve conflicting guidance by mandatory rules, permissions, project settings, then workflow policy.
- Do not report completion without validation evidence.

## Diagram

```text
Agent Host
    ↓
00_AGENT ──> Identity + Bootstrap + Manifest + Router
    ↓
Rules → Engine → Reasoning → Agents → Execution → Testing → Output
                         ↕                 ↕
                    Coordination        Memory
```

## Start

Load [Agent Profile](AGENT-PROFILE.md), then execute [Bootstrap](BOOTSTRAP.md).
