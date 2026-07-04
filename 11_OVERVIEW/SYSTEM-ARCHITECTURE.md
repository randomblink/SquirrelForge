# SquirrelForge System Architecture

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `README.md`, `ARCHITECTURE.md`
Used By: All layers
Last Updated: 2026-07-04

## Purpose

This document provides the overview-layer summary of the SquirrelForge system architecture.

The root `ARCHITECTURE.md` remains the top-level architecture document. This file gives the overview layer a concise, aligned version of the same system model.

---

## Architecture Model

SquirrelForge is organized as a numbered, layered AI agent architecture.

The system separates:

- rules from workflows,
- reasoning from execution,
- agent identity from domain knowledge,
- runtime configuration from source-layer documentation,
- validation from implementation,
- and WordPress-specific knowledge from the general agent system.

This separation keeps the system maintainable and prevents one layer from silently owning responsibilities that belong elsewhere.

---

## Primary Layer Groups

| Group | Layers | Purpose |
|---|---|---|
| Foundation | `00_CORE`, `01_RULES`, `02_WORKFLOWS`, `03_CHECKLISTS` | Core orchestration, mandatory rules, repeatable procedures, and completion criteria. |
| Orientation | `11_OVERVIEW`, `12_AGENT`, `13_SKILLS`, `14_ENGINE`, `15_TEMPLATES` | System overview, agent entry, reusable capabilities, task interpretation, and reusable artifacts. |
| Work Roles | `16_AGENTS`, `17_COORDINATION` | Specialist roles, ownership, handoffs, queues, and conflict management. |
| Intelligence | `18_MEMORY`, `19_REASONING`, `25_KNOWLEDGE`, `30_LEARNING`, `34_AIDRIVER` | Context, decisions, knowledge, improvement, and AI-driver behavior. |
| Execution | `20_EXECUTION`, `21_CONFIGURATION`, `22_INTERFACES`, `26_INTEGRATIONS`, `28_RUNTIME-CONFIG`, `37_STORAGE` | Controlled action, settings, contracts, external tools, runtime profiles, and persistence. |
| Control | `23_GOVERNANCE`, `24_SECURITY`, `27_OBSERVABILITY`, `29_TESTING`, `35_RESILIENCE` | Quality gates, safety, diagnostics, validation, and recovery. |
| Improvement and Operations | `32_OPTIMIZATION`, `33_AUTOMATION`, `36_COMMUNICATION` | Optimization, scheduled or event-driven work, and communication. |
| Domain Knowledge | `38_WORDPRESS` | WordPress-specific engineering knowledge and guidance. |

---

## Request Lifecycle

```text
User Request
   ↓
Agent Bootstrap
   ↓
Rule and Context Loading
   ↓
Project Loading
   ↓
Capability Routing
   ↓
Domain Knowledge Loading
   ↓
Reasoning and Risk Review
   ↓
Planning and Workflow Selection
   ↓
Permission Review
   ↓
Execution
   ↓
Validation and Testing
   ↓
Observability and Diagnostics
   ↓
Learning and Memory Update
   ↓
Response
```

---

## Dependency Rule

Layers may depend on documented upstream outputs and interface contracts.

Layers must not rely on another layer's hidden internal state.

A layer may reference another layer's responsibility, but it must not duplicate that responsibility unless the duplication is explicitly documented as a summary or routing aid.

---

## Domain Rule

Domain knowledge must remain scoped to its domain.

For example, WordPress-specific behavior belongs in `38_WORDPRESS` and `01_RULES/WORDPRESS-RULES.md`.

The Agent Layer may route to WordPress knowledge, but it must not become a copied WordPress handbook.

---

## Runtime Rule

Architecture documents and runtime code must stay aligned.

Documentation layers describe responsibilities, contracts, rules, workflows, and expected behavior.

Runtime code under `src/` implements selected executable parts of that architecture.

When runtime and documentation disagree, the discrepancy must be recorded and resolved instead of ignored.

---

## Rule

> SquirrelForge must preserve clear layer ownership: each layer owns its responsibility, exposes documented contracts, and avoids silently absorbing another layer's job.
