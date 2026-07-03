# Testing Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

This workflow defines the standard process the `AGENT-TESTING` follows to set up a testing environment and write tests for a WordPress project.

### Phase 1 — Setup & Configuration

1.  **Identify Framework:** Determine the appropriate testing framework (e.g., PHPUnit for unit tests, Playwright/Cypress for end-to-end tests).
2.  **Install Dependencies:** Add the necessary development dependencies to `composer.json` or `package.json`.
3.  **Scaffold Configuration:** Generate the necessary configuration files (e.g., `phpunit.xml.dist`, `playwright.config.js`).
4.  **Create Bootstrap File:** For PHPUnit, create the `tests/bootstrap.php` file to load the WordPress environment.

**Deliverable:** A configured and ready-to-use testing environment.

### Phase 2 — Test Planning

1.  **Identify Target:** Determine the specific function, class, or user flow to be tested.
2.  **Define Test Cases:** For the given target, define a set of test cases that cover:
    -   The "happy path" (expected inputs and outputs).
    -   Edge cases (e.g., empty values, incorrect data types).
    -   Error conditions.

**Deliverable:** A list of test cases to be implemented.

### Phase 3 — Test Implementation

1.  **Create Test File:** Create a new test file in the appropriate directory (e.g., `tests/test-my-function.php`).
2.  **Write Tests:** Implement the planned test cases, following the syntax and conventions of the chosen framework.
3.  **Use Assertions:** Ensure each test makes a specific assertion to verify the outcome (e.g., `assertEquals`, `assertTrue`).

**Deliverable:** A new test file containing the implemented test cases.

### Phase 4 — Execution & Reporting

1.  **Run Tests:** Execute the test suite from the command line.
2.  **Report Results:** Provide the output from the test runner, indicating which tests passed or failed.
3.  **Provide Instructions:** Give the user the exact command needed to run the tests themselves.

**Deliverable:** A test execution report and clear instructions for the user.