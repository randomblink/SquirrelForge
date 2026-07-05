# SquirrelForge Agent Orchestrator

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/README.md`, `src/Agent/AgentOrchestrator.php`, `src/Agent/AgentRegistry.php`, `src/Agent/PipelineStages.php`
Used By: Coordination, Reporting, Governance
Last Updated: 2026-07-05

## Purpose

The Agent Orchestrator runs the registered pipeline agents through the fixed handoff sequence in `16_AGENTS/README.md`'s Normal Work Order — Architect, Planner, Developer, Reviewer, Security, Performance, Documentation, Release — executing exactly one agent per stage and following the `next_stage` that stage's own agent declares, until a stage produces none.

The Orchestrator follows declared handoffs; it does not decide whether a stage's output is acceptable. Each stage agent (Reviewer, Security, Performance, Documentation, Release, and the rest, all cleaned earlier in this pass) determines its own outcome status and `next_stage` value; the Orchestrator only executes agents in sequence and reports what happened. It does not wait on asynchronous work — each stage executes synchronously within the same run.

---

## Responsibilities

The Orchestrator must:

- start from the first stage in the fixed sequence,
- look up exactly one registered agent for the current stage,
- execute that stage's agent with the accumulated context and record its result,
- follow the `next_stage` the stage agent itself declares,
- stop the run immediately when a stage produces no `next_stage`, or when no agent is registered for the current stage,
- confirm at startup that every stage in the fixed sequence has a registered agent,
- and report the full trace, accumulated history, final status, and whether the pipeline reached a completed release.

---

## Inputs

The Orchestrator should receive:

- the initial context (the goal and any fields the first stage requires),
- the registered agents for every pipeline stage,
- and, for a re-entrant run, an existing `history` to continue from.

A run must not proceed past a stage that produced no `next_stage`, and must not skip a stage in the fixed sequence.

---

## Outputs

The Orchestrator should produce:

- `trace`: each executed stage's result, in execution order,
- `history`: each stage's result keyed by stage name,
- `status`: the last executed stage's own reported status, or `Blocked` if no agent was found for the current stage,
- and `complete`: `true` only when the last executed stage is `release` and its status is `Ready`.

---

## Orchestration Process

1. Start at the first stage in the fixed sequence (`architect` by default).
2. Look up the registered agent that supports the current stage.
3. If none is found, record a `Blocked` trace entry with the reason and stop.
4. Execute the stage agent with the accumulated context and record its result in `history` and `trace`.
5. Read the `next_stage` the stage agent declared in its result.
6. If `next_stage` is present, repeat from step 2 for that stage; otherwise stop.
7. Report `trace`, `history`, the final `status`, and `complete`.

---

## Handoff Sequence

```text
Architect
    ↓
Planner
    ↓
Developer
    ↓
Reviewer
    ↓
Security
    ↓
Performance
    ↓
Documentation
    ↓
Release
```

This is the canonical, ordered stage list in `src/Agent/PipelineStages.php`. The Orchestrator does not reorder it; a stage agent that needs a different next stage declares one explicitly through its own `next_stage`.

---

## Managed Agents

| Agent | Stage Key | Specification |
|---|---|---|
| Agent Architect | `architect` | `16_AGENTS/AGENT-ARCHITECT.md` |
| Agent Planner | `planner` | `16_AGENTS/AGENT-PLANNER.md` |
| Agent Developer | `developer` | `16_AGENTS/AGENT-DEVELOPER.md` |
| Agent Reviewer | `reviewer` | `16_AGENTS/AGENT-REVIEWER.md` |
| Agent Security | `security` | `16_AGENTS/AGENT-SECURITY.md` |
| Agent Performance | `performance` | `16_AGENTS/AGENT-PERFORMANCE.md` |
| Agent Documentation | `documentation` | `16_AGENTS/AGENT-DOCUMENTATION.md` |
| Agent Release | `release` | `16_AGENTS/AGENT-RELEASE.md` |

---

## Orchestrator-Level Status

The Orchestrator does not define its own status vocabulary. The `status` it reports is authored entirely by whichever stage executed last, using that stage's own outcome states (for example Reviewer's `APPROVED`/`REVISION_REQUIRED`, Release's `Ready`/`Hold`).

The one status the Orchestrator originates itself is `Blocked` — reported only when no agent is registered for the current stage, distinct from any stage agent's own reported outcome.

---

## Permission Boundary

The Orchestrator may look up and execute registered stage agents in the fixed sequence and follow the `next_stage` each declares.

It must not decide a stage's outcome, validate a handoff's correctness, retry a failed stage, or reorder the fixed sequence itself. Parallel work, rerouting, and recovery beyond this linear sequence belong to `17_COORDINATION` and `14_ENGINE/TASK-ROUTER.md`.

---

## Domain Rule

The fixed handoff sequence applies identically to WordPress and non-WordPress work; domain-specific context is loaded by each stage agent per its own Domain Rule, not by the Orchestrator.

---

## Rule

> Only one agent owns a stage at a time, and every transfer between stages follows the `next_stage` that stage's own agent explicitly declared. The Orchestrator executes the fixed sequence and reports what happened — it does not judge whether a stage's result was good enough to proceed.
