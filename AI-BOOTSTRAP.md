# SquirrelForge AI Bootstrap

Status: Stable
Owner: SquirrelForge Maintainers
Applies To: External AI coding agents and agent hosts

## Purpose

This file is the vendor-neutral entry point for external AI agents working inside the SquirrelForge repository.

SquirrelForge is the controlling Agent system for this repository. An external AI agent must use the SquirrelForge managers and routing documents before changing project files when SquirrelForge defines an applicable path.

This file identifies the startup chain and delegation points. It does not replace the detailed rules owned by the SquirrelForge layers.

---

## Source-of-Truth Hierarchy

When instructions overlap, resolve them in this order:

1. Direct user instructions for the current task.
2. Mandatory safety, permission, and platform constraints from the active agent host.
3. This repository bootstrap file.
4. SquirrelForge authoritative managers and layer documents.
5. Domain-specific managers, Skills, Knowledge, Roles, standards, and validation files selected by routing.

Do not copy detailed domain, security, workflow, Skill, Knowledge, Role, or validation rules into this file. Reference the owning documents instead.

---

## Required Startup Chain

Before project-changing execution, load and follow:

1. [`README.md`](README.md)
2. [`00_CORE/SYSTEM-ORCHESTRATOR.md`](00_CORE/SYSTEM-ORCHESTRATOR.md)
3. [`12_AGENT/README.md`](12_AGENT/README.md)
4. [`12_AGENT/BOOTSTRAP.md`](12_AGENT/BOOTSTRAP.md)

The Agent Bootstrap determines request intake, rule loading, capability routing, domain loading, planning, permission review, execution, verification, and completion requirements.

---

## Domain Routing

Do not assume a domain-specific path before routing.

After startup, determine the request domain through the Agent and Engine routing documents selected by [`12_AGENT/BOOTSTRAP.md`](12_AGENT/BOOTSTRAP.md). Only then load the relevant domain manager and supporting files.

For WordPress work, route through:

- [`38_WORDPRESS/WORDPRESS-MANAGER.md`](38_WORDPRESS/WORDPRESS-MANAGER.md)

WordPress implementation must then use the authoritative WordPress pipeline, Skill routing, Knowledge selection, Role routing, standards, security validation, documentation, and release review documents selected by the WordPress Manager.

---

## Requirements and Planning Gate

Before implementation, identify whether material requirements are missing.

If material requirements are missing, ask for them or propose explicit defaults for user approval. Do not implement until the requirements and execution plan are clear enough to validate the result.

For routed work, the plan must include the applicable:

- domain decision,
- primary Skill,
- supporting Skills,
- Knowledge files,
- Role chain,
- implementation steps,
- validation gates,
- documentation expectations,
- release-readiness expectations,
- and stop conditions.

---

## Execution Rules

- Do not use direct request-to-code behavior when SquirrelForge defines a routing and planning path.
- Preserve unrelated repository files and user changes.
- Inspect relevant files before editing them.
- Modify only files in scope for the approved plan.
- Use the execution, recovery, testing, documentation, and release requirements selected by the authoritative SquirrelForge managers.
- Do not mark work complete without validation evidence or a clear explanation of validation that could not be run.

---

## Required Final Report

At completion, report:

- changed files,
- implementation summary,
- validation performed,
- validation failures or skipped checks,
- unresolved risks or assumptions,
- documentation updates,
- release-readiness status,
- and whether the work is complete.
