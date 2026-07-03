# Skill: Migrate Plugin

## Purpose

This document defines the systematic workflow SquirrelForge must follow to perform a data or architectural migration for a WordPress plugin. This includes upgrading database schemas, changing option structures, or moving from one storage format to another.

## Core Principle

Migrations are high-risk operations that must prioritize data integrity, backward compatibility, and reversibility. The process must be idempotent, testable, and safe to run in any environment.

---

## Required Inputs

- The plugin's source code.
- A clear definition of the "before" and "after" state for the data or architecture.
- The specific trigger for the migration (e.g., "on plugin version update").

## Expected Outputs

- The PHP code required to perform the migration.
- A testing plan to verify the migration's success and data integrity.
- A rollback plan in case of failure.
- A final report detailing the migration process.

---

## Workflow

1.  **Intent Analysis & Planning**:
    -   Deconstruct the request: `Task: migrate_plugin, From: v1_options_array, To: v2_cpt_structure`.
    -   Define the migration trigger (e.g., checking a stored version number against the plugin's current version constant).

2.  **Knowledge Selection**:
    -   The `Knowledge Manager` selects `DATABASE.md`, `PLUGIN-HANDBOOK.md`, `SECURITY.md`, and `REFACTORING-STANDARD.md`.

3.  **Migration Path Design**:
    -   Design the step-by-step process for the migration. For data migrations, this includes:
        -   Reading data from the old format.
        -   Transforming it to the new format.
        -   Writing it to the new location.
        -   Verifying the write.
        -   Safely cleaning up the old data (or marking it as migrated).

4.  **Code Generation**:
    -   Generate the migration function(s). The migration logic should be idempotent (safe to run multiple times without causing issues).
    -   Generate the trigger logic (e.g., an `admin_init` hook that checks a version option and runs the migration if needed).

5.  **Validation & Review**:
    -   The `Security Validator` and `Code Reviewer` check the migration code for security flaws, performance bottlenecks (especially with large datasets), and logical errors.
    -   **Gate**: Migrations that perform unbounded queries or could lead to data loss must be blocked.

6.  **Testing Plan**:
    -   The `Testing Planner` generates a detailed testing plan, including:
        -   Tests with no data.
        -   Tests with a small amount of data.
        -   Tests with a large amount of data (if applicable).
        -   Tests to verify that running the migration twice does not cause errors.

7.  **Rollback Plan**:
    -   Define the manual or automated steps required to revert the migration in case of a critical failure.

8.  **Documentation & Reporting**:
    -   Update the `CHANGELOG.md` and `README.md` to note the data migration.
    -   Generate a final report detailing the migration process, testing plan, and rollback procedure.

---

## Agent Rules

1.  **Prioritize Data Integrity**: The agent must never generate a migration that deletes old data until it has been successfully and verifiably moved to the new location.
2.  **Be Idempotent**: The migration logic must be wrapped in checks (e.g., `if ( get_option('my_plugin_version') < '2.0.0' )`) to prevent it from running more than once.
3.  **Work in Batches**: For large datasets, the agent must design the migration to run in smaller, time-limited batches to avoid server timeouts. This can be done using the Action Scheduler or custom cron tasks.
4.  **Plan for Failure**: The agent must assume the migration could fail halfway through and design it to be resumable or safely reversible.