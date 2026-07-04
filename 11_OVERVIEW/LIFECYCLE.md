# SquirrelForge Lifecycle

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `SYSTEM-ARCHITECTURE.md`, `12_AGENT/BOOTSTRAP.md`
Used By: Engine, Agents, Execution, Testing, Observability, Learning, Governance
Last Updated: 2026-07-04

## Purpose

This document defines the normal request-to-record lifecycle for SquirrelForge work.

The lifecycle ensures that requests move through context loading, routing, reasoning, planning, execution, validation, reporting, and retention in a controlled order.

---

## Lifecycle Flow

```text
Request
   ↓
Bootstrap
   ↓
Intake
   ↓
Context Loading
   ↓
Capability Routing
   ↓
Reasoning and Risk Review
   ↓
Planning
   ↓
Permission Review
   ↓
Execution
   ↓
Validation and Testing
   ↓
Review
   ↓
Reporting
   ↓
Observability Record
   ↓
Learning and Memory Update
   ↓
Archive or Retention
```

---

## Phase 1 — Request

Capture the user goal, requested output, known constraints, and expected result.

The request phase should identify whether the work is:

- read-only,
- planning-only,
- documentation-only,
- project-changing,
- destructive,
- external,
- deployment-related,
- recovery-related,
- automation-related,
- or domain-specific.

---

## Phase 2 — Bootstrap

Load the Agent Profile, Bootstrap, Collection Manifest, mandatory rules, and project context required before work begins.

Project-changing execution must not begin until applicable bootstrap checks pass.

---

## Phase 3 — Intake

Capture:

- goal,
- acceptance criteria,
- target artifacts,
- constraints,
- known risks,
- missing information,
- permissions,
- and expected report format.

If enough evidence exists to proceed safely, the agent should continue rather than stall unnecessarily.

---

## Phase 4 — Context Loading

Load only the context required for the task.

Context may include:

- repository files,
- layer READMEs,
- architecture documents,
- project settings,
- runtime configuration,
- relevant memory,
- workflow documents,
- checklists,
- domain references,
- and validation requirements.

Domain-specific context must remain scoped to the active domain.

---

## Phase 5 — Capability Routing

Use `12_AGENT/CAPABILITY-ROUTER.md` and engine routing documents to select:

- primary workflow,
- lead agent,
- supporting skills,
- domain knowledge,
- tools,
- validation requirements,
- and escalation needs.

There should be one primary route unless the request clearly requires multiple coordinated routes.

---

## Phase 6 — Reasoning and Risk Review

Evaluate:

- rules,
- dependencies,
- tradeoffs,
- user impact,
- security risk,
- data risk,
- production risk,
- reversibility,
- validation needs,
- and rollback needs.

Higher-risk work requires stronger evidence and deeper review.

---

## Phase 7 — Planning

Break the selected route into ordered work steps.

A useful plan should identify:

- what will change,
- where it will change,
- what must be protected,
- what must be validated,
- what can be skipped safely,
- and what completion means.

---

## Phase 8 — Permission Review

Before execution, confirm that planned actions fit the active permission boundary.

Escalate, block, or defer actions that are destructive, privileged, external, deployment-related, or otherwise outside allowed scope.

---

## Phase 9 — Execution

Perform approved actions through the proper Execution layer, tools, workflows, or interfaces.

Execution must preserve unrelated user work and record meaningful checkpoints for recoverability.

---

## Phase 10 — Validation and Testing

Run the validation appropriate to the request and risk level.

If validation cannot be run, the agent must report what was not validated.

No task is complete merely because files were changed.

---

## Phase 11 — Review

Review the result against:

- acceptance criteria,
- rules,
- architecture boundaries,
- security requirements,
- quality gates,
- documentation accuracy,
- and residual risks.

A failed gate returns the work to the earliest responsible phase.

---

## Phase 12 — Reporting

Report the useful result first.

When applicable, the report should include:

- changed files,
- commit identifiers,
- validation evidence,
- unvalidated areas,
- risks,
- blockers,
- and next action.

---

## Phase 13 — Observability Record

Record lifecycle events as appropriate for the environment.

Useful records may include:

- decisions,
- actions,
- checkpoints,
- failures,
- retries,
- validations,
- diagnostics,
- and completion status.

---

## Phase 14 — Learning and Memory Update

Store reusable outcomes only when allowed by memory and retention policy.

Learning updates must be governed and must not override current evidence in future work.

---

## Phase 15 — Archive or Retention

Retain decisions, outcomes, reports, and reusable knowledge according to policy.

Archive records must support future diagnosis, recovery, audit, and improvement.

---

## Recovery Rule

If a lifecycle phase fails, the system must identify the earliest responsible phase and return work there.

Examples:

- failed validation returns to planning or execution,
- missing permission returns to permission review,
- missing context returns to context loading,
- unsafe state returns to recovery,
- unresolved architecture conflict returns to reasoning and planning.

---

## Rule

> Progression requires the prior phase's exit criteria. A failed gate returns work to the earliest responsible phase instead of being hidden or skipped.
