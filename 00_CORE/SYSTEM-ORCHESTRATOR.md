# SquirrelForge System Orchestrator

## Purpose

The System Orchestrator defines how SquirrelForge's architectural layers operate as one governed AI agent. It coordinates startup, request handling, cross-layer communication, execution, observation, learning, recovery, shutdown, and extension without absorbing the responsibilities of the components it connects.

The System Orchestrator is a control-plane specification. It establishes lifecycle order, routing boundaries, shared identifiers, readiness gates, failure propagation, and global invariants. Domain managers remain authoritative for their own state and operations.

---

## Scope and Authority

The System Orchestrator may:

- Start, stop, and health-check platform managers in dependency order.
- Create correlation, trace, request, workflow, and execution identifiers.
- Route validated requests to the responsible manager.
- Coordinate synchronous decisions and asynchronous events.
- Enforce readiness, security, validation, approval, and governance gates.
- Pause or terminate processing when a required control fails.
- Coordinate recovery without performing domain-specific recovery itself.

The System Orchestrator must not:

- Replace a domain manager as the authoritative owner of state.
- Execute tools, workflows, or storage mutations directly.
- Bypass validation, authorization, approval, or governance.
- Rewrite audit history or hide failure information.
- Treat degraded dependencies as healthy.
- Allow a response to claim success before the applicable review and validation gates confirm the outcome.

---

## Layer Map

| Capability | Primary Authority | Supporting Components |
|---|---|---|
| Core orchestration | `00_CORE` | Agent, Engine, Interfaces |
| Rules | `01_RULES` | Governance, Security, Configuration |
| Workflow catalog | `02_WORKFLOWS` | Engine, Automation, Execution |
| Completion checklists | `03_CHECKLISTS` | Testing, Governance, specialist agents |
| System overview | `11_OVERVIEW` | Root README and Architecture |
| Agent entry and bootstrap | `12_AGENT` | Core, Rules, Engine |
| Reusable skills | `13_SKILLS` | Agents, Workflows, Engine |
| Planning and routing | `14_ENGINE` | Reasoning, AI Driver, Workflows |
| Templates | `15_TEMPLATES` | Workflows, Communication, Governance |
| Agent execution roles | `16_AGENTS` | Agent registry, Skills, Coordination |
| Coordination | `17_COORDINATION` | Communication, Execution |
| Memory | `18_MEMORY` | Storage, Knowledge, Learning |
| Reasoning | `19_REASONING` | AI Driver, Knowledge, Rules |
| Execution | `20_EXECUTION` | Integrations, Automation, Resilience |
| Configuration | `21_CONFIGURATION` | Runtime Config, Security, Governance |
| Interfaces | `22_INTERFACES` | All communicating layers |
| Governance | `23_GOVERNANCE` | Rules, Security, Testing, Audit |
| Security | `24_SECURITY` | Configuration, Runtime Config, Observability |
| Knowledge | `25_KNOWLEDGE` | Memory, Storage, AI Driver |
| Integrations | `26_INTEGRATIONS` | Security, Configuration, Observability |
| Observability | `27_OBSERVABILITY` | Communication, Storage, Resilience |
| Runtime configuration | `28_RUNTIME-CONFIG` | Configuration, Security, Storage |
| Testing | `29_TESTING` | Execution, Governance, domain layers |
| Learning | `30_LEARNING` | Memory, Knowledge, Observability |
| Optimization | `32_OPTIMIZATION` | Observability, Learning, Testing |
| Automation | `33_AUTOMATION` | Execution, Communication, Resilience |
| AI direction | `34_AIDRIVER` | Engine, Reasoning, Integrations, Safety controls |
| Resilience | `35_RESILIENCE` | Execution, Storage, Observability, Security |
| Communication | `36_COMMUNICATION` | Interfaces, Storage, Observability |
| Persistence | `37_STORAGE` | Security, Resilience, Configuration |
| WordPress domain knowledge | `38_WORDPRESS` | Rules, Testing, Security, Workflows |

Some capabilities are logical interfaces until a dedicated runtime component exists. Documentation describing a capability does not prove that a corresponding executable runtime service is implemented.

---

## Global Identifiers

Every request carries a stable identity envelope across all layers.

| Identifier | Scope |
|---|---|
| Request ID | One external or internal request |
| Correlation ID | All operations related to the same user or system intent |
| Trace ID | End-to-end observability trace |
| Conversation ID | Conversation continuity |
| Goal ID | Structured goal produced by goal interpretation |
| Workflow ID | Selected workflow definition |
| Workflow Instance ID | One execution of a workflow |
| Execution ID | One controlled execution lifecycle |
| Task ID | One task within an execution |
| Agent ID | Registered agent identity |
| Message ID | One communication envelope |
| Event ID | One immutable event occurrence |

Identifiers must be propagated, not regenerated, when control crosses a layer. A component may add a child identifier but must retain parent correlation and trace identifiers.

---

## Startup Sequence

Startup is dependency-ordered and fail-closed. A phase may begin only when its required predecessor reports `READY` or an explicitly permitted degraded state.

1. Bootstrap governance and mandatory rules.
2. Initialize configuration and runtime configuration.
3. Initialize security controls.
4. Initialize required storage.
5. Initialize observability and audit paths.
6. Initialize communication paths.
7. Discover approved integrations, tools, skills, and capabilities.
8. Initialize knowledge sources.
9. Initialize compatible memory.
10. Initialize AI Driver and reasoning capabilities when required.
11. Register workflows and agent roles.
12. Initialize execution, automation, optimization, and resilience services that exist in the active runtime.
13. Evaluate readiness and publish limitations.

The platform must not claim an executable service is ready merely because a documentation layer describing that service exists.

---

## Readiness Gate

The platform becomes available for a requested capability only when the requirements for that capability are satisfied.

Readiness may require:

- valid configuration,
- active security controls,
- required storage availability,
- writable audit and logging paths,
- verified communication paths,
- required knowledge or memory availability,
- an approved workflow,
- an available agent or execution owner,
- required tools,
- validation capability,
- and recovery support appropriate to risk.

Optional components may be degraded only when policy defines a safe fallback. The readiness report must identify unavailable capabilities.

---

## Request Lifecycle

```text
Request
  ↓
Agent Bootstrap
  ↓
Intake and Validation
  ↓
Context and Domain Loading
  ↓
Capability Routing
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
Review
  ↓
Observation and Reporting
  ↓
Learning and Memory Update
  ↓
Retention or Archive
```

The canonical lifecycle aligns with `11_OVERVIEW/LIFECYCLE.md`. The Core Layer governs cross-layer progression; individual layers remain authoritative for their own operations.

---

## Failure Propagation

A failed required gate stops dependent progression.

Failure handling must:

1. preserve the failure record,
2. identify the responsible layer,
3. identify the earliest safe recovery phase,
4. preserve recoverable state,
5. prevent false success reporting,
6. invoke rollback or recovery when applicable,
7. and re-run required validation after repair.

The orchestrator coordinates recovery but does not absorb the recovery logic owned by `35_RESILIENCE` or execution components.

---

## Shutdown

Controlled shutdown should:

1. stop accepting new mutating work,
2. allow safe in-flight work to complete or checkpoint,
3. preserve logs and audit records,
4. flush required communication and storage queues,
5. persist allowed memory and learning records,
6. stop dependent services in reverse-safe order,
7. and publish final health and shutdown state.

---

## Extension Rule

New layers, managers, agents, integrations, or domain modules must:

- declare ownership boundaries,
- define dependencies,
- expose documented interfaces,
- define readiness and health behavior,
- define failure and recovery behavior,
- define validation requirements,
- and update the root architecture maps and Agent collection manifest when layer structure changes.

---

## Global Invariants

1. No layer bypasses mandatory rules, permissions, security, validation, or governance gates.
2. The orchestrator coordinates but does not replace domain authorities.
3. Identifiers remain traceable across layer boundaries.
4. Documentation presence does not prove runtime implementation.
5. Degraded capabilities must be reported explicitly.
6. Failure records must not be hidden or rewritten.
7. Completion requires applicable validation evidence.
8. Domain-specific knowledge remains scoped to its domain.
9. WordPress-specific knowledge is owned by `38_WORDPRESS`.
10. Architecture maps must use current layer names and numbers.

---

## Rule

> The System Orchestrator controls lifecycle progression and cross-layer coordination, while each layer remains authoritative for its own state, operations, and evidence.
