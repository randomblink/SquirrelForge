# SquirrelForge Goal Planner

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/TASK-DECOMPOSER.md`
Used By: `14_ENGINE/ENGINE-OVERVIEW.md`
Last Updated: 2026-07-01

## Purpose

The Goal Planner is the first stage of the `14_ENGINE` request lifecycle. It converts a raw user request into a structured, actionable goal with clear boundaries and success criteria before any decomposition or execution begins.

## Responsibilities

-   Interpret the user's request to identify the core intent.
-   Define the primary goal and the expected final deliverable.
-   Establish clear scope boundaries (in-scope and out-of-scope).
-   Define explicit, measurable acceptance criteria.
-   Identify initial dependencies, risks, and assumptions.
-   Produce a structured `Goal Definition` record.
-   Request clarification if critical information is missing to form a viable plan.

## Planning Process

1.  **Receive Request:** Ingest a raw user request.
2.  **Analyze Intent:** Identify the primary goal, deliverables, and constraints.
3.  **Define Scope:** Explicitly list what is in-scope and out-of-scope for this request.
4.  **Set Criteria:** Define measurable acceptance criteria that will be used for final validation.
5.  **Assess Preliminaries:** List initial dependencies, known risks, and any assumptions being made.
6.  **Check for Clarity:** If the goal is ambiguous or blocked by missing information, request clarification.
7.  **Emit Definition:** If the goal is clear, produce a structured `Goal Definition` record and pass it to the `Task Decomposer`.

## Goal Definition

| Field | Description |
|---|---|
| **Goal ID** | A unique identifier for this structured goal. |
| **Original Request** | The raw, unmodified user request for traceability. |
| **Primary Goal** | A clear, one-sentence statement of the main objective. |
| **Expected Output** | The final deliverable (e.g., a new plugin file, a report, a modified function). |
| **In Scope** | A list of specific tasks or areas that are part of this goal. |
| **Out of Scope** | A list of related but explicitly excluded tasks. |
| **Acceptance Criteria** | A checklist of measurable conditions that must be met for the goal to be considered complete. |
| **Assumptions** | Any assumptions made during planning that could affect the outcome if incorrect. |
| **Dependencies** | A list of initial high-level dependencies (e.g., a specific tool, access to an API). |
| **Initial Risks** | A list of high-level risks identified at the planning stage. |
| **Validation Needs** | The types of validation required (e.g., Security, Performance, QA). |
| **Status** | `Ready`, `Needs Clarification`, or `Blocked`. |

## Rule

1.  **No Execution Without a Goal:** No task may be decomposed or executed until a `Goal Definition` with a `Ready` status has been produced.
2.  **Clarity Over Assumption:** If the primary goal or acceptance criteria are ambiguous, the Goal Planner **must** request clarification rather than making a high-risk assumption.
3.  **Immutability:** Once passed to the `Task Decomposer`, the `Goal Definition` for a given `Goal ID` should be treated as immutable. Changes require a new planning cycle.