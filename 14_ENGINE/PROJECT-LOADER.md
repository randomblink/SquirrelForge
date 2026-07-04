# SquirrelForge Project Loader

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `12_AGENT/BOOTSTRAP.md`, `01_RULES`, `21_CONFIGURATION`, `28_RUNTIME-CONFIG`
Used By: Engine, Capability Router, Workflow Selector, Task Router
Last Updated: 2026-07-04

## Purpose

The Project Loader establishes verified project context before planning or project-changing execution begins.

It identifies the project root, project type, repository state, applicable instructions, configuration, available interfaces, domain requirements, and current limitations.

The Project Loader does not select the final implementation strategy or perform project-changing actions.

---

## Loading Process

1. Verify the project root and repository identity.
2. Detect project type and active domains.
3. Inspect current project state and uncommitted work when available.
4. Load root project instructions and mandatory rules.
5. Load project configuration and runtime profile.
6. Discover available interfaces, tools, integrations, and execution limits.
7. Identify relevant domain knowledge without loading unrelated domains.
8. Initialize or restore Engine planning state.
9. Initialize task context with verified evidence and known unknowns.
10. Pass project context to capability routing and workflow selection.
11. Confirm readiness state and publish limitations.

---

## Project Context Record

The Project Loader should produce a structured context record containing, when available:

- project root,
- repository identity,
- active branch or revision,
- project type,
- active domains,
- relevant instructions,
- applicable rules,
- configuration profile,
- runtime environment,
- available tools and interfaces,
- permission boundaries,
- current changes,
- known test commands or validation paths,
- known risks,
- missing context,
- and readiness state.

Unknown values must remain explicitly unknown rather than being guessed.

---

## Core Components

| Component | Location |
|---|---|
| Workflow Selector | `14_ENGINE/WORKFLOW-SELECTOR.md` |
| Task Router | `14_ENGINE/TASK-ROUTER.md` |
| State Manager | `14_ENGINE/STATE-MANAGER.md` |
| Context Manager | `14_ENGINE/CONTEXT-MANAGER.md` |
| Validation | `14_ENGINE/VALIDATION.md` |
| Output Rules | `14_ENGINE/OUTPUT-RULES.md` |

---

## Domain Loading Rule

The Project Loader identifies relevant domains but does not force-load every domain layer.

Examples:

- WordPress plugin or theme project → identify `38_WORDPRESS` as relevant.
- General architecture cleanup → load architecture, overview, Agent, Engine, and affected layer documents.
- Runtime PHP implementation → inspect `src/`, `tests/`, configuration, interfaces, and execution requirements.

WordPress must not be assumed merely because SquirrelForge supports WordPress.

---

## Readiness States

| State | Meaning |
|---|---|
| `READY` | Required project context is available for the requested work. |
| `READY_WITH_LIMITATIONS` | Work may continue, but unavailable tools, tests, interfaces, or context must be disclosed. |
| `BLOCKED` | Required project identity, permission, context, or dependency is missing. |
| `RECOVERY_REQUIRED` | Existing incomplete or unsafe project state must be resolved first. |
| `LOAD_FAILED` | The project could not be loaded and no safe degraded path exists. |

---

## Startup Checklist

- [ ] Project root verified
- [ ] Repository or project identity verified
- [ ] Project type identified
- [ ] Active domains identified
- [ ] Current state inspected
- [ ] Applicable instructions loaded
- [ ] Mandatory rules loaded
- [ ] Configuration and runtime profile loaded where applicable
- [ ] Available interfaces and tools identified
- [ ] Permission boundaries identified
- [ ] Relevant domain knowledge identified
- [ ] State initialized or restored
- [ ] Context initialized with evidence and unknowns
- [ ] Readiness state published

---

## Rule

> A project is ready only when the Project Loader has verified enough context for the requested work and has disclosed any remaining limitations. Readiness must not be inferred from documentation presence alone.
