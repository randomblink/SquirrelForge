Status: Stable

---
# Skill: Debug Plugin

## Purpose

This document defines the systematic workflow SquirrelForge must follow to diagnose and fix bugs in WordPress plugins. This includes fatal errors, white screens, hook conflicts, API failures, database problems, and performance bottlenecks.

## Core Principle

Debugging is a process of elimination. The workflow starts by gathering evidence and forming a hypothesis, then systematically testing that hypothesis to isolate the root cause before implementing a safe and verified fix.

---

## Required Inputs

- A clear description of the bug's symptoms (e.g., "white screen on activation," "settings not saving").
- Reliable steps to reproduce the issue.
- Access to the plugin's source code and relevant environment details (error logs, WP version, PHP version).

## Expected Outputs

- A root cause analysis explaining the bug.
- A proposed code change to fix the bug.
- A final report detailing the diagnosis, the fix, and the verification steps.

---

## Workflow

1.  **Intent Analysis & Information Gathering**:
    -   Deconstruct the request: `Task: debug_plugin, Symptom: fatal_error, Trigger: activation`.
    -   Enable `WP_DEBUG` and `WP_DEBUG_LOG`.
    -   Review PHP error logs, the browser console, and network request logs for initial clues.

2.  **Replication & Isolation**:
    -   Follow the steps to reproduce the bug in a clean environment.
    -   Systematically disable other plugins and switch to a default theme to confirm the issue is within the target plugin and not a conflict.

3.  **Hypothesis & Code Analysis**:
    -   Based on the evidence, form a specific hypothesis (e.g., "Hypothesis: `register_activation_hook` callback is calling a non-existent function").
    -   Analyze the code relevant to the hypothesis. Use static analysis and trace the execution path.

4.  **Root Cause Identification**:
    -   Use targeted debugging (e.g., adding `error_log()` statements, using Xdebug) to pinpoint the exact line(s) of code causing the failure.

5.  **Fix Implementation**:
    -   The `Code Generator` applies a minimal, targeted fix for the identified root cause.
    -   The fix must still adhere to all relevant `STANDARDS` documents.

6.  **Verification & Regression Testing**:
    -   Follow the original steps to confirm the bug is resolved.
    -   Execute the project's test suite (or a relevant subset) to ensure the fix has not introduced any new problems (regressions).

7.  **Documentation & Reporting**:
    -   Update the `CHANGELOG.md` with a "Fixed" entry for the bug.
    -   Generate a final report detailing the symptoms, root cause, the fix applied, and verification results.

---

## Common Debugging Strategies

| Symptom | Initial Strategy |
|---|---|
| **Fatal Error / White Screen** | Check PHP error logs. Enable `WP_DEBUG_LOG`. |
| **Hook/Filter Issue** | Use a debugging plugin (e.g., Query Monitor) to inspect hook priorities and callbacks. |
| **REST/AJAX Failure** | Check the browser's Network tab for the response status code and JSON body. |
| **Database Error** | Enable `SAVEQUERIES` and inspect the list of SQL queries for errors or inefficiencies. |
| **Performance Bottleneck** | Use a profiler like Query Monitor or Xdebug to identify slow functions and queries. |
| **Compatibility Conflict** | Perform a conflict test by disabling other plugins/themes one by one. |

---

## Agent Rules

1.  **Evidence First**: The agent must not guess. It must gather evidence from logs and reproduction steps before forming a hypothesis.
2.  **Isolate the Problem**: The agent must always recommend or perform a conflict test to rule out external factors.
3.  **Fix the Cause, Not the Symptom**: The implemented fix must address the root cause of the bug, not just suppress the error message.
4.  **Safety is Paramount**: Any fix must pass the same security and standards validation as new code.
5.  **Verify the Fix**: The agent must define a clear set of steps to verify that the bug is gone and that no new bugs have been introduced.
