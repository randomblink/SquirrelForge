# SquirrelForge Interfaces Layer

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `00_CORE/SYSTEM-ORCHESTRATOR.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `26_INTEGRATIONS`, `16_AGENTS`, `14_ENGINE`, `19_REASONING`, `18_MEMORY`
Last Updated: 2026-07-07

## Purpose

The Interfaces layer defines the versioned, stable contracts consumers use to reach Agent, Workflow, Memory, and Engine operations without coupling directly to the components that actually perform them.

Each interface owns its request/response schema, typed errors, receipts, and correlation references. It does not own the decisions, state, or execution behind those calls — that authority remains with the specialist components each interface fronts.

---

## Layer Boundary

`22_INTERFACES` owns:

- the versioned contract for agent-facing operations — assignment, handoff, cancellation, and state queries (`AGENT-API.md`),
- the versioned contract for workflow-facing operations — selection, initialization, progression, and termination (`WORKFLOW-API.md`),
- the versioned contract for memory-facing operations — storage, retrieval, search, promotion, and archival (`MEMORY-API.md`),
- and the versioned contract for engine-facing operations — request submission, state queries, external input, cancellation, and result retrieval (`ENGINE-API.md`).

`22_INTERFACES` does not own:

- capability-based routing or agent selection (owned by `14_ENGINE/TASK-ROUTER.md`, reached via `16_AGENTS/AGENT-MANAGER.md`),
- workflow selection or step sequencing (owned by `14_ENGINE/WORKFLOW-SELECTOR.md` and `20_EXECUTION/WORKFLOW-EXECUTOR.md`),
- memory storage, retention, or retrieval decisions (owned by `18_MEMORY`),
- execution state or run creation (owned by `00_CORE/SYSTEM-ORCHESTRATOR.md` for identifier creation and `14_ENGINE/STATE-MANAGER.md` for state authority),
- execution itself (owned by `20_EXECUTION/EXECUTION-ENGINE.md`),
- handoff mechanics (owned by `17_COORDINATION/HANDOFF-PROTOCOL.md`),
- validation (owned by `14_ENGINE/VALIDATION.md`),
- and authorization decisions (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md` — every interface call must already carry a valid permission reference).

---

## Components

| Component | Responsibility |
|---|---|
| `AGENT-API.md` | Versioned contract for submitting assignment, handoff, and cancellation requests and querying assignment state. |
| `WORKFLOW-API.md` | Versioned contract for selecting, initializing, progressing, and terminating a workflow run. |
| `MEMORY-API.md` | Versioned contract for storing, retrieving, searching, promoting, and archiving memory records. |
| `ENGINE-API.md` | Versioned contract for submitting engine requests, querying execution state, providing input, cancelling, and retrieving results. |

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
Consumer request
   ↓
Validate request schema and permission reference
   ↓
Invoke the versioned interface (Agent / Workflow / Memory / Engine API)
   ↓
Route to the owning component for the actual decision, state change, or execution
   ↓
Validate response schema
   ↓
Record the correlated event
```

---

## Dependencies

Interfaces depend on:

- `00_CORE/SYSTEM-ORCHESTRATOR.md` for request routing and identifier creation,
- `24_SECURITY/AUTHORIZATION-MANAGER.md` for the permission reference every call must carry,
- and the specialist components each interface fronts (`16_AGENTS`, `14_ENGINE`, `20_EXECUTION`, `18_MEMORY`, `17_COORDINATION`) for the actual decisions and state changes.

---

## State Rule

Interfaces do not own or mutate authoritative state. Every interface call either reads state already owned by `14_ENGINE/STATE-MANAGER.md` and the relevant domain manager, or submits a request that the owning component decides and records.

---

## Domain Rule

Interface contracts apply identically regardless of domain; domain-specific content is carried in the request and response payloads they transport, not interpreted by the interfaces themselves.

---

## Diagram

```text
Consumer → Versioned Interface (Agent/Workflow/Memory/Engine API) → Owning Component → Response
```

---

## Rule

> Interfaces are versioned; breaking changes require a major version and migration path. Every interface call must carry a valid permission reference and correlation ID and must be treated as a request, not a decision — interfaces return what the owning component decided, they do not decide.
