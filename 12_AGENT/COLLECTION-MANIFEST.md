# SquirrelForge Agent Collection Manifest

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `00_OVERVIEW/SYSTEM-ARCHITECTURE.md`
Used By: Bootstrap, Project Loader, and agent hosts
Last Updated: 2026-07-01

## Collection Map

| Order | Collection | Source | Load Policy |
|---:|---|---|---|
| 1 | Agent entry | `00_AGENT/` | Always |
| 2 | Architecture and lifecycle | `00_OVERVIEW/` | Always; audit on maintenance tasks |
| 3 | System and project rules | `01_RULES/` | Always; load applicable domain rules |
| 4 | Configuration | `21_CONFIGURATION/` | Always; snapshot before execution |
| 5 | Engine | `14_ENGINE/` | Always; component details as invoked |
| 6 | Interfaces | `22_INTERFACES/` | Load contracts used by the request |
| 7 | Skills | `13_SKILLS/` | Load selected capabilities only |
| 8 | Workflows | `02_WORKFLOWS/` | Load one primary and required supporting workflows |
| 9 | Checklists | `03_CHECKLISTS/` | Load applicable completion checks |
| 10 | Templates | `15_TEMPLATES/` | Load only when producing that artifact |
| 11 | Reasoning | `19_REASONING/` | Load components proportional to decision risk |
| 12 | Specialist agents | `16_AGENTS/` | Load assigned roles only |
| 13 | Coordination | `17_COORDINATION/` | Load for queues, handoffs, parallel work, or recovery |
| 14 | Memory | `18_MEMORY/` | Load the index and relevant memory class |
| 15 | Execution | `20_EXECUTION/` | Load for all mutating work |
| 16 | Testing | `24_TESTING/` | Load test levels selected by the test plan |
| 17 | Governance | `23_GOVERNANCE/` | Load for material changes and every release |

## Authority

The manifest organizes documents; it does not override them. A referenced source file is authoritative for its component. `00_OVERVIEW/GLOSSARY.md` defines canonical terminology.

## Context Bundles

### Minimal Read-Only Request

Agent Profile + Rules + Defaults + Project Loader + relevant skill/workflow + Output Rules.

### Implementation Request

Minimal bundle + planning, reasoning, developer agent, execution, checkpoints, applicable tests, and checklists.

### Release Request

Implementation bundle + reviewer roles, release workflow, governance, full reporting, release templates, and archive policy.

### Recovery Request

Agent Profile + Rules + current state + execution log + checkpoint and rollback managers + failure recovery + validation.
