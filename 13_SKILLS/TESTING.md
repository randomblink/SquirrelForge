# Skill: Testing

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To assist in creating and running tests for a WordPress project, including setting up testing frameworks (like PHPUnit) and generating test cases for specific features.

### 2. When to Use
Use this skill for requests related to software testing.
-   "Write a unit test for this function."
-   "Help me set up PHPUnit for my plugin."
-   "Create a test case for this bug."

### 3. Inputs
-   The full path to the project codebase.
-   The specific function, class, or feature to be tested.

### 4. Workflow
This skill executes the `02_WORKFLOWS/TESTING-WORKFLOW.md`. It involves scaffolding test files, configuring the testing environment, and writing sample tests.

### 5. Expected Outputs
-   A configured testing framework (e.g., `phpunit.xml.dist`, `tests/bootstrap.php`).
-   New test files with example test cases (e.g., `tests/test-sample.php`).
-   Clear instructions on how to run the tests from the command line.

### 6. Quality Checklist (Definition of Done)
-   [ ] The testing framework is correctly configured.
-   [ ] The generated test cases are syntactically correct.
-   [ ] The test cases accurately target the specified code.
-   [ ] The instructions for running the tests are clear and correct.

### 7. Related Skills
-   `Bug Fixing` (writing tests to reproduce and verify fixes)
-   `Plugin Developer` (integrating tests into the development process)

### 8. References
-   `02_WORKFLOWS/TESTING-WORKFLOW.md`
-   PHPUnit Documentation
-   WordPress Core Test Handbook