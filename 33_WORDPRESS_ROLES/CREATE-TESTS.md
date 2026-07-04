# Skill: Create Tests

## Purpose

This document defines the process for generating test plans, test cases, and testing infrastructure for a WordPress project by orchestrating the specialist roles.

## Core Principle

Testing is a non-negotiable part of the development lifecycle. This skill ensures that for every feature built, a corresponding, verifiable testing strategy is also created, proving correctness and preventing regressions.

---

## Pipeline Execution for Creating Tests

This skill implements the "QA & Testing" stage of the master `38_WORDPRESS/PIPELINE.md` and involves collaboration between multiple roles to ensure comprehensive coverage.

| Stage | Responsible Role(s) | Key Actions for this Skill |
|---|---|---|
| 1. Triage | `Role Manager` | Receive the request to create tests for a feature and assign it to the `QA Engineer`. |
| 2. Test Planning | `QA Engineer` | Analyze the feature's requirements and architecture to create a high-level test plan. |
| 3. Specialist Input | `Security Engineer`, `Performance Engineer` | The `Security Engineer` provides input on security-specific test cases (e.g., permission failures). The `Performance Engineer` provides input on load and stress test cases. |
| 4. Test Case Generation | `QA Engineer` | Generate the detailed test cases, including manual steps and stubs for automated tests. |
| 5. Technical Review | `PHP Engineer`, `JS Engineer`, etc. | The original implementation engineer reviews the test plan for technical feasibility and to identify any missed edge cases. **(GATE)** |
| 6. Finalization | `QA Engineer` | Incorporate feedback and produce the final, approved test plan. |

---

## Agent Rules

1.  **Cover All Angles**: The generated test plan must include cases for success, failure, and security.
2.  **Be Specific**: Manual test steps must be clear, unambiguous, and include a specific expected outcome. Automated test stubs should have descriptive names.
3.  **Automate Where Practical**: The agent should prefer generating stubs for automated unit and integration tests for backend logic over purely manual checklists for the same logic.
4.  **Follow the Standard**: All generated test plans and files must adhere to the formats and requirements defined in `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`.