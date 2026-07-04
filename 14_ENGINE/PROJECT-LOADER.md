# Agent Engine: Project Loader

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `00_CORE/SYSTEM-ORCHESTRATOR.md`, `21_CONFIGURATION/README.md`
Used By: `14_ENGINE/ENGINE-OVERVIEW.md`
Last Updated: 2026-07-04

## Purpose

The Project Loader is the first component in the `14_ENGINE` execution flow. It is responsible for verifying the project environment, loading core configuration and rules, and initializing the essential managers required to begin processing a user request. It ensures that the system starts from a known, valid state.

---

## Responsibilities

-   Verify the project's root directory and structural integrity.
-   Load and validate the project's configuration (`21_CONFIGURATION`).
-   Load core system rules (`01_RULES/AGENT-BEHAVIOR.md`).
-   Initialize core engine managers (`State Manager`, `Context Manager`).
-   Load the catalog of available workflows (`02_WORKFLOWS`).
-   Produce a "Project Ready" state or a "Project Invalid" error.

---

## Loading Process

The Project Loader follows a strict, fail-fast sequence. A failure at any step prevents the project from being marked as ready.

1.  **Verify Project Root:** Confirm the existence of a valid project structure.
2.  **Load Configuration:** Load the project's configuration files and environment variables.
3.  **Load Core Rules:** Load foundational rules that govern all agent behavior.
4.  **Initialize State Manager:** Create an instance of the `State Manager` to track the lifecycle.
5.  **Initialize Context Manager:** Create an instance of the `Context Manager` to manage working memory.
6.  **Load Workflow Catalog:** Scan and register all available workflows.
7.  **Confirm Readiness:** If all steps succeed, emit a `Project Ready` status. If any step fails, emit a `Project Invalid` status with a descriptive error.

---

## Readiness Checklist

A project is only considered `Ready` after all startup checklist items have been completed successfully. This checklist represents the final output of the loader.

| Check | Status | Notes |
|---|---|---|
| Project Root Verified | `Pass` / `Fail` | The root directory is valid and accessible. |
| Configuration Loaded | `Pass` / `Fail` | Configuration is parsed and validated. |
| Core Rules Loaded | `Pass` / `Fail` | `AGENT-BEHAVIOR.md` is loaded into initial context. |
| State Manager Initialized | `Pass` / `Fail` | The manager is ready to track state. |
| Context Manager Initialized | `Pass` / `Fail` | The manager is ready to build context. |
| Workflow Catalog Loaded | `Pass` / `Fail` | At least one valid workflow is registered. |

---

## Output

The Project Loader produces a simple status object that gates further execution.

| Field | Description |
|---|---|
| `status` | `Ready` or `Invalid`. |
| `project_root` | The absolute path to the project's root directory. |
| `error_message` | A descriptive message if the status is `Invalid`. |
| `workflow_catalog` | A list of registered and validated workflow identifiers. |

---

## Rules

1.  **Fail-Fast:** The loading process must halt immediately upon the first failure.
2.  **Idempotency:** Executing the Project Loader multiple times on an unchanged project must produce the identical readiness state.
3.  **No Task-Specific Logic:** The Project Loader must not contain logic specific to any single task. Its responsibility is limited to preparing the general project environment.
4.  **Configuration First:** Configuration must be loaded before any component that depends on it, such as the `Context Manager` or `State Manager`.