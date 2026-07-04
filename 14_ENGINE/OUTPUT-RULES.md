# SquirrelForge Output Rules

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/VALIDATION.md`, `21_CONFIGURATION/README.md`
Used By: Engine and Agents
Last Updated: 2026-07-01

## Purpose

This document defines the contracts for all final reports produced by an agent. It ensures that every outcome—whether successful, blocked, or failed—is communicated in a clear, consistent, and auditable format.

---

## Responsibilities

-   Define the structure for all final agent reports.
-   Enforce honest reporting of validation status and evidence.
-   Provide distinct reporting contracts for different task outcomes.
-   Ensure that no sensitive or internal-only data is exposed in final reports.

---

## Reporting Contracts

| Report Type | Purpose |
|---|---|
| **Complete Report** | Used when all work is finished and all required validation gates have passed. |
| **Limited Validation Report** | Used when work is finished but some non-critical validation checks were skipped or are unavailable. |
| **Blocked Report** | Used when work cannot proceed due to a missing dependency, required clarification, or external factor. |
| **Failed Report** | Used when work was attempted but could not be completed successfully due to an error or failed validation gate. |
| **Read-Only Report** | Used for tasks that only involve inspection or analysis, with no changes made. |
| **Plan-Only Report** | Used when the output is a plan for future work, not a completed implementation. |

---

## Standard Report Structure

All final reports must contain the following fields, where applicable.

| Field | Description |
|---|---|
| **Outcome** | The final status, matching one of the Reporting Contracts (e.g., `Complete`, `Blocked`). |
| **Summary** | A brief, human-readable summary of what was done and the result. |
| **Artifacts Changed** | A manifest of all files created, modified, or deleted. |
| **Validation Evidence** | A reference to the `Validation Record` from the `14_ENGINE/VALIDATION.md` component. |
| **Missing Checks** | An explicit list of any required validation checks that were not performed. |
| **Unresolved Risks** | A list of any known risks that still apply to the completed work. |
| **Recommended Next Step** | A clear, actionable next step for the user or a subsequent agent. |

---

## Rules

1.  **Outcome First:** Every report must begin with a clear `Outcome` status.
2.  **Evidence Required:** A task cannot be reported as `Complete` without corresponding `Validation Evidence` showing a `Passed` outcome for all required checks.
3.  **Honesty About Gaps:** If any validation checks were skipped or are unavailable, the report `Outcome` must be `Limited Validation`, and the `Missing Checks` field must be populated. An unsupported completion claim is a critical failure.
4.  **No Secrets:** Final reports must not contain secrets, credentials, internal-only data, or excessive diagnostic information.
5.  **Canonical Terminology:** All reports must use the official terminology defined in the project's documentation (e.g., `Skill`, `Role`, `Validation Gate`).
6.  **Clarity for Failure:** `Blocked` or `Failed` reports must clearly state the reason for the failure and provide a `Recommended Next Step` for remediation.
