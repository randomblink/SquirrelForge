# SquirrelForge

[![Tests](https://github.com/randomblink/SquirrelForge/actions/workflows/tests.yml/badge.svg)](https://github.com/randomblink/SquirrelForge/actions/workflows/tests.yml)

A modular, explainable, observable AI agent architecture.

## Overview

SquirrelForge is a layered framework for building intelligent AI agents that can reason, plan, execute workflows, interact with tools, learn from experience, and operate under governance and security controls.

Rather than being tied to a specific large language model or AI provider, SquirrelForge separates intelligence from orchestration. This allows the platform to support multiple AI providers, local models, traditional software components, and future technologies without changing its overall architecture.

The project is designed around clear responsibilities, modular components, and complete traceability from user input to final response.

## Design Goals

SquirrelForge is designed to be:

- Modular
- Explainable
- Observable
- Secure
- Extensible
- Provider-independent
- Event-driven
- Auditable
- Maintainable

## Architecture

The platform is organized into specialized numbered layers. The numbering has intentional gaps (e.g. no 04-10, no 31) reserved for future layers.

| Layer | Purpose |
|---|---|
| `00_CORE` | System coordination and orchestration (control plane, startup, lifecycle) |
| `01_RULES` | Mandatory behavior and project standards |
| `02_WORKFLOWS` | Repeatable procedures for development, review, testing, and release |
| `03_CHECKLISTS` | Verifiable completion and quality criteria |
| `11_OVERVIEW` | Architecture, vocabulary, and lifecycle entry point (this directory) |
| `12_AGENT` | Agent-facing collection of identity, rules, and capabilities |
| `13_SKILLS` | Reusable capabilities for planning, implementation, and review |
| `14_ENGINE` | Goal interpretation, workflow selection, task planning and routing |
| `15_TEMPLATES` | Reusable, governed starting points for project artifacts |
| `16_AGENTS` | Specialized roles: architecture, planning, implementation, review, release |
| `17_COORDINATION` | Task priority, ownership, handoffs, and conflict resolution |
| `18_MEMORY` | Active context, execution history, and reusable knowledge |
| `19_REASONING` | Decision-making, rule compliance, risk management, explanation |
| `20_EXECUTION` | Controlled execution with checkpoints, rollback, and logging |
| `21_CONFIGURATION` | Runtime policy and project settings |
| `22_INTERFACES` | Stable contracts between layers |
| `23_GOVERNANCE` | Versions, changes, quality gates, and deprecation |
| `24_SECURITY` | Security architecture, policies, and operational controls |
| `25_KNOWLEDGE` | Knowledge acquisition, storage, validation, and retrieval |
| `26_INTEGRATIONS` | External systems, APIs, AI providers, and automation platforms |
| `27_OBSERVABILITY` | Logging, metrics, tracing, and diagnostics |
| `28_RUNTIME-CONFIG` | Runtime settings, environment profiles, feature flags, secrets |
| `29_TESTING` | Test planning, levels, regression, and reporting |
| `30_LEARNING` | Turning outcomes and feedback into validated improvements |
| `32_OPTIMIZATION` | Performance, cost, and resource improvements from evidence |
| `33_AUTOMATION` | Approved automatic work from schedules, events, and rules |
| `33_WORDPRESS_ROLES` | WordPress-specific specialist roles: architecture, implementation, validation, and release |
| `34_AIDRIVER` | AI reasoning core driving the agent |
| `35_RESILIENCE` | Failure detection, recovery, and safe degradation |
| `36_COMMUNICATION` | Information exchange between users, agents, and systems |
| `37_STORAGE` | Storage, retrieval, replication, versioning, and disposal of data |
| `38_WORDPRESS` | WordPress-specific knowledge for plugins, themes, and REST APIs |

## Request Lifecycle

```text
User
   ↓
Input
   ↓
Validation
   ↓
Reasoning
   ↓
Planning
   ↓
Workflow
   ↓
Execution
   ↓
Observability
   ↓
Learning
   ↓
Memory
   ↓
Response
```

## Core Principles

- Every request is validated.
- Every important decision is explainable.
- Every workflow is observable.
- Every execution is traceable.
- Every failure is diagnosable.
- Every learning update is governed.
- Every component has a single responsibility.
- No component bypasses security controls.

## Repository Structure

```text
00_CORE/
01_RULES/
02_WORKFLOWS/
03_CHECKLISTS/
11_OVERVIEW/
12_AGENT/
13_SKILLS/
14_ENGINE/
15_TEMPLATES/
16_AGENTS/
17_COORDINATION/
18_MEMORY/
19_REASONING/
20_EXECUTION/
21_CONFIGURATION/
22_INTERFACES/
23_GOVERNANCE/
24_SECURITY/
25_KNOWLEDGE/
26_INTEGRATIONS/
27_OBSERVABILITY/
28_RUNTIME-CONFIG/
29_TESTING/
30_LEARNING/
32_OPTIMIZATION/
33_AUTOMATION/
33_WORDPRESS_ROLES/
34_AIDRIVER/
35_RESILIENCE/
36_COMMUNICATION/
37_STORAGE/
38_WORDPRESS/

src/            # PHP runtime (Kernel, Container, Agent, Workflow, ...)
tests/          # PHPUnit test suite

ARCHITECTURE.md
README.md
LICENSE
CHANGELOG.md
CONTRIBUTING.md
CODE-OF-CONDUCT.md
composer.json
phpunit.xml
```

## Documentation

The primary architectural documents are:

- [`ARCHITECTURE.md`](ARCHITECTURE.md)
- [`00_CORE/SYSTEM-ORCHESTRATOR.md`](00_CORE/SYSTEM-ORCHESTRATOR.md)

Each layer contains its own README describing its purpose and components.

## AI Agent Bootstrap

External AI coding agents enter SquirrelForge through [`AI-BOOTSTRAP.md`](AI-BOOTSTRAP.md).

Compatible coding agents may discover [`AGENTS.md`](AGENTS.md) automatically; that file delegates to the vendor-neutral bootstrap.

SquirrelForge then routes work through its internal orchestration, Agent, domain, Skill, Knowledge, Role, execution, validation, documentation, and release-readiness layers.

## Development Philosophy

SquirrelForge emphasizes:

- Clear separation of responsibilities
- Independent, composable components
- Standardized interfaces
- Safe execution
- Continuous observability
- Controlled learning
- Long-term maintainability

## Roadmap

Current focus includes:

- Reference implementation of the Agent role pipeline (Architect, Planner, Developer, Reviewer, Security, Performance, Documentation, Release) with LLM-backed reasoning -- done, see `src/Agent/`
- Plugin and extension framework
- Tool adapter system
- Workflow engine
- Web administration interface
- Production deployment support

## License

The `LICENSE` file exists but is currently empty. Add the appropriate open-source or commercial license before the first public release.

## Status

SquirrelForge has a working PHP reference implementation (`src/`) alongside its architecture specification. The core Kernel/Container/Application runtime, the eight-stage Agent role pipeline with optional LLM reasoning (`src/Agent/`), and a PHPUnit test suite (`tests/`) are in place and passing, enforced automatically by CI (`.github/workflows/tests.yml`) on every push and pull request.

`CONTRIBUTING.md` already exists; see it for coding standards, documentation conventions, naming rules, branching strategy, pull request expectations, and testing requirements.
