# SquirrelForge Execution Planner

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/TASK-DECOMPOSER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, `14_ENGINE/WORKFLOW-SELECTOR.md`, `14_ENGINE/STATE-MANAGER.md`
Used By: Task Router, Coordination, Execution Layer, Validation
Last Updated: 2026-07-04

## Purpose

The Execution Planner converts the validated task graph, dependency analysis, workflow selection, risk context, and permission requirements into an ordered execution plan.

It defines how approved work should proceed safely, efficiently, observably, and recoverably. It does not perform project-changing actions itself.

---

## Responsibilities

The Execution Planner must:

- build the execution sequence,
- preserve task and workflow dependencies,
- identify prerequisite gates,
- define execution boundaries,
- identify required permissions,
- identify tools and interfaces required by each step,
- minimize unnecessary work,
- identify safe parallel work,
- schedule validation checkpoints,
- schedule recovery checkpoints where risk requires them,
- define stop conditions,
- define expected outputs and evidence for each step,
- identify rollback or recovery paths for risky changes,
- and produce a plan ready for routing and controlled execution.

---

## Planning Inputs

The planner should receive:

- structured goal,
- acceptance criteria,
- task graph,
- dependency analysis,
- selected primary workflow,
- approved supporting workflows,
- verified project context,
- active domain context,
- risk assessment,
- permission boundaries,
- available tools and interfaces,
- validation requirements,
- and current state.

Unknown capability or availability must not be treated as confirmed.

---

## Planning Process

1. Receive the task graph and dependency analysis.
2. Confirm the selected workflow and active domain context.
3. Reject or block tasks with unresolved required dependencies.
4. Order tasks by dependency, risk, and lifecycle requirements.
5. Identify safe parallel groups only where dependency analysis permits them.
6. Define the owner type, execution boundary, required permissions, tools, and interfaces for each step.
7. Insert validation checkpoints at useful boundaries.
8. Insert backup, rollback, or recovery checkpoints where risk requires them.
9. Define expected output and required evidence for every step.
10. Define stop, retry, reroute, rollback, and recovery conditions.
11. Review the plan for unnecessary work, hidden scope expansion, unsafe parallelism, and missing validation.
12. Record the plan and pass ready tasks to the Task Router.

---

## Execution Plan Model

| Field | Description |
|---|---|
| Step ID | Stable identifier for the plan step. |
| Sequence | Ordered execution position or parallel group. |
| Task ID | Task being performed. |
| Workflow | Primary or supporting workflow responsible for the step. |
| Owner Type | Agent, execution component, tool boundary, or review owner required. |
| Dependencies | Required prior steps, artifacts, decisions, or conditions. |
| Domain Context | Domain-specific context required by the step, if any. |
| Permissions | Permission boundary required before execution. |
| Tools and Interfaces | Required execution tools or documented interfaces. |
| Expected Output | Artifact, state change, decision, or evidence expected. |
| Validation | Required validation and evidence owner. |
| Checkpoint | Backup, resume, rollback, or recovery checkpoint requirement. |
| Stop Conditions | Conditions that prevent safe continuation. |
| Recovery Path | Required rollback, repair, reroute, or recovery behavior. |
| Status | State compatible with the State Manager. |

---

## Ordering Rules

Execution order must respect:

1. mandatory rules and permissions,
2. safety and security prerequisites,
3. project and environment readiness,
4. task dependencies,
5. workflow requirements,
6. domain requirements,
7. checkpoint requirements,
8. validation dependencies,
9. efficiency opportunities,
10. and reporting requirements.

Efficiency must not override correctness, safety, permissions, validation, or recoverability.

---

## Parallel Execution Rule

Tasks may be placed in a parallel execution group only when the Dependency Analyzer has established that:

- no unresolved ordering dependency exists,
- shared state cannot be mutated unsafely,
- permission requirements do not conflict,
- ownership boundaries are clear,
- tools and interfaces can support concurrent work,
- failure in one task does not invalidate another without a defined coordination rule,
- and validation can distinguish each task's result.

When uncertain, prefer ordered execution.

---

## Validation Checkpoint Rule

Validation should occur at the earliest useful boundary, not only at the end of the full plan.

A checkpoint may validate:

- an artifact,
- a state transition,
- a security control,
- an interface contract,
- a workflow milestone,
- a migration step,
- a deployment stage,
- or a recovery state.

Failed validation must stop dependent steps and return work to the earliest responsible lifecycle phase.

---

## Recovery Planning Rule

Higher-risk steps must define the recovery behavior before execution.

Recovery planning may require:

- backup verification,
- checkpoint creation,
- rollback commands or procedures,
- state restoration requirements,
- retry limits,
- rerouting conditions,
- or escalation to `35_RESILIENCE`.

The plan must not claim a rollback path exists unless the required mechanism is actually available.

---

## Stop Conditions

Execution must stop or pause when:

- a required dependency becomes unavailable,
- a permission check fails,
- a security gate fails,
- validation fails,
- project state changes in a way that invalidates the plan,
- a destructive action exceeds the approved boundary,
- a required tool or interface becomes unavailable,
- or recovery is required before safe continuation.

The stopped state must be recorded without erasing completed evidence.

---

## Resume Rule

Resume from the latest validated, recoverable state when possible.

Before resuming, verify that:

- project state still matches the plan assumptions,
- dependencies remain valid,
- permissions remain valid,
- required tools and interfaces remain available,
- and previous validation evidence is still applicable.

Do not resume blindly from an old checkpoint when the surrounding state has changed.

---

## Domain Rule

Domain-specific steps must include the required domain context.

For WordPress work, relevant `38_WORDPRESS` references, WordPress rules, and applicable WordPress validation must be attached to the affected steps.

Non-WordPress execution plans must not automatically include WordPress-specific steps or checks.

---

## Rule

> Every execution plan must be dependency-correct, permission-aware, validation-driven, and recoverable at the level required by its risk. The planner defines the path; controlled execution components perform the actions.
