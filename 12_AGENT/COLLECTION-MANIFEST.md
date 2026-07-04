# SquirrelForge Agent Collection Manifest

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `README.md`, `ARCHITECTURE.md`, `12_AGENT/README.md`
Used By: Bootstrap, Project Loader, Capability Router, and agent hosts
Last Updated: 2026-07-04

## Purpose

The Agent Collection Manifest maps the Agent Layer to the authoritative source layers it may load during startup, planning, execution, validation, recovery, and completion.

The manifest is a routing map. It does not replace the source-layer documents.

---

## Collection Map

| Order | Collection | Source | Load Policy |
|---:|---|---|---|
| 1 | Agent entry | `12_AGENT/` | Always |
| 2 | Core orchestration | `00_CORE/` | Always for runtime and lifecycle context |
| 3 | Architecture and overview | `README.md`, `ARCHITECTURE.md`, `11_OVERVIEW/` | Always; audit on maintenance tasks |
| 4 | System and project rules | `01_RULES/` | Always; load applicable domain rules |
| 5 | Configuration | `21_CONFIGURATION/`, `28_RUNTIME-CONFIG/` | Always; snapshot before execution |
| 6 | Engine | `14_ENGINE/` | Always; component details as invoked |
| 7 | Interfaces | `22_INTERFACES/` | Load contracts used by the request |
| 8 | Skills | `13_SKILLS/` | Load selected capabilities only |
| 9 | Workflows | `02_WORKFLOWS/` | Load one primary and required supporting workflows |
| 10 | Checklists | `03_CHECKLISTS/` | Load applicable completion checks |
| 11 | Templates | `15_TEMPLATES/` | Load only when producing that artifact |
| 12 | Reasoning | `19_REASONING/` | Load components proportional to decision risk |
| 13 | Specialist agents | `16_AGENTS/` | Load assigned roles only |
| 14 | Coordination | `17_COORDINATION/` | Load for queues, handoffs, parallel work, or recovery |
| 15 | Memory | `18_MEMORY/` | Load the index and relevant memory class |
| 16 | Execution | `20_EXECUTION/` | Load for all mutating work |
| 17 | Security | `24_SECURITY/` | Load for permissions, secrets, access control, external actions, and risk-sensitive work |
| 18 | Knowledge | `25_KNOWLEDGE/` | Load when retrieved knowledge, facts, or domain references are required |
| 19 | Integrations | `26_INTEGRATIONS/` | Load when external systems, APIs, tools, or providers are involved |
| 20 | Observability | `27_OBSERVABILITY/` | Load for logging, diagnostics, metrics, traces, dashboards, and incident review |
| 21 | Testing | `29_TESTING/` | Load test levels selected by the test plan |
| 22 | Learning | `30_LEARNING/` | Load when feedback, evaluation, or improvement records are required |
| 23 | Optimization | `32_OPTIMIZATION/` | Load when performance, cost, resource, or workflow optimization is requested |
| 24 | Automation | `33_AUTOMATION/` | Load for scheduled, event-driven, rule-driven, or conditional work |
| 25 | AI Driver | `34_AIDRIVER/` | Load when model/provider orchestration, prompt execution, or AI-driver behavior is involved |
| 26 | Resilience | `35_RESILIENCE/` | Load for failure handling, retries, degradation, recovery, and continuity |
| 27 | Communication | `36_COMMUNICATION/` | Load for user messaging, summaries, notifications, and response shaping |
| 28 | Storage | `37_STORAGE/` | Load for persistence, retrieval, replication, retention, archival, and disposal |
| 29 | WordPress | `38_WORDPRESS/` | Load only for WordPress-specific work |
| 30 | Governance | `23_GOVERNANCE/` | Load for material changes, releases, lifecycle decisions, quality gates, and deprecation |

---

## Authority

The manifest organizes documents; it does not override them.

A referenced source file remains authoritative for its component.

When the manifest conflicts with the root `README.md`, `ARCHITECTURE.md`, or a layer README, the conflict must be corrected instead of silently resolved by guesswork.

---

## Loading Rules

- Always load the Agent entry documents before using other layers.
- Always load mandatory rules before planning or execution.
- Load domain-specific documents only when the request requires that domain.
- Load WordPress references from `38_WORDPRESS`, not from a copied Agent document.
- Load execution, security, validation, and governance documents before mutating high-risk project state.
- Do not treat unavailable or missing files as loaded.
- Do not report completion without validation evidence.

---

## Context Bundles

### Minimal Read-Only Request

Agent Profile + Bootstrap + Manifest + mandatory rules + project context + relevant skill or workflow + communication/output rules.

### Documentation Cleanup Request

Minimal bundle + root README + `ARCHITECTURE.md` + affected layer README files + cross-reference targets + applicable checklist.

### Implementation Request

Minimal bundle + planning + reasoning + assigned specialist agent + execution + checkpoints + applicable tests + checklists + security review where needed.

### WordPress Request

Minimal or implementation bundle + relevant `38_WORDPRESS` references + WordPress security and validation expectations.

### Release Request

Implementation bundle + reviewer roles + release workflow + governance + full reporting + release templates + archive policy.

### Recovery Request

Agent Profile + Bootstrap + rules + current state + execution log + checkpoint and rollback managers + failure recovery + validation.

---

## Maintenance Rule

Whenever the repository layer structure changes, this manifest must be updated in the same cleanup pass as the root `README.md`, `ARCHITECTURE.md`, and affected layer READMEs.
