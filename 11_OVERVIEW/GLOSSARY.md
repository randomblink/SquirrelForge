# SquirrelForge Glossary

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `SYSTEM-ARCHITECTURE.md`
Used By: All layers
Last Updated: 2026-07-04

## Purpose

This glossary defines common SquirrelForge terms used across the architecture.

Layer documents may refine these terms for their own scope, but must not silently redefine them in a conflicting way.

---

| Term | Definition |
|---|---|
| Acceptance Criteria | The conditions that must be satisfied for a request, task, milestone, or workflow to be considered complete. |
| Agent | A specialized role that owns a bounded type of work. |
| Agent Host | The runtime or environment that loads and operates an agent. |
| Archive | A retained record of completed work, decisions, results, and reusable knowledge according to policy. |
| Artifact | A file, document, code change, report, configuration, template, or other output produced by work. |
| Bootstrap | The startup sequence that loads identity, rules, manifest, context, permissions, and readiness checks before execution. |
| Capability | A skill, tool, workflow, agent role, or domain reference that can be selected to satisfy a request. |
| Capability Router | The component that maps a request to the correct workflow, agent, skills, tools, domain knowledge, and validation. |
| Checkpoint | A persisted, validated state from which execution may resume. |
| Checklist | A structured list of required checks used to verify readiness, completion, quality, or safety. |
| Configuration | Settings that control behavior, policy, environment, tools, or runtime operation. |
| Context | Information required for the active task. |
| Context Loading | The process of retrieving the relevant files, rules, memory, settings, workflows, and domain references needed for work. |
| Decision | A recorded choice made after considering rules, evidence, risk, and alternatives. |
| Domain Knowledge | Specialized knowledge for a specific field or platform, such as WordPress. |
| Execution | Controlled performance of planned workflows and actions. |
| Execution Boundary | The allowed scope of actions, files, tools, commands, environments, and permissions for a task. |
| Evidence | Verifiable support for a claim, such as inspected files, test output, runtime output, source documentation, or user-provided requirements. |
| Governance | Rules and processes for lifecycle control, quality gates, versioning, approvals, deprecation, and change management. |
| Goal | The requested outcome that directs planning and execution. |
| Handoff | The transfer of task ownership from one agent or component to another with context, status, artifacts, risks, and acceptance criteria. |
| Interface | A stable contract for communication between components or layers. |
| Knowledge | Validated, reusable information independent of one execution. |
| Layer | A numbered architectural area that owns a distinct responsibility. |
| Learning | Governed improvement based on feedback, evaluation, outcomes, and experience records. |
| Lifecycle | The ordered path a request follows from intake through planning, execution, validation, reporting, learning, and retention. |
| Memory | Stored context, history, knowledge, or project-specific decisions. |
| Milestone | A verifiable group of completed tasks representing material progress. |
| Observability | Logging, metrics, tracing, diagnostics, dashboards, and alerts that explain system behavior and outcomes. |
| Permission | An allowed action or access boundary granted by policy, environment, project settings, or the user. |
| Project State | The current condition of a project, including files, configuration, branch, changes, tests, environment, and known issues. |
| Quality Gate | A mandatory condition that must pass before lifecycle progression. |
| Recovery | The process of restoring safe operation after failure, interruption, unsafe state, or incomplete work. |
| Resilience | The system's ability to handle failure through retries, fallback, recovery, graceful degradation, and continuity planning. |
| Risk | The possibility that an action could cause harm, data loss, security exposure, regressions, failed deployment, or user-impacting behavior. |
| Rule | A mandatory constraint that governs behavior, planning, execution, validation, or completion. |
| Skill | A reusable capability for a specific class of work. |
| Strategy | The selected high-level approach for achieving a goal. |
| Task | The smallest independently executable and validatable unit of work. |
| Testing | Evidence-producing checks that confirm behavior, safety, compatibility, quality, or regression status. |
| Validation | Evidence-based confirmation that an output meets its acceptance criteria. |
| Workflow | A repeatable, ordered procedure for a class of requests. |
| WordPress Layer | The `38_WORDPRESS` domain layer that contains WordPress-specific engineering knowledge and guidance. |

---

## Rule

> Use glossary terms consistently across layers. If a layer needs a narrower meaning, it must define the refinement without contradicting the shared definition.
