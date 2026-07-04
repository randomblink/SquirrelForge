# Agent Engine: Validation

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/STATE-MANAGER.md`
Used By: `14_ENGINE/ENGINE-OVERVIEW.md`
Last Updated: 2026-07-04

## Purpose

The Validation component acts as a central coordinator for all validation activities. It does not perform validation itself but ensures that every task provides sufficient evidence to prove it has met all functional, quality, and security requirements before being marked `Complete` by the `State Manager`.

---

## Responsibilities

-   Receive a task that is pending validation.
-   Load the correct validation checklist based on the task's domain.
-   Verify that the task's output includes an evidence package.
-   Confirm that evidence exists for every required check on the checklist.
-   Enforce that all non-negotiable checks (e.g., security) have passing evidence.
-   Produce a formal `Validation Record` detailing the outcome.
-   Return a `Passed` or `Failed` status to the `State Manager`.

---

## Validation Flow

1.  **Receive Task:** Ingest a task from the `State Manager` with a status of `Pending Validation`.
2.  **Load Checklist:** Identify the task's domain (e.g., `Core`, `WordPress`) and load the appropriate validation checklist. For example, a WordPress task would load the checklist from `38_WORDPRESS/STANDARDS/VALIDATION.md`.
3.  **Verify Evidence:** For each item on the checklist, confirm that the task's output contains corresponding evidence (e.g., a test report, a linter result, a security scan log).
4.  **Assess Outcome:** Determine the final status (`Passed` or `Failed`) based on the completeness and success of the provided evidence.
5.  **Generate Record:** Create an immutable `Validation Record` that documents the checklist used, the evidence reviewed, and the final outcome.
6.  **Report Status:** Send the final status to the `State Manager` to transition the task to `Complete` or back to `In Progress` for remediation.

---

## Validation Record

| Field | Description |
|---|---|
| Validation ID | Unique identifier for this validation event. |
| Task ID | The task being validated. |
| Checklist Used | Reference to the domain-specific checklist that was applied. |
| Evidence Summary | A list of evidence files or records that were reviewed. |
| Outcome | `Passed` or `Failed`. |
| Failures | A list of specific checks that failed, if any. |
| Timestamp | When the validation was performed. |

---

## Rule

1.  **No Evidence, No Completion:** A task cannot be marked `Complete` without a corresponding `Validation Record` showing a `Passed` outcome.
2.  **Evidence-Based:** The Validation component **must not** perform checks itself. Its sole responsibility is to verify the existence and status of evidence provided by other components (e.g., `Security Validator`, `Testing Planner`).
3.  **Domain-Specific Checklists:** Core validation must remain generic. All domain-specific requirements (e.g., WordPress coding standards) **must** be defined in a domain-specific checklist and loaded on demand.
4.  **Traceability:** Every validation attempt, whether it passes or fails, must produce an immutable `Validation Record`.