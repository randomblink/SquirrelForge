# SquirrelForge Architecture

## Purpose

SquirrelForge is a modular AI agent architecture for planning, reasoning, executing workflows, observing outcomes, learning from results, and improving future behavior under governance and security controls.

This document provides the top-level view of how the system is organized after the numbered-layer restructure.

---

## Core Architecture

SquirrelForge is organized into numbered architectural layers. Each layer owns a distinct responsibility and should expose clear contracts to the layers that depend on it.

| Layer | Responsibility |
|---|---|
| `00_CORE` | System coordination, startup, lifecycle, and orchestration. |
| `01_RULES` | Mandatory behavior, project standards, and operating constraints. |
| `02_WORKFLOWS` | Repeatable procedures for development, review, testing, release, and recovery. |
| `03_CHECKLISTS` | Verifiable completion, readiness, and quality criteria. |
| `11_OVERVIEW` | System overview, vocabulary, and lifecycle entry point. |
| `12_AGENT` | Agent identity, agent rules, operating profile, and capabilities. |
| `13_SKILLS` | Reusable skills for planning, implementation, review, and support work. |
| `14_ENGINE` | Goal interpretation, workflow selection, task planning, and routing. |
| `15_TEMPLATES` | Governed starting points for reusable project artifacts. |
| `16_AGENTS` | Specialized agent roles such as architect, planner, developer, reviewer, security, performance, documentation, and release. |
| `17_COORDINATION` | Task priority, ownership, handoffs, queueing, and conflict resolution. |
| `18_MEMORY` | Active context, execution history, durable memory, and reusable knowledge. |
| `19_REASONING` | Decision-making, rule evaluation, risk assessment, strategy, explanation, and self-assessment. |
| `20_EXECUTION` | Controlled action dispatch, checkpoints, rollback, and execution logging. |
| `21_CONFIGURATION` | Runtime policy, project settings, environment configuration, and operational controls. |
| `22_INTERFACES` | Stable contracts, schemas, events, and integration boundaries between layers. |
| `23_GOVERNANCE` | Versioning, change control, quality gates, lifecycle rules, and deprecation policy. |
| `24_SECURITY` | Security architecture, access control, threat handling, and operational safety. |
| `25_KNOWLEDGE` | Knowledge acquisition, validation, storage, retrieval, and use. |
| `26_INTEGRATIONS` | External systems, APIs, tools, AI providers, and automation platforms. |
| `27_OBSERVABILITY` | Logging, metrics, tracing, diagnostics, dashboards, and alerts. |
| `28_RUNTIME-CONFIG` | Environment profiles, feature flags, secrets, runtime options, and configuration validation. |
| `29_TESTING` | Test strategy, test levels, regression checks, and test reporting. |
| `30_LEARNING` | Feedback, evaluation, experience tracking, pattern discovery, and governed improvement. |
| `32_OPTIMIZATION` | Evidence-based performance, cost, resource, and workflow improvements. |
| `33_AUTOMATION` | Approved automatic work triggered by schedules, events, rules, and conditions. |
| `34_AIDRIVER` | AI reasoning driver, model coordination, prompt execution, and provider control. |
| `35_RESILIENCE` | Failure detection, retries, recovery, graceful degradation, and continuity. |
| `36_COMMUNICATION` | Messages, notifications, summaries, responses, and communication protocols. |
| `37_STORAGE` | Storage, retrieval, replication, retention, archival, and disposal of data. |
| `38_WORDPRESS` | WordPress-specific knowledge for plugins, themes, blocks, REST APIs, WooCommerce, and WordPress products. |

The numbering intentionally leaves gaps for future layers. New layers must not be inserted casually; they require an architecture decision and cross-reference update.

---

## Runtime Implementation

The repository also contains a PHP reference implementation under `src/` with tests under `tests/`.

The documentation layers describe the architecture. The PHP runtime implements selected parts of that architecture.

These two parts must stay aligned, but they are not the same thing:

- Documentation defines responsibilities, rules, contracts, workflows, and expected behavior.
- Runtime code implements executable components, adapters, services, agents, and tests.

When the documentation and runtime disagree, the discrepancy must be recorded and resolved rather than ignored.

---

## Primary Request Lifecycle

```text
User Request
   ↓
Input Interpretation
   ↓
Rule and Context Loading
   ↓
Validation
   ↓
Reasoning
   ↓
Planning
   ↓
Workflow Selection
   ↓
Permission and Risk Review
   ↓
Execution
   ↓
Testing and Verification
   ↓
Observability
   ↓
Learning
   ↓
Memory Update
   ↓
Response
```

---

## System Rule

No component should act alone.

Every important action must be:

- validated,
- reasoned through,
- executed through an approved workflow,
- observed,
- logged,
- recoverable,
- explainable,
- and governed.

---

## WordPress Boundary

The WordPress layer is currently `38_WORDPRESS`.

WordPress-specific files must not be placed under `32_WORDPRESS`; `32` is reserved for Optimization in the current architecture.

The WordPress layer currently functions as a WordPress Knowledge Base. Any future WordPress Agent boot, execution, plugin, theme, or deployment documents must either:

1. live inside `38_WORDPRESS` as WordPress-specific knowledge or operating references, or
2. live in the appropriate general agent layer and reference `38_WORDPRESS` for WordPress-specific rules.

This prevents the system from mixing layer numbers and duplicating responsibilities.

---

## Key Documents

- `README.md`
- `00_CORE/SYSTEM-ORCHESTRATOR.md`
- `12_AGENT/README.md`
- `14_ENGINE/README.md`
- `19_REASONING/README.md`
- `20_EXECUTION/ACTION-DISPATCHER.md`
- `27_OBSERVABILITY/OBSERVABILITY-MANAGER.md`
- `30_LEARNING/LEARNING-MANAGER.md`
- `38_WORDPRESS/README.md`

---

## Cleanup Rules

During cleanup, SquirrelForge must follow these rules:

1. The root `README.md` is the top-level repository map.
2. `ARCHITECTURE.md` is the top-level architecture explanation.
3. Each numbered layer README is authoritative for that layer's purpose and roster.
4. Cross-references must point to existing files.
5. Layer numbers must not be reused for different domains.
6. New documents must be placed in the layer that owns their responsibility.
7. WordPress-specific guidance belongs in `38_WORDPRESS` unless it is a general agent/runtime concern.
8. Stale references must be corrected or removed.
9. Empty placeholders must either be completed or explicitly marked as placeholders.
10. Runtime claims must match the actual `src/` and `tests/` state.

---

## Completion Criteria

This file is complete when it:

- explains the current numbered layer architecture,
- distinguishes documentation layers from runtime implementation,
- defines the primary request lifecycle,
- identifies the WordPress layer boundary,
- points readers to the key architecture documents,
- and provides cleanup rules for keeping the system consistent.
