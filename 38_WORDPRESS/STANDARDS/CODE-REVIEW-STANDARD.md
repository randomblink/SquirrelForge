Status: Stable

---
# SquirrelForge WordPress Code Review Standard

## Purpose

This document defines the standard process and criteria SquirrelForge uses to perform automated and systematic code reviews. The goal is to identify defects, improve code quality, and ensure adherence to all project standards before code is approved.

---

## Core Principles

- **Automate First**: Use automated tools to catch common issues related to standards and security.
- **Be Consistent**: Apply the same review criteria to all code.
- **Focus on Quality**: The review process is a quality gate, not a suggestion box.
- **Provide Actionable Feedback**: All findings must be clear, specific, and include a recommended fix.

---

## Review Areas

Every code review must assess the following areas:

1.  **Security**: Does the code introduce any vulnerabilities? (Reference: `SECURITY.md`)
2.  **Standards Compliance**: Does the code follow all defined coding, architecture, and documentation standards?
3.  **Logic and Correctness**: Does the code do what it's supposed to do? Does it handle edge cases and errors correctly?
4.  **Performance**: Does the code introduce any obvious performance bottlenecks? (Reference: `PERFORMANCE.md`)
5.  **Maintainability**: Is the code clear, readable, and easy to modify in the future?

---

## Automated Tooling

The review process should leverage static analysis tools where possible.

- **PHP_CodeSniffer**: To enforce PHP coding standards (`WordPress-Core`, `WordPress-Docs` rulesets).
- **ESLint/JSHint**: To enforce JavaScript coding standards.
- **Stylelint**: To enforce CSS coding standards.
- **PHPStan/Psalm**: For stricter static analysis to find potential bugs and type errors.

---

## Manual Review Checklist (Automated Agent)

In addition to tooling, the agent must perform a logical review:

- [ ] **Security**:
    - Are all inputs sanitized?
    - Is all output escaped?
    - Are nonces and capability checks present for all privileged actions?
    - Are REST and AJAX endpoints properly secured?
- [ ] **Architecture**:
    - Does the code adhere to the project's architectural standard?
    - Are responsibilities clearly separated?
    - Are there any new circular dependencies?
- [ ] **Logic**:
    - Does the code handle `null`, empty, or invalid inputs?
    - Are there any off-by-one errors or incorrect loop conditions?
    - Is error handling robust?
- [ ] **Readability**:
    - Are variable and function names clear and descriptive?
    - Is complex logic broken down into smaller, understandable methods?

---

## Severity Levels

- **Critical**: A security vulnerability, a fatal error, or a major architectural violation. Must be fixed before approval.
- **Warning**: A standards violation, a likely bug, or a performance issue. Should be fixed before approval.
- **Notice**: A minor issue, a style nitpick, or a suggestion for improvement. Can be addressed later.

---

## Forbidden Patterns

- **Ignoring Validation Results**: Approving code that has failed a security or standards validation check.
- **Introducing Anti-Patterns**: Adding "god classes," global state, or other patterns that violate the architecture standard.
- **Leaving "TODO" Comments**: Code should be complete; "TODO" comments indicate unfinished work.

---

## Agent Rules

1.  **Follow the Pipeline**: The Code Reviewer stage in the `AGENT-PIPELINE.md` must be executed after code generation and validation.
2.  **Use Checklists**: Systematically check the generated code against the security, architecture, and readability checklists.
3.  **Classify Findings**: Assign a severity level to every identified issue.
4.  **Reject Critical Issues**: If any "Critical" issues are found, the pipeline must halt, and the code must be sent back to the Code Generator or Refactoring Advisor for a fix.
5.  **Generate a Report**: The output of a code review is a structured report listing all findings, their locations, severity, and recommended actions.

## Rule

Code review must identify blocking issues, risks, required changes, and final approval status.
