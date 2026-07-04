# SquirrelForge Templates Layer

Version: 1.0.0
Status: Stable
Owner: Documentation Maintainers
Depends On: `01_RULES`, `02_WORKFLOWS`, `21_CONFIGURATION`, `23_GOVERNANCE`
Used By: Engine, Workflows, Agents, Communication, Governance, Releases
Last Updated: 2026-07-04

## Purpose

The Templates Layer provides reusable, governed starting points for repeatable SquirrelForge artifacts.

Templates improve consistency without replacing reasoning, project context, validation, or domain-specific requirements.

---

## Layer Boundary

`15_TEMPLATES` owns:

- reusable artifact structures,
- template metadata,
- placeholder conventions,
- template selection guidance,
- template validation requirements,
- and reusable project scaffolds.

`15_TEMPLATES` does not own:

- workflow selection,
- project-specific content,
- execution,
- testing implementation,
- governance approval,
- domain knowledge,
- or final artifact state.

A copied template becomes a project artifact only after it is instantiated, completed, and validated in the appropriate workflow.

---

## Template Categories

Templates may support:

- planning,
- architecture,
- requirements,
- task definitions,
- handoffs,
- decisions,
- issue reports,
- pull requests,
- validation reports,
- release documents,
- incident and recovery records,
- communication artifacts,
- project scaffolds,
- and domain-specific artifacts.

The authoritative roster must match files and directories that actually exist in this layer.

---

## Template Lifecycle

```text
Need Identified
   ↓
Template Selected
   ↓
Template Copied or Instantiated
   ↓
Project Context Applied
   ↓
Placeholders Resolved
   ↓
Applicable Rules and Domain Requirements Applied
   ↓
Artifact Validated
   ↓
Artifact Used or Published
```

---

## Selection Rule

Select a template based on:

- requested outcome,
- active workflow,
- artifact type,
- project context,
- active domain,
- governance requirements,
- and validation needs.

Do not use a template merely because its name appears similar to the request.

---

## Source Preservation Rule

Reusable source templates must not be edited for one project instance.

Project-specific work should copy or instantiate the template into the correct project location, then modify the instance.

Changes to the reusable source template must be intentional improvements to the template itself and should be reviewed as shared architecture changes.

---

## Placeholder Rule

Unresolved required placeholders block completion or release of the instantiated artifact.

Placeholders should be:

- clearly identifiable,
- documented when their meaning is not obvious,
- removed or resolved before completion,
- and validated by the applicable workflow or checklist.

Unknown information must not be replaced with invented values merely to remove a placeholder.

---

## Domain Rule

Domain-specific templates load only when the active request requires that domain.

For WordPress work, templates may be combined with relevant `38_WORDPRESS` guidance and WordPress rules.

General project work must not inherit WordPress-specific template requirements automatically.

---

## Validation Rule

An instantiated template is not complete merely because all sections contain text.

Validation should confirm, when applicable:

- required sections are present,
- placeholders are resolved,
- content matches current project evidence,
- links and references are valid,
- terminology matches the glossary and architecture,
- applicable rules are satisfied,
- domain requirements are satisfied,
- and the artifact meets its workflow acceptance criteria.

---

## Rule

> Templates provide governed structure, not automatic correctness. Every instantiated artifact must be adapted to verified project context and validated before use or release.
