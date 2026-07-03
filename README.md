# SquirrelForge

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

The platform is organized into specialized layers.

| Layer | Purpose |
|---|---|
| 00 Core | System coordination and orchestration |
| Input | Receive and normalize requests |
| Validation | Verify requests and enforce rules |
| Reasoning | Evaluate goals, risks, and decisions |
| Planning | Build execution plans |
| Workflow | Select and manage workflows |
| Execution | Execute approved actions |
| Tools | Connect external services and capabilities |
| Knowledge | Provide structured information and retrieval |
| Memory | Store and retrieve contextual information |
| Observability | Logging, metrics, tracing, and diagnostics |
| Learning | Improve from outcomes and feedback |
| Security | Authentication, authorization, and protection |
| Governance | Policies, compliance, and oversight |
| Response | Build and deliver final responses |

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
01_INPUT/
02_VALIDATION/
03_REASONING/
04_PLANNING/
05_WORKFLOW/
20_EXECUTION/
21_TOOLS/
22_KNOWLEDGE/
23_MEMORY/
27_LEARNING/
31_OBSERVABILITY/
32_SECURITY/
33_GOVERNANCE/
34_RESPONSE/

ARCHITECTURE.md
README.md
LICENSE
CHANGELOG.md
CONTRIBUTING.md
```

## Documentation

The primary architectural documents are:

- [`ARCHITECTURE.md`](ARCHITECTURE.md)
- [`00_CORE/SYSTEM-ORCHESTRATOR.md`](00_CORE/SYSTEM-ORCHESTRATOR.md)

Each layer contains its own README describing its purpose and components.

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

- Complete architecture specification
- Reference implementation
- Agent runtime
- Plugin and extension framework
- Tool adapter system
- AI provider abstraction
- Workflow engine
- Web administration interface
- Production deployment support

## License

Add the appropriate open-source or commercial license before the first public release.

## Status

SquirrelForge is currently in the architecture and framework development phase. Individual layer specifications are being completed before implementation begins.

The next recommended file is `CONTRIBUTING.md`, which defines coding standards, documentation conventions, naming rules, branching strategy, pull request expectations, testing requirements, and the overall development workflow for anyone contributing to SquirrelForge.
