# SquirrelForge Task Router

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/STATE-MANAGER.md`, `16_AGENTS/AGENT-SPECIALIZATION.md`
Used By: Coordination
Last Updated: 2026-07-01

Routes each ready task to the workflow and agent capable of satisfying its requirements. Routing must honor dependencies, permissions, ownership, capacity, and task priority. It must also emit a traceable routing record and handle rerouting when conditions change.

---

## Responsibilities

-   Receive a task from the `State Manager` that is ready for execution.
-   Resolve the best-fit agent and workflow for the task.
-   Verify agent capabilities, specialization, and availability.
-   Enforce task dependencies and priority order.
-   Assign task ownership to a specific agent.
-   Notify the `State Manager` to transition the task to `In Progress` once routed.
-   Handle rerouting requests when a task is blocked or requires different skills.

---

## Routing Inputs

-   **Task Definition:** The specific goal, requirements, and completion criteria.
-   **Task Dependencies:** A list of other tasks that must be completed first.
-   **Task Priority:** The urgency of the task relative to others.
-   **Required Capabilities:** The skills or specializations needed, as defined in `16_AGENTS/AGENT-SPECIALIZATION.md`.
-   **Agent Availability:** The current status and capacity of candidate agents.
-   **Workflow Catalog:** The list of available, validated workflows.

---

## Routing Process

1.  **Receive Task:** Ingest a task from the `State Manager` with a status of `Not Started` or `Pending Rerouting`.
2.  **Analyze Requirements:** Identify the core capabilities and constraints from the task definition.
3.  **Filter Candidates:** Select agents and workflows that match the required capabilities.
4.  **Evaluate Fitness:** Score candidates based on specialization, priority, and capacity.
5.  **Select Best Fit:** Choose the highest-scoring agent and workflow.
6.  **Assign Ownership:** Formally assign the task to the selected agent.
7.  **Emit Record:** Publish a `Routing Decision` record.
8.  **Update State:** Notify the `State Manager` that the task has been routed, triggering a transition to `In Progress`.

---

## Ownership and Rerouting

-   **Ownership:** A task is owned by exactly one agent at a time. Ownership is not transferred until a formal handoff or rerouting event occurs.
-   **Rerouting Conditions:** A task must be returned to the Task Router for rerouting if:
    -   The assigned agent reports it cannot complete the task.
    -   A validation gate fails and requires a different skill set to remediate.
    -   The task is explicitly escalated or delegated.

---

## Rules

1.  **State-Driven:** The Task Router must only act on tasks provided by the `State Manager`.
2.  **Specialization Enforcement:** A task must only be routed to an agent whose registered specializations match the task's requirements.
3.  **Dependency Enforcement:** A task must not be routed for execution until all of its declared dependencies are `Complete`.
4.  **Traceability:** Every routing decision, including reroutes, must produce an immutable routing record.
