# Skill: Create Tests

## Purpose

This document defines the end-to-end process SquirrelForge must follow to generate test plans, test cases, and testing infrastructure for a WordPress project.

## Core Principle

Testing is a non-negotiable part of the development lifecycle. This skill ensures that for every feature built, a corresponding, verifiable testing strategy is also created, proving correctness and preventing regressions.

---

## Required Inputs

- A reference to the code or feature to be tested.
- The type of testing required (e.g., unit, integration, E2E, manual checklist).

## Expected Outputs

- A `TESTING.md` file with a manual testing checklist.
- Scaffolding for automated tests (e.g., PHPUnit test files with placeholder test methods).
- Configuration files for the testing framework (e.g., `phpunit.xml.dist`).

---

## Workflow

This skill is typically executed as the "Testing Plan" stage of the master `PIPELINE.md`.

1.  **Intent Analysis**:
    -   Deconstruct the request: `Task: create_tests, Target: My_Plugin_Settings_Class, Type: unit`.

2.  **Knowledge Selection**:
    -   The `Knowledge Manager` selects `TESTING-STANDARD.md` and the relevant coding standards.

3.  **Code Analysis**:
    -   Scan the target code to identify public methods, user-facing UI, and integration points (hooks, REST endpoints).

4.  **Test Case Generation**:
    -   Based on the analysis, generate a list of test cases covering:
        -   **Happy Path**: Expected, successful usage.
        -   **Error Conditions**: How the code handles invalid input or failures.
        -   **Security**: Tests for authorization and permission failures.
        -   **Edge Cases**: Tests with empty, null, or boundary values.

5.  **Test Scaffolding & Planning**:
    -   **Manual Plan**: Generate a `TESTING.md` file with a human-readable checklist based on the generated test cases.
    -   **Automated Scaffolding**: If automated tests are requested, generate the necessary configuration files and test files with empty methods for each test case.

6.  **Validation**:
    -   The `Standards Validator` checks that the generated test files and plans adhere to the `TESTING-STANDARD.md`.

7.  **Final Report**:
    -   The final task report is updated to include the generated testing plan and instructions on how to execute the tests.

---

## Agent Rules

1.  **Cover All Angles**: The generated test plan must include cases for success, failure, and security.
2.  **Be Specific**: Manual test steps must be clear, unambiguous, and include a specific expected outcome.
3.  **Automate Where Practical**: The agent should prefer generating stubs for automated unit and integration tests for backend logic over purely manual checklists.
4.  **Follow the Standard**: All generated test plans and files must adhere to the formats and requirements defined in `TESTING-STANDARD.md`.