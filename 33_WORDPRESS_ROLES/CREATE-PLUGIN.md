Status: Stable

---
# Skill: Create Plugin

## Purpose

This document defines the process for creating a new, production-ready WordPress plugin from a user request by orchestrating the specialist roles according to the master `PIPELINE.md`.

## Core Principle

Creating a plugin is a systematic engineering task that proceeds from high-level requirements to a fully validated, documented, and tested implementation. Every step must pass its respective quality gate before the next can begin.

---

## Pipeline Execution for Creating a Plugin

This skill implements the master `38_WORDPRESS/PIPELINE.md` by assigning each stage to the appropriate specialist role.

| Stage | Responsible Role(s) | Key Actions for this Skill |
|---|---|---|
| 1. Intent Analysis | `Role Manager` | Deconstruct the user request into a structured task. |
| 2. Architecture Planning | `Project Architect` | Define the project's purpose, scope, and requirements. Output: `Project Architecture Plan`. |
| 3. Implementation Planning | `Plugin Architect` | Design the detailed plugin file structure, classes, hooks, and data model. Output: `Plugin Architecture Specification`. |
| 4. Role & Task Routing | `Role Manager` | Consult the `ROLE-ROUTING-MATRIX.md` to produce the `WordPress Role Routing Decision` for the plugin. |
| 5. Code Generation | `PHP Engineer`, `JS Engineer`, `CSS Engineer`, etc. | Write the PHP, JavaScript, and CSS code as defined in the specification and routing decision. |
| 6. Security Validation | `Security Engineer` | Audit the generated code for vulnerabilities. **(GATE)** |
| 7. Performance Validation | `Performance Engineer` | Profile and analyze performance-sensitive areas if identified by the architect. **(GATE)** |
| 8. QA & Testing | `QA Engineer` | Execute a test plan to verify all functional and non-functional requirements. **(GATE)** |
| 9. Documentation | `Documentation Engineer` | Generate `README.md`, `readme.txt`, `CHANGELOG.md`, and PHPDoc blocks. |
| 10. Release Preparation | `Release Engineer` | Verify all gates passed, version the plugin, and package the final ZIP file. **(FINAL GATE)** |

---

## Agent Rules

1.  **Follow the Pipeline**: This skill must be executed by following the `PIPELINE.md` stages in order, without skipping any validation or review steps.
2.  **Gate Enforcement**: A `Fail` status from any validation role (`Security`, `Performance`, `QA`) or a `Blocked` status from the `Release Engineer` must halt the process and return the work to the appropriate implementation role for remediation.
3.  **Traceability**: The final report must include the pass/fail status of each validation gate, providing a clear audit trail of the quality assurance process.
## Rule

A WordPress plugin must be created through approved architecture, implementation, security, QA, documentation, and release gates before it is considered complete.
