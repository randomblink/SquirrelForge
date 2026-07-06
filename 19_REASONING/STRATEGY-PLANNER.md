# SquirrelForge Strategy Planner

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `19_REASONING/DECISION-ENGINE.md`, `14_ENGINE/WORKFLOW-SELECTOR.md`, `14_ENGINE/TASK-DECOMPOSER.md`, `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/STATE-MANAGER.md`
Used By: Explanation Engine, Task Decomposer
Last Updated: 2026-07-06

## Purpose

The Strategy Planner converts the Decision Engine's selected option into a high-level executable strategy — objective, strategic phases, proposed milestones, and expected outcome — that aligns with project goals and long-term maintainability, and hands that strategy downstream for detailed execution planning.

The Strategy Planner defines strategic direction; it does not perform the detailed task decomposition, dependency ordering, or validation-checkpoint scheduling that `14_ENGINE/TASK-DECOMPOSER.md` and `14_ENGINE/EXECUTION-PLANNER.md` already own. It does not select the workflow itself — that is already selected by `14_ENGINE/WORKFLOW-SELECTOR.md` before the Decision Engine runs — and it does not assign agents to tasks (owned by `14_ENGINE/TASK-ROUTER.md`). A proposed milestone becomes authoritative only once `14_ENGINE/STATE-MANAGER.md` records it for tracking.

---

## Responsibilities

The Strategy Planner must:

- receive the Decision Engine's selected option and Decision Record,
- define the strategic objective and expected outcome,
- propose high-level implementation phases aligned with the already-selected workflow,
- propose milestones for `14_ENGINE/STATE-MANAGER.md` to track once execution begins,
- note where validation is strategically required, without scheduling the specific checkpoints (owned by `14_ENGINE/EXECUTION-PLANNER.md`),
- produce a Strategy Record,
- and forward the strategy to `19_REASONING/EXPLANATION-ENGINE.md`, and onward to `14_ENGINE/TASK-DECOMPOSER.md` for detailed execution planning.

---

## Strategy Planning Process

1. Receive the approved Decision Record from `19_REASONING/DECISION-ENGINE.md`.
2. Define the strategic objective and expected outcome.
3. Propose high-level implementation phases.
4. Propose milestones.
5. Note where validation is strategically required.
6. Produce the Strategy Record.
7. Forward the strategy to `19_REASONING/EXPLANATION-ENGINE.md`.

---

## Strategy Model

| Field | Description | Authoritative Owner |
|---|---|---|
| Strategy ID | Unique identifier. | Strategy Planner |
| Objective | Primary goal. | Strategy Planner |
| Phases | Proposed high-level implementation phases. | Strategy Planner |
| Workflow | Reference to the already-selected workflow. | `14_ENGINE/WORKFLOW-SELECTOR.md` |
| Proposed Milestones | Candidate milestones for tracking. | Strategy Planner proposes; `14_ENGINE/STATE-MANAGER.md` tracks once execution begins |
| Validation Notes | Where validation is strategically required. | Strategy Planner notes; `14_ENGINE/EXECUTION-PLANNER.md` schedules the actual checkpoints |
| Expected Outcome | Final deliverable. | Strategy Planner |

Agent assignment is not a Strategy field; agents are assigned to tasks by `14_ENGINE/TASK-ROUTER.md` during execution, not decided at strategy time.

---

## Planning Principles

- Build incrementally.
- Validate early and often.
- Minimize unnecessary dependencies.
- Preserve architectural consistency.
- Support future extensibility.
- Prefer reusable components.

---

## Permission Boundary

The Strategy Planner may define the strategic objective, propose phases and milestones, and note where validation is strategically needed.

It must not perform detailed task decomposition or dependency ordering (owned by `14_ENGINE/TASK-DECOMPOSER.md`), schedule specific validation or recovery checkpoints (owned by `14_ENGINE/EXECUTION-PLANNER.md`), select the workflow (owned by `14_ENGINE/WORKFLOW-SELECTOR.md`), or assign agents to tasks (owned by `14_ENGINE/TASK-ROUTER.md`).

---

## Domain Rule

Strategic planning applies identically regardless of domain; domain-specific content is carried in the objective and phases, not interpreted by the Strategy Planner itself.

---

## Rule

> Every approved decision must be converted into a documented strategic direction before detailed execution planning begins. The Strategy Planner defines strategic intent; it does not perform detailed task decomposition, checkpoint scheduling, workflow selection, or agent assignment — those remain owned by the Engine layer.
